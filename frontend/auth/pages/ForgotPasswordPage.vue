<template>
  <section class="auth-page" aria-labelledby="forgot-title">
    <header class="auth-page__header">
      <a :href="baseUrl" class="auth-page__brand">Dispatch</a>
      <svg class="auth-page__route" viewBox="0 0 112 24" aria-hidden="true" focusable="false">
        <path d="M12 12h88" fill="none" stroke="currentColor" stroke-width="2" />
        <circle cx="12" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
        <circle cx="56" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
        <circle cx="100" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
      </svg>
      <p class="ds-eyebrow">Account recovery</p>
      <h1 id="forgot-title" class="page-title">Forgot password</h1>
      <p class="page-description">Enter your account email and we will send a reset link.</p>
    </header>
    <form class="auth-form" :action="`${baseUrl}forgot-password`" method="POST">
      <input type="hidden" name="csrf_token" :value="csrfToken" />
      <label class="form-label" for="email">Email address</label>
      <input class="form-control mb-3" id="email" name="email" type="email" required maxlength="150" autocomplete="email" />
      <button class="btn btn-primary w-100" type="submit">Send reset link</button>
      <p class="text-center mt-3"><a :href="`${baseUrl}login`">Back to sign in</a></p>
    </form>
  </section>
</template>

<script setup>
defineProps({
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' }
});
</script>