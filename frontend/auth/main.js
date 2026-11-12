import LoginPage from './pages/LoginPage.vue';
import RegisterPage from './pages/RegisterPage.vue';
import ProfilePage from './pages/ProfilePage.vue';
import ForgotPasswordPage from './pages/ForgotPasswordPage.vue';
import ResetPasswordPage from './pages/ResetPasswordPage.vue';
import { mount as mountAddresses } from '../addresses/main.js';
import { mountPage } from '../shared/mountPage.js';

const root = document.getElementById('vue-auth-root');
if (document.getElementById('vue-address-root')) mountAddresses();

if (root) {
    const pages = {
        login: LoginPage,
        register: RegisterPage,
        profile: ProfilePage,
        'forgot-password': ForgotPasswordPage,
        'reset-password': ResetPasswordPage
    };
    const page = pages[root.dataset.page];
    // Focused auth screens rely on their own centered card, so hide the app shell.
    const standalone = ['login', 'register', 'forgot-password', 'reset-password'];
    const focusMode = standalone.includes(root.dataset.page);

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

        mountPage(root, page, {
            baseUrl: root.dataset.baseUrl,
            csrfToken: root.dataset.csrfToken,
            errorMessage: root.dataset.errorMessage || '',
            successMessage: root.dataset.successMessage || '',
            vapidKey: root.dataset.vapidKey || '',
            oldEmail: root.dataset.oldEmail || '',
            loginContext: root.dataset.loginContext || 'customer',
            oldRegister,
            user,
            token: root.dataset.token || '',
            tokenValid: root.dataset.tokenValid === '1'
        }, { hideNav: focusMode, hideFooter: focusMode });
    }
}
