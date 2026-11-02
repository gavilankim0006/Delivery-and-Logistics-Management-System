<script setup>
import { ref, onMounted } from 'vue';
import {
    TextInput,
    required,
    email as emailValidator,
    phPhone,
    normalizePhPhone,
    minLength,
    passwordStrength
} from '../../shared/index.js';

const props = defineProps({
    baseUrl: { type: String, required: true },
    csrfToken: { type: String, required: true },
    errorMessage: { type: String, default: '' },
    successMessage: { type: String, default: '' },
    oldRegister: { type: Object, default: () => ({}) }
});

const name = ref('');
const email = ref('');
const phone = ref('');
const companyName = ref('');
const password = ref('');
const passwordConfirm = ref('');
const submitting = ref(false);

const nameInputRef = ref(null);
const emailInputRef = ref(null);
const phoneInputRef = ref(null);
const passwordInputRef = ref(null);
const passwordConfirmInputRef = ref(null);

onMounted(() => {
    // Populate previously typed fields from server flash or session storage
    if (props.oldRegister && Object.keys(props.oldRegister).length > 0) {
        if (props.oldRegister.name) name.value = props.oldRegister.name;
        if (props.oldRegister.email) email.value = props.oldRegister.email;
        if (props.oldRegister.phone) phone.value = props.oldRegister.phone;
        if (props.oldRegister.company_name) companyName.value = props.oldRegister.company_name;
    } else {
        try {
            const saved = sessionStorage.getItem('dispatch_auth_register');
            if (saved) {
                const parsed = JSON.parse(saved);
                if (parsed.name) name.value = parsed.name;
                if (parsed.email) email.value = parsed.email;
                if (parsed.phone) phone.value = parsed.phone;
                if (parsed.companyName) companyName.value = parsed.companyName;
            }
        } catch {
            // Ignore storage access error
        }
    }
});

function confirmPasswordRule(val) {
    if (!val) return 'Please confirm your password.';
    if (val !== password.value) return 'Password confirmation does not match.';
    return null;
}

function handleSubmit(event) {
    // Normalize phone number before validation and submission
    if (phone.value) {
        phone.value = normalizePhPhone(phone.value);
    }

    const checks = [
        { valid: nameInputRef.value ? nameInputRef.value.validate() : true, ref: nameInputRef },
        { valid: emailInputRef.value ? emailInputRef.value.validate() : true, ref: emailInputRef },
        { valid: phoneInputRef.value ? phoneInputRef.value.validate() : true, ref: phoneInputRef },
        { valid: passwordInputRef.value ? passwordInputRef.value.validate() : true, ref: passwordInputRef },
        { valid: passwordConfirmInputRef.value ? passwordConfirmInputRef.value.validate() : true, ref: passwordConfirmInputRef }
    ];

    const firstInvalid = checks.find((c) => !c.valid);
    if (firstInvalid) {
        event.preventDefault();
        firstInvalid.ref.value?.focus();
        return;
    }

    try {
        sessionStorage.setItem('dispatch_auth_register', JSON.stringify({
            name: name.value,
            email: email.value,
            phone: phone.value,
            companyName: companyName.value
        }));
    } catch {
        // Ignore storage access error
    }

    submitting.value = true;
}
</script>

<template>
    <section class="auth-page auth-page--register" aria-labelledby="auth-title">
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
            <h1 id="auth-title" class="page-title">Create an account</h1>
            <p class="page-description">Set up a customer account to arrange your deliveries.</p>
        </header>

        <div v-if="successMessage" class="alert alert-success" role="status">{{ successMessage }}</div>
        <div v-if="errorMessage" class="alert alert-danger" role="alert">{{ errorMessage }}</div>

        <form class="auth-form" :action="`${baseUrl}register`" method="POST" novalidate @submit="handleSubmit">
            <input type="hidden" name="csrf_token" :value="csrfToken">

            <TextInput
                id="register-name"
                ref="nameInputRef"
                v-model="name"
                name="name"
                type="text"
                label="Full name or primary contact"
                placeholder="e.g. Juan Dela Cruz"
                autocomplete="name"
                minlength="2"
                :required="true"
                :rules="[required, minLength(2)]"
            />

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <TextInput
                        id="register-email"
                        ref="emailInputRef"
                        v-model="email"
                        name="email"
                        type="email"
                        label="Email address"
                        placeholder="name@example.com"
                        autocomplete="email"
                        :required="true"
                        :rules="[required, emailValidator]"
                    />
                </div>
                <div class="col-12 col-md-6">
                    <TextInput
                        id="register-phone"
                        ref="phoneInputRef"
                        v-model="phone"
                        name="phone"
                        type="tel"
                        label="Mobile phone"
                        placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                        helper-text="Philippine mobile for Semaphore SMS delivery alerts."
                        autocomplete="tel"
                        :required="true"
                        :rules="[required, phPhone]"
                    />
                </div>
            </div>

            <TextInput
                id="register-company"
                v-model="companyName"
                name="company_name"
                type="text"
                label="Company or business name (Optional)"
                placeholder="e.g. Acme Trading Corp"
                helper-text="Add this if you are shipping on behalf of a company or store."
                autocomplete="organization"
            />

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <TextInput
                        id="register-password"
                        ref="passwordInputRef"
                        v-model="password"
                        name="password"
                        type="password"
                        label="Password"
                        placeholder="At least 8 characters, letters and numbers"
                        helper-text="At least 8 characters with at least one letter and one number."
                        autocomplete="new-password"
                        minlength="8"
                        :required="true"
                        :show-password-toggle="true"
                        :rules="[required, passwordStrength]"
                    />
                </div>
                <div class="col-12 col-md-6">
                    <TextInput
                        id="register-password-confirm"
                        ref="passwordConfirmInputRef"
                        v-model="passwordConfirm"
                        name="password_confirm"
                        type="password"
                        label="Confirm password"
                        placeholder="Re-enter password"
                        autocomplete="new-password"
                        minlength="8"
                        :required="true"
                        :show-password-toggle="true"
                        :rules="[required, confirmPasswordRule]"
                    />
                </div>
            </div>

            <div class="d-grid my-4">
                <button type="submit" class="btn btn-primary py-2 fw-medium" :disabled="submitting">
                    <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    <span>{{ submitting ? 'Submitting...' : 'Create account' }}</span>
                </button>
            </div>

            <p class="text-center text-muted mb-0">
                Already registered?
                <a :href="`${baseUrl}login`" class="fw-semibold text-decoration-none">Sign in</a>
            </p>
        </form>
    </section>
</template>
