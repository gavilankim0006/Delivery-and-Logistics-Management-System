<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Live Dispatch Board']);
?>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-kanban text-primary me-2"></i>Live Dispatch Operations</h1>
            <p class="text-muted small mb-0">Active delivery routing, driver matching, and exception handling</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" onclick="window.location.reload();">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh Board
            </button>
        </div>
    </div>

    <!-- Summary Counters (Section 3.6 Wireframe) -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-md-3">
            <div class="card p-3 shadow-sm border-0 border-start border-4 border-primary">
                <span class="text-muted small text-uppercase fw-semibold">Today's Orders</span>
                <h3 class="fw-bold my-1"><?= $today_total ?></h3>
                <span class="text-muted small">Placed today</span>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card p-3 shadow-sm border-0 border-start border-4 border-warning">
                <span class="text-muted small text-uppercase fw-semibold">Awaiting Booking</span>
                <h3 class="fw-bold my-1 text-warning"><?= $awaiting_booking ?></h3>
                <span class="text-muted small">Paid & ready for courier</span>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card p-3 shadow-sm border-0 border-start border-4 border-info">
                <span class="text-muted small text-uppercase fw-semibold">In Transit</span>
                <h3 class="fw-bold my-1 text-info"><?= $in_transit ?></h3>
                <span class="text-muted small">En route to pickup / drop</span>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card p-3 shadow-sm border-0 border-start border-4 border-danger">
                <span class="text-muted small text-uppercase fw-semibold">Failed / Exceptions</span>
                <h3 class="fw-bold my-1 text-danger"><?= $failed_count ?></h3>
                <span class="text-muted small">Requires staff action</span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body py-3">
            <form action="<?= base_url('dispatch') ?>" method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= ($status_filter === 'draft') ? 'selected' : '' ?>>Draft</option>
                        <option value="pending_payment" <?= ($status_filter === 'pending_payment') ? 'selected' : '' ?>>Pending Payment</option>
                        <option value="paid" <?= ($status_filter === 'paid') ? 'selected' : '' ?>>Paid</option>
                        <option value="booking" <?= ($status_filter === 'booking') ? 'selected' : '' ?>>Booking</option>
                        <option value="rider_assigned" <?= ($status_filter === 'rider_assigned') ? 'selected' : '' ?>>Rider Assigned</option>
                        <option value="picked_up" <?= ($status_filter === 'picked_up') ? 'selected' : '' ?>>Picked Up</option>
                        <option value="delivered" <?= ($status_filter === 'delivered') ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= ($status_filter === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                        <option value="failed" <?= ($status_filter === 'failed') ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date_filter) ?>" placeholder="Filter by date">
                </div>
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search order #, customer, or company..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary btn-sm flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
                    <?php if (!empty($status_filter) || !empty($date_filter) || !empty($search)): ?>
                        <a href="<?= base_url('dispatch') ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-responsive-stack" style="font-size: 0.875rem;">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Pickup &rarr; Drop-off</th>
                        <th>Status</th>
                        <th>Rider Details</th>
                        <th>Fee</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No orders matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $ord): ?>
                            <tr>
                                <td data-label="Order #" class="fw-bold text-primary">
                                    <?= e($ord['order_no']) ?>
                                    <div class="text-muted small fw-normal"><?= date('M d, H:i', strtotime($ord['created_at'])) ?></div>
                                </td>
                                <td data-label="Customer">
                                    <div class="fw-semibold"><?= e($ord['customer_name']) ?></div>
                                    <?php if (!empty($ord['company_name'])): ?>
                                        <div class="text-muted small"><?= e($ord['company_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Route" style="max-width: 320px;">
                                    <div class="text-truncate small"><i class="bi bi-geo-alt-fill text-success me-1"></i><?= e($ord['pickup_address']) ?></div>
                                    <div class="text-truncate small"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($ord['dropoff_address']) ?></div>
                                    <span class="badge bg-light text-muted border" style="font-size: 0.7rem;"><?= $ord['distance_km'] ?> km &bull; ~<?= $ord['estimated_duration_min'] ?> min</span>
                                </td>
                                <td data-label="Status">
                                    <span class="badge-status badge-<?= e($ord['status']) ?>">
                                        <?= str_replace('_', ' ', e($ord['status'])) ?>
                                    </span>
                                </td>
                                <td data-label="Rider">
                                    <?php if (!empty($ord['rider_name'])): ?>
                                        <div class="fw-semibold"><?= e($ord['rider_name']) ?></div>
                                        <div class="text-muted small"><?= e($ord['plate_number']) ?> &bull; <?= e($ord['rider_phone']) ?></div>
                                        <?php if (!empty($ord['share_link'])): ?>
                                            <a href="<?= e($ord['share_link']) ?>" target="_blank" class="badge bg-primary text-decoration-none mt-1" style="font-size: 0.68rem;">
                                                <i class="bi bi-box-arrow-up-right me-1"></i>Live Track
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Fee" class="fw-bold">
                                    ₱<?= number_format($ord['delivery_fee'], 2) ?>
                                </td>
                                <td data-label="Actions" class="text-end">
                                    <a href="<?= base_url('orders/' . $ord['id']) ?>" class="btn btn-outline-primary btn-sm py-1 px-2">
                                        View Order
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $this->call->view('templates/footer'); ?>
