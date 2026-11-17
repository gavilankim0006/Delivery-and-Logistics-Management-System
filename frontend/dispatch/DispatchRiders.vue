<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    riders: { type: Array, default: () => [] },
    baseUrl: { type: String, default: '/' }
});

const search = ref('');
const statusFilter = ref('all');

const STATUS_OPTIONS = [
    { key: 'all', label: 'All', icon: 'bi-people' },
    { key: 'available', label: 'Available', icon: 'bi-check-circle' },
    { key: 'busy', label: 'On delivery', icon: 'bi-truck' },
    { key: 'offline', label: 'Offline', icon: 'bi-moon' }
];

const counts = computed(() => {
    const totals = { all: props.riders.length, available: 0, busy: 0, offline: 0 };
    for (const rider of props.riders) {
        if (totals[rider.status] !== undefined) totals[rider.status] += 1;
    }
    return totals;
});

const filteredRiders = computed(() => {
    const term = search.value.trim().toLowerCase();
    return props.riders.filter((rider) => {
        if (statusFilter.value !== 'all' && rider.status !== statusFilter.value) return false;
        if (!term) return true;
        return [
            rider.name,
            rider.phone,
            rider.vehicle_type,
            rider.plate_number,
            rider.account_email,
            rider.active_order_no
        ].some((value) => String(value || '').toLowerCase().includes(term));
    });
});

function statusLabel(status) {
    if (status === 'busy') return 'On delivery';
    if (status === 'available') return 'Available';
    return 'Offline';
}

function statusClass(status) {
    if (status === 'busy') return 'is-busy';
    if (status === 'available') return 'is-available';
    return 'is-offline';
}

function initials(name) {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'R';
    return (parts[0][0] + (parts[1]?.[0] || '')).toUpperCase();
}

function ratingText(rider) {
    const value = Number(rider.rating_average || 0);
    return value > 0 ? value.toFixed(1) : '—';
}

function starClass(rider, index) {
    return index <= Math.round(Number(rider.rating_average || 0)) ? 'bi-star-fill' : 'bi-star';
}

function phoneUrl(phone) {
    return `tel:${String(phone || '').replace(/[^+0-9]/g, '')}`;
}

function orderUrl(id) {
    return `${props.baseUrl}orders/${encodeURIComponent(id)}`;
}

function number(value) {
    return Number(value || 0).toLocaleString();
}
</script>

