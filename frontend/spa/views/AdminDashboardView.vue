<template>
  <section class="ds-page">
    <header class="ds-page-header">
      <div>
        <p class="ds-eyebrow">Administration</p>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-description">Today's order volume, revenue and payment health at a glance.</p>
      </div>
      <div class="ds-toolbar">
        <a class="btn btn-outline-secondary btn-sm" href="#/admin/users">
          <i class="bi bi-people" aria-hidden="true"></i> Users
        </a>
        <a class="btn btn-outline-secondary btn-sm" :href="`${baseUrl}admin`" target="_blank" rel="noopener">
          <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Open panel
        </a>
      </div>
    </header>

    <div v-if="error" class="ds-notice ds-notice--danger mb-3" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ error }}
    </div>

    <div v-if="loading" class="text-center py-5"><div class="spinner-border" role="status"></div></div>

    <template v-else>
      <div class="ds-stat-grid">
        <article class="surface ds-metric-card">
          <span class="ds-metric-card__label"><i class="bi bi-box-seam" aria-hidden="true"></i> Orders today</span>
          <span class="ds-metric-card__value">{{ data.ordersToday }}</span>
        </article>
        <article class="surface ds-metric-card">
          <span class="ds-metric-card__label"><i class="bi bi-cash-stack" aria-hidden="true"></i> Month revenue</span>
          <span class="ds-metric-card__value">{{ money(data.monthRevenue) }}</span>
        </article>
        <article class="surface ds-metric-card">
          <span class="ds-metric-card__label"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i> Failed payments</span>
          <span class="ds-metric-card__value">{{ data.failedPayments }}</span>
        </article>
      </div>

      <section class="ds-section">
        <header class="ds-section-header">
          <h2 class="ds-section-title">Recent orders</h2>
          <a class="btn btn-link btn-sm" href="#/orders">View all</a>
        </header>
        <div v-if="!data.recentOrders.length" class="surface ds-empty-state">
          <i class="bi bi-inbox" aria-hidden="true"></i>
          <p class="ds-empty-state__message">No recent orders yet.</p>
        </div>
        <div v-else class="surface table-responsive">
          <table class="table ds-table align-middle mb-0">
            <thead>
              <tr><th>Order</th><th>Customer</th><th>Status</th><th class="text-end">Fee</th></tr>
            </thead>
            <tbody>
              <tr v-for="order in data.recentOrders" :key="order.id">
                <td class="fw-semibold">{{ order.order_no }}</td>
                <td>{{ order.customer_name }}</td>
                <td><span class="ds-status-chip" :class="`ds-status-chip--${order.status}`">{{ order.status }}</span></td>
                <td class="text-end money">{{ money(order.delivery_fee) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="ds-section">
        <header class="ds-section-header">
          <h2 class="ds-section-title">Recent API logs</h2>
        </header>
        <div v-if="!data.recentLogs.length" class="surface ds-empty-state">
          <i class="bi bi-journal-text" aria-hidden="true"></i>
          <p class="ds-empty-state__message">No logs recorded.</p>
        </div>
        <pre v-else class="p-3 mb-0" style="max-height:220px;overflow:auto">{{ data.recentLogs }}</pre>
      </section>
    </template>
  </section>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' }
});

const loading = ref(true);
const error = ref('');
const data = ref({
  ordersToday: 0,
  monthRevenue: 0,
  failedPayments: 0,
  recentOrders: [],
  recentLogs: []
});

function money(value) {
  return `₱${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const response = await fetch(`${props.baseUrl}admin/data`, { credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Could not load admin dashboard.');
    data.value = result.data;
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

load();
</script>
