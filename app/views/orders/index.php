<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Orders']);
$statusLabels = [
    'draft' => 'Draft',
    'pending_payment' => 'Pending payment',
    'paid' => 'Paid',
    'booking' => 'Booking',
    'rider_assigned' => 'Rider assigned',
    'picked_up' => 'Picked up',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
    'failed' => 'Failed'
];
$statusClasses = [
    'draft' => 'draft',
    'pending_payment' => 'pending',
    'paid' => 'paid',
    'booking' => 'booking',
    'rider_assigned' => 'assigned',
    'picked_up' => 'picked',
    'delivered' => 'delivered',
    'cancelled' => 'cancelled',
    'failed' => 'failed'
];
$query = array_filter([
    'status' => $filters['status'] ?? '',
    'date' => $filters['date'] ?? '',
    'search' => $filters['search'] ?? ''
], static fn($value) => $value !== '');
$routeStages = ['placed', 'paid', 'rider_assigned', 'picked_up', 'delivered'];
$routeStatusStage = [
    'draft' => 'placed', 'pending_payment' => 'placed', 'paid' => 'paid', 'booking' => 'paid',
    'rider_assigned' => 'rider_assigned', 'picked_up' => 'picked_up', 'delivered' => 'delivered'
];
$timelineStages = ['draft' => 'placed', 'pending_payment' => 'placed', 'paid' => 'paid', 'booking' => 'paid', 'rider_assigned' => 'rider_assigned', 'picked_up' => 'picked_up', 'delivered' => 'delivered'];
$orderListProps = [
    'title' => $page_title ?? 'Orders',
    'orders' => $orders ?? [],
    'filters' => array_intersect_key($filters ?? [], array_flip(['status', 'date', 'search'])),
    'role' => $role ?? 'customer',
    'page' => (int)($page ?? 1),
    'totalPages' => (int)($total_pages ?? 1),
    'totalOrders' => (int)($total_orders ?? 0),
    'today' => date('Y-m-d'),
    'baseUrl' => base_url()
];
?>
<div
    id="vue-order-list-root"
    data-page="order-list"
    data-requires-vue="true"
    data-props="<?= e(json_encode($orderListProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
