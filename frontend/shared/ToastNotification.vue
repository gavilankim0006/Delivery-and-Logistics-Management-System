<script setup>
import { toasts, removeToast } from './toast.js';

function iconClass(type) {
    switch (type) {
        case 'success':
            return 'bi bi-check-circle-fill text-success';
        case 'error':
            return 'bi bi-exclamation-triangle-fill text-danger';
        case 'warning':
            return 'bi bi-exclamation-circle-fill text-warning';
        default:
            return 'bi bi-info-circle-fill text-info';
    }
}

function alertClass(type) {
    switch (type) {
        case 'success':
            return 'border-success';
        case 'error':
            return 'border-danger';
        case 'warning':
            return 'border-warning';
        default:
            return 'border-info';
    }
}
</script>

<template>
    <div
        class="toast-stack toast-container position-fixed top-0 end-0 p-3"
        aria-live="polite"
        aria-atomic="true"
    >
        <transition-group name="toast-fade">
            <div
                v-for="item in toasts"
                :key="item.id"
                class="toast toast-item show shadow mb-2 bg-white rounded-3 border-start border-4"
                :class="alertClass(item.type)"
                role="alert"
                aria-live="assertive"
                aria-atomic="true"
            >
                <div class="d-flex align-items-center p-3">
                    <i :class="[iconClass(item.type), 'fs-5 me-2 flex-shrink-0']" aria-hidden="true"></i>
                    <div class="toast-body toast-message p-0 flex-grow-1 text-dark">
                        {{ item.message }}
                    </div>
                    <button
                        type="button"
                        class="btn-close ms-2 me-0 p-1 flex-shrink-0"
                        aria-label="Close"
                        @click="removeToast(item.id)"
                    ></button>
                </div>
            </div>
        </transition-group>
    </div>
</template>

<style scoped>
.toast-stack {
    z-index: 1085;
    width: 100%;
    max-width: 420px;
    pointer-events: none;
}

.toast-item {
    pointer-events: auto;
}

.toast-message {
    font-size: 0.925rem;
    line-height: 1.4;
}

.toast-fade-enter-active,
.toast-fade-leave-active {
    transition: all 0.25s ease-out;
}
.toast-fade-enter-from {
    opacity: 0;
    transform: translateY(-16px) scale(0.96);
}
.toast-fade-leave-to {
    opacity: 0;
    transform: translateX(30px);
}
</style>
