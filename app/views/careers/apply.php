<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Apply to join Dispatch']);
?>
<div class="container page-shell py-4">
    <header class="page-header mb-4">
        <div>
            <a href="<?= e(base_url()) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to home</a>
            <h1 class="page-title mt-2">Join the Dispatch team</h1>
            <p class="page-description">Apply as a rider or dispatcher. We need your ID details and a document image for verification.</p>
        </div>
    </header>

    <?php foreach (['success', 'error', 'info'] as $type): ?>
        <?php if ($message = flash($type)): ?>
            <div class="alert alert-<?= $type === 'error' ? 'danger' : e($type) ?>" role="status"><?= e($message) ?></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <section class="surface surface-section" aria-labelledby="application-form-title">
        <h2 id="application-form-title" class="h4 mb-3">Application details</h2>
        <div class="alert alert-warning" role="note"><strong>Privacy:</strong> ID number and uploaded document are sensitive. They are visible only to authorized administrators. Submit only if comfortable sharing them for recruitment verification.</div>
        <form action="<?= e(base_url('careers/apply')) ?>" method="POST" enctype="multipart/form-data" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-12 col-md-6">
                <label class="form-label" for="full_name">Full legal name</label>
                <input class="form-control" id="full_name" name="full_name" autocomplete="name" maxlength="150" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="role_applied">Role</label>
                <select class="form-select" id="role_applied" name="role_applied" required>
                    <option value="">Choose a role</option><option value="rider">Rider</option><option value="dispatcher">Dispatcher</option>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="email">Email address</label>
                <input class="form-control" type="email" id="email" name="email" autocomplete="email" maxlength="254" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="phone">Phone number</label>
                <input class="form-control" type="tel" id="phone" name="phone" autocomplete="tel" maxlength="40" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="location">Current city or area</label>
                <input class="form-control" id="location" name="location" autocomplete="address-level2" maxlength="180" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="availability">Availability</label>
                <input class="form-control" id="availability" name="availability" maxlength="180" placeholder="Days, hours, or preferred shift" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="experience">Relevant experience</label>
                <textarea class="form-control" id="experience" name="experience" rows="4" maxlength="3000" required></textarea>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="id_type">ID type</label>
                <input class="form-control" id="id_type" name="id_type" maxlength="100" placeholder="For example, driver's license" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="id_number">ID number</label>
                <input class="form-control" id="id_number" name="id_number" maxlength="100" autocomplete="off" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="id_document">ID document image or PDF</label>
                <input class="form-control" type="file" id="id_document" name="id_document" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                <div class="form-text">JPEG, PNG, WebP, or PDF. Maximum 8 MB. Upload a clear copy for verification.</div>
            </div>
            <div class="col-12 d-flex flex-wrap align-items-center gap-3 pt-2">
                <button type="submit" class="btn btn-primary">Submit application</button>
                <span class="small text-muted">You can contact our team later if you need your application or document removed.</span>
            </div>
        </form>
    </section>
</div>
<?php $this->call->view('templates/footer'); ?>
