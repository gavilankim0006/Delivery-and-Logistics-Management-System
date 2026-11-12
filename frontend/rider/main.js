import RiderDeliveries from './pages/RiderDeliveries.vue';
import RiderDelivery from './pages/RiderDelivery.vue';
import { mountPage } from '../shared/mountPage.js';

const root = document.getElementById('vue-rider-root');
if (root) {
    let props = {};
    try { props = JSON.parse(root.dataset.props || '{}'); } catch { props = {}; }
    const component = root.dataset.page === 'list' ? RiderDeliveries : root.dataset.page === 'show' ? RiderDelivery : null;
    if (component) mountPage(root, component, props);
}
