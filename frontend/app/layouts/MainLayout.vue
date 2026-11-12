<template>
  <div class="app-shell d-flex flex-column min-vh-100">
    <header class="app-shell__header">
      <div class="page-shell app-shell__bar">
        <a :href="baseUrl" class="app-shell__brand">Dispatch</a>
        <nav class="app-shell__nav" aria-label="Account">
          <template v-if="user">
            <span class="role-chip">{{ user.name || user.email || 'Account' }}</span>
          </template>
          <template v-else>
            <a class="btn btn-secondary btn-sm" :href="`${baseUrl}login`">Sign in</a>
            <a class="btn btn-primary btn-sm" :href="`${baseUrl}register`">Create account</a>
          </template>
        </nav>
      </div>
    </header>

    <main class="app-main app-shell__main">
      <router-view />
    </main>

    <footer class="app-footer">
      <div class="page-shell">{{ new Date().getFullYear() }} Dispatch · Delivery and logistics management</div>
    </footer>
  </div>
</template>

<script setup>
import { inject } from 'vue';

const state = inject('initialState', {});
const user = state.user || null;
const baseUrl = state.baseUrl || (state.base_url || '/');
</script>

<style scoped>
.app-shell {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.app-shell__header {
  position: sticky;
  top: 0;
  z-index: 1020;
  background: var(--surface);
  border-bottom: 1px solid var(--line);
  box-shadow: 0 2px 10px rgb(23 43 58 / 4%);
}

.app-shell__bar {
  min-height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--s-3);
}

.app-shell__brand {
  color: var(--ink);
  font-size: var(--font-size-20);
  font-weight: 700;
  text-decoration: none;
}

.app-shell__brand:hover { color: var(--route-dark); }

.app-shell__nav {
  display: flex;
  align-items: center;
  gap: var(--s-2);
}

.app-shell__main { flex: 1 0 auto; }
</style>
