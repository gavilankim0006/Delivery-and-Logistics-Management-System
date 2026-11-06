<script setup>
import { ref, onMounted } from 'vue';
import { TextInput, required, email as emailValidator } from '../../shared/index.js';

const props = defineProps({
    baseUrl: { type: String, required: true },
    csrfToken: { type: String, required: true },
    errorMessage: { type: String, default: '' },
    successMessage: { type: String, default: '' },
    oldEmail: { type: String, default: '' },
    loginContext: { type: String, default: 'customer' }
});

const isApplicant = props.loginContext === 'applicant';
const loginAction = isApplicant ? 'applicant/login' : 'login';
const email = ref('');
const password = ref('');
const submitting = ref(false);

const emailInputRef = ref(null);
const passwordInputRef = ref(null);

onMounted(() => {
    if (props.oldEmail) {
        email.value = props.oldEmail;
    } else {
        try {
            const saved = sessionStorage.getItem('dispatch_auth_email');
            if (saved) email.value = saved;
        } catch {
            // Ignore storage access error
        }
    }
});

function handleSubmit(event) {
    const isEmailValid = emailInputRef.value ? emailInputRef.value.validate() : true;
    const isPasswordValid = passwordInputRef.value ? passwordInputRef.value.validate() : true;

    if (!isEmailValid) {
        event.preventDefault();
        emailInputRef.value?.focus();
        return;
    }

    if (!isPasswordValid) {
        event.preventDefault();
        passwordInputRef.value?.focus();
        return;
    }

    try {
        sessionStorage.setItem('dispatch_auth_email', email.value);
    } catch {
        // Ignore storage access error
    }

    submitting.value = true;
}
</script>

<template>
    <section class="auth-page" aria-labelledby="auth-title">
        <header class="auth-page__header">
            <a :href="baseUrl" class="auth-page__brand text-decoration-none" title="Back to home page">
                <i class="bi bi-box-seam" aria-hidden="true"></i>Dispatch
            </a>
            <svg class="auth-page__route" viewBox="0 0 112 24" aria-hidden="true" focusable="false">
                <path d="M12 12h88" fill="none" stroke="currentColor" stroke-width="2" />
                <circle cx="12" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
                <circle cx="56" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
                <circle cx="100" cy="12" r="5" fill="var(--paper)" stroke="currentColor" stroke-width="2" />
            </svg>
            <h1 id="auth-title" class="page-title">{{ isApplicant ? 'Applicant sign in' : 'Customer sign in' }}</h1>
            <p class="page-description">{{ isApplicant ? 'Sign in with the account you want to use if your application is approved. You will continue to the rider or dispatcher application form.' : 'Sign in to manage orders, deliveries, and saved addresses.' }}</p>
        </header>

        <div v-if="successMessage" class="alert alert-success" role="status">{{ successMessage }}</div>
        <div v-if="errorMessage" class="alert alert-danger" role="alert">{{ errorMessage }}</div>

        <form class="auth-form" :action="`${baseUrl}${loginAction}`" method="POST" novalidate @submit="handleSubmit">
            <input type="hidden" name="csrf_token" :value="csrfToken">

            <TextInput
                id="login-email"
                ref="emailInputRef"
                v-model="email"
                name="email"
                type="email"
                label="Email address"
                placeholder="name@example.com"
                autocomplete="username"
                :required="true"
                :rules="[required, emailValidator]"
            />

            <TextInput
                id="login-password"
                ref="passwordInputRef"
                v-model="password"
                name="password"
                type="password"
                label="Password"
                placeholder="Enter your password"
                autocomplete="current-password"
                :required="true"
                :show-password-toggle="true"
                :rules="[required]"
            />

            <div class="d-flex justify-content-end mb-3">
                <a :href="`${baseUrl}forgot-password`" class="small text-decoration-none">Forgot password?</a>
            </div>
            <div class="d-grid mb-4">
                <button type="submit" class="btn btn-primary py-2 fw-medium" :disabled="submitting">
                    <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    <span>{{ submitting ? 'Submitting...' : 'Sign in' }}</span>
                </button>
            </div>

            <p class="text-center text-muted mb-0">
                New here?
                <a :href="`${baseUrl}register`" class="fw-semibold text-decoration-none">{{ isApplicant ? 'Create an account to apply' : 'Create a customer account' }}</a>
            </p>
            <p v-if="isApplicant" class="text-center small mb-0">
                Signing in as a customer? <a :href="`${baseUrl}login`">Customer sign in</a>.
            </p>
            <p v-else class="text-center small mb-0">
                Applying as a rider or dispatcher? <a :href="`${baseUrl}applicant/login`">Applicant sign in</a>.
            </p>
        </form>
    </section>
</template>
