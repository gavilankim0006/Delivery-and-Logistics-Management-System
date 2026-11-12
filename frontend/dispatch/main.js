import { createApp } from 'vue';
import DispatchBoard from './DispatchBoard.vue';
import DispatchControls from './DispatchControls.vue';
import DispatchPage from './DispatchPage.vue';
import { mountPage } from '../shared/mountPage.js';

const pageRoot = document.getElementById('dispatch-page-vue-root');
if (pageRoot) {
    let props = {};
    try {
        props = JSON.parse(pageRoot.dataset.props || '{}');
    } catch {
        props = {};
    }
    mountPage(pageRoot, DispatchPage, {
        controlsProps: props.controls || {},
        boardProps: props.board || {},
        baseUrl: pageRoot.dataset.baseUrl || props.baseUrl || '/',
        csrfToken: pageRoot.dataset.csrfToken || props.csrfToken || '',
        csrfName: pageRoot.dataset.csrfName || 'csrf_token',
        flashes: props.flashes || []
    });
} else {
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
}
