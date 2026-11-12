<template>
  <div class="container page-shell py-4">
    <header class="page-header mb-4">
      <div>
        <p class="ds-eyebrow">Administration</p>
        <h1 class="page-title">Fee rates and settings</h1>
        <p class="page-description">Configure delivery pricing, support contact channels, and system notification gateways.</p>
      </div>
    </header>

    <div v-for="(flash, index) in flashes" :key="index" class="alert alert-dismissible fade show" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
      <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
      {{ flash.message }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <!-- Main Pricing and Contact Settings Form -->
    <section class="surface surface-section mb-4" aria-label="Delivery pricing and support settings">
      <form :action="`${baseUrl}admin/settings`" method="POST" id="fee-settings-form" novalidate>
        <input type="hidden" name="csrf_token" :value="csrfToken">

        <section class="form-section mb-4" aria-labelledby="pricing-heading">
          <h2 class="h5 mb-3" id="pricing-heading">
            <i class="bi bi-calculator text-primary me-2" aria-hidden="true"></i>Delivery fee calculation
          </h2>
          <div class="row g-3">
            <div class="col-12 col-md-6 shared-field-wrap">
              <label for="base-delivery-fee" class="form-label shared-label">
                <span>Base delivery fee</span>
                <span class="text-danger ms-1" aria-hidden="true">*</span>
              </label>
              <div class="input-group">
                <span class="input-group-text" aria-hidden="true">₱</span>
                <input
                  id="base-delivery-fee"
                  type="number"
                  min="0"
                  max="100000"
                  step="0.01"
                  class="form-control"
                  name="base_delivery_fee"
                  :value="settings.base_delivery_fee || '60.00'"
                  required
                  aria-describedby="base-fee-help"
                >
                <span class="input-group-text">PHP</span>
              </div>
              <div class="form-text shared-helper-text" id="base-fee-help">Standard base charge that covers the initial distance limit.</div>
            </div>

            <div class="col-12 col-md-6 shared-field-wrap">
              <label for="free-distance-km" class="form-label shared-label">
                <span>Free distance limit</span>
                <span class="text-danger ms-1" aria-hidden="true">*</span>
              </label>
              <div class="input-group">
                <input
                  id="free-distance-km"
                  type="number"
                  min="0"
                  max="1000"
                  step="0.1"
                  class="form-control"
                  name="free_distance_km"
                  :value="settings.free_distance_km || '2.0'"
                  required
                  aria-describedby="free-distance-help"
                >
                <span class="input-group-text">km</span>
              </div>
              <div class="form-text shared-helper-text" id="free-distance-help">Transit distance included in the base fee before per-km charges apply.</div>
            </div>

            <div class="col-12 col-md-6 shared-field-wrap">
              <label for="per-km-rate" class="form-label shared-label">
                <span>Distance rate per km</span>
                <span class="text-danger ms-1" aria-hidden="true">*</span>
              </label>
              <div class="input-group">
                <span class="input-group-text" aria-hidden="true">₱</span>
                <input
                  id="per-km-rate"
                  type="number"
                  min="0"
                  max="10000"
                  step="0.01"
                  class="form-control"
                  name="per_km_rate"
                  :value="settings.per_km_rate || '12.50'"
                  required
                  aria-describedby="per-km-rate-help"
                >
                <span class="input-group-text">PHP</span>
              </div>
              <div class="form-text shared-helper-text" id="per-km-rate-help">Additional charge applied for each kilometer beyond the free distance limit.</div>
            </div>

            <div class="col-12 col-md-6 shared-field-wrap">
              <label for="free-weight-kg" class="form-label shared-label">
                <span>Free weight limit</span>
                <span class="text-danger ms-1" aria-hidden="true">*</span>
              </label>
              <div class="input-group">
                <input
                  id="free-weight-kg"
                  type="number"
                  min="0"
                  max="1000"
                  step="0.1"
                  class="form-control"
                  name="free_weight_kg"
                  :value="settings.free_weight_kg || '5.0'"
                  required
                  aria-describedby="free-weight-help"
                >
                <span class="input-group-text">kg</span>
              </div>
              <div class="form-text shared-helper-text" id="free-weight-help">Package weight covered under the base fee before per-kg charges apply.</div>
            </div>

            <div class="col-12 col-md-6 shared-field-wrap">
              <label for="per-kg-rate" class="form-label shared-label">
                <span>Weight rate per kg</span>
                <span class="text-danger ms-1" aria-hidden="true">*</span>
              </label>
              <div class="input-group">
                <span class="input-group-text" aria-hidden="true">₱</span>
                <input
                  id="per-kg-rate"
                  type="number"
                  min="0"
                  max="10000"
                  step="0.01"
                  class="form-control"
                  name="per_kg_rate"
                  :value="settings.per_kg_rate || '5.00'"
                  required
                  aria-describedby="per-kg-rate-help"
                >
                <span class="input-group-text">PHP</span>
              </div>
              <div class="form-text shared-helper-text" id="per-kg-rate-help">Additional charge applied for each kilogram beyond the free weight limit.</div>
            </div>
          </div>
        </section>

        <section class="form-section pt-3 border-top mb-4" aria-labelledby="support-heading">
          <h2 class="h5 mb-3" id="support-heading">
            <i class="bi bi-headset text-primary me-2" aria-hidden="true"></i>Customer support contact channels
          </h2>
          <div class="row g-3">
            <div class="col-12 col-md-6 shared-field-wrap">
              <label for="support-email" class="form-label shared-label">
                <span>Customer support email</span>
                <span class="text-danger ms-1" aria-hidden="true">*</span>
              </label>
              <div class="input-group">
                <span class="input-group-text" aria-hidden="true"><i class="bi bi-envelope"></i></span>
                <input
                  id="support-email"
                  type="email"
                  class="form-control"
                  name="support_email"
                  :value="settings.support_email || 'support@omniroute.local'"
                  required
                  aria-describedby="support-email-help"
                >
              </div>
              <div class="form-text shared-helper-text" id="support-email-help">Official email displayed to customers on tracking pages and notifications.</div>
            </div>

            <div class="col-12 col-md-6 shared-field-wrap">
              <label for="support-phone" class="form-label shared-label">
                <span>Customer support phone</span>
                <span class="text-danger ms-1" aria-hidden="true">*</span>
              </label>
              <div class="input-group">
                <span class="input-group-text" aria-hidden="true"><i class="bi bi-telephone"></i></span>
                <input
                  id="support-phone"
                  type="tel"
                  class="form-control"
                  name="support_phone"
                  :value="settings.support_phone || '+63 2 8123 4567'"
                  pattern="^[0-9+\s\-().]+$"
                  required
                  aria-describedby="support-phone-help"
                >
              </div>
              <div class="form-text shared-helper-text" id="support-phone-help">Philippine mobile, landline, or customer hotline (e.g., +63 2 8123 4567 or 09171234567).</div>
            </div>
          </div>
        </section>

        <div class="d-flex justify-content-end mt-4">
          <button type="submit" class="btn btn-primary fw-semibold">
            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Save fee rates and support settings
          </button>
        </div>
      </form>
    </section>

    <!-- Notification Gateways & Test Consoles -->
    <section class="surface surface-section mb-4" aria-labelledby="gateways-heading">
      <div class="mb-4">
        <h2 class="h5 mb-1" id="gateways-heading">
          <i class="bi bi-broadcast text-primary me-2" aria-hidden="true"></i>Notification gateways &amp; test consoles
        </h2>
        <p class="text-muted small mb-0">Verify external delivery notification channels before dispatching live orders.</p>
      </div>

      <div class="row g-4">
        <!-- Email (SMTP) Card -->
        <div class="col-12 col-lg-4">
          <div class="card h-100 border shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
              <span class="fw-bold"><i class="bi bi-envelope text-primary me-1"></i> Email (SMTP)</span>
              <span class="badge" :class="mail.enabled ? 'bg-success' : 'bg-secondary'">
                {{ mail.enabled ? 'Active' : 'Disabled' }}
              </span>
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
              <div>
                <p class="small text-muted mb-2">
                  <strong>Host:</strong> {{ mail.host || 'Not configured' }}<br>
                  <strong>Port:</strong> {{ mail.port || 587 }}<br>
                  <strong>From:</strong> {{ mail.fromAddress || 'noreply@omniroute.local' }}
                </p>
                <p class="small text-muted">Sends templated customer order confirmations, status transitions, and delivery notifications.</p>
              </div>
              <form :action="`${baseUrl}admin/test-email`" method="POST" id="test-email-form" class="mt-3 pt-3 border-top" novalidate>
                <input type="hidden" name="csrf_token" :value="csrfToken">
                <div class="shared-field-wrap mb-2">
                  <label for="test-email-address" class="form-label shared-label small">
                    <span>Recipient email address</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                  </label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text" aria-hidden="true"><i class="bi bi-envelope"></i></span>
                    <input
                      id="test-email-address"
                      type="email"
                      class="form-control"
                      name="email"
                      placeholder="e.g. admin@omniroute.local"
                      required
                    >
                  </div>
                  <div class="form-text shared-helper-text">Valid recipient email address for SMTP check.</div>
                </div>
                <button type="submit" class="btn btn-outline-primary btn-sm w-100 fw-semibold">
                  <i class="bi bi-send me-1" aria-hidden="true"></i>Send test email
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- SMS (Semaphore) Card -->
        <div class="col-12 col-lg-4">
          <div class="card h-100 border shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
              <span class="fw-bold"><i class="bi bi-chat-text me-1 text-success"></i> SMS (Semaphore)</span>
              <span class="badge" :class="semaphore.enabled ? 'bg-success' : 'bg-warning text-dark'">
                {{ semaphore.enabled ? 'Live API' : 'Simulation mode' }}
              </span>
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
              <div>
                <p class="small text-muted mb-2">
                  <strong>Sender:</strong> {{ semaphore.senderName || 'SEMAPHORE' }}<br>
                  <strong>API key:</strong> {{ semaphore.apiKey ? `Configured (${semaphore.apiKey.slice(0, 4)}***)` : 'Not set' }}
                </p>
                <p class="small text-muted">Delivers order update SMS messages to Philippine mobile recipients via Semaphore API.</p>
              </div>
              <form :action="`${baseUrl}admin/test-sms`" method="POST" id="test-sms-form" class="mt-3 pt-3 border-top" novalidate>
                <input type="hidden" name="csrf_token" :value="csrfToken">
                <div class="shared-field-wrap mb-2">
                  <label for="test-sms-phone" class="form-label shared-label small">
                    <span>Recipient mobile phone</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                  </label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text" aria-hidden="true"><i class="bi bi-phone"></i></span>
                    <input
                      id="test-sms-phone"
                      type="tel"
                      class="form-control"
                      name="phone"
                      placeholder="09171234567"
                      pattern="^(?:09|\+639|639)\d{9}$"
                      required
                    >
                  </div>
                  <div class="form-text shared-helper-text">Philippine mobile (09XXXXXXXXX or +639XXXXXXXXX)</div>
                </div>
                <div class="shared-field-wrap mb-2">
                  <div class="d-flex justify-content-between align-items-center">
                    <label for="test-sms-message" class="form-label shared-label small mb-1">
                      <span>Message text</span>
                    </label>
                    <span class="text-muted text-12">
                      {{ smsMessage.length }} / 480 ({{ smsChunks }} {{ smsChunks === 1 ? 'SMS' : 'SMS chunks' }})
                    </span>
                  </div>
                  <textarea
                    id="test-sms-message"
                    class="form-control form-control-sm"
                    name="message"
                    rows="2"
                    maxlength="480"
                    v-model="smsMessage"
                  ></textarea>
                  <div class="form-text shared-helper-text">160 characters per SMS chunk (up to 480 total)</div>
                </div>
                <button type="submit" class="btn btn-outline-primary btn-sm w-100 fw-semibold">
                  <i class="bi bi-chat-dots me-1" aria-hidden="true"></i>Send test SMS
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Push (Firebase FCM) Card -->
        <div class="col-12 col-lg-4">
          <div class="card h-100 border shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
              <span class="fw-bold"><i class="bi bi-bell me-1 text-warning"></i> Push (Firebase FCM)</span>
              <span class="badge" :class="firebase.enabled ? 'bg-success' : 'bg-secondary'">
                {{ firebase.enabled ? 'Enabled' : 'Disabled' }}
              </span>
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
              <div>
                <p class="small text-muted mb-2">
                  <strong>Project ID:</strong> {{ firebase.projectId || 'Not configured' }}<br>
                  <strong>Admin key:</strong>
                  <span :class="firebase.hasKey ? 'text-success' : 'text-warning'">
                    {{ firebase.hasKey ? 'Found' : 'Missing (simulated)' }}
                  </span>
                </p>
                <p class="small text-muted">Sends high-priority web/mobile push notifications to active drivers and customer devices.</p>
              </div>
              <form :action="`${baseUrl}admin/test-push`" method="POST" id="test-push-form" class="mt-3 pt-3 border-top" novalidate>
                <input type="hidden" name="csrf_token" :value="csrfToken">
                <div class="shared-field-wrap mb-2">
                  <label for="test-push-user" class="form-label shared-label small">
                    <span>Target registered user</span>
                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                  </label>
                  <select
                    id="test-push-user"
                    name="user_id"
                    class="form-select form-select-sm"
                    required
                    :disabled="!usersWithTokens || usersWithTokens.length === 0"
                  >
                    <option v-if="!usersWithTokens || usersWithTokens.length === 0" value="" disabled selected>
                      No registered users with active push token
                    </option>
                    <template v-else>
                      <option value="" disabled selected>-- Select recipient user with token --</option>
                      <option v-for="user in usersWithTokens" :key="user.id" :value="user.id">
                        {{ user.name }} ({{ user.email }})
                      </option>
                    </template>
                  </select>
                  <div class="form-text shared-helper-text">Users who registered device notification tokens in the web/mobile app.</div>
                </div>
                <button
                  type="submit"
                  class="btn btn-outline-primary btn-sm w-100 fw-semibold"
                  :disabled="!usersWithTokens || usersWithTokens.length === 0"
                >
                  <i class="bi bi-bell me-1" aria-hidden="true"></i>Send test push notification
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Delivery Notification Outbox Queue -->
    <section class="surface surface-section" aria-labelledby="outbox-heading">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h2 class="h5 mb-1" id="outbox-heading">
            <i class="bi bi-inbox text-primary me-2" aria-hidden="true"></i>Delivery notification outbox queue
          </h2>
          <div class="small text-muted">
            Queue status summary:
            <span class="badge bg-warning text-dark me-1">{{ outboxStats.pending || 0 }} pending</span>
            <span class="badge bg-success me-1">{{ outboxStats.sent || 0 }} sent</span>
            <span class="badge bg-danger me-1">{{ outboxStats.failed || 0 }} failed</span>
          </div>
        </div>
        <form :action="`${baseUrl}admin/dispatch-outbox`" method="POST" class="d-inline">
          <input type="hidden" name="csrf_token" :value="csrfToken">
          <button
            type="submit"
            class="btn btn-outline-primary btn-sm fw-semibold"
            :disabled="!outboxStats.pending || Number(outboxStats.pending) === 0"
          >
            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Process pending queue now
          </button>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-clean table-responsive-stack align-middle mb-0">
          <thead>
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Order</th>
              <th scope="col">Channel</th>
              <th scope="col">Recipient</th>
              <th scope="col">Status</th>
              <th scope="col">Message</th>
              <th scope="col">Sent / Created</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!outbox || outbox.length === 0">
              <td colspan="7" class="text-center py-4 text-muted">No notification queue entries recorded yet.</td>
            </tr>
            <tr v-for="item in outbox" :key="item.id">
              <td data-label="ID" class="text-muted">#{{ item.id }}</td>
              <td data-label="Order">
                <a v-if="item.order_id" :href="`${baseUrl}orders/${item.order_id}`" class="fw-bold text-decoration-none">
                  {{ item.order_no || `#${item.order_id}` }}
                </a>
                <span v-else class="text-muted">—</span>
              </td>
              <td data-label="Channel">
                <span class="badge text-bg-light border text-uppercase">
                  <i v-if="item.channel === 'email'" class="bi bi-envelope me-1" aria-hidden="true"></i>
                  <i v-else-if="item.channel === 'sms'" class="bi bi-chat-dots me-1" aria-hidden="true"></i>
                  <i v-else-if="item.channel === 'push'" class="bi bi-bell me-1" aria-hidden="true"></i>
                  {{ item.channel }}
                </span>
              </td>
              <td data-label="Recipient" class="font-monospace small">{{ item.recipient }}</td>
              <td data-label="Status">
                <span class="badge" :class="item.status === 'sent' ? 'bg-success' : (item.status === 'pending' ? 'bg-warning text-dark' : 'bg-danger')">
                  {{ capitalize(item.status) }}
                </span>
              </td>
              <td data-label="Message" class="small text-break" style="max-width: 320px;">
                {{ truncate(item.message, 90) }}
              </td>
              <td data-label="Sent / Created" class="text-muted small text-nowrap">
                {{ formatDateTime(item.sent_at || item.created_at) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  outbox: { type: Array, default: () => [] },
  outboxStats: { type: Object, default: () => ({}) },
  usersWithTokens: { type: Array, default: () => [] },
  mail: { type: Object, default: () => ({}) },
  semaphore: { type: Object, default: () => ({}) },
  firebase: { type: Object, default: () => ({}) },
  semaphoreEnabled: { type: Boolean, default: false },
  firebaseEnabled: { type: Boolean, default: false },
  mailEnabled: { type: Boolean, default: false },
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' },
  flashes: { type: Array, default: () => [] }
});

const smsMessage = ref('Dispatch test message from Admin.');
const smsChunks = computed(() => {
  const len = smsMessage.value ? smsMessage.value.length : 0;
  return Math.ceil(len / 160) || 1;
});

function capitalize(str) {
  if (!str) return '';
  return String(str).charAt(0).toUpperCase() + String(str).slice(1);
}

function truncate(str, len = 90) {
  if (!str) return '';
  if (str.length <= len) return str;
  return str.slice(0, len) + '...';
}

function formatDateTime(dateStr) {
  if (!dateStr) return '—';
  try {
    const d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return dateStr;
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const mins = String(d.getMinutes()).padStart(2, '0');
    const secs = String(d.getSeconds()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${mins}:${secs}`;
  } catch {
    return dateStr;
  }
}
</script>
