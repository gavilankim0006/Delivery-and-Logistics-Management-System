<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$riderPortalCssPath = dirname(__DIR__, 3) . '/public/css/rider-portal.css';
$extraCss = '<link href="' . e(base_url('css/rider-portal.css')) . '?v=' . (is_file($riderPortalCssPath) ? filemtime($riderPortalCssPath) : '1') . '" rel="stylesheet">';
foreach (vite_entry_css('frontend/rider/main.js') as $riderCssFile) {
    $extraCss .= '<link href="' . e(base_url('build/' . ltrim($riderCssFile, '/'))) . '" rel="stylesheet">';
}
$this->call->view('templates/header', [
    'page_title' => $page_title ?? 'My deliveries',
    'extra_css'  => $extraCss
]);
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$riderProps = [
    'orders' => $orders ?? [],
    'history' => $history ?? [],
    'stats' => $stats ?? [],
    'rider' => $rider ?? null,
    'account' => $account ?? ['name' => '', 'email' => '', 'phone' => ''],
    'portalLinked' => (bool)$portal_linked,
    'dataUrl' => base_url('rider/data'),
    'availabilityUrl' => base_url('rider/availability'),
    'csrfToken' => csrf_token(),
    'baseUrl' => base_url(),
    'flashes' => $flashes,
];
?>
<div id="vue-rider-root" data-page="list" data-props="<?= e(json_encode($riderProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <noscript><div class="container py-4"><div class="alert alert-warning">Enable JavaScript to view deliveries.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('build/rider.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
