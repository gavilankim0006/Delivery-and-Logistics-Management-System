<?php
/**
 * Isolated courier contract checks; no database or external credentials required.
 */

define('PREVENT_DIRECT_ACCESS', true);
define('APP_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR);

require_once APP_DIR . 'libraries/CourierProviderFactory.php';

function t6_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function t6_test(string $name, callable $test): void
{
    echo "Running: {$name}... ";
    $test();
    echo "PASSED\n";
}

try {
    putenv('COURIER_DRIVER');
    putenv('LALAMOVE_API_KEY');
    putenv('LALAMOVE_API_SECRET');

    t6_test('mock is the default without Lalamove credentials', function (): void {
        t6_assert(CourierProviderFactory::driverName() === 'mock', 'Default courier driver should be mock.');
        t6_assert(CourierProviderFactory::create() instanceof MockCourierDriver, 'Factory did not return mock driver.');
    });

    t6_test('mock quote and booking follow v3 response envelopes', function (): void {
        $driver = new MockCourierDriver();
        $order = ['id' => 12, 'order_no' => 'DLV-TEST-0012', 'distance_km' => 6.25, 'delivery_fee' => 140, 'pickup_address' => 'Makati', 'dropoff_address' => 'BGC', 'weight_kg' => 2];
        $quote = $driver->getQuote($order);
        t6_assert(isset($quote['data']['quotationId'], $quote['data']['priceBreakdown']['total'], $quote['data']['stops'][0]['coordinates']['lat']), 'Quotation response is missing v3 fields.');
        $booked = $driver->placeOrder($order, $quote);
        t6_assert(isset($booked['data']['orderId'], $booked['data']['driverId'], $booked['data']['shareLink'], $booked['data']['driver']['plateNumber']), 'Place-order response is missing v3 fields.');
    });

    t6_test('mock event payload maps each requested lifecycle action', function (): void {
        $driver = new MockCourierDriver();
        $order = ['id' => 12, 'order_no' => 'DLV-TEST-0012', 'lalamove_order_id' => 'MOCK-ORD-000012', 'lalamove_quotation_id' => 'MOCK-QUO-000012'];
        $expected = [
            'rider_assigned' => ['DRIVER_ASSIGNED', 'ON_GOING'],
            'picked_up' => ['ORDER_STATUS_CHANGED', 'PICKED_UP'],
            'delivered' => ['ORDER_STATUS_CHANGED', 'COMPLETED'],
            'failed' => ['ORDER_STATUS_CHANGED', 'REJECTED'],
            'cancelled' => ['ORDER_STATUS_CHANGED', 'CANCELED']
        ];
        foreach ($expected as $action => [$type, $status]) {
            $payload = $driver->webhookEvent($action, $order);
            t6_assert(($payload['data']['eventType'] ?? '') === $type, "Wrong provider event type for {$action}.");
            t6_assert(($payload['data']['status'] ?? '') === $status, "Wrong provider status for {$action}.");
            t6_assert(!empty($payload['data']['eventId']) && !empty($payload['data']['driver']['driverId']), "Missing event/driver data for {$action}.");
        }
    });

    t6_test('real driver remains opt-in and rejects missing secrets', function (): void {
        putenv('COURIER_DRIVER=lalamove');
        try {
            CourierProviderFactory::create();
            throw new RuntimeException('Expected missing credentials exception.');
        } catch (RuntimeException $e) {
            t6_assert(str_contains($e->getMessage(), 'LALAMOVE_API_KEY'), 'Missing-credentials error is not actionable.');
        }
    });

    echo "All courier contract checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAILED: {$e->getMessage()}\n");
    exit(1);
}
