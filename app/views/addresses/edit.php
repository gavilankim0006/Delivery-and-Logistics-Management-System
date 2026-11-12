<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Edit saved address']);
$addressProps = ['initial' => $form_data ?? [], 'errors' => $errors ?? [], 'formError' => $form_error ?? null, 'addressId' => (int)($address_id ?? 0)];
$csrfToken = csrf_token();
?>
<div id="vue-address-root" data-page="form" data-requires-vue="true" data-base-url="<?= e(base_url()) ?>" data-csrf-token="<?= e($csrfToken) ?>" data-props="<?= e(json_encode($addressProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to edit this address.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('build/auth.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
