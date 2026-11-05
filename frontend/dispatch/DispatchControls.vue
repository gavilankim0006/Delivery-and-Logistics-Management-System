<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    initial: { type: Object, required: true },
    baseUrl: { type: String, required: true },
    csrfToken: { type: String, required: true },
    csrfName: { type: String, required: true }
});

const filters = ref({
    date: props.initial.date || '',
    status: props.initial.status || '',
    search: props.initial.search || ''
});

const summary = ref(props.initial.summary || {});
const pendingRequests = ref(props.initial.pendingRequests || []);
const statusOpen = ref(false);
const holdExpanded = ref(true);
const clearUrl = computed(() => `${props.baseUrl}dispatch`);
const controlsRoot = document.getElementById('dispatch-controls-vue-root');
const clock = ref('');
let clockTimer;

// Clock
function updateClock() {
    clock.value = new Date().toLocaleTimeString([], {
        hour: '2-digit', minute: '2-digit', second: '2-digit'
    });
}

// Greeting
const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
});

const today = computed(() => {
    return new Date().toLocaleDateString([], {
        weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'
    });
});

const updateSummary = (event) => {
    summary.value = { ...summary.value, ...event.detail };
};

// Active filter tags
const activeFilterTags = computed(() => {
    const tags = [];
    if (filters.value.date) {
        const formatted = new Date(filters.value.date + 'T00:00:00').toLocaleDateString([], {
            month: 'short', day: 'numeric', year: 'numeric'
        });
        tags.push({ key: 'date', label: `Date: ${formatted}`, value: filters.value.date });
    }
    if (filters.value.status) {
        tags.push({ key: 'status', label: `Status: ${statusDisplay(filters.value.status)}`, value: filters.value.status });
    }
    if (filters.value.search) {
        tags.push({ key: 'search', label: `Search: "${filters.value.search}"`, value: filters.value.search });
    }
    return tags;
});

