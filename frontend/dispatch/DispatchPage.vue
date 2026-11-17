<script setup>
import { computed, ref, watch } from 'vue';
import DispatchControls from './DispatchControls.vue';
import DispatchBoard from './DispatchBoard.vue';
import DispatchRiders from './DispatchRiders.vue';
import DispatchActivity from './DispatchActivity.vue';

const props = defineProps({
    controlsProps: { type: Object, default: () => ({}) },
    boardProps: { type: Object, default: () => ({}) },
    baseUrl: { type: String, default: '/' },
    csrfToken: { type: String, default: '' },
    csrfName: { type: String, default: 'csrf_token' },
    flashes: { type: Array, default: () => [] }
});

const TAB_KEYS = ['board', 'riders', 'activity'];
const initialTab = typeof window !== 'undefined' ? window.location.hash.replace('#', '') : '';
const activeTab = ref(TAB_KEYS.includes(initialTab) ? initialTab : 'board');

function activateTab(key, focusTab = false) {
    if (!TAB_KEYS.includes(key)) return;
    activeTab.value = key;
    if (focusTab && typeof document !== 'undefined') {
        requestAnimationFrame(() => document.getElementById(`dispatch-tab-${key}`)?.focus());
    }
}

function onTabKeydown(event) {
    const index = TAB_KEYS.indexOf(activeTab.value);
    let next = null;
    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = (index + 1) % TAB_KEYS.length;
    else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') next = (index - 1 + TAB_KEYS.length) % TAB_KEYS.length;
    else if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = TAB_KEYS.length - 1;
    if (next === null) return;
    event.preventDefault();
    activateTab(TAB_KEYS[next], true);
}

watch(activeTab, (key) => {
    if (typeof window === 'undefined') return;
    const base = window.location.pathname + window.location.search;
    window.history.replaceState({}, '', key === 'board' ? base : `${base}#${key}`);
    window.scrollTo({ top: 0 });
});

const riders = ref(Array.isArray(props.boardProps.riders) ? props.boardProps.riders : []);
const activity = ref(Array.isArray(props.boardProps.activity) ? props.boardProps.activity : []);
const metrics = ref(props.boardProps.metrics || props.controlsProps.metrics || {});
const summary = ref(props.controlsProps.summary || {});
const dispatcherName = ref(props.boardProps.dispatcherName || props.controlsProps.dispatcherName || 'Dispatcher');

function onBoardData(payload) {
    if (!payload || typeof payload !== 'object') return;
    if (Array.isArray(payload.riders)) riders.value = payload.riders;
    if (Array.isArray(payload.activity)) activity.value = payload.activity;
    if (payload.metrics && typeof payload.metrics === 'object') metrics.value = payload.metrics;
    if (payload.summary && typeof payload.summary === 'object') summary.value = payload.summary;
    if (payload.dispatcherName) dispatcherName.value = payload.dispatcherName;
}

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
});

const dispatcherFirstName = computed(() => {
    const name = String(dispatcherName.value || '').trim();
    return name ? name.split(/\s+/)[0] : 'Dispatcher';
});

const today = computed(() => new Date().toLocaleDateString([], {
    weekday: 'long', month: 'long', day: 'numeric'
}));

function number(value) {
    return Number(value || 0).toLocaleString();
}

const tabs = computed(() => ([
    { key: 'board', label: 'Board', icon: 'bi-kanban', badge: Number(summary.value.in_transit || 0), tone: 'route' },
    { key: 'riders', label: 'Riders', icon: 'bi-bicycle', badge: Number(metrics.value.available_riders || 0), tone: 'success' },
    { key: 'activity', label: 'Activity', icon: 'bi-activity', badge: Number(metrics.value.failed_count ?? summary.value.failed_count ?? 0), tone: 'danger' }]
));
</script>

