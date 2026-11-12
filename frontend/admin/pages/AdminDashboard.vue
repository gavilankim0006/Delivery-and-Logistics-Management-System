<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    metrics: { type: Object, default: () => ({}) },
    recentOrders: { type: Array, default: () => [] },
    recentLogs: { type: Array, default: () => [] },
    links: { type: Object, default: () => ({}) },
    dataUrl: { type: String, default: '' }
});

const metrics = ref({ ...props.metrics });
const recentOrders = ref([...props.recentOrders]);
const recentLogs = ref([...props.recentLogs]);
let refreshTimer = null;
let refreshing = false;
let stopped = false;

async function refreshDashboard() {
    if (stopped || refreshing || document.hidden || !props.dataUrl) return;
    refreshing = true;
    try {
        const response = await fetch(props.dataUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store'
        });
        if (response.ok) {
            const payload = await response.json();
            if (payload.success && payload.data) {
                metrics.value = {
                    ordersToday: Number(payload.data.ordersToday || 0),
                    monthRevenue: Number(payload.data.monthRevenue || 0),
                    failedPayments: Number(payload.data.failedPayments || 0)
                };
                recentOrders.value = Array.isArray(payload.data.recentOrders) ? payload.data.recentOrders : [];
                recentLogs.value = Array.isArray(payload.data.recentLogs) ? payload.data.recentLogs : [];
            }
        }
    } catch {
        // Keep the last known data; the next interval will retry.
    } finally {
        refreshing = false;
    }
    if (!stopped) refreshTimer = window.setTimeout(refreshDashboard, 30000);
}

function handleVisibilityChange() {
    if (stopped || document.hidden) return;
    window.clearTimeout(refreshTimer);
    refreshDashboard();
}

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    refreshTimer = window.setTimeout(refreshDashboard, 30000);
});

onUnmounted(() => {
    stopped = true;
    window.clearTimeout(refreshTimer);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});

const number = (value) => new Intl.NumberFormat().format(Number(value || 0));
const money = (value) => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'PHP' }).format(Number(value || 0));
const statusLabel = (value) => String(value || '').replaceAll('_', ' ');
const endpointLabel = (value) => {
    const raw = String(value || 'API request');
    try {
        const url = new URL(raw, window.location.origin);
        return `${url.hostname}${url.pathname}`;
    } catch {
        return raw;
    }
};
</script>

<style>
.admin-dashboard {
    --admin-ink: var(--ink, #172b3a);
    --admin-muted: var(--ink-2, #627485);
    --admin-line: var(--line, #dce4e9);
    padding-block: clamp(1.5rem, 3vw, 2.5rem) !important;
}

.admin-dashboard .page-shell {
    width: min(100% - 2rem, 1180px);
}

.admin-dashboard .page-header {
    padding-bottom: 1.25rem;
    border-bottom: 1px solid var(--admin-line);
}

.admin-dashboard .page-description {
    margin-top: .4rem;
    color: var(--admin-muted);
    font-size: .95rem;
    line-height: 1.55;
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
    min-height: 132px;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: .4rem;
    padding: 1.25rem 1.4rem;
    border: 1px solid var(--admin-line);
    border-radius: var(--r-md, 10px);
    background: var(--surface, #fff);
    box-shadow: 0 1px 2px rgb(23 43 58 / 4%);
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
    overflow: hidden;
    border: 1px solid var(--admin-line);
    border-radius: var(--r-md, 12px);
    background: var(--surface, #fff);
    box-shadow: var(--shadow-card, 0 2px 8px rgb(23 43 58 / 6%));
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
.admin-dashboard .admin-log-endpoint {
    display: block;
    overflow-wrap: anywhere;
    color: var(--admin-ink);
    font-family: var(--font-family, sans-serif);
    font-size: .84rem;
    font-weight: 600;
    line-height: 1.45;
}

.admin-dashboard .table tbody td { vertical-align: middle; }
.admin-dashboard .table tbody tr:hover { background: color-mix(in srgb, var(--route) 4%, transparent); }
.admin-dashboard :is(a, button):focus-visible {
    outline: 3px solid var(--focus-ring);
    outline-offset: 3px;
}

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
                <p class="ds-eyebrow">Administration</p>
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
                                    <td><span class="badge text-bg-light text-capitalize">{{ statusLabel(order.status) }}</span></td>
                                    <td>{{ order.rider_name || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-muted mb-0" role="status">No recent orders.</p>
                </section>
            </div>
            <div class="col-12 col-xl-5">
                <section class="surface surface-section h-100" aria-labelledby="recent-logs-heading">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 id="recent-logs-heading" class="h5 mb-0">Latest API logs</h2>
                        <a :href="links.logs" class="btn btn-sm btn-secondary">View logs</a>
                    </div>
                    <div v-if="recentLogs.length" class="list-group list-group-flush min-w-0 overflow-hidden">
                        <div v-for="log in recentLogs" :key="log.id" class="list-group-item px-0 min-w-0 overflow-hidden">
                            <div class="d-flex flex-column gap-1">
                                <strong class="admin-log-endpoint">{{ endpointLabel(log.endpoint || log.url) }}</strong>
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
