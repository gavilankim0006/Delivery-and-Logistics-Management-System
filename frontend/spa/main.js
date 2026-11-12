import { createApp } from 'vue';
import App from './App.vue';

const root = document.getElementById('spa-root') || document.createElement('div');
if (!root.id) {
    root.id = 'spa-root';
    document.body.appendChild(root);
}

const app = createApp(App, {
    baseUrl: root.dataset.baseUrl || '/',
    csrfToken: root.dataset.csrfToken || ''
});

app.mount(root);
