<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Delivery control pass']);
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$controlProps = [
    'order' => $order ?? [],
    'token' => $token ?? '',
    'eta_minutes' => $eta_minutes ?? null,
    'baseUrl' => base_url(),
    'csrfToken' => csrf_token(),
    'flashes' => $flashes,
];
?>
<div id="vue-delivery-control-root" data-props="<?= e(json_encode($controlProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>"></div>
<script type="module" src="<?= e(base_url('build/delivery_control.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
