<script setup>
import { computed } from 'vue';

const props = defineProps({
    userName: { type: String, default: '' },
    stats: { type: Object, default: () => ({}) },
    activeOrders: { type: Array, default: () => [] },
    recentOrders: { type: Array, default: () => [] },
    baseUrl: { type: String, default: '/' },
    csrfToken: { type: String, default: '' },
    flashes: { type: Array, default: () => [] }
});

const statusLabels = {
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

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
});

const firstName = computed(() => (props.userName || '').trim().split(/\s+/)[0] || 'there');

const statCards = computed(() => [
    {
        key: 'active',
        label: 'Active deliveries',
        value: Number(props.stats.active_total || 0),
        icon: 'bi-box-seam',
        hint: 'Paid and on the way',
        href: `${props.baseUrl}orders`
    },
    {
        key: 'transit',
        label: 'In transit now',
        value: Number(props.stats.in_transit || 0),
        icon: 'bi-truck',
        hint: 'Assigned or picked up',
        href: `${props.baseUrl}orders?status=rider_assigned`
    },
    {
        key: 'delivered',
        label: 'Delivered',
        value: Number(props.stats.delivered_total || 0),
        icon: 'bi-check2-circle',
        hint: 'Completed all time',
        href: `${props.baseUrl}orders?status=delivered`
    }
]);

const monthSpend = computed(() => Number(props.stats.spend_month || 0));
const draftCount = computed(() => Number(props.stats.drafts || 0));

function progress(order) {
    let index = Math.max(0, stages.indexOf(stageByStatus[order.status] || 'placed'));
    for (const item of order.timeline || []) {
        const stage = timelineStages[item.to_status];
        if (stage) index = Math.max(index, stages.indexOf(stage));
    }
    return index;
}

function progressLabel(order) {
    const label = stages[progress(order)] || 'placed';
    return label.replace('_', ' ');
}

function statusLabel(order) {
    return statusLabels[order.status] || order.status;
}

