<template>
  <div class="container page-shell py-4">
    <header class="page-header mb-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <p class="ds-eyebrow">Administration</p>
        <h1 class="page-title">Reports</h1>
        <p class="page-description">Read-only operational delivery and recorded payment summaries. Payment data reflects stored records, not live payment-provider reconciliation.</p>
      </div>
      <a v-if="exportQuery" class="btn btn-primary" :href="`${baseUrl}admin/reports?${exportQuery}`">
        <i class="bi bi-download me-1" aria-hidden="true"></i>Export CSV
      </a>
    </header>

    <div v-for="(flash, index) in flashes" :key="index" class="alert alert-dismissible fade show" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
      <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
      {{ flash.message }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <div v-if="reportError" class="alert alert-warning" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ reportError }}
    </div>

    <!-- Summary KPI cards -->
    <section v-if="summary && Object.keys(summary).length" class="row g-3 mb-4" aria-label="Report summary metrics">
      <template v-if="currentType === 'payments'">
        <div class="col-6 col-md-3">
          <article class="surface ds-metric-card h-100">
            <span class="ds-metric-card__label"><i class="bi bi-credit-card" aria-hidden="true"></i> Payments</span>
            <span class="ds-metric-card__value">{{ Number(summary.total_payments || 0).toLocaleString() }}</span>
          </article>
        </div>
        <div class="col-6 col-md-3">
          <article class="surface ds-metric-card h-100">
            <span class="ds-metric-card__label"><i class="bi bi-receipt" aria-hidden="true"></i> Recorded amount</span>
            <span class="ds-metric-card__value money">₱{{ formatMoney(summary.total_amount) }}</span>
          </article>
        </div>
        <div class="col-6 col-md-3">
          <article class="surface ds-metric-card h-100">
            <span class="ds-metric-card__label"><i class="bi bi-check2-circle" aria-hidden="true"></i> Paid amount</span>
            <span class="ds-metric-card__value money text-success">₱{{ formatMoney(summary.paid_amount) }}</span>
          </article>
        </div>
        <div class="col-6 col-md-3">
          <article class="surface ds-metric-card h-100">
            <span class="ds-metric-card__label"><i class="bi bi-percent" aria-hidden="true"></i> Payment fees</span>
            <span class="ds-metric-card__value money">₱{{ formatMoney(summary.fee_amount) }}</span>
          </article>
        </div>
      </template>
      <template v-else>
        <div class="col-6 col-md-4">
          <article class="surface ds-metric-card h-100">
            <span class="ds-metric-card__label"><i class="bi bi-box-seam" aria-hidden="true"></i> Orders</span>
            <span class="ds-metric-card__value">{{ Number(summary.total_orders || 0).toLocaleString() }}</span>
          </article>
        </div>
        <div class="col-6 col-md-4">
          <article class="surface ds-metric-card h-100">
            <span class="ds-metric-card__label"><i class="bi bi-truck" aria-hidden="true"></i> Delivery fees</span>
            <span class="ds-metric-card__value money">₱{{ formatMoney(summary.delivery_fees) }}</span>
          </article>
        </div>
        <div class="col-12 col-md-4">
          <article class="surface ds-metric-card h-100">
            <span class="ds-metric-card__label"><i class="bi bi-cash-stack" aria-hidden="true"></i> Recorded paid amount</span>
            <span class="ds-metric-card__value money text-success">₱{{ formatMoney(summary.paid_amount) }}</span>
          </article>
        </div>
      </template>
    </section>

    <!-- Filter Form -->
    <section class="surface surface-section mb-4" aria-label="Report filters">
      <form :action="`${baseUrl}admin/reports`" method="GET" class="row g-3 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label" for="report-type">Report</label>
          <select id="report-type" name="type" class="form-select" v-model="selectedType">
            <option value="deliveries">Deliveries</option>
            <option value="payments">Payments</option>
          </select>
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label" for="report-from">From</label>
          <input id="report-from" class="form-control" type="date" name="from" :value="filters.from" required>
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label" for="report-to">To</label>
          <input id="report-to" class="form-control" type="date" name="to" :value="filters.to" required>
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label" for="report-status">Status</label>
          <select id="report-status" name="status" class="form-select">
            <option value="">All statuses</option>
            <option
              v-for="status in statusOptions"
              :key="status"
              :value="status"
              :selected="filters.status === status"
            >
              {{ formatStatusLabel(status) }}
            </option>
          </select>
        </div>
        <div v-if="selectedType === 'deliveries'" class="col-12 col-md-2">
          <label class="form-label" for="report-rider">Rider</label>
          <select id="report-rider" name="rider_id" class="form-select">
            <option value="0">All riders</option>
            <option
              v-for="rider in riders"
              :key="rider.id"
              :value="rider.id"
              :selected="Number(filters.rider_id) === Number(rider.id)"
            >
              {{ rider.name }}
            </option>
          </select>
        </div>
        <div class="col-auto">
          <button class="btn btn-secondary" type="submit">Apply</button>
        </div>
      </form>
      <p v-for="error in errors" :key="error" class="text-danger mt-3 mb-0" role="alert">{{ error }}</p>
    </section>

    <!-- Data Table -->
    <section class="surface" :aria-label="currentType === 'payments' ? 'Payment records' : 'Delivery orders'">
      <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0 fw-bold">
          {{ currentType === 'payments' ? 'Payment records' : 'Delivery orders' }}
        </h2>
        <span class="small text-muted">Showing up to 500 records</span>
      </div>

      <div class="table-responsive">
        <table class="table table-clean table-responsive-stack align-middle mb-0">
          <thead>
            <tr v-if="currentType === 'payments'">
              <th scope="col">Order</th>
              <th scope="col">Status</th>
              <th scope="col">Amount</th>
              <th scope="col">Fee</th>
              <th scope="col">Method</th>
              <th scope="col">Paid at</th>
              <th scope="col">Created</th>
            </tr>
            <tr v-else>
              <th scope="col">Order</th>
              <th scope="col">Customer</th>
              <th scope="col">Rider</th>
              <th scope="col">Status</th>
              <th scope="col">Distance</th>
              <th scope="col">Delivery fee</th>
              <th scope="col">Paid amount</th>
              <th scope="col">Created</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows || !rows.length">
              <td :colspan="currentType === 'payments' ? 7 : 8" class="text-center text-muted py-4">
                No records match these filters.
              </td>
            </tr>
            <template v-else-if="currentType === 'payments'">
              <tr v-for="row in rows" :key="row.id || row.order_no">
                <td data-label="Order">
                  <a v-if="row.order_id" :href="`${baseUrl}orders/${row.order_id}`" class="fw-bold text-decoration-none">
                    {{ row.order_no || `#${row.order_id}` }}
                  </a>
                  <span v-else class="text-muted">—</span>
                </td>
                <td data-label="Status">
                  <span class="badge" :class="getPaymentBadgeClass(row.status)">
                    {{ formatStatusLabel(row.status) }}
                  </span>
                </td>
                <td data-label="Amount" class="money">₱{{ formatMoney(row.amount) }}</td>
                <td data-label="Fee" class="money text-muted">₱{{ formatMoney(row.fee_amount) }}</td>
                <td data-label="Method">{{ row.payment_method || '—' }}</td>
                <td data-label="Paid at" class="text-muted small">
                  {{ row.paid_at ? formatDateTime(row.paid_at) : '—' }}
                </td>
                <td data-label="Created" class="text-muted small">
                  {{ formatDateTime(row.created_at) }}
                </td>
              </tr>
            </template>
            <template v-else>
              <tr v-for="row in rows" :key="row.id || row.order_no">
                <td data-label="Order">
                  <a :href="`${baseUrl}orders/${row.id}`" class="fw-bold text-decoration-none">
                    {{ row.order_no }}
                  </a>
                </td>
                <td data-label="Customer">{{ row.customer_name }}</td>
                <td data-label="Rider">{{ row.rider_name || 'Unassigned' }}</td>
                <td data-label="Status">
                  <span class="badge" :class="getDeliveryBadgeClass(row.status)">
                    {{ formatStatusLabel(row.status) }}
                  </span>
                </td>
                <td data-label="Distance">
                  {{ row.distance_km !== null && row.distance_km !== undefined ? `${Number(row.distance_km).toFixed(1)} km` : '—' }}
                </td>
                <td data-label="Delivery fee" class="money">₱{{ formatMoney(row.delivery_fee) }}</td>
                <td data-label="Paid amount" class="money text-muted">
                  {{ Number(row.paid_amount || 0) > 0 ? `₱${formatMoney(row.paid_amount)}` : '—' }}
                </td>
                <td data-label="Created" class="text-muted small">
                  {{ formatDateTime(row.created_at) }}
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  filters: { type: Object, default: () => ({}) },
  report: { type: Object, default: () => ({ summary: {}, rows: [] }) },
  errors: { type: Array, default: () => [] },
  riders: { type: Array, default: () => [] },
  reportError: { type: [String, Object, null], default: null },
  exportQuery: { type: String, default: '' },
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' },
  flashes: { type: Array, default: () => [] }
});

