<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Auth extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        $this->call->model('User_model');
    }

    /**
     * Show login form and process authentication
     */
    public function login()
    {
        $this->handle_login(false);
    }

    /** Applicant-specific login, always returning successful applicants to the application form. */
    public function applicant_login(): void
    {
        $this->handle_login(true);
    }

    private function handle_login(bool $forApplicant): void
    {
        $loginPath = $forApplicant ? 'applicant/login' : 'login';
        if (auth_check()) {
            if ($forApplicant) {
                // If user is already staff/rider, redirect directly to their dashboard
                if (in_array(auth_role(), ['admin', 'dispatcher', 'rider'], true)) {
                    $this->redirect_by_role(auth_role());
                    return;
                }
                redirect('careers/apply');
                return;
            }
            $this->redirect_by_role(auth_role());
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('error', 'Invalid security token. Please try again.');
                header('Location: ' . base_url($loginPath));
                exit;
            }

            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $throttleKey = $email . '|' . $ip;

            $throttle = login_throttle_check($throttleKey, 5, 300);
            if (!$throttle['allowed']) {
                $retryAfter = $throttle['retry_after'];
                flash('old_email', $email);
                flash('error', "Too many failed login attempts. Please wait {$retryAfter} seconds before trying again.");
                header('Location: ' . base_url($loginPath));
                exit;
            }

            if (empty($email) || empty($password)) {
                flash('old_email', $email);
                flash('error', 'Please provide both email address and password.');
                header('Location: ' . base_url($loginPath));
                exit;
            }

            $user = $this->User_model->authenticate($email, $password);
            if ($user) {
                login_throttle_clear($throttleKey);

                // Auto-sync: If user currently has customer role (3), check if an approved job application exists
                if ((int)$user['role_id'] === 3) {
                    $userEmailLower = strtolower(trim((string)$user['email']));
                    $approvedApp = $this->db->raw(
                        "SELECT * FROM job_applications WHERE (user_id = ? OR email = ?) AND status = 'approved' ORDER BY id DESC LIMIT 1",
                        [(int)$user['id'], $userEmailLower]
                    )->fetch(PDO::FETCH_ASSOC);

                    if ($approvedApp) {
                        $roleApplied = strtolower(trim((string)($approvedApp['role_applied'] ?? 'rider')));
                        $targetRoleId = ($roleApplied === 'dispatcher') ? 2 : 4;
                        if ($roleApplied === 'rider') {
                            $existingRider = $this->db->raw('SELECT id FROM riders WHERE user_id = ? AND deleted_at IS NULL LIMIT 1', [(int)$user['id']])->fetch(PDO::FETCH_ASSOC);
                            if (!$existingRider) {
                                $this->call->model('Rider_model');
                                $this->Rider_model->create([
                                    'user_id'      => (int)$user['id'],
                                    'name'         => $approvedApp['full_name'],
                                    'phone'        => $approvedApp['phone'],
                                    'vehicle_type' => 'Motorcycle',
                                    'plate_number' => 'PENDING-' . $user['id'],
                                    'status'       => 'available'
                                ]);
                            }
                        }
                        $this->User_model->update_user((int)$user['id'], [
                            'role_id' => $targetRoleId,
                            'status'  => 'active'
                        ]);
                        if (empty($approvedApp['user_id'])) {
                            $this->db->raw('UPDATE job_applications SET user_id = ? WHERE id = ?', [(int)$user['id'], (int)$approvedApp['id']]);
                        }
                        $refreshed = $this->User_model->find_by_id((int)$user['id']);
                        if ($refreshed) {
                            $user = $refreshed;
                        }
                    }
                }

                auth_login($user);
                flash('success', "Welcome back, {$user['name']}!");
                if ($forApplicant) {
                    if (in_array($user['role_name'], ['admin', 'dispatcher', 'rider'], true)) {
                        $this->redirect_by_role($user['role_name']);
                        return;
                    }
                    redirect('careers/apply');
                    return;
                }
                $this->redirect_by_role($user['role_name']);
                return;
            }

            login_throttle_record($throttleKey);
            flash('old_email', $email);
            flash('error', 'Invalid credentials or inactive account.');
            header('Location: ' . base_url($loginPath));
            exit;
        }

        $this->call->view('auth/login', [
            'page_title' => $forApplicant ? 'Applicant sign in' : 'Sign In',
            'login_context' => $forApplicant ? 'applicant' : 'customer',
        ]);
    }

    /**
     * Request a single-use password reset link by email.
     */
    public function forgot_password()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('error', 'Invalid security token. Please try again.');
                header('Location: ' . base_url('forgot-password'));
                exit;
            }

            $email = strtolower(trim($_POST['email'] ?? ''));
            $generic = 'If an active account exists for that email, we have sent a password reset link.';
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $user = $this->User_model->find_by_email($email);
                if ($user && $user['status'] === 'active') {
                    $db = lava_instance()->db;
                    $db->table('password_reset_tokens')->where('user_id', (int)$user['id'])->delete();
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + 3600);
                    $db->raw('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at)', [(int)$user['id'], hash('sha256', $token), $expires]);
                    require_once APP_DIR . 'libraries/EmailService.php';
                    $url = base_url('reset-password?token=' . rawurlencode($token));
                    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                    $body = '<p>We received a request to reset your password.</p><p><a href="' . $safeUrl . '">Reset your password</a></p><p>This link expires in one hour. If you did not request this, ignore this email.</p>';
                    $sent = (new EmailService())->send($user['email'], 'Reset your password', $body);
                    if (!$sent['success']) {
                        log_message('error', 'Password reset email failed: ' . ($sent['error'] ?? 'unknown error'));
                    }
                }
            }
            flash('success', $generic);
            header('Location: ' . base_url('login'));
            exit;
        }

        $this->call->view('auth/forgot_password', ['page_title' => 'Forgot password']);
    }

    /** Reset password using a valid, unexpired, single-use email token. */
    public function reset_password()
    {
        $token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
        $validToken = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
        $row = $validToken ? lava_instance()->db->raw(
            'SELECT user_id FROM password_reset_tokens WHERE token_hash = ? AND expires_at > ? LIMIT 1',
            [hash('sha256', $token), date('Y-m-d H:i:s')]
        )->fetch(PDO::FETCH_ASSOC) : null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('error', 'Invalid security token. Please try again.');
                header('Location: ' . base_url('reset-password?token=' . rawurlencode($token)));
                exit;
            }
            $password = (string)($_POST['password'] ?? '');
            if (!$row || strlen($password) < 8) {
                flash('error', !$row ? 'This password reset link is invalid or expired.' : 'Password must be at least 8 characters.');
                header('Location: ' . base_url('reset-password?token=' . rawurlencode($token)));
                exit;
            }
            $db = lava_instance()->db;
            $db->begin();
            try {
                $db->table('users')->where('id', (int)$row['user_id'])->update(['password' => password_hash($password, PASSWORD_BCRYPT)]);
                $db->table('password_reset_tokens')->where('user_id', (int)$row['user_id'])->delete();
                $db->commit();
            } catch (Throwable $e) {
                $db->roll_back();
                log_message('error', 'Password reset failed: ' . $e->getMessage());
                flash('error', 'Unable to update password right now. Please try again.');
                header('Location: ' . base_url('reset-password?token=' . rawurlencode($token)));
                exit;
            }
            flash('success', 'Password updated. You can now sign in.');
            header('Location: ' . base_url('login'));
            exit;
        }

        $this->call->view('auth/reset_password', ['page_title' => 'Reset password', 'token' => $token, 'token_valid' => (bool)$row]);
    }

    /**
     * Show registration form and process customer sign-up
     */
    public function register()
    {
        if (auth_check()) {
            $this->redirect_by_role(auth_role());
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('error', 'Invalid security token. Please try again.');
                header('Location: ' . base_url('register'));
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $companyName = trim($_POST['company_name'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            $oldRegister = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'company_name' => $companyName,
            ];

            // Server-side validations matching front-end rules
            if (empty($name) || mb_strlen($name) < 2) {
                flash('old_register', $oldRegister);
                flash('error', 'Please enter your full name (at least 2 characters).');
                header('Location: ' . base_url('register'));
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('old_register', $oldRegister);
                flash('error', 'Please enter a valid email address.');
                header('Location: ' . base_url('register'));
                exit;
            }

            $cleanedPhone = preg_replace('/[\s\-\(\)\.]/', '', $phone);
            if (empty($phone) || !preg_match('/^(09|\+639|639)\d{9}$/', $cleanedPhone)) {
                flash('old_register', $oldRegister);
                flash('error', 'Please enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).');
                header('Location: ' . base_url('register'));
                exit;
            } else {
                if (preg_match('/^(?:09|\+639|639)(\d{9})$/', $cleanedPhone, $m)) {
                    $phone = '09' . $m[1];
                }
            }

            if (strlen($password) < 8) {
                flash('old_register', $oldRegister);
                flash('error', 'Password must be at least 8 characters long.');
                header('Location: ' . base_url('register'));
                exit;
            }

            if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/\d/', $password)) {
                flash('old_register', $oldRegister);
                flash('error', 'Password must contain at least one letter and one number.');
                header('Location: ' . base_url('register'));
                exit;
            }

            if ($password !== $passwordConfirm) {
                flash('old_register', $oldRegister);
                flash('error', 'Password confirmation does not match.');
                header('Location: ' . base_url('register'));
                exit;
            }

            $res = $this->User_model->register_customer([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password
            ], [
                'company_name' => $companyName
            ]);

            if ($res['success']) {
                auth_login($res['user']);
                flash('success', 'Your account has been created successfully. Welcome to SwiftLogix!');
                header('Location: ' . base_url('orders/new'));
                exit;
            } else {
                flash('old_register', $oldRegister);
                flash('error', $res['error'] ?? 'Registration failed. Please try again.');
                header('Location: ' . base_url('register'));
                exit;
            }
        }

        $this->call->view('auth/register', [
            'page_title' => 'Register Customer Account',
        ]);
    }

    /**
     * User profile view and update
     */
    public function profile()
    {
        require_auth();
        $user = $this->User_model->find_by_id(auth_id());

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('error', 'Invalid security token.');
                header('Location: ' . base_url('profile'));
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            if (empty($name) || mb_strlen($name) < 2) {
                flash('error', 'Name cannot be empty and must be at least 2 characters.');
                header('Location: ' . base_url('profile'));
                exit;
            }

            if (!empty($phone)) {
                $cleanedPhone = preg_replace('/[\s\-\(\)\.]/', '', $phone);
                if (!preg_match('/^(09|\+639|639)\d{9}$/', $cleanedPhone)) {
                    flash('error', 'Please enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).');
                    header('Location: ' . base_url('profile'));
                    exit;
                } else {
                    if (preg_match('/^(?:09|\+639|639)(\d{9})$/', $cleanedPhone, $m)) {
                        $phone = '09' . $m[1];
                    }
                }
            }

            $updateData = [
                'name' => $name,
                'phone' => $phone
            ];
            if (auth_role() === 'customer') {
                $updateData['sms_opt_in'] = !empty($_POST['sms_opt_in']) ? 1 : 0;
                $updateData['delivery_status_sms_opt_in'] = !empty($_POST['delivery_status_sms_opt_in']) ? 1 : 0;
                $updateData['push_opt_in'] = !empty($_POST['push_opt_in']) ? 1 : 0;
                $updateData['email_notifications_opt_in'] = !empty($_POST['email_notifications_opt_in']) ? 1 : 0;
            }

            if (!empty($password)) {
                if (strlen($password) < 8) {
                    flash('error', 'New password must be at least 8 characters.');
                    header('Location: ' . base_url('profile'));
                    exit;
                }
                if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/\d/', $password)) {
                    flash('error', 'New password must contain at least one letter and one number.');
                    header('Location: ' . base_url('profile'));
                    exit;
                }
                if ($password !== $passwordConfirm) {
                    flash('error', 'Password confirmation does not match.');
                    header('Location: ' . base_url('profile'));
                    exit;
                }
                $updateData['password'] = $password;
            }

            $profileChanged = $name !== (string)($user['name'] ?? '')
                || $phone !== (string)($user['phone'] ?? '')
                || (auth_role() === 'customer' && (!empty($_POST['sms_opt_in']) ? 1 : 0) !== (int)($user['sms_opt_in'] ?? 0))
                || (auth_role() === 'customer' && (!empty($_POST['delivery_status_sms_opt_in']) ? 1 : 0) !== (int)($user['delivery_status_sms_opt_in'] ?? 0))
                || (auth_role() === 'customer' && (!empty($_POST['push_opt_in']) ? 1 : 0) !== (int)($user['push_opt_in'] ?? 0))
                || (auth_role() === 'customer' && (!empty($_POST['email_notifications_opt_in']) ? 1 : 0) !== (int)($user['email_notifications_opt_in'] ?? 1))
                || !empty($password);

            if ($profileChanged && !$this->User_model->update_user(auth_id(), $updateData)) {
                flash('error', 'Profile changes could not be saved. Please try again.');
                header('Location: ' . base_url('profile'));
                exit;
            }
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['phone'] = $phone;

            flash('success', 'Profile updated successfully.');
            header('Location: ' . base_url('profile'));
            exit;
        }

        $this->call->view('auth/profile', [
            'page_title' => 'My Profile',
            'user' => $user
        ]);
    }

    public function profile_data(): void
    {
        $this->call->library('api');
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            $this->api->respond(['success' => false, 'error' => 'Method not allowed.'], 405);
            return;
        }
        require_auth();
        $user = $this->User_model->find_by_id(auth_id());
        $this->api->respond(['success' => true, 'data' => ['user' => $user, 'role' => auth_role()]]);
    }

    public function api_profile(): void
    {
        $this->call->library('api');
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->api->respond(['success' => false, 'error' => 'Method not allowed.'], 405);
            return;
        }
        require_auth();
        if (!verify_csrf()) {
            $this->api->respond(['success' => false, 'error' => 'Invalid security token.'], 400);
            return;
        }
        $user = $this->User_model->find_by_id(auth_id());
        if (!$user) {
            $this->api->respond(['success' => false, 'error' => 'User not found.'], 404);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (empty($name) || mb_strlen($name) < 2) {
            $this->api->respond(['success' => false, 'error' => 'Name cannot be empty and must be at least 2 characters.'], 422);
            return;
        }
        if (!empty($phone)) {
            $cleanedPhone = preg_replace('/[\s\-\(\)\.]/', '', $phone);
            if (!preg_match('/^(09|\+639|639)\d{9}$/', $cleanedPhone)) {
                $this->api->respond(['success' => false, 'error' => 'Please enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).'], 422);
                return;
            }
            if (preg_match('/^(?:09|\+639|639)(\d{9})$/', $cleanedPhone, $m)) {
                $phone = '09' . $m[1];
            }
        }
        $updateData = ['name' => $name, 'phone' => $phone];
        if (auth_role() === 'customer') {
            $updateData['sms_opt_in'] = !empty($_POST['sms_opt_in']) ? 1 : 0;
            $updateData['delivery_status_sms_opt_in'] = !empty($_POST['delivery_status_sms_opt_in']) ? 1 : 0;
            $updateData['push_opt_in'] = !empty($_POST['push_opt_in']) ? 1 : 0;
            $updateData['email_notifications_opt_in'] = !empty($_POST['email_notifications_opt_in']) ? 1 : 0;
        }
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $this->api->respond(['success' => false, 'error' => 'New password must be at least 8 characters.'], 422);
                return;
            }
            if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/\d/', $password)) {
                $this->api->respond(['success' => false, 'error' => 'New password must contain at least one letter and one number.'], 422);
                return;
            }
            if ($password !== $passwordConfirm) {
                $this->api->respond(['success' => false, 'error' => 'Password confirmation does not match.'], 422);
                return;
            }
            $updateData['password'] = $password;
        }

        if (!$this->User_model->update_user(auth_id(), $updateData)) {
            $this->api->respond(['success' => false, 'error' => 'Profile changes could not be saved. Please try again.'], 500);
            return;
        }
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['phone'] = $phone;

        $updated = $this->User_model->find_by_id(auth_id());
        $this->api->respond(['success' => true, 'data' => ['user' => $updated, 'role' => auth_role()], 'message' => 'Profile updated successfully.']);
    }

    /**
     * Terminate user session
     */
    public function logout()
    {
        auth_logout();
        flash('success', 'You have been successfully logged out.');
        if (is_json_request()) {
            json_response(['success' => true, 'redirect' => base_url('login')]);
        }
        header('Location: ' . base_url('login'));
        exit;
    }

    /**
     * AJAX endpoint to save a browser Web Push subscription.
     */
    public function save_token()
    {
        require_auth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (!verify_csrf($payload['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
            exit;
        }
        $subscription = $payload['subscription'] ?? null;
        if (!is_array($subscription) || empty($subscription['endpoint']) || empty($subscription['keys']['p256dh']) || empty($subscription['keys']['auth'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'A valid browser push subscription is required.']);
            exit;
        }
        $token = json_encode($subscription, JSON_UNESCAPED_SLASHES);
        if ($token === false || strlen($token) > 4096) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Browser push subscription is invalid or too large.']);
            exit;
        }

        $saved = $this->User_model->save_fcm_token((int) auth_id(), $token);
        echo json_encode(['success' => (bool)$saved]);
        exit;
    }

    /**
     * AJAX endpoint to send a test push notification
     */
    public function test_push()
    {
        require_auth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (!verify_csrf($payload['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
            exit;
        }

        $subscription = $this->User_model->get_fcm_token((int) auth_id());
        if (!$subscription) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'No active browser subscription is registered. Click "Enable browser notifications" first.']);
            exit;
        }

        try {
            $isWebPush = str_starts_with($subscription, '{');
            if ($isWebPush) {
                require_once APP_DIR . 'libraries/BrowserPushService.php';
                $push = new BrowserPushService();
                $result = $push->sendToSubscription(
                    $subscription,
                    'Dispatch Test Notification',
                    'Browser notifications are working correctly for your account.',
                    ['type' => 'test', 'url' => base_url('profile')]
                );
            } else {
                require_once APP_DIR . 'libraries/FirebasePushService.php';
                $push = new FirebasePushService();
                $result = $push->sendToToken(
                    $subscription,
                    'Dispatch Test Notification',
                    'Browser notifications are working correctly for your account.',
                    ['type' => 'test', 'url' => base_url('profile')]
                );
            }

            if (!empty($result['sent'])) {
                echo json_encode(['success' => true, 'message' => 'Test notification delivered successfully.']);
            } else {
                http_response_code(!empty($result['disabled']) ? 503 : 502);
                echo json_encode([
                    'success' => false,
                    'error' => $result['error'] ?? 'Browser push delivery failed.',
                ]);
            }
        } catch (Throwable $e) {
            http_response_code(502);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
        exit;
    }

    /**
     * AJAX endpoint to send a test SMS via Semaphore
     */
    public function test_sms()
    {
        require_auth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (!verify_csrf($payload['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
            exit;
        }

        $user = $this->User_model->find_by_id(auth_id());
        $phone = trim((string)($payload['phone'] ?? ($user['phone'] ?? '')));
        if (empty($phone)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Please provide or save a mobile number before requesting a test SMS.']);
            exit;
        }

        $cleanedPhone = preg_replace('/[\s\-\(\)\.]/', '', $phone);
        if (preg_match('/^(?:09|\+639|639)(\d{9})$/', $cleanedPhone, $matches)) {
            $normalizedPhone = '09' . $matches[1];
        } elseif (preg_match('/^9\d{9}$/', $cleanedPhone)) {
            $normalizedPhone = '0' . $cleanedPhone;
        } else {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Please enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).']);
            exit;
        }

        try {
            require_once APP_DIR . 'libraries/SemaphoreService.php';
            $semaphore = new SemaphoreService();
            $msg = 'Dispatch: Semaphore test message. Notifications are active for your account.';
            $result = $semaphore->send($normalizedPhone, $msg);

            if (!empty($result['sent'])) {
                echo json_encode(['success' => true, 'message' => "Test SMS sent to {$normalizedPhone}."]);
            } elseif (!empty($result['disabled'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Semaphore SMS is currently disabled in system settings (SEMAPHORE_ENABLED != 1).'
                ]);
            } else {
                http_response_code(502);
                echo json_encode([
                    'success' => false,
                    'error' => $result['error'] ?? 'Semaphore SMS failed. Verify your API key and sender name in settings.'
                ]);
            }
        } catch (Throwable $e) {
            http_response_code(502);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * AJAX endpoint to send a test email notification
     */
    public function test_email()
    {
        require_auth();
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (!verify_csrf($payload['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
            exit;
        }

        $user = $this->User_model->find_by_id(auth_id());
        $email = $user['email'] ?? null;
        if (!$email) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'No email address found for this account.']);
            exit;
        }

        require_once APP_DIR . 'libraries/EmailService.php';
        $emailService = new EmailService();
        $subject = 'Dispatch Test Email Notification';
        $body = "
            <p>Hello " . e($user['name'] ?? 'User') . ",</p>
            <p>This is a test notification confirming that email alerts are configured and delivering properly for your Dispatch account.</p>
            <p>You can manage your notification preferences anytime from your Dispatch account profile settings.</p>
            <p>Best regards,<br>The Dispatch Team</p>
        ";

        $result = $emailService->send($email, $subject, $body);

        if (!empty($result['success'])) {
            if (!empty($result['simulated'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Email is currently running in simulated/log mode (MAIL_MAILER=log).'
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => "Test email sent to {$email}. Please check your Inbox and Spam/Junk folder."
                ]);
            }
        } else {
            http_response_code(502);
            echo json_encode([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to send test email. Please verify SMTP settings.'
            ]);
        }
        exit;
    }

    /**
     * Internal role-based redirect helper
     */
    private function redirect_by_role(string $role): void
    {
        switch ($role) {
            case 'admin':
                header('Location: ' . base_url('admin'));
                break;
            case 'dispatcher':
                header('Location: ' . base_url('dispatch'));
                break;
            case 'rider':
                header('Location: ' . base_url('rider'));
                break;
            default:
                header('Location: ' . base_url('orders'));
        }
        exit;
    }
}
