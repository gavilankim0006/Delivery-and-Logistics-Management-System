<?php
/**
 * Checking 2 B5 database-backed page acceptance test.
 * Requires the local app, seeded delivery_logistics database, and curl extension.
 */

$baseUrl = 'http://localhost/SIAproject';

function t5_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function t5_database()
{
    static $db = null;
    if ($db !== null) {
        return $db;
    }

    define('PREVENT_DIRECT_ACCESS', true);
    define('ROOT_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR);
    define('SYSTEM_DIR', ROOT_DIR . 'scheme' . DIRECTORY_SEPARATOR);
    define('APP_DIR', ROOT_DIR . 'app' . DIRECTORY_SEPARATOR);
    define('PUBLIC_DIR', 'public');
    require_once SYSTEM_DIR . 'kernel/Registry.php';
    require_once SYSTEM_DIR . 'kernel/Routine.php';

    if (file_exists(ROOT_DIR . '.env')) {
        foreach (file(ROOT_DIR . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (strlen($value) >= 2 && in_array($value[0], ['"', "'"], true) && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }

    require_once SYSTEM_DIR . 'database/Database.php';
    $db = Database::instance(null);
    return $db;
}

function t5_request(string $method, string $url, ?array $data = null, string $cookies = ''): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data ?? []));
    }
    if ($cookies !== '') {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('HTTP request failed: ' . $error);
    }
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($response, 0, $headerSize);
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headers, $matches);
    return [
        'status' => $status,
        'headers' => $headers,
        'body' => substr($response, $headerSize),
        'cookies' => implode('; ', $matches[1] ?? [])
    ];
}

function t5_login(string $email): string
{
    global $baseUrl;
    $page = t5_request('GET', $baseUrl . '/login');
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $page['body'], $match);
    t5_assert(!empty($match[1]), 'Login form did not provide a CSRF token.');
    $login = t5_request('POST', $baseUrl . '/login', [
        'email' => $email,
        'password' => 'password123',
        'csrf_token' => $match[1]
    ], $page['cookies']);
    t5_assert($login['status'] === 302, "Login failed for {$email}; expected redirect, got {$login['status']}.");
    return $login['cookies'] !== '' ? $login['cookies'] : $page['cookies'];
}

function t5_test(string $name, callable $test): void
{
    echo "Running: {$name}... ";
    $test();
    echo "PASSED\n";
}

function t5_counter(string $html, string $label): int
{
    $pattern = '/<span>' . preg_quote($label, '/') . '<\/span>\s*<span class="ds-segment__count">\s*(\d+)\s*<\/span>/s';
    if (!preg_match($pattern, $html, $match)) {
        throw new RuntimeException("Could not read dispatch counter '{$label}'.");
    }
    return (int)$match[1];
}

