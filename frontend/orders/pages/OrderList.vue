<script setup>
import { computed, ref } from 'vue';
import TextInput from '../../shared/TextInput.vue';
import SelectDropdown from '../../shared/SelectDropdown.vue';

const props = defineProps({
    title: { type: String, default: 'Orders' },
    role: { type: String, default: 'customer' },
    orders: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    totalOrders: { type: Number, default: 0 },
    page: { type: Number, default: 1 },
    totalPages: { type: Number, default: 1 },
    baseUrl: { type: String, default: '/' },
    today: { type: String, default: '' },
    flashes: { type: Array, default: () => [] }
});

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

const stages = ['placed', 'paid', 'rider_assigned', 'picked_up', 'delivered'];
const stageByStatus = {
    draft: 'placed',
    pending_payment: 'placed',
    paid: 'paid',
    booking: 'paid',
    rider_assigned: 'rider_assigned',
    picked_up: 'picked_up',
    delivered: 'delivered'
};

const timelineStages = {
    placed: 'placed',
    pending_payment: 'placed',
    paid: 'paid',
    booking: 'paid',
    rider_assigned: 'rider_assigned',
    picked_up: 'picked_up',
    delivered: 'delivered'
};

const statusFilter = ref(props.filters.status || '');
const dateFilter = ref(props.filters.date || '');
const searchFilter = ref(props.filters.search || '');
const showMobileFilters = ref(false);

const activeFilters = Object.fromEntries(
    Object.entries(props.filters).filter(([, value]) => value !== '' && value != null)
);

function applyQuickFilter(status) {
    const params = new URLSearchParams(activeFilters);
    if (status) {
        params.set('status', status);
    } else {
        params.delete('status');
    }
    params.delete('page');
    window.location.href = `${props.baseUrl}orders?${params.toString()}`;
}

function pageUrl(number) {
    const params = new URLSearchParams(activeFilters);
    params.set('page', number);
    return `${props.baseUrl}orders?${params.toString()}`;
}

// Keep the page strip compact: first, last, and a window around the current page.
const pageNumbers = computed(() => {
    const total = props.totalPages;
    const current = props.page;
    if (total <= 7) {
        return Array.from({ length: total }, (_, index) => index + 1);
    }
    const wanted = [...new Set([1, total, current - 1, current, current + 1])]
        .filter((n) => n >= 1 && n <= total)
        .sort((a, b) => a - b);
    const out = [];
    let previous = 0;
    for (const n of wanted) {
        if (previous && n - previous > 1) out.push('…');
        out.push(n);
        previous = n;
    }
    return out;
});

function progress(order) {
    let index = Math.max(0, stages.indexOf(stageByStatus[order.status] || 'placed'));
    for (const item of order.timeline || []) {
        const stage = timelineStages[item.to_status];
        if (stage) index = Math.max(index, stages.indexOf(stage));
    }
    return index;
}

