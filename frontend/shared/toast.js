import { ref } from 'vue';

export const toasts = ref([]);

let toastId = 0;

export function addToast(message, type = 'info', duration = 4500) {
    if (!message) return null;
    const id = ++toastId;
    const item = {
        id,
        message,
        type, // 'success' | 'error' | 'warning' | 'info'
        duration
    };
    toasts.value.push(item);

    if (duration > 0) {
        setTimeout(() => {
            removeToast(id);
        }, duration);
    }
    return id;
}

export function removeToast(id) {
    const idx = toasts.value.findIndex(t => t.id === id);
    if (idx !== -1) {
        toasts.value.splice(idx, 1);
    }
}

export const toast = {
    success(msg, duration) { return addToast(msg, 'success', duration); },
    error(msg, duration = 6000) { return addToast(msg, 'error', duration); },
    warning(msg, duration) { return addToast(msg, 'warning', duration); },
    info(msg, duration) { return addToast(msg, 'info', duration); },
    remove: removeToast
};

export default toast;
