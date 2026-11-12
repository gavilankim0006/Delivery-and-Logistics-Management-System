<template>
  <div>
    <div v-if="loading" class="container py-5 text-center"><div class="spinner-border" role="status"></div></div>
    <div v-else-if="error" class="container py-4">
      <div class="ds-notice ds-notice--danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ error }}
      </div>
    </div>
    <MessengerApp
      v-else
      :customer-id="propsData.customerId"
      :csrf-token="propsData.csrfToken"
      :base-url="baseUrl"
      :conversations="propsData.conversations"
      :active-handoff="propsData.activeHandoff"
      :messages="propsData.messages"
      :eligible-orders="propsData.eligibleOrders"
      :preset-order-id="propsData.presetOrderId"
    />
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import MessengerApp from '../../messenger/MessengerApp.vue';

const props = defineProps({
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' }
});

const loading = ref(true);
const error = ref('');
const propsData = reactive({
  customerId: 0,
  csrfToken: props.csrfToken,
  conversations: [],
  activeHandoff: null,
  messages: [],
  eligibleOrders: [],
  presetOrderId: 0
});

let messengerCss = null;

onMounted(async () => {
  messengerCss = document.createElement('link');
  messengerCss.rel = 'stylesheet';
  messengerCss.href = `${props.baseUrl}css/messenger.css`;
  document.head.appendChild(messengerCss);

  try {
    const response = await fetch(`${props.baseUrl}package-workflow?format=json&ajax=1`, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.error || 'Could not load messenger.');
    }
    propsData.customerId = result.customer_id || 0;
    propsData.csrfToken = result.csrf_token || props.csrfToken;
    propsData.conversations = result.conversations || [];
    propsData.activeHandoff = result.active_handoff || null;
    propsData.messages = result.messages || [];
    propsData.eligibleOrders = result.eligible_orders || [];
    propsData.presetOrderId = result.preset_order_id || 0;
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
});

onBeforeUnmount(() => {
  if (messengerCss && messengerCss.parentNode) messengerCss.parentNode.removeChild(messengerCss);
});
</script>
