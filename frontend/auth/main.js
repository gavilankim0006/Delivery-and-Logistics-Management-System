import { createApp } from 'vue';
import LoginPage from './pages/LoginPage.vue';
import RegisterPage from './pages/RegisterPage.vue';
import ProfilePage from './pages/ProfilePage.vue';
import { mount as mountAddresses } from '../addresses/main.js';

const root = document.getElementById('vue-auth-root');
if (document.getElementById('vue-address-root')) mountAddresses();

if (root) {
    const pages = {
        login: LoginPage,
        register: RegisterPage,
        profile: ProfilePage
    };
    const page = pages[root.dataset.page];

    if (page) {
        let user = {};
        try {
            user = JSON.parse(root.dataset.user || '{}');
        } catch {
            user = {};
        }

        let oldRegister = {};
        try {
            oldRegister = JSON.parse(root.dataset.oldRegister || '{}');
        } catch {
            oldRegister = {};
        }

        createApp(page, {
            baseUrl: root.dataset.baseUrl,
            csrfToken: root.dataset.csrfToken,
            errorMessage: root.dataset.errorMessage || '',
            successMessage: root.dataset.successMessage || '',
            vapidKey: root.dataset.vapidKey || '',
            oldEmail: root.dataset.oldEmail || '',
            loginContext: root.dataset.loginContext || 'customer',
            oldRegister,
            user
        }).mount(root);
    }
}
