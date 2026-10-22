<?php
/**
 * Courier webhook processing checks using a transactional in-memory database double.
 */

define('PREVENT_DIRECT_ACCESS', true);
define('APP_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR);

require_once APP_DIR . 'libraries/MockCourierDriver.php';

class Controller
{
}

require_once APP_DIR . 'controllers/Webhooks.php';

function t7_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function t7_test(string $name, callable $test): void
{
    echo "Running: {$name}... ";
    $test();
    echo "PASSED\n";
}

class CourierWebhookTestStatement
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

class CourierWebhookTestTable
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function insert(array $data)
    {
        return $this->db->insertEvent($data);
    }
}

class CourierWebhookTestDatabase
{
    public $orders = [];
    public $events = [];
    private $snapshots = [];

    public function transaction(): void
    {
        $this->snapshots[] = [$this->orders, $this->events];
    }

    public function commit(): void
    {
        array_pop($this->snapshots);
    }

    public function roll_back(): void
    {
        [$this->orders, $this->events] = array_pop($this->snapshots);
    }

    public function table(string $name): CourierWebhookTestTable
    {
        if ($name !== 'webhook_events') {
            throw new RuntimeException('Unexpected table in webhook test: ' . $name);
        }
        return new CourierWebhookTestTable($this);
    }

    public function insertEvent(array $data): int
    {
        foreach ($this->events as $event) {
            if ($event['provider'] === $data['provider'] && $event['event_id'] === $data['event_id']) {
                throw new RuntimeException('Duplicate webhook event.');
            }
        }
        $id = count($this->events) + 1;
        $data['id'] = $id;
        $this->events[$id] = $data;
        return $id;
    }

    public function raw(string $sql, array $params = []): CourierWebhookTestStatement
    {
        if (str_starts_with($sql, 'SELECT id, status FROM webhook_events')) {
            foreach ($this->events as $event) {
                if ($event['provider'] === $params[0] && $event['event_id'] === $params[1]) {
                    return new CourierWebhookTestStatement(['id' => $event['id'], 'status' => $event['status']]);
                }
            }
            return new CourierWebhookTestStatement(false);
        }
        if (str_starts_with($sql, 'SELECT id, status, order_no, lalamove_order_id FROM orders WHERE lalamove_order_id')) {
            foreach ($this->orders as $order) {
                if (($order['lalamove_order_id'] ?? null) === $params[0]) {
                    return new CourierWebhookTestStatement($order);
                }
            }
            return new CourierWebhookTestStatement(false);
        }
        if (str_starts_with($sql, 'SELECT id, status, order_no, lalamove_order_id FROM orders WHERE id')) {
            return new CourierWebhookTestStatement($this->orders[(int)$params[0]] ?? false);
        }
        if (str_starts_with($sql, 'UPDATE webhook_events SET status')) {
            foreach ($this->events as &$event) {
                if ($event['provider'] === $params[2] && $event['event_id'] === $params[3]) {
                    $event['status'] = $params[0];
                    $event['processed_at'] = $params[1];
                }
            }
            unset($event);
            return new CourierWebhookTestStatement(true);
        }
        throw new RuntimeException('Unexpected SQL in webhook test: ' . $sql);
    }
}

class CourierWebhookTestOrderModel
{
    private $db;
    private $allowed = [
        'paid' => ['booking', 'cancelled', 'failed'],
        'booking' => ['rider_assigned', 'cancelled', 'failed'],
        'rider_assigned' => ['picked_up', 'cancelled', 'failed'],
        'picked_up' => ['delivered', 'failed'],
        'failed' => ['booking'],
        'delivered' => [],
        'cancelled' => []
    ];
    public $history = [];

    public function __construct(CourierWebhookTestDatabase $db)
    {
        $this->db = $db;
    }

