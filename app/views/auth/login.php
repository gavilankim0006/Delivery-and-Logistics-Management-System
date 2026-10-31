<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Sign in']);
$flashError = flash('error');
$flashSuccess = flash('success');
$csrfToken = csrf_token();
?>

<div id="vue-auth-root"
    data-page="login"
    data-requires-vue="true"
    data-base-url="<?= e(base_url()) ?>"
    data-csrf-token="<?= e($csrfToken) ?>"
    data-error-message="<?= e($flashError ?: '') ?>"
    data-success-message="<?= e($flashSuccess ?: '') ?>">
    <section class="auth-page" aria-labelledby="auth-title">
        <header class="auth-page__header"><p class="auth-page__brand"><i class="bi bi-box-seam" aria-hidden="true"></i>Dispatch</p><h1 id="auth-title" class="page-title">Sign in</h1><p class="page-description">Manage your deliveries and saved addresses.</p></header>
        <form class="auth-form" action="<?= e(base_url('login')) ?>" method="POST">
            <?= csrf_field() ?>
            <div class="mb-3"><label for="email" class="form-label">Email address</label><input type="email" class="form-control" id="email" name="email" required autocomplete="email"></div>
            <div class="mb-4"><label for="password" class="form-label">Password</label><input type="password" class="form-control" id="password" name="password" required autocomplete="current-password"></div>
            <button type="submit" class="btn btn-primary">Sign in</button>
            <p class="text-center text-muted mt-4">New here? <a href="<?= e(base_url('register')) ?>">Create an account</a></p>
        </form>
    </section>
</div>
<script type="module" src="<?= e(base_url('public/build/auth.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
