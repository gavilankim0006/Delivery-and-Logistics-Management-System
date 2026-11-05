<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  orders:       { type: Array,   default: () => [] },
  portalLinked: { type: Boolean, default: false },
  baseUrl:      { type: String,  default: '/' },
});

/* ── relative-time helper ─────────────────────────────── */
function timeAgo(dateStr) {
  if (!dateStr) return '';
  const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
  if (diff < 60)   return 'just now';
  if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return `${Math.floor(diff / 86400)}d ago`;
}

/* ── tick: drives all reactive time strings ───────────── */
const tick = ref(Date.now());
let tickTimer = null;

/* ── last-refreshed tracking ──────────────────────────── */
const loadedAt = Date.now();
const lastRefreshedText = computed(() => {
  // touch `tick` so this recomputes every 15 s
  void tick.value;
  const mins = Math.floor((Date.now() - loadedAt) / 60000);
  if (mins < 1) return 'just now';
  return `${mins} min ago`;
});

/* ── auto-refresh (full page reload every 60 s) ───────── */
let autoRefreshTimer = null;

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
    // small delay so the user sees the indicator
    setTimeout(() => window.location.reload(), 350);
  } else {
    pulling.value = false;
    pullDistance.value = 0;
  }
}

/* ── lifecycle ────────────────────────────────────────── */
onMounted(() => {
  tickTimer = setInterval(() => { tick.value = Date.now(); }, 15000);
  autoRefreshTimer = setTimeout(() => window.location.reload(), 60000);

  document.addEventListener('touchstart',  onTouchStart, { passive: true });
  document.addEventListener('touchmove',   onTouchMove,  { passive: false });
  document.addEventListener('touchend',    onTouchEnd,   { passive: true });
});

onUnmounted(() => {
  clearInterval(tickTimer);
  clearTimeout(autoRefreshTimer);
  document.removeEventListener('touchstart', onTouchStart);
  document.removeEventListener('touchmove',  onTouchMove);
  document.removeEventListener('touchend',   onTouchEnd);
});

/* ── computed helpers ─────────────────────────────────── */
const activeCount = computed(() => props.orders.length);

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
    <!-- ─── Header ──────────────────────────────────── -->
    <header class="page-header mb-4">
      <div>
        <h1 class="page-title">
          My deliveries
          <span
            v-if="portalLinked && activeCount"
            class="badge bg-primary ms-2 count-badge"
            :aria-label="`${activeCount} active`"
          >{{ activeCount }}</span>
        </h1>
        <p class="page-description">
          Your active internal assignments, in pickup order.
        </p>
      </div>
    </header>

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

    <!-- ─── No deliveries state ─────────────────────── -->
    <section
      v-else-if="!orders.length"
      class="empty-state surface p-4 p-lg-5 text-center"
      role="status"
    >
      <i class="bi bi-inbox empty-state__icon"></i>
      <h2 class="h5 mt-3 mb-2">No active deliveries</h2>
      <p class="text-secondary mb-0">
        New assignments from dispatch will appear here.<br>
        Hang tight — you're all caught up!
      </p>
    </section>

    <!-- ─── Deliveries list ─────────────────────────── -->
    <template v-else>
      <!-- MOBILE cards (< lg) -->
      <div class="d-lg-none" aria-label="Active delivery assignments">
        <div
          v-for="order in orders"
          :key="'card-' + order.order_id"
          class="delivery-card surface mb-3"
        >
          <!-- top row: order number + status -->
          <div class="delivery-card__head">
            <a
              class="delivery-card__order-link"
              :href="`${baseUrl}rider/orders/${order.order_id}`"
            >
              {{ order.order_no }}
            </a>
            <span :class="chipClass(order.status)">{{ chipLabel(order.status) }}</span>
          </div>

          <!-- addresses -->
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

          <!-- footer: time + action -->
          <div class="delivery-card__foot">
            <small v-if="assignedAgo(order)" class="text-secondary">
              <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ assignedAgo(order) }}
            </small>
            <a
              class="btn btn-primary btn-action"
              :href="`${baseUrl}rider/orders/${order.order_id}`"
            >
              Open delivery
            </a>
          </div>
        </div>
      </div>

      <!-- DESKTOP table (≥ lg) -->
      <section
        class="surface d-none d-lg-block"
        aria-label="Active delivery assignments"
      >
        <div class="table-responsive">
          <table class="table table-clean table-hover align-middle mb-0">
            <thead>
              <tr>
                <th scope="col">Order</th>
                <th scope="col">Pickup</th>
                <th scope="col">Drop-off</th>
                <th scope="col">Assigned</th>
                <th scope="col">Status</th>
                <th scope="col"><span class="visually-hidden">Action</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in orders" :key="'row-' + order.order_id">
                <td>
                  <a
                    class="fw-semibold"
                    :href="`${baseUrl}rider/orders/${order.order_id}`"
                  >{{ order.order_no }}</a>
                </td>
                <td>
                  <i class="bi bi-box-arrow-up text-success me-1" aria-hidden="true"></i>
                  {{ order.pickup_address }}
                </td>
                <td>
                  <i class="bi bi-geo-alt-fill text-danger me-1" aria-hidden="true"></i>
                  {{ order.dropoff_address }}
                </td>
                <td>
                  <small class="text-secondary">{{ assignedAgo(order) || '—' }}</small>
                </td>
                <td>
                  <span :class="chipClass(order.status)">{{ chipLabel(order.status) }}</span>
                </td>
                <td class="text-end">
                  <a
                    class="btn btn-sm btn-outline-primary btn-action"
                    :href="`${baseUrl}rider/orders/${order.order_id}`"
                  >Open delivery</a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ─── Last-refreshed footer ─────────────────── -->
      <footer class="refresh-footer mt-3 text-center text-secondary">
        <small>
          <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
          Last refreshed {{ lastRefreshedText }} · auto-refreshes every 60 s
        </small>
      </footer>
    </template>
  </div>