    public function set_status(int $orderId, string $newStatus, ?string $notes = null, string $actorType = 'system', ?int $actorId = null, array $extraData = []): bool
    {
        if (!isset($this->db->orders[$orderId])) {
            throw new InvalidArgumentException('Missing test order.');
        }
        $current = $this->db->orders[$orderId]['status'];
        if (!in_array($newStatus, $this->allowed[$current] ?? [], true)) {
            throw new DomainException("Illegal order status transition from '{$current}' to '{$newStatus}'.");
        }
        $this->db->orders[$orderId] = array_merge($this->db->orders[$orderId], $extraData, ['status' => $newStatus]);
        $this->history[] = ['order_id' => $orderId, 'from' => $current, 'to' => $newStatus, 'actor_type' => $actorType];
        return true;
    }
}

function t7_order(int $id, string $status): array
{
    return ['id' => $id, 'status' => $status, 'order_no' => 'DLV-TEST-' . $id, 'lalamove_order_id' => 'MOCK-ORD-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT)];
}

function t7_process(CourierWebhookTestDatabase $db, CourierWebhookTestOrderModel $model, array $payload): array
{
    return Webhooks::processPayload($db, $model, $payload, 'admin', 1, true);
}

try {
    t7_test('all mock courier events update valid statuses and write driver snapshots', function (): void {
        $cases = [
            ['id' => 1, 'before' => 'booking', 'event' => 'rider_assigned', 'after' => 'rider_assigned'],
            ['id' => 2, 'before' => 'rider_assigned', 'event' => 'picked_up', 'after' => 'picked_up'],
            ['id' => 3, 'before' => 'picked_up', 'event' => 'delivered', 'after' => 'delivered'],
            ['id' => 4, 'before' => 'booking', 'event' => 'failed', 'after' => 'failed'],
            ['id' => 5, 'before' => 'rider_assigned', 'event' => 'cancelled', 'after' => 'cancelled']
        ];
        foreach ($cases as $case) {
            $db = new CourierWebhookTestDatabase();
            $db->orders[$case['id']] = t7_order($case['id'], $case['before']);
            $model = new CourierWebhookTestOrderModel($db);
            $payload = (new MockCourierDriver())->webhookEvent($case['event'], $db->orders[$case['id']], ['event_id' => 'T7-' . $case['id']]);
            $result = t7_process($db, $model, $payload);
            t7_assert($result['status'] === $case['after'], 'Wrong processor result for ' . $case['event']);
            t7_assert($db->orders[$case['id']]['status'] === $case['after'], 'Order state was not persisted for ' . $case['event']);
            t7_assert(count($model->history) === 1, 'Expected exactly one delivery-history transition.');
            t7_assert(($db->events[1]['status'] ?? '') === 'processed', 'Webhook event was not marked processed.');
            if ($case['event'] === 'rider_assigned') {
                t7_assert(!empty($db->orders[$case['id']]['courier_driver_id']) && !empty($db->orders[$case['id']]['courier_driver_name']), 'Driver snapshot was not stored.');
            }
            if ($case['event'] === 'cancelled') {
                t7_assert(($db->orders[$case['id']]['cancel_reason'] ?? '') === 'Mock order cancelled.', 'Cancellation reason was not stored.');
            }
        }
    });

    t7_test('duplicate event IDs do not repeat order or history mutations', function (): void {
        $db = new CourierWebhookTestDatabase();
        $db->orders[12] = t7_order(12, 'booking');
        $model = new CourierWebhookTestOrderModel($db);
        $payload = (new MockCourierDriver())->webhookEvent('rider_assigned', $db->orders[12], ['event_id' => 'T7-DUPLICATE']);
        t7_process($db, $model, $payload);
        $duplicate = t7_process($db, $model, $payload);
        t7_assert(!empty($duplicate['duplicate']), 'Repeated event was not reported as a duplicate.');
        t7_assert(count($model->history) === 1 && count($db->events) === 1, 'Duplicate event repeated side effects.');
    });

    t7_test('illegal state transition rolls back event and order mutations', function (): void {
        $db = new CourierWebhookTestDatabase();
        $db->orders[13] = t7_order(13, 'delivered');
        $model = new CourierWebhookTestOrderModel($db);
        $payload = (new MockCourierDriver())->webhookEvent('picked_up', $db->orders[13], ['event_id' => 'T7-ILLEGAL']);
        try {
            t7_process($db, $model, $payload);
            throw new RuntimeException('Illegal transition was accepted.');
        } catch (DomainException $e) {
            t7_assert(str_contains($e->getMessage(), 'Illegal order status transition'), 'Unexpected transition failure.');
        }
        t7_assert($db->orders[13]['status'] === 'delivered' && $db->events === [] && $model->history === [], 'Failed transition left partial state behind.');
    });

    t7_test('unknown external orders are recorded as ignored', function (): void {
        $db = new CourierWebhookTestDatabase();
        $model = new CourierWebhookTestOrderModel($db);
        $payload = (new MockCourierDriver())->webhookEvent('picked_up', t7_order(14, 'rider_assigned'), ['event_id' => 'T7-UNKNOWN']);
        $result = t7_process($db, $model, $payload);
        t7_assert(!empty($result['ignored']) && ($db->events[1]['status'] ?? '') === 'ignored', 'Unknown order was not safely recorded as ignored.');
    });

    t7_test('malformed event payload is rejected before persistence', function (): void {
        $db = new CourierWebhookTestDatabase();
        $model = new CourierWebhookTestOrderModel($db);
        try {
            t7_process($db, $model, ['data' => ['orderId' => 'MOCK-ORD-1', 'eventType' => 'UNEXPECTED', 'eventId' => 'T7-BAD']]);
            throw new RuntimeException('Malformed event was accepted.');
        } catch (InvalidArgumentException $e) {
            t7_assert($db->events === [], 'Malformed event was persisted.');
        }
    });

    t7_test('inbound processing requires production, real driver, secret, and partner confirmation', function (): void {
        $originalDriver = getenv('COURIER_DRIVER');
        $originalEnvironment = getenv('APP_ENV');
        $originalSecret = getenv('LALAMOVE_WEBHOOK_SECRET');
        $originalConfirmed = getenv('LALAMOVE_WEBHOOK_AUTH_CONFIRMED');
        try {
            putenv('COURIER_DRIVER=mock');
            putenv('APP_ENV=development');
            putenv('LALAMOVE_WEBHOOK_SECRET=test-secret');
            putenv('LALAMOVE_WEBHOOK_AUTH_CONFIRMED=0');
            t7_assert(!Webhooks::inboundProcessingEnabled(), 'Inbound processing must be disabled in default mock/development mode.');
            putenv('COURIER_DRIVER=lalamove');
            putenv('APP_ENV=production');
            t7_assert(!Webhooks::inboundProcessingEnabled(), 'Partner confirmation must be required even in production mode.');
            putenv('LALAMOVE_WEBHOOK_AUTH_CONFIRMED=1');
            t7_assert(Webhooks::inboundProcessingEnabled(), 'Explicit production enablement should be recognized only for the confirmed partner configuration.');
            putenv('LALAMOVE_WEBHOOK_SECRET=');
            t7_assert(!Webhooks::inboundProcessingEnabled(), 'A shared secret must be required.');
        } finally {
            foreach ([
                'COURIER_DRIVER' => $originalDriver,
                'APP_ENV' => $originalEnvironment,
                'LALAMOVE_WEBHOOK_SECRET' => $originalSecret,
                'LALAMOVE_WEBHOOK_AUTH_CONFIRMED' => $originalConfirmed
            ] as $name => $value) {
                $value === false ? putenv($name) : putenv($name . '=' . $value);
            }
        }
    });

    echo "All courier webhook processing checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAILED: {$e->getMessage()}\n");
    exit(1);
}
