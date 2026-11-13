<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    metrics: { type: Object, default: () => ({}) },
    recentOrders: { type: Array, default: () => [] },
    recentLogs: { type: Array, default: () => [] },
    links: { type: Object, default: () => ({}) },
    dataUrl: { type: String, default: '' }
});

const REFRESH_INTERVAL_MS = 30000;

const metrics = ref({ ...props.metrics });
const recentOrders = ref([...props.recentOrders]);
const recentLogs = ref([...props.recentLogs]);
const refreshing = ref(false);
const error = ref('');
const lastUpdated = ref('');

let refreshTimer = null;
let activeRequest = null;
let stopped = false;

const number = (value) => new Intl.NumberFormat().format(Number(value || 0));
const money = (value) => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(Number(value || 0));

const STATUS_LABELS = {
    draft: 'Draft',
    pending_payment: 'Pending payment',
    paid: 'Paid',
    booking: 'Booking',
    rider_assigned: 'Rider assigned',
    picked_up: 'Picked up',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
    failed: 'Failed'
};

function statusKey(value) {
    const key = String(value || '');
    return Object.prototype.hasOwnProperty.call(STATUS_LABELS, key) ? key : 'draft';
}

const statusClass = (value) => `ds-status-chip ds-status-chip--${statusKey(value)}`;
const statusLabel = (value) => STATUS_LABELS[statusKey(value)];
const riderLabel = (order) => String(order.rider_name || order.courier_driver_name || '');

function parseDate(value) {
    if (!value) return null;
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? null : date;
}

function timeAgo(value) {
    const date = parseDate(value);
    if (!date) return '—';
    const seconds = Math.round((Date.now() - date.getTime()) / 1000);
    if (seconds < 60) return 'Just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(date);
}

function formatDateTime(value) {
    const date = parseDate(value);
    return date ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date) : '';
}

function endpointLabel(value) {
    const raw = String(value || 'API request');
    try {
        const url = new URL(raw, window.location.origin);
        return url.origin === window.location.origin ? url.pathname : `${url.hostname}${url.pathname}`;
    } catch {
        return raw;
    }
}

function httpTone(status) {
    const code = Number(status || 0);
    if (code >= 200 && code < 300) return 'audit-status--success';
    if (code >= 300 && code < 500) return 'audit-status--warning';
    return 'audit-status--failure';
}

const kpis = computed(() => {
    const failed = Number(metrics.value.failedPayments || 0);
    const pending = Number(metrics.value.pendingApplications || 0);
    return [
        {
            key: 'orders-today',
            label: 'Orders today',
            icon: 'bi-box-seam',
            value: number(metrics.value.ordersToday),
            hint: 'Created since midnight',
            attention: false,
            money: false
        },
        {
            key: 'month-revenue',
            label: 'Paid revenue',
            icon: 'bi-cash-stack',
            value: money(metrics.value.monthRevenue),
            hint: 'Received month to date',
            attention: false,
            money: true
        },
        {
            key: 'failed-payments',
            label: 'Failed payments',
            icon: 'bi-exclamation-octagon',
            value: number(failed),
            hint: failed > 0 ? 'Needs review this month' : 'None this month',
            attention: failed > 0,
            money: false
        },
        {
            key: 'pending-applications',
            label: 'Open applications',
            icon: 'bi-person-badge',
            value: number(pending),
            hint: pending > 0 ? 'Awaiting a decision' : 'All caught up',
            attention: pending > 0,
            money: false
        }
    ];
});

const attentionItems = computed(() => {
    const items = [];
    const failed = Number(metrics.value.failedPayments || 0);
    const pending = Number(metrics.value.pendingApplications || 0);
    if (failed > 0) {
        items.push({
            key: 'failed-payments',
            icon: 'bi-exclamation-octagon',
            text: `${number(failed)} failed payment${failed === 1 ? '' : 's'} recorded this month.`,
            action: 'Review payments',
            href: props.links.failedPayments || props.links.reports
        });
    }
    if (pending > 0) {
        items.push({
            key: 'pending-applications',
            icon: 'bi-person-badge',
            text: `${number(pending)} job application${pending === 1 ? '' : 's'} waiting for a decision.`,
            action: 'Open applications',
            href: props.links.applications
        });
    }
    return items;
});

const quickLinks = computed(() => [
    { href: props.links.dispatch, icon: 'bi-kanban', label: 'Dispatch board', description: 'Assign riders and move deliveries.' },
    { href: props.links.orders, icon: 'bi-box-seam', label: 'Orders', description: 'Browse and filter every delivery.' },
    { href: props.links.users, icon: 'bi-people', label: 'Users', description: 'Manage staff and customer accounts.' },
    { href: props.links.riders, icon: 'bi-bicycle', label: 'Riders', description: 'Review the rider fleet and status.' },
    { href: props.links.applications, icon: 'bi-person-badge', label: 'Applications', description: 'Decide on rider and dispatcher applicants.' },
    { href: props.links.analytics, icon: 'bi-graph-up', label: 'Analytics', description: 'Delivery trends and outcomes.' },
    { href: props.links.reports, icon: 'bi-file-earmark-text', label: 'Reports', description: 'Delivery and payment summaries.' },
    { href: props.links.logs, icon: 'bi-journal-code', label: 'System logs', description: 'Recent external API requests.' }
].filter((link) => Boolean(link.href)));

