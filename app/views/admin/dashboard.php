<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Admin Dashboard']);
$dashboardProps = [
    'metrics' => [
        'ordersToday' => (int)$orders_today,
        'monthRevenue' => (float)$month_revenue,
        'failedPayments' => (int)$failed_payments,
    ],
    'recentOrders' => array_map(static fn($order) => [
        'id' => (int)$order['id'],
        'order_no' => (string)($order['order_no'] ?? ''),
        'customer_name' => (string)($order['customer_name'] ?? ''),
        'status' => (string)($order['status'] ?? ''),
        'rider_name' => (string)($order['rider_name'] ?? ''),
    ], $recent_orders ?? []),
    'recentLogs' => array_map(static fn($log) => [
        'id' => (int)($log['id'] ?? 0),
        'endpoint' => (string)($log['endpoint'] ?? ''),
        'method' => (string)($log['method'] ?? ''),
        'response_status' => (string)($log['response_status'] ?? ''),
        'created_at' => (string)($log['created_at'] ?? ''),
    ], $recent_logs ?? []),
    'links' => [
        'dispatch' => base_url('dispatch'),
        'orders' => base_url('orders'),
        'orderPrefix' => base_url('orders/'),
        'logs' => base_url('admin/logs'),
    ],
];
?>
<div
    id="vue-admin-root"
    data-page="dashboard"
    data-props="<?= e(json_encode($dashboardProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>"
>
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to view the admin dashboard.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('build/admin.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