try {
    $db = t5_database();
    $customerCookies = t5_login('maria@example.com');
    $dispatcherCookies = t5_login('dispatcher@delivery.local');
    $adminCookies = t5_login('admin@delivery.local');

    t5_test('saved addresses are database-backed and scoped to the signed-in customer', function () use ($baseUrl, $customerCookies, $db): void {
        $response = t5_request('GET', $baseUrl . '/addresses', null, $customerCookies);
        t5_assert($response['status'] === 200, "Expected customer addresses page 200, got {$response['status']}.");
        t5_assert(str_contains($response['body'], 'Saved addresses'), 'Saved address heading missing.');
        $own = $db->raw('SELECT label, address_line, contact_name, contact_phone FROM addresses WHERE customer_id = ? ORDER BY id DESC', [1])->fetchAll(PDO::FETCH_ASSOC);
        t5_assert(count($own) > 0, 'Expected seeded customer addresses in the database.');
        foreach ($own as $address) {
            foreach (['label', 'address_line', 'contact_name', 'contact_phone'] as $field) {
                t5_assert(str_contains($response['body'], htmlspecialchars((string)$address[$field], ENT_QUOTES, 'UTF-8')), "Customer address page omitted database {$field}.");
            }
        }
        $others = $db->raw('SELECT address_line FROM addresses WHERE customer_id = ?', [2])->fetchAll(PDO::FETCH_COLUMN);
        foreach ($others as $addressLine) {
            t5_assert(!str_contains($response['body'], htmlspecialchars((string)$addressLine, ENT_QUOTES, 'UTF-8')), 'Saved address page exposed another customer\'s address.');
        }
    });

    t5_test('customer saved-address CRUD validates, scopes ownership, and protects referenced rows', function () use ($baseUrl, $customerCookies, $db): void {
        $page = t5_request('GET', $baseUrl . '/addresses/new', null, $customerCookies);
        t5_assert($page['status'] === 200, 'Saved-address creation form did not load.');
        preg_match('/name="csrf_token"\\s+value="([^"]+)"/', $page['body'], $tokenMatch);
        t5_assert(!empty($tokenMatch[1]), 'Address create form did not provide a CSRF token.');
        $token = $tokenMatch[1];

        $invalid = t5_request('POST', $baseUrl . '/addresses', [
            'csrf_token' => $token, 'label' => 'Kept address label', 'address_line' => '', 'contact_name' => 'Test Contact', 'contact_phone' => 'invalid'
        ], $customerCookies);
        t5_assert($invalid['status'] === 200 && str_contains($invalid['body'], 'Kept address label'), 'Invalid address submission did not preserve input.');
        t5_assert(str_contains($invalid['body'], 'This field is required.') && str_contains($invalid['body'], 'Enter a valid phone number.'), 'Address validation messages are missing.');

        $noCsrf = t5_request('POST', $baseUrl . '/addresses', [
            'label' => 'No CSRF', 'address_line' => 'Test street', 'contact_name' => 'Test Contact', 'contact_phone' => '+639171234567'
        ], $customerCookies);
        t5_assert($noCsrf['status'] === 200, 'Missing-CSRF address request should redisplay the form.');
        t5_assert((int)$db->raw('SELECT COUNT(*) FROM addresses WHERE label = ?', ['No CSRF'])->fetchColumn() === 0, 'Missing-CSRF request inserted an address.');

        $created = t5_request('POST', $baseUrl . '/addresses', [
            'csrf_token' => $token, 'label' => 'CRUD temporary', 'address_line' => '10 Test Street, Makati', 'contact_name' => 'Test Contact', 'contact_phone' => '+639171234567', 'notes' => 'Original note'
        ], $customerCookies);
        t5_assert($created['status'] === 302, 'Valid address create did not redirect.');
        $address = $db->raw('SELECT id, customer_id FROM addresses WHERE label = ? ORDER BY id DESC LIMIT 1', ['CRUD temporary'])->fetch(PDO::FETCH_ASSOC);
        t5_assert($address && (int)$address['customer_id'] === 1, 'Created saved address was not scoped to the authenticated customer.');
        $addressId = (int)$address['id'];

        $edit = t5_request('GET', $baseUrl . '/addresses/' . $addressId . '/edit', null, $customerCookies);
        t5_assert($edit['status'] === 200 && str_contains($edit['body'], 'Original note'), 'Owner could not open the address edit form.');
        $otherCustomerCookies = t5_login('juan@example.com');
        $otherEdit = t5_request('GET', $baseUrl . '/addresses/' . $addressId . '/edit', null, $otherCustomerCookies);
        t5_assert($otherEdit['status'] === 404, 'Another customer could open the address edit form.');
        $otherPost = t5_request('POST', $baseUrl . '/addresses/' . $addressId . '/update', [
            'csrf_token' => $token, 'label' => 'Hijack', 'address_line' => 'Hijacked', 'contact_name' => 'Test Contact', 'contact_phone' => '+639171234567'
        ], $otherCustomerCookies);
        t5_assert($otherPost['status'] === 404, 'Another customer could update the address.');

        $updated = t5_request('POST', $baseUrl . '/addresses/' . $addressId . '/update', [
            'csrf_token' => $token, 'label' => 'CRUD temporary updated', 'address_line' => '12 Test Street, Makati', 'contact_name' => 'Updated Contact', 'contact_phone' => '+639171234568', 'notes' => 'Updated note'
        ], $customerCookies);
        t5_assert($updated['status'] === 302, 'Valid address update did not redirect.');
        $saved = $db->raw('SELECT label, address_line, contact_name, contact_phone, notes FROM addresses WHERE id = ? AND customer_id = ?', [$addressId, 1])->fetch(PDO::FETCH_ASSOC);
        t5_assert($saved && $saved['label'] === 'CRUD temporary updated' && $saved['address_line'] === '12 Test Street, Makati' && $saved['notes'] === 'Updated note', 'Address update did not persist the validated fields.');

        $referencedAddress = $db->raw('SELECT a.id FROM addresses a INNER JOIN orders o ON o.pickup_address_id = a.id OR o.dropoff_address_id = a.id WHERE a.customer_id = ? ORDER BY a.id LIMIT 1', [1])->fetch(PDO::FETCH_ASSOC);
        t5_assert(!empty($referencedAddress), 'Expected an owned address referenced by a seeded order.');
        $referencedAddressId = (int)$referencedAddress['id'];
        $referencedDelete = t5_request('POST', $baseUrl . '/addresses/' . $referencedAddressId . '/delete', ['csrf_token' => $token], $customerCookies);
        t5_assert($referencedDelete['status'] === 302, 'Referenced address deletion did not redirect.');
        $feedback = t5_request('GET', $baseUrl . '/addresses', null, $customerCookies);
        t5_assert(str_contains($feedback['body'], 'used by an order and cannot be deleted'), 'Referenced-address deletion did not show a friendly failure.');
        t5_assert((int)$db->raw('SELECT COUNT(*) FROM addresses WHERE id = ? AND customer_id = ?', [$referencedAddressId, 1])->fetchColumn() === 1, 'Referenced address was deleted.');

        // Updating a saved address referenced by an order must preserve the historical row.
        $historic = $db->raw('SELECT address_line, contact_name FROM addresses WHERE id = ?', [$referencedAddressId])->fetch(PDO::FETCH_ASSOC);
        $historicEdit = t5_request('GET', $baseUrl . '/addresses/' . $referencedAddressId . '/edit', null, $customerCookies);
        preg_match('/name="csrf_token"\\s+value="([^"]+)"/', $historicEdit['body'], $historicToken);
        t5_assert(!empty($historicToken[1]), 'Referenced saved address edit form did not provide CSRF token.');
        $historicUpdate = t5_request('POST', $baseUrl . '/addresses/' . $referencedAddressId . '/update', [
            'csrf_token' => $historicToken[1], 'label' => 'Updated office', 'address_line' => 'New office address', 'contact_name' => 'New Contact', 'contact_phone' => '+639171234569', 'notes' => 'New office note'
        ], $customerCookies);
        t5_assert($historicUpdate['status'] === 302, 'Referenced saved address update did not redirect.');
        $historicalNow = $db->raw('SELECT address_line, contact_name, customer_id FROM addresses WHERE id = ?', [$referencedAddressId])->fetch(PDO::FETCH_ASSOC);
        $newSaved = $db->raw('SELECT id, address_line, contact_name, customer_id FROM addresses WHERE customer_id = 1 AND label = ? ORDER BY id DESC LIMIT 1', ['Updated office'])->fetch(PDO::FETCH_ASSOC);
        t5_assert($historicalNow['address_line'] === $historic['address_line'] && $historicalNow['contact_name'] === $historic['contact_name'] && $historicalNow['customer_id'] === null, 'Historical address row changed instead of being detached.');
        t5_assert($newSaved && $newSaved['address_line'] === 'New office address' && (int)$newSaved['customer_id'] === 1, 'Copy-on-write did not create the updated saved address.');

        $forbiddenDelete = t5_request('POST', $baseUrl . '/addresses/' . $addressId . '/delete', ['csrf_token' => $token], $otherCustomerCookies);
        t5_assert($forbiddenDelete['status'] === 302, 'Unowned address delete request did not redirect.');
        t5_assert((int)$db->raw('SELECT COUNT(*) FROM addresses WHERE id = ? AND customer_id = ?', [$addressId, 1])->fetchColumn() === 1, 'Another customer deleted an owned address.');

        $deleted = t5_request('POST', $baseUrl . '/addresses/' . $addressId . '/delete', ['csrf_token' => $token], $customerCookies);
        t5_assert($deleted['status'] === 302, 'Valid address delete did not redirect.');
        t5_assert((int)$db->raw('SELECT COUNT(*) FROM addresses WHERE id = ?', [$addressId])->fetchColumn() === 0, 'Unreferenced saved address was not deleted.');
    });

    t5_test('customer address page denies staff and redirects guests', function () use ($baseUrl, $dispatcherCookies): void {
        $staff = t5_request('GET', $baseUrl . '/addresses', null, $dispatcherCookies);
        t5_assert($staff['status'] === 403, "Expected dispatcher address access 403, got {$staff['status']}.");
        $guest = t5_request('GET', $baseUrl . '/addresses');
        t5_assert($guest['status'] === 302 && stripos($guest['headers'], 'login') !== false, 'Guest address access did not redirect to login.');
    });

    t5_test('admin user and rider pages render seeded database records', function () use ($baseUrl, $adminCookies): void {
        $users = t5_request('GET', $baseUrl . '/admin/users', null, $adminCookies);
        t5_assert($users['status'] === 200 && str_contains($users['body'], 'System Administrator') && str_contains($users['body'], 'Maria Santos'), 'Admin users page did not render seeded users.');
        $filteredUsers = t5_request('GET', $baseUrl . '/admin/users?search=Maria', null, $adminCookies);
        t5_assert($filteredUsers['status'] === 200 && str_contains($filteredUsers['body'], 'Maria Santos'), 'User search did not show matching database row.');
        t5_assert(!str_contains($filteredUsers['body'], 'Juan Dela Cruz'), 'User search included a non-matching record.');

        $riders = t5_request('GET', $baseUrl . '/admin/riders', null, $adminCookies);
        t5_assert($riders['status'] === 200 && str_contains($riders['body'], 'Carlos Mendoza') && str_contains($riders['body'], 'Eduardo Cruz'), 'Admin riders page did not render seeded riders.');
        $filteredRiders = t5_request('GET', $baseUrl . '/admin/riders?status=busy', null, $adminCookies);
        t5_assert($filteredRiders['status'] === 200 && str_contains($filteredRiders['body'], 'Carlos Mendoza'), 'Rider status filter omitted matching rider.');
        t5_assert(!str_contains($filteredRiders['body'], 'Eduardo Cruz'), 'Rider status filter included an offline-status mismatch.');
    });

    t5_test('admin dashboard metrics match independent payment and order counts', function () use ($baseUrl, $adminCookies, $db): void {
        $dashboard = t5_request('GET', $baseUrl . '/admin', null, $adminCookies);
        t5_assert($dashboard['status'] === 200, "Expected admin dashboard 200, got {$dashboard['status']}.");

        $ordersToday = (int)$db->raw('SELECT COUNT(*) FROM orders WHERE created_at >= CURRENT_DATE() AND created_at < DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)')->fetchColumn();
        $monthRevenue = (float)$db->raw("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid' AND paid_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01') AND paid_at < DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)")->fetchColumn();
        $failedPayments = (int)$db->raw("SELECT COUNT(*) FROM payments WHERE status = 'failed' AND created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01') AND created_at < DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)")->fetchColumn();

        t5_assert(str_contains($dashboard['body'], 'Orders today') && str_contains($dashboard['body'], number_format($ordersToday)), 'Dashboard orders-today metric is missing or incorrect.');
        t5_assert(str_contains($dashboard['body'], 'Paid revenue this month') && str_contains($dashboard['body'], '₱' . number_format($monthRevenue, 2)), 'Dashboard month-to-date revenue metric is missing or incorrect.');
        t5_assert(str_contains($dashboard['body'], 'Failed payments') && str_contains($dashboard['body'], number_format($failedPayments)), 'Dashboard failed-payment metric is missing or incorrect.');
        foreach (['Settings', 'Reports', 'API logs'] as $label) {
            t5_assert(str_contains($dashboard['body'], $label), "Admin More menu is missing {$label}.");
        }
    });

    t5_test('admin pages deny customers', function () use ($baseUrl, $customerCookies): void {
        foreach (['/admin/users', '/admin/riders'] as $path) {
            $response = t5_request('GET', $baseUrl . $path, null, $customerCookies);
            t5_assert($response['status'] === 403, "Expected customer access to {$path} to be 403, got {$response['status']}.");
        }
    });

    t5_test('order date and search filters render matching seeded records', function () use ($baseUrl, $customerCookies): void {
        $byDate = t5_request('GET', $baseUrl . '/orders?date=2026-10-17', null, $customerCookies);
        t5_assert($byDate['status'] === 200 && str_contains($byDate['body'], 'DLV-20261017-0001'), 'Order date filter omitted the customer\'s matching order.');
        $bySearch = t5_request('GET', $baseUrl . '/orders?search=DLV-20261017-0001', null, $customerCookies);
        t5_assert($bySearch['status'] === 200 && str_contains($bySearch['body'], 'DLV-20261017-0001'), 'Order search omitted the matching order.');
        t5_assert(!str_contains($bySearch['body'], 'DLV-20261017-0002'), 'Order search included a non-matching order.');
        $unknown = t5_request('GET', $baseUrl . '/orders/999999', null, $customerCookies);
        t5_assert($unknown['status'] === 404 && str_contains($unknown['body'], 'not found'), 'Unknown order did not return friendly 404 page.');
    });

    t5_test('dispatcher board counters match independent database counts', function () use ($baseUrl, $dispatcherCookies, $db): void {
        $response = t5_request('GET', $baseUrl . '/dispatch', null, $dispatcherCookies);
        t5_assert($response['status'] === 200, "Expected dispatcher board 200, got {$response['status']}.");
        $expected = [
            "Today's Orders" => (int)$db->raw('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURRENT_DATE()')->fetchColumn(),
            'Awaiting Booking' => (int)$db->raw("SELECT COUNT(*) FROM orders WHERE status = 'paid'")->fetchColumn(),
            'In Transit' => (int)$db->raw("SELECT COUNT(*) FROM orders WHERE status IN ('booking', 'rider_assigned', 'picked_up')")->fetchColumn(),
            'Failed / Exceptions' => (int)$db->raw("SELECT COUNT(*) FROM orders WHERE status = 'failed'")->fetchColumn()
        ];
        foreach ($expected as $label => $count) {
            t5_assert(t5_counter($response['body'], $label) === $count, "Dispatch counter '{$label}' differs from independent SQL count {$count}.");
        }

        $todayUrl = htmlspecialchars($baseUrl . '/dispatch?date=' . date('Y-m-d'), ENT_QUOTES, 'UTF-8');
        foreach ([
            $todayUrl,
            htmlspecialchars($baseUrl . '/dispatch?status=paid', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($baseUrl . '/dispatch?status=failed', ENT_QUOTES, 'UTF-8')
        ] as $filterUrl) {
            t5_assert(str_contains($response['body'], 'href="' . $filterUrl . '"'), "Dispatch summary link lost its query string: {$filterUrl}.");
        }

        $failed = t5_request('GET', $baseUrl . '/dispatch?status=failed', null, $dispatcherCookies);
        t5_assert($failed['status'] === 200, "Expected failed dispatch filter 200, got {$failed['status']}.");
        t5_assert(str_contains($failed['body'], 'value="failed" selected'), 'Failed dispatch filter was not selected.');
    });
} catch (Throwable $e) {
    fwrite(STDERR, "[FAILED] {$e->getMessage()}\n");
    exit(1);
}

echo "All Checking 2 B5 read-page tests passed.\n";