const liveStatusLabel = computed(() => {
    if (error.value) return 'Updates paused';
    if (refreshing.value) return 'Refreshing…';
    return `Updated ${lastUpdated.value}`;
});

function scheduleRefresh() {
    window.clearTimeout(refreshTimer);
    if (stopped || !props.dataUrl) return;
    refreshTimer = window.setTimeout(() => refreshDashboard(), REFRESH_INTERVAL_MS);
}

async function refreshDashboard(options = {}) {
    if (stopped || !props.dataUrl || activeRequest) return;
    const controller = new AbortController();
    activeRequest = controller;
    refreshing.value = true;
    try {
        const response = await fetch(props.dataUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: controller.signal
        });
        if (!response.ok) throw new Error(`Request failed with status ${response.status}.`);
        const payload = await response.json();
        if (!payload.success || !payload.data) throw new Error('Dashboard data was unavailable.');
        metrics.value = {
            ordersToday: Number(payload.data.ordersToday || 0),
            monthRevenue: Number(payload.data.monthRevenue || 0),
            failedPayments: Number(payload.data.failedPayments || 0),
            pendingApplications: Number(payload.data.pendingApplications || 0)
        };
        recentOrders.value = Array.isArray(payload.data.recentOrders) ? payload.data.recentOrders : [];
        recentLogs.value = Array.isArray(payload.data.recentLogs) ? payload.data.recentLogs : [];
        error.value = '';
        lastUpdated.value = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    } catch (requestError) {
        if (requestError.name !== 'AbortError') {
            error.value = options.manual
                ? 'The dashboard could not be refreshed. Check your connection and try again.'
                : 'Live updates are paused; showing the last known figures.';
        }
    } finally {
        if (activeRequest === controller) activeRequest = null;
        refreshing.value = false;
        scheduleRefresh();
    }
}

function handleVisibilityChange() {
    if (stopped || document.hidden) return;
    window.clearTimeout(refreshTimer);
    refreshDashboard();
}

onMounted(() => {
    lastUpdated.value = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    document.addEventListener('visibilitychange', handleVisibilityChange);
    scheduleRefresh();
});

