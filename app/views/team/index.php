<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Team members']);
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$teamProps = [
    'owner' => $owner ?? [],
    'members' => $members ?? [],
    'isOwner' => !empty($is_owner),
    'memberLimit' => (int)($member_limit ?? 10),
    'baseUrl' => base_url(),
    'csrfToken' => csrf_token(),
    'flashes' => $flashes,
];
?>
<div
    id="vue-team-root"
    data-page="team"
    data-props="<?= e(json_encode($teamProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to manage team members.</div></div></noscript>
</div>
<?php $dashboardBundlePath = dirname(__DIR__, 2) . '/../public/build/dashboard.js'; $dashboardBundleVersion = is_file($dashboardBundlePath) ? '?v=' . (string)filemtime($dashboardBundlePath) : ''; ?>
<script type="module" src="<?= e(base_url('build/dashboard.js' . $dashboardBundleVersion)) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
