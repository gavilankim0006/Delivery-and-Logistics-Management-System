import DeliveryControlShow from './pages/DeliveryControlShow.vue';
import { mountPage } from '../shared/mountPage.js';

const root = document.getElementById('vue-delivery-control-root');
if (root) {
    let props = {};
    try {
        props = JSON.parse(root.dataset.props || '{}');
    } catch {
        props = {};
    }
    mountPage(root, DeliveryControlShow, props);
}
