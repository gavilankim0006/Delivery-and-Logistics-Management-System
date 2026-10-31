<script setup>
import { ref } from 'vue';
import Notifications from '../../../public/js/notifications.js';

const props = defineProps({
    baseUrl: { type: String, required: true },
    csrfToken: { type: String, required: true },
    user: { type: Object, required: true },
    errorMessage: { type: String, default: '' },
    successMessage: { type: String, default: '' },
    vapidKey: { type: String, default: '' }
});
const submitting = ref(false);
const smsResult = ref('');
const smsError = ref(false);
const pushResult = ref('');
const pushError = ref(false);

function submit() {
    submitting.value = true;
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
                <div><h1 class="page-title">My profile</h1><p class="page-description">Manage the contact details associated with your account.</p></div>
            </header>
            <div v-if="successMessage" class="alert alert-success" role="status">{{ successMessage }}</div>
            <div v-if="errorMessage" class="alert alert-danger" role="alert">{{ errorMessage }}</div>
            <form class="surface surface-section" :action="`${baseUrl}profile`" method="POST" @submit="submit">
                <input type="hidden" name="csrf_token" :value="csrfToken">
                <section class="account-summary" aria-label="Account details">
                    <div><span class="form-label">Role</span><p class="account-summary__value mb-0">{{ user.role_name || 'Customer' }}</p></div>
                    <div><span class="form-label">Email address</span><p class="account-summary__value mb-0">{{ user.email }}</p><p class="form-text mb-0">This email cannot be changed here.</p></div>
                </section>
                <div class="form-section">
                    <div class="mb-3"><label for="profile-name" class="form-label">Full name</label><input id="profile-name" class="form-control" type="text" name="name" autocomplete="name" :value="user.name" required></div>
                    <div class="mb-4">
                        <label for="profile-phone" class="form-label">Phone number</label>
                        <div class="input-group">
                            <input id="profile-phone" class="form-control" type="tel" name="phone" autocomplete="tel" placeholder="e.g. 09171234567 or +639171234567" :value="user.phone || ''">
                            <button v-if="user.phone" type="button" class="btn btn-outline-secondary" @click="testSms">Test SMS</button>
                        </div>
                        <div class="form-text">Used for Semaphore SMS order alerts. Format: Philippine mobile (09XXXXXXXXX or +639XXXXXXXXX).</div>
                        <p v-if="smsResult" class="form-text" :role="smsError ? 'alert' : 'status'">{{ smsResult }}</p>
                    </div>
                    <section v-if="user.role_name === 'customer'" class="card bg-light border-0 mb-4 p-3" aria-labelledby="notification-heading">
                        <h2 id="notification-heading" class="h6 fw-bold mb-3"><i class="bi bi-bell text-primary" aria-hidden="true"></i> Notification preferences</h2>
                        <div class="p-3 bg-white rounded border mb-3">
                            <p class="fw-semibold mb-2"><i class="bi bi-phone me-1" aria-hidden="true"></i> SMS notifications (Semaphore)</p>
                            <div class="form-check mb-2"><input id="sms_opt_in" class="form-check-input" type="checkbox" name="sms_opt_in" value="1" :checked="Number(user.sms_opt_in) === 1"><label class="form-check-label" for="sms_opt_in">Receive SMS updates for order events.</label></div>
                            <div class="form-check"><input id="delivery_status_sms_opt_in" class="form-check-input" type="checkbox" name="delivery_status_sms_opt_in" value="1" :checked="Number(user.delivery_status_sms_opt_in) === 1"><label class="form-check-label" for="delivery_status_sms_opt_in">Receive delivery status SMS updates.</label></div>
                        </div>
                        <div id="push-notification-controls" class="p-3 bg-white rounded border">
                            <p class="fw-semibold mb-2"><i class="bi bi-app-indicator me-1" aria-hidden="true"></i> Push notifications (Firebase FCM)</p>
                            <div class="form-check mb-3"><input id="push_opt_in" class="form-check-input" type="checkbox" name="push_opt_in" value="1" :checked="Number(user.push_opt_in) === 1"><label class="form-check-label" for="push_opt_in">Receive real-time push notifications for live order milestones.</label></div>
                            <div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-outline-primary" @click="enableNotifications"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>Enable browser notifications</button><button type="button" class="btn btn-outline-secondary" @click="sendTestPush"><i class="bi bi-send me-1" aria-hidden="true"></i>Send test notification</button></div>
                            <p v-if="pushResult" class="form-text mt-2" :role="pushError ? 'alert' : 'status'">{{ pushResult }}</p>
                        </div>
                    </section>
                    <div class="mb-3"><h2 class="h6 fw-bold">Change password</h2><p class="form-text">Leave these blank to keep your current password.</p></div>
                    <div class="row"><div class="col-md-6 mb-3"><label for="profile-password" class="form-label">New password</label><input id="profile-password" class="form-control" type="password" name="password" minlength="8" autocomplete="new-password"></div><div class="col-md-6 mb-3"><label for="profile-password-confirm" class="form-label">Confirm new password</label><input id="profile-password-confirm" class="form-control" type="password" name="password_confirm" minlength="8" autocomplete="new-password"></div></div>
                    <div class="d-flex justify-content-end gap-2 mt-4"><a :href="baseUrl" class="btn btn-outline-secondary">Cancel</a><button type="submit" class="btn btn-primary px-4" :disabled="submitting">{{ submitting ? 'Saving…' : 'Save changes' }}</button></div>
                </div>
            </form>
        </div>
    </div>
</template>
