<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$adminRiderProps = [
    'riders' => $riders ?? [],
    'unlinkedRiderUsers' => $unlinked_rider_users ?? [],
    'statusFilter' => $status_filter,
    'baseUrl' => base_url(),
    'csrfToken' => csrf_token(),
    'flashes' => $flashes,
];
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Rider Fleet Management']);
?>
<div id="vue-admin-root" data-page="riders" data-props="<?= e(json_encode($adminRiderProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>">
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to manage the rider fleet.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('build/admin.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
