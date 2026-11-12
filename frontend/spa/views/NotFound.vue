<template>
  <section class="ds-page">
    <div class="surface ds-empty-state">
      <i class="bi bi-signpost-split" aria-hidden="true"></i>
      <p class="text-muted mb-1">404 · Not found</p>
      <h1 class="ds-empty-state__title">We couldn't find that page</h1>
      <p class="ds-empty-state__message">The workspace route <code>{{ route }}</code> isn't available.</p>
      <a class="btn btn-primary" href="#/">Return to overview</a>
    </div>
  </section>
</template>

<script setup>
defineProps({ route: { type: String, default: '/' } });
</script>
