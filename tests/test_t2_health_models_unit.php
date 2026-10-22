<?php
/**
 * Database-free checks for the health endpoint and model API contracts.
 */

define('PREVENT_DIRECT_ACCESS', true);
define('APP_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR);

function unit_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class Model
{
    public $db;

    public function __construct()
    {
        $this->db = $GLOBALS['unit_db'];
    }
}

class Controller {}

class Database
{
    public static string $healthState = 'connected';

    public static function instance($name = null): self
    {
        if (self::$healthState === 'throws') {
            throw new RuntimeException('simulated connection failure');
        }
        return new self();
    }

    public function health_check(): bool
    {
        return self::$healthState === 'connected';
    }
}

class UnitStatement
{
    private array $rows;
    private bool $single;

    public function __construct(array $rows = [], bool $single = false)
    {
        $this->rows = $rows;
        $this->single = $single;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT)
    {
        return $this->single ? ($this->rows[0] ?? false) : false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT): array
    {
        return $this->rows;
    }
}

class UnitTable
{
    private UnitDatabase $db;
    private string $table;

    public function __construct(UnitDatabase $db, string $table)
    {
        $this->db = $db;
        $this->table = $table;
    }

    public function insert(array $data): void
    {
        $this->db->inserts[] = [$this->table, $data];
        $this->db->lastId = 37;
    }

    public function where(string $column, $value): self
    {
        $this->db->builderConditions[] = [$this->table, $column, $value];
        return $this;
    }

    public function where_null(string $column): self
    {
        $this->db->builderConditions[] = [$this->table, $column, null];
        return $this;
    }

    public function update(array $fields): int
    {
        $this->db->updates[] = [$this->table, $fields];
        return $this->db->updateResult;
    }
}

class UnitDatabase
{
    public array $queries = [];
    public array $inserts = [];
    public array $updates = [];
    public array $builderConditions = [];
    public int $lastId = 0;
    public int $updateResult = 1;
    public array $rows = [];

    public function raw(string $sql, array $params = []): UnitStatement
    {
        $this->queries[] = [$sql, $params];
        return new UnitStatement($this->rows, true);
    }

    public function table(string $table): UnitTable
    {
        return new UnitTable($this, $table);
    }

    public function last_id(): int
    {
        return $this->lastId;
    }
}

$GLOBALS['unit_db'] = new UnitDatabase();
require_once APP_DIR . 'models/Customer_model.php';
require_once APP_DIR . 'models/Delivery_model.php';
require_once APP_DIR . 'controllers/Health.php';

function unit_health_request(string $state): array
{
    Database::$healthState = $state;
    http_response_code(200);
    ob_start();
    (new Health())->index();
    $body = (string)ob_get_clean();
    return [http_response_code(), json_decode($body, true), $body];
}

try {
    $connected = unit_health_request('connected');
    unit_assert($connected[0] === 200, 'Connected health probe should return HTTP 200.');
    unit_assert($connected[1] === ['status' => 'ok', 'database' => 'connected'], 'Connected health response mismatch.');

    foreach (['unavailable', 'throws'] as $state) {
        $unavailable = unit_health_request($state);
        unit_assert($unavailable[0] === 503, 'Unavailable health probe should return HTTP 503.');
        unit_assert($unavailable[1] === ['status' => 'error', 'database' => 'unavailable'], 'Unavailable health response must be generic.');
        unit_assert(!str_contains($unavailable[2], 'simulated connection failure'), 'Health response must not disclose the connection error.');
    }

    $customer = new Customer_model();
    $delivery = new Delivery_model();
    $GLOBALS['unit_db']->rows = [['id' => 8, 'name' => 'Customer']];
    unit_assert(count($customer->get_all()) === 1, 'Customer get_all should return rows.');
    unit_assert(str_contains($GLOBALS['unit_db']->queries[0][0], 'u.deleted_at IS NULL'), 'Customer reads must hide deleted accounts.');
    unit_assert($customer->get_by_id(8)['id'] === 8, 'Customer get_by_id should fetch a matching row.');
    $customer->get_filtered(['user_id' => 4, 'search' => 'Ada', 'limit' => 200, 'offset' => -4]);
    $filteredCustomer = end($GLOBALS['unit_db']->queries);
    unit_assert($filteredCustomer[1] === [4, '%Ada%', '%Ada%', '%Ada%'], 'Customer filter values should use bound parameters.');
    unit_assert(str_contains($filteredCustomer[0], 'LIMIT 100 OFFSET 0'), 'Customer pagination bounds should be clamped.');
    unit_assert($customer->create(['user_id' => 4]) === 37, 'Customer create should return the inserted id.');
    unit_assert($customer->update_customer(8, ['company_name' => 'Example']) === true, 'Customer update should report the affected row.');
    unit_assert($customer->delete(8) === false, 'Customer deletion must fail closed to retain order history.');

    $GLOBALS['unit_db']->rows = [['id' => 15, 'order_id' => 8]];
    unit_assert($delivery->get_all(0)[0]['id'] === 15, 'Delivery get_all should return rows.');
    unit_assert(str_contains(end($GLOBALS['unit_db']->queries)[0], 'LIMIT 1'), 'Delivery get_all should clamp its limit.');
    unit_assert($delivery->get_by_id(15)['id'] === 15, 'Delivery get_by_id should fetch a matching row.');
    unit_assert($delivery->get_by_order(8)[0]['order_id'] === 8, 'Delivery get_by_order should return timeline rows.');
    $delivery->get_filtered(['order_id' => 8, 'to_status' => 'paid', 'actor_type' => 'system', 'limit' => 900]);
    $filteredDelivery = end($GLOBALS['unit_db']->queries);
    unit_assert($filteredDelivery[1] === [8, 'paid', 'system'], 'Delivery filter values should use bound parameters.');
    unit_assert(str_contains($filteredDelivery[0], 'LIMIT 500 OFFSET 0'), 'Delivery pagination bounds should be clamped.');
    unit_assert($delivery->create(['order_id' => 8, 'to_status' => 'paid']) === 37, 'Delivery create should return the inserted id.');
    unit_assert($delivery->update_delivery(15, ['to_status' => 'delivered']) === false, 'Delivery history must be immutable.');
    unit_assert($delivery->delete(15) === false, 'Delivery history deletion must fail closed.');

    echo "Database-free health and Customer/Delivery model API checks: PASS\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
