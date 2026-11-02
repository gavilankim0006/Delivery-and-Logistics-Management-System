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
        if (auth_check()) {
            $this->redirect_by_role(auth_role());
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('error', 'Invalid security token. Please try again.');
                header('Location: ' . base_url('login'));
                exit;
            }

            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $throttleKey = $email . '|' . $ip;

            // Check login throttling (max 5 attempts per 5 minutes)
            $throttle = login_throttle_check($throttleKey, 5, 300);
            if (!$throttle['allowed']) {
                $retryAfter = $throttle['retry_after'];
                flash('old_email', $email);
                flash('error', "Too many failed login attempts. Please wait {$retryAfter} seconds before trying again.");
                header('Location: ' . base_url('login'));
                exit;
            }

            if (empty($email) || empty($password)) {
                flash('old_email', $email);
                flash('error', 'Please provide both email address and password.');
                header('Location: ' . base_url('login'));
                exit;
            }

            $user = $this->User_model->authenticate($email, $password);

            if ($user) {
                login_throttle_clear($throttleKey);
                auth_login($user);
                flash('success', "Welcome back, {$user['name']}!");
                $this->redirect_by_role($user['role_name']);
                return;
            } else {
                login_throttle_record($throttleKey);
                flash('old_email', $email);
                flash('error', 'Invalid credentials or inactive account.');
                header('Location: ' . base_url('login'));
                exit;
            }
        }

        $this->call->view('auth/login', [
            'page_title' => 'Sign In',
        ]);
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

    /**
     * Terminate user session
     */
    public function logout()
    {
        auth_logout();
        flash('success', 'You have been successfully logged out.');
        header('Location: ' . base_url('login'));
        exit;
    }

    /**
     * AJAX endpoint to save client FCM token
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
        $token = trim($payload['token'] ?? '');

        if (empty($token)) {
            http_response_code(422);
            echo json_encode(['error' => 'Token is required']);
            exit;
        }

        $saved = $this->User_model->save_fcm_token(auth_id(), $token);
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

        $token = $this->User_model->get_fcm_token(auth_id());
        if (!$token) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'No active device registered. Click "Enable browser notifications" first.']);
            exit;
        }

        $this->call->library('FirebaseService');
        $sent = $this->firebaseservice->sendToDevice(
            $token,
            'Dispatch Test Notification',
            'Browser notifications are working correctly for your account.',
            ['type' => 'test', 'url' => base_url('profile')]
        );

        if ($sent) {
            echo json_encode(['success' => true, 'message' => 'Test notification delivered successfully.']);
        } else {
            http_response_code(502);
            echo json_encode(['success' => false, 'error' => 'Firebase delivery failed. Check server logs or re-enable notifications.']);
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
        $phone = $user['phone'] ?? null;
        if (!$phone) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Save your mobile number in your profile before requesting a test SMS.']);
            exit;
        }

        $this->call->library('SemaphoreService');
        $msg = 'Dispatch: Semaphore test message. Notifications are active for your account.';
        $sent = $this->semaphoreservice->send($phone, $msg);

        if ($sent) {
            echo json_encode(['success' => true, 'message' => "Test SMS sent to {$phone}."]);
        } else {
            http_response_code(502);
            echo json_encode(['success' => false, 'error' => 'Semaphore SMS failed. Verify your API key and sender name in settings.']);
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

        $this->call->library('EmailService');
        $subject = 'Dispatch Test Email Notification';
        $body = "
            <p>Hello " . e($user['name'] ?? 'User') . ",</p>
            <p>This is a test notification confirming that email alerts are configured and delivering properly for your Dispatch account.</p>
            <p>You can manage your notification preferences anytime from your <a href=\"" . e(base_url('profile')) . "\">account profile</a>.</p>
            <p>Best regards,<br>The Dispatch Team</p>
        ";

        $sent = $this->emailservice->send($email, $subject, $body);

        if ($sent) {
            echo json_encode(['success' => true, 'message' => "Test email sent to {$email}."]);
        } else {
            http_response_code(502);
            echo json_encode(['success' => false, 'error' => 'Failed to send test email. Please verify SMTP settings.']);
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
