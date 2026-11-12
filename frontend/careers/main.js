import CareersApply from './pages/CareersApply.vue';
import { mountPage } from '../shared/mountPage.js';

const root = document.getElementById('vue-careers-root');
if (root) {
    let props = {};
    try {
        props = JSON.parse(root.dataset.props || '{}');
    } catch {
        props = {};
    }
    mountPage(root, CareersApply, props);
}
