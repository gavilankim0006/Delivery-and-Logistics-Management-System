<?php
define('PREVENT_DIRECT_ACCESS', true);
define('ROOT_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('SYSTEM_DIR', ROOT_DIR . 'scheme' . DIRECTORY_SEPARATOR);
define('APP_DIR', ROOT_DIR . 'app' . DIRECTORY_SEPARATOR);
define('PUBLIC_DIR', 'public');

require_once SYSTEM_DIR . 'kernel/Registry.php';
require_once SYSTEM_DIR . 'kernel/Routine.php';

// Load .env
if (file_exists(ROOT_DIR . '.env')) {
    foreach (file(ROOT_DIR . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
        $_ENV[trim($key)] = trim($value);
    }
}

require_once SYSTEM_DIR . 'database/Database.php';

echo "=== T1 ACCEPTANCE TEST: Schema & Seed Data Validation ===\n";

$db = Database::instance(null);

// 1. Check Tables count
$stmt = $db->raw("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "[INFO] Found " . count($tables) . " tables in database.\n";

$expectedTables = [
    'roles', 'users', 'customers', 'riders', 'addresses', 'orders',
    'parcels', 'payments', 'deliveries', 'notifications', 'api_logs',
    'webhook_events', 'settings'
];

foreach ($expectedTables as $t) {
    if (!in_array($t, $tables)) {
        echo "[FAIL] Missing table: $t\n";
        exit(1);
    }
}
echo "[PASS] All 13 expected tables exist.\n";

// 2. Count admin (role_id = 1)
$adminCount = (int) $db->table('users')->where('role_id', 1)->select_count('*', 'count')->get()['count'];
echo "[CHECK] Admin count: $adminCount (Expected: 1)\n";
if ($adminCount !== 1) {
    echo "[FAIL] Expected 1 admin, found $adminCount\n";
    exit(1);
}

// 3. Count dispatcher (role_id = 2)
$dispatcherCount = (int) $db->table('users')->where('role_id', 2)->select_count('*', 'count')->get()['count'];
echo "[CHECK] Dispatcher count: $dispatcherCount (Expected: 1)\n";
if ($dispatcherCount !== 1) {
    echo "[FAIL] Expected 1 dispatcher, found $dispatcherCount\n";
    exit(1);
}

// 4. Count customers
$customerCount = (int) $db->table('customers')->select_count('*', 'count')->get()['count'];
echo "[CHECK] Customer count: $customerCount (Expected: 3)\n";
if ($customerCount !== 3) {
    echo "[FAIL] Expected 3 customers, found $customerCount\n";
    exit(1);
}

// 5. Count riders
$riderCount = (int) $db->table('riders')->select_count('*', 'count')->get()['count'];
echo "[CHECK] Rider count: $riderCount (Expected: 3)\n";
if ($riderCount !== 3) {
    echo "[FAIL] Expected 3 riders, found $riderCount\n";
    exit(1);
}

// 6. Count orders
$orderCount = (int) $db->table('orders')->select_count('*', 'count')->get()['count'];
echo "[CHECK] Order count: $orderCount (Expected: 10)\n";
if ($orderCount !== 10) {
    echo "[FAIL] Expected 10 orders, found $orderCount\n";
    exit(1);
}

// 7. Check Order Status Breakdown
$statuses = $db->table('orders')
    ->group_by('status')
    ->select('status')
    ->select_count('*', 'total')
    ->get_all();

echo "[INFO] Order status distribution:\n";
foreach ($statuses as $s) {
    echo "  - " . $s['status'] . ": " . $s['total'] . "\n";
}

echo "=== T1 ACCEPTANCE TEST PASSED ===\n";
exit(0);