<template>
    <div class="container-fluid page-shell dispatch-page-shell py-3">
        <div v-for="(flash, index) in flashes" :key="index" class="alert alert-dismissible fade show mb-3" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
            <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
            {{ flash.message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <!-- ─── Workspace tabs ─────────────────────────── -->
        <nav
            class="dispatch-workspace-tabs"
            role="tablist"
            aria-label="Dispatch workspace"
            @keydown="onTabKeydown"
        >
            <button
                v-for="tab in tabs"
                :key="tab.key"
                :id="`dispatch-tab-${tab.key}`"
                type="button"
                role="tab"
                class="dispatch-workspace-tab"
                :class="{ 'is-active': activeTab === tab.key }"
                :aria-selected="activeTab === tab.key ? 'true' : 'false'"
                :aria-controls="`dispatch-panel-${tab.key}`"
                :tabindex="activeTab === tab.key ? 0 : -1"
                @click="activateTab(tab.key)"
            >
                <i class="bi" :class="tab.icon" aria-hidden="true"></i>
                <span>{{ tab.label }}</span>
                <span
                    v-if="tab.badge > 0"
                    class="dispatch-workspace-tab-badge"
                    :class="`is-${tab.tone}`"
                >{{ tab.badge > 99 ? '99+' : tab.badge }}</span>
            </button>
        </nav>

        <!-- ═══ BOARD TAB ═══ -->
        <div
            v-show="activeTab === 'board'"
            id="dispatch-panel-board"
            role="tabpanel"
            aria-labelledby="dispatch-tab-board"
        >
            <div id="dispatch-controls-vue-root">
                <DispatchControls
                    :initial="controlsProps"
                    :base-url="baseUrl"
                    :csrf-token="csrfToken"
                    :csrf-name="csrfName"
                />
            </div>

            <section class="surface dispatch-board-card mt-3" aria-label="Dispatch orders" data-dispatch-vue-board>
                <DispatchBoard
                    :data-url="boardProps.dataUrl"
                    :base-url="baseUrl"
                    :csrf-token="csrfToken"
                    :fulfillment-actions="boardProps.fulfillmentActions"
                    :available-riders="boardProps.availableRiders"
                    @board-data="onBoardData"
                />
            </section>
        </div>

        <!-- ═══ RIDERS TAB ═══ -->
        <div
            v-show="activeTab === 'riders'"
            id="dispatch-panel-riders"
            role="tabpanel"
            aria-labelledby="dispatch-tab-riders"
        >
            <header class="dispatch-workspace-banner surface dispatch-fade-in mb-3">
                <div>
                    <h2 class="dispatch-workspace-title">{{ greeting }}, {{ dispatcherFirstName }}</h2>
                    <p class="dispatch-workspace-date mb-0">{{ today }} · Rider roster</p>
                </div>
                <div class="dispatch-workspace-stats" aria-label="Live roster summary">
                    <span class="dispatch-workspace-stat dispatch-workspace-stat--available">
                        <i class="bi bi-check-circle" aria-hidden="true"></i>
                        {{ number(metrics.available_riders) }} available
                    </span>
                    <span class="dispatch-workspace-stat dispatch-workspace-stat--busy">
                        <i class="bi bi-truck" aria-hidden="true"></i>
                        {{ number(metrics.active_riders) }} on delivery
                    </span>
                    <span class="dispatch-workspace-stat">
                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                        {{ number(metrics.delivered_today) }} delivered today
                    </span>
                </div>
            </header>
            <DispatchRiders :riders="riders" :base-url="baseUrl" />
        </div>

        <!-- ═══ ACTIVITY TAB ═══ -->
        <div
            v-show="activeTab === 'activity'"
            id="dispatch-panel-activity"
            role="tabpanel"
            aria-labelledby="dispatch-tab-activity"
        >
            <header class="dispatch-workspace-banner surface dispatch-fade-in mb-3">
                <div>
                    <h2 class="dispatch-workspace-title">{{ greeting }}, {{ dispatcherFirstName }}</h2>
                    <p class="dispatch-workspace-date mb-0">{{ today }} · Operational activity</p>
                </div>
                <div class="dispatch-workspace-stats" aria-label="Live activity summary">
                    <span class="dispatch-workspace-stat dispatch-workspace-stat--success">
                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                        {{ number(metrics.delivered_today) }} delivered today
                    </span>
                    <span class="dispatch-workspace-stat">
                        <i class="bi bi-calendar-week" aria-hidden="true"></i>
                        {{ number(metrics.delivered_week) }} this week
                    </span>
                    <span class="dispatch-workspace-stat dispatch-workspace-stat--danger">
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                        {{ number(summary.failed_count) }} failed
                    </span>
                </div>
            </header>
            <DispatchActivity :activity="activity" :base-url="baseUrl" />
        </div>
    </div>

    <!-- ─── Mobile bottom nav ─────────────────────────── -->
    <nav class="dispatch-mobile-bottom-bar" aria-label="Dispatch mobile navigation">
        <div class="dispatch-mobile-bottom-bar-inner">
            <button
                type="button"
                class="dispatch-mobile-nav-item"
                :class="{ 'is-active': activeTab === 'board' }"
                :aria-current="activeTab === 'board' ? 'page' : undefined"
                aria-controls="dispatch-panel-board"
                @click="activateTab('board')"
            >
                <i class="bi bi-kanban" aria-hidden="true"></i>
                <span>Board</span>
                <span v-if="Number(summary.in_transit) > 0" class="dispatch-mobile-nav-badge dispatch-mobile-nav-badge--route">{{ summary.in_transit }}</span>
            </button>
            <button
                type="button"
                class="dispatch-mobile-nav-item"
                :class="{ 'is-active': activeTab === 'riders' }"
                :aria-current="activeTab === 'riders' ? 'page' : undefined"
                aria-controls="dispatch-panel-riders"
                @click="activateTab('riders')"
            >
                <i class="bi bi-bicycle" aria-hidden="true"></i>
                <span>Riders</span>
                <span v-if="Number(metrics.available_riders) > 0" class="dispatch-mobile-nav-badge dispatch-mobile-nav-badge--success">{{ metrics.available_riders }}</span>
            </button>
            <button
                type="button"
                class="dispatch-mobile-nav-item"
                :class="{ 'is-active': activeTab === 'activity' }"
                :aria-current="activeTab === 'activity' ? 'page' : undefined"
                aria-controls="dispatch-panel-activity"
                @click="activateTab('activity')"
            >
                <i class="bi bi-activity" aria-hidden="true"></i>
                <span>Activity</span>
                <span v-if="Number(summary.failed_count) > 0" class="dispatch-mobile-nav-badge">{{ summary.failed_count }}</span>
            </button>
            <a :href="`${baseUrl}orders`" class="dispatch-mobile-nav-item">
                <i class="bi bi-box-seam" aria-hidden="true"></i>
                <span>Orders</span>
            </a>
        </div>
    </nav>
</template>

<style scoped>
.dispatch-workspace-tabs {
    display: flex;
    gap: var(--s-1);
    padding: var(--s-1);
    margin-bottom: var(--s-3);
    border: 1px solid var(--line);
    border-radius: var(--r-md);
    background: var(--paper);
    overflow-x: auto;
}

.dispatch-workspace-tab {
    position: relative;
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--s-2);
    min-height: 44px;
    padding: var(--s-2) var(--s-4);
    border: 0;
    border-radius: var(--r-sm);
    background: transparent;
    color: var(--ink-2);
    font-size: var(--font-size-14);
    font-weight: 600;
    white-space: nowrap;
    transition: background var(--dispatch-transition), color var(--dispatch-transition);
}

.dispatch-workspace-tab:hover { color: var(--ink); }

.dispatch-workspace-tab:focus-visible {
    outline: 2px solid var(--route);
    outline-offset: 2px;
}

.dispatch-workspace-tab.is-active {
    background: var(--surface);
    color: var(--route-dark);
    box-shadow: var(--shadow-card);
}

.dispatch-workspace-tab-badge {
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: var(--r-pill);
    background: var(--route);
    color: var(--surface);
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.dispatch-workspace-tab-badge.is-route { background: var(--route); }
.dispatch-workspace-tab-badge.is-success { background: var(--success); }
.dispatch-workspace-tab-badge.is-danger { background: var(--danger); }

.dispatch-workspace-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--s-3);
    flex-wrap: wrap;
    padding: var(--s-3) var(--s-4);
    border-radius: var(--r-md);
}

