<template>
  <section class="ds-page">
    <header class="ds-page-header">
      <div>
        <p class="ds-eyebrow">Workspace</p>
        <h1 class="page-title">Delivery workspace</h1>
        <p class="page-description">
          Vue-rendered tools for administration, dispatch, orders, addresses, deliveries,
          messaging and your account.
        </p>
      </div>
    </header>

    <div class="workspace-cards">
      <a
        v-for="item in links"
        :key="item.path"
        class="surface workspace-card card--interactive"
        :href="`#${item.path}`"
      >
        <span class="workspace-card__icon">
          <i class="bi" :class="item.icon" aria-hidden="true"></i>
        </span>
        <span class="workspace-card__body">
          <span class="workspace-card__title">{{ item.label }}</span>
          <span class="workspace-card__desc">{{ item.description }}</span>
        </span>
        <i class="bi bi-arrow-right workspace-card__arrow" aria-hidden="true"></i>
      </a>
    </div>
  </section>
</template>

<script setup>
defineProps({ baseUrl: { type: String, default: '/' } });

const links = [
  { path: '/admin', label: 'Admin dashboard', icon: 'bi-speedometer2', description: 'Operations snapshot and recent activity.' },
  { path: '/admin/users', label: 'Users', icon: 'bi-people', description: 'Manage staff and customer accounts.' },
  { path: '/admin/riders', label: 'Riders', icon: 'bi-bicycle', description: 'Review the rider fleet and status.' },
  { path: '/admin/analytics', label: 'Analytics', icon: 'bi-graph-up', description: 'Delivery trends and outcomes.' },
  { path: '/dispatch', label: 'Dispatch board', icon: 'bi-kanban', description: 'Assign riders and move deliveries.' },
  { path: '/orders', label: 'Orders', icon: 'bi-box-seam', description: 'Browse, filter and open deliveries.' },
  { path: '/addresses', label: 'Addresses', icon: 'bi-geo-alt', description: 'Saved pickup and drop-off places.' },
  { path: '/rider', label: 'Deliveries', icon: 'bi-truck', description: 'Fulfil your active assignments.' },
  { path: '/messenger', label: 'Messenger', icon: 'bi-chat-dots', description: 'Coordinate with customers and staff.' },
  { path: '/profile', label: 'Profile', icon: 'bi-person', description: 'Update your details and preferences.' }
];
</script>

<style scoped>
.workspace-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: var(--s-3);
}

.workspace-card {
  display: flex;
  align-items: center;
  gap: var(--s-3);
  padding: var(--s-4);
  color: inherit;
  text-decoration: none;
}

.workspace-card__icon {
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  display: grid;
  place-items: center;
  border-radius: var(--r-md);
  background: var(--status-blue-bg);
  color: var(--route);
  font-size: 1.2rem;
}

.workspace-card__body {
  min-width: 0;
  display: grid;
  gap: 2px;
}

.workspace-card__title {
  color: var(--ink);
  font-size: var(--font-size-16);
  font-weight: 700;
  letter-spacing: -0.01em;
}

.workspace-card__desc {
  color: var(--ink-2);
  font-size: var(--font-size-14);
}

.workspace-card__arrow {
  margin-inline-start: auto;
  color: var(--ink-3);
  transition: transform 180ms ease-out, color 180ms ease-out;
}

.workspace-card:hover .workspace-card__arrow {
  color: var(--route);
  transform: translateX(3px);
}

@media (prefers-reduced-motion: reduce) {
  .workspace-card__arrow { transition: none; }
}
</style>
