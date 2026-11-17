<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  orders:          { type: Array,   default: () => [] },
  history:         { type: Array,   default: () => [] },
  stats:           { type: Object,  default: () => ({}) },
  rider:           { type: Object,  default: null },
  account:         { type: Object,  default: () => ({}) },
  portalLinked:    { type: Boolean, default: false },
  dataUrl:         { type: String,  default: '' },
  availabilityUrl: { type: String,  default: '' },
  csrfToken:       { type: String,  default: '' },
  baseUrl:         { type: String,  default: '/' },
  flashes:         { type: Array,   default: () => [] },
});

/* ── live state (seeded from server props, refreshed over JSON) ── */
const liveOrders   = ref(props.orders);
const liveHistory  = ref(props.history);
const liveStats    = ref(props.stats || {});
const liveRider    = ref(props.rider);
const liveAccount  = ref(props.account || {});
const portalLinked = ref(props.portalLinked);

const activeTab = ref('active');
const loading   = ref(false);
const refreshing = ref(false);
const loadError  = ref('');
const lastUpdated = ref('');
const actionMessage = ref('');
const actionMessageType = ref('success');
const availabilityBusy = ref(false);

let refreshTimer = null;
let messageTimer = null;

/* ── relative-time helper ─────────────────────────────── */
function timeAgo(dateStr) {
  if (!dateStr) return '';
  const diff = (Date.now() - new Date(String(dateStr).replace(' ', 'T')).getTime()) / 1000;
  if (Number.isNaN(diff)) return '';
  if (diff < 60)   return 'just now';
  if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return `${Math.floor(diff / 86400)}d ago`;
}

/* ── tick: drives all reactive time strings ───────────── */
const tick = ref(Date.now());
let tickTimer = null;

onMounted(() => {
  tickTimer = setInterval(() => { tick.value = Date.now(); }, 15000);
});

/* ── last-refreshed tracking ──────────────────────────── */
const lastRefreshedText = computed(() => {
  void tick.value;
  if (!lastUpdated.value) return 'just now';
  const diff = (Date.now() - lastUpdated.value.getTime()) / 1000;
  if (diff < 60) return 'just now';
  return `${Math.floor(diff / 60)} min ago`;
});

/* ── auto-refresh: pull fresh JSON every 30 s ─────────── */
async function loadData(isManual = false) {
  if (!props.dataUrl) return;
  if (isManual) refreshing.value = true;
  else loading.value = true;
  try {
    const response = await fetch(props.dataUrl, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      cache: 'no-store',
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.error || 'Unable to refresh deliveries.');
    }
    const data = result.data || {};
    portalLinked.value = Boolean(data.portal_linked);
    liveRider.value   = data.rider ?? null;
    liveOrders.value  = Array.isArray(data.orders) ? data.orders : [];
    liveHistory.value = Array.isArray(data.history) ? data.history : [];
    liveStats.value   = data.stats || {};
    liveAccount.value = data.account || liveAccount.value;
    loadError.value = '';
    lastUpdated.value = new Date();
  } catch (error) {
    loadError.value = error.message || 'Unable to refresh deliveries.';
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
}

/* ── pull-to-refresh state ────────────────────────────── */
const pulling       = ref(false);
const pullDistance   = ref(0);
const pullTriggered = ref(false);
let startY = 0;
const PULL_THRESHOLD = 80;

function onTouchStart(e) {
  if (window.scrollY > 5) return;
  startY = e.touches[0].clientY;
  pulling.value = false;
  pullDistance.value = 0;
  pullTriggered.value = false;
}

function onTouchMove(e) {
  if (pullTriggered.value) return;
  const dy = e.touches[0].clientY - startY;
  if (dy > 10 && window.scrollY <= 0) {
    pulling.value = true;
    pullDistance.value = Math.min(dy * 0.45, 120);
    if (pullDistance.value > 10) e.preventDefault();
  }
}

function onTouchEnd() {
  if (pulling.value && pullDistance.value >= PULL_THRESHOLD) {
    pullTriggered.value = true;
    loadData(true).finally(() => {
      pulling.value = false;
      pullDistance.value = 0;
      pullTriggered.value = false;
    });
  } else {
    pulling.value = false;
    pullDistance.value = 0;
  }
}

/* ── lifecycle ────────────────────────────────────────── */
onMounted(() => {
  const requestedTab = new URLSearchParams(window.location.search).get('tab');
  if (['active', 'history', 'profile'].includes(requestedTab)) {
    activeTab.value = requestedTab;
  }
  if (props.dataUrl) {
    refreshTimer = setInterval(() => {
      if (!document.hidden) loadData();
    }, 30000);
  }

  document.addEventListener('touchstart', onTouchStart, { passive: true });
  document.addEventListener('touchmove',  onTouchMove,  { passive: false });
  document.addEventListener('touchend',   onTouchEnd,   { passive: true });
});

onUnmounted(() => {
  clearInterval(tickTimer);
  clearInterval(refreshTimer);
  clearTimeout(messageTimer);
  document.removeEventListener('touchstart', onTouchStart);
  document.removeEventListener('touchmove',  onTouchMove);
  document.removeEventListener('touchend',   onTouchEnd);
});

/* ── identity & status ────────────────────────────────── */
const riderName = computed(() =>
  liveRider.value?.name || liveAccount.value?.name || 'Rider'
);
const initials = computed(() => {
  const parts = String(riderName.value).trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return 'R';
  return (parts[0][0] + (parts[1]?.[0] || '')).toUpperCase();
});
const greeting = computed(() => {
  const h = new Date().getHours();
  if (h < 12) return 'Good morning';
  if (h < 17) return 'Good afternoon';
  return 'Good evening';
});
const today = computed(() =>
  new Date().toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' })
);

