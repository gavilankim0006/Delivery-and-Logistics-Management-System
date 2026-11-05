<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', [
    'page_title' => $page_title ?? 'Delivery unavailable',
    'extra_css'  => '<link href="' . base_url('css/rider-portal.css') . '?v=' . (is_file(dirname(__DIR__, 2) . '/public/css/rider-portal.css') ? filemtime(dirname(__DIR__, 2) . '/public/css/rider-portal.css') : '1') . '" rel="stylesheet">'
]);
?>
<div class="container page-shell py-5 ds-page">
    <section class="surface p-4 p-md-5 text-center" role="status" style="border-radius:.75rem; border:1px solid var(--line,#e5e7eb); max-width:32rem; margin:0 auto;">
        <i class="bi bi-exclamation-triangle" style="font-size:3rem; color:#ffc107; display:block; margin-bottom:1rem;" aria-hidden="true"></i>
        <h1 class="h4 mb-2">Delivery unavailable</h1>
        <p class="text-muted mb-4">The delivery may have been reassigned, completed, or it may not belong to your rider account.</p>
        <a class="btn btn-primary" href="<?= base_url('rider') ?>" style="min-height:44px; display:inline-flex; align-items:center; gap:.375rem;">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Back to my deliveries
        </a>
    </section>
</div>
<?php $this->call->view('templates/footer'); ?>
