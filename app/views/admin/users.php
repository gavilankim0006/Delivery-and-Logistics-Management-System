<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'User Management']);
?>

<div class="container page-shell py-4">
    <header class="page-header mb-4">
        <div>
            <h1 class="page-title">User and role management</h1>
            <p class="page-description">Review staff and customer accounts and update staff access.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">Add staff user</button>
    </header>

    <section class="surface surface-section mb-4" aria-label="Filter users">
        <form action="<?= base_url('admin/users') ?>" method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-5">
                <label for="user-search" class="form-label">Search users</label>
                <input id="user-search" type="search" name="search" class="form-control" placeholder="Name, email, or phone" value="<?= e($search) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label for="user-role" class="form-label">Role</label>
                <select id="user-role" name="role" class="form-select">
                    <option value="">All roles</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= ($role_filter === (int)$r['id']) ? 'selected' : '' ?>><?= ucfirst(e($r['name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 d-flex flex-wrap align-items-center gap-2">
                <button type="submit" class="btn btn-secondary">Apply filters</button>
                <?php if (!empty($search) || $role_filter !== null): ?>
                    <a href="<?= base_url('admin/users') ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
                <span class="small text-muted ms-md-auto"><?= number_format((int)$total) ?> users</span>
            </div>
        </form>
    </section>

    <section class="surface" aria-label="User list">
        <div class="table-responsive">
            <table class="table table-clean table-responsive-stack align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">Created</th>
                        <th scope="col" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No users match these filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td data-label="ID" class="text-muted">#<?= (int)$u['id'] ?></td>
                                <td data-label="Name" class="fw-medium">
                                    <?= e($u['name']) ?>
                                    <?php if (!empty($u['company_name'])): ?>
                                        <div class="small text-muted"><?= e($u['company_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Email" class="text-break"><?= e($u['email']) ?></td>
                                <td data-label="Phone"><?= e($u['phone'] ?: '—') ?></td>
                                <td data-label="Role">
                                    <?php if ($u['role_name'] === 'admin'): ?>
                                        <span class="role-chip role-chip--admin">Admin</span>
                                    <?php elseif ($u['role_name'] === 'dispatcher'): ?>
                                        <span class="role-chip role-chip--dispatcher">Dispatcher</span>
                                    <?php else: ?>
                                        <span class="role-chip role-chip--customer">Customer</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status">
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="availability-chip account-status-chip--active">Active</span>
                                    <?php else: ?>
                                        <span class="availability-chip account-status-chip--inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Created" class="text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                <td data-label="Action" class="text-end">
                                    <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                        <a class="btn btn-secondary" href="<?= base_url('admin/users/' . (int)$u['id']) ?>">View / edit</a>
                                        <?php if ($u['id'] != auth_id()): ?>
                                            <?php $next_status = ($u['status'] === 'active') ? 'deactivate' : 'activate'; ?>
                                            <form action="<?= base_url('admin/toggle_user_status/' . (int)$u['id']) ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to <?= $next_status ?> <?= e($u['name']) ?>?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-secondary"><?= ucfirst($next_status) ?> user</button>
                                            </form>
                                            <form action="<?= base_url('admin/users/' . (int)$u['id'] . '/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Remove <?= e($u['name']) ?>? This account will be hidden and cannot sign in.');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-outline-danger">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">Current user</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= base_url('admin/create_user') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h2 class="modal-title h5" id="createUserModalLabel">Create staff account</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="staff-name" class="form-label">Full name <span class="text-danger">(required)</span></label>
                        <input id="staff-name" type="text" name="name" class="form-control" required autocomplete="name" placeholder="e.g. Alex Garcia">
                    </div>
                    <div class="mb-3">
                        <label for="staff-email" class="form-label">Email address <span class="text-danger">(required)</span></label>
                        <input id="staff-email" type="email" name="email" class="form-control" required autocomplete="email" placeholder="name@delivery.local">
                    </div>
                    <div class="mb-3">
                        <label for="staff-phone" class="form-label">Phone number</label>
                        <input id="staff-phone" type="tel" name="phone" class="form-control" autocomplete="tel" placeholder="+639171234567">
                    </div>
                    <div class="mb-3">
                        <label for="staff-role" class="form-label">System role <span class="text-danger">(required)</span></label>
                        <select id="staff-role" name="role_id" class="form-select" required>
                            <option value="2">Dispatcher (operations and board)</option>
                            <option value="1">Administrator (full access)</option>
                        </select>
                    </div>
                    <div>
                        <label for="staff-password" class="form-label">Temporary password <span class="text-danger">(required)</span></label>
                        <input id="staff-password" type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create user</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $this->call->view('templates/footer'); ?>
