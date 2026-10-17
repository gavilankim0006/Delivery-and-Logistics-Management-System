<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'User Management']);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">User & Role Management</h1>
            <p class="text-muted small mb-0">System accounts, permissions, and status controls</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="bi bi-person-plus-fill me-1"></i> Add Staff User
        </button>
    </div>

    <!-- Filter Bar -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body py-3">
            <form action="<?= base_url('admin/users') ?>" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, email, or phone..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ($role_filter === (int)$r['id']) ? 'selected' : '' ?>>
                                <?= ucfirst(e($r['name'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary btn-sm"><i class="bi bi-funnel me-1"></i> Filter</button>
                    <?php if (!empty($search) || $role_filter !== null): ?>
                        <a href="<?= base_url('admin/users') ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
                    <?php endif; ?>
                    <span class="text-muted small ms-auto align-self-center">Total: <?= $total ?></span>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No users matched criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="text-muted">#<?= $u['id'] ?></td>
                                <td class="fw-semibold">
                                    <?= e($u['name']) ?>
                                    <?php if (!empty($u['company_name'])): ?>
                                        <div class="text-muted small" style="font-size: 0.75rem;"><?= e($u['company_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($u['email']) ?></td>
                                <td><?= e($u['phone'] ?: '—') ?></td>
                                <td>
                                    <?php if ($u['role_name'] === 'admin'): ?>
                                        <span class="badge bg-danger">Admin</span>
                                    <?php elseif ($u['role_name'] === 'dispatcher'): ?>
                                        <span class="badge bg-warning text-dark">Dispatcher</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Customer</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                <td class="text-end">
                                    <?php if ($u['id'] != auth_id()): ?>
                                        <form action="<?= base_url('admin/toggle_user_status/' . $u['id']) ?>" method="POST" class="d-inline" onsubmit="return confirm('Toggle status for <?= e($u['name']) ?>?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm <?= ($u['status'] === 'active') ? 'btn-outline-danger' : 'btn-outline-success' ?> py-0 px-2" style="font-size: 0.75rem;">
                                                <?= ($u['status'] === 'active') ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Staff Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= base_url('admin/create_user') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="createUserModalLabel">Create Staff Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Alex Garcia">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required placeholder="name@delivery.local">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="+639171234567">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">System Role <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <option value="2">Dispatcher (Operations & Board)</option>
                            <option value="1">Administrator (Full Access)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Temporary Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="8" placeholder="At least 8 characters">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $this->call->view('templates/footer'); ?>