const selectedType = ref(props.filters.type || 'deliveries');
const currentType = computed(() => props.filters.type || 'deliveries');
const summary = computed(() => (props.report && props.report.summary) || {});
const rows = computed(() => (props.report && props.report.rows) || []);

const statusOptions = computed(() => {
  if (selectedType.value === 'payments') {
    return ['pending', 'paid', 'failed', 'refunded', 'cancelled'];
  }
  return ['draft', 'pending_payment', 'paid', 'booking', 'rider_assigned', 'picked_up', 'delivered', 'cancelled', 'failed'];
});

function formatStatusLabel(status) {
  if (!status) return '';
  return status.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function formatMoney(value) {
  const num = Number(value || 0);
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDateTime(dateStr) {
  if (!dateStr) return '—';
  try {
    const d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return dateStr;
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const mins = String(d.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${mins}`;
  } catch {
    return dateStr;
  }
}

function getPaymentBadgeClass(status) {
  switch (status) {
    case 'paid':
      return 'bg-success';
    case 'pending':
      return 'bg-warning text-dark';
    case 'failed':
      return 'bg-danger';
    case 'refunded':
      return 'bg-info text-dark';
    case 'cancelled':
      return 'bg-secondary';
    default:
      return 'bg-light text-dark border';
  }
}

function getDeliveryBadgeClass(status) {
  switch (status) {
    case 'delivered':
      return 'bg-success';
    case 'picked_up':
    case 'rider_assigned':
      return 'bg-info text-dark';
    case 'booking':
    case 'paid':
      return 'bg-primary';
    case 'pending_payment':
      return 'bg-warning text-dark';
    case 'failed':
      return 'bg-danger';
    case 'cancelled':
      return 'bg-secondary';
    default:
      return 'bg-light text-dark border';
  }
}
</script>
