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
                flash('error', "Too many failed login attempts. Please wait {$retryAfter} seconds before trying again.");
                header('Location: ' . base_url('login'));
                exit;
            }

            if (empty($email) || empty($password)) {
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
     * Show customer registration form and handle signup
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

            // Server-side validations
            if (empty($name) || strlen($name) < 2) {
                flash('error', 'Please enter your full name.');
                header('Location: ' . base_url('register'));
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Please enter a valid email address.');
                header('Location: ' . base_url('register'));
                exit;
            }

            if (empty($phone)) {
                flash('error', 'Please enter a valid contact phone number.');
                header('Location: ' . base_url('register'));
                exit;
            }

            if (strlen($password) < 8) {
                flash('error', 'Password must be at least 8 characters long.');
                header('Location: ' . base_url('register'));
                exit;
            }

            if ($password !== $passwordConfirm) {
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

            if (empty($name)) {
                flash('error', 'Name cannot be empty.');
                header('Location: ' . base_url('profile'));
                exit;
            }

            $updateData = [
                'name' => $name,
                'phone' => $phone
            ];
            if (auth_role() === 'customer') {
                $updateData['sms_opt_in'] = !empty($_POST['sms_opt_in']) ? 1 : 0;
                $updateData['delivery_status_sms_opt_in'] = !empty($_POST['delivery_status_sms_opt_in']) ? 1 : 0;
                $updateData['push_opt_in'] = !empty($_POST['push_opt_in']) ? 1 : 0;
            }

            if (!empty($password)) {
                if (strlen($password) < 8) {
                    flash('error', 'New password must be at least 8 characters.');
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
                || $password !== '';
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

        $this->User_model->save_fcm_token(auth_id(), $token);
        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * AJAX endpoint to test client push notification
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

        $user = $this->User_model->find_by_id(auth_id());
        $token = trim($user['fcm_token'] ?? '');
        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['error' => 'No push device token found for your account. Please enable browser notifications first.']);
            exit;
        }

        try {
            $this->call->library('FirebasePushService');
            $firebase = new FirebasePushService();
            $result = $firebase->sendToToken($token, 'Dispatch Notification Test', 'Live push notification verified successfully!');
            if (!empty($result['sent'])) {
                echo json_encode(['success' => true, 'message' => 'Push notification delivered to your browser via FCM!']);
            } elseif (!empty($result['disabled'])) {
                echo json_encode(['success' => true, 'message' => 'Firebase Push is in local test mode (simulated notification).']);
            } else {
                echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Could not deliver push notification.']);
            }
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * AJAX endpoint to test customer SMS notification
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
        $phone = trim($user['phone'] ?? '');
        if (empty($phone)) {
            http_response_code(400);
            echo json_encode(['error' => 'Please enter and save a mobile phone number in your profile first.']);
            exit;
        }

        try {
            $this->call->library('SemaphoreService');
            $sms = new SemaphoreService();
            $res = $sms->send($phone, 'Dispatch SMS Test: Your phone number is verified and ready for delivery updates.');
            if (!empty($res['sent'])) {
                echo json_encode(['success' => true, 'message' => "Test SMS sent to {$phone}!"]);
            } elseif (!empty($res['disabled'])) {
                echo json_encode(['success' => true, 'message' => "Semaphore is disabled in configuration. SMS was simulated for {$phone}."]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to send test SMS.']);
            }
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Helper to redirect authenticated user to their role landing page
     */
    protected function redirect_by_role(?string $role): void
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
            case 'customer':
            default:
                header('Location: ' . base_url('orders'));
                break;
        }
        exit;
    }
}
