<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$root = dirname(__DIR__, 3);
$logs = $logs ?? [];
$props = ['logs' => $logs, 'baseUrl' => base_url()];
$extraCss = '';
?>
<?php $this->call->view('templates/header', ['page_title' => $page_title ?? 'API Audit Logs']); ?>
<div id="vue-admin-root"
    data-page="logs"
    data-props="<?= e(json_encode($props, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>">
</div>
<script type="module" src="<?= e(base_url('build/admin.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>