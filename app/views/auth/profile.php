<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'My profile']);
?>

<div class="container py-4 ds-page page-shell">
    <div class="profile-page">
        <header class="page-header">
            <div>
                <h1 class="page-title">My profile</h1>
                <p class="page-description">Manage the contact details associated with your account.</p>
            </div>
        </header>

        <form class="surface surface-section" action="<?= base_url('profile') ?>" method="POST">
            <?= csrf_field() ?>

            <section class="account-summary" aria-label="Account details">
                <div><span class="form-label">Role</span><p class="account-summary__value mb-0"><?= e($user['role_name'] ?? 'Customer') ?></p></div>
                <div><span class="form-label">Email address</span><p class="account-summary__value mb-0"><?= e($user['email']) ?></p><p class="form-text mb-0">This email cannot be changed here.</p></div>
            </section>

            <div class="form-section">
                <div class="mb-3">
                    <label for="name" class="form-label">Full name</label>
                    <input type="text" class="form-control" id="name" name="name" autocomplete="name" value="<?= e($user['name']) ?>" required>
                </div>

                <div class="mb-4">
                    <label for="phone" class="form-label">Phone number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" autocomplete="tel" value="<?= e($user['phone'] ?? '') ?>">
                </div>

                <hr class="my-4">
                <h2 class="h5 fw-bold mb-3">Change password <span class="text-muted fw-normal">(Optional)</span></h2>
                <p class="text-muted">Leave both password fields blank to keep your current password.</p>

                <div class="row">
                    <div class="col-12 col-md-6 mb-3">
                        <label for="password" class="form-label">New password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="At least 8 characters" minlength="8" autocomplete="new-password">
                    </div>
                    <div class="col-12 col-md-6 mb-3">
                        <label for="password_confirm" class="form-label">Confirm new password</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Enter the password again" autocomplete="new-password">
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                    <a href="<?= base_url() ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4">Save changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php $this->call->view('templates/footer'); ?>
