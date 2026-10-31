<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Address_model extends Model
{
    protected $table = 'addresses';
    protected $primary_key = 'id';

    protected function getDb()
    {
        return $this->db ?? lava_instance()->db;
    }

    public function get_owned(int $addressId, int $customerId): ?array
    {
        $row = $this->getDb()->table('addresses')
            ->select('id, label, address_line, contact_name, contact_phone, notes, created_at')
            ->where('id', $addressId)
            ->where('customer_id', $customerId)
            ->get();
        return $row ?: null;
    }

    public function create_for_customer(int $customerId, array $data): int
    {
        $db = $this->getDb();
        $db->table('addresses')->insert([
            'customer_id' => $customerId,
            'label' => $data['label'] !== '' ? $data['label'] : null,
            'address_line' => $data['address_line'],
            'contact_name' => $data['contact_name'],
            'contact_phone' => $data['contact_phone'],
            'notes' => $data['notes'] !== '' ? $data['notes'] : null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return (int)$db->last_id();
    }

    public function update_owned(int $addressId, int $customerId, array $data): bool
    {
        $db = $this->getDb();
        $db->transaction();
        try {
            $address = $db->raw(
                'SELECT id, latitude, longitude FROM addresses WHERE id = ? AND customer_id = ? FOR UPDATE',
                [$addressId, $customerId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$address) {
                $db->roll_back();
                return false;
            }
            $references = $db->raw(
                'SELECT id FROM orders WHERE pickup_address_id = ? OR dropoff_address_id = ? LIMIT 1 FOR UPDATE',
                [$addressId, $addressId]
            )->fetch(PDO::FETCH_ASSOC);
            if ($references) {
                // Keep order snapshots stable; detach the historical row from saved addresses.
                $db->table('addresses')->insert([
                    'customer_id' => $customerId,
                    'label' => $data['label'] !== '' ? $data['label'] : null,
                    'address_line' => $data['address_line'],
                    'latitude' => $address['latitude'],
                    'longitude' => $address['longitude'],
                    'contact_name' => $data['contact_name'],
                    'contact_phone' => $data['contact_phone'],
                    'notes' => $data['notes'] !== '' ? $data['notes'] : null,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $db->table('addresses')->where('id', $addressId)->where('customer_id', $customerId)->update(['customer_id' => null]);
            } else {
                $db->table('addresses')->where('id', $addressId)->where('customer_id', $customerId)->update([
                    'label' => $data['label'] !== '' ? $data['label'] : null,
                    'address_line' => $data['address_line'],
                    'contact_name' => $data['contact_name'],
                    'contact_phone' => $data['contact_phone'],
                    'notes' => $data['notes'] !== '' ? $data['notes'] : null,
                ]);
            }
            $db->commit();
            return true;
        } catch (\Throwable $e) {
            $db->roll_back();
            throw $e;
        }
    }

    /**
     * Delete an owned address only if no order references it.
     * Returns false for not found/not owned, 'referenced' when in use, or true on deletion.
     *
     * @return bool|string
     */
    public function delete_owned(int $addressId, int $customerId)
    {
        $db = $this->getDb();
        $db->transaction();
        try {
            $owned = $db->raw(
                'SELECT id FROM addresses WHERE id = ? AND customer_id = ? FOR UPDATE',
                [$addressId, $customerId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$owned) {
                $db->roll_back();
                return false;
            }
            $references = $db->raw(
                'SELECT id FROM orders WHERE pickup_address_id = ? OR dropoff_address_id = ? LIMIT 1 FOR UPDATE',
                [$addressId, $addressId]
            )->fetch(PDO::FETCH_ASSOC);
            if ($references) {
                $db->roll_back();
                return 'referenced';
            }
            $db->table('addresses')->where('id', $addressId)->where('customer_id', $customerId)->delete();
            $db->commit();
            return true;
        } catch (\Throwable $e) {
            $db->roll_back();
            throw $e;
        }
    }

    /**
     * Get saved addresses owned by a customer.
     */
    public function get_by_customer(int $customerId): array
    {
        $rows = $this->getDb()->table('addresses')
            ->select('id, label, address_line, contact_name, contact_phone, notes, created_at')
            ->where('customer_id', $customerId)
            ->order_by('id', 'DESC')
            ->get_all();

        return $rows ?: [];
    }
}
