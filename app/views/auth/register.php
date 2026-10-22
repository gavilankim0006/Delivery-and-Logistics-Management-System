<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Create an account']);
?>

<section class="auth-page auth-page--register">
    <header class="auth-page__header">
        <p class="auth-page__brand"><i class="bi bi-box-seam" aria-hidden="true"></i>Dispatch</p>
        <svg class="auth-page__route" viewBox="0 0 112 24" aria-hidden="true" focusable="false">
            <path d="M12 12h88" fill="none" stroke="currentColor" stroke-width="2" />
            <circle cx="12" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
            <circle cx="56" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
            <circle cx="100" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
        </svg>
        <h1 class="page-title">Create an account</h1>
        <p class="page-description">Set up a customer account to arrange your deliveries.</p>
    </header>

    <form class="auth-form" action="<?= base_url('register') ?>" method="POST">
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="name" class="form-label">Full name or primary contact <span class="text-danger" aria-hidden="true">*</span></label>
            <input type="text" class="form-control" id="name" name="name" placeholder="e.g. Juan Dela Cruz" required autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>">
        </div>

        <div class="row">
            <div class="col-12 col-md-6 mb-3">
                <label for="email" class="form-label">Email address <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="col-12 col-md-6 mb-3">
                <label for="phone" class="form-label">Mobile phone <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="tel" class="form-control" id="phone" name="phone" placeholder="+639171234567" required autocomplete="tel" value="<?= e($_POST['phone'] ?? '') ?>">
            </div>
        </div>

        <div class="mb-3">
            <label for="company_name" class="form-label">Company or business name <span class="text-muted small">(Optional)</span></label>
            <input type="text" class="form-control" id="company_name" name="company_name" placeholder="e.g. Acme Trading Corp" autocomplete="organization" value="<?= e($_POST['company_name'] ?? '') ?>">
        </div>

        <div class="row">
            <div class="col-12 col-md-6 mb-3">
                <label for="password" class="form-label">Password <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="password" class="form-control" id="password" name="password" placeholder="At least 8 characters" required minlength="8" autocomplete="new-password">
            </div>
            <div class="col-12 col-md-6 mb-3">
                <label for="password_confirm" class="form-label">Confirm password <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Enter the password again" required minlength="8" autocomplete="new-password">
            </div>
        </div>

        <div class="d-grid mb-4">
            <button type="submit" class="btn btn-primary py-2 fw-medium">Create account</button>
        </div>

        <p class="text-center text-muted mb-0">
            Already have an account?
            <a href="<?= base_url('login') ?>" class="fw-semibold text-decoration-none">Sign in</a>
        </p>
    </form>
</section>

<?php $this->call->view('templates/footer'); ?>
