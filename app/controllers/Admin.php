<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Admin extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->helper('auth');
        // Enforce strict role check: admin only (deny by default)
        require_role('admin');

        $this->call->model('User_model');
        $this->call->model('Rider_model');
    }

    /**
     * Admin Dashboard Overview
     */
    public function index()
    {
        $db = $this->db;

        // Fetch basic counts
        $totalOrders = (int)$db->table('orders')->select_count('*', 'c')->get()['c'];
        $totalUsers = (int)$db->table('users')->select_count('*', 'c')->get()['c'];
        $totalRiders = (int)$db->table('riders')->select_count('*', 'c')->get()['c'];

        $todayRevenue = $db->raw("
            SELECT SUM(amount) AS rev FROM payments WHERE status = 'paid' AND DATE(created_at) = CURRENT_DATE()
        ")->fetch(PDO::FETCH_ASSOC);
        $revenueVal = (float)($todayRevenue['rev'] ?? 0);

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
            'total_orders' => $totalOrders,
            'total_users' => $totalUsers,
            'total_riders' => $totalRiders,
            'today_revenue' => $revenueVal,
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
            $roleId = (int)($_POST['role_id'] ?? 3);
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
                    $this->User_model->update_user((int)$id, ['status' => $newStatus]);
                    flash('success', "User {$user['name']} status changed to {$newStatus}.");
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
            'status_filter' => $statusFilter
        ]);
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

            $this->Rider_model->update_rider((int)$id, [
                'name' => $name,
                'phone' => $phone,
                'vehicle_type' => $vehicleType,
                'plate_number' => $plateNumber,
                'status' => $status
            ]);

            flash('success', "Rider details updated.");
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

            $this->Rider_model->delete_rider((int)$id);
            flash('success', 'Rider record removed.');
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

        $this->call->view('admin/settings', [
            'page_title' => 'Fee Rates & Configuration',
            'settings' => $settings
        ]);
    }

    /**
     * Reports Screen placeholder (T8)
     */
    public function reports()
    {
        $this->call->view('admin/reports', [
            'page_title' => 'Analytics & Reports'
        ]);
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
