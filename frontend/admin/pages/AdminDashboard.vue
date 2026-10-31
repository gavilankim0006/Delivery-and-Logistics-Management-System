<script setup>
const props = defineProps({
    metrics: { type: Object, default: () => ({}) },
    recentOrders: { type: Array, default: () => [] },
    recentLogs: { type: Array, default: () => [] },
    links: { type: Object, default: () => ({}) }
});
const number = (value) => new Intl.NumberFormat().format(Number(value || 0));
const money = (value) => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'PHP' }).format(Number(value || 0));
</script>

<template>
    <div class="container page-shell py-4">
        <header class="page-header mb-4">
            <div>
                <h1 class="page-title">Admin dashboard</h1>
                <p class="page-description">Today’s order volume, month-to-date payments, and service activity.</p>
            </div>
            <a :href="links.dispatch" class="btn btn-secondary">View dispatch</a>
        </header>

        <section class="dashboard-metrics mb-4" aria-label="Key operating metrics">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="ds-metric h-100">
                        <span class="ds-metric__label">Orders today</span>
                        <p class="ds-metric__value mb-0">{{ number(metrics.ordersToday) }}</p>
                        <span class="ds-metric__hint">Created since midnight</span>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="ds-metric h-100">
                        <span class="ds-metric__label">Paid revenue this month</span>
                        <p class="ds-metric__value money mb-0">{{ money(metrics.monthRevenue) }}</p>
                        <span class="ds-metric__hint">Payments received month to date</span>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="ds-metric h-100">
                        <span class="ds-metric__label">Failed payments</span>
                        <p class="ds-metric__value mb-0">{{ number(metrics.failedPayments) }}</p>
                        <span class="ds-metric__hint">This month</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-12 col-xl-7">
                <section class="surface surface-section h-100" aria-labelledby="recent-orders-heading">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 id="recent-orders-heading" class="h5 mb-0">Recent orders</h2>
                        <a :href="links.orders" class="btn btn-sm btn-secondary">View all</a>
                    </div>
                    <div v-if="recentOrders.length" class="table-responsive">
                        <table class="table table-clean align-middle mb-0">
                            <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Status</th><th scope="col">Rider</th></tr></thead>
                            <tbody>
                                <tr v-for="order in recentOrders" :key="order.id">
                                    <td><a :href="`${links.orderPrefix}${order.id}`">{{ order.order_no }}</a></td>
                                    <td>{{ order.customer_name }}</td>
                                    <td><span class="badge text-bg-light">{{ order.status }}</span></td>
                                    <td>{{ order.rider_name || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-muted mb-0" role="status">No recent orders.</p>
                </section>
            </div>
            <div class="col-12 col-xl-5">
                <section class="surface surface-section h-100" style="min-width:0; overflow:hidden" aria-labelledby="recent-logs-heading">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 id="recent-logs-heading" class="h5 mb-0">Latest API logs</h2>
                        <a :href="links.logs" class="btn btn-sm btn-secondary">View logs</a>
                    </div>
                    <div v-if="recentLogs.length" class="list-group list-group-flush" style="min-width:0; overflow:hidden">
                        <div v-for="log in recentLogs" :key="log.id" class="list-group-item px-0" style="min-width:0; overflow:hidden">
                            <div class="d-flex flex-column gap-1">
                                <strong class="text-break" style="overflow-wrap:anywhere">{{ log.endpoint || log.url || 'API request' }}</strong>
                                <span class="small text-muted">{{ log.created_at }}</span>
                            </div>
                            <div class="small text-muted">{{ log.method || '—' }} · {{ log.response_status || '—' }}</div>
                        </div>
                    </div>
                    <p v-else class="text-muted mb-0" role="status">No recent API activity.</p>
                </section>
            </div>
        </div>
    </div>
</template>
