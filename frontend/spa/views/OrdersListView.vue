<template>
  <section class="ds-page">
    <header class="ds-page-header">
      <div>
        <p class="ds-eyebrow">Operations</p>
        <h1 class="page-title">Orders</h1>
        <p class="page-description">Browse and filter deliveries across every status.</p>
      </div>
      <a class="btn btn-primary" :href="`${baseUrl}orders/new`">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> New order
      </a>
    </header>

    <form class="surface ds-toolbar mb-3" @submit.prevent="applyFilters">
      <div class="flex-grow-1">
        <label class="form-label" for="spa-order-status">Status</label>
        <select id="spa-order-status" v-model="status" class="form-select">
          <option value="">All statuses</option>
          <option v-for="item in statuses" :key="item[0]" :value="item[0]">{{ item[1] }}</option>
        </select>
      </div>
      <div class="flex-grow-1">
        <label class="form-label" for="spa-order-date">Date</label>
        <input id="spa-order-date" v-model="date" type="date" class="form-control" />
      </div>
      <div class="flex-grow-1">
        <label class="form-label" for="spa-order-search">Search</label>
        <input id="spa-order-search" v-model="search" type="search" class="form-control" placeholder="Order number or customer" />
      </div>
      <button class="btn btn-outline-primary" type="submit">
        <i class="bi bi-funnel" aria-hidden="true"></i> Apply
      </button>
    </form>

    <div v-if="error" class="ds-notice ds-notice--danger mb-3" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ error }}
    </div>
    <div v-if="loading" class="text-center py-5"><div class="spinner-border" role="status"></div></div>

    <div v-else-if="!orders.length" class="surface ds-empty-state">
      <i class="bi bi-inbox" aria-hidden="true"></i>
      <p class="ds-empty-state__message">No orders match these filters.</p>
    </div>

    <div v-else class="surface table-responsive">
      <table class="table ds-table table-responsive-stack align-middle mb-0">
        <thead>
          <tr>
            <th>Order</th>
            <th>Status</th>
            <th>Route</th>
            <th>Customer</th>
            <th class="text-end">Fee</th>
            <th><span class="visually-hidden">Action</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td data-label="Order" class="fw-semibold">{{ order.order_no }}</td>
            <td data-label="Status">
              <span class="ds-status-chip" :class="`ds-status-chip--${order.status}`">{{ order.status }}</span>
            </td>
            <td data-label="Route" class="small route-cell">
              <span class="route-cell__address">{{ order.pickup_address }}</span>
              <span class="route-cell__address text-muted">to {{ order.dropoff_address }}</span>
            </td>
            <td data-label="Customer" class="small">{{ order.customer_name }}</td>
            <td data-label="Fee" class="text-end money">{{ money(order.delivery_fee) }}</td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" :href="`#/orders/${order.id}`">View</a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <nav v-if="totalPages > 1" class="mt-3" aria-label="Orders pagination">
      <ul class="pagination">
        <li class="page-item" :class="{ disabled: page <= 1 }">
          <button class="page-link" type="button" @click="changePage(page - 1)">Previous</button>
        </li>
        <li class="page-item disabled">
          <span class="page-link">Page {{ page }} of {{ totalPages }}</span>
        </li>
        <li class="page-item" :class="{ disabled: page >= totalPages }">
          <button class="page-link" type="button" @click="changePage(page + 1)">Next</button>
        </li>
      </ul>
    </nav>
  </section>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' }
});

const orders = ref([]);
const loading = ref(false);
const error = ref('');
const status = ref('');
const date = ref('');
const search = ref('');
const page = ref(1);
const totalPages = ref(1);

const statuses = [
  ['draft', 'Draft'],
  ['pending_payment', 'Pending payment'],
  ['paid', 'Paid'],
  ['booking', 'Booking'],
  ['rider_assigned', 'Rider assigned'],
  ['picked_up', 'Picked up'],
  ['delivered', 'Delivered'],
  ['cancelled', 'Cancelled'],
  ['failed', 'Failed']
];

function money(value) {
  return `₱${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const params = new URLSearchParams();
    if (status.value) params.set('status', status.value);
    if (date.value) params.set('date', date.value);
    if (search.value) params.set('search', search.value);
    params.set('page', page.value);
    const response = await fetch(`${props.baseUrl}orders/data?${params.toString()}`, { credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Could not load orders.');
    orders.value = result.data.orders || [];
    totalPages.value = result.data.totalPages || 1;
    page.value = result.data.page || page.value;
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  page.value = 1;
  load();
}

function changePage(next) {
  if (next < 1 || next > totalPages.value) return;
  page.value = next;
  load();
}

load();
</script>
