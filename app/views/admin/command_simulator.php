<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Delivery command simulator']);
$form = $form_data ?? [];
?>
<div class="container page-shell py-4">
    <header class="page-header mb-4"><div><h1 class="page-title">Delivery command simulator</h1><p class="page-description">Explore capacity and fee scenarios for a saved service zone.</p></div><a class="btn btn-outline-secondary" href="<?= base_url('admin/service-areas') ?>">Manage zones</a></header>
    <div class="alert alert-info">Estimates only. This tool uses drop-off coordinates to select zone orders and a simple rider-throughput assumption; it does not model live traffic, provider capacity, or demand changes. Orders with missing coordinates are not assigned to a zone.</div>
    <?php if (empty($areas)): ?><div class="alert alert-warning">Create an active polygon service area before running a scenario.</div><?php elseif ($simulation_error): ?><div class="alert alert-danger" role="alert"><?= e($simulation_error) ?></div><?php endif; ?>
    <form action="<?= base_url('admin/command-simulator') ?>" method="POST" class="surface p-3 p-md-4">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-12 col-md-6"><label class="form-label" for="area-id">Service area</label><select class="form-select" id="area-id" name="area_id" required><?php foreach ($areas as $area): ?><option value="<?= (int)$area['id'] ?>" <?= (int)$selected_area_id === (int)$area['id'] ? 'selected' : '' ?>><?= e($area['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-md-3"><label class="form-label" for="projected-orders">Projected orders</label><input class="form-control" id="projected-orders" name="projected_orders" type="number" min="1" max="5000" value="<?= e($form['projected_orders'] ?? max(1, count($zone_data['orders'] ?? []))) ?>" required></div>
            <div class="col-6 col-md-3"><label class="form-label" for="scenario-hours">Scenario hours</label><input class="form-control" id="scenario-hours" name="scenario_hours" type="number" min="1" max="24" step="0.5" value="<?= e($form['scenario_hours'] ?? '8') ?>" required></div>
            <div class="col-6 col-md-3"><label class="form-label" for="average-distance">Avg distance (km)</label><input class="form-control" id="average-distance" name="average_distance_km" type="number" min="0.1" max="1000" step="0.1" value="<?= e($form['average_distance_km'] ?? '8') ?>" required></div>
            <div class="col-6 col-md-3"><label class="form-label" for="average-weight">Avg parcel weight (kg)</label><input class="form-control" id="average-weight" name="average_weight_kg" type="number" min="0.1" max="1000" step="0.1" value="<?= e($form['average_weight_kg'] ?? '2') ?>" required></div>
            <div class="col-6 col-md-3"><label class="form-label" for="riders">Riders available</label><input class="form-control" id="riders" name="riders" type="number" min="0" max="500" value="<?= e($form['riders'] ?? $available_riders) ?>" required></div>
            <div class="col-6 col-md-3"><label class="form-label" for="throughput">Deliveries / rider / hour</label><input class="form-control" id="throughput" name="deliveries_per_rider_hour" type="number" min="0.1" max="20" step="0.1" value="<?= e($form['deliveries_per_rider_hour'] ?? '1') ?>" required></div>
        </div>
        <details class="mt-4"><summary class="fw-semibold">Proposed zone fee rates</summary><p class="small text-muted mt-2">Baseline rates come from the current global settings; scenario values are not saved.</p><div class="row g-3 mt-1">
            <?php foreach (['base_delivery_fee' => 'Base fee', 'free_distance_km' => 'Free distance (km)', 'per_km_rate' => 'Rate per km', 'free_weight_kg' => 'Free weight (kg)', 'per_kg_rate' => 'Rate per kg'] as $key => $label): ?>
                <div class="col-6 col-lg"><label class="form-label" for="sim-<?= e($key) ?>"><?= e($label) ?></label><input class="form-control" id="sim-<?= e($key) ?>" name="<?= e($key) ?>" type="number" min="0" max="100000" step="0.01" value="<?= e($form[$key] ?? ($area_rates[$key] ?? $baseline_rates[$key])) ?>" required></div>
            <?php endforeach; ?>
        </div></details>
        <div class="d-flex justify-content-end mt-4"><button type="submit" class="btn btn-primary" <?= empty($areas) ? 'disabled' : '' ?>>Run what-if scenario</button></div>
    </form>
    <?php if ($result): ?>
        <section class="surface p-3 p-md-4 mt-4" aria-labelledby="scenario-results">
            <h2 class="h5" id="scenario-results">Scenario results</h2>
            <p class="text-muted">Zone <?= e($zone_data['zone']['name'] ?? '') ?> · <?= (int)$result['zone_orders'] ?> existing orders in zone · <?= (int)$result['missing_coordinates'] ?> orders skipped for missing coordinates.</p>
            <div class="row g-3">
                <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Estimated capacity</div><strong><?= (int)$result['capacity'] ?> deliveries</strong></div></div>
                <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Projected backlog</div><strong><?= (int)$result['projected_backlog'] ?> orders</strong></div></div>
                <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Baseline average fee</div><strong>₱<?= number_format($result['baseline_fee'], 2) ?></strong></div></div>
                <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Proposed average fee</div><strong>₱<?= number_format($result['scenario_fee'], 2) ?></strong></div></div>
                <div class="col-12"><hr><p class="mb-1">Gross revenue at baseline fees: <strong>₱<?= number_format($result['baseline_gross_revenue'], 2) ?></strong></p><p class="mb-1">Gross revenue at proposed fees: <strong>₱<?= number_format($result['scenario_gross_revenue'], 2) ?></strong></p><p class="mb-0">Estimated workload at entered rider throughput: <strong><?= $result['estimated_workload_hours'] === null ? 'No riders available' : number_format($result['estimated_workload_hours'], 1) . ' rider-hours' ?></strong></p></div>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php $this->call->view('templates/footer'); ?>
