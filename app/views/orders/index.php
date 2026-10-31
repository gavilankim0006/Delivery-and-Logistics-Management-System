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
<div id="vue-orders-root" data-page="list" data-props="<?= e(json_encode($orderListProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
<noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to view the orders list.</div></div></noscript>
<div class="container py-4 page-shell">
    <header class="page-header">
        <div>
            <h1 class="page-title"><?= $role === 'customer' ? 'My orders' : 'Orders' ?></h1>
            <p class="text-muted mb-0 page-description">Review delivery details and follow each order's progress.</p>
        </div>
        <?php if ($role === 'customer'): ?>
            <a class="btn btn-primary" href="<?= base_url('orders/new') ?>">New order</a>
        <?php endif; ?>
    </header>

    <form class="row g-3 align-items-end mb-4" method="get" action="<?= base_url('orders') ?>">
        <div class="col-12 col-md-3">
            <label class="form-label" for="order-status">Status</label>
            <select class="form-select" id="order-status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label" for="order-date">Date</label>
            <input class="form-control" id="order-date" type="date" name="date" max="<?= date('Y-m-d') ?>" value="<?= e($filters['date'] ?? '') ?>">
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="order-search">Search</label>
            <input class="form-control" id="order-search" type="search" name="search" maxlength="100" value="<?= e($filters['search'] ?? '') ?>" placeholder="Order number, customer, or address">
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
        <div class="table-responsive surface">
            <table class="table align-middle mb-0 table-responsive-stack table-clean">
                <thead>
                    <tr><th scope="col">Order</th><?php if ($role !== 'customer'): ?><th scope="col">Customer</th><?php endif; ?><th scope="col">Route</th><th scope="col">Status</th><th scope="col">Fee</th><th scope="col"><span class="visually-hidden">Details</span></th></tr>
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
                                        foreach ($order['timeline'] ?? [] as $timelineEntry) {
                                            $milestone = $timelineStages[$timelineEntry['to_status'] ?? ''] ?? null;
                                            if ($milestone !== null) {
                                                $progressIndex = max($progressIndex, array_search($milestone, $routeStages, true));
                                            }
                                        }
                                        ?>
                                        <span class="compact-route-progress__fill compact-route-progress__fill--<?= (int)$progressIndex ?>"></span>
                                    </span>
                                </div>
                            <?php elseif ($role === 'customer'): ?>
                                <p class="small text-muted mt-2 mb-0">Progress stopped · <?= e($statusLabels[$status] ?? 'Unknown') ?></p>
                            <?php endif; ?>
                        </td>
                        <td data-label="Fee" class="text-nowrap money">₱<?= e(number_format((float)$order['delivery_fee'], 2)) ?></td>
                        <td data-label="Details"><a class="btn btn-sm btn-outline-secondary" href="<?= base_url('orders/' . (int)$order['id']) ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages > 1): ?>
            <nav class="mt-3" aria-label="Order pages">
                <ul class="pagination justify-content-end">
                    <?php if ($page > 1): ?>
                        <?php $prevQuery = $query; $prevQuery['page'] = $page - 1; ?>
                        <li class="page-item"><a class="page-link" href="<?= base_url('orders') ?>?<?= e(http_build_query($prevQuery)) ?>" aria-label="Previous page">&laquo; Previous</a></li>
                    <?php else: ?>
                        <li class="page-item disabled"><span class="page-link" aria-hidden="true">&laquo; Previous</span></li>
                    <?php endif; ?>
                    <?php for ($pageNumber = 1; $pageNumber <= $total_pages; $pageNumber++): ?>
                        <?php $pageQuery = $query; $pageQuery['page'] = $pageNumber; ?>
                        <li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>"><a class="page-link" href="<?= base_url('orders') ?>?<?= e(http_build_query($pageQuery)) ?>" <?= $pageNumber === $page ? 'aria-current="page"' : '' ?>><?= $pageNumber ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <?php $nextQuery = $query; $nextQuery['page'] = $page + 1; ?>
                        <li class="page-item"><a class="page-link" href="<?= base_url('orders') ?>?<?= e(http_build_query($nextQuery)) ?>" aria-label="Next page">Next &raquo;</a></li>
                    <?php else: ?>
                        <li class="page-item disabled"><span class="page-link" aria-hidden="true">Next &raquo;</span></li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
        <p class="small text-muted text-end">Showing <?= count($orders) ?> of <?= (int)$total_orders ?> orders</p>
    <?php endif; ?>
</div>
</div>
<script nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>">document.querySelector('#vue-orders-root > .container[hidden]')?.removeAttribute('hidden');</script>
<script type="module" src="<?= e(base_url('public/build/orders.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
