<script setup>
import { computed } from 'vue';

const props = defineProps({
    year: { type: Number, default: () => new Date().getFullYear() },
    title: { type: String, default: 'Dispatch' },
    description: { type: String, default: 'Delivery and logistics management' },
    role: { type: String, default: 'guest' },
    baseUrl: { type: String, default: '/' },
    currentUri: { type: String, default: '' }
});

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

const isNewOrderActive = computed(() => navIsActive('orders/new'));
const isOrdersActive = computed(() => (navIsActive('orders') && !isNewOrderActive.value) || currentPath.value === '/' || currentPath.value.endsWith('/orders'));
const isMessagesActive = computed(() => navIsActive('package-workflow'));
const isAddressesActive = computed(() => navIsActive('addresses'));
const isProfileActive = computed(() => navIsActive('profile'));
</script>

<template>
    <footer class="app-footer mt-auto">
        <div class="container page-shell d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>&copy; {{ year }} {{ title }}</span>
            <span>{{ description }}</span>
        </div>
    </footer>

    <!-- Customer Mobile Bottom Navigation -->
    <nav v-if="role === 'customer'" class="customer-bottom-nav d-md-none" aria-label="Customer mobile navigation">
        <a :href="url('orders')" class="customer-bottom-nav__item" :class="{ 'is-active': isOrdersActive }" :aria-current="isOrdersActive ? 'page' : undefined">
            <i class="bi customer-bottom-nav__icon" :class="isOrdersActive ? 'bi-box-seam-fill' : 'bi-box-seam'" aria-hidden="true"></i>
            <span class="customer-bottom-nav__label">Orders</span>
        </a>
        <a :href="url('package-workflow')" class="customer-bottom-nav__item" :class="{ 'is-active': isMessagesActive }" :aria-current="isMessagesActive ? 'page' : undefined">
            <i class="bi customer-bottom-nav__icon" :class="isMessagesActive ? 'bi-chat-dots-fill' : 'bi-chat-dots'" aria-hidden="true"></i>
            <span class="customer-bottom-nav__label">Messages</span>
        </a>
        <a :href="url('orders/new')" class="customer-bottom-nav__item customer-bottom-nav__item--action" :class="{ 'is-active': isNewOrderActive }" :aria-current="isNewOrderActive ? 'page' : undefined" aria-label="Create new order">
            <div class="customer-bottom-nav__action-btn">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
            </div>
            <span class="customer-bottom-nav__label">New</span>
        </a>
        <a :href="url('addresses')" class="customer-bottom-nav__item" :class="{ 'is-active': isAddressesActive }" :aria-current="isAddressesActive ? 'page' : undefined">
            <i class="bi customer-bottom-nav__icon" :class="isAddressesActive ? 'bi-geo-alt-fill' : 'bi-geo-alt'" aria-hidden="true"></i>
            <span class="customer-bottom-nav__label">Addresses</span>
        </a>
        <a :href="url('profile')" class="customer-bottom-nav__item" :class="{ 'is-active': isProfileActive }" :aria-current="isProfileActive ? 'page' : undefined">
            <i class="bi customer-bottom-nav__icon" :class="isProfileActive ? 'bi-person-fill' : 'bi-person'" aria-hidden="true"></i>
            <span class="customer-bottom-nav__label">Account</span>
        </a>
    </nav>
</template>

<style scoped>
.customer-bottom-nav {
    display: none;
}
@media (min-width: 768px) {
    .customer-bottom-nav {
        display: none !important;
    }
}
</style>
