import { createApp } from 'vue';
import App from './App.vue';
import { createAppRouter } from './router';

const root = document.getElementById('app');
if (root) {
    const initialState = window.__INITIAL_STATE__ || {};
    const app = createApp(App);
    app.provide('initialState', initialState);
    app.use(createAppRouter());
    app.mount(root);
}