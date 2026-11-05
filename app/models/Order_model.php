<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Order Model
 * Handles order creation transactions, state machine validation, deliveries audit logging, and queries.
 */
class Order_model extends Model
{
    protected $table = 'orders';
    protected $primary_key = 'id';

    protected const ALLOWED_TRANSITIONS = [
        'draft' => ['pending_payment', 'cancelled'],
        'pending_payment' => ['paid', 'cancelled', 'failed'],
        'paid' => ['booking', 'rider_assigned', 'cancelled', 'failed'],
        'booking' => ['rider_assigned', 'failed', 'cancelled'],
        'rider_assigned' => ['picked_up', 'cancelled', 'failed'],
        'picked_up' => ['delivered', 'failed'],
        'failed' => ['booking', 'rider_assigned'],
        'delivered' => [],
        'cancelled' => []
    ];

    protected function getDb()
    {
        return $this->db ?? lava_instance()->db;
    }

    public function generate_order_no(?string $dateStr = null): string
    {
        $dateStr = $dateStr ?? date('Ymd');
        $prefix = "DLV-{$dateStr}-";
        $row = $this->getDb()->raw(
            'SELECT order_no FROM orders WHERE order_no LIKE ? ORDER BY id DESC LIMIT 1',
            ["{$prefix}%"]
        )->fetch(PDO::FETCH_ASSOC);
        $lastSequence = $row ? (int)substr($row['order_no'], strrpos($row['order_no'], '-') + 1) : 0;
        return $prefix . str_pad((string)($lastSequence + 1), 4, '0', STR_PAD_LEFT);
    }