function removeFilter(key) {
    filters.value[key] = '';
    if (key === 'search') {
        const searchEl = document.getElementById('dispatch-search');
        if (searchEl) {
            searchEl.value = '';
            searchEl.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    triggerFilterChange();
}

function triggerFilterChange() {
    const searchEl = document.getElementById('dispatch-search');
    if (searchEl) searchEl.dispatchEvent(new Event('input', { bubbles: true }));
    const dateEl = document.getElementById('dispatch-date');
    if (dateEl) dateEl.dispatchEvent(new Event('change', { bubbles: true }));
}

function statusDisplay(value) {
    return String(value || 'all').replaceAll('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
}

onMounted(() => {
    controlsRoot?.addEventListener('dispatch-summary', updateSummary);
    updateClock();
    clockTimer = setInterval(updateClock, 1000);
});

onUnmounted(() => {
    controlsRoot?.removeEventListener('dispatch-summary', updateSummary);
    clearInterval(clockTimer);
});

function number(value) {
    return Number(value || 0).toLocaleString();
}

function orderUrl(id) {
    return `${props.baseUrl}orders/${encodeURIComponent(id)}`;
}

function requestReviewUrl(id) {
    return `${props.baseUrl}dispatch/requests/${encodeURIComponent(id)}/review`;
}

function clearSearch() {
    filters.value.search = '';
    const searchEl = document.getElementById('dispatch-search');
    if (searchEl) {
        searchEl.value = '';
        searchEl.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function kpiActive(key) {
    if (key === 'today') return filters.value.date === props.initial.today;
    if (key === 'awaiting') return filters.value.status === 'paid';
    if (key === 'transit') return filters.value.status === 'in_transit';
    if (key === 'failed') return filters.value.status === 'failed';
    return false;
}
</script>

<template>
    <form :action="clearUrl" method="get" class="dispatch-filter-form" id="dispatch-filter-form">
        <!-- Shift Banner -->
        <div class="dispatch-shift-banner dispatch-fade-in">
            <div class="dispatch-shift-greeting">
                <h2>{{ greeting }}, Dispatcher</h2>
                <p>{{ today }}</p>
            </div>
            <div class="dispatch-shift-clock">
                <i class="bi bi-clock" aria-hidden="true"></i>
                <span>{{ clock }}</span>
            </div>
        </div>

        <!-- KPI Metric Cards -->
        <div class="dispatch-kpi-grid" role="group" aria-label="Dispatch summary metrics">
            <a
                class="dispatch-kpi-card"
                :class="{ 'is-active': kpiActive('today') }"
                :href="`${clearUrl}?date=${encodeURIComponent(initial.today)}`"
                :aria-label="`Today's orders: ${number(summary.today_total)}`"
            >
                <div class="dispatch-kpi-icon dispatch-kpi-icon--today">
                    <i class="bi bi-calendar-check" aria-hidden="true"></i>
                </div>
                <span class="dispatch-kpi-label">Today's Orders</span>
                <span class="dispatch-kpi-value">{{ number(summary.today_total) }}</span>
                <span class="dispatch-kpi-hint">Orders placed today</span>
            </a>
            <a
                class="dispatch-kpi-card dispatch-kpi-card--warning"
                :class="{ 'is-active': kpiActive('awaiting') }"
                :href="`${clearUrl}?status=paid`"
                :aria-label="`Awaiting booking: ${number(summary.awaiting_booking)}`"
            >
                <div class="dispatch-kpi-icon dispatch-kpi-icon--awaiting">
                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                </div>
                <span class="dispatch-kpi-label">Awaiting Booking</span>
                <span class="dispatch-kpi-value">{{ number(summary.awaiting_booking) }}</span>
                <span class="dispatch-kpi-hint">Paid, needs assignment</span>
            </a>
            <a
                class="dispatch-kpi-card"
                :class="{ 'is-active': kpiActive('transit') }"
                :href="`${clearUrl}?status=in_transit`"
                :aria-label="`In transit: ${number(summary.in_transit)}`"
            >
                <div class="dispatch-kpi-icon dispatch-kpi-icon--transit">
                    <i class="bi bi-truck" aria-hidden="true"></i>
                </div>
                <span class="dispatch-kpi-label">In Transit</span>
                <span class="dispatch-kpi-value">{{ number(summary.in_transit) }}</span>
                <span class="dispatch-kpi-hint">Active deliveries</span>
            </a>
            <a
                class="dispatch-kpi-card dispatch-kpi-card--danger"
                :class="{ 'is-active': kpiActive('failed') }"
                :href="`${clearUrl}?status=failed`"
                :aria-label="`Failed: ${number(summary.failed_count)}`"
            >
                <div class="dispatch-kpi-icon dispatch-kpi-icon--failed">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                </div>
                <span class="dispatch-kpi-label">Failed / Exceptions</span>
                <span class="dispatch-kpi-value">{{ number(summary.failed_count) }}</span>
                <span class="dispatch-kpi-hint">Needs attention</span>
            </a>
        </div>

        <!-- Search & Filter Controls -->
        <section class="surface surface-section mb-4" aria-label="Dispatch filters">
            <div class="dispatch-toolbar">
                <div class="dispatch-search-group">
                    <div class="dispatch-search">
                        <label for="dispatch-search" class="form-label">Search orders</label>
                        <div class="dispatch-search-input-group">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input
                                id="dispatch-search"
                                v-model="filters.search"
                                type="text"
                                name="search"
                                class="form-control"
                                :class="{ 'pe-4': Boolean(filters.search) }"
                                placeholder="Order #, customer, or company..."
                                autocomplete="off"
                            >
                            <button
                                v-if="filters.search"
                                type="button"
                                class="dispatch-search-clear position-absolute end-0 top-50 translate-middle-y me-2 border-0 bg-transparent text-muted p-0 d-flex align-items-center justify-content-center"
                                style="width: 24px; height: 24px; cursor: pointer; z-index: 4;"
                                aria-label="Clear search"
                                @click="clearSearch"
                            >
                                <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <button
                        class="btn btn-secondary dispatch-status-toggle"
                        type="button"
                        :aria-expanded="statusOpen ? 'true' : 'false'"
                        aria-controls="dispatch-status-panel"
                        @click="statusOpen = !statusOpen"
                    >
                        <i class="bi bi-sliders" aria-hidden="true"></i>
                        <span>Filters</span>
                    </button>
                </div>
                <div class="dispatch-date-filter">
                    <label for="dispatch-date" class="form-label">Order date</label>
                    <input id="dispatch-date" v-model="filters.date" type="date" name="date" class="form-control">
                </div>
            </div>

            <!-- Active filter tags -->
            <div v-if="activeFilterTags.length" class="dispatch-active-filters">
                <span
                    v-for="tag in activeFilterTags"
                    :key="tag.key"
                    class="dispatch-filter-tag"
                >
                    {{ tag.label }}
                    <button
                        type="button"
                        :aria-label="`Remove ${tag.key} filter`"
                        @click="removeFilter(tag.key)"
                    >
                        <i class="bi bi-x" aria-hidden="true"></i>
                    </button>
                </span>
            </div>

            <div class="dispatch-filter-controls">
                <div class="dispatch-status-panel" id="dispatch-status-panel" v-show="statusOpen">
                    <label for="dispatch-status" class="form-label">Status</label>
                    <select id="dispatch-status" v-model="filters.status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="pending_payment">Pending payment</option>
                        <option value="paid">Paid</option>
                        <option value="booking">Booking</option>
                        <option value="rider_assigned">Rider assigned</option>
                        <option value="picked_up">Picked up</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div class="dispatch-filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel" aria-hidden="true"></i>
                        Apply filters
                    </button>
                    <a v-if="filters.status || filters.date || filters.search" :href="clearUrl" class="btn btn-secondary">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                        Clear
                    </a>
                </div>
            </div>
        </section>
    </form>

    <!-- Hold Requests Panel (Enhanced) -->
    <section v-if="pendingRequests.length" class="dispatch-hold-requests dispatch-fade-in" aria-labelledby="customer-requests-heading">
        <div
            class="dispatch-hold-header"
            role="button"
            tabindex="0"
            :aria-expanded="holdExpanded ? 'true' : 'false'"
            aria-controls="hold-request-body"
            @click="holdExpanded = !holdExpanded"
            @keydown.enter.prevent="holdExpanded = !holdExpanded"
            @keydown.space.prevent="holdExpanded = !holdExpanded"
        >
            <h2 id="customer-requests-heading">
                <i class="bi bi-pause-circle text-warning" aria-hidden="true"></i>
                Hold Requests
                <span class="dispatch-hold-count">{{ pendingRequests.length }}</span>
            </h2>
            <button
                type="button"
                class="dispatch-hold-toggle"
                :aria-expanded="holdExpanded ? 'true' : 'false'"
                tabindex="-1"
            >
                <span>{{ holdExpanded ? 'Collapse' : 'Expand' }}</span>
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </button>
        </div>
        <div id="hold-request-body" v-show="holdExpanded" class="dispatch-hold-body" role="region" aria-labelledby="customer-requests-heading">
            <p class="small text-muted px-4 pt-3 mb-0">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                Accepting a hold request records the dispatch decision only; it does not pause a courier automatically.
            </p>
            <div
                v-for="request in pendingRequests"
                :key="request.id"
                class="dispatch-hold-request-card"
            >
                <a :href="orderUrl(request.order_id)" class="dispatch-hold-order">
                    <i class="bi bi-box-seam me-1" aria-hidden="true"></i>
                    {{ request.order_no }}
                </a>
                <div class="dispatch-hold-detail">
                    <p v-if="request.request_text" class="dispatch-hold-text">{{ request.request_text }}</p>
                    <p v-else class="dispatch-hold-text text-muted">No note provided</p>
                    <p class="dispatch-hold-time">
                        <i class="bi bi-clock me-1" aria-hidden="true"></i>
                        {{ request.created_at }}
                    </p>
                </div>
                <div class="dispatch-hold-actions">
                    <form method="post" :action="requestReviewUrl(request.id)">
                        <input type="hidden" :name="csrfName" :value="csrfToken">
                        <input type="hidden" name="decision" value="accepted">
                        <button type="submit" class="btn btn-sm btn-outline-success" title="Accept this hold request">
                            <i class="bi bi-check-lg" aria-hidden="true"></i>
                            <span class="d-none d-sm-inline">Accept</span>
                        </button>
                    </form>
                    <form method="post" :action="requestReviewUrl(request.id)">
                        <input type="hidden" :name="csrfName" :value="csrfToken">
                        <input type="hidden" name="decision" value="rejected">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject this hold request">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                            <span class="d-none d-sm-inline">Reject</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</template>
