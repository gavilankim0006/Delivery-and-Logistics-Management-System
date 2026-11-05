<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$dispatchCssPath = dirname(__DIR__, 3) . '/public/css/dispatch-portal.css';
$extra_css = '<link href="' . base_url('css/dispatch-portal.css') . (is_file($dispatchCssPath) ? '?v=' . (string)filemtime($dispatchCssPath) : '') . '" rel="stylesheet">';
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Live Dispatch Board', 'extra_css' => $extra_css]);
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
        <!-- Server-rendered fallback while Vue hydrates -->
        <div class="dispatch-shift-banner">
            <div class="dispatch-shift-greeting">
                <h2>Live Dispatch Operations</h2>
                <p><?= date('l, F j, Y') ?></p>
            </div>
            <div class="dispatch-shift-clock">
                <i class="bi bi-clock" aria-hidden="true"></i>
                <span><?= date('g:i A') ?></span>
            </div>
        </div>

        <!-- KPI fallback -->
        <div class="dispatch-kpi-grid">
            <a href="<?= base_url('dispatch?date=' . date('Y-m-d')) ?>" class="dispatch-kpi-card">
                <div class="dispatch-kpi-icon dispatch-kpi-icon--today"><i class="bi bi-calendar-check" aria-hidden="true"></i></div>
                <span class="dispatch-kpi-label">Today's Orders</span>
                <span class="dispatch-kpi-value"><?= (int)($today_total ?? 0) ?></span>
            </a>
            <a href="<?= base_url('dispatch?status=paid') ?>" class="dispatch-kpi-card dispatch-kpi-card--warning">
                <div class="dispatch-kpi-icon dispatch-kpi-icon--awaiting"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
                <span class="dispatch-kpi-label">Awaiting Booking</span>
                <span class="dispatch-kpi-value"><?= (int)($awaiting_booking ?? 0) ?></span>
            </a>
            <a href="<?= base_url('dispatch?status=in_transit') ?>" class="dispatch-kpi-card">
                <div class="dispatch-kpi-icon dispatch-kpi-icon--transit"><i class="bi bi-truck" aria-hidden="true"></i></div>
                <span class="dispatch-kpi-label">In Transit</span>
                <span class="dispatch-kpi-value"><?= (int)($in_transit ?? 0) ?></span>
            </a>
            <a href="<?= base_url('dispatch?status=failed') ?>" class="dispatch-kpi-card dispatch-kpi-card--danger">
                <div class="dispatch-kpi-icon dispatch-kpi-icon--failed"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div>
                <span class="dispatch-kpi-label">Failed / Exceptions</span>
                <span class="dispatch-kpi-value"><?= (int)($failed_count ?? 0) ?></span>
            </a>
        </div>

        <!-- Hidden structured data for no-JS / SEO fallback -->
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

    <section class="surface dispatch-board-card" aria-label="Dispatch orders" data-dispatch-vue-board>
        <div id="dispatch-vue-root"
            data-data-url="<?= e($dispatch_data_url) ?>"
            data-base-url="<?= e($dispatch_base_url) ?>"
            data-csrf-token="<?= e($csrf_token) ?>"
            data-fulfillment-actions="<?= !empty($fulfillment_actions) ? 'true' : 'false' ?>"
            data-available-riders="<?= e(json_encode($available_riders, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
            <div class="table-responsive"><table class="table table-clean table-responsive-stack dispatch-orders-table align-middle mb-0"><tbody><tr><td class="text-center py-4 text-muted" role="status">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <div class="dispatch-loading-spinner" style="width: 20px; height: 20px; border-width: 2px;"></div>
                    Loading dispatch orders…
                </div>
            </td></tr></tbody></table></div>
        </div>
    </section>
</div>
<?php
$dispatchBundlePath = dirname(__DIR__, 3) . '/public/build/dispatch.js';
$dispatchBundleVersion = is_file($dispatchBundlePath) ? '?v=' . (string)filemtime($dispatchBundlePath) : '';
$extra_js = '<script type="module" nonce="' . e(defined('CSP_NONCE') ? CSP_NONCE : '') . '" src="' . e(base_url('build/dispatch.js')) . e($dispatchBundleVersion) . '"></script>';
$this->call->view('templates/footer', ['extra_js' => $extra_js]);
?>
