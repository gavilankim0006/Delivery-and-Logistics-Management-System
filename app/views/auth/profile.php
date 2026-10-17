<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'My Profile']);
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="bi bi-person-gear text-primary me-2"></i>Account Profile
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="<?= base_url('profile') ?>" method="POST">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label text-muted">Role</label>
                            <div>
                                <span class="badge bg-primary px-3 py-2 text-uppercase" style="letter-spacing: 0.05em;">
                                    <?= e($user['role_name'] ?? 'Customer') ?>
                                </span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address (Read-Only)</label>
                            <input type="email" class="form-control bg-light" id="email" value="<?= e($user['email']) ?>" readonly disabled>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= e($user['name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>">
                        </div>

                        <hr class="my-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-primary"></i>Change Password (Optional)</h6>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Leave blank to keep current">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password_confirm" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Leave blank to keep current">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <a href="<?= base_url() ?>" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->call->view('templates/footer'); ?>
