import { createApp } from 'vue';
import MessengerApp from './MessengerApp.vue';

window.__VUE_MESSENGER_MOUNTED__ = true;
if (window.PackageMessenger && typeof window.PackageMessenger.stopPolling === 'function') {
    window.PackageMessenger.stopPolling();
}

const root = document.getElementById('messenger-vue-root');
if (root) {
    let props = {};
    try {
        props = JSON.parse(root.dataset.props || '{}');
    } catch (err) {
        console.error('Failed to parse messenger dataset props:', err);
        props = {};
    }
    createApp(MessengerApp, props).mount(root);
}
