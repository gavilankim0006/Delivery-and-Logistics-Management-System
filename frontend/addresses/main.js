import AddressList from './pages/AddressList.vue';
import AddressForm from './pages/AddressForm.vue';
import { mountPage } from '../shared/mountPage.js';

export function mount() {
    const root = document.getElementById('vue-address-root');
    if (!root) return;
    let props = {};
    try { props = JSON.parse(root.dataset.props || '{}'); } catch { props = {}; }
    const component = root.dataset.page === 'list' ? AddressList : AddressForm;
    mountPage(root, component, { ...props, baseUrl: root.dataset.baseUrl, csrfToken: root.dataset.csrfToken });
}
