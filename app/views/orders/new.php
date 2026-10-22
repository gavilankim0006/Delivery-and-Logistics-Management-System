<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'New delivery']);
$formData = $form_data ?? [];
$errors = $errors ?? [];
$value = static fn(string $key, string $fallback = ''): string => e($formData[$key] ?? $fallback);
?>
<div class="container py-4 ds-page page-shell">
    <nav aria-label="Breadcrumb" class="breadcrumb-line mb-3"><a href="<?= base_url('orders') ?>">My orders</a><span aria-hidden="true"> / </span><span aria-current="page">New delivery</span></nav>
    <header class="page-header"><div><h1 class="page-title">New delivery</h1><p class="page-description">Enter both stops and parcel details. Your delivery fee is calculated securely on save.</p></div></header>

    <?php if (!empty($form_error)): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php endif; ?>
    <form method="post" action="<?= base_url('orders') ?>" novalidate>
        <?= csrf_field() ?>
        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <div class="surface surface-section">
                    <section class="mb-4">
                        <h2 class="h5 mb-3">Pickup</h2>
                        <?php foreach ([
                            'pickup_address' => ['Address', 'text', 'e.g. Makati CBD, Ayala Avenue'],
                            'pickup_contact_name' => ['Contact name', 'text', 'Name of the person handing over the parcel'],
                            'pickup_contact_phone' => ['Contact phone', 'tel', '+639171234567']
                        ] as $field => [$label, $type, $placeholder]): ?>
                            <div class="mb-3">
                                <label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
                                <input class="form-control <?= isset($errors[$field]) ? 'is-invalid' : '' ?>" id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= e($type) ?>" maxlength="<?= $field === 'pickup_address' ? 500 : ($field === 'pickup_contact_name' ? 100 : 30) ?>" value="<?= $value($field, $field === 'pickup_contact_name' ? ($user['name'] ?? '') : ($field === 'pickup_contact_phone' ? ($user['phone'] ?? '') : '')) ?>" placeholder="<?= e($placeholder) ?>" required>
                                <?php if (isset($errors[$field])): ?><div class="invalid-feedback"><?= e($errors[$field]) ?></div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div><label class="form-label" for="pickup_notes">Pickup notes <span class="text-muted">(Optional)</span></label><textarea class="form-control <?= isset($errors['pickup_notes']) ? 'is-invalid' : '' ?>" id="pickup_notes" name="pickup_notes" rows="2" maxlength="1000"><?= $value('pickup_notes') ?></textarea><?php if (isset($errors['pickup_notes'])): ?><div class="invalid-feedback"><?= e($errors['pickup_notes']) ?></div><?php endif; ?></div>
                    </section>

                    <section class="border-top pt-4 mb-4">
                        <h2 class="h5 mb-3">Drop-off</h2>
                        <?php foreach ([
                            'dropoff_address' => ['Address', 'text', 'e.g. BGC High Street, Taguig'],
                            'dropoff_contact_name' => ['Contact name', 'text', 'Name of the person receiving the parcel'],
                            'dropoff_contact_phone' => ['Contact phone', 'tel', '+639175551234']
                        ] as $field => [$label, $type, $placeholder]): ?>
                            <div class="mb-3">
                                <label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
                                <input class="form-control <?= isset($errors[$field]) ? 'is-invalid' : '' ?>" id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= e($type) ?>" maxlength="<?= $field === 'dropoff_address' ? 500 : ($field === 'dropoff_contact_name' ? 100 : 30) ?>" value="<?= $value($field) ?>" placeholder="<?= e($placeholder) ?>" required>
                                <?php if (isset($errors[$field])): ?><div class="invalid-feedback"><?= e($errors[$field]) ?></div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div><label class="form-label" for="dropoff_notes">Drop-off notes <span class="text-muted">(Optional)</span></label><textarea class="form-control <?= isset($errors['dropoff_notes']) ? 'is-invalid' : '' ?>" id="dropoff_notes" name="dropoff_notes" rows="2" maxlength="1000"><?= $value('dropoff_notes') ?></textarea><?php if (isset($errors['dropoff_notes'])): ?><div class="invalid-feedback"><?= e($errors['dropoff_notes']) ?></div><?php endif; ?></div>
                    </section>

                    <section class="border-top pt-4">
                        <h2 class="h5 mb-3">Parcel</h2>
                        <div class="mb-3"><label class="form-label" for="parcel_description">Description</label><input class="form-control <?= isset($errors['parcel_description']) ? 'is-invalid' : '' ?>" id="parcel_description" name="parcel_description" maxlength="255" value="<?= $value('parcel_description') ?>" required><?php if (isset($errors['parcel_description'])): ?><div class="invalid-feedback"><?= e($errors['parcel_description']) ?></div><?php endif; ?></div>
                        <div class="row g-3">
                            <div class="col-12 col-sm-4"><label class="form-label" for="parcel_weight">Weight (kg)</label><input class="form-control <?= isset($errors['parcel_weight']) ? 'is-invalid' : '' ?>" id="parcel_weight" name="parcel_weight" type="number" min="0.01" max="1000" step="0.01" value="<?= $value('parcel_weight', '1.00') ?>" required><?php if (isset($errors['parcel_weight'])): ?><div class="invalid-feedback"><?= e($errors['parcel_weight']) ?></div><?php endif; ?></div>
                            <div class="col-12 col-sm-4"><label class="form-label" for="parcel_size">Size</label><select class="form-select <?= isset($errors['parcel_size']) ? 'is-invalid' : '' ?>" id="parcel_size" name="parcel_size" required><option value="">Choose</option><?php foreach (['S', 'M', 'L'] as $size): ?><option value="<?= $size ?>" <?= ($formData['parcel_size'] ?? 'M') === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select><?php if (isset($errors['parcel_size'])): ?><div class="invalid-feedback"><?= e($errors['parcel_size']) ?></div><?php endif; ?></div>
                            <div class="col-12 col-sm-4 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" id="is_fragile" name="is_fragile" type="checkbox" value="1" <?= !empty($formData['is_fragile']) ? 'checked' : '' ?>><label class="form-check-label" for="is_fragile">Fragile parcel</label></div></div>
                        </div>
                    </section>
                </div>
            </div>

            <aside class="col-12 col-lg-5">
                <section class="surface surface-section mb-3">
                        <h2 class="h5">Route estimate</h2>
                        <p class="text-muted">Until map routing is enabled, enter the estimated road distance. The fee is calculated from current rates when you save.</p>
                        <div class="mb-3"><label class="form-label" for="distance_km">Distance (km)</label><input class="form-control <?= isset($errors['distance_km']) ? 'is-invalid' : '' ?>" id="distance_km" name="distance_km" type="number" min="0.01" max="10000" step="0.01" value="<?= $value('distance_km') ?>" required><?php if (isset($errors['distance_km'])): ?><div class="invalid-feedback"><?= e($errors['distance_km']) ?></div><?php endif; ?></div>
                        <div class="route-preview" role="img" aria-label="Map preview will be available with route integration">
                            <div><svg class="route-preview__line" viewBox="0 0 112 24" aria-hidden="true"><path d="M12 12h88"/><circle cx="12" cy="12" r="5"/><circle cx="100" cy="12" r="5"/></svg><div>Map preview will appear here</div></div>
                        </div>
                </section>
                <div class="d-flex flex-wrap justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="<?= base_url('orders') ?>">Cancel</a>
                    <button class="btn btn-primary" type="submit">Save draft order</button>
                </div>
            </aside>
        </div>
    </form>
</div>
<?php $this->call->view('templates/footer'); ?>
