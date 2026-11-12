<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Delivery link unavailable']);
?>
<div id="static-page-vue-root" data-page-kind="delivery-control-not-found" data-base-url="<?= e(base_url()) ?>"></div>
<script type="module" src="<?= e(base_url('build/static_pages.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
