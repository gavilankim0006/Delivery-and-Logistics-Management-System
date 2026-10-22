<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Order details']);
$statusLabels = [
    'draft' => 'Draft', 'pending_payment' => 'Pending payment', 'paid' => 'Paid',
    'booking' => 'Booking', 'rider_assigned' => 'Rider assigned', 'picked_up' => 'Picked up',
    'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'failed' => 'Failed'
];
$statusClasses = [
    'draft' => 'draft', 'pending_payment' => 'pending', 'paid' => 'paid', 'booking' => 'booking',
    'rider_assigned' => 'assigned', 'picked_up' => 'picked', 'delivered' => 'delivered',
    'cancelled' => 'cancelled', 'failed' => 'failed'
];
$trackerSteps = [
    'draft' => ['label' => 'Placed', 'status' => 'draft'],
    'paid' => ['label' => 'Paid', 'status' => 'paid'],
    'rider_assigned' => ['label' => 'Rider assigned', 'status' => 'rider_assigned'],
    'picked_up' => ['label' => 'Picked up', 'status' => 'picked_up'],
    'delivered' => ['label' => 'Delivered', 'status' => 'delivered']
];
$trackerProgress = [
    'draft' => 0, 'pending_payment' => 0, 'paid' => 1, 'booking' => 1,
    'rider_assigned' => 2, 'picked_up' => 3, 'delivered' => 4
];
$lastReached = $trackerProgress[$order['status']] ?? 0;
foreach ($order['timeline'] ?? [] as $entry) {
    if (isset($trackerProgress[$entry['to_status']])) {
        $lastReached = max($lastReached, $trackerProgress[$entry['to_status']]);
    }
}
?>
<div class="container py-4 ds-page page-shell">
    <nav aria-label="Breadcrumb" class="mb-3"><a href="<?= base_url('orders') ?>">Orders</a><span class="text-muted"> / <?= e($order['order_no']) ?></span></nav>
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4 ds-page-header page-header">
        <div>
            <h1 class="h3 fw-bold mb-2 page-title"><?= e($order['order_no']) ?></h1>
            <?php $status = $order['status']; $statusClass = $statusClasses[$status] ?? 'draft'; ?>
            <span class="status-chip status-chip--<?= e($statusClass) ?> ds-status-chip"><span aria-hidden="true">●</span> <?= e($statusLabels[$status] ?? 'Unknown') ?></span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (($role ?? null) === 'customer' && $order['status'] === 'draft'): ?>
                <a class="btn btn-primary" href="<?= base_url('orders/' . (int)$order['id'] . '/edit') ?>">Edit draft</a>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteDraftModal">Delete draft</button>
            <?php endif; ?>
            <?php if (($role ?? null) === 'customer' && in_array($order['status'], ['paid', 'booking', 'rider_assigned', 'picked_up', 'delivered'], true)): ?>
                <form method="post" action="<?= base_url('orders/' . (int)$order['id'] . '/control-pass') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-outline-primary" type="submit"><?= !empty($control_pass_active) ? 'Rotate control pass' : 'Create control pass' ?></button></form>
                <?php if (!empty($control_pass_active)): ?><form method="post" action="<?= base_url('orders/' . (int)$order['id'] . '/control-pass/revoke') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-outline-danger" type="submit">Revoke pass</button></form><?php endif; ?>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= base_url('orders') ?>">Back to orders</a>
        </div>
    </header>

    <?php if (is_file(__DIR__ . '/../partials/route_tracker.php')): ?>
        <?php $this->call->view('partials/route_tracker', ['order' => $order]); ?>
    <?php else: ?>
        <section class="card mb-4 route-tracker ds-panel" aria-labelledby="progress-heading">
            <div class="card-body">
                <h2 class="h5" id="progress-heading">Delivery progress</h2>
                <ol class="list-unstyled d-flex justify-content-between gap-2 my-4 route-tracker__steps">
                    <?php foreach (array_values($trackerSteps) as $stepIndex => $step): ?>
                        <?php
                        $reached = $stepIndex <= $lastReached;
                        $isCurrent = $stepIndex === $lastReached;
                        $stepTime = null;
                        foreach ($order['timeline'] ?? [] as $entry) {
                            if ($entry['to_status'] === $step['status']) { $stepTime = $entry['created_at']; }
                        }
                        $stepClass = $reached ? ($isCurrent ? 'route-tracker__step--current' : 'route-tracker__step--complete') : 'route-tracker__step--upcoming';
                        if ($isCurrent && in_array($order['status'], ['cancelled', 'failed'], true)) { $stepClass .= ' route-tracker__step--exception'; }
                        ?>
                        <li class="text-center flex-fill route-tracker__step <?= e($stepClass) ?>">
                            <span class="route-tracker__node" aria-hidden="true"><?= $isCurrent && in_array($order['status'], ['cancelled', 'failed'], true) ? '×' : ($reached ? '✓' : '') ?></span>
                            <span class="d-block small fw-medium"><?= e($step['label']) ?></span>
                            <?php if ($stepTime): ?><time class="d-block small text-muted" datetime="<?= e(date(DATE_ATOM, strtotime($stepTime))) ?>"><?= e(date('M j, g:i a', strtotime($stepTime))) ?></time><?php else: ?><span class="d-block small text-muted">Pending</span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if (in_array($order['status'], ['cancelled', 'failed'], true)): ?>
                    <p class="mb-0 ds-notice">This order was <?= e($statusLabels[$order['status']]) ?>. <?= e($order['cancel_reason'] ?? '') ?></p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="surface ds-panel p-3 p-md-4" aria-label="Delivery details">
        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <section aria-labelledby="route-heading">
                    <h2 class="h5 mb-3" id="route-heading">Delivery route</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Pickup</dt><dd class="col-sm-9"><?= e($order['pickup_address']) ?></dd>
                        <dt class="col-sm-3">Contact</dt><dd class="col-sm-9"><?= e($order['pickup_contact_name']) ?> · <?= e($order['pickup_phone']) ?></dd>
                        <?php if (!empty($order['pickup_notes'])): ?><dt class="col-sm-3">Pickup notes</dt><dd class="col-sm-9"><?= nl2br(e($order['pickup_notes'])) ?></dd><?php endif; ?>
                        <dt class="col-sm-3">Drop-off</dt><dd class="col-sm-9"><?= e($order['dropoff_address']) ?></dd>
                        <dt class="col-sm-3">Contact</dt><dd class="col-sm-9"><?= e($order['dropoff_contact_name']) ?> · <?= e($order['dropoff_phone']) ?></dd>
                        <?php if (!empty($order['dropoff_notes'])): ?><dt class="col-sm-3">Drop-off notes</dt><dd class="col-sm-9"><?= nl2br(e($order['dropoff_notes'])) ?></dd><?php endif; ?>
                    </dl>
                </section>
            </div>
            <div class="col-12 col-lg-5 border-start-lg">
                <section aria-labelledby="parcel-heading">
                    <h2 class="h5 mb-3" id="parcel-heading">Parcel and fee</h2>
                    <p class="mb-1 fw-semibold"><?= e($order['parcel_description'] ?? 'Parcel details unavailable') ?></p>
                    <?php if (!empty($order['weight_kg'])): ?><p class="text-muted mb-3"><?= e(number_format((float)$order['weight_kg'], 2)) ?> kg · Size <?= e($order['parcel_size']) ?><?= !empty($order['is_fragile']) ? ' · Fragile' : '' ?></p><?php endif; ?>
                    <dl class="row mb-0"><dt class="col-7">Distance</dt><dd class="col-5 text-end"><?= e(number_format((float)$order['distance_km'], 2)) ?> km</dd><dt class="col-7">Estimated time</dt><dd class="col-5 text-end"><?= (int)$order['estimated_duration_min'] ?> min</dd><dt class="col-7 fw-bold">Delivery fee</dt><dd class="col-5 text-end fw-bold">₱<?= e(number_format((float)$order['delivery_fee'], 2)) ?></dd></dl>
                    <?php if (!empty($order['courier_driver_name']) || !empty($order['rider_name'])): ?><hr><h3 class="h6">Courier driver</h3><p class="mb-0"><?= e(!empty($order['courier_driver_name']) ? $order['courier_driver_name'] : $order['rider_name']) ?><?= !empty($order['courier_driver_vehicle']) || !empty($order['plate_number']) ? ' · ' . e(!empty($order['courier_driver_vehicle']) ? $order['courier_driver_vehicle'] : $order['plate_number']) : '' ?><?= !empty($order['courier_driver_phone']) || !empty($order['rider_phone']) ? '<br>' . e(!empty($order['courier_driver_phone']) ? $order['courier_driver_phone'] : $order['rider_phone']) : '' ?></p><?php endif; ?>
                    <?php if (!empty($order['share_link'])): ?><p class="mt-2 mb-0"><a href="<?= e($order['share_link']) ?>" target="_blank" rel="noopener noreferrer">Track with courier</a></p><?php endif; ?>
                </section>
            </div>
        </div>
    </section>

    <section class="mt-4" aria-labelledby="history-heading">
        <h2 class="h5 mb-3" id="history-heading">Delivery history</h2>
        <ul class="list-group list-group-flush">
            <?php foreach ($order['timeline'] as $entry): ?>
                <li class="list-group-item d-flex flex-wrap justify-content-between gap-2"><span><strong><?= e($statusLabels[$entry['to_status']] ?? $entry['to_status']) ?></strong><?php if (!empty($entry['notes'])): ?> · <?= e($entry['notes']) ?><?php endif; ?></span><time class="text-muted small" datetime="<?= e(date(DATE_ATOM, strtotime($entry['created_at']))) ?>"><?= e(date('M j, Y g:i a', strtotime($entry['created_at']))) ?></time></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if (($role ?? null) === 'customer' && $order['status'] === 'draft'): ?>
        <div class="modal fade" id="deleteDraftModal" tabindex="-1" aria-labelledby="deleteDraftModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="deleteDraftModalLabel">Delete this draft?</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body"><p class="mb-0">Order <?= e($order['order_no']) ?> will be hidden from your order list. Its history will be retained.</p></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep draft</button>
                        <form method="post" action="<?= base_url('orders/' . (int)$order['id'] . '/delete') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger">Delete draft</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!in_array($order['status'], ['picked_up', 'delivered', 'cancelled', 'failed'], true)): ?>
        <div class="mt-4">
            <button type="button" class="btn btn-link text-danger p-0" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">Cancel order</button>
        </div>
        <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="cancelOrderModalLabel">Cancel this order?</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Order <?= e($order['order_no']) ?> will be marked as cancelled. This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep order</button>
                        <form method="post" action="<?= base_url('orders/' . (int)$order['id'] . '/cancel') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger">Cancel order</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php $this->call->view('templates/footer'); ?>
