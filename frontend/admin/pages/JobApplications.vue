<template>
  <div class="container page-shell py-4">
    <header class="page-header mb-4">
      <div>
        <p class="ds-eyebrow">Administration</p>
        <h1 class="page-title">Job applications</h1>
        <p class="page-description">Review rider and dispatcher applications. ID files are sensitive; access only when needed for verification.</p>
      </div>
    </header>

    <div
      v-for="(flash, index) in flashes"
      :key="index"
      class="alert"
      :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`"
      role="status"
    >
      {{ flash.message }}
    </div>

    <section class="surface surface-section mb-4" aria-label="Filter applications">
      <form :action="`${baseUrl}admin/applications`" method="GET" class="row g-3 align-items-end">
        <div class="col-12 col-md-5">
          <label class="form-label" for="application-status">Status</label>
          <select class="form-select" id="application-status" name="status">
            <option value="">All applications</option>
            <option value="pending" :selected="statusFilter === 'pending'">Pending</option>
            <option value="approved" :selected="statusFilter === 'approved'">Approved</option>
            <option value="rejected" :selected="statusFilter === 'rejected'">Rejected</option>
          </select>
        </div>
        <div class="col-auto">
          <button class="btn btn-secondary" type="submit">Filter</button>
        </div>
      </form>
    </section>

    <section v-if="!applications || !applications.length" class="surface surface-section">
      <h2 class="h5">No applications found</h2>
      <p class="mb-0 text-muted">Applications will appear here after candidates submit them.</p>
    </section>

    <div v-else class="d-grid gap-3">
      <article
        v-for="application in applications"
        :key="application.id"
        class="surface surface-section"
      >
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
          <div>
            <h2 class="h5 mb-1">{{ application.full_name }}</h2>
            <span class="badge text-bg-light me-1">{{ capitalize(application.role_applied) }}</span>
            <span class="badge" :class="getStatusBadgeClass(application.status)">{{ capitalize(application.status) }}</span>
          </div>
          <span class="small text-muted">Submitted {{ application.created_at }}</span>
        </div>

        <div class="row g-3 small mb-3">
          <div class="col-12 col-md-6">
            <div><strong>Notification email:</strong> {{ application.email }}</div>
            <div><strong>Account email:</strong> {{ application.account_email || 'Not linked' }}</div>
            <div><strong>Phone:</strong> {{ application.phone }}</div>
            <div><strong>Location:</strong> {{ application.location }}</div>
          </div>
          <div class="col-12 col-md-6">
            <div><strong>Availability:</strong> {{ application.availability }}</div>
            <div><strong>ID type:</strong> {{ application.id_type }}</div>
            <div><strong>ID number:</strong> {{ application.id_number }}</div>
            <div>
              <strong>ID file:</strong>
              <template v-if="application.id_original_name || application.id_storage_key">
                <a
                  :href="`${baseUrl}admin/applications/${application.id}/id`"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="ms-1"
                >
                  View securely ({{ getFileExtension(application.id_original_name) }})
                </a>
              </template>
              <span v-else class="text-muted ms-1">No file uploaded</span>
            </div>
          </div>
          <div class="col-12">
            <strong>Experience:</strong>
            <p class="pre-wrap mb-0 mt-1">{{ application.experience || '—' }}</p>
          </div>
        </div>

        <form
          :action="`${baseUrl}admin/applications/${application.id}/review`"
          method="POST"
          class="row g-2 align-items-end mt-2"
        >
          <input type="hidden" name="csrf_token" :value="csrfToken">
          <div class="col-12 col-md-3">
            <label class="form-label" :for="`status-${application.id}`">Decision</label>
            <select
              class="form-select"
              :id="`status-${application.id}`"
              name="status"
              required
              v-model="getFormState(application).status"
            >
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
          <div class="col-12 col-md-7">
            <label class="form-label" :for="`note-${application.id}`">Internal note</label>
            <input
              class="form-control"
              :id="`note-${application.id}`"
              name="admin_note"
              maxlength="3000"
              v-model="getFormState(application).admin_note"
              placeholder=""
            >
          </div>
          <div class="col-12 col-md-2">
            <button class="btn btn-primary w-100" type="submit">Save review</button>
          </div>
          <div class="col-12">
            <small class="text-muted">
              <i class="bi bi-info-circle me-1"></i>Approving creates the login account, registers rider profile (if Rider), and emails login credentials to the applicant.
            </small>
          </div>
        </form>

        <div v-if="application.user_id" class="mt-3 pt-2 border-top d-flex flex-wrap align-items-center gap-2 small">
          <span class="text-success fw-medium">
            <i class="bi bi-person-check-fill me-1"></i>Account active: User #{{ application.user_id }} ({{ application.account_email || application.email }})
          </span>
          <a :href="`${baseUrl}admin/users/${application.user_id}`" class="badge text-bg-primary text-decoration-none">View user</a>
          <a v-if="application.role_applied === 'rider'" :href="`${baseUrl}admin/riders`" class="badge text-bg-info text-decoration-none">Manage rider fleet</a>
        </div>
        <div v-else-if="application.status === 'approved'" class="mt-3 pt-2 border-top small text-warning-emphasis">
          <i class="bi bi-exclamation-triangle-fill me-1"></i>Account pending provisioning. Click <strong>Save review</strong> with "Approved" to generate their account and send the notification email.
        </div>
      </article>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  applications: {
    type: Array,
    default: () => []
  },
  statusFilter: {
    type: String,
    default: null
  },
  baseUrl: {
    type: String,
    default: '/'
  },
  csrfToken: {
    type: String,
    default: ''
  },
  flashes: {
    type: Array,
    default: () => []
  }
});

const formStates = ref({});

const getFormState = (application) => {
  if (!formStates.value[application.id]) {
    formStates.value[application.id] = {
      status: application.status || 'pending',
      admin_note: application.admin_note || ''
    };
  }
  return formStates.value[application.id];
};

const initFormStates = () => {
  const states = {};
  if (Array.isArray(props.applications)) {
    props.applications.forEach((app) => {
      states[app.id] = {
        status: app.status || 'pending',
        admin_note: app.admin_note || ''
      };
    });
  }
  formStates.value = states;
};

initFormStates();
watch(() => props.applications, initFormStates, { deep: true });

const capitalize = (str) => {
  if (!str || typeof str !== 'string') return '';
  return str.charAt(0).toUpperCase() + str.slice(1);
};

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'approved':
      return 'text-bg-success';
    case 'pending':
      return 'text-bg-warning';
    case 'rejected':
      return 'text-bg-secondary';
    default:
      return 'text-bg-secondary';
  }
};

const getFileExtension = (filename) => {
  if (!filename || typeof filename !== 'string') return 'DOCUMENT';
  const parts = filename.split('.');
  if (parts.length < 2) return 'DOCUMENT';
  const ext = parts.pop().trim().toUpperCase();
  return ext || 'DOCUMENT';
};
</script>
