<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Service_area_model extends Model
{
    protected function getDb()
    {
        return $this->db ?? lava_instance()->db;
    }

    public function get_all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM service_areas' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY name ASC, id ASC';
        return $this->getDb()->raw($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $row = $this->getDb()->raw('SELECT * FROM service_areas WHERE id = ? LIMIT 1', [$id])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(?int $id, string $name, array $polygon, array $rates, bool $active): int
    {
        $db = $this->getDb();
        $payload = [
            'name' => $name,
            'polygon_json' => json_encode($polygon, JSON_THROW_ON_ERROR),
            'base_delivery_fee' => $rates['base_delivery_fee'],
            'free_distance_km' => $rates['free_distance_km'],
            'per_km_rate' => $rates['per_km_rate'],
            'free_weight_kg' => $rates['free_weight_kg'],
            'per_kg_rate' => $rates['per_kg_rate'],
            'is_active' => $active ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        if ($id) {
            $db->table('service_areas')->where('id', $id)->update($payload);
            return $id;
        }
        $payload['created_at'] = date('Y-m-d H:i:s');
        $db->table('service_areas')->insert($payload);
        return (int)$db->last_id();
    }

    public function delete(int $id): bool
    {
        return (bool)$this->getDb()->table('service_areas')->where('id', $id)->delete();
    }

    public function get_orders_in_zone(int $id): array
    {
        $db = $this->getDb();
        $zone = $this->find($id);
        if (!$zone || !(int)$zone['is_active']) {
            throw new InvalidArgumentException('Choose an active service area.');
        }
        require_once APP_DIR . 'libraries/DeliveryCommandSimulator.php';
        $polygon = DeliveryCommandSimulator::normalizePolygon($zone['polygon_json']);
        $orders = $db->raw("SELECT o.id, o.order_no, o.status, o.distance_km, o.delivery_fee, o.created_at,
                pa.latitude AS pickup_lat, pa.longitude AS pickup_lng,
                da.latitude AS dropoff_lat, da.longitude AS dropoff_lng
            FROM orders o
            INNER JOIN addresses pa ON pa.id = o.pickup_address_id
            INNER JOIN addresses da ON da.id = o.dropoff_address_id
            WHERE o.status NOT IN ('draft', 'cancelled')
            ORDER BY o.id DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $inZone = [];
        $missingCoordinates = 0;
        foreach ($orders as $order) {
            if ($order['dropoff_lat'] === null || $order['dropoff_lng'] === null) {
                $missingCoordinates++;
                continue;
            }
            if (DeliveryCommandSimulator::pointInPolygon((float)$order['dropoff_lat'], (float)$order['dropoff_lng'], $polygon)) {
                $inZone[] = $order;
            }
        }
        return ['orders' => $inZone, 'missing_coordinates' => $missingCoordinates, 'zone' => $zone];
    }
}
