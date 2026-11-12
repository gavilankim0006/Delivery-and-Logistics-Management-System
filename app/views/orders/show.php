<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Order details']);
$currentUser = auth_user() ?: [];
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$orderDetailProps = [
    'order' => $order ?? [],
    'role' => $role ?? '',
    'controlPassActive' => !empty($control_pass_active),
    'riderLocationEnabled' => !empty($rider_location_enabled),
    'paymentNotice' => in_array($_GET['payment'] ?? '', ['return', 'cancelled'], true) ? $_GET['payment'] : '',
    'demoPaymentSimulationEnabled' => !empty($demo_payment_simulation_enabled),
    'currentUser' => array_intersect_key($currentUser, array_flip(['id', 'email', 'phone', 'delivery_status_sms_opt_in', 'push_opt_in', 'email_notifications_opt_in', 'fcm_token'])),
    'csrfToken' => csrf_token(),
    'baseUrl' => base_url(),
    'flashes' => $flashes,
];
?>
<div id="vue-orders-root" data-page="detail" data-props="<?= e(json_encode($orderDetailProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <?= csrf_field() ?>
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to view order details.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('build/orders.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
