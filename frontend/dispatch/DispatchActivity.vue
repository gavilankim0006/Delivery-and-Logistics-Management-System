<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    activity: { type: Array, default: () => [] },
    baseUrl: { type: String, default: '/' }
});

const filter = ref('all');
const tick = ref(Date.now());
let tickTimer = null;

onMounted(() => {
    tickTimer = setInterval(() => { tick.value = Date.now(); }, 30000);
});
onUnmounted(() => clearInterval(tickTimer));

const FILTERS = [
    { key: 'all', label: 'All activity', icon: 'bi-activity' },
    { key: 'assignment', label: 'Assignments', icon: 'bi-person-check' },
    { key: 'delivery', label: 'Delivery', icon: 'bi-truck' },
    { key: 'failed', label: 'Exceptions', icon: 'bi-exclamation-triangle' }
];

const filteredActivity = computed(() => {
    return props.activity.filter((entry) => {
        if (filter.value === 'assignment') return entry.to_status === 'rider_assigned';
        if (filter.value === 'delivery') return ['picked_up', 'delivered'].includes(entry.to_status);
        if (filter.value === 'failed') return ['failed', 'cancelled'].includes(entry.to_status);
        return true;
    });
});

const filterCounts = computed(() => {
    const counts = { all: props.activity.length, assignment: 0, delivery: 0, failed: 0 };
    for (const entry of props.activity) {
        if (entry.to_status === 'rider_assigned') counts.assignment += 1;
        else if (['picked_up', 'delivered'].includes(entry.to_status)) counts.delivery += 1;
        else if (['failed', 'cancelled'].includes(entry.to_status)) counts.failed += 1;
    }
    return counts;
});

function statusLabel(status) {
    return String(status || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function iconClass(status) {
    switch (status) {
        case 'rider_assigned': return 'bi-person-check';
        case 'picked_up': return 'bi-box-arrow-up';
        case 'delivered': return 'bi-check2-circle';
        case 'failed': return 'bi-exclamation-triangle';
        case 'cancelled': return 'bi-x-circle';
        case 'paid': return 'bi-credit-card';
        case 'booking': return 'bi-hourglass-split';
        default: return 'bi-arrow-right-circle';
    }
}

function iconTone(status) {
    if (status === 'failed') return 'is-danger';
    if (status === 'cancelled') return 'is-muted';
    if (status === 'delivered') return 'is-success';
    if (status === 'rider_assigned' || status === 'picked_up') return 'is-route';
    if (status === 'paid') return 'is-amber';
    return 'is-muted';
}

function actorLabel(entry) {
    const name = String(entry.actor_name || '').trim();
    const roleNoun = { rider: 'Rider', dispatcher: 'Dispatcher', customer: 'Customer' }[entry.actor_type] || '';

    if (!name) {
        const fallback = String(entry.actor_type || 'System').replaceAll('_', ' ');
        return fallback.charAt(0).toUpperCase() + fallback.slice(1);
    }

    if (!roleNoun) return name;

    // Avoid "Dispatcher Lead Dispatcher" style duplication when the name
    // already carries the role noun; keep the name as authored otherwise.
    if (new RegExp(`\\b${roleNoun}\\b`, 'i').test(name)) {
        return name.toLowerCase() === roleNoun.toLowerCase() ? roleNoun : name;
    }

    return `${roleNoun} ${name}`;
}

function activityText(entry) {
    const label = statusLabel(entry.to_status).toLowerCase();
    if (entry.to_status === 'rider_assigned') return `assigned a rider — now ${label}`;
    if (entry.to_status === 'picked_up') return 'picked up the parcel';
    if (entry.to_status === 'delivered') return 'completed the delivery';
    if (entry.to_status === 'failed') return 'reported a failed delivery';
    if (entry.to_status === 'cancelled') return 'cancelled the order';
    return `moved the order to ${label}`;
}

function timeAgo(value) {
    void tick.value;
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return '';
    const minutes = Math.floor((Date.now() - date.getTime()) / 60000);
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return `${days}d ago`;
}

function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleString([], {
        month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
    });
}

function orderUrl(id) {
    return `${props.baseUrl}orders/${encodeURIComponent(id)}`;
}
</script>

<template>
    <section aria-label="Dispatch activity feed">
        <!-- Filters -->
        <div class="dispatch-activity-filters" role="group" aria-label="Filter activity">
            <button
                v-for="option in FILTERS"
                :key="option.key"
                type="button"
                class="dispatch-activity-filter"
                :class="{ 'is-active': filter === option.key }"
                :aria-pressed="filter === option.key ? 'true' : 'false'"
                @click="filter = option.key"
            >
                <i class="bi" :class="option.icon" aria-hidden="true"></i>
                {{ option.label }}
                <span class="dispatch-activity-filter-count">{{ filterCounts[option.key] }}</span>
            </button>
        </div>

        <!-- Empty states -->
        <div v-if="!activity.length" class="dispatch-empty-state surface">
            <div class="dispatch-empty-icon">
                <i class="bi bi-activity" aria-hidden="true"></i>
            </div>
            <h3>No activity yet</h3>
            <p>Rider assignments, pickups, and completions will stream into this feed.</p>
        </div>
        <div v-else-if="!filteredActivity.length" class="dispatch-empty-state surface">
            <div class="dispatch-empty-icon">
                <i class="bi bi-funnel" aria-hidden="true"></i>
            </div>
            <h3>No activity in this filter</h3>
            <p>Choose another filter to review the latest dispatch events.</p>
        </div>

        <!-- Timeline -->
        <ol v-else class="dispatch-activity-list">
            <li v-for="entry in filteredActivity" :key="entry.id" class="dispatch-activity-item">
                <span class="dispatch-activity-icon" :class="iconTone(entry.to_status)">
                    <i class="bi" :class="iconClass(entry.to_status)" aria-hidden="true"></i>
                </span>
                <div class="dispatch-activity-body">
                    <p class="dispatch-activity-line">
                        <a :href="orderUrl(entry.order_id)" class="dispatch-activity-order">{{ entry.order_no }}</a>
                        <span class="dispatch-activity-actor">{{ actorLabel(entry) }}</span>
                        <span class="dispatch-activity-text">{{ activityText(entry) }}</span>
                    </p>
                    <p v-if="entry.notes" class="dispatch-activity-note">{{ entry.notes }}</p>
                    <p class="dispatch-activity-meta">
                        <span v-if="entry.customer_name">
                            <i class="bi bi-person me-1" aria-hidden="true"></i>{{ entry.customer_name }}
                        </span>
                        <span :title="formatDateTime(entry.created_at)">
                            <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ timeAgo(entry.created_at) }}
                        </span>
                        <span v-if="entry.from_status && entry.from_status !== entry.to_status" class="dispatch-activity-transition">
                            {{ statusLabel(entry.from_status) }} → {{ statusLabel(entry.to_status) }}
                        </span>
                    </p>
                </div>
            </li>
        </ol>
    </section>
