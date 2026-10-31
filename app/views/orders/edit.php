<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Edit draft order']);
$formData = $form_data ?? [];
$errors = $errors ?? [];
$rates = function_exists('get_delivery_rates') ? get_delivery_rates() : [];
$csrfField = csrf_field();
$orderFormProps = [
    'mode' => 'edit', 'orderId' => (int)$order_id, 'formData' => $formData,
    'errors' => $errors, 'formError' => $form_error ?? '', 'csrfField' => $csrfField,
    'rates' => $rates, 'baseUrl' => base_url()
];
?>
<div id="vue-orders-root" data-page="form" data-props="<?= e(json_encode($orderFormProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <?= $csrfField ?>
    <noscript><div class="container py-4"><div class="alert alert-warning" role="status">JavaScript is required to render this form.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('build/orders.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
