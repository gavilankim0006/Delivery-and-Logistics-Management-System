<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Sign in']);
?>

<section class="auth-page">
    <header class="auth-page__header">
        <p class="auth-page__brand"><i class="bi bi-box-seam" aria-hidden="true"></i>Dispatch</p>
        <svg class="auth-page__route" viewBox="0 0 112 24" aria-hidden="true" focusable="false">
            <path d="M12 12h88" fill="none" stroke="currentColor" stroke-width="2" />
            <circle cx="12" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
            <circle cx="56" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
            <circle cx="100" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
        </svg>
        <h1 class="page-title">Sign in</h1>
        <p class="page-description">Manage your deliveries and saved addresses.</p>
    </header>

    <form class="auth-form" action="<?= base_url('login') ?>" method="POST">
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required autofocus autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>">
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
        </div>

        <div class="d-grid mb-4">
            <button type="submit" class="btn btn-primary py-2 fw-medium">Sign in</button>
        </div>

        <p class="text-center text-muted mb-0">
            New here?
            <a href="<?= base_url('register') ?>" class="fw-semibold text-decoration-none">Create an account</a>
        </p>
    </form>
</section>

<?php $this->call->view('templates/footer'); ?>
