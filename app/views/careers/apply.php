<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Apply to join Dispatch']);
$formData = $form_data ?? [];
$fieldErrors = $errors ?? [];
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
        <h2 id="application-form-title" class="h4 mb-2">Application details</h2>
        <p class="text-muted mb-3">Signed in as <strong><?= e(auth_user()['email'] ?? '') ?></strong>. Use application email below for decision notifications. If accepted, staff account uses your sign-in email. Required fields are marked with <span class="text-danger" aria-hidden="true">*</span>.</p>
        <?php if ($fieldErrors): ?>
            <div class="alert alert-danger" role="alert" aria-labelledby="application-errors-title" tabindex="-1">
                <strong id="application-errors-title">Please review your application.</strong>
                <?php if (isset($fieldErrors['form'])): ?><p class="mb-0 mt-2"><?= e($fieldErrors['form']) ?></p><?php endif; ?>
                <ul class="mb-0 mt-2">
                    <?php foreach ($fieldErrors as $field => $message): ?>
                        <?php if ($field !== 'form'): ?><li><a href="#<?= e($field) ?>"><?= e($message) ?></a></li><?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <div class="alert alert-warning" role="note"><strong>Privacy:</strong> ID number and uploaded document are sensitive. They are visible only to authorized administrators. Submit only if comfortable sharing them for recruitment verification.</div>

        <form action="<?= e(base_url('careers/apply')) ?>" method="POST" enctype="multipart/form-data" class="row g-3">
            <?= csrf_field() ?>

            <!-- Full legal name -->
            <div class="col-12 col-md-6 shared-field-wrap">
                <label class="form-label shared-label" for="full_name">
                    <span>Full legal name</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <input
                    class="form-control shared-input <?= isset($fieldErrors['full_name']) ? 'is-invalid' : '' ?>"
                    id="full_name"
                    name="full_name"
                    type="text"
                    autocomplete="name"
                    minlength="2"
                    maxlength="150"
                    placeholder="Juan Dela Cruz"
                    value="<?= e($formData['full_name'] ?? '') ?>"
                    required
                    <?= isset($fieldErrors['full_name']) ? 'aria-invalid="true" aria-describedby="full_name-error"' : '' ?>
                >
                <?php if (isset($fieldErrors['full_name'])): ?>
                    <div class="invalid-feedback d-block mt-1" role="alert" id="full_name-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i><?= e($fieldErrors['full_name']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Role applied -->
            <div class="col-12 col-md-6 shared-field-wrap">
                <label class="form-label shared-label" for="role_applied">
                    <span>Role</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <select
                    class="form-select shared-select <?= isset($fieldErrors['role_applied']) ? 'is-invalid' : '' ?>"
                    id="role_applied"
                    name="role_applied"
                    required
                    <?= isset($fieldErrors['role_applied']) ? 'aria-invalid="true" aria-describedby="role_applied-error"' : '' ?>
                >
                    <option value="" disabled <?= empty($formData['role_applied']) ? 'selected' : '' ?>>Choose a role</option>
                    <option value="rider" <?= ($formData['role_applied'] ?? '') === 'rider' ? 'selected' : '' ?>>Rider</option>
                    <option value="dispatcher" <?= ($formData['role_applied'] ?? '') === 'dispatcher' ? 'selected' : '' ?>>Dispatcher</option>
                </select>
                <?php if (isset($fieldErrors['role_applied'])): ?>
                    <div class="invalid-feedback d-block mt-1" role="alert" id="role_applied-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i><?= e($fieldErrors['role_applied']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Email address -->
            <div class="col-12 col-md-6 shared-field-wrap">
                <label class="form-label shared-label" for="email">
                    <span>Email address for application notifications</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <input
                    class="form-control shared-input <?= isset($fieldErrors['email']) ? 'is-invalid' : '' ?>"
                    type="email"
                    id="email"
                    name="email"
                    autocomplete="email"
                    inputmode="email"
                    maxlength="254"
                    placeholder="name@example.com"
                    value="<?= e($formData['email'] ?? '') ?>"
                    required
                    <?= isset($fieldErrors['email']) ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>
                >
                <?php if (isset($fieldErrors['email'])): ?>
                    <div class="invalid-feedback d-block mt-1" role="alert" id="email-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i><?= e($fieldErrors['email']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Phone number -->
            <div class="col-12 col-md-6 shared-field-wrap">
                <label class="form-label shared-label" for="phone">
                    <span>Phone number</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <input
                    class="form-control shared-input <?= isset($fieldErrors['phone']) ? 'is-invalid' : '' ?>"
                    type="tel"
                    id="phone"
                    name="phone"
                    autocomplete="tel"
                    inputmode="tel"
                    maxlength="40"
                    placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                    value="<?= e($formData['phone'] ?? '') ?>"
                    required
                    <?= isset($fieldErrors['phone']) ? 'aria-invalid="true" aria-describedby="phone-error phone-help"' : 'aria-describedby="phone-help"' ?>
                >
                <div id="phone-help" class="form-text shared-helper-text mt-1">Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX)</div>
                <?php if (isset($fieldErrors['phone'])): ?>
                    <div class="invalid-feedback d-block mt-1" role="alert" id="phone-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i><?= e($fieldErrors['phone']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Current city or area -->
            <div class="col-12 col-md-6 shared-field-wrap">
                <label class="form-label shared-label" for="location">
                    <span>Current city or area</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <input
                    class="form-control shared-input <?= isset($fieldErrors['location']) ? 'is-invalid' : '' ?>"
                    id="location"
                    name="location"
                    type="text"
                    autocomplete="address-level2"
                    maxlength="180"
                    placeholder="e.g. Quezon City, Metro Manila"
                    value="<?= e($formData['location'] ?? '') ?>"
                    required
                    <?= isset($fieldErrors['location']) ? 'aria-invalid="true" aria-describedby="location-error"' : '' ?>
                >
                <?php if (isset($fieldErrors['location'])): ?>
                    <div class="invalid-feedback d-block mt-1" role="alert" id="location-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i><?= e($fieldErrors['location']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Availability -->
            <div class="col-12 col-md-6 shared-field-wrap">
                <label class="form-label shared-label" for="availability">
                    <span>Availability</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <select
                    class="form-select shared-input <?= isset($fieldErrors['availability']) ? 'is-invalid' : '' ?>"
                    id="availability"
                    name="availability"
                    required
                    <?= isset($fieldErrors['availability']) ? 'aria-invalid="true" aria-describedby="availability-error availability-help"' : 'aria-describedby="availability-help"' ?>
                >
                    <option value="" disabled <?= empty($formData['availability']) ? 'selected' : '' ?>>Choose your availability</option>
                    <?php foreach (['Full-time', 'Part-time', 'Weekdays', 'Weekends', 'Flexible'] as $availabilityOption): ?>
                        <option value="<?= e($availabilityOption) ?>" <?= ($formData['availability'] ?? '') === $availabilityOption ? 'selected' : '' ?>><?= e($availabilityOption) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="availability-help" class="form-text shared-helper-text mt-1">Choose the schedule that best describes when you can work.</div>
                <?php if (isset($fieldErrors['availability'])): ?>
                    <div class="invalid-feedback d-block mt-1" role="alert" id="availability-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i><?= e($fieldErrors['availability']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Relevant experience -->
            <div class="col-12 shared-field-wrap">
                <label class="form-label shared-label" for="experience">
                    <span>Relevant experience</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <textarea
                    class="form-control shared-textarea <?= isset($fieldErrors['experience']) ? 'is-invalid' : '' ?>"
                    id="experience"
                    name="experience"
                    rows="4"
                    maxlength="3000"
                    placeholder="Describe your delivery or dispatch experience, skills, and previous work..."
                    required
                    <?= isset($fieldErrors['experience']) ? 'aria-invalid="true" aria-describedby="experience-error"' : '' ?>
                ><?= e($formData['experience'] ?? '') ?></textarea>
                <?php if (isset($fieldErrors['experience'])): ?>
                    <div class="invalid-feedback d-block mt-1" role="alert" id="experience-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i><?= e($fieldErrors['experience']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ID type -->
            <div class="col-12 col-md-6 shared-field-wrap">
                <label class="form-label shared-label" for="id_type">
                    <span>ID type</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <select
                    class="form-select shared-input <?= isset($fieldErrors['id_type']) ? 'is-invalid' : '' ?>"
                    id="id_type"
                    name="id_type"
                    required
                    <?= isset($fieldErrors['id_type']) ? 'aria-invalid="true" aria-describedby="id_type-error id_type-help"' : 'aria-describedby="id_type-help"' ?>
                >
                    <option value="" disabled <?= empty($formData['id_type']) ? 'selected' : '' ?>>Choose your ID type</option>
                    <?php foreach (["Driver’s License", 'PhilID / National ID', 'UMID', 'Passport', 'Postal ID', 'Voter’s ID', 'Other government-issued ID'] as $idTypeOption): ?>
                        <option value="<?= e($idTypeOption) ?>" <?= ($formData['id_type'] ?? '') === $idTypeOption ? 'selected' : '' ?>><?= e($idTypeOption) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="id_type-help" class="form-text shared-helper-text mt-1">Select the same ID type shown on the document you upload.</div>
                <?php if (isset($fieldErrors['id_type'])): ?>
                    <div class="invalid-feedback d-block mt-1" role="alert" id="id_type-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i><?= e($fieldErrors['id_type']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ID number -->
            <div class="col-12 col-md-6 shared-field-wrap">
                <label class="form-label shared-label" for="id_number">
                    <span>ID number</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <input
                    class="form-control shared-input <?= isset($fieldErrors['id_number']) ? 'is-invalid' : '' ?>"
                    id="id_number"
                    name="id_number"
                    type="text"
                    maxlength="100"
                    autocomplete="off"
                    placeholder="Enter ID number"
                    value="<?= e($formData['id_number'] ?? '') ?>"
                    required
                    <?= isset($fieldErrors['id_number']) ? 'aria-invalid="true" aria-describedby="id_number-error"' : '' ?>
                >
                <?php if (isset($fieldErrors['id_number'])): ?>
                    <div class="invalid-feedback d-block mt-1" role="alert" id="id_number-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i><?= e($fieldErrors['id_number']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ID document upload -->
            <div class="col-12 shared-field-wrap">
                <label class="form-label shared-label" for="id_document">
                    <span>ID document image or PDF</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <input
                    class="form-control shared-input <?= isset($fieldErrors['id_document']) ? 'is-invalid' : '' ?>"
                    type="file"
                    id="id_document"
                    name="id_document"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    data-max-size-mb="8"
                    required
                    <?= isset($fieldErrors['id_document']) ? 'aria-invalid="true" aria-describedby="id_document-error id_document-help"' : 'aria-describedby="id_document-help"' ?>
                >
                <div id="id_document-help" class="form-text shared-helper-text mt-1">JPEG, PNG, WebP, or PDF. Maximum 8 MB. Upload a clear copy for verification.</div>
                <?php if (isset($fieldErrors['id_document'])): ?>
                    <div class="invalid-feedback d-block mt-1" role="alert" id="id_document-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i><?= e($fieldErrors['id_document']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="col-12 d-flex flex-wrap align-items-center gap-3 pt-2">
                <button type="submit" class="btn btn-primary">Send application</button>
                <span class="small text-muted">You can contact our team later if you need your application or document removed.</span>
            </div>
        </form>
    </section>
</div>
<?php $this->call->view('templates/footer'); ?>
