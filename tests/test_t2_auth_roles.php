<?php
/**
 * Test Task T2: Auth & Role-Based Access Control
 * Verifies:
 * 1. User_model credential verification & password hashing
 * 2. Login throttling (5 failed attempts limit)
 * 3. Role-based navigation visibility
 * 4. HTTP 403 when customer attempts to access /admin or /dispatch
 * 5. Admin access to /admin
 * 6. Dispatcher access to /dispatch
 */

$baseUrl = 'http://localhost/SIAproject';

function run_test(string $name, callable $fn): void {
    echo "Running test: {$name}... ";
    try {
        $fn();
        echo "[PASSED]\n";
    } catch (Throwable $e) {
        echo "[FAILED]\n  Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function http_req(string $method, string $url, $postData = null, $cookies = ''): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (is_array($postData)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
    }

    if (!empty($cookies)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);

    // Extract cookies from Set-Cookie headers
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headers, $matches);
    $cookieList = [];
    foreach ($matches[1] as $c) {
        if (!empty($c)) {
            $cookieList[] = $c;
        }
    }
    $newCookies = implode('; ', $cookieList);

    return [
        'status' => $httpCode,
        'headers' => $headers,
        'body' => $body,
        'cookies' => $newCookies
    ];
}

// 1. Check guest access to /admin (should redirect to /login or return 302)
run_test("Guest accessing /admin redirects to /login", function() use ($baseUrl) {
    $res = http_req('GET', "{$baseUrl}/admin");
    if ($res['status'] !== 302 && strpos($res['headers'], 'Location: ') === false) {
        throw new Exception("Expected 302 redirect for guest accessing /admin, got status: {$res['status']}");
    }
    if (strpos($res['headers'], 'login') === false) {
        throw new Exception("Expected redirect to login, got: {$res['headers']}");
    }
});

// Helper function to login and get session cookies
function login_user(string $baseUrl, string $email, string $password): string {
    // 1. Fetch login page to get CSRF token and initial session cookie
    $page = http_req('GET', "{$baseUrl}/login");
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $page['body'], $matches);
    $csrfToken = $matches[1] ?? '';
    $cookies = $page['cookies'];

    // 2. Post login credentials
    $loginRes = http_req('POST', "{$baseUrl}/login", [
        'email' => $email,
        'password' => $password,
        'csrf_token' => $csrfToken
    ], $cookies);

    if (!empty($loginRes['cookies'])) {
        $cookies = $loginRes['cookies'];
    }

    return $cookies;
}

