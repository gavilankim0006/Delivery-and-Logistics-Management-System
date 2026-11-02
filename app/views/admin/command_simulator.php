<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Delivery command simulator']);
$form = $form_data ?? [];
?>

<div class="container page-shell py-4">
    <header class="page-header mb-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-title">Delivery command simulator</h1>
            <p class="page-description">Explore fleet capacity, courier workload, and pricing scenarios for a saved service zone.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= base_url('admin/service-areas') ?>">
            <i class="bi bi-map me-1"></i>Manage zones
        </a>
    </header>

    <div class="alert alert-info">
        <i class="bi bi-info-circle-fill me-2"></i>
        Estimates only. This tool uses drop-off coordinates to select zone orders and a rider-throughput model; it does not model live traffic, third-party courier capacity, or dynamic demand surges. Orders missing coordinates are omitted from zone totals.
    </div>

    <?php if (empty($areas)): ?>
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Create an active polygon service area before running a simulation scenario.
        </div>
    <?php elseif ($simulation_error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <?= e($simulation_error) ?>
        </div>
    <?php endif; ?>

    <form action="<?= base_url('admin/command-simulator') ?>" method="POST" class="surface p-3 p-md-4" id="command-simulator-form" novalidate>
        <?= csrf_field() ?>

        <div class="row g-3">
            <!-- Service Area -->
            <div class="col-12 col-md-6 shared-field-wrap">
                <label class="form-label shared-label" for="area-id">
                    <span>Service area</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <select
                    class="form-select"
                    id="area-id"
                    name="area_id"
                    required
                    onchange="if(this.value){window.location.href='<?= base_url('admin/command-simulator') ?>?area_id='+encodeURIComponent(this.value);}"
                    aria-describedby="area-id-help"
                >
                    <?php if (empty($areas)): ?>
                        <option value="" disabled selected>No service areas</option>
                    <?php else: ?>
                        <option value="" disabled <?= empty($selected_area_id) ? 'selected' : '' ?>>-- Select service area --</option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?= (int)$area['id'] ?>" <?= (int)$selected_area_id === (int)$area['id'] ? 'selected' : '' ?>>
                                <?= e($area['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <div class="form-text shared-helper-text" id="area-id-help">Select an active polygon zone to model workload and revenue.</div>
            </div>

            <!-- Projected Orders -->
            <div class="col-6 col-md-3 shared-field-wrap">
                <label class="form-label shared-label" for="projected-orders">
                    <span>Projected orders</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <div class="input-group">
                    <input
                        class="form-control"
                        id="projected-orders"
                        name="projected_orders"
                        type="number"
                        min="1"
                        max="5000"
                        step="1"
                        value="<?= e($form['projected_orders'] ?? max(1, count($zone_data['orders'] ?? []))) ?>"
                        required
                        aria-describedby="projected-orders-help"
                    >
                    <span class="input-group-text">orders</span>
                </div>
                <div class="form-text shared-helper-text" id="projected-orders-help">Expected delivery volume in this scenario.</div>
            </div>

            <!-- Scenario Duration -->
            <div class="col-6 col-md-3 shared-field-wrap">
                <label class="form-label shared-label" for="scenario-hours">
                    <span>Scenario duration</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <div class="input-group">
                    <input
                        class="form-control"
                        id="scenario-hours"
                        name="scenario_hours"
                        type="number"
                        min="1"
                        max="24"
                        step="1"
                        value="<?= e($form['scenario_hours'] ?? '8') ?>"
                        required
                        aria-describedby="scenario-hours-help"
                    >
                    <span class="input-group-text">hrs</span>
                </div>
                <div class="form-text shared-helper-text" id="scenario-hours-help">Simulation shift window in whole hours.</div>
            </div>

            <!-- Average Distance -->
            <div class="col-6 col-md-3 shared-field-wrap">
                <label class="form-label shared-label" for="average-distance">
                    <span>Avg distance</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <div class="input-group">
                    <input
                        class="form-control"
                        id="average-distance"
                        name="average_distance_km"
                        type="number"
                        min="0.1"
                        max="1000"
                        step="0.1"
                        value="<?= e($form['average_distance_km'] ?? '8') ?>"
                        required
                        aria-describedby="average-distance-help"
                    >
                    <span class="input-group-text">km</span>
                </div>
                <div class="form-text shared-helper-text" id="average-distance-help">Mean transit distance per delivery.</div>
            </div>

            <!-- Average Weight -->
            <div class="col-6 col-md-3 shared-field-wrap">
                <label class="form-label shared-label" for="average-weight">
                    <span>Avg parcel weight</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <div class="input-group">
                    <input
                        class="form-control"
                        id="average-weight"
                        name="average_weight_kg"
                        type="number"
                        min="0.1"
                        max="1000"
                        step="0.1"
                        value="<?= e($form['average_weight_kg'] ?? '2') ?>"
                        required
                        aria-describedby="average-weight-help"
                    >
                    <span class="input-group-text">kg</span>
                </div>
                <div class="form-text shared-helper-text" id="average-weight-help">Estimated parcel package weight.</div>
            </div>

            <!-- Riders Available -->
            <div class="col-6 col-md-3 shared-field-wrap">
                <label class="form-label shared-label" for="riders">
                    <span>Riders available</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <div class="input-group">
                    <input
                        class="form-control"
                        id="riders"
                        name="riders"
                        type="number"
                        min="1"
                        max="500"
                        step="1"
                        value="<?= e($form['riders'] ?? max(1, $available_riders)) ?>"
                        required
                        aria-describedby="riders-help"
                    >
                    <span class="input-group-text">riders</span>
                </div>
                <div class="form-text shared-helper-text" id="riders-help">Active couriers allocated to this zone.</div>
            </div>

            <!-- Deliveries per Rider per Hour (Throughput) -->
            <div class="col-6 col-md-3 shared-field-wrap">
                <label class="form-label shared-label" for="throughput">
                    <span>Rider throughput</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                </label>
                <div class="input-group">
                    <input
                        class="form-control"
                        id="throughput"
                        name="deliveries_per_rider_hour"
                        type="number"
                        min="0.1"
                        max="20"
                        step="0.1"
                        value="<?= e($form['deliveries_per_rider_hour'] ?? '1.0') ?>"
                        required
                        aria-describedby="throughput-help"
                    >
                    <span class="input-group-text">deliv/hr</span>
                </div>
                <div class="form-text shared-helper-text" id="throughput-help">Deliveries completed per rider per hour.</div>
            </div>
        </div>

        <!-- Proposed Zone Fee Rates Accordion -->
        <details class="mt-4 border rounded p-3 bg-light">
            <summary class="fw-semibold text-primary" style="cursor: pointer;">
                <i class="bi bi-sliders me-1"></i>Proposed zone fee rates (optional override)
            </summary>
            <p class="small text-muted mt-2 mb-3">
                Baseline rates default to the active zone or system-wide settings; modifications here apply only to this what-if scenario.
            </p>
            <div class="row g-3">
                <!-- Base Fee -->
                <div class="col-12 col-sm-6 col-lg shared-field-wrap">
                    <label class="form-label shared-label small" for="sim-base_delivery_fee">
                        <span>Base fee</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">₱</span>
                        <input
                            class="form-control"
                            id="sim-base_delivery_fee"
                            name="base_delivery_fee"
                            type="number"
                            min="0"
                            max="100000"
                            step="0.01"
                            value="<?= e($form['base_delivery_fee'] ?? ($area_rates['base_delivery_fee'] ?? $baseline_rates['base_delivery_fee'])) ?>"
                            required
                        >
                        <span class="input-group-text">PHP</span>
                    </div>
                    <div class="form-text small">Zone base rate.</div>
                </div>

                <!-- Free Distance -->
                <div class="col-12 col-sm-6 col-lg shared-field-wrap">
                    <label class="form-label shared-label small" for="sim-free_distance_km">
                        <span>Free distance</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <input
                            class="form-control"
                            id="sim-free_distance_km"
                            name="free_distance_km"
                            type="number"
                            min="0"
                            max="10000"
                            step="0.1"
                            value="<?= e($form['free_distance_km'] ?? ($area_rates['free_distance_km'] ?? $baseline_rates['free_distance_km'])) ?>"
                            required
                        >
                        <span class="input-group-text">km</span>
                    </div>
                    <div class="form-text small">Included distance.</div>
                </div>

                <!-- Rate per km -->
                <div class="col-12 col-sm-6 col-lg shared-field-wrap">
                    <label class="form-label shared-label small" for="sim-per_km_rate">
                        <span>Rate per km</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">₱</span>
                        <input
                            class="form-control"
                            id="sim-per_km_rate"
                            name="per_km_rate"
                            type="number"
                            min="0"
                            max="10000"
                            step="0.01"
                            value="<?= e($form['per_km_rate'] ?? ($area_rates['per_km_rate'] ?? $baseline_rates['per_km_rate'])) ?>"
                            required
                        >
                        <span class="input-group-text">PHP</span>
                    </div>
                    <div class="form-text small">Per km charge.</div>
                </div>

                <!-- Free Weight -->
                <div class="col-12 col-sm-6 col-lg shared-field-wrap">
                    <label class="form-label shared-label small" for="sim-free_weight_kg">
                        <span>Free weight</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <input
                            class="form-control"
                            id="sim-free_weight_kg"
                            name="free_weight_kg"
                            type="number"
                            min="0"
                            max="1000"
                            step="0.1"
                            value="<?= e($form['free_weight_kg'] ?? ($area_rates['free_weight_kg'] ?? $baseline_rates['free_weight_kg'])) ?>"
                            required
                        >
                        <span class="input-group-text">kg</span>
                    </div>
                    <div class="form-text small">Included weight.</div>
                </div>

                <!-- Rate per kg -->
                <div class="col-12 col-sm-6 col-lg shared-field-wrap">
                    <label class="form-label shared-label small" for="sim-per_kg_rate">
                        <span>Rate per kg</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">₱</span>
                        <input
                            class="form-control"
                            id="sim-per_kg_rate"
                            name="per_kg_rate"
                            type="number"
                            min="0"
                            max="10000"
                            step="0.01"
                            value="<?= e($form['per_kg_rate'] ?? ($area_rates['per_kg_rate'] ?? $baseline_rates['per_kg_rate'])) ?>"
                            required
                        >
                        <span class="input-group-text">PHP</span>
                    </div>
                    <div class="form-text small">Per kg charge.</div>
                </div>
            </div>
        </details>

        <div class="d-flex justify-content-end mt-4">
            <button
                type="submit"
                class="btn btn-primary fw-semibold"
                <?= empty($areas) ? 'disabled' : '' ?>
            >
                <i class="bi bi-play-circle me-1"></i>Run what-if scenario
            </button>
        </div>
    </form>

    <?php if ($result): ?>
        <section class="surface p-3 p-md-4 mt-4" aria-labelledby="scenario-results">
            <h2 class="h5" id="scenario-results">
                <i class="bi bi-graph-up-arrow text-primary me-2"></i>Scenario results
            </h2>
            <p class="text-muted">
                Zone <strong><?= e($zone_data['zone']['name'] ?? '') ?></strong> &middot;
                <?= (int)$result['zone_orders'] ?> existing orders in zone &middot;
                <?= (int)$result['missing_coordinates'] ?> orders skipped for missing coordinates.
            </p>

            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <div class="border rounded p-3 h-100 bg-light">
                        <div class="small text-muted mb-1">Estimated capacity</div>
                        <strong class="fs-5"><?= (int)$result['capacity'] ?> deliveries</strong>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="border rounded p-3 h-100 bg-light">
                        <div class="small text-muted mb-1">Projected backlog</div>
                        <strong class="fs-5 <?= (int)$result['projected_backlog'] > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= (int)$result['projected_backlog'] ?> orders
                        </strong>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="border rounded p-3 h-100 bg-light">
                        <div class="small text-muted mb-1">Baseline average fee</div>
                        <strong class="fs-5 money">₱<?= number_format($result['baseline_fee'], 2) ?></strong>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="border rounded p-3 h-100 bg-light">
                        <div class="small text-muted mb-1">Proposed average fee</div>
                        <strong class="fs-5 money">₱<?= number_format($result['scenario_fee'], 2) ?></strong>
                    </div>
                </div>
                <div class="col-12">
                    <hr>
                    <p class="mb-1">
                        Gross revenue at baseline fees:
                        <strong class="money">₱<?= number_format($result['baseline_gross_revenue'], 2) ?></strong>
                    </p>
                    <p class="mb-1">
                        Gross revenue at proposed fees:
                        <strong class="money">₱<?= number_format($result['scenario_gross_revenue'], 2) ?></strong>
                    </p>
                    <p class="mb-0">
                        Estimated workload at entered rider throughput:
                        <strong>
                            <?= $result['estimated_workload_hours'] === null ? 'No riders available' : number_format($result['estimated_workload_hours'], 1) . ' rider-hours' ?>
                        </strong>
                    </p>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php $this->call->view('templates/footer'); ?>
