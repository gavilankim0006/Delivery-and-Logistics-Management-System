<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Navigation from './Navigation.vue';
import Footer from './Footer.vue';
import ToastNotification from '../shared/ToastNotification.vue';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    role: { type: String, default: 'guest' },
    baseUrl: { type: String, default: '/' },
    currentUri: { type: String, default: '' },
    csrfToken: { type: String, default: '' },
    flashes: { type: Object, default: () => ({}) },
    showCourierSimulator: { type: Boolean, default: false },
    hideNav: { type: Boolean, default: false },
    hideFooter: { type: Boolean, default: false }
});

// Server flash messages show as a single full-width banner. They are copied
// into local state so the banner can fade out after a few seconds without
// re-appearing (pages call toast.* directly for action feedback).
const BANNER_DURATION = 5000;

const activeFlashes = ref({ ...(props.flashes || {}) });
const hasFlashes = computed(() => {
    return !!(activeFlashes.value.success || activeFlashes.value.error || activeFlashes.value.warning);
});

let bannerTimer = null;

onMounted(() => {
    if (!hasFlashes.value) return;
    bannerTimer = setTimeout(() => {
        activeFlashes.value = {};
    }, BANNER_DURATION);
});

onBeforeUnmount(() => {
    clearTimeout(bannerTimer);
});
</script>

<template>
    <div class="app-layout d-flex flex-column min-vh-100">
        <Navigation
            v-if="!hideNav"
            :user="user"
            :role="role"
            :base-url="baseUrl"
            :current-uri="currentUri"
            :csrf-token="csrfToken"
            :show-courier-simulator="showCourierSimulator"
        />

        <!-- Reactive Toasts -->
        <ToastNotification />

        <!-- Server flash banner (auto-dismisses) -->
        <Transition name="flash-banner">
            <div v-if="hasFlashes" class="container page-shell" aria-live="polite">
                <div class="mt-3">
                    <div v-if="activeFlashes.success" class="alert alert-success d-flex align-items-center gap-2" role="status">
                        <i class="bi bi-check-circle-fill flex-shrink-0" aria-hidden="true"></i>
                        <div>{{ activeFlashes.success }}</div>
                    </div>
                    <div v-if="activeFlashes.error" class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0" aria-hidden="true"></i>
                        <div>{{ activeFlashes.error }}</div>
                    </div>
                    <div v-if="activeFlashes.warning" class="alert alert-warning d-flex align-items-center gap-2" role="status">
                        <i class="bi bi-info-circle-fill flex-shrink-0" aria-hidden="true"></i>
                        <div>{{ activeFlashes.warning }}</div>
                    </div>
                </div>
            </div>
        </Transition>

        <main class="app-main flex-grow-1" id="main-content" tabindex="-1">
            <slot />
        </main>

        <Footer
            v-if="!hideFooter"
            :role="role"
            :base-url="baseUrl"
            :current-uri="currentUri"
        />
    </div>
</template>

<style scoped>
.flash-banner-enter-active,
.flash-banner-leave-active {
    transition: opacity 0.35s ease, transform 0.35s ease;
}

.flash-banner-enter-from,
.flash-banner-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
