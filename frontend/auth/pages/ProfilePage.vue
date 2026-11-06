<script setup>
import { ref } from 'vue';
import Notifications from '../../../public/js/notifications.js';
import {
    TextInput,
    required,
    minLength,
    phPhone,
    normalizePhPhone,
    passwordStrength
} from '../../shared/index.js';

const props = defineProps({
    baseUrl: { type: String, required: true },
    csrfToken: { type: String, required: true },
    user: { type: Object, required: true },
    errorMessage: { type: String, default: '' },
    successMessage: { type: String, default: '' },
    vapidKey: { type: String, default: '' }
});

const name = ref(props.user.name || '');
const phone = ref(props.user.phone || '');
const phoneError = ref('');
const password = ref('');
const passwordConfirm = ref('');
const submitting = ref(false);

const nameInputRef = ref(null);
const phoneInputRef = ref(null);
const passwordInputRef = ref(null);
const passwordConfirmInputRef = ref(null);

const emailResult = ref('');
const emailError = ref(false);
const smsResult = ref('');
const smsError = ref(false);
const pushResult = ref('');
const pushError = ref(false);

function validatePhone() {
    if (!phone.value || !phone.value.trim()) {
        phoneError.value = '';
        return true;
    }
    const err = phPhone(phone.value);
    phoneError.value = err || '';
    return !err;
}

function handlePhoneInput() {
    if (phoneError.value) {
        validatePhone();
    }
}

function handlePhoneBlur() {
    validatePhone();
}

function optionalPasswordStrengthRule(val) {
    if (!val) return null;
    return passwordStrength(val);
}

function confirmNewPasswordRule(val) {
    if (!password.value) return null;
    if (!val) return 'Please confirm your new password.';
    if (val !== password.value) return 'Password confirmation does not match.';
    return null;
}

function handleSubmit(event) {
    if (phone.value) {
        phone.value = normalizePhPhone(phone.value);
    }

    const isNameValid = nameInputRef.value ? nameInputRef.value.validate() : true;
    const isPhoneValid = validatePhone();
    const isPasswordValid = passwordInputRef.value ? passwordInputRef.value.validate() : true;
    const isPasswordConfirmValid = passwordConfirmInputRef.value ? passwordConfirmInputRef.value.validate() : true;

    if (!isNameValid) {
        event.preventDefault();
        nameInputRef.value?.focus();
        return;
    }

    if (!isPhoneValid) {
        event.preventDefault();
        phoneInputRef.value?.focus();
        return;
    }

    if (!isPasswordValid) {
        event.preventDefault();
        passwordInputRef.value?.focus();
        return;
    }

    if (!isPasswordConfirmValid) {
        event.preventDefault();
        passwordConfirmInputRef.value?.focus();
        return;
    }

    submitting.value = true;
}

async function testEmail(event) {
    const button = event.currentTarget;
    button.disabled = true;
    emailResult.value = '';
    emailError.value = false;
    try {
        const response = await fetch(`${props.baseUrl}auth/test_email`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ csrf_token: props.csrfToken })
        });
        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch {
            throw new Error(`Test email endpoint returned HTTP ${response.status} with a non-JSON response. Check the server error log.`);
        }
        if (!response.ok || !result.success) throw new Error(result.error || 'Unable to send test email.');
        emailResult.value = result.message || 'Test email sent.';
    } catch (error) {
        emailError.value = true;
        emailResult.value = error?.message || 'Network error while requesting test email.';
    } finally {
        button.disabled = false;
    }
}

async function testSms(event) {
    const button = event.currentTarget;
    button.disabled = true;
    smsResult.value = '';
    smsError.value = false;
    try {
        const response = await fetch(`${props.baseUrl}auth/test_sms`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ csrf_token: props.csrfToken })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || 'Unable to send test SMS.');
        smsResult.value = result.message || 'Test SMS sent.';
    } catch (error) {
        smsError.value = true;
        smsResult.value = error?.message || 'Network error while requesting test SMS.';
    } finally {
        button.disabled = false;
    }
}

