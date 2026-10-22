<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => 'Order not found']);
?>
<div class="container py-5 ds-page page-shell">
    <section class="empty-state mx-auto py-4 py-md-5" aria-labelledby="not-found-heading">
        <p class="text-muted mb-2">404 · Not found</p>
        <h1 class="h3 fw-bold" id="not-found-heading">We couldn't find that order</h1>
        <p class="text-muted">It may no longer be available, or you may not have access to it.</p>
        <a class="btn btn-primary" href="<?= base_url('orders') ?>">Back to orders</a>
    </section>
</div>
<?php $this->call->view('templates/footer'); ?>