function date(value) {
    const parsed = new Date(value.replace(' ', 'T'));
    return Number.isNaN(parsed.getTime())
        ? value
        : parsed.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

function money(value) {
    return `₱${Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })}`;
}
</script>

<template>
    <div class="container py-4 page-shell">
        <div v-for="(flash, index) in flashes" :key="index" class="alert alert-dismissible fade show mb-3" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
            <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
            {{ flash.message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <header class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <p class="ds-eyebrow">Operations</p>
                <h1 class="page-title mb-1">{{ title }}</h1>
                <p class="text-muted mb-0 page-description">
                    Review delivery details and follow each order's progress.
                </p>
            </div>
            <a v-if="role === 'customer'" class="btn btn-primary" :href="`${baseUrl}orders/new`">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> New order
            </a>
        </header>

        <!-- Customer Mobile Quick Status Filter Pills -->
        <div v-if="role === 'customer'" class="customer-mobile-filters d-md-none">
            <div class="customer-filter-pills-scroll" role="tablist" aria-label="Filter orders by status">
                <button
                    type="button"
                    class="customer-filter-pill"
                    :class="{ 'is-active': !statusFilter }"
                    @click="applyQuickFilter('')"
                >
                    All
                </button>
                <button
                    type="button"
                    class="customer-filter-pill"
                    :class="{ 'is-active': statusFilter === 'draft' }"
                    @click="applyQuickFilter('draft')"
                >
                    Draft
                </button>
                <button
                    type="button"
                    class="customer-filter-pill"
                    :class="{ 'is-active': statusFilter === 'pending_payment' }"
                    @click="applyQuickFilter('pending_payment')"
                >
                    Pending payment
                </button>
                <button
                    type="button"
                    class="customer-filter-pill"
                    :class="{ 'is-active': statusFilter === 'paid' }"
                    @click="applyQuickFilter('paid')"
                >
                    Paid
                </button>
                <button
                    type="button"
                    class="customer-filter-pill"
                    :class="{ 'is-active': statusFilter === 'rider_assigned' }"
                    @click="applyQuickFilter('rider_assigned')"
                >
                    In transit
                </button>
                <button
                    type="button"
                    class="customer-filter-pill"
                    :class="{ 'is-active': statusFilter === 'delivered' }"
                    @click="applyQuickFilter('delivered')"
                >
                    Delivered
                </button>
            </div>

            <button
                type="button"
                class="customer-filter-toggle-btn"
                :aria-expanded="showMobileFilters ? 'true' : 'false'"
                @click="showMobileFilters = !showMobileFilters"
            >
                <span>
                    <i class="bi bi-funnel me-1" aria-hidden="true"></i>
                    {{ showMobileFilters ? 'Hide search & filters' : 'Search & more filters' }}
                    <span v-if="dateFilter || searchFilter" class="badge bg-primary ms-1">Active</span>
                </span>
                <i :class="showMobileFilters ? 'bi bi-chevron-up' : 'bi bi-chevron-down'" aria-hidden="true"></i>
            </button>
        </div>

        <!-- Filter Form -->
        <form
            class="row g-3 align-items-end mb-4"
            :class="{ 'd-none d-md-flex': role === 'customer' && !showMobileFilters }"
            method="get"
            :action="`${baseUrl}orders`"
        >
            <div class="col-12 col-md-3">
                <SelectDropdown
                    id="order-status"
                    name="status"
                    v-model="statusFilter"
                    label="Status"
                    :options="statuses"
                    placeholder="All statuses"
                    :allow-empty="true"
                    :placeholder-disabled="false"
                />
            </div>

            <div class="col-12 col-md-3">
                <TextInput
                    id="order-date"
                    name="date"
                    v-model="dateFilter"
                    type="date"
                    label="Date"
                    :max="today"
                />
            </div>

            <div class="col-12 col-md-4">
                <TextInput
                    id="order-search"
                    name="search"
                    v-model="searchFilter"
                    type="search"
                    label="Search"
                    placeholder="Search by order number or recipient"
                    :clearable="true"
                    :debounce="300"
                    maxlength="100"
                />
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-outline-primary flex-grow-1" type="submit">Filter</button>
                <a
                    v-if="Object.keys(activeFilters).length"
                    class="btn btn-outline-secondary"
                    :href="`${baseUrl}orders`"
                >
                    Clear
                </a>
            </div>
        </form>

        <section v-if="orders.length === 0" class="empty-state surface p-4" aria-labelledby="orders-empty-title" aria-live="polite">
            <h2 id="orders-empty-title" class="h5">
                {{ Object.keys(activeFilters).length ? 'No matching orders' : 'No orders yet' }}
            </h2>
            <p class="mb-0">
                {{
                    Object.keys(activeFilters).length
                        ? 'Try changing the filters to find the delivery you need.'
                        : 'Create your first delivery to get started.'
                }}
            </p>
            <a
                v-if="role === 'customer' && !Object.keys(activeFilters).length"
                class="btn btn-primary mt-3"
                :href="`${baseUrl}orders/new`"
            >
                Create an order
            </a>
        </section>

        <template v-else>
            <!-- Mobile Adaptive Order Cards (Phone View) -->
            <div class="d-md-none">
                <article
                    v-for="order in orders"
                    :key="`mobile-${order.id}`"
                    class="surface p-3 mb-3 rounded-3 border"
                >
                    <div class="order-card-header">
                        <div>
                            <a class="order-card-title fw-bold" :href="`${baseUrl}orders/${order.id}`">
                                {{ order.order_no }}
                            </a>
                            <div class="order-card-date">{{ date(order.created_at) }}</div>
                        </div>
                        <span class="status-chip" :class="`status-chip--${order.status}`">
                            {{ statuses.find(([key]) => key === order.status)?.[1] || order.status }}
                        </span>
                    </div>

                    <div class="order-card-route">
                        <div class="order-card-route-stop">
                            <span class="order-card-dot order-card-dot--pickup" aria-hidden="true"></span>
                            <span class="order-card-route-text small">
                                <strong>Pickup:</strong> {{ order.pickup_address }}
                            </span>
                        </div>
                        <div class="order-card-route-stop">
                            <span class="order-card-dot order-card-dot--dropoff" aria-hidden="true"></span>
                            <span class="order-card-route-text small">
                                <strong>Drop-off:</strong> {{ order.dropoff_address }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="role === 'customer' && !['cancelled', 'failed'].includes(order.status)"
                        class="compact-route-progress my-2"
                        role="img"
                        :aria-label="`Delivery progress: ${statuses.find(([key]) => key === order.status)?.[1] || 'Unknown'}`"
                    >
                        <span class="compact-route-progress__track" aria-hidden="true">
                            <span
                                class="compact-route-progress__fill"
                                :style="{ width: `${(progress(order) / (stages.length - 1)) * 100}%` }"
                            ></span>
                        </span>
                        <span class="compact-route-progress__label small text-muted">
                            {{ Number(order.distance_km || 0).toFixed(2) }} km · {{ stages[progress(order)].replace('_', ' ') }}
                        </span>
                    </div>

                    <div class="order-card-footer">
                        <div>
                            <span class="text-muted small d-block">Delivery fee</span>
                            <span class="order-card-fee">{{ money(order.delivery_fee) }}</span>
                        </div>
                        <div class="order-card-actions">
                            <a
                                v-if="role === 'customer' && ['draft', 'pending_payment'].includes(order.status)"
                                class="btn btn-primary"
                                :href="`${baseUrl}orders/${order.id}`"
                            >
                                Pay now
                            </a>
                            <a
                                v-else
                                class="btn btn-outline-secondary"
                                :href="`${baseUrl}orders/${order.id}`"
                            >
                                Details
                            </a>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Desktop / Tablet Table View -->
            <div class="table-responsive surface d-none d-md-block">
                <table class="table align-middle mb-0 table-responsive-stack table-clean">
                    <thead>
                        <tr>
                            <th scope="col">Order</th>
                            <th v-if="role !== 'customer'" scope="col">Customer</th>
                            <th scope="col">Route</th>
                            <th scope="col">Status</th>
                            <th scope="col">Fee</th>
                            <th scope="col"><span class="visually-hidden">Details</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="order in orders" :key="order.id">
                            <td data-label="Order">
                                <a class="fw-semibold text-decoration-none" :href="`${baseUrl}orders/${order.id}`">
                                    {{ order.order_no }}
                                </a>
                                <div class="small text-muted">{{ date(order.created_at) }}</div>
                            </td>
                            <td v-if="role !== 'customer'" data-label="Customer">{{ order.customer_name }}</td>
                            <td data-label="Route" class="route-cell">
                                <span class="route-cell__address small">
                                    <strong>From:</strong> {{ order.pickup_address }}
                                </span>
                                <span class="route-cell__address small">
                                    <strong>To:</strong> {{ order.dropoff_address }}
                                </span>
                                <span class="small text-muted">
                                    {{ Number(order.distance_km || 0).toFixed(2) }} km
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="status-chip" :class="`status-chip--${order.status}`">
                                    {{ statuses.find(([key]) => key === order.status)?.[1] || order.status }}
                                </span>
                                <div
                                    v-if="role === 'customer' && !['cancelled', 'failed'].includes(order.status)"
                                    class="compact-route-progress mt-2"
                                    role="img"
                                    :aria-label="`Delivery progress: ${statuses.find(([key]) => key === order.status)?.[1] || 'Unknown'}`"
                                >
                                    <span class="compact-route-progress__track" aria-hidden="true">
                                        <span
                                            class="compact-route-progress__fill"
                                            :class="`compact-route-progress__fill--${progress(order)}`"
                                        ></span>
                                    </span>
                                </div>
                                <p v-else-if="role === 'customer'" class="small text-muted mt-2 mb-0">
                                    Progress stopped · {{ order.status }}
                                </p>
                            </td>
                            <td data-label="Fee" class="text-nowrap money">{{ money(order.delivery_fee) }}</td>
                            <td data-label="Details">
                                <a class="btn btn-sm btn-outline-secondary" :href="`${baseUrl}orders/${order.id}`" :aria-label="`Track order ${order.order_no}`">
                                    Track order
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="totalPages > 1" class="mt-3" aria-label="Order pages">
                <ul class="pagination justify-content-end">
                    <li class="page-item" :class="{ disabled: page <= 1 }">
                        <a class="page-link" :href="pageUrl(Math.max(1, page - 1))" aria-label="Previous page">
                            « Previous
                        </a>
                    </li>
                    <li
                        v-for="(number, index) in pageNumbers"
                        :key="`${number}-${index}`"
                        class="page-item"
                        :class="{ active: number === page, disabled: number === '…' }"
                    >
                        <span v-if="number === '…'" class="page-link" aria-hidden="true">…</span>
                        <a
                            v-else
                            class="page-link"
                            :href="pageUrl(number)"
                            :aria-current="number === page ? 'page' : undefined"
                        >
                            {{ number }}
                        </a>
                    </li>
                    <li class="page-item" :class="{ disabled: page >= totalPages }">
                        <a class="page-link" :href="pageUrl(Math.min(totalPages, page + 1))" aria-label="Next page">
                            Next »
                        </a>
                    </li>
                </ul>
            </nav>
            <p class="small text-muted text-end">Showing {{ orders.length }} of {{ totalOrders }} orders</p>
        </template>
    </div>
</template>
