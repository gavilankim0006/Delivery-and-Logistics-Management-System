<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$adminDashboardCssPath = dirname(__DIR__, 3) . '/public/css/admin-dashboard.css';
$extraCss = '<link rel="stylesheet" href="' . e(base_url('css/admin-dashboard.css')) . (is_file($adminDashboardCssPath) ? '?v=' . (string)filemtime($adminDashboardCssPath) : '') . '">';
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Admin Dashboard', 'extra_css' => $extraCss]);

$dashboardProps = [
    'metrics' => [
        'ordersToday' => (int)($orders_today ?? 0),
        'monthRevenue' => (float)($month_revenue ?? 0),
        'failedPayments' => (int)($failed_payments ?? 0),
        'pendingApplications' => (int)($pending_applications ?? 0),
    ],
    'recentOrders' => array_map(static fn($order) => [
        'id' => (int)($order['id'] ?? 0),
        'order_no' => (string)($order['order_no'] ?? ''),
        'customer_name' => (string)($order['customer_name'] ?? ''),
        'status' => (string)($order['status'] ?? ''),
        'rider_name' => (string)($order['rider_name'] ?? ''),
        'courier_driver_name' => (string)($order['courier_driver_name'] ?? ''),
        'created_at' => (string)($order['created_at'] ?? ''),
    ], $recent_orders ?? []),
    'recentLogs' => array_map(static fn($log) => [
        'id' => (int)($log['id'] ?? 0),
        'service' => (string)($log['service'] ?? ''),
        'method' => (string)($log['method'] ?? ''),
        'endpoint' => (string)($log['endpoint'] ?? ''),
        'response_status' => (string)($log['response_status'] ?? ''),
        'created_at' => (string)($log['created_at'] ?? ''),
    ], $recent_logs ?? []),
    'links' => [
        'dispatch' => base_url('dispatch'),
        'orders' => base_url('orders'),
        'orderPrefix' => base_url('orders/'),
        'logs' => base_url('admin/logs'),
        'users' => base_url('admin/users'),
        'riders' => base_url('admin/riders'),
        'applications' => base_url('admin/applications'),
        'analytics' => base_url('admin/analytics'),
        'reports' => base_url('admin/reports'),
        'failedPayments' => base_url('admin/reports?type=payments&status=failed'),
    ],
    'dataUrl' => base_url('admin/data'),
];
?>
<div
    id="vue-admin-root"
    data-page="dashboard"
    data-props="<?= e(json_encode($dashboardProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>"
>
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to view the admin dashboard.</div></div></noscript>
</div>
<?php $adminBundlePath = dirname(__DIR__, 3) . '/public/build/admin.js'; $adminBundleVersion = is_file($adminBundlePath) ? '?v=' . (string)filemtime($adminBundlePath) : ''; ?>
<script type="module" src="<?= e(base_url('build/admin.js' . $adminBundleVersion)) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
