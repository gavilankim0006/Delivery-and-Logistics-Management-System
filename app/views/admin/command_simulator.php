<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$areas = $areas ?? [];
$selectedAreaId = (int)($selected_area_id ?? 0);
$zoneData = $zone_data ?? null;
$baselineRates = $baseline_rates ?? get_delivery_rates();
$areaRates = $area_rates ?? $baselineRates;
$availableRiders = (int)($available_riders ?? 0);
$result = $result ?? null;
$simulationError = $simulation_error ?? null;
$formData = $form_data ?? [];
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$props = [
    'areas' => $areas,
    'selectedAreaId' => $selectedAreaId,
    'zoneData' => $zoneData,
    'baselineRates' => $baselineRates,
    'areaRates' => $areaRates,
    'availableRiders' => $availableRiders,
    'result' => $result,
    'simulationError' => $simulationError,
    'formData' => $formData,
    'baseUrl' => base_url(),
    'csrfToken' => csrf_token(),
    'flashes' => $flashes,
];
?>
<?php $this->call->view('templates/header', ['page_title' => $page_title ?? 'Delivery command simulator']); ?>
<div id="vue-admin-root"
    data-page="command-simulator"
    data-props="<?= e(json_encode($props, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>">
</div>
<script type="module" src="<?= e(base_url('build/admin.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>