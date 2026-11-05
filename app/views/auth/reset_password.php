<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Reset password']);
?>
<section class="auth-page" aria-labelledby="reset-title">
    <header class="auth-page__header"><a href="<?= e(base_url()) ?>" class="auth-page__brand">Dispatch</a><h1 id="reset-title" class="page-title">Reset password</h1><p class="page-description">Choose a new password for your account.</p></header>
    <?php if ($error = flash('error')): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if (!empty($token_valid)): ?>
    <form class="auth-form" action="<?= e(base_url('reset-password')) ?>" method="POST">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
        <label class="form-label" for="password">New password (at least 8 characters)</label>
        <input class="form-control mb-3" id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
        <button class="btn btn-primary w-100" type="submit">Update password</button>
    </form>
    <?php else: ?>
    <div class="alert alert-danger" role="alert">This password reset link is invalid or expired. <a href="<?= e(base_url('forgot-password')) ?>">Request another link</a>.</div>
    <?php endif; ?>
</section>
<?php $this->call->view('templates/footer'); ?>
