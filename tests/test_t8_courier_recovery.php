<?php
/**
 * Isolated courier cancellation and post-booking recovery checks.
 */

define('PREVENT_DIRECT_ACCESS', true);
define('APP_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR);

require_once APP_DIR . 'libraries/OrderCancellationService.php';
require_once APP_DIR . 'libraries/CourierBookingService.php';

function t8_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function t8_test(string $name, callable $test): void
{
    echo "Running: {$name}... ";
    $test();
    echo "PASSED\n";
}

class CourierRecoveryTestOrderModel
{
    public array $order;
    public array $history = [];
    public array $statusChanges = [];
    public int $cancelCalls = 0;

    public function __construct(array $order)
    {
        $this->order = $order;
    }

    public function assert_cancellation_allowed(int $orderId): array
    {
        if ((int)$this->order['id'] !== $orderId) {
            throw new InvalidArgumentException('Order not found.');
        }
        if (!in_array($this->order['status'], ['draft', 'pending_payment', 'paid', 'booking', 'rider_assigned'], true)) {
            throw new DomainException('Order cannot be cancelled from its current status.');
        }
        return $this->order;
    }

    public function cancel_order(int $orderId, string $reason, string $actorType = 'customer', ?int $actorId = null): bool
    {
        $this->cancelCalls++;
        $from = $this->order['status'];
        $this->order['status'] = 'cancelled';
        $this->order['cancel_reason'] = $reason;
        $this->history[] = ['from' => $from, 'to' => 'cancelled', 'actor' => $actorType, 'actor_id' => $actorId];
        return true;
    }

    public function set_status(int $orderId, string $status, ?string $notes = null, string $actorType = 'system', ?int $actorId = null, array $extraData = []): bool
    {
        $from = $this->order['status'];
        $this->order = array_merge($this->order, $extraData, ['status' => $status]);
        $this->statusChanges[] = [$from, $status];
        return true;
    }
}

class CourierRecoveryTestDriver implements CourierProviderInterface
{
    public int $cancelCalls = 0;
    public int $quoteCalls = 0;
    public int $placeCalls = 0;
    public int $driverDetailsCalls = 0;
    public $onCancel = null;
    public $onDriverDetails = null;
    public bool $throwOnCancel = false;
    public bool $throwOnDriverDetails = false;

    public function getQuote(array $order): array
    {
        $this->quoteCalls++;
        return ['data' => ['quotationId' => 'TEST-QUO-1']];
    }

    public function placeOrder(array $order, array $quote): array
    {
        $this->placeCalls++;
        return ['data' => [
            'orderId' => 'TEST-ORD-1',
            'quotationId' => $quote['data']['quotationId'],
            'driverId' => 'TEST-DRV-1',
            'shareLink' => 'https://example.test/track/TEST-ORD-1'
        ]];
    }

    public function cancelOrder(string $orderId, string $reason = ''): array
    {
        $this->cancelCalls++;
        if (is_callable($this->onCancel)) {
            ($this->onCancel)($orderId, $reason);
        }
        if ($this->throwOnCancel) {
            throw new RuntimeException('Provider timeout.');
        }
        return ['data' => ['orderId' => $orderId, 'status' => 'CANCELED']];
    }

    public function driverDetails(string $driverId, ?string $orderId = null): array
    {
        $this->driverDetailsCalls++;
        if (is_callable($this->onDriverDetails)) {
            ($this->onDriverDetails)($driverId, $orderId);
        }
        if ($this->throwOnDriverDetails) {
            throw new RuntimeException('Driver details unavailable.');
        }
        return ['data' => ['driverId' => $driverId, 'name' => 'Test Driver']];
    }

    public function webhookEvent(string $eventType, array $order, array $extra = []): array
    {
        throw new LogicException('Synthetic events are not used in this test.');
    }
}

class CourierRecoveryTestStatement
{
    private $row;

    public function __construct($row)
    {
        $this->row = $row;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT)
    {
        return $this->row ?: false;
    }
}

class CourierRecoveryTestDatabase
{
    public array $order;
    public array $updates = [];
    public bool $locked = false;
    public bool $transactionOpen = false;

    public function __construct(array $order)
    {
        $this->order = $order;
    }

    public function transaction(): void { $this->transactionOpen = true; }
    public function commit(): void { $this->transactionOpen = false; }
    public function roll_back(): void { $this->transactionOpen = false; }

    public function raw(string $sql, array $params = []): CourierRecoveryTestStatement
    {
        if (str_contains($sql, 'SELECT status, lalamove_quotation_id, lalamove_order_id FROM orders')) {
            return new CourierRecoveryTestStatement($this->order);
        }
        if (str_contains($sql, 'INSERT INTO dispatch_recovery_locks')) {
            if ($this->locked) throw new PDOException('Duplicate recovery lock.', 23000);
            $this->locked = true;
            return new CourierRecoveryTestStatement(true);
        }
        if (str_starts_with($sql, 'DELETE FROM dispatch_recovery_locks')) {
            $this->locked = false;
            return new CourierRecoveryTestStatement(true);
        }
        if (str_contains($sql, 'FROM orders o') && str_contains($sql, 'INNER JOIN addresses pa')) {
            return new CourierRecoveryTestStatement($this->order);
        }
        if (str_starts_with($sql, 'SELECT status FROM orders')) {
            return new CourierRecoveryTestStatement(['status' => $this->order['status']]);
        }
        if (str_starts_with($sql, 'UPDATE orders SET')) {
            $this->updates[] = ['sql' => $sql, 'params' => $params];
            if (str_contains($sql, 'lalamove_quotation_id')) {
                $this->order['lalamove_quotation_id'] = $params[0];
            } elseif (str_contains($sql, 'lalamove_order_id')) {
                $this->order['lalamove_order_id'] = $params[0];
                $this->order['share_link'] = $params[1];
                $this->order['courier_driver_id'] = $params[2];
                $this->order['courier_driver_name'] = $params[3];
                $this->order['courier_driver_phone'] = $params[4];
                $this->order['courier_driver_vehicle'] = $params[5];
            } elseif (str_contains($sql, 'courier_driver_id')) {
                $this->order['courier_driver_id'] = $params[0];
                $this->order['courier_driver_name'] = $params[1];
                $this->order['courier_driver_phone'] = $params[2];
                $this->order['courier_driver_vehicle'] = $params[3];
            }
            return new CourierRecoveryTestStatement(true);
        }
        throw new RuntimeException('Unexpected SQL in recovery test: ' . $sql);
    }
}

function t8_booking_order(): array
{
    return [
        'id' => 21, 'order_no' => 'DLV-TEST-0021', 'status' => 'paid',
        'pickup_address' => 'Makati', 'pickup_lat' => 14.55, 'pickup_lng' => 121.02,
        'pickup_contact_name' => 'Sender', 'pickup_phone' => '+639171234567',
        'dropoff_address' => 'BGC', 'dropoff_lat' => 14.58, 'dropoff_lng' => 121.06,
        'dropoff_contact_name' => 'Receiver', 'dropoff_phone' => '+639171234568',
        'weight_kg' => 1, 'parcel_description' => 'Test parcel', 'distance_km' => 5, 'delivery_fee' => 100
    ];
}

try {
    t8_test('provider cancellation completes before local cancellation', function (): void {
        $model = new CourierRecoveryTestOrderModel(['id' => 1, 'status' => 'rider_assigned', 'lalamove_order_id' => 'PROVIDER-1']);
        $driver = new CourierRecoveryTestDriver();
        $driver->onCancel = function () use ($model): void {
            t8_assert($model->order['status'] === 'rider_assigned' && $model->history === [], 'Local state changed before provider cancellation.');
        };
        $service = new OrderCancellationService($model, $driver);
        $service->cancel(1, 'Customer requested', 'customer', 10);
        t8_assert($driver->cancelCalls === 1 && $model->cancelCalls === 1, 'Expected provider then local cancellation calls.');
        t8_assert($model->order['status'] === 'cancelled' && count($model->history) === 1, 'Local cancellation state/history missing.');
    });

    t8_test('provider cancellation failure leaves local order and history unchanged', function (): void {
        $model = new CourierRecoveryTestOrderModel(['id' => 2, 'status' => 'booking', 'lalamove_order_id' => 'PROVIDER-2']);
        $driver = new CourierRecoveryTestDriver();
        $driver->throwOnCancel = true;
        try {
            (new OrderCancellationService($model, $driver))->cancel(2, 'Retry cancellation');
            throw new RuntimeException('Provider failure was not surfaced.');
        } catch (RuntimeException $e) {
            t8_assert(str_contains($e->getMessage(), 'could not be confirmed'), 'Unexpected cancellation failure message.');
        }
        t8_assert($driver->cancelCalls === 1 && $model->cancelCalls === 0, 'Local cancellation ran despite provider failure.');
        t8_assert($model->order['status'] === 'booking' && $model->history === [], 'Provider failure mutated local state/history.');
    });

    t8_test('local-only cancellation skips provider and ineligible order fails before provider call', function (): void {
        $local = new CourierRecoveryTestOrderModel(['id' => 3, 'status' => 'draft', 'lalamove_order_id' => null]);
        (new OrderCancellationService($local))->cancel(3, 'No courier booked');
        t8_assert($local->order['status'] === 'cancelled' && count($local->history) === 1, 'Local-only cancellation failed.');

        $ineligible = new CourierRecoveryTestOrderModel(['id' => 4, 'status' => 'delivered', 'lalamove_order_id' => 'PROVIDER-4']);
        $driver = new CourierRecoveryTestDriver();
        try {
            (new OrderCancellationService($ineligible, $driver))->cancel(4, 'Too late');
            throw new RuntimeException('Delivered order cancellation was accepted.');
        } catch (DomainException $e) {
            t8_assert($driver->cancelCalls === 0 && $ineligible->cancelCalls === 0, 'Ineligible order made a provider/local cancellation call.');
        }
    });

    t8_test('driver-details failure retains provider booking without retrying placeOrder', function (): void {
        $order = t8_booking_order();
        $db = new CourierRecoveryTestDatabase($order);
        $model = new CourierRecoveryTestOrderModel($order);
        $driver = new CourierRecoveryTestDriver();
        $driver->throwOnDriverDetails = true;
        $driver->onDriverDetails = function () use ($db): void {
            t8_assert($db->order['lalamove_order_id'] === 'TEST-ORD-1', 'Driver lookup ran before provider order ID persistence.');
        };
        $result = (new CourierBookingService($db, $model, $driver))->book(21, 'admin', 1);

        t8_assert(($result['order']['data']['orderId'] ?? '') === 'TEST-ORD-1', 'Booking response was not returned.');
        t8_assert($driver->placeCalls === 1 && $driver->driverDetailsCalls === 1, 'Unexpected booking or driver lookup call count.');
        t8_assert($db->order['lalamove_order_id'] === 'TEST-ORD-1', 'Provider order ID was not persisted before details lookup.');
        t8_assert($db->order['share_link'] === 'https://example.test/track/TEST-ORD-1', 'Share link was not persisted.');
        t8_assert($db->order['courier_driver_id'] === 'TEST-DRV-1', 'Top-level driver ID was not persisted.');
        t8_assert($model->order['status'] === 'booking', 'Driver-details failure incorrectly failed or advanced the booking.');
        t8_assert($model->statusChanges === [['paid', 'booking']], 'Unexpected state changes occurred.');
    });

    echo "All courier recovery checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAILED: {$e->getMessage()}\n");
    exit(1);
}