function date(value) {
    if (!value) return '';
    const parsed = new Date(String(value).replace(' ', 'T'));
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
    <div class="container py-4 ds-page page-shell">
        <div v-for="(flash, index) in flashes" :key="index" class="alert alert-dismissible fade show mb-3" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
            <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
            {{ flash.message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <header class="ds-page-header page-header mb-4">
            <div>
                <p class="ds-eyebrow">{{ greeting }}</p>
                <h1 class="page-title mb-1">Hi {{ firstName }}</h1>
                <p class="text-muted mb-0 page-description">
                    Here's what's happening with your deliveries right now.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary" :href="`${baseUrl}orders/new`">
                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>New delivery
                </a>
                <a class="btn btn-outline-primary" :href="`${baseUrl}package-workflow`">
                    <i class="bi bi-chat-dots me-1" aria-hidden="true"></i>Messages
                </a>
                <a class="btn btn-outline-secondary" :href="`${baseUrl}addresses`">
                    <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Saved addresses
                </a>
            </div>
        </header>

        <section class="row g-3 mb-4" aria-label="Delivery statistics">
            <div v-for="card in statCards" :key="card.key" class="col-12 col-sm-6 col-lg-3">
                <a class="surface ds-stat-card p-3 h-100 d-block text-decoration-none" :href="card.href">
                    <span class="ds-stat-card__icon" aria-hidden="true"><i class="bi" :class="card.icon"></i></span>
                    <span class="ds-stat-card__value">{{ card.value }}</span>
                    <span class="ds-stat-card__label">{{ card.label }}</span>
                    <span class="ds-stat-card__hint">{{ card.hint }}</span>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="surface ds-stat-card p-3 h-100">
                    <span class="ds-stat-card__icon" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
                    <span class="ds-stat-card__value">{{ money(monthSpend) }}</span>
                    <span class="ds-stat-card__label">Spent this month</span>
                    <span class="ds-stat-card__hint">{{ Number(stats.payments_month || 0) }} payment(s) recorded</span>
                </div>
            </div>
        </section>

        <div v-if="draftCount" class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2" role="status">
            <span>
                <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>
                You have {{ draftCount }} draft {{ draftCount === 1 ? 'order' : 'orders' }} awaiting payment.
            </span>
            <a class="btn btn-sm btn-outline-primary" :href="`${baseUrl}orders?status=draft`">Review drafts</a>
        </div>

        <section class="mb-4" aria-labelledby="active-deliveries-heading">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h5 mb-0" id="active-deliveries-heading">Active deliveries</h2>
                <a class="small text-decoration-none" :href="`${baseUrl}orders`">View all orders</a>
            </div>

            <div v-if="activeOrders.length === 0" class="empty-state surface p-4">
                <h3 class="h6 mb-1">No active deliveries</h3>
                <p class="text-muted mb-3">Everything is delivered. Ready for the next one?</p>
                <a class="btn btn-primary" :href="`${baseUrl}orders/new`">Create an order</a>
            </div>

            <div v-else class="row g-3">
                <div v-for="order in activeOrders" :key="order.id" class="col-12 col-lg-4">
                    <article class="surface p-3 h-100 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <a class="fw-bold text-decoration-none" :href="`${baseUrl}orders/${order.id}`">{{ order.order_no }}</a>
                            <span class="status-chip" :class="`status-chip--${order.status}`">{{ statusLabel(order) }}</span>
                        </div>
                        <div class="d-flex flex-column gap-2 mb-3 small">
                            <span class="d-flex gap-2">
                                <span class="order-card-dot order-card-dot--pickup mt-1" aria-hidden="true"></span>
                                <span><strong>From:</strong> {{ order.pickup_address }}</span>
                            </span>
                            <span class="d-flex gap-2">
                                <span class="order-card-dot order-card-dot--dropoff mt-1" aria-hidden="true"></span>
                                <span><strong>To:</strong> {{ order.dropoff_address }}</span>
                            </span>
                        </div>
                        <div class="compact-route-progress mb-3" role="img" :aria-label="`Delivery progress: ${progressLabel(order)}`">
                            <span class="compact-route-progress__track" aria-hidden="true">
                                <span
                                    class="compact-route-progress__fill"
                                    :class="`compact-route-progress__fill--${progress(order)}`"
                                    :style="{ width: `${(progress(order) / (stages.length - 1)) * 100}%` }"
                                ></span>
                            </span>
                            <span class="compact-route-progress__label small text-muted text-capitalize">{{ progressLabel(order) }}</span>
                        </div>
                        <div class="mt-auto d-flex justify-content-between align-items-center gap-2">
                            <span class="fw-semibold">{{ money(order.delivery_fee) }}</span>
                            <a class="btn btn-sm btn-outline-primary" :href="`${baseUrl}orders/${order.id}`">Track delivery</a>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section aria-labelledby="recent-orders-heading">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h5 mb-0" id="recent-orders-heading">Recent orders</h2>
                <a class="small text-decoration-none" :href="`${baseUrl}orders`">See full history</a>
            </div>

            <div v-if="recentOrders.length === 0" class="empty-state surface p-4">
                <p class="mb-0 text-muted">Your order history will appear here after your first delivery.</p>
            </div>

            <ul v-else class="surface list-unstyled mb-0 ds-recent-orders">
                <li v-for="order in recentOrders" :key="order.id" class="ds-recent-orders__item">
                    <div class="ds-recent-orders__main">
                        <a class="fw-semibold text-decoration-none" :href="`${baseUrl}orders/${order.id}`">{{ order.order_no }}</a>
                        <span class="small text-muted">{{ date(order.created_at) }}</span>
                    </div>
                    <div class="ds-recent-orders__route small text-muted text-truncate">
                        {{ order.pickup_address }} → {{ order.dropoff_address }}
                    </div>
                    <div class="ds-recent-orders__meta">
                        <span class="status-chip" :class="`status-chip--${order.status}`">{{ statusLabel(order) }}</span>
                        <span class="fw-semibold text-nowrap">{{ money(order.delivery_fee) }}</span>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>

<style scoped>
.ds-stat-card {
    display: grid;
    grid-template-columns: auto 1fr;
    grid-template-rows: auto auto auto;
    column-gap: var(--s-3, 12px);
    row-gap: 2px;
    color: inherit;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.ds-stat-card:hover,
.ds-stat-card:focus {
    transform: translateY(-2px);
    box-shadow: var(--shadow-pop, 0 8px 24px rgba(20, 33, 61, 0.12));
    text-decoration: none;
}

.ds-stat-card__icon {
    grid-row: 1 / span 3;
    align-self: center;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: var(--route, #087F98);
    background: color-mix(in srgb, var(--route, #087F98) 12%, transparent);
}

.ds-stat-card__value {
    font-size: 1.45rem;
    font-weight: 800;
    line-height: 1.15;
    color: var(--ink, #172B3A);
    font-variant-numeric: tabular-nums;
}

.ds-stat-card__label {
    font-weight: 700;
    font-size: var(--font-size-14, 14px);
    color: var(--ink, #172B3A);
}

.ds-stat-card__hint {
    font-size: var(--font-size-12, 12px);
    color: var(--ink-3, #617687);
}

.ds-recent-orders__item {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(0, 2fr) auto;
    align-items: center;
    gap: var(--s-3, 12px);
    padding: var(--s-3, 12px) var(--s-4, 16px);
    border-bottom: 1px solid var(--paper, #EEF2F5);
}

.ds-recent-orders__item:last-child {
    border-bottom: 0;
}

.ds-recent-orders__main {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.ds-recent-orders__meta {
    display: flex;
    align-items: center;
    gap: var(--s-3, 12px);
}

@media (max-width: 767.98px) {
    .ds-recent-orders__item {
        grid-template-columns: 1fr;
        gap: 6px;
    }

    .ds-recent-orders__meta {
        justify-content: space-between;
    }
}
</style>
