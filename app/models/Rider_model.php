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

    /**
     * Get all riders, optionally filtered by status
     */
    public function get_all(?string $status = null): array
    {
        $db = $this->getDb();
        if ($status !== null && $status !== '') {
            $sql = "SELECT * FROM riders WHERE status = ? ORDER BY name ASC";
            $stmt = $db->raw($sql, [$status]);
        } else {
            $sql = "SELECT * FROM riders ORDER BY id DESC";
            $stmt = $db->raw($sql);
        }
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Find single rider by ID
     */
    public function find_by_id(int $id): ?array
    {
        $db = $this->getDb();
        $sql = "SELECT * FROM riders WHERE id = ? LIMIT 1";
        $stmt = $db->raw($sql, [$id]);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    /**
     * Create new rider
     */
    public function create(array $data): array
    {
        $db = $this->getDb();
        try {
            $insert = [
                'user_id' => !empty($data['user_id']) ? (int)$data['user_id'] : null,
                'name' => trim($data['name']),
                'phone' => trim($data['phone']),
                'vehicle_type' => trim($data['vehicle_type'] ?? 'Motorcycle'),
                'plate_number' => strtoupper(trim($data['plate_number'])),
                'status' => $data['status'] ?? 'available',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $db->table('riders')->insert($insert);
            $id = (int)$db->last_id();
            return ['success' => true, 'id' => $id];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update existing rider
     */
    public function update_rider(int $id, array $data): bool
    {
        $db = $this->getDb();
        $fields = [];
        if (isset($data['name'])) $fields['name'] = trim($data['name']);
        if (isset($data['phone'])) $fields['phone'] = trim($data['phone']);
        if (isset($data['vehicle_type'])) $fields['vehicle_type'] = trim($data['vehicle_type']);
        if (isset($data['plate_number'])) $fields['plate_number'] = strtoupper(trim($data['plate_number']));
        if (isset($data['status'])) $fields['status'] = $data['status'];
        $fields['updated_at'] = date('Y-m-d H:i:s');

        return (bool)$db->table('riders')->where('id', $id)->update($fields);
    }

    /**
     * Delete rider
     */
    public function delete_rider(int $id): bool
    {
        $db = $this->getDb();
        return (bool)$db->table('riders')->where('id', $id)->delete();
    }
}
