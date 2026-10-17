<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Admin Dashboard']);
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Administrative Dashboard</h1>
            <p class="text-muted small mb-0">System performance, dispatch overview, and financial health</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('dispatch') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-kanban me-1"></i> Live Dispatch
            </a>
            <a href="<?= base_url('admin/settings') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-gear me-1"></i> Fee Rates
            </a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Total Orders</span>
                        <h3 class="fw-bold my-1"><?= number_format($total_orders) ?></h3>
                        <span class="text-success small"><i class="bi bi-arrow-up-right"></i> All-time</span>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                        <i class="bi bi-box-seam fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Today's Revenue</span>
                        <h3 class="fw-bold my-1 text-primary">₱<?= number_format($today_revenue, 2) ?></h3>
                        <span class="text-muted small">Paid online via PayMongo</span>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Registered Users</span>
                        <h3 class="fw-bold my-1"><?= number_format($total_users) ?></h3>
                        <span class="text-muted small">Admins, dispatchers, customers</span>
                    </div>
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Active Fleet</span>
                        <h3 class="fw-bold my-1"><?= number_format($total_riders) ?></h3>
                        <span class="text-muted small">Registered delivery riders</span>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                        <i class="bi bi-bicycle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row g-4">
        <!-- Recent Orders -->
        <div class="col-lg-7">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Deliveries</h6>
                    <a href="<?= base_url('dispatch') ?>" class="small text-decoration-none">View All &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Fee</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_orders)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No orders found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_orders as $ord): ?>
                                    <tr>
                                        <td class="fw-semibold text-primary"><?= e($ord['order_no']) ?></td>
                                        <td><?= e($ord['customer_name']) ?></td>
                                        <td>₱<?= number_format($ord['delivery_fee'], 2) ?></td>
                                        <td>
                                            <span class="badge-status badge-<?= e($ord['status']) ?>">
                                                <?= str_replace('_', ' ', e($ord['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?= base_url('orders/' . $ord['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size: 0.75rem;">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Latest API Activity -->
        <div class="col-lg-5">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-activity me-2 text-primary"></i>Latest API Logs</h6>
                    <a href="<?= base_url('admin/logs') ?>" class="small text-decoration-none">All Logs &rarr;</a>
                </div>
                <div class="list-group list-group-flush" style="font-size: 0.825rem;">
                    <?php if (empty($recent_logs)): ?>
                        <div class="p-3 text-center text-muted">No API calls recorded yet.</div>
                    <?php else: ?>
                        <?php foreach ($recent_logs as $log): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                <div>
                                    <div class="fw-semibold">
                                        <span class="badge bg-secondary me-1"><?= e($log['service']) ?></span>
                                        <span class="text-dark"><?= e($log['method']) ?></span>
                                    </div>
                                    <div class="text-muted text-truncate" style="max-width: 260px;" title="<?= e($log['endpoint']) ?>">
                                        <?= e($log['endpoint']) ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge <?= ($log['response_status'] >= 200 && $log['response_status'] < 300) ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $log['response_status'] ?: 'ERR' ?>
                                    </span>
                                    <div class="text-muted" style="font-size: 0.7rem;"><?= $log['execution_time_ms'] ?> ms</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->call->view('templates/footer'); ?>
