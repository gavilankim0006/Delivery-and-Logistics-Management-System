<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'API Audit Logs']);
?>

<div class="container page-shell py-4">
    <header class="page-header mb-4">
        <div>
            <h1 class="page-title">API audit logs</h1>
            <p class="page-description">Recent external requests recorded by the API client.</p>
        </div>
        <a href="<?= base_url('admin') ?>" class="btn btn-secondary">Admin dashboard</a>
    </header>

    <section class="surface" aria-label="API request logs">
        <div class="table-responsive">
            <table class="table table-clean table-responsive-stack align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Time</th>
                        <th scope="col">Service</th>
                        <th scope="col">Method</th>
                        <th scope="col">Endpoint</th>
                        <th scope="col">Status</th>
                        <th scope="col">Latency</th>
                        <th scope="col">Attempt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No API logs have been recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td data-label="ID" class="text-muted">#<?= (int)$l['id'] ?></td>
                                <td data-label="Time" class="text-nowrap"><?= date('M d, Y H:i:s', strtotime($l['created_at'])) ?></td>
                                <td data-label="Service"><?= e($l['service']) ?></td>
                                <td data-label="Method" class="fw-medium"><?= e($l['method']) ?></td>
                                <td data-label="Endpoint" class="font-monospace text-break"><?= e($l['endpoint']) ?></td>
                                <td data-label="Status">
                                    <?php $responseOk = $l['response_status'] >= 200 && $l['response_status'] < 300; ?>
                                    <span class="audit-status <?= $responseOk ? 'audit-status--success' : 'audit-status--failure' ?>" role="status">
                                        <?= !empty($l['response_status']) ? (int)$l['response_status'] : 'Error' ?>
                                    </span>
                                </td>
                                <td data-label="Latency" class="text-nowrap"><?= number_format((float)$l['execution_time_ms']) ?> ms</td>
                                <td data-label="Attempt"><?= (int)$l['attempt'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php $this->call->view('templates/footer'); ?>
