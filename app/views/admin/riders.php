<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Rider Fleet Management']);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Rider Fleet Management</h1>
            <p class="text-muted small mb-0">Couriers and vehicles representing Lalamove drivers & in-house fleet</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createRiderModal">
            <i class="bi bi-plus-circle me-1"></i> Add Rider
        </button>
    </div>

    <!-- Filter Bar -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body py-3">
            <form action="<?= base_url('admin/riders') ?>" method="GET" class="d-flex gap-2 align-items-center">
                <span class="text-muted small fw-semibold">Status:</span>
                <select name="status" class="form-select form-select-sm" style="max-width: 200px;" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="available" <?= ($status_filter === 'available') ? 'selected' : '' ?>>Available</option>
                    <option value="busy" <?= ($status_filter === 'busy') ? 'selected' : '' ?>>Busy (On Delivery)</option>
                    <option value="offline" <?= ($status_filter === 'offline') ? 'selected' : '' ?>>Offline</option>
                </select>
                <?php if ($status_filter !== null): ?>
                    <a href="<?= base_url('admin/riders') ?>" class="btn btn-outline-secondary btn-sm">Clear Filter</a>
                <?php endif; ?>
                <span class="text-muted small ms-auto">Total: <?= count($riders) ?> Riders</span>
            </form>
        </div>
    </div>

    <!-- Riders Table -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Rider Name</th>
                        <th>Contact Phone</th>
                        <th>Vehicle Type</th>
                        <th>Plate Number</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($riders)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No riders found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($riders as $r): ?>
                            <tr>
                                <td class="text-muted">#<?= $r['id'] ?></td>
                                <td class="fw-semibold"><?= e($r['name']) ?></td>
                                <td><?= e($r['phone']) ?></td>
                                <td>
                                    <i class="bi bi-bicycle me-1 text-primary"></i> <?= e($r['vehicle_type']) ?>
                                </td>
                                <td><span class="badge bg-light text-dark border font-monospace"><?= e($r['plate_number']) ?></span></td>
                                <td>
                                    <?php if ($r['status'] === 'available'): ?>
                                        <span class="badge bg-success">Available</span>
                                    <?php elseif ($r['status'] === 'busy'): ?>
                                        <span class="badge bg-warning text-dark">Busy</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Offline</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.75rem;"
                                            data-bs-toggle="modal" data-bs-target="#editRiderModal<?= $r['id'] ?>">
                                        Edit
                                    </button>
                                    <form action="<?= base_url('admin/delete_rider/' . $r['id']) ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete rider <?= e($r['name']) ?>?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.75rem;">Delete</button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Modal for this Rider -->
                            <div class="modal fade" id="editRiderModal<?= $r['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="<?= base_url('admin/update_rider/' . $r['id']) ?>" method="POST">
                                            <?= csrf_field() ?>
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold">Edit Rider #<?= $r['id'] ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body text-start">
                                                <div class="mb-3">
                                                    <label class="form-label">Full Name</label>
                                                    <input type="text" name="name" class="form-control" value="<?= e($r['name']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Phone</label>
                                                    <input type="text" name="phone" class="form-control" value="<?= e($r['phone']) ?>" required>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Vehicle Type</label>
                                                        <select name="vehicle_type" class="form-select">
                                                            <option value="Motorcycle" <?= $r['vehicle_type'] === 'Motorcycle' ? 'selected' : '' ?>>Motorcycle</option>
                                                            <option value="Sedan" <?= $r['vehicle_type'] === 'Sedan' ? 'selected' : '' ?>>Sedan</option>
                                                            <option value="MPV" <?= $r['vehicle_type'] === 'MPV' ? 'selected' : '' ?>>MPV</option>
                                                            <option value="Van" <?= $r['vehicle_type'] === 'Van' ? 'selected' : '' ?>>Van</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Plate Number</label>
                                                        <input type="text" name="plate_number" class="form-control" value="<?= e($r['plate_number']) ?>" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-select">
                                                        <option value="available" <?= $r['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                                                        <option value="busy" <?= $r['status'] === 'busy' ? 'selected' : '' ?>>Busy</option>
                                                        <option value="offline" <?= $r['status'] === 'offline' ? 'selected' : '' ?>>Offline</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Rider Modal -->
<div class="modal fade" id="createRiderModal" tabindex="-1" aria-labelledby="createRiderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= base_url('admin/create_rider') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="createRiderModalLabel">Register New Rider</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Mario Batungbacal">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" required placeholder="+639181112233">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehicle Type</label>
                            <select name="vehicle_type" class="form-select">
                                <option value="Motorcycle" selected>Motorcycle</option>
                                <option value="Sedan">Sedan</option>
                                <option value="MPV">MPV</option>
                                <option value="Van">Van</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Plate Number <span class="text-danger">*</span></label>
                            <input type="text" name="plate_number" class="form-control" required placeholder="ABC-1234">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Initial Status</label>
                        <select name="status" class="form-select">
                            <option value="available" selected>Available</option>
                            <option value="busy">Busy</option>
                            <option value="offline">Offline</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Rider</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $this->call->view('templates/footer'); ?>