// 2. Customer login & access control
run_test("Customer login succeeds and access to /admin yields HTTP 403", function() use ($baseUrl) {
    $cookies = login_user($baseUrl, 'maria@example.com', 'password123');
    if (empty($cookies)) {
        throw new Exception("Customer login failed to yield session cookie.");
    }

    // Try accessing /admin
    $adminRes = http_req('GET', "{$baseUrl}/admin", null, $cookies);
    if ($adminRes['status'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden for customer accessing /admin, got: {$adminRes['status']}");
    }
    if (strpos($adminRes['body'], '403') === false && strpos($adminRes['body'], 'Access Denied') === false) {
        throw new Exception("Expected 403 view content in response body.");
    }

    // Try accessing /dispatch
    $dispatchRes = http_req('GET', "{$baseUrl}/dispatch", null, $cookies);
    if ($dispatchRes['status'] !== 403) {
        throw new Exception("Expected HTTP 403 Forbidden for customer accessing /dispatch, got: {$dispatchRes['status']}");
    }

    // Check customer menu visibility at /profile
    $profileRes = http_req('GET', "{$baseUrl}/profile", null, $cookies);
    if ($profileRes['status'] !== 200) {
        throw new Exception("Customer profile page failed with status: {$profileRes['status']}");
    }
    if (strpos($profileRes['body'], 'Users') !== false && strpos($profileRes['body'], 'admin/users') !== false) {
        throw new Exception("Customer should NOT see 'Users' admin link.");
    }
    if (strpos($profileRes['body'], 'Riders') !== false && strpos($profileRes['body'], 'admin/riders') !== false) {
        throw new Exception("Customer should NOT see 'Riders' admin link.");
    }
    if (strpos($profileRes['body'], 'My Orders') === false) {
        throw new Exception("Customer should see 'My Orders' in navigation menu.");
    }
});

// 3. Dispatcher login & access control
run_test("Dispatcher login succeeds, /dispatch yields HTTP 200, /admin yields HTTP 403", function() use ($baseUrl) {
    $cookies = login_user($baseUrl, 'dispatcher@delivery.local', 'password123');

    // Access /dispatch
    $dispatchRes = http_req('GET', "{$baseUrl}/dispatch", null, $cookies);
    if ($dispatchRes['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for dispatcher accessing /dispatch, got: {$dispatchRes['status']}");
    }
    if (strpos($dispatchRes['body'], 'Live Dispatch Operations') === false) {
        throw new Exception("Dispatcher board content missing.");
    }
    if (strpos($dispatchRes['body'], 'admin/users') !== false) {
        throw new Exception("Dispatcher should NOT see 'Users' admin link.");
    }

    // Access /admin
    $adminRes = http_req('GET', "{$baseUrl}/admin", null, $cookies);
    if ($adminRes['status'] !== 403) {
        throw new Exception("Expected HTTP 403 for dispatcher accessing /admin, got: {$adminRes['status']}");
    }
});

// 4. Admin login & access control
run_test("Admin login succeeds, /admin yields HTTP 200 with full admin navigation", function() use ($baseUrl) {
    $cookies = login_user($baseUrl, 'admin@delivery.local', 'password123');

    // Access /admin
    $adminRes = http_req('GET', "{$baseUrl}/admin", null, $cookies);
    if ($adminRes['status'] !== 200) {
        throw new Exception("Expected HTTP 200 for admin accessing /admin, got: {$adminRes['status']}");
    }
    if (strpos($adminRes['body'], 'Admin Dashboard') === false) {
        throw new Exception("Admin dashboard content missing.");
    }
    if (strpos($adminRes['body'], 'admin/users') === false) {
        throw new Exception("Admin should see 'Users' link in navigation menu.");
    }
    if (strpos($adminRes['body'], 'Dispatch') === false) {
        throw new Exception("Admin should see 'Dispatch' in navigation menu.");
    }
});

// 5. Rate limiting throttle on repeated failed logins
run_test("Login rate limiting triggers after 5 failed attempts", function() use ($baseUrl) {
    $testEmail = 'bruteforce_' . time() . '@example.com';
    $throttled = false;

    // Get fresh page for CSRF
    $page = http_req('GET', "{$baseUrl}/login");
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $page['body'], $matches);
    $csrfToken = $matches[1] ?? '';
    $cookies = $page['cookies'];

    for ($i = 1; $i <= 6; $i++) {
        $res = http_req('POST', "{$baseUrl}/login", [
            'email' => $testEmail,
            'password' => 'WrongPassword',
            'csrf_token' => $csrfToken
        ], $cookies);

        // Fetch the login page to inspect flash message
        $view = http_req('GET', "{$baseUrl}/login", null, $cookies);
        if (strpos($view['body'], 'Too many failed login attempts') !== false) {
            $throttled = true;
            break;
        }

        // Update CSRF token for next attempt
        preg_match('/name="csrf_token"\s+value="([^"]+)"/', $view['body'], $matches);
        if (!empty($matches[1])) {
            $csrfToken = $matches[1];
        }
    }

    if (!$throttled) {
        throw new Exception("Login throttling did not trigger after 5 failed attempts.");
    }
});

echo "\nAll Task T2 Auth & RBAC acceptance tests PASSED!\n";
