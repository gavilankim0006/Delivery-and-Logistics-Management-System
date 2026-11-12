<template>
  <div class="dispatch-page-shell">
    <DispatchControls
      :initial="{}"
      :base-url="baseUrl"
      :csrf-token="csrfToken"
      csrf-name="csrf_token"
    />
    <DispatchBoard
      :data-url="`${baseUrl}dispatch/data`"
      :base-url="baseUrl"
      :csrf-token="csrfToken"
      :available-riders="[]"
    />
  </div>
</template>

<script setup>
import DispatchControls from '../../dispatch/DispatchControls.vue';
import DispatchBoard from '../../dispatch/DispatchBoard.vue';

defineProps({
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' }
});
</script>
