<template>
  <div class="page-shell ds-page">
    <header class="ds-page-header">
      <div>
        <p class="ds-eyebrow">Application shell</p>
        <h1 class="page-title">Welcome to Dispatch</h1>
        <p class="page-description">This Vue application shell is connected to the server-provided session.</p>
      </div>
    </header>

    <div class="app-links">
      <a v-for="link in links" :key="link.href" class="surface card--interactive app-link" :href="link.href">
        <span class="app-link__title">{{ link.title }}</span>
        <span class="app-link__desc">{{ link.description }}</span>
      </a>
    </div>

    <details class="surface app-state">
      <summary class="app-state__summary">Server-provided state</summary>
      <pre class="app-state__body">{{ stateLabel }}</pre>
    </details>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue';

const state = inject('initialState', {});
const baseUrl = computed(() => state.baseUrl || state.base_url || '/');
const stateLabel = computed(() => (Object.keys(state).length ? JSON.stringify(state, null, 2) : 'None'));

const links = computed(() => [
  { href: `${baseUrl.value}spa`, title: 'Vue workspace', description: 'The full role-aware workspace.' },
  { href: `${baseUrl.value}orders`, title: 'Orders', description: 'Browse and manage deliveries.' },
  { href: `${baseUrl.value}admin`, title: 'Admin', description: 'Operations and reporting.' }
]);
</script>

<style scoped>
.app-links {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: var(--s-3);
  margin-block-end: var(--s-4);
}

.app-link {
  display: grid;
  gap: var(--s-1);
  padding: var(--s-4);
  color: inherit;
  text-decoration: none;
}

.app-link__title { color: var(--ink); font-size: var(--font-size-16); font-weight: 700; }
.app-link__desc { color: var(--ink-2); font-size: var(--font-size-14); }

.app-state { padding: var(--s-4); }

.app-state__summary {
  cursor: pointer;
  color: var(--ink);
  font-weight: 700;
}

.app-state__body {
  margin: var(--s-3) 0 0;
  max-height: 260px;
  overflow: auto;
  white-space: pre-wrap;
}
</style>
