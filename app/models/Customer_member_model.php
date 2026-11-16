<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Customer Member Model
 * Team members that operate under one business customer account.
 */
class Customer_member_model extends Model
{
    protected $table = 'customer_members';
    protected $primary_key = 'id';

    protected function getDb()
    {
        return $this->db ?? lava_instance()->db;
    }

    public function get_by_customer(int $customerId): array
    {
        $stmt = $this->getDb()->raw(
            'SELECT cm.id, cm.user_id, cm.created_at, u.name, u.email, u.phone, u.status
             FROM customer_members cm
             INNER JOIN users u ON u.id = cm.user_id
             WHERE cm.customer_id = ? ORDER BY cm.id ASC',
            [$customerId]
        );
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function count_for_customer(int $customerId): int
    {
        $stmt = $this->getDb()->raw(
            'SELECT COUNT(*) FROM customer_members WHERE customer_id = ?',
            [$customerId]
        );
        return $stmt ? (int)$stmt->fetchColumn() : 0;
    }

    public function create(int $customerId, int $userId, ?int $createdBy = null): int
    {
        $db = $this->getDb();
        $db->table('customer_members')->insert([
            'customer_id' => $customerId,
            'user_id' => $userId,
            'created_by' => $createdBy,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return (int)$db->last_id();
    }

    public function find_for_customer(int $id, int $customerId): ?array
    {
        $stmt = $this->getDb()->raw(
            'SELECT id, user_id FROM customer_members WHERE id = ? AND customer_id = ? LIMIT 1',
            [$id, $customerId]
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    public function delete_for_customer(int $id, int $customerId): bool
    {
        $stmt = $this->getDb()->raw(
            'DELETE FROM customer_members WHERE id = ? AND customer_id = ?',
            [$id, $customerId]
        );
        return $stmt !== false && $stmt->rowCount() === 1;
    }
}
