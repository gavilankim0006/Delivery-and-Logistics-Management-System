<?php
/** Database-free tests for report filter validation and safe CSV cells. */

define('PREVENT_DIRECT_ACCESS', true);

class Model
{
    public $db;
    public function __construct() { $this->db = $GLOBALS['report_db']; }
}
class ReportTestStatement
{
    private array $row;
    public function __construct(array $row = []) { $this->row = $row; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT) { return $this->row; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT) { return []; }
}
class ReportTestDb
{
    public array $calls = [];
    public function raw(string $sql, array $params = []): ReportTestStatement
    {
        $this->calls[] = [$sql, $params];
        return new ReportTestStatement();
    }
}
function report_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
require_once dirname(__DIR__) . '/app/models/Report_model.php';

$GLOBALS['report_db'] = new ReportTestDb();
$reportModel = new Report_model();

$valid = Report_model::normalize_filters('deliveries', [
    'from' => '2026-10-01',
    'to' => '2026-10-20',
    'status' => 'delivered',
    'rider_id' => '12'
]);
report_assert($valid['errors'] === [], 'Valid delivery report filters should pass.');
report_assert($valid['filters']['from'] === '2026-10-01', 'Start date should be retained.');
report_assert($valid['filters']['status'] === 'delivered', 'Allowed status should be retained.');
report_assert($valid['filters']['rider_id'] === 12, 'Positive rider ID should be normalized to an integer.');

$invalid = Report_model::normalize_filters('payments', [
    'from' => '2026-02-31',
    'to' => '2026-01-01',
    'status' => 'unknown'
]);
report_assert(isset($invalid['errors']['from']), 'Invalid calendar dates should be rejected.');
report_assert(isset($invalid['errors']['status']), 'Unknown payment statuses should be rejected.');
report_assert($invalid['filters']['type'] === 'payments', 'Unknown report input must not switch report type.');

$reversed = Report_model::normalize_filters('payments', [
    'from' => '2026-10-20', 'to' => '2026-10-01'
]);
report_assert(isset($reversed['errors']['date_range']), 'Reversed ranges should be rejected.');

$tooLong = Report_model::normalize_filters('deliveries', [
    'from' => '2024-01-01',
    'to' => '2026-10-20'
]);
report_assert(isset($tooLong['errors']['date_range']), 'Ranges longer than 367 days should be rejected.');

report_assert(Report_model::safe_csv_cell('=1+1') === "'=1+1", 'Formula-leading CSV cells must be neutralized.');
report_assert(Report_model::safe_csv_cell("  @SUM(A1:A2)") === "'  @SUM(A1:A2)", 'Whitespace-prefixed formulas must be neutralized.');
report_assert(Report_model::safe_csv_cell('DLV-20261020-0001') === 'DLV-20261020-0001', 'Ordinary order numbers should not be modified.');
report_assert(Report_model::safe_csv_cell('Makati') === 'Makati', 'Ordinary text should remain unchanged.');

$reportModel->get_delivery_report($valid['filters']);
$deliveryCalls = $GLOBALS['report_db']->calls;
report_assert(count($deliveryCalls) === 2, 'Delivery report should issue separate aggregate and row queries.');
report_assert(str_contains($deliveryCalls[0][0], 'o.deleted_at IS NULL'), 'Delivery report should exclude soft-deleted orders.');
report_assert(str_contains($deliveryCalls[0][0], 'paid.order_id = o.id'), 'Paid amounts should be aggregated before joining orders.');
report_assert($deliveryCalls[0][1] === ['2026-10-01 00:00:00', '2026-10-21 00:00:00', 'delivered', 12], 'Delivery filters should use bound parameters and an exclusive end date.');
report_assert(str_contains($deliveryCalls[1][0], 'LIMIT 500'), 'Delivery export query should be bounded.');

$GLOBALS['report_db']->calls = [];
$paymentFilters = Report_model::normalize_filters('payments', [
    'from' => '2026-10-01', 'to' => '2026-10-20', 'status' => 'paid'
])['filters'];
$reportModel->get_payment_report($paymentFilters);
$paymentCalls = $GLOBALS['report_db']->calls;
report_assert(count($paymentCalls) === 2, 'Payment report should issue separate aggregate and row queries.');
report_assert(str_contains($paymentCalls[0][0], 'p.created_at >= ? AND p.created_at < ?'), 'Payment report should filter by a half-open date range.');
report_assert($paymentCalls[0][1] === ['2026-10-01 00:00:00', '2026-10-21 00:00:00', 'paid'], 'Payment filters should use bound parameters.');

echo "Report filter and CSV safety checks: PASS\n";
