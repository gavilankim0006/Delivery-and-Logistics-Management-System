<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Package_workflow_model extends Model
{
    private function dbx() { return $this->db ?? lava_instance()->db; }

    public function eligible_orders(int $customerId): array
    {
        $stmt = $this->dbx()->raw("SELECT id, order_no, created_at, status FROM orders WHERE customer_id=? AND deleted_at IS NULL AND status IN ('paid','booking','rider_assigned','picked_up') ORDER BY id DESC", [$customerId]);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function search_customers(int $excludeId, string $term): array
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
        $stmt = $this->dbx()->raw("SELECT c.id, u.name, c.company_name, u.email FROM customers c
            JOIN users u ON u.id=c.user_id JOIN roles r ON r.id=u.role_id
            WHERE c.id<>? AND u.status='active' AND u.deleted_at IS NULL AND r.name='customer'
            AND (u.name LIKE ? OR c.company_name LIKE ? OR u.email LIKE ?) ORDER BY u.name LIMIT 20", [$excludeId, $like, $like, $like]);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function create_report(int $orderId, int $customerId, string $description): bool
    {
        $owned = $this->dbx()->raw("SELECT id FROM orders WHERE id=? AND customer_id=? AND deleted_at IS NULL AND status NOT IN ('draft','cancelled')", [$orderId,$customerId])->fetch(PDO::FETCH_ASSOC);
        if (!$owned) return false;
        return (bool)$this->dbx()->raw("INSERT INTO missing_package_reports (order_id,customer_id,description) VALUES (?,?,?)", [$orderId,$customerId,$description]);
    }

    public function list_reports(): array
    {
        $s=$this->dbx()->raw("SELECT m.*,o.order_no,u.name AS customer_name,u.email FROM missing_package_reports m JOIN orders o ON o.id=m.order_id JOIN customers c ON c.id=m.customer_id JOIN users u ON u.id=c.user_id ORDER BY m.created_at DESC");
        return $s ? $s->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function update_report(int $id,string $status,string $note,int $admin): bool
    { return (bool)$this->dbx()->raw('UPDATE missing_package_reports SET status=?,admin_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?',[$status,$note,$admin,$id]); }

    public function create_handoff(int $orderId, int $sender, int $recipient): ?array
    {
        if ($sender === $recipient) return null;
        $db = $this->dbx();
        $db->transaction();
        try {
            $owned = $db->raw("SELECT id FROM orders WHERE id=? AND customer_id=? AND deleted_at IS NULL AND status IN ('paid','booking','rider_assigned','picked_up') FOR UPDATE", [$orderId, $sender])->fetch(PDO::FETCH_ASSOC);
            $target = $db->raw("SELECT c.id FROM customers c JOIN users u ON u.id=c.user_id JOIN roles r ON r.id=u.role_id WHERE c.id=? AND u.status='active' AND u.deleted_at IS NULL AND r.name='customer'", [$recipient])->fetch(PDO::FETCH_ASSOC);
            if (!$owned || !$target) {
                $db->roll_back();
                return null;
            }
            $db->raw("UPDATE package_handoffs SET status='revoked' WHERE order_id=? AND sender_customer_id=? AND status='pending'", [$orderId, $sender]);
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $db->raw("INSERT INTO package_handoffs (order_id, sender_customer_id, recipient_customer_id, token_hash, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))", [$orderId, $sender, $recipient, $hash]);
            $newId = (int)$db->last_id();
            $db->commit();
            return [
                'token' => $token,
                'handoff_id' => $newId,
                'order_id' => $orderId,
            ];
        } catch (Throwable $e) {
            $db->roll_back();
            throw $e;
        }
    }

    public function conversations(int $customerId): array
    {
        $s = $this->dbx()->raw("SELECT h.id, h.order_id, h.sender_customer_id, h.recipient_customer_id,
            h.status, h.expires_at, h.created_at, h.confirmed_at,
            o.order_no, o.status AS order_status, da.address_line AS destination_address,
            us.name AS sender_name, ur.name AS recipient_name,
            cs.company_name AS sender_company, cr.company_name AS recipient_company,
            m.body AS latest_message, m.created_at AS latest_message_at,
            (SELECT COUNT(*) FROM package_handoff_messages unread
             WHERE unread.handoff_id=h.id AND unread.sender_customer_id<>?
             AND unread.id>CASE WHEN ?=h.sender_customer_id THEN h.sender_read_message_id ELSE h.recipient_read_message_id END) AS unread_count
            FROM package_handoffs h
            JOIN orders o ON o.id=h.order_id
            LEFT JOIN addresses da ON da.id=o.dropoff_address_id
            JOIN customers cs ON cs.id=h.sender_customer_id
            JOIN users us ON us.id=cs.user_id
            JOIN customers cr ON cr.id=h.recipient_customer_id
            JOIN users ur ON ur.id=cr.user_id
            LEFT JOIN package_handoff_messages m ON m.id=(SELECT MAX(lastmsg.id) FROM package_handoff_messages lastmsg WHERE lastmsg.handoff_id=h.id)
            WHERE h.sender_customer_id=? OR h.recipient_customer_id=?
            ORDER BY COALESCE(m.created_at, h.created_at) DESC",
            [$customerId, $customerId, $customerId, $customerId]);
        return $s ? $s->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function conversation(int $handoffId, int $customerId): ?array
    {
        $s = $this->dbx()->raw("SELECT h.*, o.order_no, o.status AS order_status, da.address_line AS destination_address,
            us.name AS sender_name, ur.name AS recipient_name,
            cs.company_name AS sender_company, cr.company_name AS recipient_company
            FROM package_handoffs h
            JOIN orders o ON o.id=h.order_id
            LEFT JOIN addresses da ON da.id=o.dropoff_address_id
            JOIN customers cs ON cs.id=h.sender_customer_id
            JOIN users us ON us.id=cs.user_id
            JOIN customers cr ON cr.id=h.recipient_customer_id
            JOIN users ur ON ur.id=cr.user_id
            WHERE h.id=? AND (h.sender_customer_id=? OR h.recipient_customer_id=?)",
            [$handoffId, $customerId, $customerId]);
        $row = $s ? $s->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    public function find_by_token(string $token, int $customerId): ?array
    {
        $hash = hash('sha256', $token);
        $s = $this->dbx()->raw("SELECT h.*, o.order_no, o.status AS order_status, da.address_line AS destination_address,
            us.name AS sender_name, ur.name AS recipient_name
            FROM package_handoffs h
            JOIN orders o ON o.id=h.order_id
            LEFT JOIN addresses da ON da.id=o.dropoff_address_id
            JOIN customers cs ON cs.id=h.sender_customer_id
            JOIN users us ON us.id=cs.user_id
            JOIN customers cr ON cr.id=h.recipient_customer_id
            JOIN users ur ON ur.id=cr.user_id
            WHERE h.token_hash=? AND h.recipient_customer_id=?", [$hash, $customerId]);
        $row = $s ? $s->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    public function messages(int $handoffId, int $customerId): array
    {
        if (!$this->conversation($handoffId, $customerId)) return [];
        $s = $this->dbx()->raw('SELECT m.*, u.name AS sender_name FROM package_handoff_messages m
            JOIN customers c ON c.id=m.sender_customer_id
            JOIN users u ON u.id=c.user_id
            WHERE m.handoff_id=? ORDER BY m.id DESC LIMIT 100', [$handoffId]);
        return $s ? array_reverse($s->fetchAll(PDO::FETCH_ASSOC)) : [];
    }

    public function mark_read(int $handoffId, int $customerId): bool
    {
        $h = $this->conversation($handoffId, $customerId);
        if (!$h) return false;
        $col = (int)$h['sender_customer_id'] === $customerId ? 'sender_read_message_id' : 'recipient_read_message_id';
        $latest = $this->dbx()->raw('SELECT COALESCE(MAX(id),0) AS id FROM package_handoff_messages WHERE handoff_id=?', [$handoffId])->fetch(PDO::FETCH_ASSOC);
        return (bool)$this->dbx()->raw("UPDATE package_handoffs SET {$col}=? WHERE id=?", [(int)($latest['id'] ?? 0), $handoffId]);
    }

    public function send_message(int $handoffId, int $customerId, string $body): ?array
    {
        $h = $this->conversation($handoffId, $customerId);
        if (!$h || $h['status'] !== 'pending' || strtotime($h['expires_at']) <= time() || trim($body) === '' || strlen($body) > 4000) {
            return null;
        }
        $allowed = (int)$h['sender_customer_id'] === $customerId || (int)$h['recipient_customer_id'] === $customerId;
        if (!$allowed) return null;

        $db = $this->dbx();
        $ins = $db->raw("INSERT INTO package_handoff_messages (handoff_id, sender_customer_id, body, created_at) VALUES (?, ?, ?, NOW())", [$handoffId, $customerId, $body]);
        if (!$ins) return null;

        $msgId = (int)$db->last_id();
        $this->mark_read($handoffId, $customerId);

        $s = $db->raw("SELECT m.*, u.name AS sender_name FROM package_handoff_messages m JOIN customers c ON c.id=m.sender_customer_id JOIN users u ON u.id=c.user_id WHERE m.id=?", [$msgId]);
        $row = $s ? $s->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: [
            'id' => $msgId,
            'handoff_id' => $handoffId,
            'sender_customer_id' => $customerId,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function confirm_handoff(int $handoffId, int $recipient): bool
    {
        return (bool)$this->dbx()->raw("UPDATE package_handoffs SET status='confirmed', confirmed_at=NOW() WHERE id=? AND recipient_customer_id=? AND status='pending' AND expires_at>NOW()", [$handoffId, $recipient]);
    }

    public function confirm(string $token, int $recipient): bool
    {
        return (bool)$this->dbx()->raw("UPDATE package_handoffs SET status='confirmed', confirmed_at=NOW() WHERE token_hash=? AND recipient_customer_id=? AND status='pending' AND expires_at>NOW()", [hash('sha256', $token), $recipient]);
    }
}
