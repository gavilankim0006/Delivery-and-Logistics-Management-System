<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$filters = $filters ?? [];
$report = $report ?? ['summary' => [], 'rows' => []];
$errors = $errors ?? [];
$riders = $riders ?? [];
$reportError = $report_error ?? null;
$type = $filters['type'] ?? 'deliveries';
$summary = $report['summary'] ?? [];
$rows = $report['rows'] ?? [];
$statusOptions = $type === 'payments'
    ? ['pending', 'paid', 'failed', 'refunded', 'cancelled']
    : ['draft', 'pending_payment', 'paid', 'booking', 'rider_assigned', 'picked_up', 'delivered', 'cancelled', 'failed'];
$exportQuery = http_build_query(array_merge($filters, ['export' => 'csv']));
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$props = [
    'filters' => $filters,
    'report' => $report,
    'errors' => $errors,
    'riders' => $riders,
    'reportError' => $reportError,
    'exportQuery' => $exportQuery,
    'baseUrl' => base_url(),
    'csrfToken' => csrf_token(),
    'flashes' => $flashes,
];
?>
<?php $this->call->view('templates/header', ['page_title' => $page_title ?? 'Analytics and Reports']); ?>
<div id="vue-admin-root"
    data-page="reports"
    data-props="<?= e(json_encode($props, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>">
</div>
<script type="module" src="<?= e(base_url('build/admin.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>