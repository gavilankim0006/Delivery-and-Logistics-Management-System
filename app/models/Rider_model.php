<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Rider_model extends Model
{
    protected $table = 'riders';
    protected $primary_key = 'id';
    protected $fillable = ['user_id', 'name', 'phone', 'vehicle_type', 'plate_number', 'status'];

    protected function getDb()
    {
        return $this->db ?? lava_instance()->db;
    }

    public function get_all(?string $status = null): array
    {
        $db = $this->getDb();
        if ($status !== null && $status !== '') {
            $allowedStatuses = ['available', 'busy', 'offline'];
            if (!in_array($status, $allowedStatuses, true)) {
                return [];
            }
            $stmt = $db->raw("SELECT r.*, u.email AS account_email, u.status AS account_status,
                EXISTS(SELECT 1 FROM rider_assignments ra WHERE ra.rider_id = r.id AND ra.released_at IS NULL) AS has_active_assignment
                FROM riders r LEFT JOIN users u ON u.id = r.user_id
                WHERE r.status = ? AND r.deleted_at IS NULL ORDER BY r.name ASC", [$status]);
        } else {
            $stmt = $db->raw("SELECT r.*, u.email AS account_email, u.status AS account_status,
                EXISTS(SELECT 1 FROM rider_assignments ra WHERE ra.rider_id = r.id AND ra.released_at IS NULL) AS has_active_assignment
                FROM riders r LEFT JOIN users u ON u.id = r.user_id
                WHERE r.deleted_at IS NULL ORDER BY r.id DESC");
        }
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function find_by_id(int $id): ?array
    {
        $stmt = $this->getDb()->raw('SELECT * FROM riders WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$id]);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    public function get_available_internal(): array
    {
        $stmt = $this->getDb()->raw("SELECT r.id, r.user_id, r.name, r.phone, r.vehicle_type, r.plate_number
            FROM riders r INNER JOIN users u ON u.id = r.user_id
            INNER JOIN roles ro ON ro.id = u.role_id AND ro.name = 'rider'
            WHERE r.status = 'available' AND r.deleted_at IS NULL
              AND u.status = 'active' AND u.deleted_at IS NULL
              AND NOT EXISTS (SELECT 1 FROM rider_assignments ra WHERE ra.rider_id = r.id AND ra.released_at IS NULL)
            ORDER BY r.name ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function get_unlinked_rider_users(): array
    {
        $stmt = $this->getDb()->raw("SELECT u.id, u.name, u.email
            FROM users u INNER JOIN roles ro ON ro.id = u.role_id
            WHERE ro.name = 'rider' AND u.status = 'active' AND u.deleted_at IS NULL
              AND NOT EXISTS (SELECT 1 FROM riders r WHERE r.user_id = u.id AND r.deleted_at IS NULL)
            ORDER BY u.name ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function find_for_user(int $userId): ?array
    {
        $stmt = $this->getDb()->raw("SELECT r.* FROM riders r
            INNER JOIN users u ON u.id = r.user_id
            INNER JOIN roles ro ON ro.id = u.role_id AND ro.name = 'rider'
            WHERE r.user_id = ? AND r.deleted_at IS NULL
              AND u.status = 'active' AND u.deleted_at IS NULL LIMIT 1", [$userId]);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    public function get_active_orders(int $userId): array
    {
        $stmt = $this->getDb()->raw("SELECT o.id AS order_id, o.order_no, o.status, ra.assigned_at,
                    pa.address_line AS pickup_address, pa.contact_name AS pickup_contact_name,
                    pa.contact_phone AS pickup_phone, pa.notes AS pickup_notes,
                    da.address_line AS dropoff_address, da.contact_name AS dropoff_contact_name,
                    da.contact_phone AS dropoff_phone, da.notes AS dropoff_notes,
                    p.description AS parcel_description, p.weight_kg, p.size AS parcel_size, p.is_fragile
            FROM riders r INNER JOIN rider_assignments ra ON ra.rider_id = r.id AND ra.released_at IS NULL
            INNER JOIN orders o ON o.id = ra.order_id
            INNER JOIN addresses pa ON pa.id = o.pickup_address_id
            INNER JOIN addresses da ON da.id = o.dropoff_address_id
            LEFT JOIN parcels p ON p.order_id = o.id
            INNER JOIN users u ON u.id = r.user_id
            INNER JOIN roles ro ON ro.id = u.role_id AND ro.name = 'rider'
            WHERE r.user_id = ? AND r.deleted_at IS NULL AND u.status = 'active' AND u.deleted_at IS NULL
              AND o.fulfillment_mode = 'internal' AND o.rider_id = r.id
              AND o.status IN ('rider_assigned', 'picked_up') AND o.deleted_at IS NULL
            ORDER BY ra.assigned_at ASC", [$userId]);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function get_delivery_for_user(int $orderId, int $userId): ?array
    {
        $stmt = $this->getDb()->raw("SELECT o.id AS order_id, o.order_no, o.status, ra.assigned_at,
                    pa.address_line AS pickup_address, pa.contact_name AS pickup_contact_name,
                    pa.contact_phone AS pickup_phone, pa.notes AS pickup_notes,
                    da.address_line AS dropoff_address, da.contact_name AS dropoff_contact_name,
                    da.contact_phone AS dropoff_phone, da.notes AS dropoff_notes,
                    p.description AS parcel_description, p.weight_kg, p.size AS parcel_size, p.is_fragile
            FROM riders r INNER JOIN rider_assignments ra ON ra.rider_id = r.id AND ra.released_at IS NULL
            INNER JOIN orders o ON o.id = ra.order_id
            INNER JOIN addresses pa ON pa.id = o.pickup_address_id
            INNER JOIN addresses da ON da.id = o.dropoff_address_id
            LEFT JOIN parcels p ON p.order_id = o.id
            INNER JOIN users u ON u.id = r.user_id
            INNER JOIN roles ro ON ro.id = u.role_id AND ro.name = 'rider'
            WHERE o.id = ? AND r.user_id = ? AND r.deleted_at IS NULL
              AND u.status = 'active' AND u.deleted_at IS NULL
              AND o.fulfillment_mode = 'internal' AND o.rider_id = r.id
              AND o.status IN ('rider_assigned', 'picked_up') AND o.deleted_at IS NULL
            ORDER BY ra.assigned_at DESC LIMIT 1", [$orderId, $userId]);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    public function create(array $data): array
    {
        $db = $this->getDb();
        foreach (['name' => 100, 'phone' => 30, 'vehicle_type' => 50, 'plate_number' => 30] as $field => $maxLength) {
            $value = trim((string)($data[$field] ?? ''));
            if ($value === '' && in_array($field, ['name', 'phone', 'plate_number'], true)) {
                return ['success' => false, 'error' => 'Name, phone, and plate number are required.'];
            }
            if (mb_strlen($value) > $maxLength) {
                return ['success' => false, 'error' => ucfirst(str_replace('_', ' ', $field)) . ' is too long.'];
            }
        }
        if (!in_array($data['status'] ?? 'available', ['available', 'busy', 'offline'], true)) {
            return ['success' => false, 'error' => 'Choose a valid rider status.'];
        }
        try {
            $db->table('riders')->insert([
                'user_id' => !empty($data['user_id']) ? (int)$data['user_id'] : null,
                'name' => trim($data['name']),
                'phone' => trim($data['phone']),
                'vehicle_type' => trim($data['vehicle_type'] ?? 'Motorcycle'),
                'plate_number' => strtoupper(trim($data['plate_number'])),
                'status' => $data['status'] ?? 'available',
                'deleted_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            return ['success' => true, 'id' => (int)$db->last_id()];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function link_account(int $riderId, int $userId): bool
    {
        $db = $this->getDb();
        $db->transaction();
        try {
            $rider = $db->raw('SELECT id, user_id, status FROM riders WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$riderId])->fetch(PDO::FETCH_ASSOC);
            $user = $db->raw("SELECT u.id, u.name, u.phone, u.status, u.deleted_at, ro.name AS role_name FROM users u
                INNER JOIN roles ro ON ro.id = u.role_id WHERE u.id = ? FOR UPDATE", [$userId])->fetch(PDO::FETCH_ASSOC);
            if (!$rider || !$user || $user['role_name'] !== 'rider' || $user['status'] !== 'active' || $user['deleted_at'] !== null || trim((string)($user['name'] ?? '')) === '' || strlen((string)($user['phone'] ?? '')) > 30) {
                throw new DomainException('Choose an active rider-role account.');
            }
            if (!empty($rider['user_id']) && (int)$rider['user_id'] !== $userId) {
                throw new DomainException('This rider already has a linked account.');
            }
            if (empty($rider['user_id']) && $rider['status'] === 'busy') {
                throw new DomainException('Resolve the rider record’s current busy state before linking a portal account.');
            }
            $linked = $db->raw('SELECT id FROM riders WHERE user_id = ? AND id <> ? AND deleted_at IS NULL LIMIT 1 FOR UPDATE', [$userId, $riderId])->fetch(PDO::FETCH_ASSOC);
            if ($linked) {
                throw new DomainException('This account is already linked to another rider record.');
            }
            $updated = $db->raw('UPDATE riders SET user_id = ?, name = ?, phone = ?, status = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL', [$userId, $user['name'] ?? '', $user['phone'] ?? '', 'available', date('Y-m-d H:i:s'), $riderId]);
            if ($updated === false || $updated->rowCount() !== 1) {
                if ((int)$rider['user_id'] !== $userId) throw new RuntimeException('Rider account could not be linked.');
            }
            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->roll_back();
            throw $e;
        }
    }

    public function unlink_account(int $riderId): bool
    {
        $db = $this->getDb();
        $db->transaction();
        try {
            $rider = $db->raw('SELECT id, user_id, status FROM riders WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$riderId])->fetch(PDO::FETCH_ASSOC);
            if (!$rider) throw new InvalidArgumentException('Rider not found.');
            $active = $db->raw('SELECT id FROM rider_assignments WHERE rider_id = ? AND released_at IS NULL LIMIT 1 FOR UPDATE', [$riderId])->fetch(PDO::FETCH_ASSOC);
            if ($active) throw new DomainException('A rider with an active assignment cannot be unlinked.');
            if (empty($rider['user_id'])) {
                $db->commit();
                return true;
            }
            $updated = $db->raw('UPDATE riders SET user_id = NULL, updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $riderId]);
            if ($updated === false || ($updated->rowCount() !== 1 && !empty($rider['user_id']))) throw new RuntimeException('Rider account could not be unlinked.');
            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->roll_back();
            throw $e;
        }
    }

    public function update_rider(int $id, array $data): bool
    {
        $fields = [];
        $maxLengths = ['name' => 100, 'phone' => 30, 'vehicle_type' => 50, 'plate_number' => 30];
        foreach (['name', 'phone', 'vehicle_type', 'plate_number', 'status'] as $key) {
            if (!isset($data[$key])) continue;
            if (!is_scalar($data[$key])) return false;
            $value = trim((string)$data[$key]);
            if (isset($maxLengths[$key]) && mb_strlen($value) > $maxLengths[$key]) return false;
            if (in_array($key, ['name', 'phone', 'plate_number'], true) && $value === '') return false;
            $fields[$key] = $key === 'plate_number' ? strtoupper($value) : $value;
        }
        if (isset($fields['status']) && !in_array($fields['status'], ['available', 'busy', 'offline'], true)) {
            return false;
        }
        if (!$fields) return false;
        if (!$this->find_by_id($id)) return false;

        $fields['updated_at'] = date('Y-m-d H:i:s');
        $db = $this->getDb();
        $db->transaction();
        try {
            $rider = $db->raw('SELECT id FROM riders WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$id])->fetch(PDO::FETCH_ASSOC);
            if (!$rider) {
                $db->roll_back();
                return false;
            }
            if (isset($fields['status']) && in_array($fields['status'], ['available', 'offline'], true)) {
                $active = $db->raw('SELECT id FROM rider_assignments WHERE rider_id = ? AND released_at IS NULL LIMIT 1 FOR UPDATE', [$id])->fetch(PDO::FETCH_ASSOC);
                if ($active) {
                    $db->roll_back();
                    return false;
                }
            }
            $updated = $db->table('riders')->where('id', $id)->where_null('deleted_at')->update($fields);
            $db->commit();
            return $updated > 0 || $this->find_by_id($id) !== null;
        } catch (Throwable $e) {
            $db->roll_back();
            return false;
        }
    }

    public function delete_rider(int $id): bool
    {
        $db = $this->getDb();
        $db->transaction();
        try {
            $rider = $db->raw('SELECT id FROM riders WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$id])->fetch(PDO::FETCH_ASSOC);
            $active = $db->raw('SELECT id FROM rider_assignments WHERE rider_id = ? AND released_at IS NULL LIMIT 1 FOR UPDATE', [$id])->fetch(PDO::FETCH_ASSOC);
            if (!$rider || $active) {
                $db->roll_back();
                return false;
            }
            $now = date('Y-m-d H:i:s');
            $result = $db->table('riders')->where('id', $id)->where_null('deleted_at')->update([
                'deleted_at' => $now, 'updated_at' => $now, 'status' => 'offline'
            ]);
            $db->commit();
            return $result === 1;
        } catch (Throwable $e) {
            $db->roll_back();
            return false;
        }
    }
}
