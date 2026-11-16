import CustomerDashboard from './pages/CustomerDashboard.vue';
import TeamMembers from './pages/TeamMembers.vue';
import { mountPage } from '../shared/mountPage.js';

const root = document.getElementById('vue-dashboard-root') || document.getElementById('vue-team-root');
if (root) {
    let props = {};
    try { props = JSON.parse(root.dataset.props || '{}'); } catch { props = {}; }
    const component = root.dataset.page === 'team' ? TeamMembers : CustomerDashboard;
    mountPage(root, component, props);
}
