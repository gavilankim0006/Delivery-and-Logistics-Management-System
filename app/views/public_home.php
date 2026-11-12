<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => 'Home']);
$publicHomeProps = ['baseUrl' => base_url()];
?>
<div id="public-home-vue-root" data-props="<?= e(json_encode($publicHomeProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to experience Dispatch.</div></div></noscript>
</div>
<script type="module" src="<?= e(base_url('build/public_home.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
