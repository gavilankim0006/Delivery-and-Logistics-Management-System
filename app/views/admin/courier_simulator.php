<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Courier Simulator']);
$statusLabels = [
    'paid' => 'Paid — ready to book', 'booking' => 'Booking', 'rider_assigned' => 'Rider assigned',
    'picked_up' => 'Picked up', 'failed' => 'Failed — can retry', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'
];
$availableEvents = [
    'booking' => ['rider_assigned', 'failed', 'cancelled'],
    'rider_assigned' => ['picked_up', 'failed', 'cancelled'],
    'picked_up' => ['delivered', 'failed'],
    'failed' => [],
    'paid' => ['failed', 'cancelled'],
];
$eventLabels = [
    'rider_assigned' => 'Rider assigned', 'picked_up' => 'Picked up', 'delivered' => 'Delivered',
    'failed' => 'Failed', 'cancelled' => 'Cancelled'
];
?>
<div class="container py-4 ds-page page-shell">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4 page-header">
        <div>
            <p class="eyebrow mb-2">Development tools · <?= e(ucfirst($driver_name)) ?> provider</p>
            <h1 class="h3 fw-bold mb-2 page-title">Courier simulator</h1>
            <p class="text-muted mb-0">Simulated Lalamove-shaped events go through the same webhook processor used by inbound deliveries.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= base_url('admin') ?>">Back to admin</a>
    </header>

    <div class="alert alert-warning" role="note"><strong>Local development only.</strong> No courier credentials or real booking requests are used here. A mock order must be booked before its status events can be simulated.</div>

    <?php if (empty($orders)): ?>
        <div class="empty-state"><h2 class="h5">No eligible orders</h2><p class="mb-0">Create an order and use the mock payment flow or prepare a paid test order before simulating a courier delivery.</p></div>
    <?php else: ?>
        <div class="table-responsive surface">
            <table class="table align-middle mb-0">
                <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Mock driver</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php $status = (string)$order['status']; $events = $availableEvents[$status] ?? []; ?>
                    <tr>
                        <td><a href="<?= base_url('orders/' . (int)$order['id']) ?>"><strong><?= e($order['order_no']) ?></strong></a><div class="small text-muted"><?= e($order['lalamove_order_id'] ?: 'No courier order yet') ?></div></td>
                        <td><?= e($order['customer_name']) ?></td>
                        <td><?= e($statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status))) ?></td>
                        <td><?= e($order['courier_driver_name'] ?? '—') ?></td>
                        <td>
                            <?php if (in_array($status, ['paid', 'failed'], true) && empty($order['lalamove_order_id'])): ?>
                                <form method="post" action="<?= base_url('admin/courier-simulator/book/' . (int)$order['id']) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-primary" type="submit">Book mock courier</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($events): ?>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php foreach ($events as $event): ?>
                                    <form method="post" action="<?= base_url('admin/courier-simulator/' . (int)$order['id']) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="event_type" value="<?= e($event) ?>">
                                        <button class="btn btn-sm <?= $event === 'failed' || $event === 'cancelled' ? 'btn-outline-danger' : 'btn-outline-primary' ?>" type="submit"><?= e($eventLabels[$event]) ?></button>
                                    </form>
                                <?php endforeach; ?>
                                </div>
                            <?php elseif (!in_array($status, ['paid', 'failed'], true)): ?>
                                <span class="small text-muted">No valid next simulation for this status.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php $this->call->view('templates/footer'); ?>
