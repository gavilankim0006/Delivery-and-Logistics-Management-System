<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Order details']);
$currentUser = auth_user() ?: [];
$orderDetailProps = [
    'order' => $order ?? [],
    'role' => $role ?? '',
    'controlPassActive' => !empty($control_pass_active),
    'riderLocationEnabled' => !empty($rider_location_enabled),
    'paymentNotice' => in_array($_GET['payment'] ?? '', ['return', 'cancelled'], true) ? $_GET['payment'] : '',
    'demoPaymentSimulationEnabled' => !empty($demo_payment_simulation_enabled),
    'currentUser' => array_intersect_key($currentUser, array_flip(['id', 'email', 'phone', 'delivery_status_sms_opt_in', 'push_opt_in', 'email_notifications_opt_in', 'fcm_token'])),
    'csrfToken' => csrf_token(),
    'baseUrl' => base_url()
];
?>
<?php if (($role ?? '') === 'customer'): ?>
<div class="container py-3">
    <a class="btn btn-outline-primary" href="<?= e(base_url('package-workflow')) ?>">Find customer / package handoff</a>
    <?php if (in_array($order['status'] ?? '', ['paid', 'booking', 'rider_assigned', 'picked_up', 'delivered'], true)): ?>
        <details class="mt-3 surface p-3 rounded">
            <summary class="fw-semibold text-danger cursor-pointer">Report missing package</summary>
            <form method="post" action="<?= e(base_url('package-workflow/report')) ?>" class="mt-3" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                <div class="mb-3 shared-field-wrap">
                    <label for="missing-description" class="form-label shared-label">
                        <span>Describe missing package</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <textarea
                        id="missing-description"
                        class="form-control shared-textarea"
                        name="description"
                        minlength="10"
                        maxlength="4000"
                        rows="3"
                        required
                        placeholder="Provide specific details about the missing package (10–4,000 characters)"></textarea>
                    <div class="form-text shared-helper-text">Include package contents, packaging description, and last known status (min 10 characters).</div>
                </div>
                <button class="btn btn-danger" type="submit">Submit report</button>
            </form>
        </details>
    <?php endif; ?>
</div>
<?php endif; ?>
<div id="vue-orders-root" data-page="detail" data-props="<?= e(json_encode($orderDetailProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <?= csrf_field() ?>
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to view order details.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('build/orders.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
