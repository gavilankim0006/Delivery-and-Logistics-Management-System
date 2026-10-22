<?php
/**
 * Checking 2 foreign-key smoke check. This test requires an explicitly approved,
 * disposable MySQL database because it attempts a deliberately invalid INSERT.
 */

$root = dirname(__DIR__);

function t2_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if (getenv('RUN_T2_FOREIGN_KEY_CHECK') !== '1') {
    fwrite(STDERR, "SKIPPED: set RUN_T2_FOREIGN_KEY_CHECK=1 only for a disposable test database.\n");
    exit(2);
}
if (getenv('T2_TEST_DB_APPROVED') !== '1') {
    fwrite(STDERR, "SKIPPED: set T2_TEST_DB_APPROVED=1 only after approving this disposable database.\n");
    exit(2);
}
if (getenv('DB_NAME') === false || trim((string)getenv('DB_NAME')) === '') {
    fwrite(STDERR, "SKIPPED: DB_NAME must explicitly name the disposable test database.\n");
    exit(2);
}
$approvedDatabase = trim((string)(getenv('T2_APPROVED_DB_NAME') ?: ''));
$targetDatabase = trim((string)getenv('DB_NAME'));
if ($approvedDatabase === '' || strcasecmp($targetDatabase, $approvedDatabase) !== 0) {
    fwrite(STDERR, "SKIPPED: T2_APPROVED_DB_NAME must exactly match the approved disposable DB_NAME.\n");
    exit(2);
}
$applicationDatabase = 'delivery_logistics';
$envFile = $root . DIRECTORY_SEPARATOR . '.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^\\s*DB_NAME\\s*=\\s*(.*?)\\s*$/', $line, $match)) {
            $applicationDatabase = trim($match[1], " \\t\\\"'");
            break;
        }
    }
}
$targetDatabase = trim((string)getenv('DB_NAME'));
if (strcasecmp($targetDatabase, $applicationDatabase) === 0 || strcasecmp($targetDatabase, 'delivery_logistics') === 0) {
    fwrite(STDERR, "SKIPPED: refusing to run the foreign-key mutation against an application database.\n");
    exit(2);
}
if ((getenv('DB_DRIVER') ?: 'mysql') !== 'mysql') {
    fwrite(STDERR, "SKIPPED: foreign-key check currently supports only MySQL.\n");
    exit(2);
}

try {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = trim((string)getenv('DB_NAME'));
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset={$charset}",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    t2_assert((bool)$pdo->query('SELECT 1')->fetchColumn(), 'Database health probe failed.');
    echo "Database health probe: PASS\n";

    $table = $pdo->query("SHOW TABLE STATUS WHERE Name = 'orders'")->fetch(PDO::FETCH_ASSOC);
    t2_assert($table && strcasecmp((string)($table['Engine'] ?? ''), 'InnoDB') === 0, 'Refusing to run: orders must use InnoDB so the test can roll back safely.');

    $orderNo = 'DLV-FK-CHECK-' . bin2hex(random_bytes(8));
    $foreignKeyRejected = false;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO orders (order_no, customer_id, pickup_address_id, dropoff_address_id, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$orderNo, 4294967294, 4294967294, 4294967294, 'draft']);
    } catch (PDOException $e) {
        $foreignKeyRejected = $e->getCode() === '23000' && (int)($e->errorInfo[1] ?? 0) === 1452;
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    t2_assert($foreignKeyRejected, 'Invalid order foreign keys were not rejected by a foreign-key constraint.');
    $persisted = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE order_no = ?');
    $persisted->execute([$orderNo]);
    t2_assert((int)$persisted->fetchColumn() === 0, 'Foreign-key smoke check left a persistent order row.');
    echo "Invalid order foreign keys rejected and rolled back: PASS\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
