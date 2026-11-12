<template>
  <section class="ds-page">
    <header class="ds-page-header">
      <div>
        <p class="ds-eyebrow">Account</p>
        <h1 class="page-title">Saved addresses</h1>
        <p class="page-description">Reuse pickup and drop-off places when creating a delivery.</p>
      </div>
      <button class="btn btn-primary" type="button" @click="startNew">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> New address
      </button>
    </header>

    <div v-if="message" class="ds-notice ds-notice--success mb-3" role="status">
      <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>{{ message }}
    </div>
    <div v-if="error" class="ds-notice ds-notice--danger mb-3" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ error }}
    </div>

    <div class="surface mb-4">
      <h2 class="ds-section-title mb-3">{{ editingId ? 'Edit address' : 'Add address' }}</h2>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label" for="spa-addr-label">Label</label>
          <input id="spa-addr-label" v-model="form.label" class="form-control" type="text" />
          <div v-if="fieldErrors.label" class="invalid-feedback d-block">{{ fieldErrors.label }}</div>
        </div>
        <div class="col-md-8">
          <label class="form-label" for="spa-addr-line">Address line</label>
          <input id="spa-addr-line" v-model="form.address_line" class="form-control" type="text" required />
          <div v-if="fieldErrors.address_line" class="invalid-feedback d-block">{{ fieldErrors.address_line }}</div>
        </div>

        <div class="col-md-4"><label class="form-label" for="spa-addr-unit">Unit number</label><input id="spa-addr-unit" v-model="form.unit_number" class="form-control" /></div>
        <div class="col-md-4"><label class="form-label" for="spa-addr-street">Street</label><input id="spa-addr-street" v-model="form.street" class="form-control" /></div>
        <div class="col-md-4"><label class="form-label" for="spa-addr-barangay">Barangay</label><input id="spa-addr-barangay" v-model="form.barangay" class="form-control" /></div>
        <div class="col-md-4"><label class="form-label" for="spa-addr-city">City</label><input id="spa-addr-city" v-model="form.city" class="form-control" /></div>
        <div class="col-md-4"><label class="form-label" for="spa-addr-province">Province</label><input id="spa-addr-province" v-model="form.province" class="form-control" /></div>
        <div class="col-md-4"><label class="form-label" for="spa-addr-contact">Contact name</label><input id="spa-addr-contact" v-model="form.contact_name" class="form-control" required /></div>
        <div class="col-md-4">
          <label class="form-label" for="spa-addr-phone">Contact phone</label>
          <input id="spa-addr-phone" v-model="form.contact_phone" class="form-control" required />
          <div v-if="fieldErrors.contact_phone" class="invalid-feedback d-block">{{ fieldErrors.contact_phone }}</div>
        </div>
        <div class="col-12">
          <label class="form-label" for="spa-addr-notes">Notes</label>
          <textarea id="spa-addr-notes" v-model="form.notes" class="form-control" rows="2"></textarea>
          <div v-if="fieldErrors.notes" class="invalid-feedback d-block">{{ fieldErrors.notes }}</div>
        </div>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary" type="button" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : (editingId ? 'Update address' : 'Save address') }}
        </button>
        <button v-if="editingId || isDirty()" class="btn btn-outline-secondary" type="button" @click="resetForm">Cancel</button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border" role="status"></div>
    </div>

    <div v-else-if="!addresses.length" class="surface ds-empty-state">
      <i class="bi bi-geo-alt" aria-hidden="true"></i>
      <p class="ds-empty-state__message">No saved addresses yet.</p>
    </div>

    <div v-else class="row g-3">
      <div v-for="address in addresses" :key="address.id" class="col-md-6">
        <article class="surface h-100 d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <h3 class="h6 mb-1">{{ address.label || 'Address' }}</h3>
            <span class="ds-status-chip ds-status-chip--draft">#{{ address.id }}</span>
          </div>
          <p class="mb-1 mt-2">{{ address.address_line }}</p>
          <p v-if="address.contact_name" class="mb-1 small text-muted">
            <i class="bi bi-person me-1" aria-hidden="true"></i>{{ address.contact_name }} · {{ address.contact_phone }}
          </p>
          <p v-if="address.notes" class="small text-muted mb-0">{{ address.notes }}</p>
          <div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
            <button class="btn btn-sm btn-outline-primary" type="button" @click="edit(address)">Edit</button>
            <button class="btn btn-sm btn-outline-danger" type="button" :disabled="deletingId === address.id" @click="remove(address)">
              {{ deletingId === address.id ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { reactive, ref } from 'vue';

const props = defineProps({
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' }
});

const addresses = ref([]);
const loading = ref(false);
const saving = ref(false);
const deletingId = ref(null);
const editingId = ref(null);
const message = ref('');
const error = ref('');
const fieldErrors = ref({});

const emptyForm = {
  label: '',
  address_line: '',
  unit_number: '',
  street: '',
  barangay: '',
  city: '',
  province: '',
  contact_name: '',
  contact_phone: '',
  notes: ''
};

const form = reactive({ ...emptyForm });

function resetForm() {
  editingId.value = null;
  Object.assign(form, emptyForm);
  fieldErrors.value = {};
}

function isDirty() {
  return Object.keys(emptyForm).some((key) => form[key] !== '');
}

function apiUrl(id = null) {
  if (id) return `${props.baseUrl}addresses/${id}/api`;
  return `${props.baseUrl}addresses/api`;
}

async function loadAddresses() {
  loading.value = true;
  error.value = '';
  try {
    const response = await fetch(`${props.baseUrl}addresses/data`, { credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.error || 'Could not load addresses.');
    }
    addresses.value = result.data || [];
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

function edit(address) {
  editingId.value = address.id;
  Object.assign(form, { ...emptyForm, ...address });
  fieldErrors.value = {};
  message.value = '';
  error.value = '';
}

function startNew() {
  resetForm();
  message.value = '';
  error.value = '';
}

async function save() {
  saving.value = true;
  message.value = '';
  error.value = '';
  fieldErrors.value = {};

  const body = new URLSearchParams();
  Object.entries(form).forEach(([key, value]) => body.append(key, value ?? ''));
  body.append('csrf_token', props.csrfToken);

  const url = editingId.value ? apiUrl(editingId.value) : apiUrl();
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
    if (!response.ok || !result.success) {
      fieldErrors.value = result.errors || {};
      throw new Error(result.error || 'Could not save address.');
    }
    message.value = result.message || 'Address saved.';
    resetForm();
    await loadAddresses();
  } catch (err) {
    if (!fieldErrors.value || !Object.keys(fieldErrors.value).length) {
      error.value = err.message;
    }
  } finally {
    saving.value = false;
  }
}

async function remove(address) {
  if (!confirm(`Delete ${address.label || 'this address'}?`)) return;
  deletingId.value = address.id;
  error.value = '';
  message.value = '';
  const body = new URLSearchParams({ csrf_token: props.csrfToken });
  try {
    const response = await fetch(`${props.baseUrl}addresses/${address.id}/api-delete`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': props.csrfToken
      },
      body: body.toString()
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.error || 'Could not delete address.');
    }
    message.value = result.message || 'Address deleted.';
    await loadAddresses();
  } catch (err) {
    error.value = err.message;
  } finally {
    deletingId.value = null;
  }
}

loadAddresses();
</script>
