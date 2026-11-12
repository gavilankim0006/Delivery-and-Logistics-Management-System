import { createApp } from 'vue';
import ServerMarkupPage from './pages/ServerMarkupPage.vue';

const root = document.getElementById('server-markup-vue-root');
if (root) {
    const markup = window.__SERVER_MARKUP__ || '';
    createApp(ServerMarkupPage, { markup, baseUrl: root.dataset.baseUrl || '/' }).mount(root);
}