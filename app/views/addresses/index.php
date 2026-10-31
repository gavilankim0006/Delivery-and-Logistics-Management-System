<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Saved addresses']);
$csrfToken = csrf_token();
$addressProps = ['addresses' => $addresses ?? []];
?>
<div id="vue-address-root" data-page="list" data-requires-vue="true" data-base-url="<?= e(base_url()) ?>" data-csrf-token="<?= e($csrfToken) ?>" data-props="<?= e(json_encode($addressProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <section class="container py-4 ds-page page-shell" aria-labelledby="address-title">
        <header class="page-header ds-page-header"><div><h1 id="address-title" class="h3 fw-bold mb-1 page-title">Saved addresses</h1><p class="text-muted mb-0 page-description">Pickup and drop-off locations saved to your customer account.</p></div><a class="btn btn-primary" href="<?= e(base_url('addresses/new')) ?>">Add address</a></header>
        <?php if (empty($addresses)): ?><div class="surface ds-panel p-4" aria-hidden="true"><div class="form-control mb-3"></div><div class="form-control mb-3"></div><div class="form-control"></div></div><?php endif; ?>
    </section>
</div>
<script type="module" src="<?= e(base_url('public/build/auth.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