<div class="container py-4 page-shell">
    <header class="page-header">
        <div>
            <h1 class="page-title"><?= e($page_title ?? 'Orders') ?></h1>
            <p class="text-muted mb-0 page-description">Review delivery details and follow each order's progress.</p>
        </div>
        <?php if ($role === 'customer'): ?>
            <a class="btn btn-primary" href="<?= base_url('orders/new') ?>">New order</a>
        <?php endif; ?>
    </header>

    <form class="row g-3 align-items-end mb-4" method="get" action="<?= base_url('orders') ?>">
        <div class="col-12 col-md-3 shared-field-wrap">
            <label class="form-label shared-label" for="order-status">Status</label>
            <select class="form-select shared-select" id="order-status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3 shared-field-wrap">
            <label class="form-label shared-label" for="order-date">Date</label>
            <input class="form-control shared-input" id="order-date" type="date" name="date" max="<?= date('Y-m-d') ?>" value="<?= e($filters['date'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-4 shared-field-wrap">
            <label class="form-label shared-label" for="order-search">Search</label>
            <div class="position-relative">
                <input class="form-control shared-input pe-5" id="order-search" type="search" name="search" maxlength="100" value="<?= e($filters['search'] ?? '') ?>" placeholder="Search by order number or recipient">
                <?php if (!empty($filters['search'])): ?>
                    <button type="button" class="btn-clear-search" aria-label="Clear search" onclick="const input = document.getElementById('order-search'); input.value = ''; input.focus();">
                        <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-outline-primary flex-grow-1" type="submit">Filter</button>
            <?php if (!empty($query)): ?><a class="btn btn-outline-secondary" href="<?= base_url('orders') ?>">Clear</a><?php endif; ?>
        </div>
    </form>

    <?php if (empty($orders)): ?>
        <?php $this->call->view('partials/empty_state', [
            'title' => empty($query) ? 'No orders yet' : 'No matching orders',
            'message' => empty($query) ? 'Create your first delivery to get started.' : 'Try changing the filters to find the delivery you need.',
            'action_label' => $role === 'customer' && empty($query) ? 'Create an order' : '',
            'action_url' => $role === 'customer' && empty($query) ? base_url('orders/new') : ''
        ]); ?>
    <?php else: ?>
        <!-- Mobile Adaptive Cards View (SSR fallback / non-JS) -->
        <div class="d-md-none">
            <?php foreach ($orders as $order): ?>
                <?php $status = $order['status']; ?>
                <article class="surface p-3 mb-3 rounded-3 border">
                    <div class="order-card-header">
                        <div>
                            <a class="order-card-title fw-bold" href="<?= base_url('orders/' . (int)$order['id']) ?>">
                                <?= e($order['order_no']) ?>
                            </a>
                            <div class="order-card-date"><?= e(date('M j, Y · g:i a', strtotime($order['created_at']))) ?></div>
                        </div>
                        <span class="status-chip status-chip--<?= e($statusClasses[$status] ?? 'draft') ?>">
                            <?= e($statusLabels[$status] ?? $status) ?>
                        </span>
                    </div>

                    <div class="order-card-route">
                        <div class="order-card-route-stop">
                            <span class="order-card-dot order-card-dot--pickup" aria-hidden="true"></span>
                            <span class="order-card-route-text small">
                                <strong>Pickup:</strong> <?= e($order['pickup_address']) ?>
                            </span>
                        </div>
                        <div class="order-card-route-stop">
                            <span class="order-card-dot order-card-dot--dropoff" aria-hidden="true"></span>
                            <span class="order-card-route-text small">
                                <strong>Drop-off:</strong> <?= e($order['dropoff_address']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="order-card-footer">
                        <div>
                            <span class="text-muted small d-block">Delivery fee</span>
                            <span class="order-card-fee">₱<?= number_format((float)$order['delivery_fee'], 2) ?></span>
                        </div>
                        <div class="order-card-actions">
                            <?php if ($role === 'customer' && in_array($status, ['draft', 'pending_payment'], true)): ?>
                                <a class="btn btn-primary" href="<?= base_url('orders/' . (int)$order['id']) ?>">Pay now</a>
                            <?php else: ?>
                                <a class="btn btn-outline-secondary" href="<?= base_url('orders/' . (int)$order['id']) ?>">Details</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="table-responsive surface d-none d-md-block">
            <table class="table align-middle mb-0 table-responsive-stack table-clean">
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <?php if ($role !== 'customer'): ?><th scope="col">Customer</th><?php endif; ?>
                        <th scope="col">Route</th>
                        <th scope="col">Status</th>
                        <th scope="col">Fee</th>
                        <th scope="col"><span class="visually-hidden">Details</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php $status = $order['status']; ?>
                    <tr>
                        <td data-label="Order">
                            <a class="fw-semibold text-decoration-none" href="<?= base_url('orders/' . (int)$order['id']) ?>"><?= e($order['order_no']) ?></a>
                            <div class="small text-muted"><?= e(date('M j, Y · g:i a', strtotime($order['created_at']))) ?></div>
                        </td>
                        <?php if ($role !== 'customer'): ?><td data-label="Customer"><?= e($order['customer_name']) ?></td><?php endif; ?>
                        <td data-label="Route" class="route-cell">
                            <span class="route-cell__address small"><strong>From:</strong> <?= e($order['pickup_address']) ?></span>
                            <span class="route-cell__address small"><strong>To:</strong> <?= e($order['dropoff_address']) ?></span>
                            <span class="small text-muted"><?= e(number_format((float)$order['distance_km'], 2)) ?> km</span>
                        </td>
                        <td data-label="Status">
                            <?php $this->call->view('partials/status_chip', ['status' => $status]); ?>
                            <?php if ($role === 'customer' && !in_array($status, ['cancelled', 'failed'], true)): ?>
                                <div class="compact-route-progress mt-2" role="img" aria-label="Delivery progress: <?= e($statusLabels[$status] ?? 'Unknown') ?>">
                                    <span class="compact-route-progress__track" aria-hidden="true">
                                        <?php
                                        $progressIndex = array_search($routeStatusStage[$status] ?? 'placed', $routeStages, true);
                                        foreach ($order['timeline'] ?? [] as $item) {
                                            $stage = $timelineStages[$item['to_status']] ?? null;
                                            if ($stage !== null) {
                                                $matchIndex = array_search($stage, $routeStages, true);
                                                if ($matchIndex !== false && $matchIndex > $progressIndex) {
                                                    $progressIndex = $matchIndex;
                                                }
                                            }
                                        }
                                        $stageIndex = $progressIndex === false ? 0 : $progressIndex;
                                        ?>
                                        <span class="compact-route-progress__fill compact-route-progress__fill--<?= (int)$stageIndex ?>"></span>
                                    </span>
                                </div>
                            <?php elseif ($role === 'customer'): ?>
                                <p class="small text-muted mt-2 mb-0">Progress stopped · <?= e($statusLabels[$status] ?? $status) ?></p>
                            <?php endif; ?>
                        </td>
                        <td data-label="Fee" class="text-nowrap money">₱<?= number_format((float)$order['delivery_fee'], 2) ?></td>
                        <td data-label="Details"><a class="btn btn-sm btn-outline-secondary" href="<?= base_url('orders/' . (int)$order['id']) ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($total_pages ?? 1) > 1): ?>
            <nav class="mt-3" aria-label="Order pages">
                <ul class="pagination justify-content-end">
                    <li class="page-item <?= ($page ?? 1) <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('orders?' . http_build_query(array_merge($query, ['page' => max(1, ($page ?? 1) - 1)]))) ?>" aria-label="Previous page">« Previous</a>
                    </li>
                    <?php for ($number = 1; $number <= (int)$total_pages; $number++): ?>
                        <li class="page-item <?= $number === (int)($page ?? 1) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= base_url('orders?' . http_build_query(array_merge($query, ['page' => $number]))) ?>" <?= $number === (int)($page ?? 1) ? 'aria-current="page"' : '' ?>><?= $number ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page ?? 1) >= (int)$total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('orders?' . http_build_query(array_merge($query, ['page' => min((int)$total_pages, ($page ?? 1) + 1)]))) ?>" aria-label="Next page">Next »</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
        <p class="small text-muted text-end">Showing <?= count($orders) ?> of <?= (int)($total_orders ?? count($orders)) ?> orders</p>
    <?php endif; ?>
</div>
</div>
<script type="module" src="<?= e(base_url('build/orders.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