</template>

<style scoped>
.dispatch-activity-filters {
    display: flex;
    gap: var(--s-2);
    flex-wrap: wrap;
    margin-bottom: var(--s-3);
}
.dispatch-activity-filter {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 40px;
    padding: 6px 12px;
    border-radius: var(--r-pill);
    border: 1px solid var(--line);
    background: var(--surface);
    color: var(--ink-2);
    font-size: var(--font-size-12);
    font-weight: 600;
    transition: all var(--dispatch-transition);
}
.dispatch-activity-filter:hover { border-color: var(--route); color: var(--route); }
.dispatch-activity-filter.is-active {
    background: var(--status-blue-bg);
    border-color: var(--route);
    color: var(--route-dark);
}
.dispatch-activity-filter-count {
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: var(--r-pill);
    background: var(--paper);
    font-size: 11px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.dispatch-empty-state {
    border-radius: var(--r-md);
    border: 1px solid var(--line);
    text-align: center;
    padding: var(--s-5) var(--s-4);
}
.dispatch-empty-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto var(--s-3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--paper);
    color: var(--ink-2);
    font-size: 1.5rem;
}
.dispatch-empty-state h3 {
    font-size: var(--font-size-16);
    font-weight: 700;
    margin-bottom: var(--s-1);
}
.dispatch-empty-state p {
    color: var(--ink-2);
    font-size: var(--font-size-14);
    margin: 0;
}

.dispatch-activity-list {
    list-style: none;
    margin: 0;
    padding: var(--s-2) var(--s-3);
    border: 1px solid var(--line);
    border-radius: var(--r-md);
    background: var(--surface);
    box-shadow: var(--shadow-card);
}
.dispatch-activity-item {
    display: flex;
    align-items: flex-start;
    gap: var(--s-3);
    padding: var(--s-3) 0;
    border-top: 1px solid var(--paper);
}
.dispatch-activity-item:first-child { border-top: 0; }

.dispatch-activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .95rem;
    flex-shrink: 0;
}
.dispatch-activity-icon.is-success { background: var(--status-success-bg); color: var(--success); }
.dispatch-activity-icon.is-route { background: var(--status-blue-bg); color: var(--route); }
.dispatch-activity-icon.is-amber { background: var(--status-pending-bg); color: var(--amber-ink); }
.dispatch-activity-icon.is-danger { background: var(--status-danger-bg); color: var(--danger); }
.dispatch-activity-icon.is-muted { background: var(--paper); color: var(--ink-2); }

.dispatch-activity-body { min-width: 0; flex: 1 1 auto; }
.dispatch-activity-line {
    margin: 0 0 4px;
    font-size: var(--font-size-14);
    line-height: 1.45;
    overflow-wrap: anywhere;
}
.dispatch-activity-order {
    font-weight: 700;
    color: var(--route-dark);
    margin-inline-end: 6px;
}
.dispatch-activity-actor {
    font-weight: 600;
    margin-inline-end: 4px;
}
.dispatch-activity-text { color: var(--ink-2); }

.dispatch-activity-note {
    margin: 0 0 4px;
    font-size: var(--font-size-12);
    color: var(--ink-2);
    background: var(--paper);
    border-radius: var(--r-sm);
    padding: 6px 10px;
    overflow-wrap: anywhere;
}

.dispatch-activity-meta {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    flex-wrap: wrap;
    margin: 0;
    font-size: var(--font-size-12);
    color: var(--ink-3, var(--ink-2));
}
.dispatch-activity-transition {
    margin-inline-start: auto;
    color: var(--ink-3, var(--ink-2));
}

@media (max-width: 575.98px) {
    .dispatch-activity-list { padding: var(--s-2); }
    .dispatch-activity-transition { margin-inline-start: 0; }
    .dispatch-activity-filters { overflow-x: auto; flex-wrap: nowrap; padding-bottom: 2px; }
    .dispatch-activity-filter { flex-shrink: 0; }
}
</style>
