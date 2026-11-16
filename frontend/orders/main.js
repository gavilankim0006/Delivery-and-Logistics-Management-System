import OrderList from './pages/OrderList.vue';
import OrderForm from './pages/OrderForm.vue';
import OrderDetail from './pages/OrderDetail.vue';
import TemplateList from './pages/TemplateList.vue';
import { mountPage } from '../shared/mountPage.js';

const root = document.getElementById('vue-orders-root') || document.getElementById('vue-order-list-root') || document.getElementById('vue-templates-root');
if (root) {
    let props = {};
    try { props = JSON.parse(root.dataset.props || '{}'); } catch { props = {}; }
    const page = root.dataset.page;
    const component = page === 'list' ? OrderList
        : page === 'form' ? OrderForm
        : page === 'detail' ? OrderDetail
        : page === 'templates' ? TemplateList
        : null;
    if (component) mountPage(root, component, props);
}
