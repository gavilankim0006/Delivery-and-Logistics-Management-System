<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    role: { type: String, default: 'guest' },
    baseUrl: { type: String, default: '/' },
    currentUri: { type: String, default: '' },
    csrfToken: { type: String, default: '' },
    showCourierSimulator: { type: Boolean, default: false }
});

const isNavOpen = ref(false);
const isMoreOpen = ref(false);
const isUserOpen = ref(false);

const notifications = ref([]);
const unreadCount = ref(0);
const isNotificationsOpen = ref(false);
let notificationTimer = null;

async function loadNotifications() {
    if (props.role !== 'customer') return;
    try {
        const response = await fetch(url('notifications/data'), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store'
        });
        if (!response.ok) return;
        const payload = await response.json();
        if (payload.success && payload.data) {
            notifications.value = Array.isArray(payload.data.notifications) ? payload.data.notifications : [];
            unreadCount.value = Number(payload.data.unreadCount || 0);
        }
    } catch {
        // Keep the current feed; the next poll will retry.
    }
}

function scheduleNotificationPoll() {
    if (typeof window === 'undefined' || props.role !== 'customer') return;
    window.clearTimeout(notificationTimer);
    notificationTimer = window.setTimeout(async () => {
        if (!document.hidden) {
            await loadNotifications();
        }
        scheduleNotificationPoll();
    }, 60000);
}

function toggleNotifications() {
    isNotificationsOpen.value = !isNotificationsOpen.value;
    if (isNotificationsOpen.value) {
        loadNotifications();
    }
}

function notificationTarget(notification) {
    return notification.order_id ? `${cleanBaseUrl.value}orders/${Number(notification.order_id)}` : cleanBaseUrl.value;
}

async function markNotificationRead(notification) {
    if (notification.read_at) return;
    notification.read_at = new Date().toISOString();
    unreadCount.value = Math.max(0, unreadCount.value - 1);
    try {
        const body = new URLSearchParams({ id: String(notification.id), csrf_token: props.csrfToken });
        const response = await fetch(url('notifications/read'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            body
        });
        if (response.ok) {
            const payload = await response.json();
            if (payload.success && payload.data) {
                unreadCount.value = Number(payload.data.unreadCount || 0);
            }
        }
    } catch {
        // Keep the optimistic state; the next poll will reconcile.
    }
}

async function markAllNotificationsRead() {
    if (unreadCount.value === 0) return;
    const now = new Date().toISOString();
    notifications.value = notifications.value.map((notification) => ({ ...notification, read_at: notification.read_at || now }));
    unreadCount.value = 0;
    try {
        const body = new URLSearchParams({ csrf_token: props.csrfToken });
        await fetch(url('notifications/read-all'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            body
        });
    } catch {
        // Keep the optimistic state; the next poll will reconcile.
    }
}

function relativeTime(value) {
    if (!value) return '';
    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return String(value);
    const seconds = Math.max(0, Math.floor((Date.now() - parsed.getTime()) / 1000));
    if (seconds < 60) return 'Just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return parsed.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

const cleanBaseUrl = computed(() => {
    let url = props.baseUrl || '/';
    if (!url.endsWith('/')) url += '/';
    return url;
});

function url(path = '') {
    const p = String(path).replace(/^\/+/, '');
    return cleanBaseUrl.value + p;
}

const currentPath = computed(() => {
    const raw = props.currentUri || (typeof window !== 'undefined' ? window.location.pathname : '/');
    return raw.replace(/\/+$/, '') || '/';
});

function navIsActive(dest) {
    const target = '/' + String(dest).replace(/^\/+|\/+$/g, '');
    const path = currentPath.value;
    return path === target || path.endsWith(target) || path.includes(target + '/');
}

const adminMoreActive = computed(() => {
    return navIsActive('admin/settings') ||
        navIsActive('admin/service-areas') ||
        navIsActive('admin/command-simulator') ||
        navIsActive('admin/reports') ||
        navIsActive('admin/package-reports') ||
        navIsActive('admin/logs') ||
        (props.showCourierSimulator && navIsActive('admin/courier-simulator'));
});

const adminDashboardActive = computed(() => {
    return navIsActive('admin') &&
        !navIsActive('admin/users') &&
        !navIsActive('admin/riders') &&
        !navIsActive('admin/applications') &&
        !navIsActive('admin/analytics') &&
        !adminMoreActive.value;
});

const brandHref = computed(() => {
    if (props.role === 'admin') return url('admin');
    if (props.role === 'dispatcher') return url('dispatch');
    if (props.role === 'rider') return url('rider');
    if (props.role === 'customer') return url('dashboard');
    return cleanBaseUrl.value;
});

const brandRoleBadge = computed(() => {
    if (props.role === 'admin') return 'Admin';
    if (props.role === 'dispatcher') return 'Dispatch team';
    return null;
});

const userDisplayName = computed(() => {
    return props.user?.name || props.user?.email || 'My account';
});

function handleClickOutside(e) {
    if (!e.target.closest('.dropdown-more')) {
        isMoreOpen.value = false;
    }
    if (!e.target.closest('.dropdown-user')) {
        isUserOpen.value = false;
    }
    if (!e.target.closest('.dropdown-notifications')) {
        isNotificationsOpen.value = false;
    }
}

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('click', handleClickOutside);
    }
    if (props.role === 'customer') {
        loadNotifications();
        scheduleNotificationPoll();
    }
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('click', handleClickOutside);
        window.clearTimeout(notificationTimer);
    }
});
</script>

