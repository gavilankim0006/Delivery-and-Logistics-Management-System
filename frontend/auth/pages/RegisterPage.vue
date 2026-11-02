<script setup>
import { ref } from 'vue';

const props = defineProps({
    baseUrl: { type: String, required: true },
    csrfToken: { type: String, required: true },
    errorMessage: { type: String, default: '' },
    successMessage: { type: String, default: '' }
});
const submitting = ref(false);

function submit() {
    submitting.value = true;
}
</script>

<template>
    <section class="auth-page auth-page--register" aria-labelledby="auth-title">
        <header class="auth-page__header">
            <a :href="baseUrl" class="auth-page__brand text-decoration-none" title="Back to home page"><i class="bi bi-box-seam" aria-hidden="true"></i>Dispatch</a>
            <svg class="auth-page__route" viewBox="0 0 112 24" aria-hidden="true" focusable="false">
                <path d="M12 12h88" fill="none" stroke="currentColor" stroke-width="2" />
                <circle cx="12" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
                <circle cx="56" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
                <circle cx="100" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
            </svg>
            <h1 id="auth-title" class="page-title">Create an account</h1>
            <p class="page-description">Set up a customer account to arrange your deliveries.</p>
        </header>

        <div v-if="successMessage" class="alert alert-success" role="status">{{ successMessage }}</div>
        <div v-if="errorMessage" class="alert alert-danger" role="alert">{{ errorMessage }}</div>

        <form class="auth-form" :action="`${baseUrl}register`" method="POST" @submit="submit">
            <input type="hidden" name="csrf_token" :value="csrfToken">
            <div class="mb-3">
                <label for="register-name" class="form-label">Full name or primary contact <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="register-name" type="text" class="form-control" name="name" placeholder="e.g. Juan Dela Cruz" required autocomplete="name">
            </div>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label for="register-email" class="form-label">Email address <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="register-email" type="email" class="form-control" name="email" placeholder="name@example.com" required autocomplete="email">
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label for="register-phone" class="form-label">Mobile phone <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="register-phone" type="tel" class="form-control" name="phone" placeholder="+639171234567" required autocomplete="tel">
                </div>
            </div>
            <div class="mb-3">
                <label for="register-company" class="form-label">Company or business name <span class="text-muted small">(Optional)</span></label>
                <input id="register-company" type="text" class="form-control" name="company_name" placeholder="e.g. Acme Trading Corp" autocomplete="organization">
            </div>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label for="register-password" class="form-label">Password <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="register-password" type="password" class="form-control" name="password" placeholder="At least 8 characters" minlength="8" required autocomplete="new-password">
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label for="register-password-confirm" class="form-label">Confirm password <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="register-password-confirm" type="password" class="form-control" name="password_confirm" placeholder="Enter the password again" minlength="8" required autocomplete="new-password">
                </div>
            </div>
            <div class="d-grid mt-2 mb-4">
                <button type="submit" class="btn btn-primary py-2 fw-medium" :disabled="submitting">
                    {{ submitting ? 'Creating account…' : 'Create account' }}
                </button>
            </div>
            <p class="text-center text-muted mb-0">
                Already have an account?
                <a :href="`${baseUrl}login`" class="fw-semibold text-decoration-none">Sign in</a>
            </p>
        </form>
    </section>
</template>
