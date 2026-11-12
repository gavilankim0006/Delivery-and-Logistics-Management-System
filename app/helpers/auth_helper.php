<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Authentication & Authorization Helper
 *
 * Provides session-based authentication, role-based authorization,
 * CSRF protection, and login rate limiting (throttling).
 */

if (!function_exists('auth_init')) {
    function auth_init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session cookie flags
            if (!headers_sent()) {
                ini_set('session.cookie_httponly', 1);
                ini_set('session.use_only_cookies', 1);
            }
            session_start();
        }
    }
}

// Auto-initialize session
auth_init();

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return !empty($_SESSION['user']['id']);
    }
}

if (!function_exists('auth_id')) {
    function auth_id(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    }
}

if (!function_exists('auth_role')) {
    function auth_role(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }
}

if (!function_exists('has_role')) {
    function has_role($roles): bool
    {
        if (!auth_check()) {
            return false;
        }
        $userRole = auth_role();
        if (is_array($roles)) {
            return in_array($userRole, $roles, true);
        }
        return $userRole === $roles;
    }
}

if (!function_exists('require_auth')) {
    function require_auth(): void
    {
        if (!auth_check()) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Unauthenticated']);
                exit;
            }
            flash('error', 'Please log in to continue.');
            header('Location: ' . base_url('login'));
            exit;
        }
    }
}

if (!function_exists('require_role')) {
    function require_role($roles): void
    {
        require_auth();

        if (!has_role($roles)) {
            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Forbidden: insufficient privileges']);
                exit;
            }

            // Render 403 Forbidden page
            $errorFile = APP_DIR . 'views/errors/error_403.php';
            if (file_exists($errorFile)) {
                require $errorFile;
            } else {
                echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h1>403 Forbidden</h1><p>You do not have permission to access this resource.</p><a href="' . base_url() . '">Return Home</a></body></html>';
            }
            exit;
        }
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $payloadToken = null): bool
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            return true;
        }

        $sessionToken = $_SESSION['csrf_token'] ?? null;
        if (empty($sessionToken)) {
            return false;
        }

        $postToken = $_POST['csrf_token'] ?? null;
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        $submitted = $payloadToken ?: ($postToken ?: $headerToken);
        if (empty($submitted)) {
            return false;
        }

        return hash_equals($sessionToken, $submitted);
    }
}

if (!function_exists('login_throttle_check')) {
    function login_throttle_check(string $key, int $maxAttempts = 5, int $decaySeconds = 300): array
    {
        $dir = defined('ROOT_DIR') ? ROOT_DIR . 'runtime/throttles' : dirname(__DIR__, 2) . '/runtime/throttles';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $file = $dir . '/' . md5($key) . '.json';
        $attempts = [];
        if (file_exists($file)) {
            $data = json_decode(@file_get_contents($file), true);
            if (is_array($data)) {
                $attempts = $data;
            }
        }

        $now = time();
        $recent = array_filter($attempts, fn($t) => ($now - $t) < $decaySeconds);
        @file_put_contents($file, json_encode(array_values($recent)));

        $count = count($recent);
        if ($count >= $maxAttempts) {
            $oldest = min($recent);
            $retryAfter = $decaySeconds - ($now - $oldest);
            return [
                'allowed' => false,
                'retry_after' => max(1, $retryAfter),
                'attempts' => $count
            ];
        }

        return [
            'allowed' => true,
            'retry_after' => 0,
            'attempts' => $count
        ];
    }
}

if (!function_exists('login_throttle_record')) {
    function login_throttle_record(string $key): void
    {
        $dir = defined('ROOT_DIR') ? ROOT_DIR . 'runtime/throttles' : dirname(__DIR__, 2) . '/runtime/throttles';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $file = $dir . '/' . md5($key) . '.json';
        $attempts = [];
        if (file_exists($file)) {
            $data = json_decode(@file_get_contents($file), true);
            if (is_array($data)) {
                $attempts = $data;
            }
        }
        $attempts[] = time();
        @file_put_contents($file, json_encode(array_values($attempts)));
    }
}

if (!function_exists('login_throttle_clear')) {
    function login_throttle_clear(string $key): void
    {
        $dir = defined('ROOT_DIR') ? ROOT_DIR . 'runtime/throttles' : dirname(__DIR__, 2) . '/runtime/throttles';
        $file = $dir . '/' . md5($key) . '.json';
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}

if (!function_exists('auth_login')) {
    function auth_login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'role_id' => (int)$user['role_id'],
            'role' => $user['role_name'] ?? ($user['role_id'] == 1 ? 'admin' : ($user['role_id'] == 2 ? 'dispatcher' : ($user['role_id'] == 4 ? 'rider' : 'customer'))),
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? null,
            'customer_id' => $user['customer_id'] ?? null,
        ];
    }
}

if (!function_exists('auth_logout')) {
    function auth_logout(): void
    {
        unset($_SESSION['user']);
        session_regenerate_id(true);
    }
}

if (!function_exists('flash')) {
    function flash(string $key, $message = null)
    {
        if ($message !== null) {
            $_SESSION['flash'][$key] = $message;
            return null;
        }

        if (isset($_SESSION['flash'][$key])) {
            $msg = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $msg;
        }

        return null;
    }
}

if (!function_exists('e')) {
    function e($val): string
    {
        return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('is_json_request')) {
    function is_json_request(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json'))
            || (isset($_SERVER['CONTENT_TYPE']) && str_contains(strtolower($_SERVER['CONTENT_TYPE']), 'application/json'))
            || isset($_GET['ajax'])
            || isset($_GET['json']);
    }
}

if (!function_exists('json_response')) {
    function json_response(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }
}

