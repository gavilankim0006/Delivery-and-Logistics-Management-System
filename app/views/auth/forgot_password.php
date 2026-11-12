<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$csrfToken = csrf_token();
?>
<?php $this->call->view('templates/header', ['page_title' => $page_title ?? 'Forgot password']); ?>
<div id="vue-auth-root"
    data-page="forgot-password"
    data-base-url="<?= e(base_url()) ?>"
    data-csrf-token="<?= e($csrfToken) ?>">
</div>
<script type="module" src="<?= e(base_url('build/auth.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>