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
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) >= 2 && in_array($value[0], ['"', "'"], true) && $value[-1] === $value[0]) {
            $value = substr($value, 1, -1);
        }
        putenv("$key=$value");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}

require_once SYSTEM_DIR . 'database/Database.php';
require_once APP_DIR . 'libraries/ApiClient.php';

echo "=== T0 ACCEPTANCE TEST: ApiClient & api_logs ===\n";

// 1. Verify DB connection
$db = Database::instance(null);
echo "[PASS] Database connected successfully.\n";

// 2. Perform test call through ApiClient
$client = new ApiClient('test_service');
$testUrl = 'https://jsonplaceholder.typicode.com/todos/1';
echo "Calling external test URL: $testUrl ...\n";
$response = $client->get($testUrl);

echo "Response status: " . $response['status'] . "\n";
echo "Response success: " . ($response['success'] ? 'true' : 'false') . "\n";

// 3. Verify api_logs entry
$dbInstance = Database::instance(null);
$row = $dbInstance->table('api_logs')
    ->where('service', 'test_service')
    ->order_by('id', 'DESC')
    ->limit(1)
    ->get();

if (!empty($row)) {
    echo "[PASS] Found api_logs entry:\n";
    echo "  - ID: " . $row['id'] . "\n";
    echo "  - Service: " . $row['service'] . "\n";
    echo "  - Method: " . $row['method'] . "\n";
    echo "  - Endpoint: " . $row['endpoint'] . "\n";
    echo "  - Status: " . $row['response_status'] . "\n";
    echo "  - Time: " . $row['execution_time_ms'] . " ms\n";
    echo "  - Created: " . $row['created_at'] . "\n";
    echo "=== T0 ACCEPTANCE TEST PASSED ===\n";
    exit(0);
} else {
    echo "[FAIL] No api_logs entry found for test_service!\n";
    exit(1);
}
