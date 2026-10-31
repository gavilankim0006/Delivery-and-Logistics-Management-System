<?php
/** Database-free tests for rider update validation and unchanged-value behavior. */

define('PREVENT_DIRECT_ACCESS', true);

class Model
{
    public $db;
    public function __construct() { $this->db = $GLOBALS['rider_db']; }
}
class RiderTestStatement
{
    private $row;
    public function __construct($row) { $this->row = $row; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT) { return $this->row ?: false; }
}
class RiderTestTable
{
    private RiderTestDb $db;
    private string $table;
    public function __construct(RiderTestDb $db, string $table) { $this->db = $db; $this->table = $table; }
    public function where(string $key, $value): self { $this->db->conditions[] = [$key, $value]; return $this; }
    public function where_null(string $key): self { $this->db->conditions[] = [$key, null]; return $this; }
    public function update(array $fields): int
    {
        if ($this->db->throwOnUpdate) throw new RuntimeException('simulated database failure');
        $this->db->updates[] = $fields;
        return $this->db->updateResult;
    }
}
class RiderTestDb
{
    public array $conditions = [];
    public array $updates = [];
    public int $updateResult = 0;
    public bool $throwOnUpdate = false;
    public bool $transactionOpen = false;
    public function transaction(): void { $this->transactionOpen = true; }
    public function commit(): void { $this->transactionOpen = false; }
    public function roll_back(): void { $this->transactionOpen = false; }
    public function raw(string $sql, array $params = []): RiderTestStatement
    {
        if (str_contains($sql, 'FROM rider_assignments')) return new RiderTestStatement(false);
        return new RiderTestStatement(($params[0] ?? 0) === 3 ? ['id' => 3, 'name' => 'Rider'] : false);
    }
    public function table(string $table): RiderTestTable { return new RiderTestTable($this, $table); }
}
function rider_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
require_once dirname(__DIR__) . '/app/models/Rider_model.php';

$db = $GLOBALS['rider_db'] = new RiderTestDb();
$model = new Rider_model();
rider_assert($model->update_rider(3, ['name' => 'Rider']) === true, 'Unchanged update should succeed for an existing rider.');
rider_assert(count($db->updates) === 1, 'Unchanged update should still attempt persistence and refresh updated_at.');
rider_assert($model->update_rider(99, ['name' => 'Missing']) === false, 'Missing rider should not be reported as updated.');
rider_assert($model->update_rider(3, ['name' => '']) === false, 'Blank required name should be rejected.');
rider_assert($model->update_rider(3, ['status' => 'unknown']) === false, 'Invalid status should be rejected.');
$db->throwOnUpdate = true;
rider_assert($model->update_rider(3, ['name' => 'Valid']) === false, 'Database errors should be converted to a failed update result.');
echo "Rider model update checks: PASS\n";
