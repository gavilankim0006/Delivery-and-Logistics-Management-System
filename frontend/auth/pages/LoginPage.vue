<script setup>
import { ref } from 'vue';

const props = defineProps({
    baseUrl: { type: String, required: true },
    csrfToken: { type: String, required: true },
    errorMessage: { type: String, default: '' },
    successMessage: { type: String, default: '' }
});
const email = ref('');
const submitting = ref(false);

function submit() {
    submitting.value = true;
}
</script>

<template>
    <section class="auth-page" aria-labelledby="auth-title">
        <header class="auth-page__header">
            <p class="auth-page__brand"><i class="bi bi-box-seam" aria-hidden="true"></i>Dispatch</p>
            <svg class="auth-page__route" viewBox="0 0 112 24" aria-hidden="true" focusable="false">
                <path d="M12 12h88" fill="none" stroke="currentColor" stroke-width="2" />
                <circle cx="12" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
                <circle cx="56" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
                <circle cx="100" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
            </svg>
            <h1 id="auth-title" class="page-title">Sign in</h1>
            <p class="page-description">Manage your deliveries and saved addresses.</p>
        </header>

        <div v-if="successMessage" class="alert alert-success" role="status">{{ successMessage }}</div>
        <div v-if="errorMessage" class="alert alert-danger" role="alert">{{ errorMessage }}</div>

        <form class="auth-form" :action="`${baseUrl}login`" method="POST" @submit="submit">
            <input type="hidden" name="csrf_token" :value="csrfToken">
            <div class="mb-3">
                <label for="login-email" class="form-label">Email address</label>
                <input id="login-email" v-model="email" type="email" class="form-control" name="email" placeholder="name@example.com" required autofocus autocomplete="email">
            </div>
            <div class="mb-4">
                <label for="login-password" class="form-label">Password</label>
                <input id="login-password" type="password" class="form-control" name="password" placeholder="Enter your password" required autocomplete="current-password">
            </div>
            <div class="d-grid mb-4">
                <button type="submit" class="btn btn-primary py-2 fw-medium" :disabled="submitting">
                    {{ submitting ? 'Signing in…' : 'Sign in' }}
                </button>
            </div>
            <p class="text-center text-muted mb-0">
                New here?
                <a :href="`${baseUrl}register`" class="fw-semibold text-decoration-none">Create an account</a>
            </p>
        </form>
    </section>
</template>
