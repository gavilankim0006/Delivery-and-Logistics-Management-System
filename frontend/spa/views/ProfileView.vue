<template>
  <section class="ds-page profile-page">
    <header class="ds-page-header">
      <div>
        <p class="ds-eyebrow">Account</p>
        <h1 class="page-title">My profile</h1>
        <p class="page-description">Manage your details and notification preferences.</p>
      </div>
    </header>

    <div v-if="message" class="ds-notice ds-notice--success mb-3" role="status">
      <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>{{ message }}
    </div>
    <div v-if="error" class="ds-notice ds-notice--danger mb-3" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ error }}
    </div>
    <div v-if="loading" class="text-center py-5"><div class="spinner-border" role="status"></div></div>

    <form v-else class="surface" @submit.prevent="save">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="spa-profile-name">Name</label>
          <input id="spa-profile-name" v-model="form.name" class="form-control" required />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="spa-profile-email">Email</label>
          <input id="spa-profile-email" :value="user?.email" class="form-control" disabled />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="spa-profile-phone">Phone</label>
          <input id="spa-profile-phone" v-model="form.phone" class="form-control" />
        </div>
      </div>

      <fieldset v-if="role === 'customer'" class="mt-4 pt-4 border-top">
        <legend class="ds-section-title">Notifications</legend>
        <div class="row g-2 mt-1">
          <div class="col-md-6">
            <div class="form-check">
              <input v-model="form.sms_opt_in" class="form-check-input" type="checkbox" id="sms" />
              <label class="form-check-label" for="sms">SMS opt-in</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check">
              <input v-model="form.delivery_status_sms_opt_in" class="form-check-input" type="checkbox" id="deliverySms" />
              <label class="form-check-label" for="deliverySms">Delivery status SMS</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check">
              <input v-model="form.push_opt_in" class="form-check-input" type="checkbox" id="push" />
              <label class="form-check-label" for="push">Push notifications</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check">
              <input v-model="form.email_notifications_opt_in" class="form-check-input" type="checkbox" id="email" />
              <label class="form-check-label" for="email">Email notifications</label>
            </div>
          </div>
        </div>
      </fieldset>

      <fieldset class="mt-4 pt-4 border-top">
        <legend class="ds-section-title">Password</legend>
        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label class="form-label" for="spa-profile-password">New password</label>
            <input id="spa-profile-password" v-model="form.password" type="password" class="form-control" autocomplete="new-password" />
          </div>
          <div class="col-md-6">
            <label class="form-label" for="spa-profile-confirm">Confirm password</label>
            <input id="spa-profile-confirm" v-model="form.password_confirm" type="password" class="form-control" autocomplete="new-password" />
          </div>
        </div>
      </fieldset>

      <div class="mt-4 pt-4 border-top">
        <button class="btn btn-primary" type="submit" :disabled="saving">
          <i class="bi bi-check2" aria-hidden="true"></i>
          {{ saving ? 'Saving…' : 'Save profile' }}
        </button>
      </div>
    </form>
  </section>
</template>

<script setup>
import { reactive, ref } from 'vue';

const props = defineProps({
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' }
});

const user = ref(null);
const role = ref('');
const loading = ref(true);
const saving = ref(false);
const message = ref('');
const error = ref('');

const form = reactive({
  name: '',
  phone: '',
  password: '',
  password_confirm: '',
  sms_opt_in: true,
  delivery_status_sms_opt_in: true,
  push_opt_in: true,
  email_notifications_opt_in: true
});

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const response = await fetch(`${props.baseUrl}profile/data`, { credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Could not load profile.');
    user.value = result.data.user;
    role.value = result.data.role || '';
    Object.assign(form, {
      name: user.value?.name || '',
      phone: user.value?.phone || '',
      password: '',
      password_confirm: '',
      sms_opt_in: user.value?.sms_opt_in === undefined ? true : Boolean(user.value.sms_opt_in),
      delivery_status_sms_opt_in: user.value?.delivery_status_sms_opt_in === undefined ? true : Boolean(user.value.delivery_status_sms_opt_in),
      push_opt_in: user.value?.push_opt_in === undefined ? true : Boolean(user.value.push_opt_in),
      email_notifications_opt_in: user.value?.email_notifications_opt_in === undefined ? true : Boolean(user.value.email_notifications_opt_in)
    });
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  message.value = '';
  error.value = '';
  const body = new URLSearchParams();
  Object.entries(form).forEach(([key, value]) => {
    if (typeof value === 'boolean') body.append(key, value ? '1' : '');
    else body.append(key, value ?? '');
  });
  body.append('csrf_token', props.csrfToken);

  try {
    const response = await fetch(`${props.baseUrl}profile/api`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': props.csrfToken
      },
      body: body.toString()
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Could not save profile.');
    user.value = result.data.user;
    role.value = result.data.role || role.value;
    message.value = result.message || 'Profile updated.';
    form.password = '';
    form.password_confirm = '';
  } catch (err) {
    error.value = err.message;
  } finally {
    saving.value = false;
  }
}

load();
</script>
