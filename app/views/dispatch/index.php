<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Live Dispatch Board']);
$dispatchProps = [
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
];
?>
<div class="container-fluid page-shell dispatch-page-shell">
    <div id="dispatch-controls-vue-root"
        data-props="<?= e(json_encode($dispatchProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>"
        data-base-url="<?= e(base_url()) ?>"
        data-csrf-token="<?= e($csrf_token) ?>"
        data-csrf-name="csrf_token">
        <div class="page-header"><h1 class="page-title">Live Dispatch Operations</h1></div>
        <div class="visually-hidden">
            <a href="<?= base_url('dispatch?date=' . date('Y-m-d')) ?>"><span>Today's Orders</span> <span class="ds-segment__count"><?= (int)($today_total ?? 0) ?></span></a>
            <a href="<?= base_url('dispatch?status=paid') ?>"><span>Awaiting Booking</span> <span class="ds-segment__count"><?= (int)($awaiting_booking ?? 0) ?></span></a>
            <span>In Transit</span> <span class="ds-segment__count"><?= (int)($in_transit ?? 0) ?></span>
            <a href="<?= base_url('dispatch?status=failed') ?>"><span>Failed / Exceptions</span> <span class="ds-segment__count"><?= (int)($failed_count ?? 0) ?></span></a>
            <select name="status">
                <option value="failed" <?= ($status_filter ?? '') === 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>
        </div>
        <p class="text-muted" role="status">Loading dispatch controls…</p>
    </div>

    <p class="small text-muted mb-3">Live updates <span id="dispatch-live-status" role="status" aria-live="polite">Connecting…</span></p>
    <section class="surface dispatch-board-card" aria-label="Dispatch orders" data-dispatch-vue-board>
        <div id="dispatch-vue-root"
            data-data-url="<?= e($dispatch_data_url) ?>"
            data-base-url="<?= e($dispatch_base_url) ?>"
            data-csrf-token="<?= e($csrf_token) ?>"
            data-fulfillment-actions="<?= !empty($fulfillment_actions) ? 'true' : 'false' ?>"
            data-available-riders="<?= e(json_encode($available_riders, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
            <div class="table-responsive"><table class="table table-clean table-responsive-stack dispatch-orders-table align-middle mb-0"><tbody><tr><td class="text-center py-4 text-muted" role="status">Loading dispatch orders…</td></tr></tbody></table></div>
        </div>
    </section>
</div>
<?php
$dispatchBundlePath = dirname(__DIR__, 3) . '/public/build/dispatch.js';
$dispatchBundleVersion = is_file($dispatchBundlePath) ? '?v=' . (string)filemtime($dispatchBundlePath) : '';
$extra_js = '<script type="module" nonce="' . e(defined('CSP_NONCE') ? CSP_NONCE : '') . '" src="' . e(base_url('public/build/dispatch.js')) . e($dispatchBundleVersion) . '"></script>';
$this->call->view('templates/footer', ['extra_js' => $extra_js]);
?>
