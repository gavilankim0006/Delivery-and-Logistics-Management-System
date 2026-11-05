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

<style>
.admin-dashboard {
    --admin-ink: var(--ink, #172b3a);
    --admin-muted: var(--ink-2, #627485);
    --admin-line: var(--line, #dce4e9);
    padding-block: var(--s-5, 2rem) !important;
}

.admin-dashboard .page-header {
    align-items: center;
    margin-bottom: clamp(1.5rem, 3vw, 2.25rem) !important;
}

.admin-dashboard .page-title {
    font-size: var(--font-size-page-title, 1.75rem);
    letter-spacing: -0.025em;
}

.admin-dashboard .page-description { max-width: 58ch; }
.admin-dashboard .dashboard-metrics .row,
.admin-dashboard > .row { --bs-gutter-x: 1rem; --bs-gutter-y: 1rem; }

.admin-dashboard .ds-metric {
    display: flex;
    min-height: 142px;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: .45rem;
    padding: var(--s-4, 1.5rem);
    border: 1px solid var(--admin-line);
    border-radius: var(--r-md, 10px);
    background: var(--surface, #fff);
    box-shadow: var(--shadow-card, 0 2px 8px rgb(23 43 58 / 6%));
}

.admin-dashboard .ds-metric__label {
    color: var(--admin-muted);
    font-size: .875rem;
    font-weight: 600;
}

.admin-dashboard .ds-metric__value {
    color: var(--admin-ink);
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: -.04em;
    line-height: 1.12;
}

.admin-dashboard .ds-metric__value.money { font-size: clamp(1.55rem, 2.6vw, 2rem); }
.admin-dashboard .ds-metric__hint { color: var(--admin-muted); font-size: .8rem; }
.admin-dashboard > .row > [class*="col-"] { min-width: 0; }

.admin-dashboard .surface-section {
    min-width: 0;
    padding: clamp(1rem, 2vw, 1.4rem);
    border-radius: var(--r-md, 12px);
}

.admin-dashboard .surface-section h2 {
    color: var(--admin-ink);
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: -.015em;
}

.admin-dashboard .table-responsive { margin-inline: -.2rem; }
.admin-dashboard .table { --bs-table-bg: transparent; }
.admin-dashboard .table thead th {
    padding-block: .7rem;
    border-bottom-color: var(--admin-line);
    color: var(--admin-muted);
    font-size: .75rem;
    font-weight: 650;
    white-space: nowrap;
}

.admin-dashboard .table tbody td {
    padding-block: .85rem;
    border-bottom-color: color-mix(in srgb, var(--admin-line) 72%, transparent);
    color: var(--admin-ink);
    font-size: .875rem;
}

.admin-dashboard .table tbody tr:last-child td { border-bottom: 0; }
.admin-dashboard .table tbody td:first-child a { font-weight: 650; text-decoration-thickness: 1px; text-underline-offset: 3px; }
.admin-dashboard .list-group-item { border-color: color-mix(in srgb, var(--admin-line) 72%, transparent); }
.admin-dashboard .list-group-item:last-child { border-bottom: 0; }
.admin-dashboard .admin-log-meta { display: flex; flex-wrap: wrap; gap: .35rem .65rem; color: var(--admin-muted); font-size: .8rem; }
.admin-dashboard .admin-log-status { font-variant-numeric: tabular-nums; font-weight: 650; }

@media (max-width: 575.98px) {
    .admin-dashboard .page-header { align-items: flex-start; }
    .admin-dashboard .page-header > .btn { width: 100%; justify-content: center; }
    .admin-dashboard .ds-metric { min-height: 122px; padding: var(--s-3, 1rem); }
    .admin-dashboard .surface-section { padding: 1rem; }
}

@media (prefers-reduced-motion: no-preference) {
    .admin-dashboard .ds-metric { transition: border-color .18s ease, box-shadow .18s ease; }
    .admin-dashboard .ds-metric:hover { border-color: color-mix(in srgb, var(--admin-line) 45%, var(--admin-ink)); box-shadow: var(--shadow-card-hover, 0 10px 24px rgb(23 43 58 / 10%)); }
}

@media (prefers-reduced-motion: reduce) {
    .admin-dashboard .ds-metric { transition: none; }
}
</style>

<template>
    <div class="container page-shell py-4 admin-dashboard">
        <header class="page-header mb-4">
            <div>
                <h1 class="page-title">Admin dashboard</h1>
                <p class="page-description">Today’s order volume, month-to-date payments, and service activity.</p>
            </div>
            <a :href="links.dispatch" class="btn btn-secondary">View dispatch</a>
        </header>

        <section class="dashboard-metrics mb-4" aria-labelledby="admin-metrics-heading">
            <h2 id="admin-metrics-heading" class="visually-hidden">Key operating metrics</h2>
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
