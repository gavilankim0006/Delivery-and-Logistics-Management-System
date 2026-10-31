import { createApp } from 'vue';
import AppNavigation from './Navigation.vue';
import AppFooter from './Footer.vue';

for (const [id, component, propName] of [
    ['vue-navigation-root', AppNavigation, 'navigation'],
    ['vue-footer-root', AppFooter, 'footer']
]) {
    const root = document.getElementById(id);
    if (!root) continue;
    let props = {};
    try {
        props = JSON.parse(root.dataset.props || '{}');
    } catch {
        props = {};
    }
    createApp(component, props[propName] || {}).mount(root);
}
