<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Delivery unavailable']);
?>
<div class="container page-shell py-5 ds-page">
    <section class="surface p-4" role="status">
        <h1 class="h3">Delivery unavailable</h1>
        <p>The delivery may have been reassigned or completed, or it may not belong to your rider account.</p>
        <a class="btn btn-primary" href="<?= base_url('rider') ?>">Back to my deliveries</a>
    </section>
</div>
<?php $this->call->view('templates/footer'); ?>
