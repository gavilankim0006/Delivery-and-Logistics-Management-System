<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Sign In']);
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 56px; height: 56px;">
                            <i class="bi bi-box-seam-fill fs-3"></i>
                        </div>
                        <h2 class="h4 fw-bold mb-1">Sign In to SwiftLogix</h2>
                        <p class="text-muted small">Enter your credentials to access your delivery account</p>
                    </div>

                    <form action="<?= base_url('login') ?>" method="POST">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                                <input type="email" class="form-control border-start-0" id="email" name="email" placeholder="name@example.com" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <label for="password" class="form-label">Password</label>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-key text-muted"></i></span>
                                <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="••••••••" required>
                            </div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary py-2 fw-medium">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                            </button>
                        </div>

                        <div class="card bg-light border-0 p-3 mb-3 text-start small">
                            <div class="fw-bold mb-1 text-secondary">Demo Accounts (Password: <code>password123</code>):</div>
                            <ul class="mb-0 ps-3 text-muted">
                                <li><strong>Admin:</strong> <code>admin@delivery.local</code></li>
                                <li><strong>Dispatcher:</strong> <code>dispatcher@delivery.local</code></li>
                                <li><strong>Customer:</strong> <code>maria@example.com</code></li>
                            </ul>
                        </div>

                        <div class="text-center">
                            <span class="text-muted small">Need an account?</span>
                            <a href="<?= base_url('register') ?>" class="small text-decoration-none fw-semibold ms-1">Create one here</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->call->view('templates/footer'); ?>
