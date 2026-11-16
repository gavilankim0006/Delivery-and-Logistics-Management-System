<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Dashboard']);
$currentUser = auth_user() ?: [];
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$dashboardProps = [
    'userName' => (string)($currentUser['name'] ?? 'there'),
    'stats' => $stats ?? [],
    'activeOrders' => $active_orders ?? [],
    'recentOrders' => $recent_orders ?? [],
    'baseUrl' => base_url(),
    'csrfToken' => csrf_token(),
    'flashes' => $flashes,
];
?>
<div
    id="vue-dashboard-root"
    data-page="dashboard"
    data-props="<?= e(json_encode($dashboardProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to view your dashboard.</div></div></noscript>
</div>
<?php $dashboardBundlePath = dirname(__DIR__, 2) . '/../public/build/dashboard.js'; $dashboardBundleVersion = is_file($dashboardBundlePath) ? '?v=' . (string)filemtime($dashboardBundlePath) : ''; ?>
<script type="module" src="<?= e(base_url('build/dashboard.js' . $dashboardBundleVersion)) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
