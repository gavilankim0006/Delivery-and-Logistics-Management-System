<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$applications = $applications ?? [];
$statusFilter = $status_filter ?? null;
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$props = [
    'applications' => $applications,
    'statusFilter' => $statusFilter,
    'baseUrl' => base_url(),
    'csrfToken' => csrf_token(),
    'flashes' => $flashes,
];
?>
<?php $this->call->view('templates/header', ['page_title' => $page_title ?? 'Job applications']); ?>
<div id="vue-admin-root"
    data-page="applications"
    data-props="<?= e(json_encode($props, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>">
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to review job applications.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('build/admin.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>