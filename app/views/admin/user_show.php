<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'User details']);
$roles = $roles ?? [];
$isCurrentUser = ((int)$user['id'] === (int)auth_id());
?>
<div class="container page-shell py-4">
    <nav aria-label="Breadcrumb" class="mb-3"><a href="<?= base_url('admin/users') ?>">Users</a><span class="text-muted"> / User #<?= (int)$user['id'] ?></span></nav>
    <header class="page-header mb-4">
        <div>
            <h1 class="page-title">User account</h1>
            <p class="page-description">Review or update account access and contact details.<?= $isCurrentUser ? ' <span class="badge bg-secondary ms-1">Your account</span>' : '' ?></p>
        </div>
    </header>

    <section class="surface surface-section" aria-labelledby="user-edit-heading">
        <h2 class="h5 mb-4" id="user-edit-heading">Edit user #<?= (int)$user['id'] ?></h2>
        <form action="<?= base_url('admin/users/' . (int)$user['id'] . '/update') ?>" method="POST">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="user-name">Full name</label>
                    <input class="form-control" id="user-name" name="name" type="text" minlength="2" maxlength="100" required value="<?= e($user['name']) ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="user-email">Email address</label>
                    <input class="form-control" id="user-email" name="email" type="email" maxlength="150" required value="<?= e($user['email']) ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="user-phone">Phone number</label>
                    <input class="form-control" id="user-phone" name="phone" type="tel" maxlength="30" value="<?= e($user['phone'] ?? '') ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="user-role">Role</label>
                    <?php if ($isCurrentUser): ?>
                        <select class="form-select" id="user-role" disabled aria-describedby="user-role-help">
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= (int)$role['id'] ?>" <?= (int)$user['role_id'] === (int)$role['id'] ? 'selected' : '' ?>><?= e(ucfirst($role['name'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="role_id" value="<?= (int)$user['role_id'] ?>">
                        <div class="form-text text-muted" id="user-role-help">You cannot change your own role to prevent losing administrator access.</div>
                    <?php else: ?>
                        <select class="form-select" id="user-role" name="role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= (int)$role['id'] ?>" <?= (int)$user['role_id'] === (int)$role['id'] ? 'selected' : '' ?>><?= e(ucfirst($role['name'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="user-status">Status</label>
                    <?php if ($isCurrentUser): ?>
                        <select class="form-select" id="user-status" disabled aria-describedby="user-status-help">
                            <option value="active" selected>Active</option>
                        </select>
                        <input type="hidden" name="status" value="active">
                        <div class="form-text text-muted" id="user-status-help">You cannot deactivate your own account while signed in.</div>
                    <?php else: ?>
                        <select class="form-select" id="user-status" name="status" required>
                            <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="user-password">New password <span class="text-muted">(leave blank to keep current)</span></label>
                    <input class="form-control" id="user-password" name="password" type="password" minlength="8" autocomplete="new-password">
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
                <button class="btn btn-primary" type="submit">Save changes</button>
                <a class="btn btn-outline-secondary" href="<?= base_url('admin/users') ?>">Back to users</a>
            </div>
        </form>
    </section>

    <section class="surface surface-section mt-4" aria-labelledby="user-account-info">
        <h2 class="h5" id="user-account-info">Account information</h2>
        <dl class="row mb-0">
            <dt class="col-sm-3">Customer company</dt><dd class="col-sm-9"><?= e($user['company_name'] ?? '—') ?></dd>
            <dt class="col-sm-3">Created</dt><dd class="col-sm-9"><?= e($user['created_at'] ?? '—') ?></dd>
        </dl>
    </section>

    <?php if ((int)$user['id'] !== (int)auth_id()): ?>
        <section class="mt-4" aria-label="Remove user account">
            <form action="<?= base_url('admin/users/' . (int)$user['id'] . '/delete') ?>" method="POST" onsubmit="return confirm('Remove this account? The account will be hidden from user lists and unable to sign in.');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger" data-busy-text="Deleting…">Delete user account</button>
            </form>
        </section>
    <?php endif; ?>
</div>
<?php $this->call->view('templates/footer'); ?>
