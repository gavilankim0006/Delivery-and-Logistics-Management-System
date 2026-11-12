<template>
  <section class="ds-page order-detail">
    <button class="btn btn-link ps-0 mb-2" type="button" @click="goBack">
      <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Back to orders
    </button>
    <div v-if="loading" class="text-center py-5"><div class="spinner-border" role="status"></div></div>
    <div v-else-if="error" class="ds-notice ds-notice--danger" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ error }}
    </div>

    <div v-else-if="data">
      <header class="ds-page-header">
        <div>
          <p class="ds-eyebrow">Order</p>
          <h1 class="page-title">{{ data.order.order_no }}</h1>
        </div>
        <span class="ds-status-chip" :class="`ds-status-chip--${data.order.status}`">{{ data.order.status }}</span>
      </header>

      <div class="surface mb-3">
        <dl class="row g-0 mb-0">
          <dt class="col-sm-3 text-muted fw-normal">Customer</dt>
          <dd class="col-sm-9">{{ data.order.customer_name }}</dd>
          <dt class="col-sm-3 text-muted fw-normal">Pickup</dt>
          <dd class="col-sm-9">{{ data.order.pickup_address }}</dd>
          <dt class="col-sm-3 text-muted fw-normal">Drop-off</dt>
          <dd class="col-sm-9">{{ data.order.dropoff_address }}</dd>
          <dt class="col-sm-3 text-muted fw-normal">Parcel</dt>
          <dd class="col-sm-9">{{ data.order.parcel_description || 'Standard Package' }} ({{ data.order.weight_kg }} kg, {{ data.order.parcel_size || 'M' }})</dd>
          <dt class="col-sm-3 text-muted fw-normal">Distance</dt>
          <dd class="col-sm-9 distance">{{ Number(data.order.distance_km || 0).toFixed(2) }} km</dd>
          <dt class="col-sm-3 text-muted fw-normal">Delivery fee</dt>
          <dd class="col-sm-9 money fw-semibold">{{ money(data.order.delivery_fee) }}</dd>
          <template v-if="data.order.rider_name">
            <dt class="col-sm-3 text-muted fw-normal">Rider</dt>
            <dd class="col-sm-9 mb-0">{{ data.order.rider_name }}</dd>
          </template>
        </dl>
      </div>

      <div class="mb-4 d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary btn-sm" :href="`${baseUrl}orders/${orderId}`" target="_blank" rel="noopener">Open full detail</a>
        <a v-if="data.role === 'customer' && data.order.status === 'draft'" class="btn btn-outline-secondary btn-sm" :href="`${baseUrl}orders/${orderId}/edit`" target="_blank" rel="noopener">Edit draft</a>
        <a v-if="data.demoPaymentSimulationEnabled && data.order.status === 'pending_payment'" class="btn btn-primary btn-sm" :href="`${baseUrl}orders/${orderId}`" target="_blank" rel="noopener">Pay / simulate</a>
      </div>

      <section class="ds-section">
        <header class="ds-section-header">
          <h2 class="ds-section-title">Timeline</h2>
        </header>
        <div v-if="!data.order.timeline?.length" class="surface ds-empty-state">
          <i class="bi bi-clock-history" aria-hidden="true"></i>
          <p class="ds-empty-state__message">No timeline events yet.</p>
        </div>
        <ul v-else class="surface ds-list">
          <li v-for="(item, index) in data.order.timeline" :key="index" class="ds-list__item">
            <span class="fw-semibold">{{ item.to_status }}</span>
            <span class="small text-muted">{{ item.created_at }}</span>
          </li>
        </ul>
      </section>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  orderId: { type: Number, required: true },
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' }
});

const data = ref(null);
const loading = ref(true);
const error = ref('');

function money(value) {
  return `₱${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function goBack() {
  window.location.hash = '#/orders';
}

async function load() {
  if (!props.orderId) {
    error.value = 'Invalid order.';
    loading.value = false;
    return;
  }
  loading.value = true;
  error.value = '';
  try {
    const response = await fetch(`${props.baseUrl}orders/${props.orderId}/data`, { credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Could not load order.');
    data.value = result.data;
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

load();
</script>

<style scoped>
.order-detail { max-width: 900px; }
</style>
