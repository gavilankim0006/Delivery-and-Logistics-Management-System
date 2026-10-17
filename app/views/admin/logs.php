<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'API Audit Logs']);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">API Audit Trail</h1>
            <p class="text-muted small mb-0">Complete historical log of external requests through ApiClient</p>
        </div>
        <a href="<?= base_url('admin') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Time</th>
                        <th>Service</th>
                        <th>Method</th>
                        <th>Endpoint</th>
                        <th>Status</th>
                        <th>Latency</th>
                        <th>Attempt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No API logs recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="text-muted">#<?= $l['id'] ?></td>
                                <td><?= date('M d H:i:s', strtotime($l['created_at'])) ?></td>
                                <td><span class="badge bg-secondary"><?= e($l['service']) ?></span></td>
                                <td class="fw-bold"><?= e($l['method']) ?></td>
                                <td class="font-monospace text-truncate" style="max-width: 320px;" title="<?= e($l['endpoint']) ?>">
                                    <?= e($l['endpoint']) ?>
                                </td>
                                <td>
                                    <span class="badge <?= ($l['response_status'] >= 200 && $l['response_status'] < 300) ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $l['response_status'] ?: 'ERR' ?>
                                    </span>
                                </td>
                                <td><?= $l['execution_time_ms'] ?> ms</td>
                                <td><?= $l['attempt'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $this->call->view('templates/footer'); ?>
