<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class DeliveryControlService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function issue(int $orderId, int $customerId): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 7 * 86400);
        $now = date('Y-m-d H:i:s');
        $this->db->transaction();
        try {
            $order = $this->db->raw('SELECT id, status FROM orders WHERE id = ? AND customer_id = ? FOR UPDATE', [$orderId, $customerId])->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new InvalidArgumentException('Order not found.');
            }
            if (!in_array($order['status'], ['paid', 'booking', 'rider_assigned', 'picked_up', 'delivered'], true)) {
                throw new DomainException('A delivery control pass is available only after an order is paid and until delivery is complete.');
            }
            $this->db->raw('UPDATE delivery_control_passes SET revoked_at = ? WHERE order_id = ? AND revoked_at IS NULL', [$now, $orderId]);
            $this->db->raw('INSERT INTO delivery_control_passes (order_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)', [$orderId, $hash, $expiresAt, $now]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->roll_back();
            throw $e;
        }
        return $token;
    }

    public function hasActivePass(int $orderId, int $customerId): bool
    {
        $row = $this->db->raw('SELECT cp.id FROM delivery_control_passes cp INNER JOIN orders o ON o.id = cp.order_id WHERE cp.order_id = ? AND o.customer_id = ? AND cp.revoked_at IS NULL AND cp.expires_at > ? LIMIT 1', [$orderId, $customerId, date('Y-m-d H:i:s')])->fetch(PDO::FETCH_ASSOC);
        return (bool)$row;
    }

    public function revoke(int $orderId, int $customerId): bool
    {
        $owned = $this->db->raw('SELECT id FROM orders WHERE id = ? AND customer_id = ? LIMIT 1', [$orderId, $customerId])->fetch(PDO::FETCH_ASSOC);
        if (!$owned) {
            return false;
        }
        $this->db->raw('UPDATE delivery_control_passes SET revoked_at = ? WHERE order_id = ? AND revoked_at IS NULL', [date('Y-m-d H:i:s'), $orderId]);
        return true;
    }

    public function publicOrder(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,50}$/', $token) || !$this->allowPublicRead($token)) {
            return null;
        }
        $hash = hash('sha256', $token);
        $order = $this->db->raw("SELECT o.id, o.order_no, o.status, o.estimated_duration_min, o.updated_at,
                da.notes AS dropoff_notes
            FROM delivery_control_passes cp
            INNER JOIN orders o ON o.id = cp.order_id
            INNER JOIN addresses da ON da.id = o.dropoff_address_id
            WHERE cp.token_hash = ? AND cp.revoked_at IS NULL AND cp.expires_at > ?
            LIMIT 1", [$hash, date('Y-m-d H:i:s')])->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return null;
        }
        $order['timeline'] = $this->db->raw('SELECT to_status, created_at FROM deliveries WHERE order_id = ? ORDER BY id ASC', [(int)$order['id']])->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $order['requests'] = $this->db->raw("SELECT request_type, request_text, status, created_at
            FROM delivery_change_requests WHERE order_id = ? ORDER BY id DESC LIMIT 5", [(int)$order['id']])->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $order;
    }

    public function submit(string $token, string $type, string $text): void
    {
        $this->assertSubmissionRateLimit($token);
        $order = $this->publicOrder($token);
        if (!$order) {
            throw new InvalidArgumentException('This delivery link is invalid or expired.');
        }
        if (!in_array($order['status'], ['paid', 'booking', 'rider_assigned'], true)) {
            throw new DomainException('Changes can no longer be submitted for this delivery stage.');
        }
        if ($type === 'instruction') {
            $text = trim($text);
            if ($text === '' || mb_strlen($text) > 1000) {
                throw new InvalidArgumentException('Drop-off instructions must be between 1 and 1000 characters.');
            }
            $this->applyInstruction((int)$order['id'], $token, $text);
            return;
        }
        if ($type !== 'hold') {
            throw new InvalidArgumentException('Choose a valid delivery request.');
        }
        $text = trim($text);
        if (mb_strlen($text) > 1000) {
            throw new InvalidArgumentException('A hold request note must be 1000 characters or fewer.');
        }
        $this->db->raw("INSERT INTO delivery_change_requests (order_id, request_type, request_text, status, source, created_at)
            VALUES (?, 'hold', ?, 'pending', 'control_pass', ?)", [(int)$order['id'], $text !== '' ? $text : null, date('Y-m-d H:i:s')]);
    }

    private function consumeRateLimit(string $purpose, string $token, int $windowSeconds, int $limit): bool
    {
        $key = hash('sha256', $token);
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'delivery_control_' . $purpose . '_' . substr($key, 0, 32);
        $handle = @fopen($path, 'c+');
        if (!$handle) {
            return false;
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }
            $state = json_decode(stream_get_contents($handle), true);
            $window = (int)floor(time() / $windowSeconds);
            $count = isset($state['window'], $state['count']) && (int)$state['window'] === $window ? (int)$state['count'] : 0;
            if ($count >= $limit) {
                throw new DomainException('Too many requests were submitted. Please try again later.');
            }
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode(['window' => $window, 'count' => $count + 1]));
            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function assertSubmissionRateLimit(string $token): void
    {
        if (!$this->consumeRateLimit('submit', $token, 3600, 10)) {
            throw new RuntimeException('Delivery requests are temporarily unavailable. Please try again later.');
        }
    }

    private function allowPublicRead(string $token): bool
    {
        try {
            return $this->consumeRateLimit('read', $token, 300, 120);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function applyInstruction(int $orderId, string $token, string $instruction): void
    {
        $this->db->transaction();
        try {
            $row = $this->db->raw("SELECT o.status, o.dropoff_address_id, da.customer_id, da.label, da.address_line, da.latitude, da.longitude,
                    da.contact_name, da.contact_phone
                FROM orders o
                INNER JOIN addresses da ON da.id = o.dropoff_address_id
                INNER JOIN delivery_control_passes cp ON cp.order_id = o.id
                WHERE o.id = ? AND cp.token_hash = ? AND cp.revoked_at IS NULL AND cp.expires_at > ?
                FOR UPDATE", [$orderId, hash('sha256', $token), date('Y-m-d H:i:s')])->fetch(PDO::FETCH_ASSOC);
            if (!$row || !in_array($row['status'], ['paid', 'booking', 'rider_assigned'], true)) {
                throw new DomainException('The delivery changed and can no longer accept new instructions.');
            }
            $now = date('Y-m-d H:i:s');
            $this->db->raw('INSERT INTO addresses (customer_id, label, address_line, latitude, longitude, contact_name, contact_phone, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                null, $row['label'], $row['address_line'], $row['latitude'], $row['longitude'], $row['contact_name'], $row['contact_phone'], $instruction, $now
            ]);
            $newAddressId = (int)$this->db->last_id();
            $this->db->raw('UPDATE orders SET dropoff_address_id = ?, updated_at = ? WHERE id = ?', [$newAddressId, $now, $orderId]);
            $this->db->raw("INSERT INTO delivery_change_requests (order_id, request_type, request_text, status, source, created_at, reviewed_at)
                VALUES (?, 'instruction', ?, 'applied', 'control_pass', ?, ?)", [$orderId, $instruction, $now, $now]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->roll_back();
            throw $e;
        }
    }
}
