<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    initial: { type: Object, required: true },
    baseUrl: { type: String, required: true },
    csrfToken: { type: String, required: true },
    csrfName: { type: String, required: true }
});
const filters = ref({ date: props.initial.date || '', status: props.initial.status || '', search: props.initial.search || '' });
const summary = ref(props.initial.summary || {});
const pendingRequests = ref(props.initial.pendingRequests || []);
const statusOpen = ref(false);
const clearUrl = computed(() => `${props.baseUrl}dispatch`);
const controlsRoot = document.getElementById('dispatch-controls-vue-root');
const updateSummary = (event) => { summary.value = { ...summary.value, ...event.detail }; };
onMounted(() => controlsRoot?.addEventListener('dispatch-summary', updateSummary));
onUnmounted(() => controlsRoot?.removeEventListener('dispatch-summary', updateSummary));

function number(value) {
    return Number(value || 0).toLocaleString();
}

function orderUrl(id) {
    return `${props.baseUrl}orders/${encodeURIComponent(id)}`;
}

function requestReviewUrl(id) {
    return `${props.baseUrl}dispatch/requests/${encodeURIComponent(id)}/review`;
}
</script>

<template>
    <form :action="clearUrl" method="get" class="dispatch-filter-form" id="dispatch-filter-form">
        <header class="page-header dispatch-page-header mb-4">
            <div><h1 class="page-title">Live Dispatch Operations</h1><p class="page-description">Review delivery status, routes, and rider assignments.</p></div>
        </header>
        <section class="surface surface-section mb-4" aria-label="Dispatch filters and order summary">
            <div class="dispatch-toolbar">
                <div class="dispatch-search-group">
                    <div class="dispatch-search"><label for="dispatch-search" class="form-label">Search orders</label><input id="dispatch-search" v-model="filters.search" type="search" name="search" class="form-control" placeholder="Order number, customer, or company"></div>
                    <button class="btn btn-secondary dispatch-status-toggle" type="button" :aria-expanded="statusOpen" aria-controls="dispatch-status-panel" @click="statusOpen = !statusOpen"><i class="bi bi-sliders" aria-hidden="true"></i><span>Filters</span></button>
                </div>
                <nav class="ds-segmented dispatch-summary" aria-label="Dispatch summary filters">
                    <a class="ds-segment" :class="{ 'is-active': filters.date === initial.today }" :href="`${clearUrl}?date=${encodeURIComponent(initial.today)}`" aria-label="Today's orders: {{ number(summary.today_total) }}"><span class="ds-segment__label">Today's Orders</span><span class="ds-segment__count">{{ number(summary.today_total) }}</span></a>
                    <a class="ds-segment" :class="{ 'is-active': filters.status === 'paid' }" :href="`${clearUrl}?status=paid`" aria-label="Awaiting booking: {{ number(summary.awaiting_booking) }}"><span class="ds-segment__label">Awaiting Booking</span><span class="ds-segment__count">{{ number(summary.awaiting_booking) }}</span></a>
                    <a class="ds-segment" :class="{ 'is-active': filters.status === 'in_transit' }" :href="`${clearUrl}?status=in_transit`" :aria-label="`In transit orders: ${number(summary.in_transit)}`"><span class="ds-segment__label">In Transit</span><span class="ds-segment__count">{{ number(summary.in_transit) }}</span></a>
                    <a class="ds-segment" :class="{ 'is-active': filters.status === 'failed' }" :href="`${clearUrl}?status=failed`" aria-label="Failed or exception orders: {{ number(summary.failed_count) }}"><span class="ds-segment__label">Failed / Exceptions</span><span class="ds-segment__count">{{ number(summary.failed_count) }}</span></a>
                </nav>
                <div class="dispatch-date-filter"><label for="dispatch-date" class="form-label">Order date</label><input id="dispatch-date" v-model="filters.date" type="date" name="date" class="form-control"></div>
            </div>
            <div class="dispatch-filter-controls">
                <div class="dispatch-status-panel" id="dispatch-status-panel" v-show="statusOpen"><label for="dispatch-status" class="form-label">Status</label><select id="dispatch-status" v-model="filters.status" name="status" class="form-select"><option value="">All statuses</option><option value="draft">Draft</option><option value="pending_payment">Pending payment</option><option value="paid">Paid</option><option value="booking">Booking</option><option value="rider_assigned">Rider assigned</option><option value="picked_up">Picked up</option><option value="delivered">Delivered</option><option value="cancelled">Cancelled</option><option value="failed">Failed</option></select></div>
                <div class="dispatch-filter-actions"><button type="submit" class="btn btn-primary">Apply filters</button><a v-if="filters.status || filters.date || filters.search" :href="clearUrl" class="btn btn-secondary">Clear</a></div>
            </div>
        </section>
    </form>
    <section v-if="pendingRequests.length" class="surface p-3 p-md-4 mb-4" aria-labelledby="customer-requests-heading">
        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center"><h2 class="h5 mb-0" id="customer-requests-heading">Hold requests awaiting review</h2><span class="badge text-bg-warning">{{ pendingRequests.length }} pending</span></div>
        <p class="small text-muted mt-2 mb-0">Accepting a hold request records the dispatch decision only; it does not pause a courier automatically.</p>
        <div class="table-responsive mt-3"><table class="table table-clean align-middle mb-0"><thead><tr><th>Order</th><th>Request</th><th>Submitted</th><th>Review</th></tr></thead><tbody>
            <tr v-for="request in pendingRequests" :key="request.id"><td><a :href="orderUrl(request.order_id)">{{ request.order_no }}</a></td><td><div v-if="request.request_text" class="small text-muted">{{ request.request_text }}</div><span v-else>No note provided</span></td><td>{{ request.created_at }}</td><td><div class="d-flex gap-2"><form method="post" :action="requestReviewUrl(request.id)"><input type="hidden" :name="csrfName" :value="csrfToken"><input type="hidden" name="decision" value="accepted"><button type="submit" class="btn btn-sm btn-outline-success">Mark accepted</button></form><form method="post" :action="requestReviewUrl(request.id)"><input type="hidden" :name="csrfName" :value="csrfToken"><input type="hidden" name="decision" value="rejected"><button type="submit" class="btn btn-sm btn-outline-danger">Reject</button></form></div></td></tr>
        </tbody></table></div>
    </section>
</template>
