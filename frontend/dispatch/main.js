import { createApp } from 'vue';
import DispatchBoard from './DispatchBoard.vue';
import DispatchControls from './DispatchControls.vue';

const controlsRoot = document.getElementById('dispatch-controls-vue-root');
if (controlsRoot) {
    let initial = {};
    try { initial = JSON.parse(controlsRoot.dataset.props || '{}'); } catch { initial = {}; }
    createApp(DispatchControls, {
        initial,
        baseUrl: controlsRoot.dataset.baseUrl || '',
        csrfToken: controlsRoot.dataset.csrfToken || '',
        csrfName: controlsRoot.dataset.csrfName || ''
    }).mount(controlsRoot);
}

const root = document.getElementById('dispatch-vue-root');

if (root) {
    createApp(DispatchBoard, {
        dataUrl: root.dataset.dataUrl,
        baseUrl: root.dataset.baseUrl,
        csrfToken: root.dataset.csrfToken,
        fulfillmentActions: root.dataset.fulfillmentActions === 'true',
        availableRiders: (() => {
            try {
                const riders = JSON.parse(root.dataset.availableRiders || '[]');
                return Array.isArray(riders) ? riders : [];
            } catch {
                return [];
            }
        })()
    }).mount(root);
}
