<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/CourierProviderFactory.php';
require_once APP_DIR . 'libraries/CourierWebhookProcessor.php';

class CourierBookingService
{
    private $db;
    private $orderModel;
    private CourierProviderInterface $driver;

    public function __construct($db = null, $orderModel = null, ?CourierProviderInterface $driver = null)
    {
        $lava = function_exists('lava_instance') ? lava_instance() : null;
        $this->db = $db ?? ($lava ? $lava->db : null);
        if ($orderModel) {
            $this->orderModel = $orderModel;
        } elseif ($lava) {
            $this->orderModel = $lava->Order_model ?? (isset($lava->call) ? $lava->call->model('Order_model') : null);
        }
        $this->driver = $driver ?? CourierProviderFactory::create();
    }

    public function book(int $orderId, string $actorType = 'system', ?int $actorId = null): array
    {
        $initialStatus = $this->claimBooking($orderId);
        $order = null;
        try {
            if ($initialStatus === 'failed') {
                $this->orderModel->set_status($orderId, 'booking', 'Staff retry requested; requesting courier quotation.', $actorType, $actorId);
            }
            $order = $this->db->raw(
                'SELECT o.*, pa.address_line AS pickup_address, pa.latitude AS pickup_lat, pa.longitude AS pickup_lng,
                        pa.contact_name AS pickup_contact_name, pa.contact_phone AS pickup_phone,
                        da.address_line AS dropoff_address, da.latitude AS dropoff_lat, da.longitude AS dropoff_lng,
                        da.contact_name AS dropoff_contact_name, da.contact_phone AS dropoff_phone,
                        p.weight_kg, p.description AS parcel_description
                 FROM orders o
                 INNER JOIN addresses pa ON pa.id = o.pickup_address_id
                 INNER JOIN addresses da ON da.id = o.dropoff_address_id
                 LEFT JOIN parcels p ON p.order_id = o.id
                 WHERE o.id = ? LIMIT 1',
                [$orderId]
            )->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new InvalidArgumentException('Order not found.');
            }

            $quote = $this->driver->getQuote($order);
            $quoteData = $quote['data'] ?? [];
            if (empty($quoteData['quotationId'])) {
                throw new RuntimeException('Courier quotation response did not include quotationId.');
            }
            $this->db->raw('UPDATE orders SET lalamove_quotation_id = ?, updated_at = ? WHERE id = ?', [$quoteData['quotationId'], date('Y-m-d H:i:s'), $orderId]);

            if ($initialStatus === 'paid') {
                $this->orderModel->set_status($orderId, 'booking', 'Courier quotation received; placing order.', $actorType, $actorId);
            }
        } catch (Throwable $e) {
            // No placement request has been made yet, so this reservation is safe to release.
            $this->releaseBooking($orderId);
            $fresh = $this->db->raw('SELECT status FROM orders WHERE id = ?', [$orderId])->fetch(PDO::FETCH_ASSOC);
            if ($fresh && in_array($fresh['status'], ['paid', 'booking'], true)) {
                $this->orderModel->set_status($orderId, 'failed', 'Courier quotation failed before order placement.', 'system', null);
            }
            throw $e;
        }

        try {
            $booked = $this->driver->placeOrder($order, $quote);
        } catch (Throwable $e) {
            $this->orderModel->set_status($orderId, 'failed', 'Courier order placement failed; provider state requires review.', 'system', null);
            // A timeout can mean the provider accepted the request. Keep the lock and quotation to block duplicate placement.
            throw $e;
        }
        $bookedData = $booked['data'] ?? [];
        if (empty($bookedData['orderId'])) {
            $this->orderModel->set_status($orderId, 'failed', 'Courier booking response did not include orderId; provider state requires review.', 'system', null);
            throw new RuntimeException('Courier booking response did not include orderId.');
        }