.dispatch-workspace-title {
    font-size: var(--font-size-20);
    font-weight: 700;
    margin: 0 0 2px;
}

.dispatch-workspace-date {
    color: var(--ink-2);
    font-size: var(--font-size-14);
}

.dispatch-workspace-stats {
    display: flex;
    gap: var(--s-2);
    flex-wrap: wrap;
}

.dispatch-workspace-stat {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: var(--r-pill);
    background: var(--paper);
    border: 1px solid var(--line);
    color: var(--ink-2);
    font-size: var(--font-size-12);
    font-weight: 600;
    white-space: nowrap;
}
.dispatch-workspace-stat--available { color: var(--success); border-color: color-mix(in srgb, var(--success) 30%, var(--line)); background: var(--status-success-bg); }
.dispatch-workspace-stat--busy { color: var(--route); border-color: color-mix(in srgb, var(--route) 30%, var(--line)); background: var(--status-blue-bg); }
.dispatch-workspace-stat--success { color: var(--success); border-color: color-mix(in srgb, var(--success) 30%, var(--line)); background: var(--status-success-bg); }
.dispatch-workspace-stat--danger { color: var(--danger); border-color: color-mix(in srgb, var(--danger) 30%, var(--line)); background: var(--status-danger-bg); }

.dispatch-mobile-nav-badge--success {
    background: var(--success);
}

@media (max-width: 767.98px) {
    /* The fixed bottom bar provides the same three destinations on phones. */
    .dispatch-workspace-tabs { display: none; }

    .dispatch-workspace-banner {
        flex-direction: column;
        align-items: flex-start;
        padding: var(--s-3);
    }
    .dispatch-workspace-stats { width: 100%; }
}
</style>
