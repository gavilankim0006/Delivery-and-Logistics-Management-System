<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$authCssFiles = glob(dirname(__DIR__, 3) . '/public/build/assets/auth-*.css');
$extraCss = !empty($authCssFiles) ? '<link rel="stylesheet" href="' . base_url('build/assets/' . basename($authCssFiles[0])) . '">' : '';
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Sign in', 'extra_css' => $extraCss]);
$flashError = flash('error');
$flashSuccess = flash('success');
$csrfToken = csrf_token();
$oldEmail = flash('old_email') ?: '';
$isApplicantLogin = ($login_context ?? '') === 'applicant';
?>

<div id="vue-auth-root"
    data-page="login"
    data-login-context="<?= $isApplicantLogin ? 'applicant' : 'customer' ?>"
    data-requires-vue="true"
    data-base-url="<?= e(base_url()) ?>"
    data-csrf-token="<?= e($csrfToken) ?>"
    data-error-message="<?= e($flashError ?: '') ?>"
    data-success-message="<?= e($flashSuccess ?: '') ?>"
    data-old-email="<?= e($oldEmail) ?>">
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to sign in.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('build/auth.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
