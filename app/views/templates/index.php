<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Order templates']);
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$templateProps = [
    'templates' => $templates ?? [],
    'templateCount' => (int)($template_count ?? 0),
    'templateLimit' => (int)($template_limit ?? 50),
    'baseUrl' => base_url(),
    'csrfToken' => csrf_token(),
    'flashes' => $flashes,
];
?>
<div
    id="vue-templates-root"
    data-page="templates"
    data-props="<?= e(json_encode($templateProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to view your order templates.</div></div></noscript>
</div>
<?php $ordersBundlePath = dirname(__DIR__, 2) . '/../public/build/orders.js'; $ordersBundleVersion = is_file($ordersBundlePath) ? '?v=' . (string)filemtime($ordersBundlePath) : ''; ?>
<script type="module" src="<?= e(base_url('build/orders.js' . $ordersBundleVersion)) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
