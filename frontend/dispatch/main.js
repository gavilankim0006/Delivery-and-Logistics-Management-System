import { createApp } from 'vue';
import DispatchBoard from './DispatchBoard.vue';

const root = document.getElementById('dispatch-vue-root');

if (root) {
    createApp(DispatchBoard, {
        dataUrl: root.dataset.dataUrl,
        baseUrl: root.dataset.baseUrl,
        csrfToken: root.dataset.csrfToken
    }).mount(root);
}
