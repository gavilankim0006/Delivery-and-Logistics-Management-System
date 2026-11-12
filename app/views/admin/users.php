<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$adminUserProps = [
    'users' => $users ?? [],
    'roles' => $roles ?? [],
    'total' => (int)($total ?? 0),
    'search' => $search ?? '',
    'roleFilter' => $role_filter,
    'page' => (int)($page ?? 1),
    'limit' => (int)($limit ?? 20),
    'baseUrl' => base_url(),
    'csrfToken' => csrf_token(),
    'flashes' => $flashes,
    'currentUserId' => auth_id(),
];
$this->call->view('templates/header', ['page_title' => $page_title ?? 'User Management']);
?>
<div id="vue-admin-root" data-page="users" data-props="<?= e(json_encode($adminUserProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>">
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to manage users.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('build/admin.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
