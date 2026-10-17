<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Fee Rates & Configuration']);
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="bi bi-sliders text-primary me-2"></i>Delivery Pricing & Operational Settings
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="<?= base_url('admin/settings') ?>" method="POST">
                        <?= csrf_field() ?>

                        <h6 class="fw-bold text-primary mb-3">Delivery Fee Calculation Engine</h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Base Delivery Fee (PHP)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" step="0.01" class="form-control" name="base_delivery_fee" value="<?= e($settings['base_delivery_fee'] ?? '60.00') ?>" required>
                                </div>
                                <span class="text-muted small">Standard flat charge for base distance.</span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Free Distance Limit (km)</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" class="form-control" name="free_distance_km" value="<?= e($settings['free_distance_km'] ?? '2.0') ?>" required>
                                    <span class="input-group-text">km</span>
                                </div>
                                <span class="text-muted small">Distance included in the base fee.</span>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Distance Rate per KM (PHP)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" step="0.01" class="form-control" name="per_km_rate" value="<?= e($settings['per_km_rate'] ?? '12.50') ?>" required>
                                </div>
                                <span class="text-muted small">Applied for each kilometer past free limit.</span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Free Weight Limit (kg)</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" class="form-control" name="free_weight_kg" value="<?= e($settings['free_weight_kg'] ?? '1.0') ?>" required>
                                    <span class="input-group-text">kg</span>
                                </div>
                                <span class="text-muted small">Weight included in the base fee.</span>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Weight Rate per KG (PHP)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" step="0.01" class="form-control" name="per_kg_rate" value="<?= e($settings['per_kg_rate'] ?? '5.00') ?>" required>
                                </div>
                                <span class="text-muted small">Applied for each kilogram past free limit.</span>
                            </div>
                        </div>

                        <hr class="my-4">
                        <h6 class="fw-bold text-primary mb-3">Customer Support Contact Info</h6>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Support Email Address</label>
                                <input type="email" class="form-control" name="support_email" value="<?= e($settings['support_email'] ?? 'support@delivery.local') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Support Phone Hotline</label>
                                <input type="text" class="form-control" name="support_phone" value="<?= e($settings['support_phone'] ?? '+63 2 8123 4567') ?>" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="<?= base_url('admin') ?>" class="btn btn-outline-secondary">Back to Dashboard</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-1"></i> Save Rates & Config
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->call->view('templates/footer'); ?>
