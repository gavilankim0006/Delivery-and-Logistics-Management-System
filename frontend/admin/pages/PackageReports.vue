<template>
  <div class="container page-shell py-4 ds-page">
    <header class="page-header mb-4">
      <div>
        <p class="ds-eyebrow">Administration</p>
        <h1 class="page-title">Missing-package reports</h1>
        <p class="page-description">Missing-package reports submitted by customers and staff.</p>
      </div>
    </header>

    <div v-for="(flash, index) in flashes" :key="index" class="alert alert-dismissible fade show" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
      <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
      {{ flash.message }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <div v-if="!reports.length" class="surface ds-empty-state">
      <i class="bi bi-inbox" aria-hidden="true"></i>
      <p class="ds-empty-state__message">No reports submitted.</p>
    </div>

    <div v-else class="surface table-responsive">
      <table class="table ds-table align-middle mb-0">
        <thead>
          <tr>
            <th>Order</th>
            <th>Customer</th>
            <th>Description</th>
            <th>Submitted</th>
            <th>Manage</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="report in reports" :key="report.id">
            <td class="fw-semibold">{{ report.order_no }}</td>
            <td>
              {{ report.customer_name }}
              <span class="d-block small text-muted">{{ report.email }}</span>
            </td>
            <td class="report-cell">
              <span class="report-text">{{ report.description }}</span>
              <span class="ds-status-chip mt-2" :class="`ds-status-chip--${statusTone(report.status)}`">{{ capitalize(report.status) }}</span>
              <p v-if="report.admin_note" class="report-text text-muted mb-0 mt-2">{{ report.admin_note }}</p>
            </td>
            <td class="small text-muted text-nowrap">{{ report.created_at }}</td>
            <td class="report-manage">
              <form :action="`${baseUrl}admin/package-reports/${report.id}`" method="POST">
                <input type="hidden" name="csrf_token" :value="csrfToken" />
                <select name="status" class="form-select mb-2" :aria-label="`Status for ${report.order_no}`">
                  <option v-for="status in statuses" :key="status" :value="status"
                          :selected="report.status === status">
                    {{ capitalize(status) }}
                  </option>
                </select>
                <textarea name="admin_note" maxlength="3000" class="form-control mb-2"
                          placeholder="Admin note" :aria-label="`Admin note for ${report.order_no}`" :value="report.admin_note || ''"></textarea>
                <button class="btn btn-primary btn-sm">Save</button>
              </form>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  reports: { type: Array, default: () => [] },
  csrfToken: { type: String, default: '' },
  baseUrl: { type: String, default: '/' },
  flashes: { type: Array, default: () => [] }
});

const statuses = ['open', 'investigating', 'resolved', 'rejected'];

function capitalize(value) {
  return value.charAt(0).toUpperCase() + value.slice(1);
}

function safeText(value) {
  return String(value ?? '').replace(/</g, '&lt;');
}

function statusTone(status) {
  if (status === 'resolved') return 'delivered';
  if (status === 'rejected') return 'failed';
  if (status === 'investigating') return 'rider-assigned';
  return 'pending';
}
</script>

<style scoped>
.report-text {
  display: inline-block;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.report-manage { min-width: 240px; }
</style>