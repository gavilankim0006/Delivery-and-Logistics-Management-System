<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$areas = $areas ?? [];
$mapsJsKey = $maps_js_key ?? '';
$nonce = $service_area_csp_nonce ?? '';
$rates = get_delivery_rates();
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$props = [
    'areas' => $areas,
    'mapsJsKey' => $mapsJsKey,
    'nonce' => $nonce,
    'rates' => $rates,
    'baseUrl' => base_url(),
    'csrfToken' => csrf_token(),
    'flashes' => $flashes,
];
?>
<?php $this->call->view('templates/header', ['page_title' => $page_title ?? 'Delivery service areas']); ?>
<div id="vue-admin-root"
    data-page="service-areas"
    data-props="<?= e(json_encode($props, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>">
    <noscript>
        <button type="button" id="zone-cancel-edit">Cancel editing (new zone)</button>
    </noscript>
</div>
<?php if (!empty($mapsJsKey)): ?>
<script nonce="<?= e($nonce) ?>">
window.initServiceAreaMap = function () {
    if (window._serviceAreaEditorInstance) return;
    if (window.ServiceAreaEditor && window.google && window.google.maps) {
        window._serviceAreaEditorInstance = new window.ServiceAreaEditor({ mapId: 'service-area-map' });
    } else {
        window.addEventListener('service-area-editor-ready', window.initServiceAreaMap, { once: true });
    }
};
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?= rawurlencode($mapsJsKey) ?>&callback=initServiceAreaMap"></script>
<script src="<?= base_url('js/service-areas.js') ?>"></script>
<?php endif; ?>
<script type="module" src="<?= e(base_url('build/admin.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>