<template>
  <div class="container page-shell py-4">
    <header class="page-header mb-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <p class="ds-eyebrow">Administration</p>
        <h1 class="page-title">API audit logs</h1>
        <p class="page-description">Recent external requests recorded by the API client.</p>
      </div>
      <a class="btn btn-secondary" :href="`${baseUrl}admin`">Admin dashboard</a>
    </header>

    <section class="surface" aria-label="API request logs">
      <div class="table-responsive">
        <table class="table table-clean table-responsive-stack align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Time</th>
              <th>Service</th>
              <th>Method</th>
              <th>Endpoint</th>
              <th>Status</th>
              <th>Latency</th>
              <th>Attempt</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!logs.length">
              <td colspan="8" class="text-center py-4 text-muted">No API logs have been recorded yet.</td>
            </tr>
            <tr v-for="log in logs" :key="log.id">
              <td data-label="ID" class="text-muted">#{{ log.id }}</td>
              <td data-label="Time" class="text-nowrap">{{ formatTime(log.created_at) }}</td>
              <td data-label="Service">{{ log.service }}</td>
              <td data-label="Method" class="fw-medium">{{ log.method }}</td>
              <td data-label="Endpoint" class="font-monospace text-break">{{ log.endpoint }}</td>
              <td data-label="Status">
                <span class="audit-status"
                      :class="log.response_status >= 200 && log.response_status < 300 ? 'audit-status--success' : 'audit-status--failure'">
                  {{ log.response_status || 'Error' }}
                </span>
              </td>
              <td data-label="Latency" class="text-nowrap">{{ Number(log.execution_time_ms || 0).toLocaleString() }} ms</td>
              <td data-label="Attempt">{{ log.attempt }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
const props = defineProps({
  logs: { type: Array, default: () => [] },
  baseUrl: { type: String, default: '/' }
});

function formatTime(value) {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}
</script>