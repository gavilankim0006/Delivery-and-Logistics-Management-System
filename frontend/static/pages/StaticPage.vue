<template>
  <div class="page-shell static-page">
    <!-- Order Not Found -->
    <section v-if="kind === 'orders-not-found'" class="surface ds-empty-state" role="status">
      <i class="bi bi-inbox" aria-hidden="true"></i>
      <p class="text-muted mb-0">404 · Not found</p>
      <h1 class="ds-empty-state__title">We couldn't find that order</h1>
      <p class="ds-empty-state__message">It may no longer be available, or you may not have access to it.</p>
      <a class="btn btn-primary" :href="url('orders')">Back to orders</a>
    </section>

    <!-- Rider Delivery Unavailable -->
    <section v-else-if="kind === 'rider-not-found'" class="surface error-panel" role="status">
      <i class="bi bi-exclamation-triangle error-panel__icon error-panel__icon--warn" aria-hidden="true"></i>
      <h1 class="h4 mb-2">Delivery unavailable</h1>
      <p class="text-muted mb-4">The delivery may have been reassigned, completed, or it may not belong to your rider account.</p>
      <a class="btn btn-primary" :href="url('rider')">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        Back to my deliveries
      </a>
    </section>

    <!-- Delivery Control Pass Not Found -->
    <section v-else-if="kind === 'delivery-control-not-found'" class="surface error-panel" role="status">
      <i class="bi bi-link-45deg error-panel__icon error-panel__icon--muted" aria-hidden="true"></i>
      <h1 class="h4 mb-2">Delivery link unavailable</h1>
      <p class="text-muted mb-4">This delivery control link is invalid, expired, or has been revoked.</p>
      <a class="btn btn-primary" :href="url()">Return home</a>
    </section>

    <!-- 403 Forbidden -->
    <section v-else-if="kind === '403' || kind === 'forbidden'" class="surface error-panel" role="alert">
      <i class="bi bi-shield-lock error-panel__icon" aria-hidden="true"></i>
      <p class="text-muted mb-1">403 · Access denied</p>
      <h1 class="h3 fw-bold mb-2">Access restricted</h1>
      <p class="text-muted mb-4">You do not have permission to view or manage this resource.</p>
      <div class="d-flex justify-content-center gap-2 flex-wrap">
        <a class="btn btn-primary" :href="url()">Return home</a>
        <a class="btn btn-outline-secondary" :href="url('login')">Switch account</a>
      </div>
    </section>

    <!-- Generic Error (500) -->
    <section v-else-if="kind === '500' || kind === 'error'" class="surface error-panel" role="alert">
      <i class="bi bi-x-octagon error-panel__icon" aria-hidden="true"></i>
      <p class="text-muted mb-1">500 · System error</p>
      <h1 class="h3 fw-bold mb-2">Something went wrong</h1>
      <p class="text-muted mb-4">An unexpected error occurred while processing your request. Please try again shortly.</p>
      <a class="btn btn-primary" :href="url()">Return home</a>
    </section>

    <!-- Generic 404 Not Found -->
    <section v-else class="surface ds-empty-state" role="status">
      <i class="bi bi-signpost-split" aria-hidden="true"></i>
      <p class="text-muted mb-0">404 · Not found</p>
      <h1 class="ds-empty-state__title">Page not found</h1>
      <p class="ds-empty-state__message">The requested page or resource could not be found.</p>
      <a class="btn btn-primary" :href="url()">Return home</a>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  kind: { type: String, default: 'not-found' },
  baseUrl: { type: String, default: '/' },
});

const cleanBaseUrl = computed(() => {
  let u = props.baseUrl || '/';
  if (!u.endsWith('/')) u += '/';
  return u;
});

function url(path = '') {
  return cleanBaseUrl.value + String(path).replace(/^\/+/, '');
}
</script>

<style scoped>
.static-page {
  display: grid;
  place-items: center;
  padding-block: var(--s-5);
}

.static-page > section {
  width: min(100%, 32rem);
}

.error-panel__icon {
  display: block;
  margin: 0 auto var(--s-3);
  font-size: 3rem;
}

.error-panel__icon--warn { color: var(--amber); }
.error-panel__icon--muted { color: var(--ink-3); }

@media (prefers-reduced-motion: reduce) {
  .static-page { scroll-behavior: auto; }
}
</style>
