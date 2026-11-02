<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'New delivery']);
$formData = $form_data ?? [];
$errors = $errors ?? [];
$csrfField = csrf_field();
$orderFormProps = [
    'mode' => 'create', 'formData' => $formData, 'errors' => $errors,
    'formError' => $form_error ?? '', 'csrfField' => $csrfField, 'user' => $user ?? [],
    'rates' => $rates ?? [], 'mapsKey' => $maps_js_key ?? '',
    'routeApiEndpoint' => $route_api_endpoint ?? '', 'baseUrl' => base_url()
];
?>
<div id="vue-orders-root" data-page="form" data-props="<?= e(json_encode($orderFormProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <?= $csrfField ?>
    <noscript><div class="container py-4"><div class="alert alert-warning" role="status">JavaScript is required to render this form. Your account and order data remain unchanged.</div></div></noscript>
</div>
<?php
$ordersJsPath = dirname(__DIR__, 2) . '/../public/build/orders.js';
$ordersJsVer = is_file($ordersJsPath) ? '?v=' . (string)filemtime($ordersJsPath) : '';
?>
<script type="module" src="<?= e(base_url('build/orders.js' . $ordersJsVer)) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php
$extra_js = null;
if (!empty($maps_js_key)) {
    $mapsKey = rawurlencode($maps_js_key);
    $mapsJsPath = dirname(__DIR__, 2) . '/../public/js/maps.js';
    $mapsJsVer = is_file($mapsJsPath) ? '?v=' . (string)filemtime($mapsJsPath) : '';
    $extra_js = '<script src="' . e(base_url('js/maps.js' . $mapsJsVer)) . '"></script>';
    $extra_js .= '<script async defer src="https://maps.googleapis.com/maps/api/js?key=' . $mapsKey . '&libraries=places,geometry&callback=initDeliveryMap"></script>';
}
$this->call->view('templates/footer', ['extra_js' => $extra_js]);
