<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Fee Rates and Configuration']);
?>

<div class="container page-shell py-4">
    <header class="page-header mb-4">
        <div>
            <h1 class="page-title">Fee rates and settings</h1>
            <p class="page-description">Configure delivery pricing and customer support contact details.</p>
        </div>
    </header>

    <section class="surface surface-section" aria-label="Delivery pricing and support settings">
        <form action="<?= base_url('admin/settings') ?>" method="POST">
            <?= csrf_field() ?>

            <section class="form-section mb-4" aria-labelledby="pricing-heading">
                <h2 class="h5 mb-3" id="pricing-heading">Delivery fee calculation</h2>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="base-delivery-fee" class="form-label">Base delivery fee (PHP)</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">₱</span>
                            <input id="base-delivery-fee" type="number" step="0.01" class="form-control" name="base_delivery_fee" value="<?= e($settings['base_delivery_fee'] ?? '60.00') ?>" required aria-describedby="base-delivery-fee-help">
                        </div>
                        <div class="form-text" id="base-delivery-fee-help">Standard charge that covers the base distance.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="free-distance" class="form-label">Free distance limit (km)</label>
                        <div class="input-group">
                            <input id="free-distance" type="number" step="0.1" class="form-control" name="free_distance_km" value="<?= e($settings['free_distance_km'] ?? '2.0') ?>" required aria-describedby="free-distance-help">
                            <span class="input-group-text">km</span>
                        </div>
                        <div class="form-text" id="free-distance-help">Distance included in the base fee.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="per-km-rate" class="form-label">Distance rate per km (PHP)</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">₱</span>
                            <input id="per-km-rate" type="number" step="0.01" class="form-control" name="per_km_rate" value="<?= e($settings['per_km_rate'] ?? '12.50') ?>" required aria-describedby="per-km-rate-help">
                        </div>
                        <div class="form-text" id="per-km-rate-help">Applied to each kilometer beyond the free distance limit.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="free-weight" class="form-label">Free weight limit (kg)</label>
                        <div class="input-group">
                            <input id="free-weight" type="number" step="0.1" class="form-control" name="free_weight_kg" value="<?= e($settings['free_weight_kg'] ?? '1.0') ?>" required aria-describedby="free-weight-help">
                            <span class="input-group-text">kg</span>
                        </div>
                        <div class="form-text" id="free-weight-help">Weight included in the base fee.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="per-kg-rate" class="form-label">Weight rate per kg (PHP)</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">₱</span>
                            <input id="per-kg-rate" type="number" step="0.01" class="form-control" name="per_kg_rate" value="<?= e($settings['per_kg_rate'] ?? '5.00') ?>" required aria-describedby="per-kg-rate-help">
                        </div>
                        <div class="form-text" id="per-kg-rate-help">Applied to each kilogram beyond the free weight limit.</div>
                    </div>
                </div>
            </section>

            <section class="form-section mb-4" aria-labelledby="support-heading">
                <h2 class="h5 mb-3" id="support-heading">Customer support</h2>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="support-email" class="form-label">Support email address</label>
                        <input id="support-email" type="email" class="form-control" name="support_email" value="<?= e($settings['support_email'] ?? 'support@delivery.local') ?>" required autocomplete="email">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="support-phone" class="form-label">Support phone hotline</label>
                        <input id="support-phone" type="tel" class="form-control" name="support_phone" value="<?= e($settings['support_phone'] ?? '+63 2 8123 4567') ?>" required autocomplete="tel">
                    </div>
                </div>
            </section>

            <div class="d-flex flex-wrap justify-content-end gap-2">
                <a href="<?= base_url('admin') ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save settings</button>
            </div>
        </form>
    </section>
</div>

<?php $this->call->view('templates/footer'); ?>
