import { createApp, h } from 'vue';
import AppLayout from '../shell/AppLayout.vue';

export function mountPage(target, Component, componentProps = {}, options = {}) {
    const root = typeof target === 'string' ? document.getElementById(target) : target;
    if (!root) return null;

    let shellData = {};
    if (typeof window !== 'undefined' && window.__APP_SHELL__) {
        shellData = { ...window.__APP_SHELL__ };
    }

    if (root.dataset.baseUrl) shellData.baseUrl = root.dataset.baseUrl;
    if (root.dataset.csrfToken) shellData.csrfToken = root.dataset.csrfToken;

    const layoutProps = {
        ...shellData,
        hideNav: options.hideNav ?? false,
        hideFooter: options.hideFooter ?? false,
        ...options.shell
    };

    const app = createApp({
        render() {
            return h(AppLayout, layoutProps, () => [h(Component, componentProps)]);
        }
    });

    app.mount(root);
    return app;
}
