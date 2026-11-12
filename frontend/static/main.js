import StaticPage from './pages/StaticPage.vue';
import { mountPage } from '../shared/mountPage.js';

const root = document.getElementById('static-page-vue-root');
if (root) {
    const kind = root.dataset.pageKind || 'not-found';
    const baseUrl = root.dataset.baseUrl || '/';
    mountPage(root, StaticPage, { kind, baseUrl });
}
