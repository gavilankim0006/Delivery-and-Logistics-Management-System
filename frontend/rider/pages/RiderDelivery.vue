<script setup>
import { ref, onMounted } from 'vue';
import { TextInput, TextArea, FileUpload } from '../../shared/index.js';

const props = defineProps({
    order: { type: Object, required: true },
    proofRecorded: { type: Boolean, default: false },
    baseUrl: { type: String, default: '/' },
    csrfField: { type: String, default: '' },
    csrfToken: { type: String, default: '' },
    locationUrl: { type: String, default: '' }
});

const order = props.order;
const status = order.status === 'picked_up' ? 'Picked up' : 'Awaiting pickup';
const locationSharing = ref(false);
const locationMessage = ref('Location sharing is off. Your location is not tracked in the background.');
let watchId = null;

const recipientName = ref('');
const deliveryNotes = ref('');
const submitting = ref(false);
const pickupSubmitting = ref(false);

const recipientInputRef = ref(null);
const photoUploadRef = ref(null);
const notesTextareaRef = ref(null);

const errors = ref({
    recipient_name: '',
    proof_photo: '',
    note: ''
});

const storageKey = `rider_delivery_draft_${order.order_id || order.id}`;

onMounted(() => {
    if (props.proofRecorded) {
        try {
            sessionStorage.removeItem(storageKey);
        } catch {
            // ignore
        }
        return;
    }

    try {
        const saved = sessionStorage.getItem(storageKey);
        if (saved) {
            const data = JSON.parse(saved);
            if (data.recipient_name) recipientName.value = data.recipient_name;
            if (data.note) deliveryNotes.value = data.note;
        }
    } catch {
        // ignore storage errors
    }
});

function validateRecipient() {
    const val = (recipientName.value || '').trim();
    if (!val) {
        errors.value.recipient_name = 'Recipient name is required.';
        return false;
    }
    if (val.length < 2) {
        errors.value.recipient_name = 'Recipient name must be at least 2 characters.';
        return false;
    }
    if (val.length > 100) {
        errors.value.recipient_name = 'Recipient name cannot exceed 100 characters.';
        return false;
    }
    errors.value.recipient_name = '';
    return true;
}

function validatePhoto() {
    if (photoUploadRef.value) {
        return photoUploadRef.value.validate();
    }
    return true;
}

function validateNotes() {
    if (deliveryNotes.value && deliveryNotes.value.length > 300) {
        errors.value.note = 'Delivery notes cannot exceed 300 characters.';
        return false;
    }
    errors.value.note = '';
    return true;
}

function handlePhotoChange() {
    errors.value.proof_photo = '';
}

function handlePhotoClear() {
    if (errors.value.proof_photo) {
        errors.value.proof_photo = 'Proof of delivery photo is required.';
    }
}

function handleDeliverySubmit(event) {
    const isRecipientValid = validateRecipient();
    const isPhotoValid = validatePhoto();
    const isNotesValid = validateNotes();

    if (!isRecipientValid) {
        event.preventDefault();
        recipientInputRef.value?.focus();
        return;
    }
    if (!isPhotoValid) {
        event.preventDefault();
        photoUploadRef.value?.focus();
        return;
    }
    if (!isNotesValid) {
        event.preventDefault();
        notesTextareaRef.value?.focus();
        return;
    }

    try {
        sessionStorage.setItem(storageKey, JSON.stringify({
            recipient_name: recipientName.value,
            note: deliveryNotes.value
        }));
    } catch {
        // ignore
    }

    submitting.value = true;
}

function handlePickupSubmit() {
    pickupSubmitting.value = true;
}

async function postLocation(payload) {
    const form = new URLSearchParams({ csrf_token: props.csrfToken, ...payload });
    const response = await fetch(props.locationUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: form
    });
    if (!response.ok) throw new Error();
}

async function sendLocation(position) {
    try {
        await postLocation({
            latitude: String(position.coords.latitude),
            longitude: String(position.coords.longitude),
            accuracy_m: String(position.coords.accuracy)
        });
        locationMessage.value = 'Location shared while this page remains open.';
    } catch {
        locationMessage.value = 'Could not share location. Check your connection and try again.';
    }
}

async function toggleLocation() {
    if (locationSharing.value) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
        locationSharing.value = false;
        try {
            await postLocation({ action: 'stop' });
            locationMessage.value = 'Location sharing is off. Your location is not tracked in the background.';
        } catch {
            locationMessage.value = 'Location sharing stopped on this device, but server could not confirm stop.';
        }
        return;
    }
    if (!navigator.geolocation) {
        locationMessage.value = 'Location sharing is not supported by this browser.';
        return;
    }
    watchId = navigator.geolocation.watchPosition(
        sendLocation,
        () => {
            locationMessage.value = 'Location unavailable. Check browser permission and try again.';
        },
        { enableHighAccuracy: true, maximumAge: 15000, timeout: 20000 }
    );
    locationSharing.value = true;
    locationMessage.value = 'Waiting for location permission…';
}
</script>

