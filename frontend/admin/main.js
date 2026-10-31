import { createApp } from 'vue';
import AdminDashboard from './pages/AdminDashboard.vue';

const pages = { dashboard: AdminDashboard };
const root = document.getElementById('vue-admin-root');
if (root) {
    const component = pages[root.dataset.page];
    if (component) {
        let props = {};
        try { props = JSON.parse(root.dataset.props || '{}'); } catch { props = {}; }
        createApp(component, props).mount(root);
    }
}
