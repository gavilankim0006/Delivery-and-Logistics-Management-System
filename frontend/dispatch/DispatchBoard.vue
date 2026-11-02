<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

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
const error = ref('');
const lastUpdated = ref('');
const search = ref(document.getElementById('dispatch-search')?.value || '');
const filterForm = document.getElementById('dispatch-filter-form');
let refreshTimer;
let searchTimer;
let activeRequest;

async function loadBoard() {
    activeRequest?.abort();
    const controller = new AbortController();
    activeRequest = controller;
    const params = new URLSearchParams(new FormData(filterForm));

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

        orders.value = result.data.orders;
        if (Array.isArray(result.data.available_riders)) {
            availableRiders.value = result.data.available_riders;
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
        if (!controller.signal.aborted) loading.value = false;
    }
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
    return ['delayed', 'failed'].includes(priority) ? 'text-bg-warning' : 'text-bg-info';
}

function onFilterSubmit(event) {
    event.preventDefault();
    const params = new URLSearchParams(new FormData(filterForm));
    window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
    loadBoard();
}

onMounted(() => {
    filterForm?.addEventListener('submit', onFilterSubmit);
    document.getElementById('dispatch-search')?.addEventListener('input', onSearch);
    document.getElementById('dispatch-date')?.addEventListener('change', loadBoard);
    document.getElementById('dispatch-status')?.addEventListener('change', loadBoard);
    loadBoard();
    refreshTimer = setInterval(loadBoard, 30000);
});

onUnmounted(() => {
    filterForm?.removeEventListener('submit', onFilterSubmit);
    document.getElementById('dispatch-search')?.removeEventListener('input', onSearch);
    document.getElementById('dispatch-date')?.removeEventListener('change', loadBoard);
    document.getElementById('dispatch-status')?.removeEventListener('change', loadBoard);
    clearInterval(refreshTimer);
    clearTimeout(searchTimer);
    activeRequest?.abort();
});
</script>

<template>
    <div class="table-responsive dispatch-table-scroll">
        <table class="table table-clean table-responsive-stack dispatch-orders-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Order</th>
                    <th scope="col">Customer</th>
                    <th scope="col">Pickup to drop-off</th>
                    <th scope="col">Status</th>
                    <th scope="col">Rider</th>
                    <th scope="col">Fee</th>
                    <th scope="col" class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="error">
                    <td colspan="7" class="text-center py-4 text-danger" role="alert">
                        {{ error }}
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" @click="loadBoard">Retry</button>
                    </td>
                </tr>
                <tr v-else-if="loading">
                    <td colspan="7" class="text-center py-4 text-muted" role="status">Loading dispatch orders…</td>
                </tr>
                <tr v-else-if="orders.length === 0">
                    <td colspan="7" class="text-center py-4 text-muted">No orders match these filters.</td>
                </tr>
                <tr v-for="order in orders" :key="order.id">
                    <td data-label="Order" class="fw-semibold text-nowrap">
                        {{ order.order_no }}
                        <div class="text-muted small">{{ formatDate(order.created_at) }}</div>
                    </td>
                    <td data-label="Customer">
                        <div class="fw-medium">{{ order.customer_name }}</div>
                        <div v-if="order.company_name" class="text-muted small">{{ order.company_name }}</div>
                    </td>
                    <td data-label="Pickup to drop-off" class="route-summary route-cell align-middle">
                        <div class="route-summary__address"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><span class="visually-hidden">Pickup: </span>{{ order.pickup_address }}</div>
                        <div class="route-summary__address"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><span class="visually-hidden">Drop-off: </span>{{ order.dropoff_address }}</div>
                        <span class="text-muted small">{{ order.distance_km }} km · about {{ order.estimated_duration_min }} min</span>
                    </td>
                    <td data-label="Status">
                        <span class="status-chip" :class="`status-chip--${statusClass(order.status)}`">{{ statusLabel(order.status) }}</span>
                        <span class="dispatch-status-extra small text-muted">
                            <span class="dispatch-status-rider">{{ order.courier_driver_name || order.rider_name || 'Not assigned' }}</span>
                            <span class="dispatch-status-fee">₱{{ money(order.delivery_fee) }}</span>
                        </span>
                    </td>
                    <td data-label="Rider">
                        <template v-if="order.courier_driver_name || order.rider_name">
                            <div class="fw-medium">{{ order.courier_driver_name || order.rider_name }}</div>
                            <div class="text-muted small">
                                {{ order.courier_driver_vehicle || order.plate_number }}
                                <template v-if="order.courier_driver_phone || order.rider_phone"> · {{ order.courier_driver_phone || order.rider_phone }}</template>
                            </div>
                            <a v-if="trackingUrl(order.share_link)" :href="trackingUrl(order.share_link)" target="_blank" rel="noopener noreferrer" class="d-inline-block mt-1">
                                Live tracking <span class="visually-hidden">(opens in a new tab)</span><i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i>
                            </a>
                        </template>
                        <span v-else class="text-muted text-nowrap">Not assigned</span>
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
                                <button type="submit" class="btn btn-sm btn-outline-primary lalamove-action">Book Lalamove</button>
                            </form>
                        </div>
                        <template v-if="order.recovery">
                            <button type="button" class="dispatch-fulfillment-hint small text-muted" tabindex="0" :title="`${order.recovery.message} (${formatStateAge(order.recovery.age_minutes)})`" :aria-label="`${order.recovery.message} (${formatStateAge(order.recovery.age_minutes)})`"><span class="visually-hidden">{{ statusLabel(order.recovery.priority) }}: </span><i class="bi bi-info-circle" aria-hidden="true"></i><span class="visually-hidden">Fulfillment details</span></button>
                            <form v-if="['book', 'retry'].includes(order.recovery.kind)" method="post" :action="recoveryUrl(order.id)" class="mb-2">
                                <input type="hidden" name="csrf_token" :value="csrfToken">
                                <button type="submit" class="btn btn-sm btn-primary">{{ order.recovery.label }}</button>
                            </form>
                            <a v-else-if="order.recovery.kind === 'escalate' && order.pickup_phone" class="btn btn-sm btn-outline-primary mb-2 ms-2" :href="phoneUrl(order.pickup_phone)">Call customer</a>
                        </template>
                        <a :href="orderUrl(order.id)" class="dispatch-row-action">View order</a>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="dispatch-live-note small text-muted px-3 py-2" role="status">
            {{ loading ? 'Refreshing…' : `Last updated ${lastUpdated}` }}
            <span v-if="search" class="visually-hidden">Search: {{ search }}</span>
        </div>
    </div>
</template>
