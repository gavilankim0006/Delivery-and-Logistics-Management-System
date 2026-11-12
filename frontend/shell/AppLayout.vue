<script setup>
import { computed, onMounted } from 'vue';
import Navigation from './Navigation.vue';
import Footer from './Footer.vue';
import ToastNotification from '../shared/ToastNotification.vue';
import { toast } from '../shared/toast.js';

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

const hasFlashes = computed(() => {
    return !!(props.flashes?.success || props.flashes?.error || props.flashes?.warning);
});

onMounted(() => {
    if (props.flashes?.success) {
        toast.success(props.flashes.success);
    }
    if (props.flashes?.error) {
        toast.error(props.flashes.error);
    }
    if (props.flashes?.warning) {
        toast.warning(props.flashes.warning);
    }
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

        <!-- Static Fallback Flash alerts -->
        <div v-if="hasFlashes" class="container page-shell" aria-live="polite">
            <div class="mt-3">
                <div v-if="flashes.success" class="alert alert-success d-flex align-items-center gap-2" role="status">
                    <i class="bi bi-check-circle-fill flex-shrink-0" aria-hidden="true"></i>
                    <div>{{ flashes.success }}</div>
                </div>
                <div v-if="flashes.error" class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0" aria-hidden="true"></i>
                    <div>{{ flashes.error }}</div>
                </div>
                <div v-if="flashes.warning" class="alert alert-warning d-flex align-items-center gap-2" role="status">
                    <i class="bi bi-info-circle-fill flex-shrink-0" aria-hidden="true"></i>
                    <div>{{ flashes.warning }}</div>
                </div>
            </div>
        </div>

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