const riderStatus = computed(() => liveRider.value?.status || 'offline');
const onDelivery  = computed(() => riderStatus.value === 'busy' || liveOrders.value.length > 0);
const statusLabel = computed(() => {
  if (onDelivery.value) return 'On delivery';
  return riderStatus.value === 'available' ? 'Available' : 'Offline';
});
const statusChipClass = computed(() => {
  if (onDelivery.value) return 'rider-status-chip rider-status-chip--busy';
  return riderStatus.value === 'available'
    ? 'rider-status-chip rider-status-chip--available'
    : 'rider-status-chip rider-status-chip--offline';
});

/* ── stats ────────────────────────────────────────────── */
const stats = computed(() => ({
  completed_total: 0,
  completed_today: 0,
  completed_week: 0,
  completed_distance_km: 0,
  active_total: liveOrders.value.length,
  rating_average: null,
  rating_count: 0,
  rating_five: 0,
  ...(liveStats.value || {}),
}));

const activeCount = computed(() => liveOrders.value.length);
const historyCount = computed(() => liveHistory.value.length);
const ratingDisplay = computed(() =>
  stats.value.rating_average !== null && stats.value.rating_average !== undefined
    ? Number(stats.value.rating_average).toFixed(1)
    : '—'
);

function starClass(rating, index) {
  const value = Number(rating || 0);
  return index <= Math.round(value) ? 'bi-star-fill' : 'bi-star';
}

/* ── availability ─────────────────────────────────────── */
function flashAction(message, type = 'success') {
  actionMessage.value = message;
  actionMessageType.value = type;
  clearTimeout(messageTimer);
  messageTimer = setTimeout(() => { actionMessage.value = ''; }, 5000);
}

async function setAvailability(status) {
  if (!props.availabilityUrl || availabilityBusy.value || onDelivery.value) return;
  availabilityBusy.value = true;
  try {
    const response = await fetch(props.availabilityUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': props.csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
      },
      body: JSON.stringify({ status }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.error || 'Availability could not be updated.');
    }
    if (liveRider.value) liveRider.value = { ...liveRider.value, status: result.data?.status || status };
    flashAction(result.message || 'Availability updated.', 'success');
  } catch (error) {
    flashAction(error.message || 'Availability could not be updated.', 'error');
  } finally {
    availabilityBusy.value = false;
  }
}

function toggleDuty() {
  setAvailability(riderStatus.value === 'available' ? 'offline' : 'available');
}

/* ── formatting helpers ───────────────────────────────── */
function money(amount) {
  return Number(amount || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2, maximumFractionDigits: 2,
  });
}

function distanceKm(order) {
  const value = Number(order?.distance_km || 0);
  return value > 0 ? `${value.toFixed(1)} km` : '';
}

function etaMin(order) {
  const value = Number(order?.estimated_duration_min || 0);
  return value > 0 ? `~${value} min` : '';
}

function parcelMeta(order) {
  const parts = [];
  if (order?.parcel_description) parts.push(order.parcel_description);
  if (order?.weight_kg) parts.push(`${Number(order.weight_kg).toFixed(1)} kg`);
  if (order?.parcel_size) parts.push(`Size ${order.parcel_size}`);
  if (Number(order?.is_fragile)) parts.push('Fragile');
  return parts.join(' · ');
}

function formatDateTime(value) {
  if (!value) return '';
  const date = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleString([], {
    month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
  });
}

function chipClass(status) {
  return status === 'picked_up'
    ? 'status-chip status-chip--picked_up'
    : 'status-chip status-chip--rider_assigned';
}

function chipLabel(status) {
  return status === 'picked_up' ? 'Picked up' : 'Awaiting pickup';
}

function assignedAgo(order) {
  void tick.value;
  return timeAgo(order.assigned_at);
}

function completedAgo(order) {
  void tick.value;
  return timeAgo(order.completed_at) || formatDateTime(order.completed_at);
}

function deliveryUrl(order) {
  return `${props.baseUrl}rider/orders/${order.order_id}`;
}

function refreshFooter() {
  return `Last refreshed ${lastRefreshedText.value} · auto-refreshes every 30 s`;
}
</script>

