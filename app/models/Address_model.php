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
        $stmt = $this->getDb()->raw(
            'SELECT id, label, address_line, contact_name, contact_phone, notes, created_at FROM addresses WHERE id = ? AND customer_id = ? LIMIT 1',
            [$addressId, $customerId]
        );
        $address = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $address ?: null;
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
                $db->raw('UPDATE addresses SET customer_id = NULL WHERE id = ? AND customer_id = ?', [$addressId, $customerId]);
            } else {
                $db->raw(
                    'UPDATE addresses SET label = ?, address_line = ?, contact_name = ?, contact_phone = ?, notes = ? WHERE id = ? AND customer_id = ?',
                    [$data['label'] !== '' ? $data['label'] : null, $data['address_line'], $data['contact_name'], $data['contact_phone'], $data['notes'] !== '' ? $data['notes'] : null, $addressId, $customerId]
                );
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
            $db->raw('DELETE FROM addresses WHERE id = ? AND customer_id = ?', [$addressId, $customerId]);
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
        $stmt = $this->getDb()->raw(
            "SELECT id, label, address_line, contact_name, contact_phone, notes, created_at
             FROM addresses
             WHERE customer_id = ?
             ORDER BY id DESC",
            [$customerId]
        );

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}
