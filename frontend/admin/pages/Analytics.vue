<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    daily: { type: Array, default: () => [] },
    outcomes: { type: Array, default: () => [] },
    links: { type: Object, default: () => ({}) },
    error: { type: String, default: '' }
});
const from = ref(props.filters.from || '');
const to = ref(props.filters.to || '');
const money = (value) => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(Number(value || 0));
const number = (value) => new Intl.NumberFormat().format(Number(value || 0));
const chart = computed(() => {
    const rows = props.daily || [];
    const width = 760;
    const height = 220;
    const pad = { top: 18, right: 12, bottom: 30, left: 12 };
    const max = Math.max(1, ...rows.map((row) => Number(row.orders || 0)));
    const points = rows.map((row, index) => {
        const x = rows.length <= 1 ? width / 2 : pad.left + (index / (rows.length - 1)) * (width - pad.left - pad.right);
        const y = pad.top + (1 - Number(row.orders || 0) / max) * (height - pad.top - pad.bottom);
        return { ...row, x, y };
    });
    const d = points.map((point, index) => `${index ? 'L' : 'M'} ${point.x} ${point.y}`).join(' ');
    const area = points.length ? `${d} L ${points.at(-1).x} ${height - pad.bottom} L ${points[0].x} ${height - pad.bottom} Z` : '';
    return { width, height, points, d, area, max };
});
const shortDate = (value) => {
    const date = new Date(`${value}T00:00:00`);
    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(date);
};
const outcomeTone = (status) => {
    if (status === 'delivered') return 'is-success';
    if (status === 'cancelled' || status === 'failed') return 'is-danger';
    if (status === 'pending_payment' || status === 'draft') return 'is-pending';
    return 'is-route';
};
</script>

<template>
    <main class="container page-shell analytics-page py-4">
        <header class="analytics-header">
            <div>
                <p class="ds-eyebrow">Administration</p>
                <h1 class="page-title">Analytics</h1>
                <p class="page-description">Delivery volume, outcomes and top routes for the selected date range.</p>
            </div>
            <a class="btn btn-secondary" :href="links.reports">Open detailed reports</a>
        </header>

        <form class="analytics-filters surface" method="get" :action="links.analytics" aria-label="Filter analytics by date">
            <div class="filter-copy"><strong>Reporting period</strong><span>Choose up to 367 days</span></div>
            <label>From <input v-model="from" name="from" type="date" required></label>
            <label>To <input v-model="to" name="to" type="date" required></label>
            <button class="btn btn-primary" type="submit">Apply dates</button>
        </form>

        <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>

        <section class="ds-stat-grid analytics-kpis" aria-label="Performance summary">
            <article class="surface ds-metric-card">
                <span class="ds-metric-card__label"><i class="bi bi-box-seam" aria-hidden="true"></i> Orders</span>
                <span class="ds-metric-card__value">{{ number(summary.order_count) }}</span>
                <span class="ds-metric-card__hint">Created in this period</span>
            </article>
            <article class="surface ds-metric-card">
                <span class="ds-metric-card__label"><i class="bi bi-cash-stack" aria-hidden="true"></i> Paid revenue</span>
                <span class="ds-metric-card__value">{{ money(summary.paid_revenue) }}</span>
                <span class="ds-metric-card__hint">Successful payments</span>
            </article>
            <article class="surface ds-metric-card">
                <span class="ds-metric-card__label"><i class="bi bi-check2-circle" aria-hidden="true"></i> Delivered</span>
                <span class="ds-metric-card__value">{{ number(summary.delivered_count) }}</span>
                <span class="ds-metric-card__hint">Completed orders</span>
            </article>
            <article class="surface ds-metric-card">
                <span class="ds-metric-card__label"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i> Unsuccessful</span>
                <span class="ds-metric-card__value">{{ number(summary.unsuccessful_count) }}</span>
                <span class="ds-metric-card__hint">Failed or cancelled orders</span>
            </article>
        </section>

        <section class="analytics-panel trend-panel" aria-labelledby="order-trend-title">
            <div class="panel-heading">
                <div><h2 id="order-trend-title">Order volume</h2><p>Orders created each day in the selected period</p></div>
                <span class="trend-total">{{ number(summary.order_count) }} total</span>
            </div>
            <div v-if="chart.points.length" class="chart-wrap">
                <svg class="trend-chart" :viewBox="`0 0 ${chart.width} ${chart.height}`" role="img" aria-labelledby="order-chart-label">
                    <title id="order-chart-label">Daily order count trend</title>
                    <line v-for="step in 4" :key="step" x1="0" :y1="18 + (step - 1) * 55" x2="760" :y2="18 + (step - 1) * 55" class="chart-grid" />
                    <path :d="chart.area" class="chart-area" />
                    <path :d="chart.d" class="chart-line" />
                    <circle v-for="point in chart.points" :key="point.date" :cx="point.x" :cy="point.y" r="4" class="chart-dot"><title>{{ shortDate(point.date) }}: {{ number(point.orders) }} orders</title></circle>
                    <text v-if="chart.points.length" :x="chart.points[0].x" y="211" text-anchor="start" class="chart-label">{{ shortDate(chart.points[0].date) }}</text>
                    <text v-if="chart.points.length > 1" :x="chart.points.at(-1).x" y="211" text-anchor="end" class="chart-label">{{ shortDate(chart.points.at(-1).date) }}</text>
                </svg>
            </div>
            <div v-else class="analytics-empty" role="status">No orders in this period. Try a wider date range.</div>
            <details class="chart-data">
                <summary>View daily order data</summary>
                <div class="table-responsive"><table class="table table-sm mb-0"><caption class="visually-hidden">Daily orders in the selected date range</caption><thead><tr><th>Date</th><th>Orders</th></tr></thead><tbody><tr v-for="row in daily" :key="row.date"><td>{{ shortDate(row.date) }}</td><td>{{ number(row.orders) }}</td></tr></tbody></table></div>
            </details>
        </section>

        <div class="analytics-lower">
            <section class="analytics-panel" aria-labelledby="delivery-outcomes-title">
                <div class="panel-heading"><div><h2 id="delivery-outcomes-title">Delivery outcomes</h2><p>Current order status for orders created in this period</p></div></div>
                <div v-if="outcomes.length" class="outcome-list">
                    <div v-for="item in outcomes" :key="item.status" class="outcome-row">
                        <div class="outcome-label"><span>{{ item.label }}</span><strong>{{ number(item.count) }}</strong></div>
                        <div class="outcome-track" :class="outcomeTone(item.status)" role="img" :aria-label="`${item.label}: ${item.count} orders`"><span :style="{ width: `${item.percent}%` }"></span></div>
                    </div>
                </div>
                <p v-else class="analytics-empty" role="status">No delivery outcomes to show yet.</p>
            </section>
            <section class="analytics-panel payment-panel" aria-labelledby="payment-title">
                <div class="panel-heading"><div><h2 id="payment-title">Payment performance</h2><p>Payment records within the selected period</p></div></div>
                <strong class="payment-total">{{ money(summary.paid_revenue) }}</strong>
                <span class="payment-caption">Paid successfully</span>
                <div class="payment-foot"><span>Payment records</span><strong>{{ number(summary.payment_count) }}</strong></div>
                <div class="payment-foot"><span>Failed payments</span><strong>{{ number(summary.failed_payment_count) }}</strong></div>
            </section>
        </div>
    </main>
</template>

