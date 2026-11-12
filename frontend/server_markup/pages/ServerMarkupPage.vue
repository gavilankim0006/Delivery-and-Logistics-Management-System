<template>
  <div v-html="markup"></div>
</template>

<script setup>
defineProps({
  markup: { type: String, default: '' },
  baseUrl: { type: String, default: '/' },
});
</script>