<template>
    <section aria-label="Rider roster">
        <!-- Roster summary -->
        <div class="dispatch-roster-summary">
            <div class="dispatch-roster-stat">
                <span class="dispatch-roster-stat-icon dispatch-roster-stat-icon--available"><i class="bi bi-check-circle" aria-hidden="true"></i></span>
                <div>
                    <span class="dispatch-roster-stat-value">{{ counts.available }}</span>
                    <span class="dispatch-roster-stat-label">Available</span>
                </div>
            </div>
            <div class="dispatch-roster-stat">
                <span class="dispatch-roster-stat-icon dispatch-roster-stat-icon--busy"><i class="bi bi-truck" aria-hidden="true"></i></span>
                <div>
                    <span class="dispatch-roster-stat-value">{{ counts.busy }}</span>
                    <span class="dispatch-roster-stat-label">On delivery</span>
                </div>
            </div>
            <div class="dispatch-roster-stat">
                <span class="dispatch-roster-stat-icon dispatch-roster-stat-icon--offline"><i class="bi bi-moon" aria-hidden="true"></i></span>
                <div>
                    <span class="dispatch-roster-stat-value">{{ counts.offline }}</span>
                    <span class="dispatch-roster-stat-label">Offline</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="surface dispatch-roster-toolbar">
            <div class="dispatch-roster-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <label class="visually-hidden" for="dispatch-roster-search">Search riders</label>
                <input
                    id="dispatch-roster-search"
                    v-model="search"
                    type="search"
                    class="form-control"
                    placeholder="Search by name, phone, plate, or order..."
                    autocomplete="off"
                >
            </div>
            <div class="dispatch-roster-filters" role="group" aria-label="Filter riders by status">
                <button
                    v-for="option in STATUS_OPTIONS"
                    :key="option.key"
                    type="button"
                    class="dispatch-roster-filter"
                    :class="{ 'is-active': statusFilter === option.key }"
                    :aria-pressed="statusFilter === option.key ? 'true' : 'false'"
                    @click="statusFilter = option.key"
                >
                    <i class="bi" :class="option.icon" aria-hidden="true"></i>
                    {{ option.label }}
                    <span class="dispatch-roster-filter-count">{{ counts[option.key] }}</span>
                </button>
            </div>
        </div>

        <!-- Empty states -->
        <div v-if="!riders.length" class="dispatch-empty-state surface">
            <div class="dispatch-empty-icon">
                <i class="bi bi-bicycle" aria-hidden="true"></i>
            </div>
            <h3>No riders in the roster</h3>
            <p>Add riders from the admin panel to build your dispatch roster.</p>
        </div>
        <div v-else-if="!filteredRiders.length" class="dispatch-empty-state surface">
            <div class="dispatch-empty-icon">
                <i class="bi bi-search" aria-hidden="true"></i>
            </div>
            <h3>No riders match these filters</h3>
            <p>Try another status or clear the search term.</p>
        </div>

        <!-- Roster cards -->
        <div v-else class="dispatch-roster-grid">
            <article
                v-for="rider in filteredRiders"
                :key="rider.id"
                class="dispatch-rider-card"
                :class="statusClass(rider.status)"
            >
                <header class="dispatch-rider-card-head">
                    <span class="dispatch-rider-avatar" aria-hidden="true">{{ initials(rider.name) }}</span>
                    <div class="dispatch-rider-identity">
                        <h3 class="dispatch-rider-name">{{ rider.name }}</h3>
                        <span class="dispatch-rider-status" :class="statusClass(rider.status)">
                            <span class="dispatch-rider-status-dot" aria-hidden="true"></span>
                            {{ statusLabel(rider.status) }}
                        </span>
                    </div>
                </header>

                <dl class="dispatch-rider-meta">
                    <div class="dispatch-rider-meta-row">
                        <dt><i class="bi bi-bicycle" aria-hidden="true"></i> Vehicle</dt>
                        <dd>{{ rider.vehicle_type || '—' }}<template v-if="rider.plate_number"> · {{ rider.plate_number }}</template></dd>
                    </div>
                    <div class="dispatch-rider-meta-row">
                        <dt><i class="bi bi-telephone" aria-hidden="true"></i> Phone</dt>
                        <dd>{{ rider.phone || '—' }}</dd>
                    </div>
                    <div v-if="rider.account_email" class="dispatch-rider-meta-row">
                        <dt><i class="bi bi-envelope" aria-hidden="true"></i> Account</dt>
                        <dd>{{ rider.account_email }}</dd>
                    </div>
                </dl>

                <div v-if="rider.active_order_no" class="dispatch-rider-assignment">
                    <span class="dispatch-rider-assignment-label">
                        <i class="bi bi-geo-alt" aria-hidden="true"></i> Active delivery
                    </span>
                    <a :href="orderUrl(rider.active_order_id)" class="dispatch-rider-assignment-order">
                        {{ rider.active_order_no }}
                    </a>
                    <span v-if="rider.active_order_status" class="dispatch-rider-assignment-state">
                        {{ String(rider.active_order_status).replaceAll('_', ' ') }}
                    </span>
                </div>
                <div v-else class="dispatch-rider-assignment dispatch-rider-assignment--none">
                    <i class="bi bi-inbox" aria-hidden="true"></i>
                    No active assignment
                </div>

                <footer class="dispatch-rider-card-foot">
                    <div class="dispatch-rider-stats">
                        <span class="dispatch-rider-stat">
                            <strong>{{ number(rider.delivered_today) }}</strong> today
                        </span>
                        <span class="dispatch-rider-stat">
                            <strong>{{ number(rider.delivered_total) }}</strong> total
                        </span>
                        <span class="dispatch-rider-stat dispatch-rider-stat--rating" :aria-label="`Rating ${ratingText(rider)} out of 5`">
                            <i :class="['bi', starClass(rider, 1)]" aria-hidden="true"></i>
                            {{ ratingText(rider) }}
                        </span>
                    </div>
                    <a v-if="rider.phone" :href="phoneUrl(rider.phone)" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-telephone" aria-hidden="true"></i>
                        Call
                    </a>
                </footer>
            </article>
        </div>
    </section>
</template>

<style scoped>
.dispatch-roster-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--s-3);
    margin-bottom: var(--s-3);
}
.dispatch-roster-stat {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    padding: var(--s-3);
    border: 1px solid var(--line);
    border-radius: var(--r-md);
    background: var(--surface);
    box-shadow: var(--shadow-card);
}
.dispatch-roster-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--r-sm);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
}
.dispatch-roster-stat-icon--available { background: var(--status-success-bg); color: var(--success); }
.dispatch-roster-stat-icon--busy { background: var(--status-blue-bg); color: var(--route); }
.dispatch-roster-stat-icon--offline { background: var(--paper); color: var(--ink-2); }
.dispatch-roster-stat-value {
    display: block;
    font-size: var(--font-size-24);
    font-weight: 700;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}
.dispatch-roster-stat-label {
    display: block;
    font-size: var(--font-size-12);
    color: var(--ink-2);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-top: 2px;
}