onUnmounted(() => {
    stopped = true;
    window.clearTimeout(refreshTimer);
    activeRequest?.abort();
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
    <div class="container page-shell py-4 admin-dashboard">
        <header class="page-header admin-dash__header">
            <div>
                <p class="ds-eyebrow">Administration</p>
                <h1 class="page-title">Admin dashboard</h1>
                <p class="page-description">Today’s order volume, month-to-date payments and the latest platform activity at a glance.</p>
            </div>
            <div class="admin-dash__actions">
                <p class="admin-dash__live" :class="{ 'is-error': error, 'is-loading': refreshing }" title="Refreshes automatically every 30 seconds">
                    <span class="admin-dash__live-dot" aria-hidden="true"></span>
                    <span>{{ liveStatusLabel }}</span>
                </p>
                <button
                    type="button"
                    class="btn btn-secondary"
                    :disabled="refreshing"
                    :aria-busy="refreshing ? 'true' : 'false'"
                    @click="refreshDashboard({ manual: true })"
                >
                    <i class="bi" :class="refreshing ? 'bi-arrow-repeat admin-dash__spin' : 'bi-arrow-clockwise'" aria-hidden="true"></i>
                    Refresh
                </button>
                <a :href="links.dispatch" class="btn btn-primary">
                    <i class="bi bi-kanban" aria-hidden="true"></i>
                    Open dispatch board
                </a>
            </div>
        </header>

        <div v-if="error" class="ds-notice ds-notice--warning admin-dash__notice" role="alert">
            <i class="bi bi-wifi-off" aria-hidden="true"></i>
            <span class="admin-dash__notice-text">{{ error }}</span>
            <button type="button" class="btn btn-sm btn-secondary" @click="refreshDashboard({ manual: true })">Try again</button>
        </div>

        <section v-if="attentionItems.length" class="ds-notice ds-notice--warning admin-dash__attention" aria-labelledby="admin-attention-heading">
            <span class="admin-dash__attention-icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
            <div class="admin-dash__attention-body">
                <h2 id="admin-attention-heading" class="admin-dash__attention-title">Needs attention</h2>
                <ul class="admin-dash__attention-list">
                    <li v-for="item in attentionItems" :key="item.key">
                        <i class="bi" :class="item.icon" aria-hidden="true"></i>
                        <span>{{ item.text }}</span>
                        <a :href="item.href">{{ item.action }}</a>
                    </li>
                </ul>
            </div>
        </section>

        <section class="ds-stat-grid admin-dash__kpis" aria-label="Key operating metrics">
            <article v-for="kpi in kpis" :key="kpi.key" class="surface ds-metric-card admin-dash__kpi" :class="{ 'is-attention': kpi.attention }">
                <span class="ds-metric-card__label"><i class="bi" :class="kpi.icon" aria-hidden="true"></i>{{ kpi.label }}</span>
                <span class="ds-metric-card__value" :class="{ 'is-money': kpi.money }">{{ kpi.value }}</span>
                <span class="ds-metric-card__hint">{{ kpi.hint }}</span>
            </article>
        </section>

        <div class="row g-3 g-xl-4 admin-dash__panels">
            <div class="col-12 col-xl-7 d-flex">
                <section class="surface admin-dash__panel" aria-labelledby="recent-orders-heading">
                    <div class="ds-section-header">
                        <div>
                            <h2 id="recent-orders-heading" class="ds-section-title">Recent orders</h2>
                            <p class="ds-section-hint">The five latest deliveries across all customers.</p>
                        </div>
                        <a :href="links.orders" class="btn btn-sm btn-secondary">View all orders</a>
                    </div>
                    <div v-if="recentOrders.length" class="table-responsive admin-dash__table-wrap">
                        <table class="table table-clean table-responsive-stack align-middle mb-0">
                            <caption class="visually-hidden">Five most recent orders</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Order</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Rider</th>
                                    <th scope="col" class="text-end">Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="order in recentOrders" :key="order.id">
                                    <td data-label="Order"><a class="admin-dash__order-link" :href="`${links.orderPrefix}${order.id}`">{{ order.order_no }}</a></td>
                                    <td data-label="Customer">{{ order.customer_name || '—' }}</td>
                                    <td data-label="Status"><span :class="statusClass(order.status)">{{ statusLabel(order.status) }}</span></td>
                                    <td data-label="Rider"><span :class="{ 'admin-dash__muted': !riderLabel(order) }">{{ riderLabel(order) || 'Unassigned' }}</span></td>
                                    <td data-label="Created" class="text-end text-nowrap"><span :title="formatDateTime(order.created_at)">{{ timeAgo(order.created_at) }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="admin-dash__empty" role="status">
                        <i class="bi bi-inbox" aria-hidden="true"></i>
                        <p>No orders yet. New deliveries appear here as customers check out.</p>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-5 d-flex">
                <section class="surface admin-dash__panel" aria-labelledby="recent-logs-heading">
                    <div class="ds-section-header">
                        <div>
                            <h2 id="recent-logs-heading" class="ds-section-title">API activity</h2>
                            <p class="ds-section-hint">Latest requests recorded by the API client.</p>
                        </div>
                        <a :href="links.logs" class="btn btn-sm btn-secondary">All logs</a>
                    </div>
                    <ul v-if="recentLogs.length" class="ds-list admin-dash__log-list">
                        <li v-for="log in recentLogs" :key="log.id" class="ds-list__item admin-dash__log">
                            <div class="admin-dash__log-main">
                                <span class="admin-dash__log-method">{{ (log.method || 'GET').toUpperCase() }}</span>
                                <span class="admin-dash__log-endpoint" :title="log.endpoint || ''">{{ endpointLabel(log.endpoint) }}</span>
                            </div>
                            <div class="admin-dash__log-meta">
                                <span class="audit-status" :class="httpTone(log.response_status)">{{ log.response_status || 'Error' }}</span>
                                <span class="admin-dash__log-time">
                                    <span v-if="log.service" class="admin-dash__log-service" :title="log.service">{{ log.service }}</span>
                                    <span :title="formatDateTime(log.created_at)">{{ timeAgo(log.created_at) }}</span>
                                </span>
                            </div>
                        </li>
                    </ul>
                    <div v-else class="admin-dash__empty" role="status">
                        <i class="bi bi-journal-check" aria-hidden="true"></i>
                        <p>No API requests recorded yet.</p>
                    </div>
                </section>
            </div>
        </div>

        <section class="admin-dash__shortcuts" aria-labelledby="admin-shortcuts-heading">
            <div class="ds-section-header">
                <div>
                    <h2 id="admin-shortcuts-heading" class="ds-section-title">Jump back in</h2>
                    <p class="ds-section-hint">Common administration areas.</p>
                </div>
            </div>
            <div class="admin-dash__shortcut-grid">
                <a v-for="link in quickLinks" :key="link.href" class="surface card--interactive admin-dash__shortcut" :href="link.href">
                    <span class="admin-dash__shortcut-icon"><i class="bi" :class="link.icon" aria-hidden="true"></i></span>
                    <span class="admin-dash__shortcut-body">
                        <span class="admin-dash__shortcut-title">{{ link.label }}</span>
                        <span class="admin-dash__shortcut-desc">{{ link.description }}</span>
                    </span>
                    <i class="bi bi-arrow-right admin-dash__shortcut-arrow" aria-hidden="true"></i>
                </a>
            </div>
        </section>
    </div>
</template>
