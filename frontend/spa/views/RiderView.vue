<template>
  <section class="ds-page">
    <template v-if="!selectedId">
      <header class="ds-page-header">
        <div>
          <p class="ds-eyebrow">Deliveries</p>
          <h1 class="page-title">My deliveries</h1>
          <p class="page-description">Your active assignments, ready to fulfil.</p>
        </div>
      </header>

      <div v-if="error" class="ds-notice ds-notice--danger mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ error }}
      </div>
      <div v-if="loading" class="text-center py-5"><div class="spinner-border" role="status"></div></div>
      <div v-else-if="!portalLinked" class="ds-notice ds-notice--warning" role="status">
        <i class="bi bi-person-x me-2" aria-hidden="true"></i>Rider account is not linked to your user.
      </div>
      <div v-else-if="!orders.length" class="surface ds-empty-state">
        <i class="bi bi-inbox" aria-hidden="true"></i>
        <p class="ds-empty-state__message">No assigned deliveries right now.</p>
      </div>
      <div v-else class="surface">
        <ul class="ds-list">
          <li v-for="order in orders" :key="order.id">
            <button type="button" class="ds-list__item border-0 bg-transparent w-100 text-start" @click="openOrder(order.id)">
              <span class="d-grid gap-1">
                <span class="fw-semibold">{{ order.order_no }}</span>
                <span class="small text-muted">{{ order.pickup_address }} → {{ order.dropoff_address }}</span>
              </span>
              <span class="ds-status-chip" :class="`ds-status-chip--${order.status}`">{{ order.status }}</span>
            </button>
          </li>
        </ul>
      </div>
    </template>

    <template v-else>
      <button class="btn btn-link ps-0 mb-2" type="button" @click="closeOrder">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Back to deliveries
      </button>

      <header class="ds-page-header">
        <div>
          <p class="ds-eyebrow">Delivery</p>
          <h1 class="page-title">{{ order?.order_no || 'Delivery' }}</h1>
        </div>
        <span v-if="order" class="ds-status-chip" :class="`ds-status-chip--${order.status}`">{{ order.status }}</span>
      </header>

      <div v-if="message" class="ds-notice ds-notice--success mb-3" role="status">
        <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>{{ message }}
      </div>
      <div v-if="error" class="ds-notice ds-notice--danger mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ error }}
      </div>
      <div v-if="loading" class="text-center py-5"><div class="spinner-border" role="status"></div></div>

      <div v-else-if="order" class="surface">
        <dl class="ds-kv mb-0">
          <div><dt>Pickup</dt><dd>{{ order.pickup_address }}</dd></div>
          <div><dt>Drop-off</dt><dd>{{ order.dropoff_address }}</dd></div>
          <div><dt>Parcel</dt><dd>{{ order.parcel_description || 'Standard Package' }} ({{ order.weight_kg }} kg)</dd></div>
          <div><dt>Proof recorded</dt><dd>{{ proofRecorded ? 'Yes' : 'No' }}</dd></div>
        </dl>

        <div class="d-flex flex-wrap gap-2 mt-3">
          <button
            v-if="order.status === 'rider_assigned'"
            class="btn btn-primary"
            type="button"
            :disabled="working"
            @click="pickup"
          >
            <i class="bi bi-box-arrow-up" aria-hidden="true"></i>
            {{ working ? 'Working…' : 'Mark picked up' }}
          </button>
        </div>

        <form v-if="order.status === 'picked_up'" class="mt-4 pt-4 border-top" @submit.prevent="complete">
          <h2 class="ds-section-title mb-3">Complete delivery</h2>
          <div class="mb-3">
            <label class="form-label" for="spa-rider-recipient">Recipient name</label>
            <input id="spa-rider-recipient" v-model="completion.recipient_name" class="form-control" required />
          </div>
          <div class="mb-3">
            <label class="form-label" for="spa-rider-note">Note</label>
            <textarea id="spa-rider-note" v-model="completion.note" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label" for="spa-rider-proof">Proof photo</label>
            <input id="spa-rider-proof" type="file" class="form-control" accept="image/*" required @change="onFileChange" />
          </div>
          <button class="btn btn-success" type="submit" :disabled="working">
            <i class="bi bi-check2-circle" aria-hidden="true"></i>
            {{ working ? 'Uploading…' : 'Complete delivery' }}
          </button>
        </form>
      </div>
    </template>
  </section>
</template>

<script setup>
import { reactive, ref } from 'vue';

const props = defineProps({
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' }
});

const orders = ref([]);
const selectedId = ref(null);
const order = ref(null);
const loading = ref(false);
const working = ref(false);
const portalLinked = ref(false);
const proofRecorded = ref(false);
const message = ref('');
const error = ref('');
const completion = reactive({ recipient_name: '', note: '' });
const proofFile = ref(null);

function money(value) {
  return `₱${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

async function loadOrders() {
  loading.value = true;
  error.value = '';
  try {
    const response = await fetch(`${props.baseUrl}rider/data`, { credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Could not load deliveries.');
    portalLinked.value = result.data.portal_linked;
    orders.value = result.data.orders || [];
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

async function openOrder(id) {
  selectedId.value = id;
  await loadOrder();
}

async function loadOrder() {
  if (!selectedId.value) return;
  loading.value = true;
  error.value = '';
  message.value = '';
  try {
    const response = await fetch(`${props.baseUrl}rider/orders/${selectedId.value}/data`, { credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Could not load delivery.');
    order.value = result.data.order;
    proofRecorded.value = result.data.proof_recorded;
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

function closeOrder() {
  selectedId.value = null;
  order.value = null;
  proofRecorded.value = false;
  message.value = '';
  error.value = '';
  completion.recipient_name = '';
  completion.note = '';
  proofFile.value = null;
  loadOrders();
}

function onFileChange(event) {
  proofFile.value = event.target.files?.[0] || null;
}

async function pickup() {
  working.value = true;
  message.value = '';
  error.value = '';
  const body = new URLSearchParams({ csrf_token: props.csrfToken });
  try {
    const response = await fetch(`${props.baseUrl}rider/orders/${selectedId.value}/api-pickup`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': props.csrfToken
      },
      body: body.toString()
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Pickup failed.');
    message.value = result.message || 'Pickup recorded.';
    await loadOrder();
  } catch (err) {
    error.value = err.message;
  } finally {
    working.value = false;
  }
}

async function complete() {
  if (!proofFile.value) {
    error.value = 'A proof photo is required.';
    return;
  }
  working.value = true;
  message.value = '';
  error.value = '';
  const body = new FormData();
  body.append('recipient_name', completion.recipient_name);
  body.append('note', completion.note);
  body.append('proof_photo', proofFile.value);
  body.append('csrf_token', props.csrfToken);

  try {
    const response = await fetch(`${props.baseUrl}rider/orders/${selectedId.value}/api-complete`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': props.csrfToken },
      body
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Completion failed.');
    message.value = result.message || 'Delivery completed.';
    completion.recipient_name = '';
    completion.note = '';
    proofFile.value = null;
    await loadOrder();
  } catch (err) {
    error.value = err.message;
  } finally {
    working.value = false;
  }
}

loadOrders();
</script>
