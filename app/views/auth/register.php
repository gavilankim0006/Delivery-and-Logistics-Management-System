<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Create an account']);
$flashError = flash('error');
$flashSuccess = flash('success');
$csrfToken = csrf_token();
?>

<div id="vue-auth-root"
    data-page="register"
    data-requires-vue="true"
    data-base-url="<?= e(base_url()) ?>"
    data-csrf-token="<?= e($csrfToken) ?>"
    data-error-message="<?= e($flashError ?: '') ?>"
    data-success-message="<?= e($flashSuccess ?: '') ?>">
</div>
<script type="module" src="<?= e(base_url('build/auth.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
