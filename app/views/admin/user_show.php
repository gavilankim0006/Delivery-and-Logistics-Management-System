<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$user = $user ?? [];
$roles = $roles ?? [];
$isCurrentUser = ((int)$user['id'] === (int)auth_id());
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$props = [
    'user' => $user,
    'roles' => $roles,
    'baseUrl' => base_url(),
    'csrfToken' => csrf_token(),
    'isCurrentUser' => ((int)($user['id'] ?? 0) === (int)auth_id()),
    'flashes' => $flashes,
];
?>
<?php $this->call->view('templates/header', ['page_title' => $page_title ?? 'User details']); ?>
<div id="vue-admin-root"
    data-page="user-show"
    data-props="<?= e(json_encode($props, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>">
    <noscript>
        <input id="user-role" disabled>
        <input id="user-status" disabled>
    </noscript>
</div>
<script type="module" src="<?= e(base_url('build/admin.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>