<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Admin Dashboard']);
?>

<div class="container page-shell py-4">
    <header class="page-header mb-4">
        <div>
            <h1 class="page-title">Admin dashboard</h1>
            <p class="page-description">Today’s order volume, month-to-date payments, and service activity.</p>
        </div>
        <a href="<?= base_url('dispatch') ?>" class="btn btn-secondary">View dispatch</a>
    </header>

    <section class="dashboard-metrics mb-4" aria-label="Key operating metrics">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="ds-metric h-100">
                    <span class="ds-metric__label">Orders today</span>
                    <p class="ds-metric__value mb-0"><?= number_format((int)$orders_today) ?></p>
                    <span class="ds-metric__hint">Created since midnight</span>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="ds-metric h-100">
                    <span class="ds-metric__label">Paid revenue this month</span>
                    <p class="ds-metric__value money mb-0">₱<?= number_format((float)$month_revenue, 2) ?></p>
                    <span class="ds-metric__hint">Payments received month to date</span>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="ds-metric h-100">
                    <span class="ds-metric__label">Failed payments</span>
                    <p class="ds-metric__value mb-0"><?= number_format((int)$failed_payments) ?></p>
                    <span class="ds-metric__hint">This month</span>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <section class="col-12 col-lg-7" aria-labelledby="recent-orders-heading">
            <div class="surface h-100">
                <header class="dashboard-section-header">
                    <h2 class="h5 mb-0" id="recent-orders-heading">Recent orders</h2>
                    <a href="<?= base_url('dispatch') ?>">View dispatch</a>
                </header>
                <?php if (empty($recent_orders)): ?>
                    <?php $this->call->view('partials/empty_state', ['title' => 'No recent orders', 'message' => 'New delivery activity will appear here.']); ?>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-clean table-responsive-stack align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Order</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Fee</th>
                                <th scope="col">Status</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $ord): ?>
                                <tr>
                                    <td data-label="Order" class="fw-medium"><?= e($ord['order_no']) ?></td>
                                    <td data-label="Customer"><?= e($ord['customer_name']) ?></td>
                                    <td data-label="Fee" class="text-nowrap money">₱<?= number_format((float)$ord['delivery_fee'], 2) ?></td>
                                    <td data-label="Status"><?php $this->call->view('partials/status_chip', ['status' => $ord['status']]); ?></td>
                                    <td data-label="Action"><a href="<?= base_url('orders/' . $ord['id']) ?>">View order</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="col-12 col-lg-5" aria-labelledby="recent-api-heading">
            <div class="surface h-100">
                <header class="dashboard-section-header">
                    <h2 class="h5 mb-0" id="recent-api-heading">Latest API activity</h2>
                    <a href="<?= base_url('admin/logs') ?>">View all logs</a>
                </header>
                <?php if (empty($recent_logs)): ?>
                    <?php $this->call->view('partials/empty_state', ['title' => 'No API activity yet', 'message' => 'Recent service activity will appear here.']); ?>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recent_logs as $log): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                                <div class="min-w-0">
                                    <div class="fw-medium"><span class="text-muted"><?= e($log['service']) ?></span> · <?= e($log['method']) ?></div>
                                    <div class="text-muted text-break" title="<?= e($log['endpoint']) ?>"><?= e($log['endpoint']) ?></div>
                                </div>
                                <div class="text-end text-nowrap">
                                    <span class="availability-chip <?= ($log['response_status'] >= 200 && $log['response_status'] < 300) ? 'account-status-chip--active' : 'availability-chip--error' ?>"><?= e($log['response_status'] ?: 'Error') ?></span>
                                    <div class="small text-muted"><?= number_format((float)$log['execution_time_ms']) ?> ms</div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php $this->call->view('templates/footer'); ?>