<template>
    <div class="container page-shell py-4 ds-page">
        <nav aria-label="Breadcrumb" class="mb-3">
            <a :href="`${baseUrl}rider`">My deliveries</a>
            <span class="text-muted"> / {{ order.order_no }}</span>
        </nav>
        <header class="page-header mb-4">
            <div>
                <h1 class="page-title">Delivery {{ order.order_no }}</h1>
                <p class="page-description">Follow pickup and drop-off instructions. Report issues to dispatch.</p>
            </div>
            <span class="status-chip">{{ status }}</span>
        </header>

        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <section class="surface p-3 p-md-4 mb-4" aria-labelledby="route-heading">
                    <h2 id="route-heading" class="h5 mb-3">Route and contacts</h2>
                    <div class="rider-stop mb-4">
                        <h3 class="h6">Pickup</h3>
                        <p class="mb-1">{{ order.pickup_address }}</p>
                        <p class="mb-0 text-muted">
                            {{ order.pickup_contact_name }} ·
                            <a :href="`tel:${String(order.pickup_phone || '').replace(/[^+0-9]/g, '')}`">{{ order.pickup_phone }}</a>
                        </p>
                        <p v-if="order.pickup_notes" class="small mt-2 mb-0">
                            <strong>Instructions:</strong> {{ order.pickup_notes }}
                        </p>
                    </div>
                    <div class="rider-stop">
                        <h3 class="h6">Drop-off</h3>
                        <p class="mb-1">{{ order.dropoff_address }}</p>
                        <p class="mb-0 text-muted">
                            {{ order.dropoff_contact_name }} ·
                            <a :href="`tel:${String(order.dropoff_phone || '').replace(/[^+0-9]/g, '')}`">{{ order.dropoff_phone }}</a>
                        </p>
                        <p v-if="order.dropoff_notes" class="small mt-2 mb-0">
                            <strong>Instructions:</strong> {{ order.dropoff_notes }}
                        </p>
                    </div>
                </section>
            </div>

            <aside class="col-12 col-lg-5">
                <section class="surface p-3 p-md-4">
                    <template v-if="order.status === 'rider_assigned'">
                        <h2 class="h5">Ready for pickup?</h2>
                        <p>Confirm pickup after you have received the parcel.</p>
                        <form method="post" :action="`${baseUrl}rider/orders/${order.order_id}/pickup`" @submit="handlePickupSubmit">
                            <div v-html="csrfField"></div>
                            <button type="submit" class="btn btn-primary w-100 rider-action-btn" :disabled="pickupSubmitting">
                                {{ pickupSubmitting ? 'Submitting...' : 'Confirm pickup' }}
                            </button>
                        </form>
                    </template>

                    <template v-else-if="order.status === 'picked_up' && !proofRecorded">
                        <h2 class="h5 mb-2">Complete delivery</h2>
                        <p class="text-muted small mb-3">Submit recipient details and proof of delivery.</p>

                        <form
                            method="post"
                            enctype="multipart/form-data"
                            :action="`${baseUrl}rider/orders/${order.order_id}/complete`"
                            novalidate
                            class="rider-delivery-form"
                            @submit="handleDeliverySubmit"
                        >
                            <div v-html="csrfField"></div>

                            <TextInput
                                id="recipient-name"
                                ref="recipientInputRef"
                                name="recipient_name"
                                label="Recipient name"
                                v-model="recipientName"
                                type="text"
                                required
                                autocomplete="off"
                                placeholder="Who received the package?"
                                maxlength="100"
                                :error="errors.recipient_name"
                                @blur="validateRecipient"
                            />

                            <FileUpload
                                id="proof-photo"
                                ref="photoUploadRef"
                                name="proof_photo"
                                label="Proof photo"
                                required
                                accept="image/*"
                                capture="environment"
                                :allowed-types="['image/jpeg', 'image/png', 'image/webp', '.jpg', '.jpeg', '.png', '.webp']"
                                :max-size-mb="5"
                                :error="errors.proof_photo"
                                @change="handlePhotoChange"
                                @clear="handlePhotoClear"
                            />

                            <TextArea
                                id="delivery-notes"
                                ref="notesTextareaRef"
                                name="note"
                                label="Delivery notes (optional)"
                                v-model="deliveryNotes"
                                :maxlength="300"
                                :rows="2"
                                placeholder="Add any delivery notes or remarks..."
                                :show-counter="true"
                                :error="errors.note"
                                @blur="validateNotes"
                            />

                            <button
                                type="submit"
                                class="btn btn-primary w-100 rider-action-btn"
                                :disabled="submitting"
                            >
                                <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                {{ submitting ? 'Submitting...' : 'Submit proof and complete delivery' }}
                            </button>
                        </form>
                    </template>

                    <template v-else-if="order.status === 'delivered' && proofRecorded">
                        <h2 class="h5">Delivery complete</h2>
                        <div class="alert alert-success d-flex align-items-center mb-0" role="alert">
                            <i class="bi bi-check-circle-fill me-2 fs-5" aria-hidden="true"></i>
                            <div>Proof has been submitted and this delivery is complete.</div>
                        </div>
                    </template>

                    <div v-if="['rider_assigned', 'picked_up'].includes(order.status)" class="surface p-3 mt-4 border rounded">
                        <h3 class="h6">Share location while this page is open</h3>
                        <p class="small text-muted" role="status" aria-live="polite">{{ locationMessage }}</p>
                        <button
                            type="button"
                            class="btn btn-outline-primary w-100 rider-action-btn"
                            :aria-pressed="locationSharing"
                            @click="toggleLocation"
                        >
                            <i :class="locationSharing ? 'bi bi-geo-alt-fill text-danger me-1' : 'bi bi-geo-alt me-1'" aria-hidden="true"></i>
                            {{ locationSharing ? 'Stop sharing location' : 'Start sharing location' }}
                        </button>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</template>

<style scoped>
.rider-action-btn,
:deep(.shared-input),
:deep(.shared-textarea),
:deep(.form-control),
:deep(.btn) {
    min-height: 44px;
    font-size: 16px;
}

:deep(.file-drop-area) {
    min-height: 72px;
}

.rider-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}
</style>
