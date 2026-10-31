<script setup>
const props = defineProps({ orders: { type: Array, default: () => [] }, portalLinked: { type: Boolean, default: false }, baseUrl: { type: String, default: '/' } });
</script>

<template>
    <div class="container page-shell py-4 ds-page">
        <header class="page-header mb-4"><div><h1 class="page-title">My deliveries</h1><p class="page-description">Your active internal assignments, in pickup order.</p></div></header>
        <section v-if="!portalLinked" class="surface p-4" role="status"><h2 class="h5">Rider account not linked</h2><p class="mb-0">Your account is active, but it is not linked to a rider profile yet. Contact an administrator to finish setup.</p></section>
        <section v-else-if="!orders.length" class="surface p-4" role="status"><h2 class="h5">No active deliveries</h2><p class="mb-0">New assignments from dispatch will appear here.</p></section>
        <section v-else class="surface" aria-label="Active delivery assignments"><div class="table-responsive"><table class="table table-clean table-responsive-stack align-middle mb-0">
            <thead><tr><th scope="col">Order</th><th scope="col">Pickup</th><th scope="col">Drop-off</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Action</span></th></tr></thead>
            <tbody><tr v-for="order in orders" :key="order.order_id"><td data-label="Order"><a :href="`${baseUrl}rider/orders/${order.order_id}`">{{ order.order_no }}</a></td><td data-label="Pickup">{{ order.pickup_address }}</td><td data-label="Drop-off">{{ order.dropoff_address }}</td><td data-label="Status"><span class="status-chip">{{ order.status === 'picked_up' ? 'Picked up' : 'Awaiting pickup' }}</span></td><td data-label="Action"><a class="btn btn-sm btn-outline-primary" :href="`${baseUrl}rider/orders/${order.order_id}`">Open delivery</a></td></tr></tbody>
        </table></div></section>
    </div>
</template>
