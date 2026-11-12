<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$dispatchCssPath = dirname(__DIR__, 3) . '/public/css/dispatch-portal.css';
$extra_css = '<link href="' . base_url('css/dispatch-portal.css') . (is_file($dispatchCssPath) ? '?v=' . (string)filemtime($dispatchCssPath) : '') . '" rel="stylesheet">';
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Live Dispatch Board', 'extra_css' => $extra_css]);

$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}

$dispatchPageProps = [
    'controls' => [
        'today' => date('Y-m-d'),
        'date' => $date_filter ?? '',
        'status' => $status_filter ?? '',
        'search' => $search ?? '',
        'summary' => [
            'today_total' => (int)($today_total ?? 0),
            'awaiting_booking' => (int)($awaiting_booking ?? 0),
            'in_transit' => (int)($in_transit ?? 0),
            'failed_count' => (int)($failed_count ?? 0),
        ],
        'pendingRequests' => array_map(static fn($request) => [
            'id' => (int)($request['id'] ?? 0),
            'order_id' => (int)($request['order_id'] ?? 0),
            'order_no' => (string)($request['order_no'] ?? ''),
            'request_text' => (string)($request['request_text'] ?? ''),
            'created_at' => !empty($request['created_at']) ? date('M j, g:i a', strtotime($request['created_at'])) : '',
        ], $pending_requests ?? []),
    ],
    'board' => [
        'dataUrl' => $dispatch_data_url ?? base_url('dispatch/data'),
        'fulfillmentActions' => !empty($fulfillment_actions),
        'availableRiders' => $available_riders ?? [],
    ],
    'baseUrl' => base_url(),
    'csrfToken' => $csrf_token ?? csrf_token(),
    'flashes' => $flashes,
];
?>
<div id="dispatch-page-vue-root"
    data-props="<?= e(json_encode($dispatchPageProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>"
    data-base-url="<?= e(base_url()) ?>"
    data-csrf-token="<?= e($csrf_token ?? csrf_token()) ?>"
    data-csrf-name="csrf_token">
</div>
<?php
$dispatchBundlePath = dirname(__DIR__, 3) . '/public/build/dispatch.js';
$dispatchBundleVersion = is_file($dispatchBundlePath) ? '?v=' . (string)filemtime($dispatchBundlePath) : '';
$extra_js = '<script type="module" nonce="' . e(defined('CSP_NONCE') ? CSP_NONCE : '') . '" src="' . e(base_url('build/dispatch.js')) . e($dispatchBundleVersion) . '"></script>';
$this->call->view('templates/footer', ['extra_js' => $extra_js]);
?>
