<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Order Template Model
 * Saved delivery setups customers can reuse when creating new drafts.
 */
class Order_template_model extends Model
{
    protected $table = 'order_templates';
    protected $primary_key = 'id';

    protected function getDb()
    {
        return $this->db ?? lava_instance()->db;
    }

    public function get_by_customer(int $customerId): array
    {
        $stmt = $this->getDb()->raw(
            'SELECT * FROM order_templates WHERE customer_id = ? ORDER BY id DESC',
            [$customerId]
        );
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function get_for_customer(int $id, int $customerId): ?array
    {
        $stmt = $this->getDb()->raw(
            'SELECT * FROM order_templates WHERE id = ? AND customer_id = ? LIMIT 1',
            [$id, $customerId]
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    public function count_for_customer(int $customerId): int
    {
        $stmt = $this->getDb()->raw(
            'SELECT COUNT(*) FROM order_templates WHERE customer_id = ?',
            [$customerId]
        );
        return $stmt ? (int)$stmt->fetchColumn() : 0;
    }

    /** Snapshot an order (any status) into a reusable template. */
    public function create_from_order(int $customerId, string $name, array $order): int
    {
        $db = $this->getDb();
        $now = date('Y-m-d H:i:s');
        $db->table('order_templates')->insert([
            'customer_id' => $customerId,
            'name' => mb_substr(trim($name), 0, 100),
            'pickup_address' => (string)$order['pickup_address'],
            'pickup_unit_number' => $order['pickup_unit_number'] ?? null,
            'pickup_street' => $order['pickup_street'] ?? null,
            'pickup_barangay' => $order['pickup_barangay'] ?? null,
            'pickup_city' => $order['pickup_city'] ?? null,
            'pickup_province' => $order['pickup_province'] ?? null,
            'pickup_latitude' => $order['pickup_lat'] ?? null,
            'pickup_longitude' => $order['pickup_lng'] ?? null,
            'pickup_contact_name' => (string)$order['pickup_contact_name'],
            'pickup_contact_phone' => (string)$order['pickup_phone'],
            'pickup_notes' => $order['pickup_notes'] ?? null,
            'dropoff_address' => (string)$order['dropoff_address'],
            'dropoff_unit_number' => $order['dropoff_unit_number'] ?? null,
            'dropoff_street' => $order['dropoff_street'] ?? null,
            'dropoff_barangay' => $order['dropoff_barangay'] ?? null,
            'dropoff_city' => $order['dropoff_city'] ?? null,
            'dropoff_province' => $order['dropoff_province'] ?? null,
            'dropoff_latitude' => $order['dropoff_lat'] ?? null,
            'dropoff_longitude' => $order['dropoff_lng'] ?? null,
            'dropoff_contact_name' => (string)$order['dropoff_contact_name'],
            'dropoff_contact_phone' => (string)$order['dropoff_phone'],
            'dropoff_notes' => $order['dropoff_notes'] ?? null,
            'parcel_description' => (string)($order['parcel_description'] ?? 'Standard Package'),
            'parcel_weight_kg' => (float)($order['weight_kg'] ?? 1),
            'parcel_size' => in_array($order['parcel_size'] ?? '', ['S', 'M', 'L'], true) ? $order['parcel_size'] : 'M',
            'is_fragile' => !empty($order['is_fragile']) ? 1 : 0,
            'distance_km' => (float)($order['distance_km'] ?? 0),
            'created_at' => $now,
            'updated_at' => $now
        ]);
        return (int)$db->last_id();
    }

    public function delete_for_customer(int $id, int $customerId): bool
    {
        $stmt = $this->getDb()->raw(
            'DELETE FROM order_templates WHERE id = ? AND customer_id = ?',
            [$id, $customerId]
        );
        return $stmt !== false && $stmt->rowCount() === 1;
    }
}
