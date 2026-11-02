<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Job applications']);
?>
<div class="container page-shell py-4">
    <header class="page-header mb-4">
        <div><h1 class="page-title">Job applications</h1><p class="page-description">Review rider and dispatcher applications. ID files are sensitive; access only when needed for verification.</p></div>
    </header>
    <?php foreach (['success', 'error'] as $type): ?>
        <?php if ($message = flash($type)): ?><div class="alert alert-<?= $type === 'error' ? 'danger' : 'success' ?>" role="status"><?= e($message) ?></div><?php endif; ?>
    <?php endforeach; ?>
    <section class="surface surface-section mb-4" aria-label="Filter applications">
        <form action="<?= e(base_url('admin/applications')) ?>" method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-5"><label class="form-label" for="application-status">Status</label><select class="form-select" id="application-status" name="status"><option value="">All applications</option><?php foreach (['pending', 'approved', 'rejected'] as $status): ?><option value="<?= e($status) ?>" <?= ($status_filter ?? '') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
            <div class="col-auto"><button class="btn btn-secondary" type="submit">Filter</button></div>
        </form>
    </section>
    <?php if (empty($applications)): ?>
        <section class="surface surface-section"><h2 class="h5">No applications found</h2><p class="mb-0 text-muted">Applications will appear here after candidates submit them.</p></section>
    <?php else: ?>
        <div class="d-grid gap-3">
            <?php foreach ($applications as $application): ?>
                <article class="surface surface-section">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                        <div><h2 class="h5 mb-1"><?= e($application['full_name']) ?></h2><span class="badge text-bg-light"><?= e(ucfirst($application['role_applied'])) ?></span> <span class="badge <?= $application['status'] === 'pending' ? 'text-bg-warning' : ($application['status'] === 'approved' ? 'text-bg-success' : 'text-bg-secondary') ?>"><?= e(ucfirst($application['status'])) ?></span></div>
                        <span class="small text-muted">Submitted <?= e($application['created_at']) ?></span>
                    </div>
                    <div class="row g-3 small mb-3">
                        <div class="col-12 col-md-6"><strong>Email:</strong> <?= e($application['email']) ?><br><strong>Phone:</strong> <?= e($application['phone']) ?><br><strong>Location:</strong> <?= e($application['location']) ?></div>
                        <div class="col-12 col-md-6"><strong>Availability:</strong> <?= e($application['availability']) ?><br><strong>ID type:</strong> <?= e($application['id_type']) ?><br><strong>ID number:</strong> <?= e($application['id_number']) ?><br><strong>ID file:</strong> <a href="<?= e(base_url('admin/applications/' . (int)$application['id'] . '/id')) ?>" target="_blank" rel="noopener noreferrer">View securely (<?= e(strtoupper(pathinfo($application['id_original_name'], PATHINFO_EXTENSION))) ?>)</a></div>
                        <div class="col-12"><strong>Experience:</strong><p class="mb-0" style="white-space:pre-wrap"><?= e($application['experience']) ?></p></div>
                    </div>
                    <form action="<?= e(base_url('admin/applications/' . (int)$application['id'] . '/review')) ?>" method="POST" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <div class="col-12 col-md-3"><label class="form-label" for="status-<?= (int)$application['id'] ?>">Decision</label><select class="form-select" id="status-<?= (int)$application['id'] ?>" name="status" required><option value="pending" <?= $application['status'] === 'pending' ? 'selected' : '' ?>>Pending</option><option value="approved" <?= $application['status'] === 'approved' ? 'selected' : '' ?>>Approved</option><option value="rejected" <?= $application['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option></select></div>
                        <div class="col-12 col-md-7"><label class="form-label" for="note-<?= (int)$application['id'] ?>">Internal note</label><input class="form-control" id="note-<?= (int)$application['id'] ?>" name="admin_note" maxlength="3000" value="<?= e($application['admin_note'] ?? '') ?>"></div>
                        <div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Save review</button></div>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php $this->call->view('templates/footer'); ?>
