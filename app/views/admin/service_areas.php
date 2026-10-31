<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Delivery service areas']);
?>
<div class="container page-shell py-4">
    <header class="page-header mb-4"><div><h1 class="page-title">Delivery service areas</h1><p class="page-description">Draw named polygon zones and set their delivery fee rates.</p></div></header>
    <?php if (empty($maps_js_key)): ?>
        <div class="alert alert-warning">The map editor is unavailable until <code>GOOGLE_MAPS_MAPS_JS_API_KEY</code> is configured with Maps JavaScript API access and HTTP referrer restrictions.</div>
    <?php endif; ?>
    <section class="surface p-3 p-md-4 mb-4">
        <h2 class="h5" id="zone-form-title">Create or edit a zone</h2>
        <form method="post" action="<?= base_url('admin/service-areas/save') ?>" id="service-area-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="area-id" value="">
            <input type="hidden" name="polygon" id="area-polygon" value="">
            <div class="row g-3">
                <div class="col-12 col-md-6"><label class="form-label" for="area-name">Zone name</label><input class="form-control" name="name" id="area-name" maxlength="100" required></div>
                <div class="col-12 col-md-6 d-flex align-items-end"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked><span class="form-check-label">Active service area</span></label></div>
            </div>
            <div class="d-flex gap-2 my-3"><button type="button" class="btn btn-outline-secondary" id="zone-undo">Undo point</button><button type="button" class="btn btn-outline-secondary" id="zone-clear">Clear polygon</button><button type="button" class="btn btn-outline-secondary d-none" id="zone-cancel-edit">Cancel editing (new zone)</button><span class="small text-muted align-self-center" id="zone-point-count">Add at least 3 points by clicking the map.</span></div>
            <div id="service-area-map" style="min-height:380px;border-radius:12px;background:#eef3f5"></div>
            <p class="form-text mt-2">Click to add polygon vertices. Use at least 3 points; the service validates the geometry again before saving.</p>
            <fieldset class="border rounded p-3 mt-4"><legend class="float-none w-auto px-2 h6">Zone fee rates (PHP)</legend><div class="row g-3">
                <?php foreach (['base_delivery_fee' => 'Base fee', 'free_distance_km' => 'Free distance (km)', 'per_km_rate' => 'Rate per km', 'free_weight_kg' => 'Free weight (kg)', 'per_kg_rate' => 'Rate per kg'] as $key => $label): ?>
                    <div class="col-6 col-lg"><label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label><input class="form-control" type="number" min="0" max="100000" step="0.01" name="<?= e($key) ?>" id="<?= e($key) ?>" value="<?= e((string)(get_delivery_rates()[$key] ?? 0)) ?>" required></div>
                <?php endforeach; ?>
            </div></fieldset>
            <div class="d-flex gap-2 justify-content-end mt-3"><button class="btn btn-primary" type="submit" data-busy-text="Saving…" <?= empty($maps_js_key) ? 'disabled' : '' ?>>Save service area</button></div>
        </form>
    </section>
    <section class="surface p-3 p-md-4"><h2 class="h5">Saved zones</h2>
        <?php if (empty($areas)): ?><p class="text-muted mb-0">No service areas yet.</p><?php else: ?>
            <div class="table-responsive"><table class="table table-clean align-middle mb-0"><thead><tr><th>Name</th><th>Status</th><th>Vertices</th><th>Actions</th></tr></thead><tbody>
                <?php foreach ($areas as $area): $vertices = json_decode($area['polygon_json'], true) ?: []; ?>
                    <tr><td><?= e($area['name']) ?></td><td><?= (int)$area['is_active'] ? 'Active' : 'Inactive' ?></td><td><?= count($vertices) ?></td><td class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary zone-edit" type="button" data-zone="<?= e(json_encode($area, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>">Edit</button>
                        <form method="post" action="<?= base_url('admin/service-areas/delete/' . (int)$area['id']) ?>" onsubmit="return confirm('Delete this service area?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form>
                    </td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
    <div class="mt-4"><a class="btn btn-primary" href="<?= base_url('admin/command-simulator') ?>">Open command simulator</a></div>
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
<script src="<?= base_url('public/js/service-areas.js') ?>"></script>
<?php endif; ?>
<?php $this->call->view('templates/footer'); ?>
