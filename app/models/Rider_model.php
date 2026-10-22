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
            $stmt = $db->raw('SELECT * FROM riders WHERE status = ? AND deleted_at IS NULL ORDER BY name ASC', [$status]);
        } else {
            $stmt = $db->raw('SELECT * FROM riders WHERE deleted_at IS NULL ORDER BY id DESC');
        }
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function find_by_id(int $id): ?array
    {
        $stmt = $this->getDb()->raw('SELECT * FROM riders WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$id]);
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
        try {
            $updated = $this->getDb()->table('riders')->where('id', $id)->where_null('deleted_at')->update($fields);
            return $updated > 0 || $this->find_by_id($id) !== null;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function delete_rider(int $id): bool
    {
        $now = date('Y-m-d H:i:s');
        return $this->getDb()->table('riders')->where('id', $id)->where_null('deleted_at')->update([
            'deleted_at' => $now,
            'updated_at' => $now,
            'status' => 'offline'
        ]) === 1;
    }
}
