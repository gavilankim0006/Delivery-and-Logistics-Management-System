<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Forgot password']);
?>
<section class="auth-page" aria-labelledby="forgot-title">
    <header class="auth-page__header"><a href="<?= e(base_url()) ?>" class="auth-page__brand">Dispatch</a><h1 id="forgot-title" class="page-title">Forgot password</h1><p class="page-description">Enter your account email and we will send a reset link.</p></header>
    <?php if ($error = flash('error')): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form class="auth-form" action="<?= e(base_url('forgot-password')) ?>" method="POST">
        <?= csrf_field() ?>
        <label class="form-label" for="email">Email address</label>
        <input class="form-control mb-3" id="email" name="email" type="email" required maxlength="150" autocomplete="email">
        <button class="btn btn-primary w-100" type="submit">Send reset link</button>
        <p class="text-center mt-3"><a href="<?= e(base_url('login')) ?>">Back to sign in</a></p>
    </form>
</section>
<?php $this->call->view('templates/footer'); ?>
