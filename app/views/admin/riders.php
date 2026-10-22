<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Rider Fleet Management']);
?>

<div class="container page-shell py-4">
    <header class="page-header mb-4">
        <div>
            <h1 class="page-title">Rider fleet</h1>
            <p class="page-description">Manage courier contacts, vehicle details, and availability.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRiderModal">Add rider</button>
    </header>

    <section class="surface surface-section mb-4" aria-label="Filter riders">
        <form action="<?= base_url('admin/riders') ?>" method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-5">
                <label for="rider-status" class="form-label">Rider status</label>
                <select id="rider-status" name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="available" <?= ($status_filter === 'available') ? 'selected' : '' ?>>Available</option>
                    <option value="busy" <?= ($status_filter === 'busy') ? 'selected' : '' ?>>Busy (on delivery)</option>
                    <option value="offline" <?= ($status_filter === 'offline') ? 'selected' : '' ?>>Offline</option>
                </select>
            </div>
            <div class="col-12 col-md-7 d-flex flex-wrap align-items-center gap-2">
                <button type="submit" class="btn btn-secondary">Apply filter</button>
                <?php if ($status_filter !== null): ?>
                    <a href="<?= base_url('admin/riders') ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
                <span class="small text-muted ms-md-auto"><?= number_format(count($riders)) ?> riders in this list</span>
            </div>
        </form>
    </section>

    <section class="surface" aria-label="Rider list">
        <div class="table-responsive">
            <table class="table table-clean table-responsive-stack align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Rider</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Vehicle</th>
                        <th scope="col">Plate number</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($riders)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No riders match this filter.</td></tr>
                    <?php else: ?>
                        <?php foreach ($riders as $r): ?>
                            <tr>
                                <td data-label="ID" class="text-muted">#<?= (int)$r['id'] ?></td>
                                <td data-label="Rider" class="fw-medium"><?= e($r['name']) ?></td>
                                <td data-label="Phone"><?= e($r['phone']) ?></td>
                                <td data-label="Vehicle"><i class="bi bi-bicycle me-1" aria-hidden="true"></i><?= e($r['vehicle_type']) ?></td>
                                <td data-label="Plate number"><span class="font-monospace"><?= e($r['plate_number']) ?></span></td>
                                <td data-label="Status">
                                    <?php if ($r['status'] === 'available'): ?>
                                        <span class="availability-chip availability-chip--available">Available</span>
                                    <?php elseif ($r['status'] === 'busy'): ?>
                                        <span class="availability-chip availability-chip--busy">Busy</span>
                                    <?php else: ?>
                                        <span class="availability-chip availability-chip--offline">Offline</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions" class="text-end">
                                    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#editRiderModal<?= (int)$r['id'] ?>">Edit</button>
                                    <button type="button" class="btn btn-link text-danger" data-bs-toggle="modal" data-bs-target="#deleteRiderModal<?= (int)$r['id'] ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php foreach ($riders as $r): ?>
    <div class="modal fade" id="editRiderModal<?= (int)$r['id'] ?>" tabindex="-1" aria-labelledby="editRiderModalLabel<?= (int)$r['id'] ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?= base_url('admin/update_rider/' . $r['id']) ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="editRiderModalLabel<?= (int)$r['id'] ?>">Edit rider #<?= (int)$r['id'] ?></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="rider-name-<?= (int)$r['id'] ?>" class="form-label">Full name</label>
                            <input id="rider-name-<?= (int)$r['id'] ?>" type="text" name="name" class="form-control" value="<?= e($r['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="rider-phone-<?= (int)$r['id'] ?>" class="form-label">Phone</label>
                            <input id="rider-phone-<?= (int)$r['id'] ?>" type="tel" name="phone" class="form-control" value="<?= e($r['phone']) ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="rider-vehicle-<?= (int)$r['id'] ?>" class="form-label">Vehicle type</label>
                                <select id="rider-vehicle-<?= (int)$r['id'] ?>" name="vehicle_type" class="form-select">
                                    <option value="Motorcycle" <?= $r['vehicle_type'] === 'Motorcycle' ? 'selected' : '' ?>>Motorcycle</option>
                                    <option value="Sedan" <?= $r['vehicle_type'] === 'Sedan' ? 'selected' : '' ?>>Sedan</option>
                                    <option value="MPV" <?= $r['vehicle_type'] === 'MPV' ? 'selected' : '' ?>>MPV</option>
                                    <option value="Van" <?= $r['vehicle_type'] === 'Van' ? 'selected' : '' ?>>Van</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="rider-plate-<?= (int)$r['id'] ?>" class="form-label">Plate number</label>
                                <input id="rider-plate-<?= (int)$r['id'] ?>" type="text" name="plate_number" class="form-control" value="<?= e($r['plate_number']) ?>" required>
                            </div>
                        </div>
                        <div>
                            <label for="rider-status-<?= (int)$r['id'] ?>" class="form-label">Status</label>
                            <select id="rider-status-<?= (int)$r['id'] ?>" name="status" class="form-select">
                                <option value="available" <?= $r['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                                <option value="busy" <?= $r['status'] === 'busy' ? 'selected' : '' ?>>Busy</option>
                                <option value="offline" <?= $r['status'] === 'offline' ? 'selected' : '' ?>>Offline</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteRiderModal<?= (int)$r['id'] ?>" tabindex="-1" aria-labelledby="deleteRiderModalLabel<?= (int)$r['id'] ?>" aria-describedby="deleteRiderModalDescription<?= (int)$r['id'] ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?= base_url('admin/delete_rider/' . $r['id']) ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="deleteRiderModalLabel<?= (int)$r['id'] ?>">Delete this rider?</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0" id="deleteRiderModalDescription<?= (int)$r['id'] ?>">This will remove the rider record for <strong><?= e($r['name']) ?></strong> (<?= e($r['plate_number']) ?>). This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete rider</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="createRiderModal" tabindex="-1" aria-labelledby="createRiderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= base_url('admin/create_rider') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h2 class="modal-title h5" id="createRiderModalLabel">Register new rider</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new-rider-name" class="form-label">Full name <span class="text-danger">(required)</span></label>
                        <input id="new-rider-name" type="text" name="name" class="form-control" required autocomplete="name" placeholder="e.g. Mario Batungbacal">
                    </div>
                    <div class="mb-3">
                        <label for="new-rider-phone" class="form-label">Phone number <span class="text-danger">(required)</span></label>
                        <input id="new-rider-phone" type="tel" name="phone" class="form-control" required autocomplete="tel" placeholder="+639181112233">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new-rider-vehicle" class="form-label">Vehicle type</label>
                            <select id="new-rider-vehicle" name="vehicle_type" class="form-select">
                                <option value="Motorcycle" selected>Motorcycle</option>
                                <option value="Sedan">Sedan</option>
                                <option value="MPV">MPV</option>
                                <option value="Van">Van</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="new-rider-plate" class="form-label">Plate number <span class="text-danger">(required)</span></label>
                            <input id="new-rider-plate" type="text" name="plate_number" class="form-control" required placeholder="ABC-1234">
                        </div>
                    </div>
                    <div>
                        <label for="new-rider-status" class="form-label">Initial status</label>
                        <select id="new-rider-status" name="status" class="form-select">
                            <option value="available" selected>Available</option>
                            <option value="busy">Busy</option>
                            <option value="offline">Offline</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save rider</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $this->call->view('templates/footer'); ?>
