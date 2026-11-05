<script setup>
import { onMounted, onUnmounted, ref, computed } from 'vue';

const props = defineProps({
    dataUrl: { type: String, required: true },
    baseUrl: { type: String, required: true },
    csrfToken: { type: String, required: true },
    fulfillmentActions: { type: Boolean, default: false },
    availableRiders: { type: Array, default: () => [] }
});

const orders = ref([]);
const availableRiders = ref(props.availableRiders);
const loading = ref(true);
const refreshing = ref(false);
const error = ref('');
const lastUpdated = ref('');
const search = ref(document.getElementById('dispatch-search')?.value || '');
const expandedCards = ref(new Set());
const summary = ref({
    today_total: 0,
    awaiting_booking: 0,
    in_transit: 0,
    failed_count: 0
});
const attentionOrders = computed(() => orders.value.filter(order =>
    order.status === 'failed' || (order.recovery && ['delayed', 'failed'].includes(order.recovery.priority))
));
let refreshTimer;
let searchTimer;
let activeRequest;

// Track previous order IDs for "new" animation
const previousOrderIds = ref(new Set());

function getFilterParams() {
    const form = document.getElementById('dispatch-filter-form');
    return form ? new URLSearchParams(new FormData(form)) : new URLSearchParams();
}

async function loadBoard(isManualRefresh = false) {
    activeRequest?.abort();
    const controller = new AbortController();
    activeRequest = controller;

    if (isManualRefresh) refreshing.value = true;

    const params = getFilterParams();

    try {
        const response = await fetch(`${props.dataUrl}?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store',
            signal: controller.signal
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.error || 'Unable to load dispatch orders.');
        }

        // Track new orders for animation
        const oldIds = new Set(orders.value.map(o => o.id));
        orders.value = result.data.orders;
        const newIds = new Set(result.data.orders.map(o => o.id));
        previousOrderIds.value = oldIds;

        if (Array.isArray(result.data.available_riders)) {
            availableRiders.value = result.data.available_riders;
        }
        if (result.data) {
            summary.value = {
                today_total: Number(result.data.today_total ?? 0),
                awaiting_booking: Number(result.data.awaiting_booking ?? 0),
                in_transit: Number(result.data.in_transit ?? 0),
                failed_count: Number(result.data.failed_count ?? 0)
            };
        }
        const controlsRoot = document.getElementById('dispatch-controls-vue-root');
        if (controlsRoot) {
            controlsRoot.dispatchEvent(new CustomEvent('dispatch-summary', { detail: {
                today_total: result.data.today_total,
                awaiting_booking: result.data.awaiting_booking,
                in_transit: result.data.in_transit,
                failed_count: result.data.failed_count
            } }));
        }
        error.value = '';
        lastUpdated.value = new Date().toLocaleTimeString([], {
            hour: 'numeric', minute: '2-digit', second: '2-digit'
        });
        const liveStatus = document.getElementById('dispatch-live-status');
        if (liveStatus) liveStatus.textContent = `Updated ${lastUpdated.value}`;
    } catch (requestError) {
        if (requestError.name !== 'AbortError') {
            error.value = requestError.message || 'Unable to refresh the dispatch board.';
            const liveStatus = document.getElementById('dispatch-live-status');
            if (liveStatus) liveStatus.textContent = 'Update failed';
        }
    } finally {
        if (!controller.signal.aborted) {
            loading.value = false;
            refreshing.value = false;
        }
    }
}

function isNewOrder(orderId) {
    return previousOrderIds.value.size > 0 && !previousOrderIds.value.has(orderId);
}

function onSearch() {
    search.value = document.getElementById('dispatch-search')?.value || '';
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadBoard, 300);
}

function formatDate(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleString([], {
        month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit'
    });
}

function timeAgo(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return '';
    const minutes = Math.floor((Date.now() - date.getTime()) / 60000);
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    return `${Math.floor(hours / 24)}d ago`;
}

function statusLabel(status) {
    return String(status || 'draft').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatStateAge(value) {
    const minutes = Math.max(0, Number(value || 0));
    const days = Math.floor(minutes / 1440);
    const hours = Math.floor((minutes % 1440) / 60);
    const remainder = Math.floor(minutes % 60);
    if (days) return `${days}d ${hours}h`;
    if (hours) return `${hours}h ${remainder}m`;
    return `${remainder}m`;
}

function fulfillmentHint(order) {
    const parts = [];
    if (order.fulfillment_message) {
        parts.push(String(order.fulfillment_message)
            .replace(/A provider quotation exists without an explicit fulfillment selection; review before booking\.?/i, 'Quote received, no fulfillment chosen.')
            .replace(/A recovery attempt is in progress or provider state needs review; do not retry automatically\.?/i, 'Courier status needs review before retrying.'));
    }
    if (order.fulfillment_state_age_minutes !== null && order.fulfillment_state_age_minutes !== undefined && order.fulfillment_state_age_minutes !== '') {
        parts.push(`In this state for ${formatStateAge(order.fulfillment_state_age_minutes)}`);
    }
    return parts.join(' ');
}

function statusClass(status) {
    return ({
        draft: 'draft', pending_payment: 'pending', paid: 'paid', booking: 'booking',
        rider_assigned: 'assigned', picked_up: 'picked', delivered: 'delivered',
        cancelled: 'cancelled', failed: 'failed'
    })[status] || 'draft';
}

function money(amount) {
    return Number(amount || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });
}

function orderUrl(id) {
    return `${props.baseUrl}orders/${encodeURIComponent(id)}`;
}

function recoveryUrl(id) {
    return `${props.baseUrl}dispatch/recover/${encodeURIComponent(id)}`;
}

function assignInternalUrl(id) {
    return `${props.baseUrl}dispatch/orders/${encodeURIComponent(id)}/assign-internal`;
}

function selectLalamoveUrl(id) {
    return `${props.baseUrl}dispatch/orders/${encodeURIComponent(id)}/select-lalamove`;
}

function canChooseFulfillment(order) {
    return props.fulfillmentActions
        && order.status === 'paid'
        && !order.fulfillment_mode
        && !order.rider_id
        && !order.lalamove_quotation_id
        && !order.lalamove_order_id;
}

function canReassignInternal(order) {
    return props.fulfillmentActions
        && order.status === 'rider_assigned'
        && order.fulfillment_mode === 'internal'
        && Boolean(Number(order.active_internal_assignment));
}

function canRetryInternalAssignment(order) {
    return props.fulfillmentActions
        && order.status === 'failed'
        && order.fulfillment_mode === 'internal'
        && !order.rider_id
        && !order.lalamove_quotation_id
        && !order.lalamove_order_id;
}

function hasActions(order) {
    return canChooseFulfillment(order) || canReassignInternal(order) || canRetryInternalAssignment(order) || order.recovery;
}

function trackingUrl(value) {
    if (typeof value !== 'string' || value.trim() === '') return null;
    try {
        const url = new URL(value, window.location.origin);
        return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
    } catch {
        return null;
    }
}

function phoneUrl(phone) {
    return `tel:${String(phone || '').replace(/[^+0-9]/g, '')}`;
}

function recoveryClass(priority) {
    if (priority === 'failed') return 'dispatch-recovery-badge--failed';
    if (priority === 'delayed') return 'dispatch-recovery-badge--delayed';
    return 'dispatch-recovery-badge--ready';
}

function rowUrgencyClass(order) {
    if (order.status === 'failed') return 'is-urgent';
    if (order.recovery && ['delayed', 'failed'].includes(order.recovery.priority)) return 'is-warning';
    return '';
}

function cardUrgencyClass(order) {
    if (order.status === 'failed') return 'dispatch-order-card--urgent';
    if (order.recovery && ['delayed', 'failed'].includes(order.recovery.priority)) return 'dispatch-order-card--warning';
    return '';
}

function toggleCardExpand(orderId) {
    const next = new Set(expandedCards.value);
    if (next.has(orderId)) {
        next.delete(orderId);
    } else {
        next.add(orderId);
    }
    expandedCards.value = next;
}

function isCardExpanded(orderId) {
    return expandedCards.value.has(orderId);
}

function riderDisplayName(order) {
    return order.courier_driver_name || order.rider_name || null;
}

function riderDisplayDetails(order) {
    const parts = [];
    if (order.courier_driver_vehicle || order.plate_number) parts.push(order.courier_driver_vehicle || order.plate_number);
    if (order.courier_driver_phone || order.rider_phone) parts.push(order.courier_driver_phone || order.rider_phone);
    return parts.join(' · ');
}

function onFilterSubmit(event) {
    event.preventDefault();
    const params = getFilterParams();
    window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
    loadBoard();
}

// Keyboard shortcut: R to refresh
function onKeydown(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') return;
    if (e.key === 'r' || e.key === 'R') {
        e.preventDefault();
        loadBoard(true);
    }
}

onMounted(() => {
    document.getElementById('dispatch-filter-form')?.addEventListener('submit', onFilterSubmit);
    document.getElementById('dispatch-search')?.addEventListener('input', onSearch);
    document.getElementById('dispatch-date')?.addEventListener('change', loadBoard);
    document.getElementById('dispatch-status')?.addEventListener('change', loadBoard);
    document.addEventListener('keydown', onKeydown);
    loadBoard();
    refreshTimer = setInterval(loadBoard, 30000);
});

onUnmounted(() => {
    document.getElementById('dispatch-filter-form')?.removeEventListener('submit', onFilterSubmit);
    document.getElementById('dispatch-search')?.removeEventListener('input', onSearch);
    document.getElementById('dispatch-date')?.removeEventListener('change', loadBoard);
    document.getElementById('dispatch-status')?.removeEventListener('change', loadBoard);
    document.removeEventListener('keydown', onKeydown);
    clearInterval(refreshTimer);
    clearTimeout(searchTimer);
    activeRequest?.abort();
});
</script>

<template>
    <!-- Live status + manual refresh bar -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="dispatch-live-indicator" role="status" aria-live="polite">
            <span
                class="dispatch-live-dot"
                :class="{
                    'dispatch-live-dot--error': error,
                    'dispatch-live-dot--loading': refreshing || loading
                }"
            ></span>
            <span v-if="error">Connection issue</span>
            <span v-else-if="loading && !lastUpdated">Connecting…</span>
            <span v-else-if="refreshing">Refreshing…</span>
            <span v-else>Live · {{ lastUpdated }}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="dispatch-kbd-hint d-none d-md-inline-flex">
                Press <kbd>R</kbd> to refresh
            </span>
            <button
                type="button"
                class="btn btn-sm btn-secondary"
                :disabled="refreshing"
                @click="loadBoard(true)"
                :aria-busy="refreshing ? 'true' : 'false'"
            >
                <i class="bi" :class="refreshing ? 'bi-arrow-repeat dispatch-spin-icon' : 'bi-arrow-clockwise'" aria-hidden="true"></i>
                <span class="d-none d-sm-inline">Refresh</span>
            </button>
        </div>
    </div>

    <section v-if="attentionOrders.length" class="dispatch-attention-panel mb-3" aria-labelledby="dispatch-attention-title">
        <div class="dispatch-attention-heading">
            <div>
                <h2 id="dispatch-attention-title" class="h6 mb-1">Needs attention</h2>
                <p class="small text-muted mb-0">Failed or delayed orders are listed first.</p>
            </div>
            <span class="badge text-bg-danger">{{ attentionOrders.length }}</span>
        </div>
        <ul class="dispatch-attention-list list-unstyled mb-0">
            <li v-for="order in attentionOrders" :key="`attention-${order.id}`" class="dispatch-attention-item">
                <div class="min-w-0">
                    <a class="fw-semibold" :href="`${baseUrl}orders/${order.id}`">{{ order.order_no }}</a>
                    <span class="small text-muted ms-2">{{ order.recovery?.message || 'Order marked failed' }}</span>
                </div>
                <a class="btn btn-sm btn-outline-primary flex-shrink-0" :href="orderUrl(order.id)">
                    {{ hasActions(order) ? 'Open actions' : 'Review order' }}
                </a>
            </li>
        </ul>
    </section>

    <!-- ═══ DESKTOP TABLE VIEW ═══ -->
    <div class="dispatch-table-desktop">
        <div class="table-responsive dispatch-table-scroll" :style="{ position: 'relative' }">
            <!-- Loading overlay for refresh -->
            <div class="dispatch-loading-overlay" :class="{ 'is-visible': refreshing }">
                <div class="dispatch-loading-spinner"></div>
            </div>

            <table class="table table-clean table-responsive-stack dispatch-orders-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Pickup to drop-off</th>
                        <th scope="col">Status</th>
                        <th scope="col">Rider</th>
                        <th scope="col" class="text-end">Fee</th>
                        <th scope="col" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="error">
                        <td colspan="7" class="text-center py-4" role="alert">
                            <div class="dispatch-empty-state" style="padding: var(--s-4);">
                                <div class="dispatch-empty-icon" style="background: var(--status-danger-bg); color: var(--danger);">
                                    <i class="bi bi-wifi-off" aria-hidden="true"></i>
                                </div>
                                <h3 class="text-danger">{{ error }}</h3>
                                <button type="button" class="btn btn-sm btn-primary" @click="loadBoard(true)">
                                    <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>
                                    Try again
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-else-if="loading && !orders.length">
                        <td colspan="7" class="text-center py-4 text-muted" role="status">
                            <div class="d-flex align-items-center justify-content-center gap-2">
                                <div class="dispatch-loading-spinner" style="width: 20px; height: 20px; border-width: 2px;"></div>
                                Loading dispatch orders…
                            </div>
                        </td>
                    </tr>
                    <tr v-else-if="orders.length === 0">
                        <td colspan="7">
                            <div class="dispatch-empty-state">
                                <div class="dispatch-empty-icon">
                                    <i class="bi bi-inbox" aria-hidden="true"></i>
                                </div>
                                <h3>No orders match these filters</h3>
                                <p>Try adjusting your search or filter criteria to find what you're looking for.</p>
                            </div>
                        </td>
                    </tr>
                    <tr
                        v-for="order in orders"
                        :key="order.id"
                        :class="[rowUrgencyClass(order), { 'is-new': isNewOrder(order.id) }]"
                    >
                        <td data-label="Order" class="fw-semibold text-nowrap">
                            {{ order.order_no }}
                            <div class="text-muted small">{{ formatDate(order.created_at) }}</div>
                            <span v-if="Number(order.pending_request_count) > 0" class="dispatch-recovery-badge dispatch-recovery-badge--delayed mt-1">
                                <i class="bi bi-pause-circle" aria-hidden="true"></i>
                                {{ order.pending_request_count }} hold
                            </span>
                        </td>
                        <td data-label="Customer">
                            <div class="fw-medium">{{ order.customer_name }}</div>
                            <div v-if="order.company_name" class="text-muted small">{{ order.company_name }}</div>
                        </td>
                        <td data-label="Pickup to drop-off" class="route-summary route-cell align-middle">
                            <div class="route-summary__address"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><span class="visually-hidden">Pickup: </span>{{ order.pickup_address }}</div>
                            <div class="route-summary__address"><i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i><span class="visually-hidden">Drop-off: </span>{{ order.dropoff_address }}</div>
                            <span class="text-muted small">{{ order.distance_km }} km · about {{ order.estimated_duration_min }} min</span>
                        </td>
                        <td data-label="Status">
                            <span class="status-chip" :class="`status-chip--${statusClass(order.status)}`">{{ statusLabel(order.status) }}</span>
                            <template v-if="order.recovery">
                                <span :class="['dispatch-recovery-badge', recoveryClass(order.recovery.priority)]" class="mt-1 d-inline-flex">
                                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                                    {{ formatStateAge(order.recovery.age_minutes) }}
                                </span>
                            </template>
                            <span class="dispatch-status-extra small text-muted">
                                <span class="dispatch-status-rider">{{ order.courier_driver_name || order.rider_name || 'Not assigned' }}</span>
                                <span class="dispatch-status-fee">₱{{ money(order.delivery_fee) }}</span>
                            </span>
                        </td>
                        <td data-label="Rider">
                            <template v-if="riderDisplayName(order)">
                                <div class="fw-medium">{{ riderDisplayName(order) }}</div>
                                <div v-if="riderDisplayDetails(order)" class="text-muted small">{{ riderDisplayDetails(order) }}</div>
                                <a v-if="trackingUrl(order.share_link)" :href="trackingUrl(order.share_link)" target="_blank" rel="noopener noreferrer" class="d-inline-flex align-items-center gap-1 mt-1 small">
                                    <i class="bi bi-geo" aria-hidden="true"></i>
                                    Live tracking
                                    <span class="visually-hidden">(opens in a new tab)</span>
                                </a>
                            </template>
                            <span v-else class="text-muted text-nowrap">
                                <i class="bi bi-person-dash me-1" aria-hidden="true"></i>
                                Not assigned
                            </span>
                        </td>
                        <td data-label="Fee" class="fw-semibold text-nowrap text-end">₱{{ money(order.delivery_fee) }}</td>
                        <td data-label="Action" class="text-end align-middle">
                            <div v-if="canChooseFulfillment(order) || canReassignInternal(order) || canRetryInternalAssignment(order)" class="dispatch-fulfillment-actions text-start mb-2">
                                <span class="form-label d-block">{{ canReassignInternal(order) ? 'Change internal rider' : (canRetryInternalAssignment(order) ? 'Reassign internal rider' : 'Choose fulfillment') }}<button v-if="fulfillmentHint(order)" type="button" class="dispatch-fulfillment-hint small text-muted" tabindex="0" :title="fulfillmentHint(order)" :aria-label="fulfillmentHint(order)"><i class="bi bi-info-circle" aria-hidden="true"></i><span class="visually-hidden">Fulfillment details</span></button></span>
                                <form method="post" :action="assignInternalUrl(order.id)" class="dispatch-fulfillment-form">
                                    <input type="hidden" name="csrf_token" :value="csrfToken">
                                    <label class="visually-hidden" :for="`rider-${order.id}`">Select an internal rider for order {{ order.order_no }}</label>
                                    <select :id="`rider-${order.id}`" name="rider_id" class="form-select form-select-sm" required :disabled="!availableRiders.length">
                                        <option value="" disabled selected>Select rider</option>
                                        <option v-if="!availableRiders.length" value="" disabled>No riders available</option>
                                        <option v-for="rider in availableRiders" :key="rider.id" :value="rider.id">{{ rider.name }} · {{ rider.vehicle_type }}<template v-if="rider.plate_number"> · {{ rider.plate_number }}</template></option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary" :disabled="!availableRiders.length">{{ canReassignInternal(order) || canRetryInternalAssignment(order) ? 'Assign rider again' : 'Assign rider' }}</button>
                                </form>
                                <form v-if="canChooseFulfillment(order)" method="post" :action="selectLalamoveUrl(order.id)" class="dispatch-lalamove-form">
                                    <input type="hidden" name="csrf_token" :value="csrfToken">
                                    <button type="submit" class="btn btn-sm btn-outline-primary lalamove-action">
                                        <i class="bi bi-truck me-1" aria-hidden="true"></i>
                                        Book Lalamove
                                    </button>
                                </form>
                            </div>
                            <template v-if="order.recovery">
                                <button type="button" class="dispatch-fulfillment-hint small text-muted" tabindex="0" :title="`${order.recovery.message} (${formatStateAge(order.recovery.age_minutes)})`" :aria-label="`${order.recovery.message} (${formatStateAge(order.recovery.age_minutes)})`"><span class="visually-hidden">{{ statusLabel(order.recovery.priority) }}: </span><i class="bi bi-info-circle" aria-hidden="true"></i><span class="visually-hidden">Fulfillment details</span></button>
                                <form v-if="['book', 'retry'].includes(order.recovery.kind)" method="post" :action="recoveryUrl(order.id)" class="mb-2">
                                    <input type="hidden" name="csrf_token" :value="csrfToken">
                                    <button type="submit" class="btn btn-sm btn-primary">{{ order.recovery.label }}</button>
                                </form>
                                <a v-else-if="order.recovery.kind === 'escalate' && order.pickup_phone" class="btn btn-sm btn-outline-primary mb-2 ms-2" :href="phoneUrl(order.pickup_phone)">
                                    <i class="bi bi-telephone me-1" aria-hidden="true"></i>
                                    Call customer
                                </a>
                            </template>
                            <a :href="orderUrl(order.id)" class="dispatch-row-action">
                                <i class="bi bi-eye me-1" aria-hidden="true"></i>
                                View order
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="dispatch-live-note small text-muted px-3 py-2" role="status">
                {{ loading ? 'Refreshing…' : `${orders.length} orders · Last updated ${lastUpdated}` }}
                <span v-if="search" class="visually-hidden">Search: {{ search }}</span>
            </div>
        </div>
    </div>

    <!-- ═══ MOBILE CARD VIEW ═══ -->
    <div class="dispatch-order-cards">
        <!-- Error -->
        <div v-if="error" class="dispatch-empty-state">
            <div class="dispatch-empty-icon" style="background: var(--status-danger-bg); color: var(--danger);">
                <i class="bi bi-wifi-off" aria-hidden="true"></i>
            </div>
            <h3 class="text-danger">{{ error }}</h3>
            <button type="button" class="btn btn-sm btn-primary" @click="loadBoard(true)">
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>
                Try again
            </button>
        </div>

        <!-- Loading -->
        <div v-else-if="loading && !orders.length" class="dispatch-empty-state">
            <div class="dispatch-loading-spinner"></div>
            <p class="text-muted">Loading dispatch orders…</p>
        </div>

        <!-- Empty -->
        <div v-else-if="orders.length === 0" class="dispatch-empty-state">
            <div class="dispatch-empty-icon">
                <i class="bi bi-inbox" aria-hidden="true"></i>
            </div>
            <h3>No orders found</h3>
            <p>Try adjusting your search or filters.</p>
        </div>

        <!-- Order cards -->
        <div
            v-for="order in orders"
            :key="order.id"
            class="dispatch-order-card"
            :class="cardUrgencyClass(order)"
        >
            <!-- Card Header -->
            <div class="dispatch-order-card-header">
                <a :href="orderUrl(order.id)" class="dispatch-order-card-id">
                    {{ order.order_no }}
                </a>
                <span class="status-chip" :class="`status-chip--${statusClass(order.status)}`">{{ statusLabel(order.status) }}</span>
            </div>

            <!-- Customer -->
            <div class="mb-2">
                <div class="fw-medium small">{{ order.customer_name }}</div>
                <div v-if="order.company_name" class="text-muted" style="font-size: .75rem;">{{ order.company_name }}</div>
            </div>

            <!-- Route Summary -->
            <div class="dispatch-order-card-route">
                <div class="dispatch-order-card-stop">
                    <span class="dispatch-order-card-stop-dot dispatch-order-card-stop-dot--pickup"></span>
                    <span>{{ order.pickup_address }}</span>
                </div>
                <div class="dispatch-order-card-connector"></div>
                <div class="dispatch-order-card-stop">
                    <span class="dispatch-order-card-stop-dot dispatch-order-card-stop-dot--dropoff"></span>
                    <span>{{ order.dropoff_address }}</span>
                </div>
            </div>

            <!-- Meta Row -->
            <div class="dispatch-order-card-meta">
                <span class="dispatch-order-card-meta-item">
                    <i class="bi bi-rulers" aria-hidden="true"></i>
                    {{ order.distance_km }} km
                </span>
                <span class="dispatch-order-card-meta-item">
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    ~{{ order.estimated_duration_min }} min
                </span>
                <span class="dispatch-order-card-meta-item">
                    <i class="bi bi-cash" aria-hidden="true"></i>
                    ₱{{ money(order.delivery_fee) }}
                </span>
                <span v-if="riderDisplayName(order)" class="dispatch-order-card-meta-item">
                    <i class="bi bi-person" aria-hidden="true"></i>
                    {{ riderDisplayName(order) }}
                </span>
                <span class="dispatch-order-card-meta-item">
                    <i class="bi bi-calendar" aria-hidden="true"></i>
                    {{ timeAgo(order.created_at) }}
                </span>
            </div>

            <!-- Recovery Badge -->
            <div v-if="order.recovery" class="mb-2">
                <span :class="['dispatch-recovery-badge', recoveryClass(order.recovery.priority)]">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                    {{ order.recovery.message.substring(0, 60) }}{{ order.recovery.message.length > 60 ? '…' : '' }}
                    ({{ formatStateAge(order.recovery.age_minutes) }})
                </span>
            </div>

            <!-- Pending requests badge -->
            <div v-if="Number(order.pending_request_count) > 0" class="mb-2">
                <span class="dispatch-recovery-badge dispatch-recovery-badge--delayed">
                    <i class="bi bi-pause-circle" aria-hidden="true"></i>
                    {{ order.pending_request_count }} hold request{{ Number(order.pending_request_count) > 1 ? 's' : '' }}
                </span>
            </div>

            <!-- Card Footer with Actions -->
            <div class="dispatch-order-card-footer">
                <!-- Expandable actions for orders that need fulfillment -->
                <template v-if="hasActions(order)">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        @click="toggleCardExpand(order.id)"
                        :aria-expanded="isCardExpanded(order.id)"
                        :aria-controls="`dispatch-card-actions-${order.id}`"
                    >
                        <i class="bi bi-lightning" aria-hidden="true"></i>
                        {{ isCardExpanded(order.id) ? 'Hide actions' : 'Actions' }}
                    </button>
                </template>
                <a :href="orderUrl(order.id)" class="btn btn-sm btn-secondary">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                    View
                </a>
                <a v-if="trackingUrl(order.share_link)" :href="trackingUrl(order.share_link)" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-secondary">
                    <i class="bi bi-geo" aria-hidden="true"></i>
                    Track
                </a>
            </div>

            <!-- Expanded Actions Panel -->
            <div v-if="isCardExpanded(order.id) && hasActions(order)" :id="`dispatch-card-actions-${order.id}`" class="mt-3 pt-3" style="border-top: 1px solid var(--line);">
                <!-- Fulfillment form -->
                <div v-if="canChooseFulfillment(order) || canReassignInternal(order) || canRetryInternalAssignment(order)">
                    <span class="form-label d-block small fw-bold mb-2">
                        {{ canReassignInternal(order) ? 'Change internal rider' : (canRetryInternalAssignment(order) ? 'Reassign internal rider' : 'Choose fulfillment') }}
                    </span>
                    <form method="post" :action="assignInternalUrl(order.id)" class="mb-2">
                        <input type="hidden" name="csrf_token" :value="csrfToken">
                        <label class="visually-hidden" :for="`m-rider-${order.id}`">Select an internal rider for order {{ order.order_no }}</label>
                        <select :id="`m-rider-${order.id}`" name="rider_id" class="form-select form-select-sm mb-2" required :disabled="!availableRiders.length">
                            <option value="" disabled selected>Select rider</option>
                            <option v-if="!availableRiders.length" value="" disabled>No riders available</option>
                            <option v-for="rider in availableRiders" :key="rider.id" :value="rider.id">{{ rider.name }} · {{ rider.vehicle_type }}</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary w-100" :disabled="!availableRiders.length">
                            <i class="bi bi-person-check me-1" aria-hidden="true"></i>
                            {{ canReassignInternal(order) || canRetryInternalAssignment(order) ? 'Assign rider again' : 'Assign rider' }}
                        </button>
                    </form>
                    <form v-if="canChooseFulfillment(order)" method="post" :action="selectLalamoveUrl(order.id)">
                        <input type="hidden" name="csrf_token" :value="csrfToken">
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                            <i class="bi bi-truck me-1" aria-hidden="true"></i>
                            Book Lalamove
                        </button>
                    </form>
                </div>

                <!-- Recovery actions -->
                <div v-if="order.recovery" class="mt-2">
                    <form v-if="['book', 'retry'].includes(order.recovery.kind)" method="post" :action="recoveryUrl(order.id)">
                        <input type="hidden" name="csrf_token" :value="csrfToken">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
                            {{ order.recovery.label }}
                        </button>
                    </form>
                    <a v-else-if="order.recovery.kind === 'escalate' && order.pickup_phone" class="btn btn-sm btn-outline-primary w-100" :href="phoneUrl(order.pickup_phone)">
                        <i class="bi bi-telephone me-1" aria-hidden="true"></i>
                        Call customer
                    </a>
                </div>

                <button type="button" class="btn btn-sm btn-secondary w-100 mt-2" @click="toggleCardExpand(order.id)">
                    <i class="bi bi-chevron-up me-1" aria-hidden="true"></i>
                    Collapse
                </button>
            </div>
        </div>

        <!-- Orders count -->
        <div v-if="orders.length" class="text-center small text-muted py-3">
            {{ orders.length }} order{{ orders.length !== 1 ? 's' : '' }} · {{ lastUpdated ? `Updated ${lastUpdated}` : 'Updating…' }}
        </div>
    </div>

    <!-- Mobile Bottom Nav (shown via CSS) -->
    <nav class="dispatch-mobile-bottom-bar" aria-label="Dispatch mobile navigation">
        <div class="dispatch-mobile-bottom-bar-inner">
            <a :href="`${props.baseUrl}dispatch`" class="dispatch-mobile-nav-item is-active">
                <i class="bi bi-kanban" aria-hidden="true"></i>
                <span>Board</span>
            </a>
            <a :href="`${props.baseUrl}dispatch?status=paid`" class="dispatch-mobile-nav-item">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                <span>Pending</span>
            </a>
            <a :href="`${props.baseUrl}dispatch?status=in_transit`" class="dispatch-mobile-nav-item">
                <i class="bi bi-truck" aria-hidden="true"></i>
                <span>Transit</span>
            </a>
            <a :href="`${props.baseUrl}dispatch?status=failed`" class="dispatch-mobile-nav-item">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                <span>Failed</span>
                <span v-if="Number(summary.failed_count) > 0" class="dispatch-mobile-nav-badge">{{ summary.failed_count }}</span>
            </a>
            <a :href="`${props.baseUrl}orders`" class="dispatch-mobile-nav-item">
                <i class="bi bi-box-seam" aria-hidden="true"></i>
                <span>Orders</span>
            </a>
        </div>
    </nav>
</template>

<style scoped>
.dispatch-spin-icon {
    animation: spin .8s linear infinite;
}
@keyframes spin {
    100% { transform: rotate(360deg); }
}
</style>
