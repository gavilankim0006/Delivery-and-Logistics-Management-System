<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', [
    'page_title' => $page_title ?? 'Analytics',
    'extra_css' => '<link rel="stylesheet" href="' . e(base_url('css/analytics.css')) . '?' . (is_file(dirname(__DIR__, 3) . '/public/css/analytics.css') ? (string)filemtime(dirname(__DIR__, 3) . '/public/css/analytics.css') : '1') . '">'
]);
$props = [
    'filters' => ['from' => (string)($filters['from'] ?? ''), 'to' => (string)($filters['to'] ?? '')],
    'summary' => array_map(static fn($value) => is_numeric($value) ? (float)$value : $value, $analytics['summary'] ?? []),
    'daily' => $analytics['daily'] ?? [],
    'outcomes' => $analytics['outcomes'] ?? [],
    'links' => ['analytics' => base_url('admin/analytics'), 'reports' => base_url('admin/reports')],
    'error' => $analytics_error ?? ''
];
?>
<div id="vue-admin-root" data-page="analytics" data-props="<?= e(json_encode($props, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>">
    <main class="container page-shell py-4">
        <h1 class="page-title">Analytics</h1>
        <?php if (!empty($errors)): ?><div class="alert alert-danger" role="alert"><?php foreach ($errors as $message): ?><p class="mb-0"><?= e($message) ?></p><?php endforeach; ?></div><?php endif; ?>
        <p class="text-muted">Order, delivery, and payment performance for the selected period.</p>
    </main>
</div>
<script type="module" src="<?= e(base_url('build/admin.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
