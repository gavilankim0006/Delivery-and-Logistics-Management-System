<script setup>
const props = defineProps({
    title: { type: String, default: 'Orders' },
    role: { type: String, default: 'customer' },
    orders: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    totalOrders: { type: Number, default: 0 },
    page: { type: Number, default: 1 },
    totalPages: { type: Number, default: 1 },
    baseUrl: { type: String, default: '/' },
    today: { type: String, default: '' }
});
const statuses = [
    ['draft', 'Draft'], ['pending_payment', 'Pending payment'], ['paid', 'Paid'], ['booking', 'Booking'],
    ['rider_assigned', 'Rider assigned'], ['picked_up', 'Picked up'], ['delivered', 'Delivered'], ['cancelled', 'Cancelled'], ['failed', 'Failed']
];
const stages = ['placed', 'paid', 'rider_assigned', 'picked_up', 'delivered'];
const stageByStatus = { draft: 'placed', pending_payment: 'placed', paid: 'paid', booking: 'paid', rider_assigned: 'rider_assigned', picked_up: 'picked_up', delivered: 'delivered' };
const timelineStages = { placed: 'placed', pending_payment: 'placed', paid: 'paid', booking: 'paid', rider_assigned: 'rider_assigned', picked_up: 'picked_up', delivered: 'delivered' };
const activeFilters = Object.fromEntries(Object.entries(props.filters).filter(([, value]) => value !== '' && value != null));
function pageUrl(number) {
    const params = new URLSearchParams(activeFilters);
    params.set('page', number);
    return `${props.baseUrl}orders?${params.toString()}`;
}
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
    return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}
function money(value) { return `₱${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`; }
</script>

<template>
    <div class="container py-4 page-shell">
        <header class="page-header">
            <div><h1 class="page-title">{{ title }}</h1><p class="text-muted mb-0 page-description">Review delivery details and follow each order's progress.</p></div>
            <a v-if="role === 'customer'" class="btn btn-primary" :href="`${baseUrl}orders/new`">New order</a>
        </header>
        <form class="row g-3 align-items-end mb-4" method="get" :action="`${baseUrl}orders`">
            <div class="col-12 col-md-3"><label class="form-label" for="order-status">Status</label><select class="form-select" id="order-status" name="status"><option value="">All statuses</option><option v-for="[value, label] in statuses" :key="value" :value="value" :selected="filters.status === value">{{ label }}</option></select></div>
            <div class="col-12 col-md-3"><label class="form-label" for="order-date">Date</label><input class="form-control" id="order-date" type="date" name="date" :max="today" :value="filters.date || ''"></div>
            <div class="col-12 col-md-4"><label class="form-label" for="order-search">Search</label><input class="form-control" id="order-search" type="search" name="search" maxlength="100" :value="filters.search || ''" placeholder="Order number, customer, or address"></div>
            <div class="col-12 col-md-2 d-flex gap-2"><button class="btn btn-outline-primary flex-grow-1" type="submit">Filter</button><a v-if="Object.keys(activeFilters).length" class="btn btn-outline-secondary" :href="`${baseUrl}orders`">Clear</a></div>
        </form>
        <section v-if="orders.length === 0" class="empty-state surface p-4" aria-live="polite"><h2 class="h5">{{ Object.keys(activeFilters).length ? 'No matching orders' : 'No orders yet' }}</h2><p class="mb-0">{{ Object.keys(activeFilters).length ? 'Try changing the filters to find the delivery you need.' : 'Create your first delivery to get started.' }}</p><a v-if="role === 'customer' && !Object.keys(activeFilters).length" class="btn btn-primary mt-3" :href="`${baseUrl}orders/new`">Create an order</a></section>
        <template v-else>
            <div class="table-responsive surface"><table class="table align-middle mb-0 table-responsive-stack table-clean"><thead><tr><th scope="col">Order</th><th v-if="role !== 'customer'" scope="col">Customer</th><th scope="col">Route</th><th scope="col">Status</th><th scope="col">Fee</th><th scope="col"><span class="visually-hidden">Details</span></th></tr></thead>
                <tbody><tr v-for="order in orders" :key="order.id"><td data-label="Order"><a class="fw-semibold text-decoration-none" :href="`${baseUrl}orders/${order.id}`">{{ order.order_no }}</a><div class="small text-muted">{{ date(order.created_at) }}</div></td>
                    <td v-if="role !== 'customer'" data-label="Customer">{{ order.customer_name }}</td><td data-label="Route" class="route-cell"><span class="route-cell__address small"><strong>From:</strong> {{ order.pickup_address }}</span><span class="route-cell__address small"><strong>To:</strong> {{ order.dropoff_address }}</span><span class="small text-muted">{{ Number(order.distance_km || 0).toFixed(2) }} km</span></td>
                    <td data-label="Status"><span class="status-chip" :class="`status-chip--${order.status}`">{{ statuses.find(([key]) => key === order.status)?.[1] || order.status }}</span><div v-if="role === 'customer' && !['cancelled', 'failed'].includes(order.status)" class="compact-route-progress mt-2" role="img" :aria-label="`Delivery progress: ${statuses.find(([key]) => key === order.status)?.[1] || 'Unknown'}`"><span class="compact-route-progress__track" aria-hidden="true"><span class="compact-route-progress__fill" :class="`compact-route-progress__fill--${progress(order)}`"></span></span></div><p v-else-if="role === 'customer'" class="small text-muted mt-2 mb-0">Progress stopped · {{ order.status }}</p></td>
                    <td data-label="Fee" class="text-nowrap money">{{ money(order.delivery_fee) }}</td><td data-label="Details"><a class="btn btn-sm btn-outline-secondary" :href="`${baseUrl}orders/${order.id}`">View</a></td></tr></tbody>
            </table></div>
            <nav v-if="totalPages > 1" class="mt-3" aria-label="Order pages"><ul class="pagination justify-content-end"><li class="page-item" :class="{ disabled: page <= 1 }"><a class="page-link" :href="pageUrl(Math.max(1, page - 1))" aria-label="Previous page">« Previous</a></li><li v-for="number in totalPages" :key="number" class="page-item" :class="{ active: number === page }"><a class="page-link" :href="pageUrl(number)" :aria-current="number === page ? 'page' : undefined">{{ number }}</a></li><li class="page-item" :class="{ disabled: page >= totalPages }"><a class="page-link" :href="pageUrl(Math.min(totalPages, page + 1))" aria-label="Next page">Next »</a></li></ul></nav>
            <p class="small text-muted text-end">Showing {{ orders.length }} of {{ totalOrders }} orders</p>
        </template>
    </div>
</template>
