<template>
  <div class="container page-shell py-4">
    <div v-for="(flash, index) in flashes" :key="index" class="alert" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
      {{ flash.message }}
    </div>

    <header class="page-header mb-4">
      <div>
        <p class="ds-eyebrow">Administration</p>
        <h1 class="page-title">User and role management</h1>
        <p class="page-description">Review staff and customer accounts and update staff access.</p>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">Add staff user</button>
    </header>

    <section class="surface surface-section mb-4" aria-label="Filter users">
      <form :action="`${baseUrl}admin/users`" method="GET" class="row g-3 align-items-end">
        <div class="col-12 col-md-5">
          <label for="user-search" class="form-label">Search users</label>
          <input id="user-search" type="search" name="search" class="form-control" placeholder="Name, email, or phone" :value="search">
        </div>
        <div class="col-12 col-md-3">
          <label for="user-role" class="form-label">Role</label>
          <select id="user-role" name="role" class="form-select">
            <option value="">All roles</option>
            <option v-for="role in roles" :key="role.id" :value="role.id" :selected="roleFilter === Number(role.id)">
              {{ capitalize(role.name) }}
            </option>
          </select>
        </div>
        <div class="col-12 col-md-4 d-flex flex-wrap align-items-center gap-2">
          <button type="submit" class="btn btn-secondary">Apply filters</button>
          <a v-if="search || roleFilter !== null" :href="`${baseUrl}admin/users`" class="btn btn-secondary">Clear</a>
          <span class="small text-muted ms-md-auto">{{ Number(total).toLocaleString() }} users</span>
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
            <tr v-if="!users || !users.length">
              <td colspan="8" class="text-center py-4 text-muted">No users match these filters.</td>
            </tr>
            <tr v-for="user in users" v-else :key="user.id">
              <td data-label="ID" class="text-muted">#{{ user.id }}</td>
              <td data-label="Name" class="fw-medium">
                {{ user.name }}
                <div v-if="user.company_name" class="small text-muted">{{ user.company_name }}</div>
              </td>
              <td data-label="Email" class="text-break">{{ user.email }}</td>
              <td data-label="Phone">{{ user.phone || '—' }}</td>
              <td data-label="Role">
                <span v-if="user.role_name === 'admin'" class="role-chip role-chip--admin">Admin</span>
                <span v-else-if="user.role_name === 'dispatcher'" class="role-chip role-chip--dispatcher">Dispatcher</span>
                <span v-else-if="user.role_name === 'rider'" class="role-chip">Rider</span>
                <span v-else class="role-chip role-chip--customer">Customer</span>
              </td>
              <td data-label="Status">
                <span v-if="user.status === 'active'" class="availability-chip account-status-chip--active">Active</span>
                <span v-else class="availability-chip account-status-chip--inactive">Inactive</span>
              </td>
              <td data-label="Created" class="text-muted">{{ formatDate(user.created_at) }}</td>
              <td data-label="Action" class="text-end">
                <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                  <a v-if="user.role_name !== 'rider'" class="btn btn-secondary" :href="`${baseUrl}admin/users/${user.id}`">View / edit</a>
                  <span v-else class="text-muted">Manage link in Riders</span>
                  <template v-if="String(user.id) !== String(currentUserId)">
                    <form :action="`${baseUrl}admin/toggle_user_status/${user.id}`" method="POST" class="d-inline" onsubmit="return confirm(`Are you sure you want to ${nextUserStatus(user)} ${user.name}?`);">
                      <input type="hidden" name="csrf_token" :value="csrfToken">
                      <button type="submit" class="btn btn-secondary">{{ capitalize(nextUserStatus(user)) }} user</button>
                    </form>
                    <form :action="`${baseUrl}admin/users/${user.id}/delete`" method="POST" class="d-inline" onsubmit="return confirm(`Remove ${user.name}? This account will be hidden and cannot sign in.`);">
                      <input type="hidden" name="csrf_token" :value="csrfToken">
                      <button type="submit" class="btn btn-outline-danger" data-busy-text="Deleting…">Delete</button>
                    </form>
                  </template>
                  <span v-else class="text-muted">Current user</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form :action="`${baseUrl}admin/create_user`" method="POST">
            <input type="hidden" name="csrf_token" :value="csrfToken">
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
                  <option value="4">Rider (limited delivery portal)</option>
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
  </div>
</template>

<script setup>
const props = defineProps({
  users: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  total: { type: [Number, String], default: 0 },
  search: { type: String, default: '' },
  roleFilter: { type: [Number, String, null], default: null },
  page: { type: [Number, String], default: 1 },
  limit: { type: [Number, String], default: 20 },
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' },
  flashes: { type: Array, default: () => [] },
  currentUserId: { type: [Number, String, null], default: null },
});

function capitalize(value) {
  const text = String(value || '');
  return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
}

function formatDate(value) {
  if (!value) return '—';
  const date = new Date(String(value).replace(' ', 'T'));
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleDateString(undefined, { month: 'short', day: '2-digit', year: 'numeric' });
}

function nextUserStatus(user) {
  return user.status === 'active' ? 'deactivate' : 'activate';
}
</script>