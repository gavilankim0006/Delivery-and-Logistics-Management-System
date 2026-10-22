<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Live Dispatch Board']);
$today = date('Y-m-d');
$today_is_active = ($date_filter === $today);
?>

<div class="container page-shell">
    <form action="<?= base_url('dispatch') ?>" method="GET" class="dispatch-filter-form" id="dispatch-filter-form">
        <header class="page-header dispatch-page-header mb-4">
            <div>
                <h1 class="page-title">Live Dispatch Operations</h1>
                <p class="page-description">Review delivery status, routes, and rider assignments.</p>
            </div>
            <div class="dispatch-search-group">
                <div class="dispatch-search">
                    <label for="dispatch-search" class="form-label">Search orders</label>
                    <input id="dispatch-search" type="search" name="search" class="form-control" placeholder="Order number, customer, or company" value="<?= e($search) ?>" data-dispatch-filter>
                </div>
                <button class="btn btn-secondary dispatch-status-toggle" type="button" aria-expanded="<?= empty($status_filter) ? 'false' : 'true' ?>" aria-controls="dispatch-status-panel">
                    <i class="bi bi-sliders" aria-hidden="true"></i><span>Filters</span>
                </button>
            </div>
            <p class="small text-muted mb-0">Live updates <span id="dispatch-live-status" role="status" aria-live="polite">Connecting…</span></p>
        </header>

        <section class="surface surface-section mb-4" aria-label="Dispatch filters and order summary">
            <div class="dispatch-toolbar">
                <nav class="ds-segmented dispatch-summary" aria-label="Dispatch summary filters">
                    <a class="ds-segment<?= $today_is_active ? ' is-active' : '' ?>" href="<?= base_url('dispatch') . '?date=' . rawurlencode($today) ?>"<?= $today_is_active ? ' aria-current="page"' : '' ?>>
                        <span>Today's Orders</span><span class="ds-segment__count" data-summary="today_total"><?= number_format((int)$today_total) ?></span>
                    </a>
                    <a class="ds-segment<?= ($status_filter === 'paid') ? ' is-active' : '' ?>" href="<?= base_url('dispatch') . '?status=paid' ?>"<?= ($status_filter === 'paid') ? ' aria-current="page"' : '' ?>>
                        <span>Awaiting Booking</span><span class="ds-segment__count" data-summary="awaiting_booking"><?= number_format((int)$awaiting_booking) ?></span>
                    </a>
                    <span class="ds-segment dispatch-summary__static" aria-label="In transit orders across booking, rider assigned, and picked up statuses">
                        <span>In Transit</span><span class="ds-segment__count" data-summary="in_transit"><?= number_format((int)$in_transit) ?></span>
                    </span>
                    <a class="ds-segment<?= ($status_filter === 'failed') ? ' is-active' : '' ?>" href="<?= base_url('dispatch') . '?status=failed' ?>"<?= ($status_filter === 'failed') ? ' aria-current="page"' : '' ?>>
                        <span>Failed / Exceptions</span><span class="ds-segment__count" data-summary="failed_count"><?= number_format((int)$failed_count) ?></span>
                    </a>
                </nav>
                <div class="dispatch-date-filter">
                    <label for="dispatch-date" class="form-label">Order date</label>
                    <input id="dispatch-date" type="date" name="date" class="form-control" value="<?= e($date_filter) ?>" data-dispatch-filter>
                </div>
            </div>

            <div class="dispatch-filter-controls">
                <div class="dispatch-status-panel" id="dispatch-status-panel"<?= empty($status_filter) ? ' hidden' : '' ?>>
                    <label for="dispatch-status" class="form-label">Status</label>
                    <select id="dispatch-status" name="status" class="form-select" data-dispatch-filter>
                        <option value="">All statuses</option>
                        <option value="draft" <?= ($status_filter === 'draft') ? 'selected' : '' ?>>Draft</option>
                        <option value="pending_payment" <?= ($status_filter === 'pending_payment') ? 'selected' : '' ?>>Pending payment</option>
                        <option value="paid" <?= ($status_filter === 'paid') ? 'selected' : '' ?>>Paid</option>
                        <option value="booking" <?= ($status_filter === 'booking') ? 'selected' : '' ?>>Booking</option>
                        <option value="rider_assigned" <?= ($status_filter === 'rider_assigned') ? 'selected' : '' ?>>Rider assigned</option>
                        <option value="picked_up" <?= ($status_filter === 'picked_up') ? 'selected' : '' ?>>Picked up</option>
                        <option value="delivered" <?= ($status_filter === 'delivered') ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= ($status_filter === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                        <option value="failed" <?= ($status_filter === 'failed') ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>
                <div class="dispatch-filter-actions">
                    <button type="submit" class="btn btn-primary">Apply filters</button>
                    <?php if (!empty($status_filter) || !empty($date_filter) || !empty($search)): ?>
                        <a href="<?= base_url('dispatch') ?>" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </form>

    <?php if (!empty($pending_requests)): ?>
        <section class="surface p-3 p-md-4 mb-4" aria-labelledby="customer-requests-heading">
            <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center"><h2 class="h5 mb-0" id="customer-requests-heading">Hold requests awaiting review</h2><span class="badge text-bg-warning"><?= (int)$pending_request_count ?> pending</span></div>
            <p class="small text-muted mt-2 mb-0">Accepting a hold request records the dispatch decision only; it does not pause a courier automatically.</p>
            <div class="table-responsive mt-3"><table class="table table-clean align-middle mb-0"><thead><tr><th>Order</th><th>Request</th><th>Submitted</th><th>Review</th></tr></thead><tbody>
                <?php foreach ($pending_requests as $request): ?><tr><td><a href="<?= base_url('orders/' . (int)$request['order_id']) ?>"><?= e($request['order_no']) ?></a></td><td><?php if (!empty($request['request_text'])): ?><div class="small text-muted"><?= nl2br(e($request['request_text'])) ?></div><?php else: ?>No note provided<?php endif; ?></td><td><?= e(date('M j, g:i a', strtotime($request['created_at']))) ?></td><td><div class="d-flex gap-2"><form method="post" action="<?= base_url('dispatch/requests/' . (int)$request['id'] . '/review') ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="accepted"><button type="submit" class="btn btn-sm btn-outline-success">Mark accepted</button></form><form method="post" action="<?= base_url('dispatch/requests/' . (int)$request['id'] . '/review') ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="rejected"><button type="submit" class="btn btn-sm btn-outline-danger">Reject</button></form></div></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </section>
    <?php endif; ?>

    <section class="surface" aria-label="Dispatch orders" data-dispatch-vue-board>
        <div id="dispatch-vue-root"
            data-data-url="<?= e($dispatch_data_url) ?>"
            data-base-url="<?= e($dispatch_base_url) ?>"
            data-csrf-token="<?= e($csrf_token) ?>">
            <div class="table-responsive">
                <table class="table table-clean table-responsive-stack dispatch-orders-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Order</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Pickup to drop-off</th>
                            <th scope="col">Status</th>
                            <th scope="col">Rider</th>
                            <th scope="col">Fee</th>
                            <th scope="col" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody id="dispatch-orders-body">
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No orders match these filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $ord): ?>
                                <?php
                                $status_classes = [
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
                                $status_class = $status_classes[$ord['status']] ?? 'draft';
                                ?>
                                <tr>
                                    <td data-label="Order" class="fw-semibold">
                                        <?= e($ord['order_no']) ?>
                                        <div class="text-muted small"><?= date('M d, H:i', strtotime($ord['created_at'])) ?></div>
                                    </td>
                                    <td data-label="Customer">
                                        <div class="fw-medium"><?= e($ord['customer_name']) ?></div>
                                        <?php if (!empty($ord['company_name'])): ?>
                                            <div class="text-muted small"><?= e($ord['company_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Pickup to drop-off" class="route-summary route-cell">
                                        <div class="route-summary__address"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><span class="visually-hidden">Pickup: </span><?= e($ord['pickup_address']) ?></div>
                                        <div class="route-summary__address"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><span class="visually-hidden">Drop-off: </span><?= e($ord['dropoff_address']) ?></div>
                                        <span class="text-muted small"><?= e($ord['distance_km']) ?> km · about <?= e($ord['estimated_duration_min']) ?> min</span>
                                    </td>
                                    <td data-label="Status">
                                        <span class="status-chip status-chip--<?= e($status_class) ?>"><?= ucfirst(str_replace('_', ' ', e($ord['status']))) ?></span>
                                    </td>
                                    <td data-label="Rider">
                                        <?php if (!empty($ord['courier_driver_name']) || !empty($ord['rider_name'])): ?>
                                            <div class="fw-medium"><?= e($ord['courier_driver_name'] ?: $ord['rider_name']) ?></div>
                                            <div class="text-muted small"><?= e($ord['courier_driver_vehicle'] ?: ($ord['plate_number'] ?? '')) ?><?= !empty($ord['courier_driver_phone']) || !empty($ord['rider_phone']) ? ' · ' . e($ord['courier_driver_phone'] ?: $ord['rider_phone']) : '' ?></div>
                                            <?php if (!empty($ord['share_link'])): ?>
                                                <a href="<?= e($ord['share_link']) ?>" target="_blank" rel="noopener noreferrer" class="d-inline-block mt-1">
                                                    Live tracking <span class="visually-hidden">(opens in a new tab)</span><i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Fee" class="fw-semibold text-nowrap">₱<?= number_format((float)$ord['delivery_fee'], 2) ?></td>
                                    <td data-label="Action" class="text-end">
                                        <?php if (!empty($ord['recovery'])): ?>
                                            <div class="small text-start mb-2"><span class="badge <?= $ord['recovery']['priority'] === 'delayed' || $ord['recovery']['priority'] === 'failed' ? 'text-bg-warning' : 'text-bg-info' ?>"><?= e(ucfirst($ord['recovery']['priority'])) ?></span><div class="mt-1"><?= e($ord['recovery']['message']) ?><?php if ($ord['recovery']['age_minutes'] !== null): ?> <span class="text-muted">(<?= (int)$ord['recovery']['age_minutes'] ?> min in state)</span><?php endif; ?></div></div>
                                            <?php if (in_array($ord['recovery']['kind'], ['book', 'retry'], true)): ?>
                                                <form method="post" action="<?= base_url('dispatch/recover/' . (int)$ord['id']) ?>" class="mb-2"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-primary"><?= e($ord['recovery']['label']) ?></button></form>
                                            <?php elseif (!empty($ord['pickup_phone'])): ?><a class="btn btn-sm btn-outline-primary mb-2" href="tel:<?= e(preg_replace('/[^+0-9]/', '', $ord['pickup_phone'])) ?>">Call customer</a><?php endif; ?>
                                        <?php endif; ?>
                                        <a href="<?= base_url('orders/' . $ord['id']) ?>" class="dispatch-row-action">View order</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<?php
$extra_js = '<script type="module" nonce="' . e(defined('CSP_NONCE') ? CSP_NONCE : '') . '" src="' . e(base_url('public/build/dispatch.js')) . '"></script>';
$extra_js .= '<script nonce="' . e(defined('CSP_NONCE') ? CSP_NONCE : '') . '">document.querySelectorAll("[data-dispatch-filter]").forEach(function(input){input.addEventListener("change",function(){var form=document.getElementById("dispatch-filter-form");if(form)form.requestSubmit()})});</script>';
$this->call->view('templates/footer');
?>
