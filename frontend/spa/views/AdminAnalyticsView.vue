<template>
  <div class="analytics-page">
    <Analytics v-if="data" v-bind="data" />
    <div v-else-if="loading" class="text-center py-5"><div class="spinner-border" role="status"></div></div>
    <div v-else-if="error" class="ds-notice ds-notice--danger" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ error }}
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import Analytics from '../../admin/pages/Analytics.vue';

const props = defineProps({
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' }
});

const loading = ref(true);
const error = ref('');
const data = ref(null);

async function load(from, to) {
  loading.value = true;
  error.value = '';
  try {
    const params = new URLSearchParams();
    if (from) params.set('from', from);
    if (to) params.set('to', to);
    const response = await fetch(`${props.baseUrl}admin/analytics/data?${params.toString()}`, { credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Could not load analytics.');
    const payload = result.data;
    data.value = {
      filters: payload.filters || { from: '', to: '' },
      summary: payload.analytics?.summary || {},
      daily: payload.analytics?.daily || [],
      outcomes: payload.analytics?.outcomes || [],
      links: payload.links || { analytics: `${props.baseUrl}admin/analytics`, reports: `${props.baseUrl}admin/reports` },
      error: payload.error || ''
    };
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

load();
</script>