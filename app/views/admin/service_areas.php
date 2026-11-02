<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Delivery service areas']);
?>

<div class="container page-shell py-4">
    <header class="page-header mb-4">
        <div>
            <h1 class="page-title">Delivery service areas</h1>
            <p class="page-description">Draw named polygon zones and customize their delivery fee rates.</p>
        </div>
    </header>

    <?php foreach (['success', 'error', 'warning', 'info'] as $type): ?>
        <?php if ($message = flash($type)): ?>
            <div class="alert alert-<?= $type === 'error' ? 'danger' : e($type) ?> alert-dismissible fade show" role="status">
                <i class="bi bi-<?= $type === 'error' ? 'exclamation-circle-fill' : ($type === 'success' ? 'check-circle-fill' : 'info-circle-fill') ?> me-2" aria-hidden="true"></i>
                <?= e($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if (empty($maps_js_key)): ?>
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            The map editor is unavailable until <code>GOOGLE_MAPS_MAPS_JS_API_KEY</code> is configured with Maps JavaScript API access and HTTP referrer restrictions.
        </div>
    <?php endif; ?>

    <section class="surface p-3 p-md-4 mb-4" aria-labelledby="zone-form-title">
        <h2 class="h5 mb-3" id="zone-form-title">
            <i class="bi bi-geo-alt-fill text-primary me-2"></i>Create or edit a zone
        </h2>

        <form method="post" action="<?= base_url('admin/service-areas/save') ?>" id="service-area-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="area-id" value="">
            <input type="hidden" name="polygon" id="area-polygon" value="">

            <div class="row g-3">
                <div class="col-12 col-md-8 shared-field-wrap">
                    <label class="form-label shared-label" for="area-name">
                        <span>Zone name</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <input
                        class="form-control"
                        name="name"
                        id="area-name"
                        minlength="2"
                        maxlength="100"
                        placeholder="e.g. Metro Manila Hub, North Sector"
                        required
                        aria-describedby="area-name-help"
                    >
                    <div class="form-text shared-helper-text" id="area-name-help">Unique descriptive name for this service area (2 to 100 characters).</div>
                </div>

                <div class="col-12 col-md-4 d-flex align-items-center mt-md-4 pt-md-2">
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="is_active"
                            id="area-is-active"
                            value="1"
                            checked
                        >
                        <label class="form-check-label fw-semibold" for="area-is-active" style="cursor: pointer;">
                            Active service area
                        </label>
                    </div>
                </div>
            </div>

            <!-- Map Controls & Container -->
            <div class="d-flex flex-wrap gap-2 my-3 align-items-center">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="zone-undo">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Undo point
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="zone-clear">
                    <i class="bi bi-trash3 me-1"></i>Clear polygon
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="zone-cancel-edit">
                    <i class="bi bi-x-circle me-1"></i>Cancel editing (new zone)
                </button>
                <span class="small text-muted align-self-center ms-auto" id="zone-point-count">
                    Add at least 3 points by clicking the map.
                </span>
            </div>

            <div id="service-area-map" style="min-height:380px;border-radius:12px;background:#eef3f5" aria-label="Interactive map to draw service area polygon vertices"></div>
            <p class="form-text text-muted mt-2">
                <i class="bi bi-info-circle me-1"></i>Click the map to place polygon vertices. At least 3 points are required; the server verifies polygon validity prior to saving.
            </p>

            <!-- Zone Specific Fee Rates -->
            <fieldset class="border rounded p-3 mt-4">
                <legend class="float-none w-auto px-2 h6 fw-bold">
                    <i class="bi bi-cash-stack text-success me-2"></i>Zone fee rates
                </legend>
                <div class="row g-3">
                    <!-- Base Fee -->
                    <div class="col-12 col-sm-6 col-lg shared-field-wrap">
                        <label class="form-label shared-label" for="base_delivery_fee">
                            <span>Base fee</span>
                            <span class="text-danger ms-1" aria-hidden="true">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">₱</span>
                            <input
                                class="form-control"
                                type="number"
                                min="0"
                                max="100000"
                                step="0.01"
                                name="base_delivery_fee"
                                id="base_delivery_fee"
                                value="<?= e((string)(get_delivery_rates()['base_delivery_fee'] ?? '60.00')) ?>"
                                required
                                aria-describedby="base-fee-help"
                            >
                            <span class="input-group-text">PHP</span>
                        </div>
                        <div class="form-text shared-helper-text" id="base-fee-help">Zone base delivery charge.</div>
                    </div>

                    <!-- Free Distance -->
                    <div class="col-12 col-sm-6 col-lg shared-field-wrap">
                        <label class="form-label shared-label" for="free_distance_km">
                            <span>Free distance</span>
                            <span class="text-danger ms-1" aria-hidden="true">*</span>
                        </label>
                        <div class="input-group">
                            <input
                                class="form-control"
                                type="number"
                                min="0"
                                max="10000"
                                step="0.1"
                                name="free_distance_km"
                                id="free_distance_km"
                                value="<?= e((string)(get_delivery_rates()['free_distance_km'] ?? '2.0')) ?>"
                                required
                                aria-describedby="free-dist-help"
                            >
                            <span class="input-group-text">km</span>
                        </div>
                        <div class="form-text shared-helper-text" id="free-dist-help">Distance included in base fee.</div>
                    </div>

                    <!-- Rate per km -->
                    <div class="col-12 col-sm-6 col-lg shared-field-wrap">
                        <label class="form-label shared-label" for="per_km_rate">
                            <span>Rate per km</span>
                            <span class="text-danger ms-1" aria-hidden="true">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">₱</span>
                            <input
                                class="form-control"
                                type="number"
                                min="0"
                                max="10000"
                                step="0.01"
                                name="per_km_rate"
                                id="per_km_rate"
                                value="<?= e((string)(get_delivery_rates()['per_km_rate'] ?? '10.00')) ?>"
                                required
                                aria-describedby="per-km-help"
                            >
                            <span class="input-group-text">PHP</span>
                        </div>
                        <div class="form-text shared-helper-text" id="per-km-help">Additional rate per km.</div>
                    </div>

                    <!-- Free Weight -->
                    <div class="col-12 col-sm-6 col-lg shared-field-wrap">
                        <label class="form-label shared-label" for="free_weight_kg">
                            <span>Free weight</span>
                            <span class="text-danger ms-1" aria-hidden="true">*</span>
                        </label>
                        <div class="input-group">
                            <input
                                class="form-control"
                                type="number"
                                min="0"
                                max="1000"
                                step="0.1"
                                name="free_weight_kg"
                                id="free_weight_kg"
                                value="<?= e((string)(get_delivery_rates()['free_weight_kg'] ?? '5.0')) ?>"
                                required
                                aria-describedby="free-wt-help"
                            >
                            <span class="input-group-text">kg</span>
                        </div>
                        <div class="form-text shared-helper-text" id="free-wt-help">Weight included in base fee.</div>
                    </div>

                    <!-- Rate per kg -->
                    <div class="col-12 col-sm-6 col-lg shared-field-wrap">
                        <label class="form-label shared-label" for="per_kg_rate">
                            <span>Rate per kg</span>
                            <span class="text-danger ms-1" aria-hidden="true">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">₱</span>
                            <input
                                class="form-control"
                                type="number"
                                min="0"
                                max="10000"
                                step="0.01"
                                name="per_kg_rate"
                                id="per_kg_rate"
                                value="<?= e((string)(get_delivery_rates()['per_kg_rate'] ?? '5.00')) ?>"
                                required
                                aria-describedby="per-kg-help"
                            >
                            <span class="input-group-text">PHP</span>
                        </div>
                        <div class="form-text shared-helper-text" id="per-kg-help">Additional rate per kg.</div>
                    </div>
                </div>
            </fieldset>

            <div class="d-flex gap-2 justify-content-end mt-4">
                <button
                    class="btn btn-primary fw-semibold"
                    type="submit"
                    <?= empty($maps_js_key) ? 'disabled' : '' ?>
                >
                    <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Save service area
                </button>
            </div>
        </form>
    </section>

    <!-- Saved Zones Table -->
    <section class="surface p-3 p-md-4" aria-labelledby="saved-zones-title">
        <h2 class="h5 mb-3" id="saved-zones-title">
            <i class="bi bi-map text-secondary me-2"></i>Saved zones
        </h2>

        <?php if (empty($areas)): ?>
            <p class="text-muted mb-0">No service areas created yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-clean align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Status</th>
                            <th scope="col">Vertices</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($areas as $area): $vertices = json_decode($area['polygon_json'], true) ?: []; ?>
                            <tr>
                                <td class="fw-semibold"><?= e($area['name']) ?></td>
                                <td>
                                    <?php if ((int)$area['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= count($vertices) ?> points</td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button
                                            class="btn btn-sm btn-outline-primary zone-edit"
                                            type="button"
                                            data-zone="<?= e(json_encode($area, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>"
                                        >
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </button>
                                        <form
                                            method="post"
                                            action="<?= base_url('admin/service-areas/delete/' . (int)$area['id']) ?>"
                                            onsubmit="return confirm('Delete this service area?')"
                                            class="d-inline"
                                        >
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger" type="submit">
                                                <i class="bi bi-trash3 me-1"></i>Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <div class="mt-4">
        <a class="btn btn-primary" href="<?= base_url('admin/command-simulator') ?>">
            <i class="bi bi-cpu me-1"></i>Open command simulator
        </a>
    </div>
</div>

<?php if (!empty($maps_js_key)): ?>
<script nonce="<?= e($service_area_csp_nonce ?? '') ?>">
window.initServiceAreaMap = function () {
    if (window._serviceAreaEditorInstance) return;
    if (window.ServiceAreaEditor && window.google && window.google.maps) {
        window._serviceAreaEditorInstance = new window.ServiceAreaEditor({ mapId: 'service-area-map' });
    } else {
        window.addEventListener('service-area-editor-ready', window.initServiceAreaMap, { once: true });
    }
};
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?= rawurlencode($maps_js_key) ?>&callback=initServiceAreaMap"></script>
<script src="<?= base_url('js/service-areas.js') ?>"></script>
<?php endif; ?>

<?php $this->call->view('templates/footer'); ?>
