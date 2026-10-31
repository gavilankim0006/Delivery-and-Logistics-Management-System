<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class DispatchFulfillmentService
{
    private $db;
    private $orderModel;

    public function __construct($db = null, $orderModel = null)
    {
        $lava = function_exists('lava_instance') ? lava_instance() : null;
        $this->db = $db ?? ($lava ? $lava->db : null);
        if ($orderModel) {
            $this->orderModel = $orderModel;
        } elseif ($lava) {
            $this->orderModel = $lava->Order_model ?? (isset($lava->call) ? $lava->call->model('Order_model') : null);
        }
    }

    public function assignInternal(int $orderId, int $riderId, int $assignerId): array
    {
        if ($orderId < 1 || $riderId < 1 || $assignerId < 1) {
            throw new InvalidArgumentException('Choose a valid order and rider.');
        }

        $db = $this->db;
        $db->transaction();
        try {
            $order = $db->raw(
                'SELECT id, status, fulfillment_mode, rider_id, lalamove_quotation_id, lalamove_order_id
                 FROM orders WHERE id = ? AND deleted_at IS NULL FOR UPDATE',
                [$orderId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new InvalidArgumentException('Order not found.');
            }
            if (!in_array($order['status'], ['paid', 'rider_assigned', 'failed'], true)) {
                throw new DomainException('This order is not ready for an internal rider assignment.');
            }
            if ($order['status'] === 'failed' && $order['fulfillment_mode'] !== 'internal') {
                throw new DomainException('A failed order cannot switch fulfillment paths; ask dispatch to review it.');
            }
            if (!in_array($order['fulfillment_mode'], [null, 'internal'], true)
                || trim((string)$order['lalamove_quotation_id']) !== ''
                || trim((string)$order['lalamove_order_id']) !== '') {
                throw new DomainException('This order has an external courier path or provider state and cannot be assigned internally.');
            }

            $active = $db->raw(
                'SELECT id, rider_id FROM rider_assignments WHERE order_id = ? AND released_at IS NULL LIMIT 1 FOR UPDATE',
                [$orderId]
            )->fetch(PDO::FETCH_ASSOC);
            if ($order['status'] === 'rider_assigned' && $order['fulfillment_mode'] === 'internal' && !$active) {
                throw new DomainException('The current assignment history is missing; ask an administrator to review this order.');
            }
            if ($active && (int)$active['rider_id'] === $riderId) {
                $db->commit();
                return ['order_id' => $orderId, 'rider_id' => $riderId, 'unchanged' => true];
            }
            if ($active) {
                $previousRider = $db->raw("SELECT id, status FROM riders WHERE id = ? AND deleted_at IS NULL FOR UPDATE", [(int)$active['rider_id']])->fetch(PDO::FETCH_ASSOC);
                if (!$previousRider || $previousRider['status'] !== 'busy') {
                    throw new DomainException('The current rider availability is inconsistent; ask an administrator to review this order.');
                }
            }
            if ($order['status'] === 'rider_assigned') {
                $transitionLock = $db->raw(
                    'SELECT order_id FROM dispatch_recovery_locks WHERE order_id = ? LIMIT 1 FOR UPDATE',
                    [$orderId]
                )->fetch(PDO::FETCH_ASSOC);
                if ($transitionLock) {
                    throw new DomainException('Another dispatch action is in progress for this order.');
                }
                $db->raw('INSERT INTO dispatch_recovery_locks (order_id, started_at) VALUES (?, ?)', [$orderId, date('Y-m-d H:i:s')]);
            }

            $rider = $db->raw(
                "SELECT r.id, r.status, r.user_id, u.status AS account_status, u.deleted_at AS account_deleted_at
                 FROM riders r INNER JOIN users u ON u.id = r.user_id
                 INNER JOIN roles ro ON ro.id = u.role_id AND ro.name = 'rider'
                 WHERE r.id = ? AND r.deleted_at IS NULL FOR UPDATE",
                [$riderId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$rider || empty($rider['user_id']) || $rider['account_status'] !== 'active' || $rider['account_deleted_at'] !== null) {
                throw new DomainException('Choose an active rider with a linked portal account.');
            }
            if ($rider['status'] !== 'available') {
                throw new DomainException('The selected rider is not available.');
            }
            $otherAssignment = $db->raw(
                'SELECT order_id FROM rider_assignments WHERE rider_id = ? AND released_at IS NULL LIMIT 1 FOR UPDATE',
                [$riderId]
            )->fetch(PDO::FETCH_ASSOC);
            if ($otherAssignment && (int)$otherAssignment['order_id'] !== $orderId) {
                throw new DomainException('The selected rider already has an active assignment.');
            }

            if ($active) {
                $db->raw('DELETE FROM rider_order_locations WHERE order_id = ?', [$orderId]);
                $previousRiderId = (int)$active['rider_id'];
                $now = date('Y-m-d H:i:s');
                $db->raw(
                    'UPDATE rider_assignments SET released_at = ?, release_reason = ? WHERE id = ? AND released_at IS NULL',
                    [$now, 'Reassigned by dispatch.', (int)$active['id']]
                );
                $releasedRider = $db->raw("UPDATE riders SET status = 'available', updated_at = ? WHERE id = ? AND status = 'busy'", [$now, $previousRiderId]);
                if ($releasedRider === false || $releasedRider->rowCount() !== 1) {
                    throw new DomainException('The previous rider could not be released; assignment was not changed.');
                }
            } elseif (!empty($order['rider_id'])) {
                throw new DomainException('This order has an unreconciled legacy rider reference and needs review before assignment.');
            }

            $now = date('Y-m-d H:i:s');
            $updatedRider = $db->raw("UPDATE riders SET status = 'busy', updated_at = ? WHERE id = ? AND status = 'available'", [$now, $riderId]);
            if ($updatedRider === false || $updatedRider->rowCount() !== 1) {
                throw new DomainException('The selected rider is no longer available.');
            }
            $db->raw(
                'INSERT INTO rider_assignments (order_id, rider_id, assigned_by, assigned_at) VALUES (?, ?, ?, ?)',
                [$orderId, $riderId, $assignerId, $now]
            );

            $notes = $active
                ? 'Dispatch reassigned the internal delivery to rider #' . $riderId . '.'
                : 'Dispatch assigned the internal delivery to rider #' . $riderId . '.';
            $db->raw('UPDATE orders SET fulfillment_mode = ?, rider_id = ?, updated_at = ? WHERE id = ?', ['internal', $riderId, $now, $orderId]);
            if ($order['status'] === 'paid' || $order['status'] === 'failed') {
                $this->orderModel->set_status(
                    $orderId,
                    'rider_assigned',
                    $notes,
                    'dispatcher',
                    $assignerId
                );
            } else {
                $db->raw(
                    'INSERT INTO deliveries (order_id, from_status, to_status, notes, actor_type, actor_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$orderId, 'rider_assigned', 'rider_assigned', $notes, 'dispatcher', $assignerId, $now]
                );
                require_once APP_DIR . 'libraries/DeliveryNotificationService.php';
                (new DeliveryNotificationService($db))->enqueueForTransition($orderId, 'rider_assigned');
            }

            $db->raw('DELETE FROM dispatch_recovery_locks WHERE order_id = ?', [$orderId]);
            $db->commit();
            if ($order['status'] === 'paid' || $order['status'] === 'failed' || $active) {
                $this->dispatchStatusNotifications($orderId);
            }
            return ['order_id' => $orderId, 'rider_id' => $riderId, 'unchanged' => false];
        } catch (Throwable $e) {
            $db->roll_back();
            throw $e;
        }
    }

    private function dispatchStatusNotifications(int $orderId): void
    {
        try {
            require_once APP_DIR . 'libraries/DeliveryNotificationService.php';
            (new DeliveryNotificationService($this->db))->dispatchPendingForOrder($orderId);
        } catch (Throwable $notificationError) {
            error_log('Delivery status notification failed for order ' . $orderId . ': ' . $notificationError->getMessage());
        }
    }

    public function updateRiderStatus(int $orderId, int $userId, string $newStatus): void
    {
        if ($orderId < 1 || $userId < 1 || !in_array($newStatus, ['picked_up', 'delivered'], true)) {
            throw new InvalidArgumentException('Choose a valid delivery action.');
        }

        $db = $this->db;
        $order = $db->raw(
            'SELECT o.id, o.status, o.fulfillment_mode
             FROM orders o
             INNER JOIN riders r ON r.id = o.rider_id AND r.user_id = ?
             INNER JOIN rider_assignments ra ON ra.order_id = o.id AND ra.rider_id = r.id AND ra.released_at IS NULL
             WHERE o.id = ? AND o.fulfillment_mode = \'internal\' AND o.deleted_at IS NULL
               AND r.deleted_at IS NULL AND o.status IN (\'rider_assigned\', \'picked_up\')
             LIMIT 1',
            [$userId, $orderId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new DomainException('This internal delivery is not assigned to your rider account.');
        }
        $expectedStatus = $newStatus === 'picked_up' ? 'rider_assigned' : 'picked_up';
        if ($order['status'] !== $expectedStatus) {
            throw new DomainException('This delivery is not ready for that action.');
        }

        // set_status re-locks the order and validates the rider assignment before committing.
        $this->orderModel->set_status($orderId, $newStatus, null, 'rider', $userId);
        try {
            require_once APP_DIR . 'libraries/DeliveryNotificationService.php';
            (new DeliveryNotificationService($db))->dispatchPendingForOrder($orderId);
        } catch (Throwable $notificationError) {
            error_log('Delivery status notification failed for order ' . $orderId . ': ' . $notificationError->getMessage());
        }
    }

    public function selectLalamove(int $orderId, int $dispatcherId): void
    {
        if ($orderId < 1 || $dispatcherId < 1) {
            throw new InvalidArgumentException('Choose a valid order.');
        }

        $db = $this->db;
        $db->transaction();
        try {
            $order = $db->raw(
                'SELECT id, status, fulfillment_mode, rider_id, lalamove_quotation_id, lalamove_order_id
                 FROM orders WHERE id = ? AND deleted_at IS NULL FOR UPDATE',
                [$orderId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new InvalidArgumentException('Order not found.');
            }
            if (!in_array($order['status'], ['paid', 'failed'], true)) {
                throw new DomainException('Only paid or safely recoverable failed orders can be routed to Lalamove.');
            }
            if ($order['status'] === 'failed' && $order['fulfillment_mode'] !== 'lalamove') {
                throw new DomainException('A failed order cannot switch fulfillment paths; ask dispatch to review it.');
            }
            if (!in_array($order['fulfillment_mode'], [null, 'lalamove'], true)) {
                throw new DomainException('This order is already committed to internal rider fulfillment.');
            }
            if (trim((string)$order['lalamove_quotation_id']) !== '' || trim((string)$order['lalamove_order_id']) !== '') {
                throw new DomainException('Lalamove provider state already exists; review it instead of starting another booking.');
            }
            if (!empty($order['rider_id'])) {
                throw new DomainException('This order has an ambiguous legacy rider reference and needs manual review.');
            }
            $transitionLock = $db->raw('SELECT order_id FROM dispatch_recovery_locks WHERE order_id = ? LIMIT 1 FOR UPDATE', [$orderId])->fetch(PDO::FETCH_ASSOC);
            if ($transitionLock) {
                throw new DomainException('A dispatch or courier booking attempt is in progress.');
            }
            $active = $db->raw(
                'SELECT id FROM rider_assignments WHERE order_id = ? AND released_at IS NULL LIMIT 1 FOR UPDATE',
                [$orderId]
            )->fetch(PDO::FETCH_ASSOC);
            if ($active) {
                throw new DomainException('Release the internal rider assignment before selecting an external courier.');
            }

            $db->raw("UPDATE orders SET fulfillment_mode = 'lalamove', updated_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $orderId]);
            $db->commit();
        } catch (Throwable $e) {
            $db->roll_back();
            throw $e;
        }
    }
}
