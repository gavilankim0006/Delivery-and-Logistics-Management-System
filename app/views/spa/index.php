<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php $this->call->view('templates/header', ['page_title' => $page_title ?? 'Vue SPA Workspace']); ?>
<div id="spa-root"
     data-base-url="<?= e($base_url) ?>"
     data-csrf-token="<?= e($csrf_token) ?>">
</div>
<?php
$spaBundlePath = dirname(__DIR__, 3) . '/public/build/spa.js';
$spaBundleVersion = is_file($spaBundlePath) ? '?v=' . (string)filemtime($spaBundlePath) : '';
$extra_js = '<script type="module" nonce="' . e(defined('CSP_NONCE') ? CSP_NONCE : '') . '" src="' . e(base_url('public/build/spa.js')) . e($spaBundleVersion) . '"></script>';
$this->call->view('templates/footer', ['extra_js' => $extra_js]);
?>
