<template>
  <section class="auth-page" aria-labelledby="reset-title">
    <header class="auth-page__header">
      <a :href="baseUrl" class="auth-page__brand">Dispatch</a>
      <svg class="auth-page__route" viewBox="0 0 112 24" aria-hidden="true" focusable="false">
        <path d="M12 12h88" fill="none" stroke="currentColor" stroke-width="2" />
        <circle cx="12" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
        <circle cx="56" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
        <circle cx="100" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
      </svg>
      <p class="ds-eyebrow">Account recovery</p>
      <h1 id="reset-title" class="page-title">Reset password</h1>
      <p class="page-description">Choose a new password for your account.</p>
    </header>

    <div v-if="!tokenValid" class="alert alert-danger" role="alert">
      This password reset link is invalid or expired.
      <p class="mb-0 mt-2"><a :href="`${baseUrl}forgot-password`">Request a new reset link</a></p>
    </div>

    <form v-else class="auth-form" :action="`${baseUrl}reset-password`" method="POST">
      <input type="hidden" name="csrf_token" :value="csrfToken" />
      <input type="hidden" name="token" :value="token" />
      <label class="form-label" for="password">New password</label>
      <input class="form-control mb-3" id="password" name="password" type="password" required minlength="8" autocomplete="new-password" />
      <label class="form-label" for="password_confirm">Confirm password</label>
      <input class="form-control mb-3" id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password" />
      <button class="btn btn-primary w-100" type="submit">Update password</button>
    </form>
  </section>
</template>

<script setup>
defineProps({
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' },
  token: { type: String, default: '' },
  tokenValid: { type: Boolean, default: false }
});
</script>