<template>
  <!-- pull-to-refresh indicator -->
  <Transition name="ptr-fade">
    <div
      v-if="pulling"
      class="ptr-indicator"
      :style="{ transform: `translateY(${pullDistance - 48}px)` }"
      aria-hidden="true"
    >
      <i
        class="bi bi-arrow-clockwise ptr-icon"
        :class="{ 'ptr-icon--ready': pullDistance >= PULL_THRESHOLD }"
      ></i>
      <span class="ptr-label">
        {{ pullDistance >= PULL_THRESHOLD ? 'Release to refresh' : 'Pull to refresh' }}
      </span>
    </div>
  </Transition>

  <div class="container page-shell py-4 ds-page rider-deliveries">
    <div
      v-for="(flash, index) in flashes"
      :key="index"
      class="alert alert-dismissible fade show mb-3"
      :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`"
      role="status"
    >
      <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
      {{ flash.message }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <!-- ─── Not-linked state ────────────────────────── -->
    <section
      v-if="!portalLinked"
      class="empty-state surface p-4 p-lg-5 text-center"
      role="status"
    >
      <i class="bi bi-person-x empty-state__icon empty-state__icon--warn"></i>
      <h2 class="h5 mt-3 mb-2">Rider account not linked</h2>
      <p class="text-secondary mb-0">
        Your account is active, but it is not linked to a rider profile yet.<br>
        Contact an administrator to finish setup.
      </p>
    </section>

    <template v-else>
      <!-- ─── Hero header ───────────────────────────── -->
      <header class="rider-hero surface rider-fade-in mb-3">
        <div class="rider-hero__identity">
          <div class="rider-hero__avatar" aria-hidden="true">{{ initials }}</div>
          <div class="rider-hero__text">
            <p class="ds-eyebrow mb-1">{{ greeting }}</p>
            <h1 class="rider-hero__name">{{ riderName }}</h1>
            <div class="rider-hero__meta">
              <span :class="statusChipClass">
                <span class="rider-status-dot" aria-hidden="true"></span>
                {{ statusLabel }}
              </span>
              <span class="rider-hero__date">
                <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>{{ today }}
              </span>
            </div>
          </div>
        </div>
        <div class="rider-hero__actions">
          <button
            type="button"
            class="rider-duty-toggle"
            :class="riderStatus === 'available' ? 'rider-duty-toggle--on' : 'rider-duty-toggle--off'"
            :disabled="availabilityBusy || onDelivery"
            :aria-pressed="riderStatus === 'available' ? 'true' : 'false'"
            :title="onDelivery ? 'Finish your active delivery before changing availability.' : (riderStatus === 'available' ? 'Go offline' : 'Go available')"
            @click="toggleDuty"
          >
            <span v-if="availabilityBusy" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <i v-else class="bi" :class="onDelivery ? 'bi-truck' : (riderStatus === 'available' ? 'bi-toggle-on' : 'bi-toggle-off')" aria-hidden="true"></i>
            <span>{{ onDelivery ? 'On delivery' : (riderStatus === 'available' ? 'Available' : 'Offline') }}</span>
          </button>
          <button
            type="button"
            class="btn btn-sm btn-secondary rider-refresh-btn"
            :disabled="refreshing"
            :aria-busy="refreshing ? 'true' : 'false'"
            @click="loadData(true)"
          >
            <i class="bi" :class="refreshing ? 'bi-arrow-repeat rider-spin' : 'bi-arrow-clockwise'" aria-hidden="true"></i>
            <span class="d-none d-sm-inline">Refresh</span>
          </button>
        </div>
      </header>

      <!-- ─── Action feedback ───────────────────────── -->
      <div
        v-if="actionMessage"
        class="alert alert-dismissible fade show mb-3"
        :class="actionMessageType === 'error' ? 'alert-danger' : 'alert-success'"
        role="status"
      >
        <i class="bi me-2" :class="actionMessageType === 'error' ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'" aria-hidden="true"></i>
        {{ actionMessage }}
        <button type="button" class="btn-close" @click="actionMessage = ''" aria-label="Close"></button>
      </div>

      <!-- ─── KPI metrics ───────────────────────────── -->
      <div class="rider-kpi-grid rider-fade-in" role="group" aria-label="Delivery summary metrics">
        <button type="button" class="rider-kpi-card" @click="activeTab = 'active'">
          <span class="rider-kpi-icon rider-kpi-icon--active"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
          <span class="rider-kpi-label">Active</span>
          <span class="rider-kpi-value">{{ activeCount }}</span>
          <span class="rider-kpi-hint">Assigned to you now</span>
        </button>
        <button type="button" class="rider-kpi-card" @click="activeTab = 'history'">
          <span class="rider-kpi-icon rider-kpi-icon--completed"><i class="bi bi-check-circle" aria-hidden="true"></i></span>
          <span class="rider-kpi-label">Completed</span>
          <span class="rider-kpi-value">{{ stats.completed_total }}</span>
          <span class="rider-kpi-hint">All-time deliveries</span>
        </button>
        <button type="button" class="rider-kpi-card" @click="activeTab = 'history'">
          <span class="rider-kpi-icon rider-kpi-icon--rating"><i class="bi bi-star" aria-hidden="true"></i></span>
          <span class="rider-kpi-label">Rating</span>
          <span class="rider-kpi-value">{{ ratingDisplay }}</span>
          <span class="rider-kpi-hint">{{ stats.rating_count }} customer{{ Number(stats.rating_count) === 1 ? '' : 's' }} rated</span>
        </button>
        <button type="button" class="rider-kpi-card" @click="activeTab = 'history'">
          <span class="rider-kpi-icon rider-kpi-icon--week"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span>
          <span class="rider-kpi-label">This week</span>
          <span class="rider-kpi-value">{{ stats.completed_week }}</span>
          <span class="rider-kpi-hint">{{ stats.completed_today }} today</span>
        </button>
      </div>

      <!-- ─── Workspace tabs ────────────────────────── -->
      <nav class="rider-tabs" role="tablist" aria-label="Rider workspace">
        <button
          type="button"
          role="tab"
          class="rider-tab"
          :class="{ 'is-active': activeTab === 'active' }"
          :aria-selected="activeTab === 'active' ? 'true' : 'false'"
          @click="activeTab = 'active'"
        >
          <i class="bi bi-box-seam" aria-hidden="true"></i>
          <span>Active</span>
          <span v-if="activeCount" class="rider-tab-badge">{{ activeCount }}</span>
        </button>
        <button
          type="button"
          role="tab"
          class="rider-tab"
          :class="{ 'is-active': activeTab === 'history' }"
          :aria-selected="activeTab === 'history' ? 'true' : 'false'"
          @click="activeTab = 'history'"
        >
          <i class="bi bi-clock-history" aria-hidden="true"></i>
          <span>History</span>
          <span v-if="historyCount" class="rider-tab-badge rider-tab-badge--muted">{{ historyCount }}</span>
        </button>
        <button
          type="button"
          role="tab"
          class="rider-tab"
          :class="{ 'is-active': activeTab === 'profile' }"
          :aria-selected="activeTab === 'profile' ? 'true' : 'false'"
          @click="activeTab = 'profile'"
        >
          <i class="bi bi-person" aria-hidden="true"></i>
          <span>Profile</span>
        </button>
      </nav>

      <div v-if="loadError" class="alert alert-warning d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-wifi-off" aria-hidden="true"></i>
        <div class="flex-grow-1">{{ loadError }}</div>
        <button type="button" class="btn btn-sm btn-outline-primary" @click="loadData(true)">Try again</button>
      </div>

      <!-- ═══ ACTIVE TAB ═══ -->
      <section v-if="activeTab === 'active'" role="tabpanel" aria-label="Active deliveries">
        <section
          v-if="!liveOrders.length"
          class="empty-state surface p-4 p-lg-5 text-center"
          role="status"
        >
          <i class="bi bi-inbox empty-state__icon" aria-hidden="true"></i>
          <h2 class="h5 mt-3 mb-2">No active deliveries</h2>
          <p class="text-secondary mb-0">
            New assignments from dispatch will appear here.<br>
            Hang tight — you're all caught up!
          </p>
        </section>

        <template v-else>
          <!-- MOBILE cards (< lg) -->
          <div class="d-lg-none" aria-label="Active delivery assignments">
            <div
              v-for="order in liveOrders"
              :key="'card-' + order.order_id"
              class="delivery-card surface mb-3"
            >
              <div class="delivery-card__head">
                <a class="delivery-card__order-link" :href="deliveryUrl(order)">
                  {{ order.order_no }}
                </a>
                <span :class="chipClass(order.status)">{{ chipLabel(order.status) }}</span>
              </div>

              <div class="delivery-card__body">
                <div class="delivery-card__addr">
                  <i class="bi bi-box-arrow-up delivery-card__icon delivery-card__icon--pickup" aria-hidden="true"></i>
                  <div>
                    <span class="delivery-card__addr-label">Pickup</span>
                    <span class="delivery-card__addr-text">{{ order.pickup_address }}</span>
                  </div>
                </div>
                <div class="delivery-card__addr">
                  <i class="bi bi-geo-alt-fill delivery-card__icon delivery-card__icon--dropoff" aria-hidden="true"></i>
                  <div>
                    <span class="delivery-card__addr-label">Drop-off</span>
                    <span class="delivery-card__addr-text">{{ order.dropoff_address }}</span>
                  </div>
                </div>
              </div>

              <div class="delivery-card__meta">
                <span v-if="parcelMeta(order)" class="delivery-card__meta-item">
                  <i class="bi bi-box" aria-hidden="true"></i>{{ parcelMeta(order) }}
                </span>
                <span class="delivery-card__meta-item">
                  <i class="bi bi-cash" aria-hidden="true"></i>₱{{ money(order.delivery_fee) }}
                </span>
                <span v-if="distanceKm(order)" class="delivery-card__meta-item">
                  <i class="bi bi-rulers" aria-hidden="true"></i>{{ distanceKm(order) }}
                </span>
                <span v-if="etaMin(order)" class="delivery-card__meta-item">
                  <i class="bi bi-clock" aria-hidden="true"></i>{{ etaMin(order) }}
                </span>
              </div>

              <div class="delivery-card__foot">
                <small v-if="assignedAgo(order)" class="text-secondary">
                  <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ assignedAgo(order) }}
                </small>
                <a class="btn btn-primary btn-action" :href="deliveryUrl(order)">
                  Open delivery
                </a>
              </div>
            </div>
          </div>

          <!-- DESKTOP table (≥ lg) -->
          <section class="surface d-none d-lg-block" aria-label="Active delivery assignments">
            <div class="table-responsive">
              <table class="table table-clean table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Order</th>
                    <th scope="col">Parcel</th>
                    <th scope="col">Pickup</th>
                    <th scope="col">Drop-off</th>
                    <th scope="col" class="text-end">Fee</th>
                    <th scope="col">Assigned</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Action</span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="order in liveOrders" :key="'row-' + order.order_id">
                    <td>
                      <a class="fw-semibold" :href="deliveryUrl(order)">{{ order.order_no }}</a>
                    </td>
                    <td class="text-secondary small">{{ parcelMeta(order) || '—' }}</td>
                    <td>
                      <i class="bi bi-box-arrow-up text-success me-1" aria-hidden="true"></i>
                      {{ order.pickup_address }}
                    </td>
                    <td>
                      <i class="bi bi-geo-alt-fill text-danger me-1" aria-hidden="true"></i>
                      {{ order.dropoff_address }}
                    </td>
                    <td class="text-end fw-semibold text-nowrap">₱{{ money(order.delivery_fee) }}</td>
                    <td>
                      <small class="text-secondary">{{ assignedAgo(order) || '—' }}</small>
                      <div v-if="distanceKm(order) || etaMin(order)" class="text-muted small">
                        {{ [distanceKm(order), etaMin(order)].filter(Boolean).join(' · ') }}
                      </div>
                    </td>
                    <td>
                      <span :class="chipClass(order.status)">{{ chipLabel(order.status) }}</span>
                    </td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary btn-action" :href="deliveryUrl(order)">Open delivery</a>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <footer class="refresh-footer mt-3 text-center text-secondary">
            <small>
              <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
              {{ refreshFooter() }}
            </small>
          </footer>
        </template>
      </section>

      <!-- ═══ HISTORY TAB ═══ -->
      <section v-else-if="activeTab === 'history'" role="tabpanel" aria-label="Completed deliveries">
        <section
          v-if="!liveHistory.length"
          class="empty-state surface p-4 p-lg-5 text-center"
          role="status"
        >
          <i class="bi bi-clock-history empty-state__icon" aria-hidden="true"></i>
          <h2 class="h5 mt-3 mb-2">No completed deliveries yet</h2>
          <p class="text-secondary mb-0">
            Deliveries you complete will be listed here with their proof and rating details.
          </p>
        </section>

        <template v-else>
          <!-- MOBILE cards (< lg) -->
          <div class="d-lg-none" aria-label="Completed deliveries">
            <div
              v-for="order in liveHistory"
              :key="'hist-card-' + order.order_id"
              class="delivery-card delivery-card--history surface mb-3"
            >
              <div class="delivery-card__head">
                <a class="delivery-card__order-link" :href="deliveryUrl(order)">{{ order.order_no }}</a>
                <span class="status-chip status-chip--delivered">Delivered</span>
              </div>
              <div class="delivery-card__body">
                <div class="delivery-card__addr">
                  <i class="bi bi-box-arrow-up delivery-card__icon delivery-card__icon--pickup" aria-hidden="true"></i>
                  <div>
                    <span class="delivery-card__addr-label">Pickup</span>
                    <span class="delivery-card__addr-text">{{ order.pickup_address }}</span>
                  </div>
                </div>
                <div class="delivery-card__addr">
                  <i class="bi bi-geo-alt-fill delivery-card__icon delivery-card__icon--dropoff" aria-hidden="true"></i>
                  <div>
                    <span class="delivery-card__addr-label">Drop-off</span>
                    <span class="delivery-card__addr-text">{{ order.dropoff_address }}</span>
                  </div>
                </div>
              </div>
              <div class="delivery-card__meta">
                <span v-if="order.proof_recipient" class="delivery-card__meta-item">
                  <i class="bi bi-person-check" aria-hidden="true"></i>{{ order.proof_recipient }}
                </span>
                <span v-if="order.rating_value" class="delivery-card__meta-item delivery-card__meta-item--rating">
                  <i :class="['bi', starClass(order.rating_value, 1)]" aria-hidden="true"></i>{{ order.rating_value }}/5
                </span>
                <span class="delivery-card__meta-item">
                  <i class="bi bi-cash" aria-hidden="true"></i>₱{{ money(order.delivery_fee) }}
                </span>
                <span v-if="distanceKm(order)" class="delivery-card__meta-item">
                  <i class="bi bi-rulers" aria-hidden="true"></i>{{ distanceKm(order) }}
                </span>
              </div>
              <div class="delivery-card__foot">
                <small class="text-secondary">
                  <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Completed {{ completedAgo(order) }}
                </small>
                <a class="btn btn-outline-primary btn-action" :href="deliveryUrl(order)">View details</a>
              </div>
            </div>
          </div>

          <!-- DESKTOP table (≥ lg) -->
          <section class="surface d-none d-lg-block" aria-label="Completed deliveries">
            <div class="table-responsive">
              <table class="table table-clean table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Order</th>
                    <th scope="col">Completed</th>
                    <th scope="col">Drop-off</th>
                    <th scope="col">Recipient</th>
                    <th scope="col" class="text-end">Fee</th>
                    <th scope="col">Rating</th>
                    <th scope="col"><span class="visually-hidden">Action</span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="order in liveHistory" :key="'hist-row-' + order.order_id">
                    <td><a class="fw-semibold" :href="deliveryUrl(order)">{{ order.order_no }}</a></td>
                    <td>
                      <div>{{ formatDateTime(order.completed_at) }}</div>
                      <small class="text-secondary">{{ completedAgo(order) }}</small>
                    </td>
                    <td>
                      <i class="bi bi-geo-alt-fill text-danger me-1" aria-hidden="true"></i>
                      {{ order.dropoff_address }}
                    </td>
                    <td class="text-secondary">{{ order.proof_recipient || '—' }}</td>
                    <td class="text-end fw-semibold text-nowrap">₱{{ money(order.delivery_fee) }}</td>
                    <td>
                      <span v-if="order.rating_value" class="text-nowrap" :aria-label="`Rated ${order.rating_value} out of 5`">
                        <i v-for="n in 5" :key="'star-' + order.order_id + '-' + n" :class="['bi', starClass(order.rating_value, n), 'delivery-rating-star']" aria-hidden="true"></i>
                        <small class="text-secondary ms-1">{{ order.rating_value }}/5</small>
                      </span>
                      <span v-else class="text-secondary small">Not rated</span>
                    </td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary btn-action" :href="deliveryUrl(order)">View details</a>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <footer class="refresh-footer mt-3 text-center text-secondary">
            <small>
              <i class="bi bi-clock-history me-1" aria-hidden="true"></i>
              Showing your latest {{ historyCount }} completed deliver{{ historyCount === 1 ? 'y' : 'ies' }}
            </small>
          </footer>
        </template>
      </section>

      <!-- ═══ PROFILE TAB ═══ -->
      <section v-else role="tabpanel" aria-label="Rider profile">
        <div class="row g-3">
          <div class="col-12 col-lg-6">
            <section class="surface rider-profile-card h-100">
              <div class="rider-profile-head">
                <div class="rider-profile-avatar" aria-hidden="true">{{ initials }}</div>
                <div>
                  <h2 class="h5 mb-1">{{ riderName }}</h2>
                  <span :class="statusChipClass">
                    <span class="rider-status-dot" aria-hidden="true"></span>
                    {{ statusLabel }}
                  </span>
                </div>
              </div>
              <dl class="rider-profile-list mb-0">
                <div class="rider-profile-row">
                  <dt><i class="bi bi-envelope" aria-hidden="true"></i> Email</dt>
                  <dd>{{ liveAccount.email || '—' }}</dd>
                </div>
                <div class="rider-profile-row">
                  <dt><i class="bi bi-telephone" aria-hidden="true"></i> Phone</dt>
                  <dd>{{ liveRider?.phone || liveAccount.phone || '—' }}</dd>
                </div>
                <div class="rider-profile-row">
                  <dt><i class="bi bi-bicycle" aria-hidden="true"></i> Vehicle</dt>
                  <dd>{{ liveRider?.vehicle_type || '—' }}</dd>
                </div>
                <div class="rider-profile-row">
                  <dt><i class="bi bi-credit-card-2-front" aria-hidden="true"></i> Plate</dt>
                  <dd>{{ liveRider?.plate_number || '—' }}</dd>
                </div>
              </dl>
              <div class="rider-profile-actions">
                <a class="btn btn-outline-primary btn-action" :href="`${baseUrl}profile`">
                  <i class="bi bi-person me-2" aria-hidden="true"></i>Account settings
                </a>
              </div>
            </section>
          </div>

          <div class="col-12 col-lg-6">
            <section class="surface rider-profile-card h-100">
              <h2 class="h5 mb-3">
                <i class="bi bi-award me-2" aria-hidden="true"></i>Performance
              </h2>
              <div class="rider-performance-grid">
                <div class="rider-performance-item">
                  <span class="rider-performance-value">{{ stats.completed_total }}</span>
                  <span class="rider-performance-label">Completed</span>
                </div>
                <div class="rider-performance-item">
                  <span class="rider-performance-value">{{ stats.completed_week }}</span>
                  <span class="rider-performance-label">This week</span>
                </div>
                <div class="rider-performance-item">
                  <span class="rider-performance-value">{{ stats.completed_today }}</span>
                  <span class="rider-performance-label">Today</span>
                </div>
                <div class="rider-performance-item">
                  <span class="rider-performance-value">{{ Number(stats.completed_distance_km || 0).toFixed(1) }}</span>
                  <span class="rider-performance-label">Km covered</span>
                </div>
              </div>

              <div class="rider-rating-summary">
                <div class="rider-rating-average">
                  <span class="rider-rating-number">{{ ratingDisplay }}</span>
                  <span class="rider-rating-stars" :aria-label="`Average rating ${ratingDisplay} out of 5`">
                    <i v-for="n in 5" :key="'avg-' + n" :class="['bi', starClass(stats.rating_average, n)]" aria-hidden="true"></i>
                  </span>
                  <span class="rider-rating-count text-secondary small">
                    {{ stats.rating_count }} rating{{ Number(stats.rating_count) === 1 ? '' : 's' }}
                  </span>
                </div>
                <p class="rider-rating-note text-secondary small mb-0">
                  {{ Number(stats.rating_five) }} customer{{ Number(stats.rating_five) === 1 ? '' : 's' }} rated you 5 stars.
                </p>
              </div>

              <div class="rider-profile-actions">
                <a class="btn btn-outline-primary btn-action" :href="`${baseUrl}rider`">
                  <i class="bi bi-arrow-clockwise me-2" aria-hidden="true"></i>Back to deliveries
                </a>
              </div>
            </section>
          </div>

          <div class="col-12">
            <section class="surface rider-profile-card rider-profile-note">
              <h2 class="h6 mb-2">
                <i class="bi bi-info-circle me-2" aria-hidden="true"></i>Availability
              </h2>
              <p class="text-secondary small mb-2">
                Staying <strong>available</strong> lets dispatch assign new orders to you. Switch to
                <strong>offline</strong> when you are done for the day. You cannot change availability
                while an active delivery is assigned to you.
              </p>
              <p class="text-secondary small mb-0">
                Push and in-app notifications for new assignments appear in the bell at the top of the page.
              </p>
            </section>
          </div>
        </div>
      </section>
    </template>
  </div>

  <!-- ─── Mobile bottom nav ───────────────────────── -->
  <nav v-if="portalLinked" class="rider-bottom-bar d-lg-none" aria-label="Rider mobile navigation">
    <div class="rider-bottom-bar-inner">
      <button
        type="button"
        class="rider-bottom-nav-item"
        :class="{ 'is-active': activeTab === 'active' }"
        :aria-current="activeTab === 'active' ? 'page' : undefined"
        @click="activeTab = 'active'"
      >
        <i class="bi bi-box-seam" aria-hidden="true"></i>
        <span>Active</span>
        <span v-if="activeCount" class="rider-bottom-badge">{{ activeCount }}</span>
      </button>
      <button
        type="button"
        class="rider-bottom-nav-item"
        :class="{ 'is-active': activeTab === 'history' }"
        @click="activeTab = 'history'"
      >
        <i class="bi bi-clock-history" aria-hidden="true"></i>
        <span>History</span>
      </button>
      <button
        type="button"
        class="rider-bottom-nav-item"
        :class="{ 'is-active': activeTab === 'profile' }"
        @click="activeTab = 'profile'"
      >
        <i class="bi bi-person" aria-hidden="true"></i>
        <span>Profile</span>
      </button>
      <button
        type="button"
        class="rider-bottom-nav-item"
        :disabled="refreshing"
        @click="loadData(true)"
      >
        <i class="bi" :class="refreshing ? 'bi-arrow-repeat rider-spin' : 'bi-arrow-clockwise'" aria-hidden="true"></i>
        <span>Refresh</span>
      </button>
    </div>
  </nav>
</template>

<style scoped>
/* ── page readiness ────────────────────────────────────── */
.rider-deliveries {
  opacity: 1;
  transition: opacity .2s ease-out;
}

@keyframes riderFadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.rider-fade-in { animation: riderFadeIn .35s ease-out both; }

@keyframes riderSpin { 100% { transform: rotate(360deg); } }
.rider-spin { animation: riderSpin .8s linear infinite; display: inline-block; }

@media (prefers-reduced-motion: reduce) {
  .rider-deliveries,
  .delivery-card,
  .rider-fade-in,
  .rider-spin,
  .ptr-indicator,
  .ptr-icon,
  .ptr-fade-enter-active,
  .ptr-fade-leave-active,
  .refresh-footer { transition: none; animation: none; }
}

/* ── hero ─────────────────────────────────────────────── */
.rider-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  padding: 1.25rem 1.5rem;
  border-radius: .75rem;
  border: 1px solid var(--line);
  box-shadow: var(--shadow-card);
}
.rider-hero__identity {
  display: flex;
  align-items: center;
  gap: 1rem;
  min-width: 0;
}
.rider-hero__avatar {
  flex-shrink: 0;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1.1rem;
  background: var(--status-blue-bg);
  color: var(--route-dark);
  border: 2px solid color-mix(in srgb, var(--route) 25%, var(--line));
}
.rider-hero__text { min-width: 0; }
.rider-hero__name {
  font-size: 1.35rem;
  font-weight: 700;
  margin-bottom: .375rem;
  overflow-wrap: anywhere;
}
.rider-hero__meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: .5rem .75rem;
}
.rider-hero__date {
  font-size: .8rem;
  color: var(--ink-2);
}
.rider-hero__actions {
  display: flex;
  align-items: center;
  gap: .5rem;
  flex-wrap: wrap;
}

/* ── status chips ─────────────────────────────────────── */
.rider-status-chip {
  display: inline-flex;
  align-items: center;
  gap: .375rem;
  padding: .25rem .7rem;
  border-radius: 999px;
  font-size: .75rem;
  font-weight: 700;
  letter-spacing: .02em;
  white-space: nowrap;
}
.rider-status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
}
.rider-status-chip--available {
  background: var(--status-success-bg);
  color: var(--success);
}
.rider-status-chip--busy {
  background: var(--status-blue-bg);
  color: var(--route-dark);
}
.rider-status-chip--offline {
  background: var(--paper);
  color: var(--ink-2);
  border: 1px solid var(--line);
}

/* ── duty toggle ──────────────────────────────────────── */
.rider-duty-toggle {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  min-height: 44px;
  padding: .5rem 1rem;
  border-radius: 999px;
  font-size: .85rem;
  font-weight: 700;
  border: 1px solid var(--line);
  background: var(--surface);
  color: var(--ink);
  transition: all .2s ease;
}
.rider-duty-toggle:disabled { opacity: .8; cursor: not-allowed; }
.rider-duty-toggle--on {
  border-color: color-mix(in srgb, var(--success) 45%, var(--line));
  background: var(--status-success-bg);
  color: var(--success);
}
.rider-duty-toggle--off { color: var(--ink-2); }
.rider-refresh-btn {
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  gap: .375rem;
}

/* ── KPI grid ─────────────────────────────────────────── */
.rider-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: .75rem;
  margin-bottom: 1rem;
}
.rider-kpi-card {
  display: flex;
  flex-direction: column;
  gap: .125rem;
  text-align: start;
  padding: .875rem 1rem;
  border: 1px solid var(--line);
  border-radius: .75rem;
  background: var(--surface);
  box-shadow: var(--shadow-card);
  color: inherit;
  transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
}
.rider-kpi-card:hover,
.rider-kpi-card:focus-visible {
  border-color: var(--route);
  box-shadow: var(--shadow-card-hover);
  transform: translateY(-2px);
}
.rider-kpi-icon {
  width: 32px;
  height: 32px;
  border-radius: .5rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: .95rem;
  margin-bottom: .25rem;
}
.rider-kpi-icon--active { background: var(--status-blue-bg); color: var(--route); }
.rider-kpi-icon--completed { background: var(--status-success-bg); color: var(--success); }
.rider-kpi-icon--rating { background: var(--status-pending-bg); color: var(--amber-ink); }
.rider-kpi-icon--week { background: var(--status-indigo-bg, var(--paper)); color: var(--status-indigo-ink, var(--route-dark)); }
.rider-kpi-label {
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--ink-2);
}
.rider-kpi-value {
  font-size: 1.6rem;
  font-weight: 700;
  line-height: 1.1;
  color: var(--ink);
  font-variant-numeric: tabular-nums;
}
.rider-kpi-hint {
  font-size: .72rem;
  color: var(--ink-3, var(--ink-2));
}

/* ── tabs ─────────────────────────────────────────────── */
.rider-tabs {
  display: flex;
  gap: .25rem;
  padding: .25rem;
  border: 1px solid var(--line);
  border-radius: .75rem;
  background: var(--paper);
  margin-bottom: 1rem;
  overflow-x: auto;
}
.rider-tab {
  flex: 1 1 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .4rem;
  min-height: 44px;
  padding: .5rem .75rem;
  border: 0;
  border-radius: .5rem;
  background: transparent;
  color: var(--ink-2);
  font-weight: 600;
  font-size: .875rem;
  white-space: nowrap;
  transition: background .2s ease, color .2s ease;
}
.rider-tab:hover { color: var(--ink); }
.rider-tab.is-active {
  background: var(--surface);
  color: var(--route-dark);
  box-shadow: var(--shadow-card);
}
.rider-tab-badge {
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--route);
  color: var(--surface);
  font-size: .7rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.rider-tab-badge--muted {
  background: var(--ink-3, var(--ink-2));
  opacity: .75;
}

/* ── empty states ─────────────────────────────────────── */
.empty-state {
  border-radius: .75rem;
  border: 1px solid var(--line);
}
.empty-state__icon {
  font-size: 3rem;
  color: var(--route);
  opacity: .55;
}
.empty-state__icon--warn {
  color: var(--amber);
  opacity: .7;
}

/* ── delivery card (mobile) ───────────────────────────── */
.delivery-card {
  border-radius: .75rem;
  border: 1px solid var(--line);
  padding: 0;
  overflow: hidden;
  transition: box-shadow .2s ease;
}
.delivery-card:active {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--route) 15%, transparent);
}

.delivery-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  padding: .875rem 1rem .5rem;
  gap: .375rem .5rem;
}
.delivery-card__head .status-chip {
  flex-shrink: 0;
  margin-inline-start: auto;
}
.delivery-card__order-link {
  font-size: 1.05rem;
  font-weight: 700;
  text-decoration: none;
  color: var(--route-dark);
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  min-width: 0;
  white-space: nowrap;
}
.delivery-card__order-link:hover { text-decoration: underline; }

.delivery-card__body {
  display: flex;
  flex-direction: column;
  gap: .625rem;
  padding: .5rem 1rem;
}
.delivery-card__addr {
  display: flex;
  align-items: flex-start;
  gap: .625rem;
}
.delivery-card__addr > div {
  flex: 1 1 auto;
  min-width: 0;
}
.delivery-card__icon {
  flex-shrink: 0;
  font-size: 1.15rem;
  margin-top: .15rem;
  width: 28px;
  text-align: center;
}
.delivery-card__icon--pickup  { color: var(--success); }
.delivery-card__icon--dropoff { color: var(--route); }

.delivery-card__addr-label {
  display: block;
  font-size: .7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: var(--ink-2);
  line-height: 1;
  margin-bottom: .15rem;
}
.delivery-card__addr-text {
  display: block;
  font-size: .9rem;
  color: var(--route-dark);
  line-height: 1.35;
  overflow-wrap: break-word;
}

.delivery-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: .375rem .75rem;
  padding: .25rem 1rem .5rem;
}
.delivery-card__meta-item {
  font-size: .78rem;
  color: var(--ink-2);
  display: inline-flex;
  align-items: center;
  gap: .3rem;
}
.delivery-card__meta-item--rating { color: var(--amber-ink); font-weight: 600; }

.delivery-card__foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  padding: .625rem 1rem .875rem;
  gap: .5rem;
  border-top: 1px solid var(--line);
  margin-top: .25rem;
}

/* ── action button (44px touch target) ────────────────── */
.btn-action {
  min-height: 44px;
  min-width: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
}

/* ── desktop table tweaks ─────────────────────────────── */
.table-clean th {
  font-size: .75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: var(--ink-2);
  border-bottom-width: 2px;
}
.table-clean td {
  padding-top: .75rem;
  padding-bottom: .75rem;
  vertical-align: middle;
}
.table-clean td:first-child a { white-space: nowrap; }

.delivery-rating-star {
  color: var(--amber);
  font-size: .8rem;
}

/* ── profile ──────────────────────────────────────────── */
.rider-profile-card {
  border-radius: .75rem;
  border: 1px solid var(--line);
  padding: 1.25rem 1.5rem;
  box-shadow: var(--shadow-card);
}
.rider-profile-head {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}
.rider-profile-avatar {
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  background: var(--status-blue-bg);
  color: var(--route-dark);
}
.rider-profile-list {
  display: flex;
  flex-direction: column;
  gap: 0;
}
.rider-profile-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: .625rem 0;
  border-top: 1px solid var(--line);
}
.rider-profile-row dt {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  font-size: .8rem;
  font-weight: 600;
  color: var(--ink-2);
  margin: 0;
}
.rider-profile-row dd {
  margin: 0;
  font-size: .875rem;
  font-weight: 600;
  text-align: end;
  overflow-wrap: anywhere;
}
.rider-profile-actions {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
  margin-top: 1rem;
}

.rider-performance-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: .75rem;
  margin-bottom: 1rem;
}
.rider-performance-item {
  display: flex;
  flex-direction: column;
  gap: .125rem;
  padding: .625rem .75rem;
  border-radius: .5rem;
  background: var(--paper);
  border: 1px solid var(--line);
}
.rider-performance-value {
  font-size: 1.25rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.rider-performance-label {
  font-size: .68rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--ink-2);
  font-weight: 600;
}
.rider-rating-summary {
  border-top: 1px solid var(--line);
  padding-top: 1rem;
}
.rider-rating-average {
  display: flex;
  align-items: center;
  gap: .625rem;
  flex-wrap: wrap;
  margin-bottom: .375rem;
}
.rider-rating-number {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1;
}
.rider-rating-stars { color: var(--amber); }
.rider-profile-note { padding: 1.25rem 1.5rem; }

/* ── small-screen card layout ─────────────────────────── */
@media (max-width: 991.98px) {
  .rider-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .rider-deliveries { padding-bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px)); }
}

@media (max-width: 575.98px) {
  .rider-hero { padding: 1rem; }
  .rider-hero__actions { width: 100%; }
  .rider-duty-toggle { flex: 1 1 auto; justify-content: center; }
  .delivery-card__head {
    flex-direction: column;
    align-items: flex-start;
    padding-bottom: .625rem;
  }
  .delivery-card__head .status-chip { margin-inline-start: 0; }
  .delivery-card__order-link { font-size: 1rem; }
  .delivery-card__foot { align-items: stretch; }
  .delivery-card__foot .btn-action { flex: 1 1 auto; }
  .delivery-card__foot small { align-self: center; }
  .rider-performance-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .rider-profile-row { flex-direction: column; align-items: flex-start; gap: .125rem; }
  .rider-profile-row dd { text-align: start; }
}

/* ── pull-to-refresh ──────────────────────────────────── */
.ptr-indicator {
  position: fixed;
  top: 0;
  left: 50%;
  translate: -50% 0;
  z-index: 1080;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .25rem;
  padding: .5rem 1rem;
  background: var(--surface);
  border-radius: 0 0 .75rem .75rem;
  box-shadow: 0 2px 12px rgba(0,0,0,.1);
  transition: transform .15s ease;
}
.ptr-icon {
  font-size: 1.25rem;
  transition: transform .25s ease, color .25s ease;
  color: var(--ink-2);
}
.ptr-icon--ready {
  transform: rotate(180deg);
  color: var(--route);
}
.ptr-label { font-size: .75rem; color: var(--ink-2); }

.ptr-fade-enter-active,
.ptr-fade-leave-active { transition: opacity .2s ease; }
.ptr-fade-enter-from,
.ptr-fade-leave-to     { opacity: 0; }

/* ── refresh footer ───────────────────────────────────── */
.refresh-footer {
  opacity: .65;
  transition: opacity .2s ease;
}
.refresh-footer:hover { opacity: 1; }

/* ── mobile bottom nav ────────────────────────────────── */
.rider-bottom-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: var(--surface);
  border-top: 1px solid var(--line);
  box-shadow: 0 -4px 16px rgba(0, 0, 0, .08);
  z-index: 1030;
  padding: .5rem .75rem calc(.5rem + env(safe-area-inset-bottom, 0px));
}
.rider-bottom-bar-inner {
  display: flex;
  align-items: center;
  justify-content: space-around;
  gap: .25rem;
}
.rider-bottom-nav-item {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  min-width: 64px;
  min-height: 44px;
  padding: .25rem .5rem;
  border: 0;
  border-radius: .5rem;
  background: transparent;
  color: var(--ink-2);
  font-size: .65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
  transition: color .2s ease, background .2s ease;
}
.rider-bottom-nav-item i { font-size: 1.2rem; }
.rider-bottom-nav-item:hover,
.rider-bottom-nav-item.is-active {
  color: var(--route);
  background: var(--status-blue-bg);
}
.rider-bottom-badge {
  position: absolute;
  top: 0;
  right: .375rem;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: var(--danger);
  color: var(--surface);
  font-size: .65rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
</style>
