<template>
  <AppLayout
    :user="shell.user"
    :role="shell.role"
    :base-url="baseUrl"
    :current-uri="currentUri"
    :csrf-token="csrfToken"
    :flashes="shell.flashes"
    :show-courier-simulator="shell.showCourierSimulator"
  >
    <div class="page-shell">
      <nav class="workspace-nav" aria-label="Vue workspace">
        <a
          v-for="item in navItems"
          :key="item.path"
          class="workspace-nav__link"
          :class="{ 'is-active': isActive(item) }"
          :href="`#${item.path}`"
          :aria-current="isActive(item) ? 'page' : undefined"
        >
          <i class="bi" :class="item.icon" aria-hidden="true"></i>
          <span>{{ item.label }}</span>
        </a>
      </nav>
    </div>

    <div :class="isFullWidth ? '' : 'page-shell'">
      <HomeView v-if="route === '/'" :base-url="baseUrl" />
      <AdminDashboardView v-else-if="route === '/admin'" :base-url="baseUrl" :csrf-token="csrfToken" />
      <AdminUsersView v-else-if="route === '/admin/users'" :base-url="baseUrl" :csrf-token="csrfToken" />
      <AdminRidersView v-else-if="route === '/admin/riders'" :base-url="baseUrl" :csrf-token="csrfToken" />
      <AdminAnalyticsView v-else-if="route === '/admin/analytics'" :base-url="baseUrl" :csrf-token="csrfToken" />
      <DispatchView v-else-if="route === '/dispatch'" :base-url="baseUrl" :csrf-token="csrfToken" />
      <OrdersListView v-else-if="route === '/orders'" :base-url="baseUrl" :csrf-token="csrfToken" />
      <OrderDetailView v-else-if="route.startsWith('/orders/')" :order-id="orderId" :base-url="baseUrl" :csrf-token="csrfToken" />
      <AddressesView v-else-if="route === '/addresses'" :base-url="baseUrl" :csrf-token="csrfToken" />
      <RiderView v-else-if="route === '/rider' || route.startsWith('/rider/')" :base-url="baseUrl" :csrf-token="csrfToken" />
      <MessengerView v-else-if="route === '/messenger'" :base-url="baseUrl" :csrf-token="csrfToken" />
      <ProfileView v-else-if="route === '/profile'" :base-url="baseUrl" :csrf-token="csrfToken" />
      <NotFound v-else :route="route" />
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';

import AppLayout from '../shell/AppLayout.vue';
import HomeView from './views/HomeView.vue';
import AdminDashboardView from './views/AdminDashboardView.vue';
import AdminUsersView from './views/AdminUsersView.vue';
import AdminRidersView from './views/AdminRidersView.vue';
import AdminAnalyticsView from './views/AdminAnalyticsView.vue';
import DispatchView from './views/DispatchView.vue';
import AddressesView from './views/AddressesView.vue';
import OrdersListView from './views/OrdersListView.vue';
import OrderDetailView from './views/OrderDetailView.vue';
import RiderView from './views/RiderView.vue';
import ProfileView from './views/ProfileView.vue';
import MessengerView from './views/MessengerView.vue';
import NotFound from './views/NotFound.vue';

defineProps({
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' }
});

const shell = (typeof window !== 'undefined' && window.__APP_SHELL__) ? window.__APP_SHELL__ : {};
const currentUri = (typeof window !== 'undefined' ? window.location.pathname : '/') || '/';

const navItems = [
  { path: '/', label: 'Overview', icon: 'bi-grid-1x2', exact: true },
  { path: '/admin', label: 'Admin', icon: 'bi-speedometer2', exact: true },
  { path: '/admin/users', label: 'Users', icon: 'bi-people' },
  { path: '/admin/riders', label: 'Riders', icon: 'bi-bicycle' },
  { path: '/admin/analytics', label: 'Analytics', icon: 'bi-graph-up' },
  { path: '/dispatch', label: 'Dispatch', icon: 'bi-kanban' },
  { path: '/orders', label: 'Orders', icon: 'bi-box-seam' },
  { path: '/addresses', label: 'Addresses', icon: 'bi-geo-alt' },
  { path: '/rider', label: 'Deliveries', icon: 'bi-truck' },
  { path: '/messenger', label: 'Messenger', icon: 'bi-chat-dots' },
  { path: '/profile', label: 'Profile', icon: 'bi-person' }
];

const route = ref(window.location.hash.replace(/^#/, '') || '/');

const orderId = computed(() => {
  const parts = route.value.split('/').filter(Boolean);
  return Number(parts[1]) || 0;
});

const isFullWidth = computed(() => route.value === '/dispatch' || route.value === '/messenger');

function isActive(item) {
  const path = route.value || '/';
  if (item.exact) return path === item.path;
  return path === item.path || path.startsWith(item.path + '/');
}

function updateRoute() {
  route.value = window.location.hash.replace(/^#/, '') || '/';
  if (typeof window !== 'undefined') window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => window.addEventListener('hashchange', updateRoute));
onUnmounted(() => window.removeEventListener('hashchange', updateRoute));
</script>