.dispatch-roster-toolbar {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    flex-wrap: wrap;
    padding: var(--s-3);
    border-radius: var(--r-md);
    border: 1px solid var(--line);
    margin-bottom: var(--s-3);
}
.dispatch-roster-search {
    position: relative;
    flex: 1 1 260px;
    min-width: 0;
}
.dispatch-roster-search i {
    position: absolute;
    left: var(--s-3);
    top: 50%;
    transform: translateY(-50%);
    color: var(--ink-3);
    pointer-events: none;
}
.dispatch-roster-search .form-control {
    padding-left: 2.5rem;
    min-height: 44px;
}
.dispatch-roster-filters {
    display: flex;
    gap: var(--s-2);
    flex-wrap: wrap;
}
.dispatch-roster-filter {
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
.dispatch-roster-filter:hover { border-color: var(--route); color: var(--route); }
.dispatch-roster-filter.is-active {
    background: var(--status-blue-bg);
    border-color: var(--route);
    color: var(--route-dark);
}
.dispatch-roster-filter-count {
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: var(--r-pill);
    background: var(--paper);
    color: inherit;
    font-size: 11px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.dispatch-roster-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: var(--s-3);
}
.dispatch-rider-card {
    display: flex;
    flex-direction: column;
    gap: var(--s-3);
    padding: var(--s-3);
    border: 1px solid var(--line);
    border-radius: var(--r-md);
    background: var(--surface);
    box-shadow: var(--shadow-card);
    transition: box-shadow var(--dispatch-transition), transform var(--dispatch-transition);
}
.dispatch-rider-card:hover {
    box-shadow: var(--shadow-card-hover);
    transform: translateY(-2px);
}
.dispatch-rider-card.is-busy { border-left: 3px solid var(--route); }
.dispatch-rider-card.is-available { border-left: 3px solid var(--success); }
.dispatch-rider-card.is-offline { border-left: 3px solid var(--line); }

.dispatch-rider-card-head {
    display: flex;
    align-items: center;
    gap: var(--s-3);
}
.dispatch-rider-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--status-blue-bg);
    color: var(--route-dark);
    font-weight: 700;
    flex-shrink: 0;
}
.dispatch-rider-identity { min-width: 0; }
.dispatch-rider-name {
    font-size: var(--font-size-16);
    font-weight: 700;
    margin: 0 0 2px;
    overflow-wrap: anywhere;
}
.dispatch-rider-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: var(--font-size-12);
    font-weight: 600;
}
.dispatch-rider-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: currentColor;
}
.dispatch-rider-status.is-available { color: var(--success); }
.dispatch-rider-status.is-busy { color: var(--route); }
.dispatch-rider-status.is-offline { color: var(--ink-3, var(--ink-2)); }

.dispatch-rider-meta {
    display: flex;
    flex-direction: column;
    gap: 0;
    margin: 0;
}
.dispatch-rider-meta-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: var(--s-3);
    padding: 6px 0;
    border-top: 1px solid var(--paper);
}
.dispatch-rider-meta-row dt {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: var(--font-size-12);
    font-weight: 600;
    color: var(--ink-2);
    margin: 0;
    flex-shrink: 0;
}
.dispatch-rider-meta-row dd {
    margin: 0;
    font-size: var(--font-size-14);
    font-weight: 600;
    text-align: end;
    overflow-wrap: anywhere;
}

.dispatch-rider-assignment {
    display: flex;
    align-items: center;
    gap: var(--s-2);
    flex-wrap: wrap;
    padding: var(--s-2) var(--s-3);
    border-radius: var(--r-sm);
    background: var(--status-blue-bg);
    font-size: var(--font-size-12);
}
.dispatch-rider-assignment--none {
    background: var(--paper);
    color: var(--ink-2);
}
.dispatch-rider-assignment-label {
    font-weight: 700;
    color: var(--route-dark);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.dispatch-rider-assignment-order {
    font-weight: 700;
    color: var(--route-dark);
}
.dispatch-rider-assignment-state {
    margin-inline-start: auto;
    color: var(--ink-2);
    text-transform: capitalize;
}

.dispatch-rider-card-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--s-2);
    flex-wrap: wrap;
    margin-top: auto;
    padding-top: var(--s-2);
    border-top: 1px solid var(--paper);
}
.dispatch-rider-stats {
    display: flex;
    gap: var(--s-3);
    flex-wrap: wrap;
}
.dispatch-rider-stat {
    font-size: var(--font-size-12);
    color: var(--ink-2);
}
.dispatch-rider-stat strong {
    color: var(--ink);
    font-size: var(--font-size-14);
}
.dispatch-rider-stat--rating {
    color: var(--amber-ink);
    font-weight: 700;
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

@media (max-width: 767.98px) {
    .dispatch-roster-summary { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--s-2); }
    .dispatch-roster-stat { flex-direction: column; align-items: flex-start; gap: var(--s-2); padding: var(--s-2); }
    .dispatch-roster-stat-value { font-size: var(--font-size-20); }
    .dispatch-roster-grid { grid-template-columns: 1fr; }
    .dispatch-roster-toolbar { align-items: stretch; }
    .dispatch-roster-search { flex: 1 1 100%; }
    .dispatch-roster-filters { overflow-x: auto; flex-wrap: nowrap; padding-bottom: 2px; }
    .dispatch-roster-filter { flex-shrink: 0; }
}
</style>
