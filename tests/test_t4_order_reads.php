<?php
/**
 * Checking 2 order read path and Phase C1 order creation test.
 * Requires the local app, seeded delivery_logistics database, and curl extension.
 */

$baseUrl = 'http://localhost/SIAproject';

function t4_database()
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
    require_once APP_DIR . 'helpers/fee_helper.php';
    $db = Database::instance(null);
    return $db;
}

function t4_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function t4_request(string $method, string $url, ?array $data = null, string $cookies = ''): array
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

function t4_login(string $email, string $password): string
{
    global $baseUrl;
    $page = t4_request('GET', $baseUrl . '/login');
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $page['body'], $match);
    t4_assert(!empty($match[1]), 'Login page did not provide a CSRF token.');
    $login = t4_request('POST', $baseUrl . '/login', [
        'email' => $email,
        'password' => $password,
        'csrf_token' => $match[1]
    ], $page['cookies']);
    t4_assert($login['status'] === 302, "Expected login redirect, got {$login['status']}.");
    return $login['cookies'] !== '' ? $login['cookies'] : $page['cookies'];
}

function t4_test(string $name, callable $test): void
{
    echo "Running: {$name}... ";
    $test();
    echo "PASSED\n";
}

try {
    $customerCookies = t4_login('maria@example.com', 'password123');
    $dispatcherCookies = t4_login('dispatcher@delivery.local', 'password123');
    $adminCookies = t4_login('admin@delivery.local', 'password123');

    t4_test('customer new-order form loads and invalid submission preserves input', function () use ($baseUrl, $customerCookies): void {
        $page = t4_request('GET', $baseUrl . '/orders/new', null, $customerCookies);
        t4_assert($page['status'] === 200, "Expected new-order form 200, got {$page['status']}.");
        preg_match('/name="csrf_token"\s+value="([^"]+)"/', $page['body'], $match);
        t4_assert(!empty($match[1]), 'New-order form did not provide a CSRF token.');
        $invalid = t4_request('POST', $baseUrl . '/orders', [
            'csrf_token' => $match[1],
            'pickup_address' => 'Kept pickup input',
            'pickup_contact_name' => 'Test Contact',
            'pickup_contact_phone' => '+639171234567',
            'dropoff_address' => 'Different test address',
            'dropoff_contact_name' => 'Receiver',
            'dropoff_contact_phone' => '+639171234568',
            'parcel_description' => 'Test parcel',
            'parcel_weight' => '0',
            'parcel_size' => 'M',
            'distance_km' => '0'
        ], $customerCookies);
        t4_assert($invalid['status'] === 200, "Expected validation response 200, got {$invalid['status']}.");
        t4_assert(str_contains($invalid['body'], 'Enter a weight greater than 0'), 'Weight validation message missing.');
        t4_assert(str_contains($invalid['body'], 'Enter a distance greater than 0'), 'Distance validation message missing.');
        t4_assert(str_contains($invalid['body'], 'Kept pickup input'), 'Invalid submission did not preserve entered values.');
    });

    t4_test('customer order list shows only owned seeded orders', function () use ($baseUrl, $customerCookies): void {
        $response = t4_request('GET', $baseUrl . '/orders', null, $customerCookies);
        t4_assert($response['status'] === 200, "Expected 200, got {$response['status']}.");
        t4_assert(str_contains($response['body'], 'DLV-20261017-0001'), 'Customer list omitted the customer\'s order.');
        t4_assert(!str_contains($response['body'], 'DLV-20261017-0003'), 'Customer list exposed another customer\'s order.');
    });

    t4_test('customer filters return matching rows and pagination links', function () use ($baseUrl, $customerCookies): void {
        $filtered = t4_request('GET', $baseUrl . '/orders?status=delivered', null, $customerCookies);
        t4_assert($filtered['status'] === 200, 'Filtered order list did not load.');
        t4_assert(str_contains($filtered['body'], 'DLV-20261017-0007'), 'Status filter omitted matching order.');
        t4_assert(!str_contains($filtered['body'], 'DLV-20261017-0001'), 'Status filter included non-matching order.');
        $paged = t4_request('GET', $baseUrl . '/orders?page=2', null, $customerCookies);
        t4_assert($paged['status'] === 200, 'Second page failed to load.');
    });

    t4_test('customer can view own order and delivery history', function () use ($baseUrl, $customerCookies): void {
        $response = t4_request('GET', $baseUrl . '/orders/1', null, $customerCookies);
        t4_assert($response['status'] === 200, "Expected 200, got {$response['status']}.");
        t4_assert(str_contains($response['body'], 'DLV-20261017-0001'), 'Order number missing from detail page.');
        t4_assert(str_contains($response['body'], 'Order created by customer'), 'Delivery history note missing.');
        t4_assert(str_contains($response['body'], 'Makati CBD Hub'), 'Pickup address missing.');
    });

    t4_test('customer cannot view another customer order or nonexistent order', function () use ($baseUrl, $customerCookies): void {
        $forbidden = t4_request('GET', $baseUrl . '/orders/3', null, $customerCookies);
        t4_assert($forbidden['status'] === 404, "Expected hidden order to return 404, got {$forbidden['status']}.");
        $missing = t4_request('GET', $baseUrl . '/orders/999999', null, $customerCookies);
        t4_assert($missing['status'] === 404, "Expected missing order to return 404, got {$missing['status']}.");
    });

    t4_test('dispatcher can see orders across customers', function () use ($baseUrl, $dispatcherCookies): void {
        $response = t4_request('GET', $baseUrl . '/orders?search=DLV-20261017-0001', null, $dispatcherCookies);
        t4_assert($response['status'] === 200, "Expected 200, got {$response['status']}.");
        t4_assert(str_contains($response['body'], 'DLV-20261017-0001'), 'Dispatcher list omitted first customer order.');
        $response2 = t4_request('GET', $baseUrl . '/orders?search=DLV-20261017-0003', null, $dispatcherCookies);
        t4_assert($response2['status'] === 200, "Expected 200, got {$response2['status']}.");
        t4_assert(str_contains($response2['body'], 'DLV-20261017-0003'), 'Dispatcher list omitted second customer order.');
    });

    t4_test('create rejects missing CSRF token without changing database rows', function () use ($baseUrl, $customerCookies): void {
        $db = t4_database();
        $before = [
            'orders' => (int)$db->raw('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'addresses' => (int)$db->raw('SELECT COUNT(*) FROM addresses')->fetchColumn()
        ];
        $response = t4_request('POST', $baseUrl . '/orders', [
            'pickup_address' => 'Makati',
            'pickup_contact_name' => 'Test Sender',
            'pickup_contact_phone' => '+639171234567',
            'dropoff_address' => 'BGC',
            'dropoff_contact_name' => 'Test Receiver',
            'dropoff_contact_phone' => '+639171234568',
            'parcel_description' => 'CSRF test parcel',
            'parcel_weight' => '1',
            'parcel_size' => 'S',
            'distance_km' => '3'
        ], $customerCookies);
        t4_assert($response['status'] === 200, 'Missing-CSRF submission should return the form with an error.');
        $afterOrders = (int)$db->raw('SELECT COUNT(*) FROM orders')->fetchColumn();
        $afterAddresses = (int)$db->raw('SELECT COUNT(*) FROM addresses')->fetchColumn();
        t4_assert($afterOrders === $before['orders'] && $afterAddresses === $before['addresses'], 'Missing-CSRF submission mutated order data.');
    });

    t4_test('delivered orders cannot be cancelled and CSRF failures do not write audit rows', function () use ($baseUrl, $customerCookies): void {
        $db = t4_database();
        $orderId = 7;
        $beforeLogs = (int)$db->raw('SELECT COUNT(*) FROM deliveries WHERE order_id = ?', [$orderId])->fetchColumn();
        $csrfFailure = t4_request('POST', $baseUrl . '/orders/' . $orderId . '/cancel', ['csrf_token' => 'invalid'], $customerCookies);
        t4_assert($csrfFailure['status'] === 302, 'CSRF failure on cancellation should redirect.');
        t4_assert((int)$db->raw('SELECT COUNT(*) FROM deliveries WHERE order_id = ?', [$orderId])->fetchColumn() === $beforeLogs, 'CSRF failure added an audit row.');
        $page = t4_request('GET', $baseUrl . '/orders/new', null, $customerCookies);
        preg_match('/name="csrf_token"\\s+value="([^"]+)"/', $page['body'], $match);
        t4_assert(!empty($match[1]), 'Cancelable order detail did not provide a CSRF token.');
        $cancel = t4_request('POST', $baseUrl . '/orders/' . $orderId . '/cancel', [
            'csrf_token' => $match[1],
            'cancel_reason' => 'Acceptance test state-machine check'
        ], $customerCookies);
        t4_assert($cancel['status'] === 302, 'Rejected cancellation should redirect back to order details.');
        $status = $db->raw('SELECT status FROM orders WHERE id = ?', [$orderId])->fetchColumn();
        $afterLogs = (int)$db->raw('SELECT COUNT(*) FROM deliveries WHERE order_id = ?', [$orderId])->fetchColumn();
        t4_assert($status === 'delivered', 'Delivered order status changed after rejected cancellation.');
        t4_assert($afterLogs === $beforeLogs, 'Rejected cancellation wrote an audit row.');
    });

    $createdOrderId = null;
    t4_test('valid form saves a draft with server fee and initial delivery log', function () use ($baseUrl, $customerCookies, &$createdOrderId): void {
        $page = t4_request('GET', $baseUrl . '/orders/new', null, $customerCookies);
        preg_match('/name="csrf_token"\s+value="([^"]+)"/', $page['body'], $match);
        t4_assert(!empty($match[1]), 'New-order form did not provide a CSRF token.');
        $created = t4_request('POST', $baseUrl . '/orders', [
            'csrf_token' => $match[1],
            'pickup_address' => 'Makati CBD, Ayala Avenue',
            'pickup_contact_name' => 'Test Sender',
            'pickup_contact_phone' => '+639171234567',
            'pickup_notes' => 'Gate 2',
            'dropoff_address' => 'BGC High Street, Taguig City',
            'dropoff_contact_name' => 'Test Receiver',
            'dropoff_contact_phone' => '+639171234568',
            'dropoff_notes' => 'Reception',
            'parcel_description' => 'Integration test parcel',
            'parcel_weight' => '5.0',
            'parcel_size' => 'M',
            'distance_km' => '7.4'
        ], $customerCookies);
        t4_assert($created['status'] === 302, "Expected create redirect, got {$created['status']}. Body: " . substr(strip_tags($created['body']), 0, 1000));
        preg_match('/Location:\s*.*?orders\/(\d+)/i', $created['headers'], $idMatch);
        t4_assert(!empty($idMatch[1]), 'Create redirect did not include the new order ID.');
        $detail = t4_request('GET', $baseUrl . '/orders/' . $idMatch[1], null, $customerCookies);
        t4_assert($detail['status'] === 200, 'Created draft order detail did not load.');
        t4_assert(str_contains($detail['body'], 'Integration test parcel'), 'Created parcel details are missing.');
        t4_assert(str_contains($detail['body'], 'Draft order') || str_contains($detail['body'], 'Draft'), 'Created order was not saved as draft.');
        t4_assert(str_contains($detail['body'], 'Gate 2'), 'Created pickup notes are missing.');
        t4_assert(str_contains($detail['body'], 'Makati CBD, Ayala Avenue'), 'Created pickup address is missing.');
        $db = t4_database();
        $order = $db->raw('SELECT id, status, delivery_fee, distance_km FROM orders WHERE id = ?', [(int)$idMatch[1]])->fetch(PDO::FETCH_ASSOC);
        t4_assert($order && $order['status'] === 'draft', 'Created order is not persisted as draft.');
        $rates = [
            'base_delivery_fee' => 60.00,
            'free_distance_km' => 2.0,
            'per_km_rate' => 12.50,
            'free_weight_kg' => 1.0,
            'per_kg_rate' => 5.00
        ];
        foreach ($db->table('settings')->get_all() as $setting) {
            if (array_key_exists($setting['setting_key'], $rates)) {
                $rates[$setting['setting_key']] = (float)$setting['setting_value'];
            }
        }
        $expectedFee = calculate_delivery_fee(7.4, 5.0, $rates)['total_fee'];
        t4_assert((float)$order['delivery_fee'] === $expectedFee, 'Persisted delivery fee does not match configured server-side fee calculation.');
        t4_assert((float)$order['distance_km'] === 7.4, 'Submitted route distance was not persisted.');
        $addresses = $db->raw('SELECT COUNT(*) FROM addresses WHERE id IN (SELECT pickup_address_id FROM orders WHERE id = ? UNION SELECT dropoff_address_id FROM orders WHERE id = ?)', [(int)$idMatch[1], (int)$idMatch[1]])->fetchColumn();
        t4_assert((int)$addresses === 2, 'Pickup and drop-off addresses were not both saved.');
        $parcel = $db->raw('SELECT description, weight_kg FROM parcels WHERE order_id = ?', [(int)$idMatch[1]])->fetch(PDO::FETCH_ASSOC);
        t4_assert($parcel && $parcel['description'] === 'Integration test parcel' && (float)$parcel['weight_kg'] === 5.0, 'Parcel data was not persisted correctly.');
        $initialLog = $db->raw('SELECT from_status, to_status, actor_type FROM deliveries WHERE order_id = ? ORDER BY id ASC LIMIT 1', [(int)$idMatch[1]])->fetch(PDO::FETCH_ASSOC);
        t4_assert($initialLog && $initialLog['from_status'] === null && $initialLog['to_status'] === 'draft' && $initialLog['actor_type'] === 'customer', 'Initial draft delivery audit row is missing or incorrect.');
        $createdOrderId = (int)$idMatch[1];
    });

    if ($createdOrderId !== null) {
        t4_test('customer can edit only own draft order and immutable history stays unchanged', function () use ($baseUrl, $customerCookies, $dispatcherCookies, $adminCookies, &$createdOrderId): void {
            $db = t4_database();
            $before = $db->raw(
                'SELECT o.order_no, o.status, o.pickup_address_id, o.dropoff_address_id, o.distance_km, o.delivery_fee, p.description, p.weight_kg FROM orders o LEFT JOIN parcels p ON p.order_id = o.id WHERE o.id = ?',
                [$createdOrderId]
            )->fetch(PDO::FETCH_ASSOC);
            $beforeLogs = (int)$db->raw('SELECT COUNT(*) FROM deliveries WHERE order_id = ?', [$createdOrderId])->fetchColumn();
            $editPage = t4_request('GET', $baseUrl . '/orders/' . $createdOrderId . '/edit', null, $customerCookies);
            t4_assert($editPage['status'] === 200, 'Draft edit page did not load for the owner.');
            preg_match('/name="csrf_token"\\s+value="([^"]+)"/', $editPage['body'], $tokenMatch);
            t4_assert(!empty($tokenMatch[1]), 'Draft edit form did not provide a CSRF token.');

            $invalid = t4_request('POST', $baseUrl . '/orders/' . $createdOrderId . '/edit', [
                'csrf_token' => $tokenMatch[1], 'pickup_address' => 'Preserved edit input', 'parcel_weight' => '0', 'distance_km' => '0'
            ], $customerCookies);
            t4_assert($invalid['status'] === 200 && str_contains($invalid['body'], 'Preserved edit input'), 'Invalid draft update did not preserve input.');
            t4_assert((int)$db->raw('SELECT COUNT(*) FROM deliveries WHERE order_id = ?', [$createdOrderId])->fetchColumn() === $beforeLogs, 'Invalid update changed delivery history.');

            $missingCsrf = t4_request('POST', $baseUrl . '/orders/' . $createdOrderId . '/edit', [
                'pickup_address' => 'Must not save'
            ], $customerCookies);
            t4_assert($missingCsrf['status'] === 200, 'Missing-CSRF draft update should redisplay the form.');
            t4_assert((string)$db->raw('SELECT status FROM orders WHERE id = ?', [$createdOrderId])->fetchColumn() === 'draft', 'Missing-CSRF request mutated the order.');

            $updated = t4_request('POST', $baseUrl . '/orders/' . $createdOrderId . '/edit', [
                'csrf_token' => $tokenMatch[1],
                'pickup_address' => 'Updated pickup address', 'pickup_contact_name' => 'Updated sender', 'pickup_contact_phone' => '+639171234567', 'pickup_notes' => 'Updated pickup notes',
                'dropoff_address' => 'Updated drop-off address', 'dropoff_contact_name' => 'Updated receiver', 'dropoff_contact_phone' => '+639171234568', 'dropoff_notes' => 'Updated drop-off notes',
                'parcel_description' => 'Updated test parcel', 'parcel_weight' => '2.5', 'parcel_size' => 'L', 'is_fragile' => '1', 'distance_km' => '9.2'
            ], $customerCookies);
            t4_assert($updated['status'] === 302, 'Valid draft update did not redirect.');
            $after = $db->raw(
                'SELECT o.order_no, o.status, o.pickup_address_id, o.dropoff_address_id, o.distance_km, o.estimated_duration_min, o.delivery_fee, p.description, p.weight_kg, p.size, p.is_fragile FROM orders o LEFT JOIN parcels p ON p.order_id = o.id WHERE o.id = ?',
                [$createdOrderId]
            )->fetch(PDO::FETCH_ASSOC);
            t4_assert($after['order_no'] === $before['order_no'] && $after['status'] === 'draft', 'Update changed order number or status.');
            t4_assert((int)$after['pickup_address_id'] !== (int)$before['pickup_address_id'] && (int)$after['dropoff_address_id'] !== (int)$before['dropoff_address_id'], 'Draft update did not copy-on-write its address snapshots.');
            t4_assert((float)$after['distance_km'] === 9.2 && (int)$after['estimated_duration_min'] === (int)ceil(9.2 * 2.8), 'Updated route values were not saved.');
            t4_assert($after['description'] === 'Updated test parcel' && (float)$after['weight_kg'] === 2.5 && $after['size'] === 'L' && (int)$after['is_fragile'] === 1, 'Updated parcel details were not saved.');
            t4_assert((int)$db->raw('SELECT COUNT(*) FROM deliveries WHERE order_id = ?', [$createdOrderId])->fetchColumn() === $beforeLogs, 'Draft update wrote delivery history.');
            $pickup = $db->raw('SELECT address_line, notes FROM addresses WHERE id = ?', [(int)$before['pickup_address_id']])->fetch(PDO::FETCH_ASSOC);
            $newPickup = $db->raw('SELECT address_line, notes, customer_id FROM addresses WHERE id = ?', [(int)$after['pickup_address_id']])->fetch(PDO::FETCH_ASSOC);
            t4_assert($pickup['address_line'] === 'Makati CBD, Ayala Avenue' && $pickup['notes'] === 'Gate 2', 'Draft update modified the prior address snapshot.');
            t4_assert($newPickup['address_line'] === 'Updated pickup address' && $newPickup['notes'] === 'Updated pickup notes' && $newPickup['customer_id'] === null, 'Draft update did not save a detached pickup snapshot.');
            $rates = ['base_delivery_fee' => 60.00, 'free_distance_km' => 2.0, 'per_km_rate' => 12.50, 'free_weight_kg' => 1.0, 'per_kg_rate' => 5.00];
            foreach ($db->table('settings')->get_all() as $setting) {
                if (array_key_exists($setting['setting_key'], $rates)) $rates[$setting['setting_key']] = (float)$setting['setting_value'];
            }
            t4_assert((float)$after['delivery_fee'] === calculate_delivery_fee(9.2, 2.5, $rates)['total_fee'], 'Updated delivery fee was not calculated server-side.');

            $otherCustomerCookies = t4_login('juan@example.com', 'password123');
            $hidden = t4_request('GET', $baseUrl . '/orders/' . $createdOrderId . '/edit', null, $otherCustomerCookies);
            t4_assert($hidden['status'] === 404, 'Another customer could access the draft edit form.');
            $otherForm = t4_request('GET', $baseUrl . '/orders/new', null, $otherCustomerCookies);
            preg_match('/name="csrf_token"\\s+value="([^"]+)"/', $otherForm['body'], $otherToken);
            t4_assert(!empty($otherToken[1]), 'Other customer form did not provide a CSRF token.');
            $otherPost = t4_request('POST', $baseUrl . '/orders/' . $createdOrderId . '/edit', [
                'csrf_token' => $otherToken[1], 'pickup_address' => 'Unauthorized update'
            ], $otherCustomerCookies);
            t4_assert($otherPost['status'] === 404, 'Another customer could POST an update to the draft.');

            $dispatcherGet = t4_request('GET', $baseUrl . '/orders/' . $createdOrderId . '/edit', null, $dispatcherCookies);
            t4_assert(in_array($dispatcherGet['status'], [302, 403], true) && !str_contains($dispatcherGet['body'], '<form method="post"'), 'Dispatcher could open a draft edit form.');
            $dispatcherPost = t4_request('POST', $baseUrl . '/orders/' . $createdOrderId . '/edit', [
                'csrf_token' => $tokenMatch[1], 'pickup_address' => 'Dispatcher update'
            ], $dispatcherCookies);
            t4_assert($dispatcherPost['status'] === 403, 'Dispatcher could POST an order update.');
            $adminGet = t4_request('GET', $baseUrl . '/orders/' . $createdOrderId . '/edit', null, $adminCookies);
            t4_assert(in_array($adminGet['status'], [302, 403], true) && !str_contains($adminGet['body'], '<form method="post"'), 'Admin could open a draft edit form.');
            $adminPost = t4_request('POST', $baseUrl . '/orders/' . $createdOrderId . '/edit', [
                'csrf_token' => $tokenMatch[1], 'pickup_address' => 'Admin update'
            ], $adminCookies);
            t4_assert($adminPost['status'] === 403, 'Admin could POST an order update.');

            $nonDraftBefore = $db->raw('SELECT status, order_no, distance_km FROM orders WHERE id = 2')->fetch(PDO::FETCH_ASSOC);
            $nonDraftGet = t4_request('GET', $baseUrl . '/orders/2/edit', null, $customerCookies);
            t4_assert($nonDraftGet['status'] === 404, 'Owner could open a non-draft order edit form.');
            $nonDraftPost = t4_request('POST', $baseUrl . '/orders/2/edit', [
                'csrf_token' => $tokenMatch[1], 'pickup_address' => 'Non-draft update'
            ], $customerCookies);
            t4_assert($nonDraftPost['status'] === 404, 'Owner could POST an update to a non-draft order.');
            $nonDraftAfter = $db->raw('SELECT status, order_no, distance_km FROM orders WHERE id = 2')->fetch(PDO::FETCH_ASSOC);
            t4_assert($nonDraftAfter === $nonDraftBefore, 'Rejected non-draft update changed order fields.');
        });
    }

    if ($createdOrderId !== null) {
        t4_test('created order cleanup uses the valid cancellation transition', function () use ($baseUrl, $customerCookies, $createdOrderId): void {
            $page = t4_request('GET', $baseUrl . '/orders/' . $createdOrderId, null, $customerCookies);
            preg_match('/name="csrf_token"\\s+value="([^"]+)"/', $page['body'], $match);
            t4_assert(!empty($match[1]), 'Order detail did not provide a CSRF token.');
            $cancel = t4_request('POST', $baseUrl . '/orders/' . $createdOrderId . '/cancel', [
                'csrf_token' => $match[1],
                'cancel_reason' => 'Automated acceptance test cleanup'
            ], $customerCookies);
            t4_assert($cancel['status'] === 302, 'Draft cancellation did not redirect successfully.');
            $db = t4_database();
            $status = $db->raw('SELECT status FROM orders WHERE id = ?', [$createdOrderId])->fetchColumn();
            t4_assert($status === 'cancelled', 'Cleanup cancellation did not use the state-machine transition.');
        });
    }
} catch (Throwable $e) {
    fwrite(STDERR, "[FAILED] {$e->getMessage()}\n");
    exit(1);
}

echo "All order read/create acceptance tests passed.\n";
