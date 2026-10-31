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
    <div class="container page-shell py-4">
        <header class="page-header mb-4"><h1 class="page-title">Admin dashboard</h1></header>
        <div class="dashboard-fallback">
            <span class="visually-hidden">Orders today: <?= number_format((int)$orders_today) ?></span>
            <span class="visually-hidden">Paid revenue this month: ₱<?= number_format((float)$month_revenue, 2) ?></span>
            <span class="visually-hidden">Failed payments: <?= number_format((int)$failed_payments) ?></span>
        </div>
        <p class="text-muted" role="status">Loading dashboard…</p>
    </div>
</div>
<script type="module" src="<?= e(base_url('build/admin.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
