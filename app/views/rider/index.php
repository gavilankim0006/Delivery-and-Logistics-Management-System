<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', [
    'page_title' => $page_title ?? 'My deliveries',
    'extra_css'  => '<link href="' . base_url('css/rider-portal.css') . '?v=' . (is_file(dirname(__DIR__, 2) . '/public/css/rider-portal.css') ? filemtime(dirname(__DIR__, 2) . '/public/css/rider-portal.css') : '1') . '" rel="stylesheet">'
]);
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$riderProps = [
    'orders' => $orders ?? [],
    'portalLinked' => (bool)$portal_linked,
    'baseUrl' => base_url(),
    'flashes' => $flashes,
];
?>
<div id="vue-rider-root" data-page="list" data-props="<?= e(json_encode($riderProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <noscript><div class="container py-4"><div class="alert alert-warning">Enable JavaScript to view deliveries.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('build/rider.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
