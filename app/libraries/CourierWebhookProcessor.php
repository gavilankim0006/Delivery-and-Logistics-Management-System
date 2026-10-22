<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class CourierWebhookProcessor
{
    private $db;
    private $orderModel;

    public function __construct($db, $orderModel)
    {
        $this->db = $db;
        $this->orderModel = $orderModel;
    }

    public function process(array $payload, string $actorType = 'system', ?int $actorId = null, bool $persistEvent = true): array
    {
        $data = $payload['data'] ?? null;
        if (!is_array($data) || empty($data['orderId'])) {
            throw new InvalidArgumentException('Invalid Lalamove webhook payload.');
        }
        $payloadEventType = (string)($data['eventType'] ?? $payload['eventType'] ?? '');
        if (isset($payload['eventType'], $data['eventType']) && $payload['eventType'] !== $data['eventType']) {
            throw new InvalidArgumentException('Webhook event type does not match its data.');
        }
        $eventStatus = strtoupper((string)($data['status'] ?? ''));
        if (!in_array($payloadEventType, ['DRIVER_ASSIGNED', 'ORDER_STATUS_CHANGED'], true)) {
            throw new InvalidArgumentException('Unsupported Lalamove webhook event type.');
        }
        if ($payloadEventType === 'ORDER_STATUS_CHANGED' && !in_array($eventStatus, ['PICKED_UP', 'COMPLETED', 'CANCELED', 'REJECTED', 'EXPIRED', 'FAILED', 'ON_GOING'], true)) {
            throw new InvalidArgumentException('Unsupported Lalamove order status.');
        }

        $eventId = (string)($data['eventId'] ?? $data['event_id'] ?? $data['id'] ?? '');
        if ($eventId === '' || strlen($eventId) > 150) {
            throw new InvalidArgumentException('Webhook event ID is missing or too long.');
        }
        $eventType = $payloadEventType;
        $db = $this->db;
        $db->transaction();
        try {
            $existing = $db->raw('SELECT id, status FROM webhook_events WHERE provider = ? AND event_id = ? LIMIT 1 FOR UPDATE', ['lalamove', $eventId])->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $db->roll_back();
            throw $e;
        }
        if ($existing && in_array($existing['status'], ['processed', 'ignored', 'failed'], true)) {
            $db->commit();
            return ['duplicate' => true, 'status' => 'duplicate'];
        }
        if (!$existing && $persistEvent) {
            try {
                $db->table('webhook_events')->insert([
                    'provider' => 'lalamove', 'event_id' => $eventId, 'event_type' => $eventType,
                    'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES), 'status' => 'received', 'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (Throwable $e) {
                $existing = $db->raw('SELECT id, status FROM webhook_events WHERE provider = ? AND event_id = ? LIMIT 1', ['lalamove', $eventId])->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    $db->commit();
                    return ['duplicate' => true, 'status' => 'duplicate'];
                }
                $db->roll_back();
                throw $e;
            }
        }

        $order = $db->raw('SELECT id, status, order_no, lalamove_order_id FROM orders WHERE lalamove_order_id = ? LIMIT 1 FOR UPDATE', [(string)$data['orderId']])->fetch(PDO::FETCH_ASSOC);
        $metadataOrderId = (int)($data['metadata']['internalOrderId'] ?? 0);
        if (!$order && $metadataOrderId > 0 && ($data['metadata']['source'] ?? '') === 'mock_simulator') {
            $order = $db->raw('SELECT id, status, order_no, lalamove_order_id FROM orders WHERE id = ? LIMIT 1 FOR UPDATE', [$metadataOrderId])->fetch(PDO::FETCH_ASSOC);
        }
        if (!$order) {
            $this->markEvent($eventId, 'ignored');
            $db->commit();
            return ['duplicate' => false, 'ignored' => true, 'status' => 'unknown_order'];
        }

        if ($eventType === 'DRIVER_ASSIGNED' && empty($data['driver']['driverId'])) {
            $db->roll_back();
            throw new InvalidArgumentException('Driver assignment event must include driver details.');
        }
        $mapped = $this->mapEvent($eventType, strtoupper((string)($data['status'] ?? '')), (array)($data['driver'] ?? []));
        if ($mapped === null) {
            $this->markEvent($eventId, 'ignored');
            $db->commit();
            return ['duplicate' => false, 'ignored' => true, 'status' => 'unsupported_event'];
        }

        [$newStatus, $driver] = $mapped;
        $extraData = [];
        if ($newStatus === 'rider_assigned') {
            $extraData = [
                'courier_driver_id' => $driver['driverId'] ?? null,
                'courier_driver_name' => $driver['name'] ?? null,
                'courier_driver_phone' => $driver['phone'] ?? null,
                'courier_driver_vehicle' => $driver['plateNumber'] ?? null
            ];
        }
        if ($newStatus === 'cancelled') {
            $extraData['cancel_reason'] = substr((string)($data['cancelReason'] ?? 'Courier cancelled the order.'), 0, 1000);
        }

        try {
            $this->orderModel->set_status(
                (int)$order['id'],
                $newStatus,
                $this->eventNotes($newStatus, $data),
                $actorType,
                $actorId,
                $extraData
            );
            $this->markEvent($eventId, 'processed');
            $db->commit();
            return ['duplicate' => false, 'order_id' => (int)$order['id'], 'status' => $newStatus];
        } catch (Throwable $e) {
            $db->roll_back();
            throw $e;
        }
    }

    private function mapEvent(string $eventType, string $providerStatus, array $driver): ?array
    {
        if ($eventType === 'DRIVER_ASSIGNED' || $providerStatus === 'ON_GOING') {
            return ['rider_assigned', $driver];
        }
        $statusMap = [
            'PICKED_UP' => 'picked_up', 'COMPLETED' => 'delivered', 'CANCELED' => 'cancelled',
            'REJECTED' => 'failed', 'EXPIRED' => 'failed', 'FAILED' => 'failed'
        ];
        return isset($statusMap[$providerStatus]) ? [$statusMap[$providerStatus], $driver] : null;
    }

    private function eventNotes(string $status, array $data): string
    {
        $notes = [
            'rider_assigned' => 'Lalamove courier driver assigned.',
            'picked_up' => 'Courier picked up the parcel.',
            'delivered' => 'Courier marked the parcel delivered.',
            'failed' => (string)($data['failureReason'] ?? 'Courier marked the delivery as failed.'),
            'cancelled' => (string)($data['cancelReason'] ?? 'Courier cancelled the order.')
        ];
        return substr($notes[$status] ?? 'Courier status updated.', 0, 1000);
    }

    private function markEvent(string $eventId, string $status): void
    {
        $this->db->raw('UPDATE webhook_events SET status = ?, processed_at = ? WHERE provider = ? AND event_id = ?', [$status, date('Y-m-d H:i:s'), 'lalamove', $eventId]);
    }
}
