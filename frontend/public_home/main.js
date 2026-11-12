import PublicHome from './pages/PublicHome.vue';
import { mountPage } from '../shared/mountPage.js';

const root = document.getElementById('public-home-vue-root');
if (root) {
    let props = {};
    try { props = JSON.parse(root.dataset.props || '{}'); } catch { props = {}; }
    mountPage(root, PublicHome, props);
}
