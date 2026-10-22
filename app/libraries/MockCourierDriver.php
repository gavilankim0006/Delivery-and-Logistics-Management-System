<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/CourierProviderInterface.php';

class MockCourierDriver implements CourierProviderInterface
{
    public function getQuote(array $order): array
    {
        $number = (int)($order['id'] ?? 0);
        $orderNo = (string)($order['order_no'] ?? ('ORDER-' . $number));
        $distance = number_format((float)($order['distance_km'] ?? 0), 2, '.', '');
        $amount = number_format((float)($order['delivery_fee'] ?? 0), 2, '.', '');
        $pickup = (string)($order['pickup_address'] ?? 'Pickup location');
        $dropoff = (string)($order['dropoff_address'] ?? 'Drop-off location');

        return ['data' => [
            'quotationId' => sprintf('MOCK-QUO-%06d', $number),
            'scheduleAt' => date(DATE_ATOM),
            'expiresAt' => date(DATE_ATOM, time() + 1800),
            'serviceType' => 'MOTORCYCLE',
            'specialRequests' => [],
            'language' => 'en_PH',
            'stops' => [
                ['stopId' => 'PICKUP-' . $number, 'coordinates' => ['lat' => '14.5547', 'lng' => '121.0244'], 'address' => $pickup],
                ['stopId' => 'DROPOFF-' . $number, 'coordinates' => ['lat' => '14.5869', 'lng' => '121.0614'], 'address' => $dropoff]
            ],
            'isRouteOptimized' => false,
            'priceBreakdown' => [
                'base' => $amount, 'specialRequests' => '0.00', 'vat' => '0.00',
                'totalBeforeOptimization' => $amount, 'totalExcludePriorityFee' => $amount,
                'total' => $amount, 'currency' => 'PHP'
            ],
            'item' => ['weight' => (string)($order['weight_kg'] ?? '1.00'), 'categories' => ['PARCEL']],
            'distance' => ['value' => $distance, 'unit' => 'km'],
            'metadata' => ['source' => 'mock', 'orderNo' => $orderNo]
        ]];
    }

    public function placeOrder(array $order, array $quote): array
    {
        $number = (int)($order['id'] ?? 0);
        $quotationId = (string)($quote['data']['quotationId'] ?? sprintf('MOCK-QUO-%06d', $number));
        $driver = $this->driverDetails(sprintf('MOCK-DRV-%04d', (($number - 1) % 3) + 1))['data'];

        return ['data' => [
            'orderId' => sprintf('MOCK-ORD-%06d', $number),
            'quotationId' => $quotationId,
            'priceBreakdown' => $quote['data']['priceBreakdown'],
            'driverId' => $driver['driverId'],
            'shareLink' => 'https://mock.lalamove.local/track/' . rawurlencode(sprintf('MOCK-ORD-%06d', $number)),
            'status' => 'ON_GOING',
            'distance' => $quote['data']['distance'],
            'stops' => $quote['data']['stops'],
            'metadata' => ['source' => 'mock', 'orderNo' => $order['order_no'] ?? null],
            'driver' => $driver
        ]];
    }

    public function cancelOrder(string $orderId, string $reason = ''): array
    {
        return ['data' => ['orderId' => $orderId, 'status' => 'CANCELED', 'reason' => $reason]];
    }

    public function driverDetails(string $driverId, ?string $orderId = null): array
    {
        $drivers = [
            'MOCK-DRV-0001' => ['name' => 'Miguel Santos', 'phone' => '+639171234501', 'plateNumber' => 'MC-1001'],
            'MOCK-DRV-0002' => ['name' => 'Andrea Reyes', 'phone' => '+639171234502', 'plateNumber' => 'MC-1002'],
            'MOCK-DRV-0003' => ['name' => 'Paolo Cruz', 'phone' => '+639171234503', 'plateNumber' => 'MC-1003']
        ];
        $driver = $drivers[$driverId] ?? $drivers['MOCK-DRV-0001'];

        return ['data' => array_merge($driver, [
            'driverId' => $driverId,
            'photo' => null,
            'coordinates' => ['lat' => '14.5547', 'lng' => '121.0244', 'updatedAt' => date(DATE_ATOM)]
        ])];
    }

    public function webhookEvent(string $eventType, array $order, array $extra = []): array
    {
        $number = (int)($order['id'] ?? 0);
        $eventId = (string)($extra['event_id'] ?? ('MOCK-EVT-' . $number . '-' . strtoupper($eventType) . '-' . bin2hex(random_bytes(4))));
        $providerOrderId = (string)($order['lalamove_order_id'] ?? sprintf('MOCK-ORD-%06d', $number));
        $statusMap = [
            'rider_assigned' => 'ON_GOING', 'picked_up' => 'PICKED_UP', 'delivered' => 'COMPLETED',
            'failed' => 'REJECTED', 'cancelled' => 'CANCELED'
        ];
        if (!isset($statusMap[$eventType])) {
            throw new InvalidArgumentException('Unsupported courier simulator event.');
        }

        $driver = $this->driverDetails((string)($order['courier_driver_id'] ?? sprintf('MOCK-DRV-%04d', (($number - 1) % 3) + 1)))['data'];
        $data = [
            'eventId' => $eventId,
            'eventType' => $eventType === 'rider_assigned' ? 'DRIVER_ASSIGNED' : 'ORDER_STATUS_CHANGED',
            'orderId' => $providerOrderId,
            'quotationId' => (string)($order['lalamove_quotation_id'] ?? sprintf('MOCK-QUO-%06d', $number)),
            'status' => $statusMap[$eventType],
            'updatedAt' => date(DATE_ATOM),
            'driver' => $driver,
            'metadata' => ['internalOrderId' => $number, 'source' => 'mock_simulator']
        ];
        if ($eventType === 'failed') {
            $data['failureReason'] = (string)($extra['reason'] ?? 'Mock driver assignment failed.');
        }
        if ($eventType === 'cancelled') {
            $data['cancelReason'] = (string)($extra['reason'] ?? 'Mock order cancelled.');
        }

        return ['data' => $data];
    }
}
