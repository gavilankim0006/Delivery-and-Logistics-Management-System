<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Add saved address']);
$formData = $form_data ?? [];
$addressProps = ['initial' => $formData, 'errors' => $errors ?? [], 'formError' => $form_error ?? null, 'addressId' => null];
$csrfToken = csrf_token();
?>
<div id="vue-address-root" data-page="form" data-requires-vue="true" data-base-url="<?= e(base_url()) ?>" data-csrf-token="<?= e($csrfToken) ?>" data-props="<?= e(json_encode($addressProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <section class="container py-4 ds-page page-shell" aria-labelledby="address-form-title"><header class="page-header"><h1 id="address-form-title" class="page-title">Add saved address</h1></header><form class="surface surface-section" aria-hidden="true"><?= csrf_field() ?><div class="form-control mb-3"></div><div class="form-control mb-3"></div><div class="form-control mb-3"></div><div class="form-control"></div></form></section>
</div>
<script type="module" src="<?= e(base_url('build/auth.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