<template>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <nav class="navbar navbar-expand-lg navbar-custom" aria-label="Main navigation">
        <div class="container page-shell">
            <a class="navbar-brand navbar-brand-custom" :href="brandHref" aria-label="Dispatch home">
                <i class="bi bi-box-seam" aria-hidden="true"></i>
                <span>Dispatch</span>
                <span v-if="brandRoleBadge" class="navbar-role">{{ brandRoleBadge }}</span>
            </a>

            <button
                class="navbar-toggler"
                type="button"
                @click="isNavOpen = !isNavOpen"
                :aria-expanded="isNavOpen ? 'true' : 'false'"
                aria-controls="mainNavbar"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div id="mainNavbar" class="collapse navbar-collapse" :class="{ show: isNavOpen }">
                <!-- Left Nav Items -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <!-- Customer links -->
                    <template v-if="role === 'customer'">
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('dashboard') }" :href="url('dashboard')" :aria-current="navIsActive('dashboard') ? 'page' : undefined">
                                <i class="bi bi-speedometer2" aria-hidden="true"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('package-workflow') }" :href="url('package-workflow')" :aria-current="navIsActive('package-workflow') ? 'page' : undefined">
                                <i class="bi bi-chat-dots" aria-hidden="true"></i>
                                <span>Package messenger</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('orders/new') }" :href="url('orders/new')" :aria-current="navIsActive('orders/new') ? 'page' : undefined">
                                <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                <span>New order</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('orders') && !navIsActive('orders/new') }" :href="url('orders')" :aria-current="navIsActive('orders') && !navIsActive('orders/new') ? 'page' : undefined">
                                <i class="bi bi-clock-history" aria-hidden="true"></i>
                                <span>My Orders</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('templates') }" :href="url('templates')" :aria-current="navIsActive('templates') ? 'page' : undefined">
                                <i class="bi bi-bookmarks" aria-hidden="true"></i>
                                <span>Templates</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('addresses') }" :href="url('addresses')" :aria-current="navIsActive('addresses') ? 'page' : undefined">
                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                <span>Saved addresses</span>
                            </a>
                        </li>
                    </template>

                    <!-- Rider links -->
                    <template v-else-if="role === 'rider'">
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('rider') }" :href="url('rider')" :aria-current="navIsActive('rider') ? 'page' : undefined">
                                <i class="bi bi-bicycle" aria-hidden="true"></i>
                                <span>My deliveries</span>
                            </a>
                        </li>
                    </template>

                    <!-- Dispatcher links -->
                    <template v-else-if="role === 'dispatcher'">
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('dispatch') }" :href="url('dispatch')" :aria-current="navIsActive('dispatch') ? 'page' : undefined">
                                <i class="bi bi-kanban me-1" aria-hidden="true"></i>
                                <span>Dispatch board</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('orders') }" :href="url('orders')" :aria-current="navIsActive('orders') ? 'page' : undefined">
                                <i class="bi bi-box-seam me-1" aria-hidden="true"></i>
                                <span>Orders</span>
                            </a>
                        </li>
                    </template>

                    <!-- Admin links -->
                    <template v-else-if="role === 'admin'">
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: adminDashboardActive }" :href="url('admin')" :aria-current="adminDashboardActive ? 'page' : undefined">
                                <i class="bi bi-speedometer2" aria-hidden="true"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('dispatch') }" :href="url('dispatch')" :aria-current="navIsActive('dispatch') ? 'page' : undefined">
                                <i class="bi bi-kanban" aria-hidden="true"></i>
                                <span>Dispatch</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('orders') }" :href="url('orders')" :aria-current="navIsActive('orders') ? 'page' : undefined">
                                <i class="bi bi-box-seam me-1" aria-hidden="true"></i>
                                <span>Orders</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('admin/users') }" :href="url('admin/users')" :aria-current="navIsActive('admin/users') ? 'page' : undefined">
                                <i class="bi bi-people" aria-hidden="true"></i>
                                <span>Users</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('admin/riders') }" :href="url('admin/riders')" :aria-current="navIsActive('admin/riders') ? 'page' : undefined">
                                <i class="bi bi-bicycle" aria-hidden="true"></i>
                                <span>Riders</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('admin/applications') }" :href="url('admin/applications')" :aria-current="navIsActive('admin/applications') ? 'page' : undefined">
                                <i class="bi bi-person-badge" aria-hidden="true"></i>
                                <span>Applications</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: navIsActive('admin/analytics') }" :href="url('admin/analytics')" :aria-current="navIsActive('admin/analytics') ? 'page' : undefined">
                                <i class="bi bi-graph-up" aria-hidden="true"></i>
                                <span>Analytics</span>
                            </a>
                        </li>

                        <!-- More Dropdown -->
                        <li class="nav-item dropdown dropdown-more">
                            <button
                                class="nav-link dropdown-toggle btn btn-link text-decoration-none border-0"
                                :class="{ active: adminMoreActive }"
                                type="button"
                                @click.stop="isMoreOpen = !isMoreOpen"
                                :aria-expanded="isMoreOpen ? 'true' : 'false'"
                            >
                                <i class="bi bi-sliders" aria-hidden="true"></i>
                                <span>More</span>
                            </button>
                            <ul class="dropdown-menu shadow-sm" :class="{ show: isMoreOpen }">
                                <li>
                                    <a class="dropdown-item" :class="{ active: navIsActive('admin/settings') }" :href="url('admin/settings')">
                                        <i class="bi bi-gear me-2" aria-hidden="true"></i>Settings
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" :class="{ active: navIsActive('admin/service-areas') }" :href="url('admin/service-areas')">
                                        <i class="bi bi-map me-2" aria-hidden="true"></i>Service areas
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" :class="{ active: navIsActive('admin/package-reports') }" :href="url('admin/package-reports')">
                                        <i class="bi bi-box-seam me-2" aria-hidden="true"></i>Missing packages
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" :class="{ active: navIsActive('admin/command-simulator') }" :href="url('admin/command-simulator')">
                                        <i class="bi bi-terminal me-2" aria-hidden="true"></i>Command simulator
                                    </a>
                                </li>
                                <li v-if="showCourierSimulator">
                                    <a class="dropdown-item" :class="{ active: navIsActive('admin/courier-simulator') }" :href="url('admin/courier-simulator')">
                                        <i class="bi bi-truck me-2" aria-hidden="true"></i>Courier simulator
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" :class="{ active: navIsActive('admin/reports') }" :href="url('admin/reports')">
                                        <i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i>System reports
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" :class="{ active: navIsActive('admin/logs') }" :href="url('admin/logs')">
                                        <i class="bi bi-journal-code me-2" aria-hidden="true"></i>System logs
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </template>
                </ul>

                <!-- Right Nav Items / Account Menu -->
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <template v-if="role && role !== 'guest'">
                        <li v-if="role === 'customer'" class="nav-item dropdown dropdown-notifications">
                            <button
                                class="nav-link dropdown-toggle btn btn-link text-decoration-none border-0 position-relative"
                                type="button"
                                @click.stop="toggleNotifications"
                                :aria-expanded="isNotificationsOpen ? 'true' : 'false'"
                                aria-label="Notifications"
                            >
                                <i class="bi bi-bell" aria-hidden="true"></i>
                                <span v-if="unreadCount > 0" class="notifications-badge">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end shadow-sm notifications-menu" :class="{ show: isNotificationsOpen }">
                                <div class="notifications-menu__header">
                                    <span class="fw-semibold">Notifications</span>
                                    <button
                                        v-if="unreadCount > 0"
                                        type="button"
                                        class="btn btn-link btn-sm p-0 text-decoration-none"
                                        @click.stop="markAllNotificationsRead"
                                    >
                                        Mark all read
                                    </button>
                                </div>
                                <div v-if="notifications.length === 0" class="notifications-menu__empty text-muted small">
                                    No notifications yet. Delivery updates will appear here.
                                </div>
                                <ul v-else class="list-unstyled mb-0 notifications-menu__list">
                                    <li
                                        v-for="notification in notifications"
                                        :key="notification.id"
                                        class="notifications-menu__item"
                                        :class="{ 'is-unread': !notification.read_at }"
                                    >
                                        <a class="notifications-menu__link" :href="notificationTarget(notification)" @click="markNotificationRead(notification)">
                                            <span class="notifications-menu__title">
                                                <span v-if="!notification.read_at" class="notifications-menu__dot" aria-hidden="true"></span>
                                                {{ notification.title }}
                                            </span>
                                            <span class="notifications-menu__body">{{ notification.body }}</span>
                                            <span class="notifications-menu__time text-muted">{{ relativeTime(notification.created_at) }}</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item dropdown dropdown-user">
                            <button
                                class="nav-link dropdown-toggle btn btn-link text-decoration-none border-0 d-flex align-items-center gap-1"
                                type="button"
                                @click.stop="isUserOpen = !isUserOpen"
                                :aria-expanded="isUserOpen ? 'true' : 'false'"
                            >
                                <i class="bi bi-person-circle" aria-hidden="true"></i>
                                <span class="d-inline-block text-truncate user-name-truncate">{{ userDisplayName }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" :class="{ show: isUserOpen }">
                                <li>
                                    <h6 class="dropdown-header d-flex flex-column">
                                        <span>{{ userDisplayName }}</span>
                                        <small class="text-muted fw-normal text-capitalize">{{ role }}</small>
                                    </h6>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li v-if="role === 'customer'">
                                    <a class="dropdown-item" :class="{ active: navIsActive('team') }" :href="url('team')">
                                        <i class="bi bi-people me-2" aria-hidden="true"></i>Team members
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" :class="{ active: navIsActive('profile') }" :href="url('profile')">
                                        <i class="bi bi-person me-2" aria-hidden="true"></i>My profile
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form :action="url('logout')" method="post" class="m-0 p-0">
                                        <input v-if="csrfToken" type="hidden" name="csrf_token" :value="csrfToken" />
                                        <button type="submit" class="dropdown-item text-danger d-flex align-items-center">
                                            <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Sign out
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    </template>
                    <template v-else>
                        <li class="nav-item me-lg-2">
                            <a class="nav-link" :href="url('')">
                                <i class="bi bi-house me-1" aria-hidden="true"></i>
                                <span>Home</span>
                            </a>
                        </li>
                        <li class="nav-item me-lg-2">
                            <a class="nav-link" :class="{ active: navIsActive('login') }" :href="url('login')">
                                <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>
                                <span>Sign in</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary btn-sm" :href="url('register')">Create account</a>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </nav>
</template>

<style scoped>
.notifications-badge {
    position: absolute;
    top: -2px;
    right: -8px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: #dc3545;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.notifications-menu {
    width: min(360px, calc(100vw - 24px));
    max-height: 430px;
    overflow: auto;
    padding: 0;
}

.notifications-menu__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--line, #D3DDE5);
    position: sticky;
    top: 0;
    background: var(--surface, #fff);
    z-index: 1;
}

.notifications-menu__empty {
    padding: 16px;
}

.notifications-menu__item {
    border-bottom: 1px solid var(--paper, #EEF2F5);
}

.notifications-menu__item:last-child {
    border-bottom: 0;
}

.notifications-menu__item.is-unread {
    background: color-mix(in srgb, var(--route, #087F98) 7%, transparent);
}

.notifications-menu__link {
    display: grid;
    gap: 2px;
    padding: 10px 16px;
    color: inherit;
    text-decoration: none;
}

.notifications-menu__link:hover,
.notifications-menu__link:focus {
    background: var(--paper, #EEF2F5);
    text-decoration: none;
}

.notifications-menu__title {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
    font-size: var(--font-size-14, 14px);
    color: var(--ink, #172B3A);
}

.notifications-menu__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--route, #087F98);
    flex-shrink: 0;
}

.notifications-menu__body {
    font-size: var(--font-size-13, 13px);
    color: var(--ink-2, #4C6070);
}

.notifications-menu__time {
    font-size: var(--font-size-12, 12px);
}
</style>
