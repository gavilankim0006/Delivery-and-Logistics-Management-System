<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', [
    'page_title' => $page_title ?? 'Delivery unavailable',
    'extra_css'  => '<link href="' . base_url('css/rider-portal.css') . '?v=' . (is_file(dirname(__DIR__, 2) . '/public/css/rider-portal.css') ? filemtime(dirname(__DIR__, 2) . '/public/css/rider-portal.css') : '1') . '" rel="stylesheet">'
]);
?>
<div id="static-page-vue-root" data-page-kind="rider-not-found" data-base-url="<?= e(base_url()) ?>"></div>
<script type="module" src="<?= e(base_url('build/static_pages.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