async function enableNotifications() {
    pushResult.value = '';
    pushError.value = false;
    const manager = Notifications.default;
    if (!manager?.subscribePush || !props.vapidKey) {
        pushError.value = true;
        pushResult.value = 'Push notifications are not configured.';
        return;
    }
    manager.vapidKey = props.vapidKey;
    manager.saveTokenUrl = `${props.baseUrl}auth/save_token`;
    manager.testPushUrl = `${props.baseUrl}auth/test_push`;
    manager.csrfToken = props.csrfToken;
    try {
        await manager.subscribePush();
        pushResult.value = 'Push notifications enabled.';
    } catch (error) {
        pushError.value = true;
        pushResult.value = error?.message || 'Unable to enable push notifications.';
    }
}

async function sendTestPush() {
    pushResult.value = '';
    pushError.value = false;
    const manager = Notifications.default;
    manager.testPushUrl = `${props.baseUrl}auth/test_push`;
    manager.csrfToken = props.csrfToken;
    try {
        const result = await manager.sendTestPush();
        pushResult.value = result.message || 'Test notification sent.';
    } catch (error) {
        pushError.value = true;
        pushResult.value = error?.message || 'Unable to send test notification.';
    }
}
</script>

<template>
    <div class="container py-4 ds-page page-shell">
        <div class="profile-page">
            <header class="page-header">
                <div>
                    <h1 class="page-title">My profile</h1>
                    <p class="page-description">Manage the contact details associated with your account.</p>
                </div>
            </header>

            <div v-if="successMessage" class="alert alert-success" role="status">{{ successMessage }}</div>
            <div v-if="errorMessage" class="alert alert-danger" role="alert">{{ errorMessage }}</div>

            <form class="surface surface-section" :action="`${baseUrl}profile`" method="POST" novalidate @submit="handleSubmit">
                <input type="hidden" name="csrf_token" :value="csrfToken">

                <section class="account-summary" aria-label="Account details">
                    <div>
                        <span class="form-label shared-label">Role</span>
                        <p class="account-summary__value mb-0">{{ user.role_name || 'Customer' }}</p>
                    </div>
                    <div>
                        <span class="form-label shared-label">Email address</span>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <p class="account-summary__value mb-0">{{ user.email }}</p>
                            <button v-if="user.email" type="button" class="btn btn-sm btn-outline-secondary" @click="testEmail">Test Email</button>
                        </div>
                        <p class="form-text shared-helper-text mb-0">This email cannot be changed here.</p>
                        <p v-if="emailResult" class="form-text mt-1" :class="emailError ? 'text-danger' : 'text-success'" :role="emailError ? 'alert' : 'status'">{{ emailResult }}</p>
                    </div>
                </section>

                <div class="form-section">
                    <TextInput
                        id="profile-name"
                        ref="nameInputRef"
                        v-model="name"
                        name="name"
                        type="text"
                        label="Full name"
                        autocomplete="name"
                        minlength="2"
                        :required="true"
                        :rules="[required, minLength(2)]"
                    />

                    <div class="mb-4 shared-field-wrap" :class="{ 'has-error': Boolean(phoneError) }">
                        <label for="profile-phone" class="form-label shared-label">
                            <span>Phone number</span>
                        </label>
                        <div class="input-group">
                            <input
                                id="profile-phone"
                                ref="phoneInputRef"
                                v-model="phone"
                                class="form-control shared-input"
                                :class="{ 'is-invalid': Boolean(phoneError) }"
                                type="tel"
                                name="phone"
                                autocomplete="tel"
                                inputmode="tel"
                                placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                                :aria-invalid="phoneError ? 'true' : 'false'"
                                aria-describedby="profile-phone-help"
                                @blur="handlePhoneBlur"
                                @input="handlePhoneInput"
                            >
                            <button v-if="phone" type="button" class="btn btn-outline-secondary" @click="testSms">Test SMS</button>
                        </div>
                        <div id="profile-phone-help" class="form-text shared-helper-text mt-1">
                            Used for Semaphore SMS order alerts. Format: Philippine mobile (09XXXXXXXXX or +639XXXXXXXXX).
                        </div>
                        <div v-if="phoneError" id="profile-phone-error" class="invalid-feedback shared-error-text d-block mt-1" role="alert" aria-live="polite">
                            <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ phoneError }}
                        </div>
                        <p v-if="smsResult" class="form-text mt-1" :class="smsError ? 'text-danger' : 'text-success'" :role="smsError ? 'alert' : 'status'">{{ smsResult }}</p>
                    </div>

                    <section v-if="user.role_name === 'Customer' || user.role_name === 'customer'" class="card bg-light border-0 mb-4 p-3" aria-labelledby="notification-heading">
                        <h2 id="notification-heading" class="h6 fw-bold mb-3"><i class="bi bi-bell text-primary" aria-hidden="true"></i> Notification preferences</h2>
                        <div class="form-check mb-3">
                            <input id="email_notifications_opt_in" class="form-check-input" type="checkbox" name="email_notifications_opt_in" value="1" :checked="Number(user.email_notifications_opt_in ?? 1) === 1">
                            <label class="form-check-label" for="email_notifications_opt_in">Receive email receipts, payment confirmations, and delivery status updates.</label>
                            <div class="mt-2"><button type="button" class="btn btn-outline-secondary btn-sm" @click="testEmail">Send test email notification</button></div>
                        </div>
                        <div class="form-check mb-2">
                            <input id="sms_opt_in" class="form-check-input" type="checkbox" name="sms_opt_in" value="1" :checked="Number(user.sms_opt_in ?? 0) === 1">
                            <label class="form-check-label" for="sms_opt_in">Receive critical account SMS alerts.</label>
                        </div>
                        <div class="form-check mb-3">
                            <input id="delivery_status_sms_opt_in" class="form-check-input" type="checkbox" name="delivery_status_sms_opt_in" value="1" :checked="Number(user.delivery_status_sms_opt_in ?? 0) === 1">
                            <label class="form-check-label" for="delivery_status_sms_opt_in">Receive package milestone SMS notifications.</label>
                        </div>
                        <div class="form-check mb-3">
                            <input id="push_opt_in" class="form-check-input" type="checkbox" name="push_opt_in" value="1" :checked="Number(user.push_opt_in ?? 0) === 1">
                            <label class="form-check-label" for="push_opt_in">Receive real-time web push notifications on this device.</label>
                            <div class="mt-2 d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" @click="enableNotifications">Enable browser notifications</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" @click="sendTestPush">Test notification</button>
                            </div>
                            <p v-if="pushResult" class="form-text mt-1" :class="pushError ? 'text-danger' : 'text-success'" :role="pushError ? 'alert' : 'status'">{{ pushResult }}</p>
                        </div>
                    </section>

                    <section class="mb-4 pt-3 border-top" aria-labelledby="password-heading">
                        <h2 id="password-heading" class="h6 fw-bold mb-1">Change password</h2>
                        <p class="text-muted small mb-3">Leave blank if you do not want to change your password.</p>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <TextInput
                                    id="profile-password"
                                    ref="passwordInputRef"
                                    v-model="password"
                                    name="password"
                                    type="password"
                                    label="New password"
                                    placeholder="Leave blank to keep current"
                                    helper-text="At least 8 characters with at least one letter and one number."
                                    autocomplete="new-password"
                                    minlength="8"
                                    :show-password-toggle="true"
                                    :rules="[optionalPasswordStrengthRule]"
                                />
                            </div>
                            <div class="col-12 col-md-6">
                                <TextInput
                                    id="profile-password-confirm"
                                    ref="passwordConfirmInputRef"
                                    v-model="passwordConfirm"
                                    name="password_confirm"
                                    type="password"
                                    label="Confirm new password"
                                    placeholder="Re-enter new password"
                                    autocomplete="new-password"
                                    minlength="8"
                                    :show-password-toggle="true"
                                    :rules="[confirmNewPasswordRule]"
                                />
                            </div>
                        </div>
                    </section>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary" :disabled="submitting">
                        <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        <span>{{ submitting ? 'Submitting...' : 'Save changes' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