</template>

<style scoped>
/* ── page readiness ────────────────────────────────────── */
.rider-deliveries {
  opacity: 1;
  transition: opacity .2s ease-out;
}

@media (prefers-reduced-motion: reduce) {
  .rider-deliveries,
  .delivery-card,
  .ptr-indicator,
  .ptr-icon,
  .ptr-fade-enter-active,
  .ptr-fade-leave-active,
  .refresh-footer { transition: none; }
}

/* ── empty states ─────────────────────────────────────── */
.empty-state {
  border-radius: .75rem;
  border: 1px solid var(--line, #e5e7eb);
}
.empty-state__icon {
  font-size: 3rem;
  color: var(--bs-primary, #0d6efd);
  opacity: .55;
}
.empty-state__icon--warn {
  color: var(--bs-warning, #ffc107);
  opacity: .7;
}

/* ── count badge ──────────────────────────────────────── */
.count-badge {
  font-size: .55em;
  vertical-align: middle;
  border-radius: 999px;
  padding: .25em .65em;
}

/* ── delivery card (mobile) ───────────────────────────── */
.delivery-card {
  border-radius: .75rem;
  border: 1px solid var(--line, #e5e7eb);
  overflow: hidden;
  transition: box-shadow .2s ease;
}
.delivery-card:active {
  box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb, 13,110,253), .15);
}

.delivery-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .875rem 1rem .5rem;
  gap: .5rem;
}
.delivery-card__order-link {
  font-size: 1.05rem;
  font-weight: 700;
  text-decoration: none;
  color: var(--route-dark, #1e293b);
  min-height: 44px;
  display: inline-flex;
  align-items: center;
}
.delivery-card__order-link:hover {
  text-decoration: underline;
}

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
.delivery-card__icon {
  flex-shrink: 0;
  font-size: 1.15rem;
  margin-top: .15rem;
  width: 28px;
  text-align: center;
}
.delivery-card__icon--pickup  { color: #16a34a; }
.delivery-card__icon--dropoff { color: #dc3545; }

.delivery-card__addr-label {
  display: block;
  font-size: .7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: var(--bs-secondary-color, #6c757d);
  line-height: 1;
  margin-bottom: .15rem;
}
.delivery-card__addr-text {
  display: block;
  font-size: .9rem;
  color: var(--route-dark, #1e293b);
  line-height: 1.35;
}

.delivery-card__foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .625rem 1rem .875rem;
  gap: .5rem;
  border-top: 1px solid var(--line, #e5e7eb);
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
  color: var(--bs-secondary-color, #6c757d);
  border-bottom-width: 2px;
}
.table-clean td {
  padding-top: .75rem;
  padding-bottom: .75rem;
  vertical-align: middle;
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
  background: var(--surface, #fff);
  border-radius: 0 0 .75rem .75rem;
  box-shadow: 0 2px 12px rgba(0,0,0,.1);
  transition: transform .15s ease;
}
.ptr-icon {
  font-size: 1.25rem;
  transition: transform .25s ease, color .25s ease;
  color: var(--bs-secondary-color, #6c757d);
}
.ptr-icon--ready {
  transform: rotate(180deg);
  color: var(--bs-primary, #0d6efd);
}
.ptr-label {
  font-size: .75rem;
  color: var(--bs-secondary-color, #6c757d);
}

.ptr-fade-enter-active,
.ptr-fade-leave-active { transition: opacity .2s ease; }
.ptr-fade-enter-from,
.ptr-fade-leave-to     { opacity: 0; }

/* ── refresh footer ───────────────────────────────────── */
.refresh-footer {
  opacity: .65;
  transition: opacity .2s ease;
}
.refresh-footer:hover {
  opacity: 1;
}
</style>
