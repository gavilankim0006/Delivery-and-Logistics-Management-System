<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Register Customer Account']);
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 56px; height: 56px;">
                            <i class="bi bi-person-plus-fill fs-3"></i>
                        </div>
                        <h2 class="h4 fw-bold mb-1">Create Customer Account</h2>
                        <p class="text-muted small">Send packages and book express deliveries easily</p>
                    </div>

                    <form action="<?= base_url('register') ?>" method="POST">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name / Primary Contact <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="e.g. Juan Dela Cruz" required value="<?= e($_POST['name'] ?? '') ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="name@domain.com" required value="<?= e($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Mobile Phone (PH) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="phone" name="phone" placeholder="+639171234567" required value="<?= e($_POST['phone'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="company_name" class="form-label">Company / Business Name <span class="text-muted small">(Optional)</span></label>
                            <input type="text" class="form-control" id="company_name" name="company_name" placeholder="e.g. Acme Trading Corp" value="<?= e($_POST['company_name'] ?? '') ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="At least 8 chars" required minlength="8">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password_confirm" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Repeat password" required minlength="8">
                            </div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary py-2 fw-medium">
                                <i class="bi bi-check2-circle me-1"></i> Register Account
                            </button>
                        </div>

                        <div class="text-center">
                            <span class="text-muted small">Already have an account?</span>
                            <a href="<?= base_url('login') ?>" class="small text-decoration-none fw-semibold ms-1">Sign in here</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->call->view('templates/footer'); ?>
