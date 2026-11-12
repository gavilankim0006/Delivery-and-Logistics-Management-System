import AdminDashboard from './pages/AdminDashboard.vue';
import Analytics from './pages/Analytics.vue';
import Logs from './pages/Logs.vue';
import PackageReports from './pages/PackageReports.vue';
import Settings from './pages/Settings.vue';
import Reports from './pages/Reports.vue';
import ServiceAreas from './pages/ServiceAreas.vue';
import JobApplications from './pages/JobApplications.vue';
import CourierSimulator from './pages/CourierSimulator.vue';
import CommandSimulator from './pages/CommandSimulator.vue';
import UserShow from './pages/UserShow.vue';
import AdminUsers from './pages/AdminUsers.vue';
import AdminRiders from './pages/AdminRiders.vue';
import { mountPage } from '../shared/mountPage.js';

const pages = {
    dashboard: AdminDashboard,
    analytics: Analytics,
    logs: Logs,
    'package-reports': PackageReports,
    settings: Settings,
    reports: Reports,
    'service-areas': ServiceAreas,
    'service-area': ServiceAreas,
    applications: JobApplications,
    'job-applications': JobApplications,
    'job-application': JobApplications,
    application: JobApplications,
    'courier-simulator': CourierSimulator,
    'command-simulator': CommandSimulator,
    'user-show': UserShow,
    'user-detail': UserShow,
    users: AdminUsers,
    'user-list': AdminUsers,
    riders: AdminRiders,
    'rider-list': AdminRiders,
    'user-details': UserShow,
    'user': UserShow,
    show: UserShow,
    detail: UserShow
};

const root = document.getElementById('vue-admin-root');
if (root) {
    const component = pages[root.dataset.page];
    if (component) {
        let props = {};
        try { props = JSON.parse(root.dataset.props || '{}'); } catch { props = {}; }
        mountPage(root, component, props);
    }
}
