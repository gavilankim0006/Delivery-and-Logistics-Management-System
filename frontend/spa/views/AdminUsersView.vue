<template>
  <section class="ds-page">
    <header class="ds-page-header">
      <div>
        <p class="ds-eyebrow">Administration</p>
        <h1 class="page-title">Users</h1>
        <p class="page-description">Create, search and manage staff and customer accounts.</p>
      </div>
      <button class="btn btn-primary" type="button" @click="startCreate">
        <i class="bi bi-person-plus" aria-hidden="true"></i> New user
      </button>
    </header>

    <div v-if="message" class="ds-notice ds-notice--success mb-3" role="status">
      <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>{{ message }}
    </div>
    <div v-if="error" class="ds-notice ds-notice--danger mb-3" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ error }}
    </div>

    <div class="surface mb-4" v-if="showForm">
      <h2 class="ds-section-title mb-3">{{ editingId ? 'Edit user' : 'Create user' }}</h2>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label" for="spa-user-name">Name</label>
          <input id="spa-user-name" v-model="form.name" class="form-control" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="spa-user-email">Email</label>
          <input id="spa-user-email" v-model="form.email" class="form-control" type="email" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="spa-user-phone">Phone</label>
          <input id="spa-user-phone" v-model="form.phone" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="spa-user-role">Role</label>
          <select id="spa-user-role" v-model="form.role_id" class="form-select">
            <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
          </select>
        </div>
        <div class="col-md-3" v-if="editingId">
          <label class="form-label" for="spa-user-status">Status</label>
          <select id="spa-user-status" v-model="form.status" class="form-select">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="spa-user-password">{{ editingId ? 'New password (optional)' : 'Password' }}</label>
          <input id="spa-user-password" v-model="form.password" type="password" class="form-control" autocomplete="new-password" />
        </div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary" type="button" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : (editingId ? 'Update user' : 'Create user') }}
        </button>
        <button class="btn btn-outline-secondary" type="button" @click="cancelForm">Cancel</button>
      </div>
    </div>

    <form class="surface ds-toolbar mb-3" @submit.prevent="load">
      <div class="flex-grow-1">
        <label class="form-label" for="spa-user-search">Search</label>
        <input id="spa-user-search" v-model="search" class="form-control" placeholder="Name or email" />
      </div>
      <div class="flex-grow-1">
        <label class="form-label" for="spa-user-filter">Role</label>
        <select id="spa-user-filter" v-model="roleFilter" class="form-select">
          <option value="">All roles</option>
          <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
        </select>
      </div>
      <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Apply</button>
    </form>

    <div v-if="loading" class="text-center py-5"><div class="spinner-border" role="status"></div></div>
    <div v-else-if="!users.length" class="surface ds-empty-state">
      <i class="bi bi-people" aria-hidden="true"></i>
      <p class="ds-empty-state__message">No users match these filters.</p>
    </div>

    <div v-else class="surface table-responsive">
      <table class="table ds-table table-responsive-stack align-middle mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th><span class="visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id">
            <td data-label="ID" class="text-muted">{{ user.id }}</td>
            <td data-label="Name" class="fw-semibold">{{ user.name }}</td>
            <td data-label="Email" class="small">{{ user.email }}</td>
            <td data-label="Role">{{ roleName(user.role_id) }}</td>
            <td data-label="Status">
              <span class="ds-status-chip" :class="user.status === 'active' ? 'ds-status-chip--active' : 'ds-status-chip--inactive'">
                {{ user.status }}
              </span>
            </td>
            <td data-label="Actions">
              <div class="d-flex flex-wrap gap-1 justify-content-end">
                <button class="btn btn-sm btn-outline-primary" type="button" @click="startEdit(user)">Edit</button>
                <button class="btn btn-sm btn-outline-secondary" type="button" :disabled="workingId === user.id" @click="toggle(user)">
                  {{ workingId === user.id ? '…' : (user.status === 'active' ? 'Deactivate' : 'Activate') }}
                </button>
                <button class="btn btn-sm btn-outline-danger" type="button" :disabled="workingId === user.id" @click="remove(user)">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup>
