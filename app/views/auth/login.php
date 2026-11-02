<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$authCssFiles = glob(dirname(__DIR__, 3) . '/public/build/assets/auth-*.css');
$extraCss = !empty($authCssFiles) ? '<link rel="stylesheet" href="' . base_url('build/assets/' . basename($authCssFiles[0])) . '">' : '';
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Sign in', 'extra_css' => $extraCss]);
$flashError = flash('error');
$flashSuccess = flash('success');
$csrfToken = csrf_token();
$oldEmail = flash('old_email') ?: '';
?>

<div id="vue-auth-root"
    data-page="login"
    data-requires-vue="true"
    data-base-url="<?= e(base_url()) ?>"
    data-csrf-token="<?= e($csrfToken) ?>"
    data-error-message="<?= e($flashError ?: '') ?>"
    data-success-message="<?= e($flashSuccess ?: '') ?>"
    data-old-email="<?= e($oldEmail) ?>">
    <!-- Server-rendered fallback matching shared design system -->
    <section class="auth-page" aria-labelledby="auth-title">
        <header class="auth-page__header">
            <a href="<?= e(base_url()) ?>" class="auth-page__brand text-decoration-none" title="Back to home page">
                <i class="bi bi-box-seam" aria-hidden="true"></i>Dispatch
            </a>
            <svg class="auth-page__route" viewBox="0 0 112 24" aria-hidden="true" focusable="false">
                <path d="M12 12h88" fill="none" stroke="currentColor" stroke-width="2" />
                <circle cx="12" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
                <circle cx="56" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
                <circle cx="100" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
            </svg>
            <h1 id="auth-title" class="page-title">Sign in</h1>
            <p class="page-description">Manage your deliveries and saved addresses.</p>
        </header>

        <?php if ($flashSuccess): ?>
            <div class="alert alert-success" role="status"><?= e($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert alert-danger" role="alert"><?= e($flashError) ?></div>
        <?php endif; ?>

        <form class="auth-form" action="<?= e(base_url('login')) ?>" method="POST" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3 shared-field-wrap">
                <label for="email" class="form-label shared-label">
                    <span>Email address</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <input
                    type="email"
                    class="form-control shared-input"
                    id="email"
                    name="email"
                    value="<?= e($oldEmail) ?>"
                    required
                    autocomplete="username"
                    inputmode="email"
                    placeholder="name@example.com"
                >
            </div>
            <div class="mb-4 shared-field-wrap">
                <label for="password" class="form-label shared-label">
                    <span>Password</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <input
                    type="password"
                    class="form-control shared-input"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
                >
            </div>
            <div class="d-grid mb-4">
                <button type="submit" class="btn btn-primary py-2 fw-medium">Sign in</button>
            </div>
            <p class="text-center text-muted mb-0">
                New here? <a href="<?= e(base_url('register')) ?>" class="fw-semibold text-decoration-none">Create an account</a>
            </p>
        </form>
    </section>
</div>
<script type="module" src="<?= e(base_url('build/auth.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
