<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Analytics & Reports']);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Analytics & Delivery Reports</h1>
            <p class="text-muted small mb-0">Historical order metrics, revenue summaries, and report exports</p>
        </div>
    </div>

    <div class="card shadow-sm border-0 p-4 text-center py-5">
        <div class="text-muted mb-3"><i class="bi bi-file-earmark-bar-graph fs-1 text-primary"></i></div>
        <h4 class="fw-bold">Reports Module Initialized</h4>
        <p class="text-muted">Detailed date range filtering, financial reconciliations, and CSV/PDF export are loaded in Task T8.</p>
        <div>
            <a href="<?= base_url('admin') ?>" class="btn btn-outline-primary btn-sm">Return to Admin Dashboard</a>
        </div>
    </div>
</div>

<?php $this->call->view('templates/footer'); ?>
