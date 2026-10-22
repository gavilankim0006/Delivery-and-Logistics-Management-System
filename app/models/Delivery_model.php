<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Delivery_model extends Model
{
    protected $table = 'deliveries';
    protected $primary_key = 'id';
    protected $fillable = ['order_id', 'from_status', 'to_status', 'notes', 'actor_type', 'actor_id'];

    protected function getDb()
    {
        return $this->db ?? lava_instance()->db;
    }

    public function get_all(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->getDb()->raw("SELECT * FROM deliveries ORDER BY id DESC LIMIT {$limit}");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function get_by_id(int $id): ?array
    {
        $stmt = $this->getDb()->raw('SELECT * FROM deliveries WHERE id = ? LIMIT 1', [$id]);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    public function get_by_order(int $orderId): array
    {
        $stmt = $this->getDb()->raw('SELECT * FROM deliveries WHERE order_id = ? ORDER BY id ASC', [$orderId]);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function get_filtered(array $filters = []): array
    {
        $where = [];
        $params = [];
        foreach (['order_id', 'to_status', 'actor_type'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $where[] = "{$key} = ?";
                $params[] = $key === 'order_id' ? (int)$filters[$key] : (string)$filters[$key];
            }
        }
        $limit = max(1, min(500, (int)($filters['limit'] ?? 100)));
        $offset = max(0, (int)($filters['offset'] ?? 0));
        $sql = 'SELECT * FROM deliveries' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->getDb()->raw($sql, $params);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function create(array $data): int
    {
        $db = $this->getDb();
        $db->table('deliveries')->insert([
            'order_id' => (int)$data['order_id'],
            'from_status' => $data['from_status'] ?? null,
            'to_status' => (string)$data['to_status'],
            'notes' => $data['notes'] ?? null,
            'actor_type' => (string)($data['actor_type'] ?? 'system'),
            'actor_id' => isset($data['actor_id']) ? (int)$data['actor_id'] : null,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        return (int)$db->last_id();
    }

    /** Delivery records are append-only audit history. */
    public function update_delivery(int $id, array $data): bool
    {
        return false;
    }

    /** Delivery records are append-only audit history. */
    public function delete(int $id): bool
    {
        return false;
    }
}
