<template>
  <section class="ds-page">
    <header class="ds-page-header">
      <div>
        <p class="ds-eyebrow">Administration</p>
        <h1 class="page-title">Rider fleet</h1>
        <p class="page-description">Review riders, their vehicles and account links.</p>
      </div>
      <a class="btn btn-outline-primary" :href="`${baseUrl}admin/riders`" target="_blank" rel="noopener">
        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Manage riders
      </a>
    </header>

    <div v-if="error" class="ds-notice ds-notice--danger mb-3" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ error }}
    </div>

    <div class="surface ds-toolbar mb-3">
      <div class="flex-grow-1" style="max-width: 260px;">
        <label class="form-label" for="spa-rider-status">Status</label>
        <select id="spa-rider-status" v-model="statusFilter" class="form-select" @change="load">
          <option value="">All statuses</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="text-center py-5"><div class="spinner-border" role="status"></div></div>
    <div v-else-if="!riders.length" class="surface ds-empty-state">
      <i class="bi bi-bicycle" aria-hidden="true"></i>
      <p class="ds-empty-state__message">No riders match this filter.</p>
    </div>

    <div v-else class="surface table-responsive">
      <table class="table ds-table table-responsive-stack align-middle mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Vehicle</th>
            <th>Status</th>
            <th>User account</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rider in riders" :key="rider.id">
            <td data-label="ID" class="text-muted">{{ rider.id }}</td>
            <td data-label="Name" class="fw-semibold">{{ rider.name }}</td>
            <td data-label="Phone" class="small">{{ rider.phone }}</td>
            <td data-label="Vehicle" class="small">{{ rider.vehicle_type || '—' }} {{ rider.plate_number ? `(${rider.plate_number})` : '' }}</td>
            <td data-label="Status">
              <span class="ds-status-chip" :class="rider.status === 'active' ? 'ds-status-chip--active' : 'ds-status-chip--inactive'">
                {{ rider.status }}
              </span>
            </td>
            <td data-label="User account" class="small">{{ rider.user_id ? rider.user_name || rider.user_id : 'Unlinked' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' }
});

const riders = ref([]);
const unlinked = ref([]);
const loading = ref(true);
const error = ref('');
const statusFilter = ref('');

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const params = new URLSearchParams();
    if (statusFilter.value) params.set('status', statusFilter.value);
    const response = await fetch(`${props.baseUrl}admin/riders/data?${params.toString()}`, { credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Could not load riders.');
    riders.value = result.data.riders || [];
    unlinked.value = result.data.unlinkedUsers || [];
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

load();
</script>
