<?php

define('PREVENT_DIRECT_ACCESS', true);
define('APP_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR);
require_once APP_DIR . 'libraries/DeliveryCommandSimulator.php';
require_once APP_DIR . 'libraries/DispatchRecoveryService.php';
require_once APP_DIR . 'libraries/CourierBookingService.php';
require_once APP_DIR . 'libraries/DeliveryControlService.php';

function t9_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function t9_test(string $name, callable $test): void
{
    echo "Running: {$name}... "; $test(); echo "PASSED\n";
}

class T9Statement
{
    private $row;
    public function __construct($row = false) { $this->row = $row; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT) { return $this->row; }
}
class T9Database
{
    public array $order;
    public bool $locked = false;
    public bool $transactionOpen = false;
    public array $calls = [];
    public function __construct(array $order) { $this->order = $order; }
    public function transaction(): void { $this->transactionOpen = true; }
    public function commit(): void { $this->transactionOpen = false; }
    public function roll_back(): void { $this->transactionOpen = false; }
    public function raw(string $sql, array $params = []): T9Statement
    {
        $this->calls[] = [$sql, $params];
        if (str_contains($sql, 'SELECT status, fulfillment_mode, rider_id, lalamove_quotation_id, lalamove_order_id FROM orders')) return new T9Statement($this->order);
        if (str_contains($sql, 'INSERT INTO dispatch_recovery_locks')) {
            if ($this->locked) throw new PDOException('Duplicate lock.', 23000);
            $this->locked = true; return new T9Statement(true);
        }
        if (str_starts_with($sql, 'DELETE FROM dispatch_recovery_locks')) { $this->locked = false; return new T9Statement(true); }
        if (str_contains($sql, 'FROM orders o') && str_contains($sql, 'INNER JOIN addresses pa')) return new T9Statement($this->order);
        if (str_starts_with($sql, 'UPDATE orders SET lalamove_quotation_id')) { $this->order['lalamove_quotation_id'] = $params[0]; return new T9Statement(true); }
        if (str_starts_with($sql, 'UPDATE orders SET lalamove_order_id')) { $this->order['lalamove_order_id'] = $params[0]; return new T9Statement(true); }
        if (str_starts_with($sql, 'SELECT status FROM orders')) return new T9Statement(['status' => $this->order['status']]);
        throw new RuntimeException('Unexpected SQL: ' . $sql);
    }
}
class T9OrderModel
{
    public array $order; public array $changes = [];
    public function __construct(array $order) { $this->order = $order; }
    public function set_status(int $id, string $status, ?string $notes = null, string $actorType = 'system', ?int $actorId = null): bool
    { $this->changes[] = $status; $this->order['status'] = $status; return true; }
}
class T9CourierDriver implements CourierProviderInterface
{
    public int $quoteCalls = 0; public int $placeCalls = 0; public $duringPlace = null; public bool $throwPlace = false;
    public function getQuote(array $order): array { $this->quoteCalls++; return ['data'=>['quotationId'=>'T9-QUOTE','stops'=>[['stopId'=>'P'],['stopId'=>'D']]]]; }
    public function placeOrder(array $order, array $quote): array
    { $this->placeCalls++; if (is_callable($this->duringPlace)) ($this->duringPlace)(); if ($this->throwPlace) throw new RuntimeException('Provider timed out.'); return ['data'=>['orderId'=>'T9-ORDER','quotationId'=>'T9-QUOTE']]; }
    public function cancelOrder(string $orderId, string $reason = ''): array { return []; }
    public function driverDetails(string $driverId, ?string $orderId = null): array { return ['data'=>['driverId'=>$driverId]]; }
    public function webhookEvent(string $eventType, array $order, array $extra = []): array { throw new LogicException('Not used.'); }
}

try {
    t9_test('service polygons normalize and include interior, boundary, and exterior points', function (): void {
        $polygon = DeliveryCommandSimulator::normalizePolygon([
            ['lat'=>14.5,'lng'=>121.0], ['lat'=>14.5,'lng'=>121.1], ['lat'=>14.6,'lng'=>121.1], ['lat'=>14.6,'lng'=>121.0]
        ]);
        t9_assert(DeliveryCommandSimulator::pointInPolygon(14.55,121.05,$polygon), 'Interior point was excluded.');
        t9_assert(DeliveryCommandSimulator::pointInPolygon(14.5,121.05,$polygon), 'Boundary point was excluded.');
        t9_assert(!DeliveryCommandSimulator::pointInPolygon(14.7,121.05,$polygon), 'Exterior point was included.');
    });
    t9_test('invalid, crossing, and overlapping service polygons are rejected', function (): void {
        foreach ([
            [['lat'=>14,'lng'=>121],['lat'=>14,'lng'=>122]],
            [['lat'=>14,'lng'=>121],['lat'=>15,'lng'=>122],['lat'=>14,'lng'=>122],['lat'=>15,'lng'=>121]],
            [['lat'=>14,'lng'=>121],['lat'=>14,'lng'=>123],['lat'=>14,'lng'=>122],['lat'=>15,'lng'=>122],['lat'=>15,'lng'=>121]]
        ] as $points) {
            try { DeliveryCommandSimulator::normalizePolygon($points); throw new RuntimeException('Invalid polygon accepted.'); }
            catch (InvalidArgumentException $e) { t9_assert($e->getMessage() !== 'Invalid polygon accepted.', 'Unexpected test failure.'); }
        }
    });
    t9_test('scenario estimates baseline/proposed fees and capacity within bounds', function (): void {
        $baseline = ['base_delivery_fee'=>60,'free_distance_km'=>2,'per_km_rate'=>12.5,'free_weight_kg'=>1,'per_kg_rate'=>5];
        $scenario = DeliveryCommandSimulator::simulate([
            'projected_orders'=>'100','average_distance_km'=>'8','average_weight_kg'=>'2','scenario_hours'=>'8','deliveries_per_rider_hour'=>'1','riders'=>'5',
            'base_delivery_fee'=>'70','free_distance_km'=>'2','per_km_rate'=>'15','free_weight_kg'=>'1','per_kg_rate'=>'6'
        ], $baseline, 5);
        t9_assert($scenario['capacity'] === 40 && $scenario['projected_backlog'] === 60, 'Capacity math incorrect.');
        t9_assert($scenario['scenario_gross_revenue'] > $scenario['baseline_gross_revenue'], 'Proposed revenue should reflect higher scenario fee.');
        try { DeliveryCommandSimulator::simulate(['projected_orders'=>6000], $baseline, 1); throw new RuntimeException('Out-of-range volume accepted.'); }
        catch (InvalidArgumentException $e) { t9_assert($e->getMessage() !== 'Out-of-range volume accepted.', 'Unexpected test failure.'); }
    });
    t9_test('recovery recommendation flags uncertain provider quotations for review', function (): void {
        $now = '2026-10-18 12:00:00';
        t9_assert(DispatchRecoveryService::recommendation(['status'=>'booking','status_since'=>'2026-10-18 11:01:00'], $now) === null, 'Booking under threshold was flagged.');
        $delayed = DispatchRecoveryService::recommendation(['status'=>'booking','status_since'=>'2026-10-18 11:00:00','lalamove_order_id'=>'PROVIDER-1'], $now);
        t9_assert(($delayed['kind'] ?? '') === 'escalate', 'Stale booking should be escalation only.');
        $paid = DispatchRecoveryService::recommendation(['status'=>'paid','status_since'=>'2026-10-18 11:59:00'], $now);
        t9_assert(($paid['kind'] ?? '') === 'choose_fulfillment', 'Paid order without a dispatch choice must require explicit fulfillment selection.');
        $ambiguous = DispatchRecoveryService::recommendation(['status'=>'failed','status_since'=>'2026-10-18 11:00:00','lalamove_quotation_id'=>'QUOTE-1'], $now);
        t9_assert(($ambiguous['kind'] ?? '') === 'escalate', 'Failed order with provider quotation must not auto-retry.');
        $internalFailure = DispatchRecoveryService::recommendation(['status'=>'failed','fulfillment_mode'=>'internal','status_since'=>'2026-10-18 11:00:00'], $now);
        t9_assert($internalFailure === null, 'Internal assignment failures must not enter courier recovery.');
    });
    t9_test('courier booking reserves an order during placement and releases on success', function (): void {
        $order = ['id'=>9,'order_no'=>'DLV-9','status'=>'paid','fulfillment_mode'=>'lalamove','rider_id'=>null,'lalamove_quotation_id'=>null,'lalamove_order_id'=>null,
            'pickup_address'=>'Pickup','pickup_lat'=>14.5,'pickup_lng'=>121.0,'pickup_contact_name'=>'A','pickup_phone'=>'1',
            'dropoff_address'=>'Dropoff','dropoff_lat'=>14.6,'dropoff_lng'=>121.1,'dropoff_contact_name'=>'B','dropoff_phone'=>'2',
            'weight_kg'=>1,'parcel_description'=>'Parcel','distance_km'=>2,'delivery_fee'=>60];
        $db = new T9Database($order); $model = new T9OrderModel($order); $driver = new T9CourierDriver();
        $driver->duringPlace = function () use ($db): void { t9_assert($db->locked, 'Lock was released during placement.'); };
        (new CourierBookingService($db,$model,$driver))->book(9,'dispatcher',1);
        t9_assert($driver->quoteCalls===1 && $driver->placeCalls===1, 'Unexpected quote/place call count.');
        t9_assert(!$db->locked && $db->order['lalamove_order_id']==='T9-ORDER', 'Success should persist provider ID and release lock.');
    });
    t9_test('ambiguous provider placement retains reservation and prevents retry', function (): void {
        $order = ['id'=>10,'order_no'=>'DLV-10','status'=>'paid','fulfillment_mode'=>'lalamove','rider_id'=>null,'lalamove_quotation_id'=>null,'lalamove_order_id'=>null,
            'pickup_address'=>'Pickup','pickup_lat'=>14.5,'pickup_lng'=>121.0,'pickup_contact_name'=>'A','pickup_phone'=>'1',
            'dropoff_address'=>'Dropoff','dropoff_lat'=>14.6,'dropoff_lng'=>121.1,'dropoff_contact_name'=>'B','dropoff_phone'=>'2',
            'weight_kg'=>1,'parcel_description'=>'Parcel','distance_km'=>2,'delivery_fee'=>60];
        $db = new T9Database($order); $model = new T9OrderModel($order); $driver = new T9CourierDriver(); $driver->throwPlace = true;
        try { (new CourierBookingService($db,$model,$driver))->book(10); throw new RuntimeException('Timeout swallowed.'); }
        catch (RuntimeException $e) { t9_assert($e->getMessage() !== 'Timeout swallowed.', 'Unexpected test failure.'); }
        t9_assert($db->locked && $db->order['lalamove_quotation_id']==='T9-QUOTE', 'Ambiguous attempt should retain quote and lock.');
        try { (new CourierBookingService($db,$model,$driver))->book(10); throw new RuntimeException('Duplicate retry accepted.'); }
        catch (DomainException $e) { t9_assert($driver->placeCalls===1, 'A duplicate provider placement was attempted.'); }
    });
    t9_test('control pass rate limit is atomic per hashed capability', function (): void {
        $service = new DeliveryControlService(null);
        $method = new ReflectionMethod(DeliveryControlService::class, 'assertSubmissionRateLimit');
        $method->setAccessible(true);
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        for ($i=0; $i<10; $i++) $method->invoke($service, $token);
        try {
            $method->invoke($service, $token);
            throw new RuntimeException('Submission limit was not enforced.');
        } catch (ReflectionException $e) {
            throw $e;
        } catch (DomainException $e) {
            t9_assert(str_contains($e->getMessage(), 'Too many requests'), 'Unexpected rate limit error.');
        }
    });
    echo "All signature feature logic checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n"); exit(1);
}
