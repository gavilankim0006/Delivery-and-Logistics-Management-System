<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Saved addresses']);
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$csrfToken = csrf_token();
$addressProps = [
    'addresses' => $addresses ?? [],
    'flashes' => $flashes,
];
?>
<div id="vue-address-root" data-page="list" data-requires-vue="true" data-base-url="<?= e(base_url()) ?>" data-csrf-token="<?= e($csrfToken) ?>" data-props="<?= e(json_encode($addressProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to view saved addresses.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('build/auth.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