        $driverDetails = $bookedData['driver'] ?? [];
        $driverId = (string)($driverDetails['driverId'] ?? $bookedData['driverId'] ?? '');
        $this->db->raw('UPDATE orders SET lalamove_order_id = ?, share_link = ?, courier_driver_id = ?, courier_driver_name = ?, courier_driver_phone = ?, courier_driver_vehicle = ?, updated_at = ? WHERE id = ?', [
            $bookedData['orderId'], $bookedData['shareLink'] ?? null, $driverId !== '' ? $driverId : null,
            $driverDetails['name'] ?? null, $driverDetails['phone'] ?? null, $driverDetails['plateNumber'] ?? null,
            date('Y-m-d H:i:s'), $orderId
        ]);
        // The provider order ID is durable now, so another booking attempt is blocked by the order itself.
        $this->releaseBooking($orderId);

        if (empty($driverDetails['driverId']) && $driverId !== '') {
            try {
                $driverDetails = ($this->driver->driverDetails($driverId, (string)$bookedData['orderId']))['data'] ?? [];
                $this->db->raw('UPDATE orders SET courier_driver_id = ?, courier_driver_name = ?, courier_driver_phone = ?, courier_driver_vehicle = ?, updated_at = ? WHERE id = ?', [
                    $driverDetails['driverId'] ?? $driverId, $driverDetails['name'] ?? null,
                    $driverDetails['phone'] ?? null, $driverDetails['plateNumber'] ?? null,
                    date('Y-m-d H:i:s'), $orderId
                ]);
            } catch (Throwable $e) {
                error_log(sprintf(
                    'Courier driver details lookup failed for order %d (provider order %s): %s',
                    $orderId,
                    (string)$bookedData['orderId'],
                    $e->getMessage()
                ));
            }
        }
        $driverDetails = array_merge($driverDetails, ['driverId' => $driverId]);

        if ($this->driver instanceof MockCourierDriver) {
            $freshOrder = $this->db->raw('SELECT * FROM orders WHERE id = ?', [$orderId])->fetch(PDO::FETCH_ASSOC);
            $assignment = $this->driver->webhookEvent('rider_assigned', $freshOrder);
            require_once APP_DIR . 'controllers/Webhooks.php';
            Webhooks::processPayload($this->db, $this->orderModel, $assignment, 'system', null, true);
        }
        try {
            require_once APP_DIR . 'libraries/DeliveryNotificationService.php';
            (new DeliveryNotificationService($this->db))->dispatchPendingForOrder($orderId);
        } catch (Throwable $notificationError) {
            error_log('Courier booking status notification failed for order ' . $orderId . ': ' . $notificationError->getMessage());
        }

        return ['quotation' => $quote, 'order' => $booked];
    }

    private function claimBooking(int $orderId): string
    {
        $this->db->transaction();
        $insertAttempted = false;
        try {
            $order = $this->db->raw('SELECT status, fulfillment_mode, rider_id, lalamove_quotation_id, lalamove_order_id FROM orders WHERE id = ? FOR UPDATE', [$orderId])->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new InvalidArgumentException('Order not found.');
            }
            if (!in_array($order['status'], ['paid', 'failed'], true)
                || ($order['fulfillment_mode'] ?? null) !== 'lalamove'
                || trim((string)($order['rider_id'] ?? '')) !== ''
                || trim((string)($order['lalamove_order_id'] ?? '')) !== ''
                || trim((string)($order['lalamove_quotation_id'] ?? '')) !== '') {
                throw new DomainException('This order is not safe for automatic courier booking; review provider state first.');
            }
            $insertAttempted = true;
            $this->db->raw('INSERT INTO dispatch_recovery_locks (order_id, started_at) VALUES (?, ?)', [$orderId, date('Y-m-d H:i:s')]);
            $this->db->commit();
            return (string)$order['status'];
        } catch (Throwable $e) {
            $this->db->roll_back();
            if ($insertAttempted && $e instanceof PDOException && (string)$e->getCode() === '23000') {
                throw new DomainException('A courier booking attempt is already in progress or requires provider review.', 0, $e);
            }
            throw $e;
        }
    }

    private function releaseBooking(int $orderId): void
    {
        $this->db->raw('DELETE FROM dispatch_recovery_locks WHERE order_id = ?', [$orderId]);
    }
}
