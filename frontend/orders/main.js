import { createApp } from 'vue';
import OrderList from './pages/OrderList.vue';
import OrderForm from './pages/OrderForm.vue';
import OrderDetail from './pages/OrderDetail.vue';

const root = document.getElementById('vue-orders-root');
if (root) {
    let props = {};
    try { props = JSON.parse(root.dataset.props || '{}'); } catch { props = {}; }
    const component = root.dataset.page === 'list' ? OrderList : root.dataset.page === 'form' ? OrderForm : root.dataset.page === 'detail' ? OrderDetail : null;
    if (component) createApp(component, props).mount(root);
}
