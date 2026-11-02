<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Admin extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        $this->call->helper('fee');
        // Enforce strict role check: admin only (deny by default)
        require_role('admin');

        $this->call->model('User_model');
        $this->call->model('Rider_model');
        $this->call->model('Order_model');
        $this->call->model('Service_area_model');
        $this->call->model('Report_model');
        $this->call->model('Job_application_model');
        $this->call->model('Package_workflow_model');
        $this->call->library('JobApplicationDocumentService');

        $this->call->library('CourierProviderFactory');
        $this->call->library('MockCourierDriver');
        $this->call->library('DeliveryCommandSimulator');
    }

    public function package_reports(): void
    {
        $this->call->view('admin/package_reports', ['page_title'=>'Missing-package reports','reports'=>$this->Package_workflow_model->list_reports()]);
    }

    public function review_package_report(int $id): void
    {
        $status=(string)($_POST['status']??''); $note=trim((string)($_POST['admin_note']??''));
        if ($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf()||!in_array($status,['open','investigating','resolved','rejected'],true)||mb_strlen($note)>3000||!$this->Package_workflow_model->update_report($id,$status,$note,(int)(auth_user()['id']??0))) flash('error','Invalid report update.');
        else flash('success','Missing-package report updated.');
        redirect('admin/package-reports');
    }

    public function applications(): void
    {
        $statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? (string)$_GET['status'] : null;
        $this->call->view('admin/job_applications', [
            'page_title' => 'Job applications',
            'applications' => $this->Job_application_model->list_all($statusFilter),
            'status_filter' => $statusFilter,
        ]);
    }

    public function review_application(int $id): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('admin/applications');
            return;
        }
        $status = (string)($_POST['status'] ?? '');
        $note = trim((string)($_POST['admin_note'] ?? ''));
        if (!in_array($status, ['pending', 'approved', 'rejected'], true) || mb_strlen($note) > 3000
            || !$this->Job_application_model->find($id)) {
            flash('error', 'Application review could not be saved. Check decision and note.');
            redirect('admin/applications');
            return;
        }
        $this->Job_application_model->update_status($id, $status, (int)auth_id(), $note);
        flash('success', 'Application review saved.');
        redirect('admin/applications');
    }

    public function application_id_document(int $id): void
    {
        $application = $this->Job_application_model->find($id);
        if (!$application) {
            flash('error', 'Job application not found.');
            redirect('admin/applications');
            return;
        }

        // 1. Try reading existing file from disk cache
        $path = $this->JobApplicationDocumentService->readPath((string)$application['id_storage_key']);

        // 2. If missing on disk (e.g. Render container restart), restore from database backup
        if ((!$path || !is_file($path)) && !empty($application['id_file_data'])) {
            $path = $this->JobApplicationDocumentService->restoreFromData(
                (string)$application['id_storage_key'],
                $application['id_file_data']
            );
        }

        // 3. If file is completely unavailable (e.g. uploaded prior to fix and cleared by restart)
        if (!$path || !is_file($path)) {
            flash('error', 'The ID document for ' . ($application['full_name'] ?? 'this applicant') . ' is not available in server storage (it was removed during a server restart). Please ask the applicant to re-upload their ID.');
            redirect('admin/applications');
            return;
        }

        if (!in_array($application['id_mime_type'], ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
            flash('error', 'Unsupported document format.');
            redirect('admin/applications');
            return;
        }

        header('Content-Type: ' . $application['id_mime_type']);
        header('Content-Length: ' . (string)filesize($path));
        header('Content-Disposition: inline; filename="' . rawurlencode((string)$application['id_original_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($path);
        exit;
    }

    /**
     * Admin Dashboard Overview
     */
    public function index()
    {
        $db = $this->db;

        $ordersToday = (int)$db->raw("
            SELECT COUNT(*) AS c
            FROM orders
            WHERE created_at >= CURRENT_DATE()
              AND created_at < DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)
        ")->fetch(PDO::FETCH_ASSOC)['c'];

        $monthRevenue = $db->raw("
            SELECT COALESCE(SUM(amount), 0) AS revenue
            FROM payments
            WHERE status = 'paid'
              AND paid_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
              AND paid_at < DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)
        ")->fetch(PDO::FETCH_ASSOC);
        $monthRevenueValue = (float)($monthRevenue['revenue'] ?? 0);

        $failedPayments = (int)$db->raw("
            SELECT COUNT(*) AS c
            FROM payments
            WHERE status = 'failed'
              AND created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
              AND created_at < DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)
        ")->fetch(PDO::FETCH_ASSOC)['c'];

        // Recent orders
        $recentOrders = $db->raw("
            SELECT o.*, u.name AS customer_name, r.name AS rider_name
            FROM orders o
            INNER JOIN customers c ON o.customer_id = c.id
            INNER JOIN users u ON c.user_id = u.id
            LEFT JOIN riders r ON o.rider_id = r.id
            ORDER BY o.id DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Latest API logs
        $recentLogs = $db->table('api_logs')
            ->order_by('id', 'DESC')
            ->limit(5)
            ->get_all();

        $this->call->view('admin/dashboard', [
            'page_title' => 'Admin Dashboard',
            'orders_today' => $ordersToday,
            'month_revenue' => $monthRevenueValue,
            'failed_payments' => $failedPayments,
            'recent_orders' => $recentOrders,
            'recent_logs' => $recentLogs
        ]);
    }

    /**
     * User Management Screen
     */
    public function users()
    {
        $search = trim($_GET['search'] ?? '');
        $roleFilter = isset($_GET['role']) && $_GET['role'] !== '' ? (int)$_GET['role'] : null;

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $users = $this->User_model->get_users($limit, $offset, $search, $roleFilter);
        $totalUsers = $this->User_model->count_users($search, $roleFilter);
        $roles = $this->User_model->get_roles();

        $this->call->view('admin/users', [
            'page_title' => 'User Management',
            'users' => $users,
            'roles' => $roles,
            'total' => $totalUsers,
            'search' => $search,
            'role_filter' => $roleFilter,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * Create new staff user (Admin or Dispatcher)
     */
    public function create_user()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('error', 'Invalid CSRF token.');
                header('Location: ' . base_url('admin/users'));
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $roleId = (int)($_POST['role_id'] ?? 0);
            $password = $_POST['password'] ?? '';

            if (empty($name) || empty($email) || empty($password)) {
                flash('error', 'Name, email, and password are required.');
                header('Location: ' . base_url('admin/users'));
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Invalid email address format.');
                header('Location: ' . base_url('admin/users'));
                exit;
            }
            if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || strlen($password) < 8 || !in_array($roleId, [1, 2, 4], true)) {
                flash('error', 'Check the name, role, and password requirements.');
                header('Location: ' . base_url('admin/users'));
                exit;
            }

            if ($this->User_model->email_in_use($email)) {
                flash('error', 'Email address is already in use.');
                header('Location: ' . base_url('admin/users'));
                exit;
            }

            $res = $this->User_model->create_staff([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'role_id' => $roleId,
                'password' => $password,
                'status' => 'active'
            ]);

            if ($res['success']) {
                flash('success', "User {$name} created successfully.");
            } else {
                flash('error', $res['error']);
            }
        }

        header('Location: ' . base_url('admin/users'));
        exit;
    }

    /**
     * Show one active user account.
     */
    public function show_user(int $id): void
    {
        $user = $this->User_model->find_by_id($id);
        if (!$user) {
            http_response_code(404);
            $this->call->view('errors/error_404');
            return;
        }
        $roles = $this->User_model->get_roles();
        $this->call->view('admin/user_show', [
            'page_title' => 'User details',
            'user' => $user,
            'roles' => $roles
        ]);
    }

    /**
     * Update user account details.
     */
    public function update_user(int $id): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            return;
        }
        if (!verify_csrf()) {
            flash('error', 'Invalid CSRF token.');
            header('Location: ' . base_url('admin/users'));
            exit;
        }

        $user = $this->User_model->find_by_id($id);
        if (!$user) {
            flash('error', 'User not found.');
            header('Location: ' . base_url('admin/users'));
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $password = $_POST['password'] ?? '';

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($phone) > 30 || !in_array($roleId, [1, 2, 3, 4], true)
            || !in_array($status, ['active', 'inactive'], true)
            || ($password !== '' && strlen($password) < 8)) {
            flash('error', 'Please correct the user details and try again.');
            header('Location: ' . base_url('admin/users/' . $id));
            exit;
        }
        if ($this->User_model->email_in_use($email, $id)) {
            flash('error', 'That email address is already in use.');
            header('Location: ' . base_url('admin/users/' . $id));
            exit;
        }
        if ($id === auth_id() && ($status !== 'active' || $roleId !== 1)) {
            flash('error', 'You cannot deactivate or remove administrator access from your own account.');
            header('Location: ' . base_url('admin/users/' . $id));
            exit;
        }

        $changes = ['name' => $name, 'email' => $email, 'phone' => $phone, 'role_id' => $roleId, 'status' => $status];
        if ($password !== '') {
            $changes['password'] = $password;
        }
        if ($this->User_model->update_user($id, $changes)) {
            flash('success', 'User details updated.');
        } else {
            flash('error', 'Account update was not completed. Active deliveries must be resolved, and rider accounts must be provisioned through Rider management.');
        }
        header('Location: ' . base_url('admin/users/' . $id));
        exit;
    }

    /**
     * Soft-delete a user account without removing its historical references.
     */
    public function delete_user(int $id): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            return;
        }
        if (!verify_csrf()) {
            flash('error', 'Invalid CSRF token.');
            header('Location: ' . base_url('admin/users'));
            exit;
        }
        if ($id === auth_id()) {
            flash('error', 'You cannot delete your own administrative account.');
            header('Location: ' . base_url('admin/users'));
            exit;
        }

        if ($this->User_model->soft_delete_user($id)) {
            flash('success', 'User account removed.');
        } else {
            flash('error', 'User account was not removed. Resolve active rider deliveries before removing this account.');
        }
        header('Location: ' . base_url('admin/users'));
        exit;
    }

    /**
     * Toggle user status (active / inactive)
     */
    public function toggle_user_status($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('error', 'Invalid CSRF token.');
                header('Location: ' . base_url('admin/users'));
                exit;
            }

            $user = $this->User_model->find_by_id((int)$id);
            if ($user) {
                // Prevent deactivating own account
                if ($user['id'] == auth_id()) {
                    flash('error', 'You cannot deactivate your own administrative account.');
                } else {
                    $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
                    if ($this->User_model->update_user((int)$id, ['status' => $newStatus])) {
                        flash('success', "User {$user['name']} status changed to {$newStatus}.");
                    } else {
                        flash('error', 'User status was not changed. Resolve active rider deliveries before deactivation.');
                    }
                }
            }
        }
        header('Location: ' . base_url('admin/users'));
        exit;
    }

    /**
     * Rider Management Screen
     */
    public function riders()
    {
        $statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
        $riders = $this->Rider_model->get_all($statusFilter);

        $this->call->view('admin/riders', [
            'page_title' => 'Rider Fleet Management',
            'riders' => $riders,
            'unlinked_rider_users' => $this->Rider_model->get_unlinked_rider_users(),
            'status_filter' => $statusFilter
        ]);
    }

    public function link_rider_account(int $id): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            return;
        }
        if (!verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('admin/riders');
            return;
        }
        try {
            $userId = (int)($_POST['user_id'] ?? 0);
            $this->Rider_model->link_account($id, $userId);
            flash('success', 'Rider portal account linked.');
        } catch (Throwable $e) {
            flash('error', 'Rider account was not linked: ' . $e->getMessage());
        }
        redirect('admin/riders');
    }

    public function unlink_rider_account(int $id): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            return;
        }
        if (!verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('admin/riders');
            return;
        }
        try {
            $this->Rider_model->unlink_account($id);
            flash('success', 'Rider portal account unlinked.');
        } catch (Throwable $e) {
            flash('error', 'Rider account was not unlinked: ' . $e->getMessage());
        }
        redirect('admin/riders');
    }

    /**
     * Create new rider
     */
    public function create_rider()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('error', 'Invalid security token.');
                header('Location: ' . base_url('admin/riders'));
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $vehicleType = trim($_POST['vehicle_type'] ?? 'Motorcycle');
            $plateNumber = strtoupper(trim($_POST['plate_number'] ?? ''));
            $status = $_POST['status'] ?? 'available';

            if (empty($name) || empty($phone) || empty($plateNumber)) {
                flash('error', 'Name, phone, and vehicle plate number are required.');
                header('Location: ' . base_url('admin/riders'));
                exit;
            }

            $res = $this->Rider_model->create([
                'name' => $name,
                'phone' => $phone,
                'vehicle_type' => $vehicleType,
                'plate_number' => $plateNumber,
                'status' => $status
            ]);

            if ($res['success']) {
                flash('success', "Rider {$name} ({$plateNumber}) registered successfully.");
            } else {
                flash('error', $res['error']);
            }
        }
        header('Location: ' . base_url('admin/riders'));
        exit;
    }

    /**
     * Update rider details
     */
    public function update_rider($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('error', 'Invalid security token.');
                header('Location: ' . base_url('admin/riders'));
                exit;
            }

            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $vehicleType = trim($_POST['vehicle_type'] ?? 'Motorcycle');
            $plateNumber = strtoupper(trim($_POST['plate_number'] ?? ''));
            $status = $_POST['status'] ?? 'available';

            if ($this->Rider_model->update_rider((int)$id, [
                'name' => $name,
                'phone' => $phone,
                'vehicle_type' => $vehicleType,
                'plate_number' => $plateNumber,
                'status' => $status
            ])) {
                flash('success', 'Rider details updated.');
            } else {
                flash('error', 'Rider details were invalid or the rider was not found.');
            }
        }
        header('Location: ' . base_url('admin/riders'));
        exit;
    }

    /**
     * Delete rider
     */
    public function delete_rider($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('error', 'Invalid security token.');
                header('Location: ' . base_url('admin/riders'));
                exit;
            }

            if ($this->Rider_model->delete_rider((int)$id)) {
                flash('success', 'Rider record removed.');
            } else {
                flash('error', 'Rider not found or already removed.');
            }
        }
        header('Location: ' . base_url('admin/riders'));
        exit;
    }

    /**
     * Settings Screen placeholder (further extended in T3)
     */
    public function settings()
    {
        $db = $this->db;
        $settingsRaw = $db->table('settings')->get_all();
        $settings = [];
        foreach ($settingsRaw as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                flash('error', 'Invalid security token.');
                header('Location: ' . base_url('admin/settings'));
                exit;
            }

            foreach ($_POST as $k => $v) {
                if (in_array($k, ['base_delivery_fee', 'per_km_rate', 'per_kg_rate', 'free_distance_km', 'free_weight_kg', 'support_email', 'support_phone'])) {
                    $db->table('settings')->where('setting_key', $k)->update([
                        'setting_value' => trim($v),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            flash('success', 'Fee settings updated successfully.');
            header('Location: ' . base_url('admin/settings'));
            exit;
        }

        $outbox = $db->raw("
            SELECT o.id, o.order_id, o.user_id, o.status_code, o.channel, o.recipient, o.message, o.status, o.sent_at, o.created_at, ord.order_no
            FROM delivery_notification_outbox o
            LEFT JOIN orders ord ON ord.id = o.order_id
            ORDER BY o.id DESC
            LIMIT 30
        ")->fetchAll(PDO::FETCH_ASSOC);

        $outboxStatsRaw = $db->raw("
            SELECT status, COUNT(*) as cnt
            FROM delivery_notification_outbox
            GROUP BY status
        ")->fetchAll(PDO::FETCH_ASSOC);
        $outboxStats = [];
        foreach ($outboxStatsRaw as $row) {
            $outboxStats[$row['status']] = (int)$row['cnt'];
        }

        $usersWithTokens = $db->raw("
            SELECT id, name, email, role_id, fcm_token
            FROM users
            WHERE fcm_token IS NOT NULL AND fcm_token <> '' AND deleted_at IS NULL
            ORDER BY id ASC
            LIMIT 50
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->call->view('admin/settings', [
            'page_title' => 'Fee Rates & Configuration',
            'settings' => $settings,
            'outbox' => $outbox,
            'outbox_stats' => $outboxStats,
            'users_with_tokens' => $usersWithTokens,
            'semaphore_enabled' => getenv('SEMAPHORE_ENABLED') === '1',
            'semaphore_api_key' => getenv('SEMAPHORE_API_KEY') ?: '',
            'semaphore_sender_name' => getenv('SEMAPHORE_SENDER_NAME') ?: 'SEMAPHORE',
            'firebase_enabled' => getenv('FIREBASE_PUSH_ENABLED') === '1',
            'firebase_project_id' => getenv('FIREBASE_PROJECT_ID') ?: '',
            'firebase_has_key' => !empty(getenv('FIREBASE_PRIVATE_KEY')) || file_exists(dirname(APP_DIR) . DIRECTORY_SEPARATOR . 'delivery-and-logistics-system-firebase-adminsdk-fbsvc-0a9478f6dd.json')
        ]);
    }

    /**
     * Trigger queue dispatch for pending delivery notifications
     */
    public function dispatch_outbox()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('error', 'Invalid security token.');
            redirect('admin/settings');
            return;
        }

        try {
            $this->call->library('DeliveryNotificationService');
            $notificationService = new DeliveryNotificationService($this->db);

            $pendingOrders = $this->db->raw("
                SELECT DISTINCT order_id
                FROM delivery_notification_outbox
                WHERE status = 'pending'
            ")->fetchAll(PDO::FETCH_COLUMN);

            $processedCount = 0;
            foreach ($pendingOrders as $orderId) {
                $notificationService->dispatchPendingForOrder((int)$orderId);
                $processedCount++;
            }

            flash('success', "Notification queue processed: {$processedCount} order(s) evaluated.");
        } catch (Throwable $e) {
            flash('error', 'Notification dispatch failed: ' . $e->getMessage());
        }

        redirect('admin/settings');
    }

    /**
     * Admin tool to send a test SMS via Semaphore
     */
    public function test_sms()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('error', 'Invalid security token.');
            redirect('admin/settings');
            return;
        }

        $phone = trim($_POST['phone'] ?? '');
        $message = trim($_POST['message'] ?? 'Dispatch test message from Admin.');
        if (empty($phone)) {
            flash('error', 'Recipient phone number is required.');
            redirect('admin/settings');
            return;
        }

        try {
            $this->call->library('SemaphoreService');
            $sms = new SemaphoreService();
            $res = $sms->send($phone, $message);
            if (!empty($res['sent'])) {
                flash('success', "Test SMS successfully sent to {$phone} via Semaphore!");
            } elseif (!empty($res['disabled'])) {
                flash('warning', "Semaphore is currently disabled (SEMAPHORE_ENABLED != 1). Message simulated for {$phone}.");
            } else {
                flash('error', 'Semaphore request failed.');
            }
        } catch (Throwable $e) {
            flash('error', 'SMS sending failed: ' . $e->getMessage());
        }

        redirect('admin/settings');
    }

    /**
     * Admin tool to send a test Push notification via Firebase
     */
    public function test_push()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('error', 'Invalid security token.');
            redirect('admin/settings');
            return;
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        $user = $userId ? $this->User_model->find_by_id($userId) : null;
        $token = trim($user['fcm_token'] ?? ($_POST['token'] ?? ''));

        if (empty($token)) {
            flash('error', 'Target user has no registered FCM push token.');
            redirect('admin/settings');
            return;
        }

        try {
            $this->call->library('FirebasePushService');
            $firebase = new FirebasePushService();
            $res = $firebase->sendToToken($token, 'Dispatch Admin Test', 'This is a test notification from the admin control panel.');
            if (!empty($res['sent'])) {
                flash('success', 'Push notification successfully dispatched via FCM!');
            } elseif (!empty($res['disabled'])) {
                flash('warning', 'Firebase Push is disabled (FIREBASE_PUSH_ENABLED != 1).');
            } else {
                flash('error', 'Push dispatch failed: ' . ($res['error'] ?? 'Unknown error'));
            }
        } catch (Throwable $e) {
            flash('error', 'Push failed: ' . $e->getMessage());
        }

        redirect('admin/settings');
    }

    /**
     * Development-only courier simulation console.
     */
    public function courier_simulator()
    {
        try {
            if (config_item('environment') !== 'development' || CourierProviderFactory::driverName() !== 'mock') {
                http_response_code(404);
                return;
            }
        } catch (Throwable $e) {
            http_response_code(404);
            return;
        }

        $orders = $this->db->raw("SELECT o.id, o.order_no, o.status, o.lalamove_order_id, o.courier_driver_name, u.name AS customer_name
            FROM orders o INNER JOIN customers c ON c.id = o.customer_id INNER JOIN users u ON u.id = c.user_id
            WHERE o.status IN ('paid', 'booking', 'rider_assigned', 'picked_up', 'failed') ORDER BY o.id DESC LIMIT 50")
            ->fetchAll(PDO::FETCH_ASSOC);
        $this->call->view('admin/courier_simulator', [
            'page_title' => 'Courier Simulator',
            'orders' => $orders,
            'driver_name' => CourierProviderFactory::driverName()
        ]);
    }

    public function book_courier_order(int $id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('error', 'Invalid request or security token.');
            header('Location: ' . base_url('admin/courier-simulator'));
            exit;
        }
        try {
            if (config_item('environment') !== 'development' || CourierProviderFactory::driverName() !== 'mock') {
                http_response_code(404);
                return;
            }
            $this->call->library('CourierBookingService');
            (new CourierBookingService($this->db, $this->Order_model, new MockCourierDriver()))->book($id, 'admin', auth_id());
            flash('success', 'Mock courier booking completed and rider assignment was processed through the webhook handler.');
        } catch (Throwable $e) {
            flash('error', 'Courier booking failed: ' . $e->getMessage());
        }
        header('Location: ' . base_url('admin/courier-simulator'));
        exit;
    }

    public function simulate_courier_event(int $id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            flash('error', 'Invalid request or security token.');
            header('Location: ' . base_url('admin/courier-simulator'));
            exit;
        }
        try {
            if (config_item('environment') !== 'development' || CourierProviderFactory::driverName() !== 'mock') {
                http_response_code(404);
                return;
            }
        } catch (Throwable $e) {
            http_response_code(404);
            return;
        }

        $eventType = (string)($_POST['event_type'] ?? '');
        $allowed = ['rider_assigned', 'picked_up', 'delivered', 'failed', 'cancelled'];
        $order = $this->db->raw('SELECT * FROM orders WHERE id = ? LIMIT 1', [$id])->fetch(PDO::FETCH_ASSOC);
        $transitions = [
            'booking' => ['rider_assigned', 'failed', 'cancelled'],
            'rider_assigned' => ['picked_up', 'failed', 'cancelled'],
            'picked_up' => ['delivered', 'failed'],
            'paid' => ['failed', 'cancelled']
        ];
        if (!$order || !in_array($eventType, $allowed, true) || !in_array($eventType, $transitions[$order['status']] ?? [], true)) {
            flash('error', 'Choose an existing order and a courier event allowed from its current status.');
            header('Location: ' . base_url('admin/courier-simulator'));
            exit;
        }

        try {
            $driver = new MockCourierDriver();
            $payload = $driver->webhookEvent($eventType, $order, ['reason' => trim((string)($_POST['reason'] ?? ''))]);
            $this->call->controller('Webhooks');
            $result = Webhooks::processPayload($this->db, $this->Order_model, $payload, 'admin', auth_id(), true);
            flash(!empty($result['ignored']) ? 'warning' : 'success', !empty($result['ignored']) ? 'The event was ignored.' : 'Courier event processed: ' . $eventType . '.');
        } catch (Throwable $e) {
            flash('error', 'Courier event could not be processed: ' . $e->getMessage());
        }
        header('Location: ' . base_url('admin/courier-simulator'));
        exit;
    }

    public function service_areas()
    {
        $this->call->view('admin/service_areas', [
            'page_title' => 'Delivery service areas',
            'areas' => $this->Service_area_model->get_all(),
            'maps_js_key' => getenv('GOOGLE_MAPS_MAPS_JS_API_KEY') ?: '',
            'service_area_csp_nonce' => defined('CSP_NONCE') ? CSP_NONCE : ''
        ]);
    }

    public function save_service_area()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('admin/service-areas');
            return;
        }
        try {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 100) {
                throw new InvalidArgumentException('Area name is required and must be 100 characters or fewer.');
            }
            $polygon = DeliveryCommandSimulator::normalizePolygon($_POST['polygon'] ?? '');
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            if ($id && !$this->Service_area_model->find($id)) {
                throw new InvalidArgumentException('Service area was not found.');
            }
            $rates = [];
            foreach (['base_delivery_fee', 'free_distance_km', 'per_km_rate', 'free_weight_kg', 'per_kg_rate'] as $key) {
                $raw = $_POST[$key] ?? '';
                if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > 100000) {
                    throw new InvalidArgumentException('Enter valid non-negative fee values.');
                }
                $rates[$key] = (float)$raw;
            }
            $this->Service_area_model->save($id, $name, $polygon, $rates, !empty($_POST['is_active']));
            flash('success', 'Service area saved.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/service-areas');
    }

    public function delete_service_area(int $id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
        } elseif ($this->Service_area_model->delete($id)) {
            flash('success', 'Service area deleted.');
        } else {
            flash('error', 'Service area was not found.');
        }
        redirect('admin/service-areas');
    }

    public function command_simulator()
    {
        $areas = $this->Service_area_model->get_all(true);
        $selectedId = (int)($_GET['area_id'] ?? ($areas[0]['id'] ?? 0));
        $zoneData = null;
        if ($selectedId > 0) {
            try {
                $zoneData = $this->Service_area_model->get_orders_in_zone($selectedId);
            } catch (Throwable $e) {
                flash('error', 'Unable to read service area: ' . $e->getMessage());
            }
        }
        $rates = get_delivery_rates();
        $availableRiders = (int)$this->db->raw("SELECT COUNT(*) AS c FROM riders WHERE status = 'available'")->fetch(PDO::FETCH_ASSOC)['c'];
        $areaRates = $zoneData ? array_intersect_key($zoneData['zone'], array_flip(['base_delivery_fee', 'free_distance_km', 'per_km_rate', 'free_weight_kg', 'per_kg_rate'])) : $rates;
        $this->call->view('admin/command_simulator', [
            'page_title' => 'Delivery command simulator', 'areas' => $areas, 'selected_area_id' => $selectedId,
            'zone_data' => $zoneData, 'baseline_rates' => $rates, 'area_rates' => $areaRates, 'available_riders' => $availableRiders,
            'result' => null, 'simulation_error' => null, 'form_data' => []
        ]);
    }

    public function run_command_simulation()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
            http_response_code(400);
            flash('error', 'Invalid request or security token.');
            redirect('admin/command-simulator');
            return;
        }
        $areas = $this->Service_area_model->get_all(true);
        $selectedId = (int)($_POST['area_id'] ?? 0);
        $zoneData = null;
        $result = null;
        $simulationError = null;
        try {
            if ($selectedId < 1 || !in_array($selectedId, array_map('intval', array_column($areas, 'id')), true)) {
                throw new InvalidArgumentException('Choose an active service area.');
            }
            $zoneData = $this->Service_area_model->get_orders_in_zone($selectedId);
            $simulationInput = array_merge($zoneData['zone'], $_POST);
            $result = DeliveryCommandSimulator::simulate($simulationInput, get_delivery_rates(), (int)$this->db->raw("SELECT COUNT(*) AS c FROM riders WHERE status = 'available'")->fetch(PDO::FETCH_ASSOC)['c']);
            $result['zone_orders'] = count($zoneData['orders']);
            $result['missing_coordinates'] = $zoneData['missing_coordinates'];
        } catch (Throwable $e) {
            $simulationError = $e->getMessage();
        }
        $this->call->view('admin/command_simulator', [
            'page_title' => 'Delivery command simulator', 'areas' => $areas, 'selected_area_id' => $selectedId,
            'zone_data' => $zoneData, 'baseline_rates' => get_delivery_rates(), 'area_rates' => $zoneData ? array_intersect_key($zoneData['zone'], array_flip(['base_delivery_fee', 'free_distance_km', 'per_km_rate', 'free_weight_kg', 'per_kg_rate'])) : get_delivery_rates(),
            'available_riders' => (int)$this->db->raw("SELECT COUNT(*) AS c FROM riders WHERE status = 'available'")->fetch(PDO::FETCH_ASSOC)['c'],
            'result' => $result, 'simulation_error' => $simulationError, 'form_data' => $_POST
        ]);
    }

    /**
     * Read-only delivery and payment reports for administrators.
     */
    public function reports()
    {
        $type = isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : 'deliveries';
        $normalized = Report_model::normalize_filters($type, $_GET);
        $filters = $normalized['filters'];
        $report = ['summary' => [], 'rows' => []];
        $riders = [];
        $reportError = null;
        if (!$normalized['errors']) {
            try {
                $report = $filters['type'] === 'payments'
                    ? $this->Report_model->get_payment_report($filters)
                    : $this->Report_model->get_delivery_report($filters);
                if ($filters['type'] === 'deliveries') {
                    $riders = $this->Rider_model->get_all();
                }
            } catch (Throwable $e) {
                error_log('Admin report query failed: ' . $e->getMessage());
                $reportError = 'Reports could not be loaded. Check that the database migrations are up to date, then try again.';
            }
        }

        if (($_GET['export'] ?? '') === 'csv' && $reportError === null && !$normalized['errors']) {
            $this->export_report_csv($filters, $report);
            return;
        }

        $this->call->view('admin/reports', [
            'page_title' => 'Analytics & Reports',
            'filters' => $filters,
            'errors' => $normalized['errors'],
            'report' => $report,
            'report_error' => $reportError,
            'riders' => $riders
        ]);
    }

    private function export_report_csv(array $filters, array $report): void
    {
        $filename = 'delivery-' . $filters['type'] . '-report-' . $filters['from'] . '-to-' . $filters['to'] . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            http_response_code(500);
            return;
        }
        fwrite($output, "\xEF\xBB\xBF");
        if ($filters['type'] === 'payments') {
            fputcsv($output, ['Order number', 'Status', 'Amount', 'Fee', 'Method', 'Paid at', 'Created at']);
            foreach ($report['rows'] as $row) {
                fputcsv($output, [
                    $this->csv_safe_cell($row['order_no'] ?? ''),
                    $this->csv_safe_cell($row['status'] ?? ''),
                    (string)($row['amount'] ?? '0.00'),
                    (string)($row['fee'] ?? '0.00'),
                    $this->csv_safe_cell($row['payment_method'] ?? ''),
                    $this->csv_safe_cell($row['paid_at'] ?? ''),
                    $this->csv_safe_cell($row['created_at'] ?? '')
                ]);
            }
        } else {
            fputcsv($output, ['Order number', 'Customer', 'Rider', 'Status', 'Distance (km)', 'Delivery fee', 'Paid amount', 'Created at']);
            foreach ($report['rows'] as $row) {
                fputcsv($output, [
                    $this->csv_safe_cell($row['order_no'] ?? ''),
                    $this->csv_safe_cell($row['customer_name'] ?? ''),
                    $this->csv_safe_cell($row['rider_name'] ?? ''),
                    $this->csv_safe_cell($row['status'] ?? ''),
                    (string)($row['distance_km'] ?? '0.00'),
                    (string)($row['delivery_fee'] ?? '0.00'),
                    (string)($row['paid_amount'] ?? '0.00'),
                    $this->csv_safe_cell($row['created_at'] ?? '')
                ]);
            }
        }
        fclose($output);
    }

    private function csv_safe_cell($value): string
    {
        return Report_model::safe_csv_cell($value);
    }

    /**
     * API Logs Screen (T8)
     */
    public function logs()
    {
        $db = $this->db;
        $logs = $db->table('api_logs')->order_by('id', 'DESC')->limit(100)->get_all();

        $this->call->view('admin/logs', [
            'page_title' => 'API Audit Logs',
            'logs' => $logs
        ]);
    }
}
