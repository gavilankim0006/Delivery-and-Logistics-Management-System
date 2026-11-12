<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$token = $token ?? ($_GET['token'] ?? '');
$validToken = isset($token_valid) ? (bool)$token_valid : false;
$csrfToken = csrf_token();
?>
<?php $this->call->view('templates/header', ['page_title' => $page_title ?? 'Reset password']); ?>
<div id="vue-auth-root"
    data-page="reset-password"
    data-base-url="<?= e(base_url()) ?>"
    data-csrf-token="<?= e($csrfToken) ?>"
    data-token="<?= e($token) ?>"
    data-token-valid="<?= $validToken ? '1' : '0' ?>">
</div>
<script type="module" src="<?= e(base_url('build/auth.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>