import { reactive, ref } from 'vue';

const props = defineProps({
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' }
});

const users = ref([]);
const roles = ref([]);
const loading = ref(true);
const saving = ref(false);
const workingId = ref(null);
const showForm = ref(false);
const editingId = ref(null);
const error = ref('');
const message = ref('');
const search = ref('');
const roleFilter = ref('');
const page = ref(1);
const total = ref(0);

const form = reactive({
  name: '',
  email: '',
  phone: '',
  role_id: 2,
  status: 'active',
  password: ''
});

const emptyForm = {
  name: '', email: '', phone: '', role_id: 2, status: 'active', password: ''
};

function roleName(id) {
  return roles.value.find((role) => Number(role.id) === Number(id))?.name || id;
}

async function loadRoles() {
  if (roles.value.length) return;
  const response = await fetch(`${props.baseUrl}admin/users/data`, { credentials: 'same-origin' });
  const result = await response.json();
  if (response.ok && result.success) roles.value = result.data.roles || [];
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    await loadRoles();
    const params = new URLSearchParams();
    if (search.value) params.set('search', search.value);
    if (roleFilter.value) params.set('role', roleFilter.value);
    params.set('page', page.value);
    const response = await fetch(`${props.baseUrl}admin/users/data?${params.toString()}`, { credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Could not load users.');
    users.value = result.data.users || [];
    roles.value = result.data.roles || roles.value;
    total.value = result.data.total || 0;
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

function resetForm() {
  Object.assign(form, emptyForm);
  editingId.value = null;
  showForm.value = false;
}

function startCreate() {
  resetForm();
  showForm.value = true;
  error.value = '';
  message.value = '';
}

function startEdit(user) {
  editingId.value = user.id;
  showForm.value = true;
  Object.assign(form, {
    name: user.name || '',
    email: user.email || '',
    phone: user.phone || '',
    role_id: Number(user.role_id || 2),
    status: user.status || 'active',
    password: ''
  });
}

function cancelForm() {
  resetForm();
}

async function save() {
  saving.value = true;
  error.value = '';
  message.value = '';
  const body = new URLSearchParams();
  Object.entries(form).forEach(([key, value]) => body.append(key, value ?? ''));
  body.append('csrf_token', props.csrfToken);
  const url = editingId.value
    ? `${props.baseUrl}admin/users/${editingId.value}/api`
    : `${props.baseUrl}admin/users/api`;
  try {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': props.csrfToken
      },
      body: body.toString()
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Could not save user.');
    message.value = result.message || 'User saved.';
    resetForm();
    await load();
  } catch (err) {
    error.value = err.message;
  } finally {
    saving.value = false;
  }
}

async function toggle(user) {
  workingId.value = user.id;
  error.value = '';
  message.value = '';
  const body = new URLSearchParams({ csrf_token: props.csrfToken });
  try {
    const response = await fetch(`${props.baseUrl}admin/users/${user.id}/api-toggle`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': props.csrfToken
      },
      body: body.toString()
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Could not toggle status.');
    message.value = result.message || 'Status updated.';
    await load();
  } catch (err) {
    error.value = err.message;
  } finally {
    workingId.value = null;
  }
}

async function remove(user) {
  if (!confirm(`Delete ${user.name}?`)) return;
  workingId.value = user.id;
  error.value = '';
  message.value = '';
  const body = new URLSearchParams({ csrf_token: props.csrfToken });
  try {
    const response = await fetch(`${props.baseUrl}admin/users/${user.id}/api-delete`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': props.csrfToken
      },
      body: body.toString()
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Could not delete user.');
    message.value = result.message || 'User removed.';
    await load();
  } catch (err) {
    error.value = err.message;
  } finally {
    workingId.value = null;
  }
}

load();
</script>