    public function create_order(array $data): array
    {
        $db = $this->getDb();
        $db->transaction();
        $orderDate = date('Ymd');
        $lockName = 'delivery_order_' . $orderDate;
        $orderNumberLockAcquired = false;
        try {
            $lock = $db->raw('SELECT GET_LOCK(?, 5)', [$lockName]);
            $orderNumberLockAcquired = $lock && (int)$lock->fetchColumn() === 1;
            if (!$orderNumberLockAcquired) {
                throw new RuntimeException('Could not reserve an order number. Please retry.');
            }

            $customerId = (int)$data['customer_id'];
            $orderNo = $this->generate_order_no($orderDate);
            $pickupId = $this->insertAddress($db, $customerId, $data['pickup']);
            $dropoffId = $this->insertAddress($db, $customerId, $data['dropoff']);
            $db->table('orders')->insert([
                'order_no' => $orderNo,
                'customer_id' => $customerId,
                'rider_id' => null,
                'pickup_address_id' => $pickupId,
                'dropoff_address_id' => $dropoffId,
                'status' => 'draft',
                'distance_km' => (float)($data['distance_km'] ?? 0),
                'estimated_duration_min' => (int)($data['estimated_duration_min'] ?? 0),
                'delivery_fee' => (float)($data['delivery_fee'] ?? 0),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $orderId = (int)$db->last_id();
            $parcel = $data['parcel'] ?? [];
            $db->table('parcels')->insert([
                'order_id' => $orderId,
                'description' => trim($parcel['description'] ?? 'Standard Package'),
                'weight_kg' => (float)($parcel['weight_kg'] ?? 1),
                'size' => $parcel['size'] ?? 'M',
                'is_fragile' => !empty($parcel['is_fragile']) ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $db->table('deliveries')->insert([
                'order_id' => $orderId,
                'from_status' => null,
                'to_status' => 'draft',
                'notes' => 'Order created in draft status.',
                'actor_type' => $data['actor_type'] ?? 'customer',
                'actor_id' => $data['actor_id'] ?? $customerId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $db->commit();
            return ['order_id' => $orderId, 'order_no' => $orderNo, 'status' => 'draft', 'delivery_fee' => (float)$data['delivery_fee']];
        } catch (Throwable $e) {
            $db->roll_back();
            throw $e;
        } finally {
            if ($orderNumberLockAcquired) {
                try {
                    $db->raw('SELECT RELEASE_LOCK(?)', [$lockName]);
                } catch (Throwable $releaseError) {
                    error_log('Unable to release order-number lock: ' . $releaseError->getMessage());
                }
            }
        }
    }

    private function insertAddress($db, int $customerId, array $address): int
    {
        $db->table('addresses')->insert([
            'customer_id' => $customerId,
            'label' => $address['label'] ?? null,
            'address_line' => trim($address['address_line']),
            'unit_number' => $address['unit_number'] ?? null,
            'street' => $address['street'] ?? null,
            'barangay' => $address['barangay'] ?? null,
            'city' => $address['city'] ?? null,
            'province' => $address['province'] ?? null,
            'latitude' => $address['latitude'] ?? null,
            'longitude' => $address['longitude'] ?? null,
            'contact_name' => trim($address['contact_name']),
            'contact_phone' => trim($address['contact_phone']),
            'notes' => $address['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return (int)$db->last_id();
    }

    public function soft_delete_draft(int $orderId, int $customerId): bool
    {
        $stmt = $this->getDb()->raw(
            "UPDATE orders SET deleted_at = ?, updated_at = ?
             WHERE id = ? AND customer_id = ? AND status = 'draft' AND deleted_at IS NULL",
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $orderId, $customerId]
        );
        return $stmt !== false && $stmt->rowCount() === 1;
    }

    public function update_draft_order(int $orderId, int $customerId, array $data): bool
    {
        $db = $this->getDb();
        $db->transaction();
        try {
            $order = $db->raw(
                "SELECT id, pickup_address_id, dropoff_address_id FROM orders
                 WHERE id = ? AND customer_id = ? AND status = 'draft' AND deleted_at IS NULL FOR UPDATE",
                [$orderId, $customerId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $db->roll_back();
                return false;
            }

            $newAddressIds = [];
            foreach (['pickup' => 'pickup_address_id', 'dropoff' => 'dropoff_address_id'] as $kind => $column) {
                $address = $data[$kind];
                $old = $db->raw(
                    'SELECT id, label, latitude, longitude FROM addresses WHERE id = ? AND (customer_id = ? OR customer_id IS NULL) FOR UPDATE',
                    [(int)$order[$column], $customerId]
                )->fetch(PDO::FETCH_ASSOC);
                if (!$old) {
                    throw new RuntimeException('Draft order address ownership mismatch.');
                }
                $db->table('addresses')->insert([
                    'customer_id' => null,
                    'label' => $old['label'],
                    'address_line' => $address['address_line'],
                    'unit_number' => $address['unit_number'] ?? null,
                    'street' => $address['street'] ?? null,
                    'barangay' => $address['barangay'] ?? null,
                    'city' => $address['city'] ?? null,
                    'province' => $address['province'] ?? null,
                    'latitude' => $old['latitude'],
                    'longitude' => $old['longitude'],
                    'contact_name' => $address['contact_name'],
                    'contact_phone' => $address['contact_phone'],
                    'notes' => ($address['notes'] ?? '') !== '' ? $address['notes'] : null,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $newAddressIds[$kind] = (int)$db->last_id();
            }

            $parcel = $db->raw('SELECT id FROM parcels WHERE order_id = ? LIMIT 1 FOR UPDATE', [$orderId])->fetch(PDO::FETCH_ASSOC);
            if ($parcel) {
                $db->raw(
                    'UPDATE parcels SET description = ?, weight_kg = ?, size = ?, is_fragile = ? WHERE order_id = ?',
                    [$data['parcel']['description'], $data['parcel']['weight_kg'], $data['parcel']['size'], $data['parcel']['is_fragile'], $orderId]
                );
            } else {
                $db->raw(
                    'INSERT INTO parcels (order_id, description, weight_kg, size, is_fragile, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                    [$orderId, $data['parcel']['description'], $data['parcel']['weight_kg'], $data['parcel']['size'], $data['parcel']['is_fragile'], date('Y-m-d H:i:s')]
                );
            }

            $updated = $db->raw(
                "UPDATE orders SET pickup_address_id = ?, dropoff_address_id = ?, distance_km = ?, estimated_duration_min = ?, delivery_fee = ?, updated_at = ?
                 WHERE id = ? AND customer_id = ? AND status = 'draft' AND deleted_at IS NULL",
                [$newAddressIds['pickup'], $newAddressIds['dropoff'], $data['distance_km'], $data['estimated_duration_min'], $data['delivery_fee'], date('Y-m-d H:i:s'), $orderId, $customerId]
            );
            if ($updated === false || $updated->rowCount() !== 1) {
                throw new RuntimeException('Draft order changed before update completed.');
            }
            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->roll_back();
            throw $e;
        }
    }

    public function set_status(int $orderId, string $newStatus, ?string $notes = null, string $actorType = 'system', ?int $actorId = null, array $extraData = []): bool
    {
        $db = $this->getDb();
        $db->transaction();
        try {
            $order = $db->raw('SELECT id, status, fulfillment_mode, rider_id, lalamove_quotation_id, lalamove_order_id FROM orders WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$orderId])->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new InvalidArgumentException("Order #{$orderId} not found.");
            }
            $currentStatus = $order['status'];
            if ($newStatus === 'rider_assigned' && ($order['fulfillment_mode'] ?? null) === 'lalamove'
                && trim((string)$order['lalamove_order_id']) === '' && !in_array($actorType, ['lalamove_webhook', 'system'], true)) {
                throw new DomainException('Lalamove driver assignment must be recorded through its courier webhook.');
            }
            if (($order['fulfillment_mode'] ?? null) === 'internal' && in_array($newStatus, ['picked_up', 'delivered'], true)) {
                $ownedAssignment = $db->raw(
                    'SELECT ra.id FROM rider_assignments ra
                     INNER JOIN riders r ON r.id = ra.rider_id
                     INNER JOIN users u ON u.id = r.user_id
                     INNER JOIN roles ro ON ro.id = u.role_id AND ro.name = \'rider\'
                     WHERE ra.order_id = ? AND ra.rider_id = ? AND ra.released_at IS NULL AND r.user_id = ?
                       AND r.deleted_at IS NULL AND u.status = \'active\' AND u.deleted_at IS NULL
                     LIMIT 1 FOR UPDATE',
                    [$orderId, (int)$order['rider_id'], (int)($actorId ?? 0)]
                )->fetch(PDO::FETCH_ASSOC);
                if ($actorType !== 'rider' || !$actorId || !$ownedAssignment) {
                    throw new DomainException('Only the assigned rider can update this internal delivery.');
                }
            }
            if ($newStatus === 'rider_assigned' && ($order['fulfillment_mode'] ?? null) === 'internal') {
                if (trim((string)$order['lalamove_quotation_id']) !== '' || trim((string)$order['lalamove_order_id']) !== '') {
                    throw new DomainException('An internal rider assignment cannot coexist with Lalamove provider state.');
                }
                $activeAssignment = $db->raw(
                    'SELECT rider_id FROM rider_assignments WHERE order_id = ? AND rider_id = ? AND released_at IS NULL LIMIT 1 FOR UPDATE',
                    [$orderId, (int)$order['rider_id']]
                )->fetch(PDO::FETCH_ASSOC);
                if ($actorType !== 'dispatcher' || !$actorId || !$activeAssignment
                    || (int)$activeAssignment['rider_id'] !== (int)$order['rider_id']) {
                    throw new DomainException('Internal rider assignments must be changed through DispatchFulfillmentService.');
                }
            }
            if ($currentStatus === $newStatus) {
                $db->commit();
                return true;
            }
            if (!in_array($newStatus, self::ALLOWED_TRANSITIONS[$currentStatus] ?? [], true)) {
                throw new DomainException("Illegal order status transition from '{$currentStatus}' to '{$newStatus}'.");
            }

            $updates = array_merge($extraData, ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')]);
            if (($order['fulfillment_mode'] ?? null) === 'internal' && $newStatus === 'rider_assigned') {
                $updates['fulfillment_mode'] = 'internal';
                if (array_key_exists('rider_id', $extraData)) {
                    $updates['rider_id'] = (int)$extraData['rider_id'];
                }
            }
            if (($order['fulfillment_mode'] ?? null) === 'internal' && in_array($newStatus, ['delivered', 'cancelled', 'failed'], true)) {
                $updates['rider_id'] = null;
            }
            if ($db->table('orders')->where('id', $orderId)->update($updates) === false) {
                throw new RuntimeException('Order status update failed.');
            }
            if (($order['fulfillment_mode'] ?? null) === 'internal' && in_array($newStatus, ['delivered', 'cancelled', 'failed'], true)) {
                $assignment = $db->raw('SELECT id, rider_id FROM rider_assignments WHERE order_id = ? AND released_at IS NULL LIMIT 1 FOR UPDATE', [$orderId])->fetch(PDO::FETCH_ASSOC);
                if ($assignment) {
                    $now = date('Y-m-d H:i:s');
                    $db->raw('UPDATE rider_assignments SET released_at = ?, release_reason = ? WHERE id = ?', [$now, 'Order ' . $newStatus . '.', (int)$assignment['id']]);
                    $db->raw("UPDATE riders SET status = 'available', updated_at = ? WHERE id = ? AND status = 'busy'", [$now, (int)$assignment['rider_id']]);
                }
            }
            $db->table('deliveries')->insert([
                'order_id' => $orderId,
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
                'notes' => $notes ?? "Status transitioned from {$currentStatus} to {$newStatus}.",
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            if (in_array($newStatus, ['rider_assigned', 'picked_up', 'delivered', 'cancelled', 'failed'], true)) {
                require_once APP_DIR . 'libraries/DeliveryNotificationService.php';
                (new DeliveryNotificationService($db))->enqueueForTransition($orderId, $newStatus);
            }
            if (($order['fulfillment_mode'] ?? null) === 'internal' && in_array($newStatus, ['delivered', 'cancelled', 'failed'], true)) {
                $db->raw('DELETE FROM rider_order_locations WHERE order_id = ?', [$orderId]);
            }
            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->roll_back();
            throw $e;
        }
    }

    public function assert_cancellation_allowed(int $orderId): array
    {
        $order = $this->getDb()->raw('SELECT * FROM orders WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$orderId])->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new InvalidArgumentException('Order not found.');
        }
        if (!in_array('cancelled', self::ALLOWED_TRANSITIONS[$order['status']] ?? [], true)) {
            throw new DomainException("Order cannot be cancelled from its current status '{$order['status']}'.");
        }
        return $order;
    }

    public function cancel_order(int $orderId, string $reason, string $actorType = 'customer', ?int $actorId = null): bool
    {
        $this->assert_cancellation_allowed($orderId);
        return $this->set_status($orderId, 'cancelled', "Cancellation Reason: {$reason}", $actorType, $actorId, ['cancel_reason' => $reason]);
    }

    public function get_customer_shared_location(int $orderId, int $customerId): ?array
    {
        $stmt = $this->getDb()->raw(
            "SELECT loc.latitude, loc.longitude, loc.accuracy_m, loc.received_at,
                    TIMESTAMPDIFF(SECOND, loc.received_at, NOW()) AS age_seconds
             FROM orders o
             INNER JOIN rider_order_locations loc ON loc.order_id = o.id
             WHERE o.id = ? AND o.customer_id = ? AND o.deleted_at IS NULL
               AND o.fulfillment_mode = 'internal' AND o.status IN ('rider_assigned', 'picked_up')
               AND EXISTS (
                   SELECT 1 FROM rider_assignments ra
                   WHERE ra.order_id = o.id AND ra.rider_id = o.rider_id AND ra.released_at IS NULL
               )
               AND loc.sharing_enabled = 1 AND loc.received_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
               AND loc.latitude IS NOT NULL AND loc.longitude IS NOT NULL
             LIMIT 1",
            [$orderId, $customerId]
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ?: null;
    }

    public function get_by_id(int $orderId, ?int $customerId = null): ?array
    {
        $where = 'WHERE o.id = ? AND o.deleted_at IS NULL';
        $params = [$orderId];
        if ($customerId !== null) {
            $where .= ' AND o.customer_id = ?';
            $params[] = $customerId;
        }
        $sql = "SELECT o.*,
                       (SELECT pay.checkout_url FROM payments pay WHERE pay.order_id = o.id AND pay.status = 'pending' ORDER BY pay.id DESC LIMIT 1) AS checkout_url,
                       u.name AS customer_name, u.email AS customer_email, u.phone AS customer_user_phone,
                       c.company_name,
                       pa.address_line AS pickup_address, pa.unit_number AS pickup_unit_number, pa.street AS pickup_street, pa.barangay AS pickup_barangay, pa.city AS pickup_city, pa.province AS pickup_province,
                       pa.contact_name AS pickup_contact_name, pa.contact_phone AS pickup_phone,
                       pa.latitude AS pickup_lat, pa.longitude AS pickup_lng, pa.notes AS pickup_notes,
                       da.address_line AS dropoff_address, da.unit_number AS dropoff_unit_number, da.street AS dropoff_street, da.barangay AS dropoff_barangay, da.city AS dropoff_city, da.province AS dropoff_province,
                       da.contact_name AS dropoff_contact_name, da.contact_phone AS dropoff_phone,
                       da.latitude AS dropoff_lat, da.longitude AS dropoff_lng, da.notes AS dropoff_notes,
                       p.description AS parcel_description, p.weight_kg, p.size AS parcel_size, p.is_fragile,
                       r.name AS rider_name, r.phone AS rider_phone, r.vehicle_type AS rider_vehicle, r.plate_number,
                       pay.id AS payment_id, pay.status AS payment_status, pay.payment_method, pay.amount AS paid_amount, pay.paymongo_session_id,
                       dp.id AS delivery_proof_id, dp.recipient_name AS delivery_proof_recipient,
                       dp.note AS delivery_proof_note, dp.captured_at AS delivery_proof_captured_at
                FROM orders o
                INNER JOIN customers c ON o.customer_id = c.id
                INNER JOIN users u ON c.user_id = u.id
                INNER JOIN addresses pa ON o.pickup_address_id = pa.id
                INNER JOIN addresses da ON o.dropoff_address_id = da.id
                LEFT JOIN parcels p ON p.order_id = o.id
                LEFT JOIN riders r ON o.rider_id = r.id
                LEFT JOIN payments pay ON pay.order_id = o.id
                LEFT JOIN delivery_proofs dp ON dp.order_id = o.id
                {$where} LIMIT 1";
        $order = $this->getDb()->raw($sql, $params)->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return null;
        }
        $order['payment_status'] = $this->getDb()->raw("SELECT status FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1", [$orderId])->fetch(PDO::FETCH_ASSOC)['status'] ?? null;
        $order['timeline'] = $this->getDb()->raw('SELECT * FROM deliveries WHERE order_id = ? ORDER BY id ASC', [$orderId])->fetchAll(PDO::FETCH_ASSOC);
        return $order;
    }

    protected function build_list_conditions(array $filters): array
    {
        $where = 'WHERE o.deleted_at IS NULL';
        $params = [];
        if (!empty($filters['customer_id'])) {
            $where .= ' AND o.customer_id = ?';
            $params[] = (int)$filters['customer_id'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND o.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['date'])) {
            $where .= ' AND DATE(o.created_at) = ?';
            $params[] = $filters['date'];
        }
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $where .= ' AND (o.order_no LIKE ? OR u.name LIKE ? OR c.company_name LIKE ? OR pa.address_line LIKE ? OR da.address_line LIKE ?)';
            array_push($params, $term, $term, $term, $term, $term);
        }
        return [$where, $params];
    }

    public function count_list(array $filters = []): int
    {
        [$where, $params] = $this->build_list_conditions($filters);
        $stmt = $this->getDb()->raw(
            "SELECT COUNT(*) AS total FROM orders o
             INNER JOIN customers c ON o.customer_id = c.id
             INNER JOIN users u ON c.user_id = u.id
             INNER JOIN addresses pa ON o.pickup_address_id = pa.id
             INNER JOIN addresses da ON o.dropoff_address_id = da.id {$where}",
            $params
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ? (int)$row['total'] : 0;
    }

    public function get_list(array $filters = []): array
    {
        [$where, $params] = $this->build_list_conditions($filters);
        $limit = max(1, min(100, (int)($filters['limit'] ?? 50)));
        $offset = max(0, (int)($filters['offset'] ?? 0));
        $sql = "SELECT o.*, u.name AS customer_name, c.company_name,
                       pa.address_line AS pickup_address, pa.unit_number AS pickup_unit_number, pa.street AS pickup_street, pa.barangay AS pickup_barangay, pa.city AS pickup_city, pa.province AS pickup_province,
                       da.address_line AS dropoff_address, da.unit_number AS dropoff_unit_number, da.street AS dropoff_street, da.barangay AS dropoff_barangay, da.city AS dropoff_city, da.province AS dropoff_province,
                       p.description AS parcel_description, p.weight_kg, r.name AS rider_name, r.plate_number
                FROM orders o
                INNER JOIN customers c ON o.customer_id = c.id
                INNER JOIN users u ON c.user_id = u.id
                INNER JOIN addresses pa ON o.pickup_address_id = pa.id
                INNER JOIN addresses da ON o.dropoff_address_id = da.id
                LEFT JOIN parcels p ON p.order_id = o.id
                LEFT JOIN riders r ON o.rider_id = r.id
                {$where} ORDER BY o.id DESC LIMIT {$limit} OFFSET {$offset}";
        $orders = $this->getDb()->raw($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
        if (!$orders) {
            return [];
        }

        $ids = array_map(static fn($order) => (int)$order['id'], $orders);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $timelines = $this->getDb()->raw(
            "SELECT order_id, to_status, created_at FROM deliveries WHERE order_id IN ({$placeholders}) ORDER BY id ASC",
            $ids
        )->fetchAll(PDO::FETCH_ASSOC);
        $timelineByOrder = [];
        foreach ($timelines as $entry) {
            $timelineByOrder[(int)$entry['order_id']][] = $entry;
        }
        foreach ($orders as &$order) {
            $order['timeline'] = $timelineByOrder[(int)$order['id']] ?? [];
        }
        unset($order);
        return $orders;
    }
}
