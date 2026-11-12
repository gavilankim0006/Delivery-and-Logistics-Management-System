<script setup>
import DispatchControls from './DispatchControls.vue';
import DispatchBoard from './DispatchBoard.vue';

const props = defineProps({
    controlsProps: { type: Object, default: () => ({}) },
    boardProps: { type: Object, default: () => ({}) },
    baseUrl: { type: String, default: '/' },
    csrfToken: { type: String, default: '' },
    csrfName: { type: String, default: 'csrf_token' },
    flashes: { type: Array, default: () => [] }
});
</script>

<template>
    <div class="container-fluid page-shell dispatch-page-shell py-3">
        <div v-for="(flash, index) in flashes" :key="index" class="alert alert-dismissible fade show mb-3" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
            <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
            {{ flash.message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <DispatchControls
            :initial="controlsProps"
            :base-url="baseUrl"
            :csrf-token="csrfToken"
            :csrf-name="csrfName"
        />

        <section class="surface dispatch-board-card mt-3" aria-label="Dispatch orders" data-dispatch-vue-board>
            <DispatchBoard
                :data-url="boardProps.dataUrl"
                :base-url="baseUrl"
                :csrf-token="csrfToken"
                :fulfillment-actions="boardProps.fulfillmentActions"
                :available-riders="boardProps.availableRiders"
            />
        </section>
    </div>
</template>
