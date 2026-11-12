<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { TextInput, TextArea, FileUpload } from '../../shared/index.js';

const props = defineProps({
    order: { type: Object, required: true },
    proofRecorded: { type: Boolean, default: false },
    baseUrl: { type: String, default: '/' },
    csrfField: { type: String, default: '' },
    csrfToken: { type: String, default: '' },
    locationUrl: { type: String, default: '' },
    flashes: { type: Array, default: () => [] }
});

/* ── Constants ────────────────────────────────────────── */
const order = props.order;
const statusLabel = computed(() => {
    switch (order.status) {
        case 'picked_up': return 'Picked up';
        case 'delivered': return 'Delivered';
        default: return 'Awaiting pickup';
    }
});
const statusChipClass = computed(() => {
    return `status-chip status-chip--${order.status}`;
});

/* ── Stepper ──────────────────────────────────────────── */
const stepperSteps = computed(() => {
    const s = order.status;
    return [
        { label: 'Assigned', icon: 'bi-check-lg', complete: true, active: s === 'rider_assigned' },
        { label: 'Picked up', icon: 'bi-check-lg', complete: ['picked_up', 'delivered'].includes(s), active: s === 'picked_up' },
        { label: 'Delivered', icon: 'bi-check-lg', complete: s === 'delivered', active: s === 'delivered' },
    ];
});

/* ── Location sharing ─────────────────────────────────── */
const locationSharing = ref(false);
const locationMessage = ref('Location sharing is off.');
const locationBusy = ref(false);
let watchId = null;

/* ── Form state ───────────────────────────────────────── */
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

/* ── Form completeness ────────────────────────────────── */
const formProgress = computed(() => {
    const steps = [];
    steps.push(!!(recipientName.value || '').trim());
    steps.push(!!(photoUploadRef.value && photoUploadRef.value.hasFile && photoUploadRef.value.hasFile()));
    // notes are optional so always "done"
    steps.push(true);
    return steps;
});

/* ── Draft persistence ────────────────────────────────── */
const storageKey = `rider_delivery_draft_${order.order_id || order.id}`;

onMounted(() => {
    if (props.proofRecorded) {
        try { sessionStorage.removeItem(storageKey); } catch { /* ignore */ }
        return;
    }
    try {
        const saved = sessionStorage.getItem(storageKey);
        if (saved) {
            const data = JSON.parse(saved);
            if (data.recipient_name) recipientName.value = data.recipient_name;
            if (data.note) deliveryNotes.value = data.note;
        }
    } catch { /* ignore */ }
});

/* ── Page fade in ─────────────────────────────────────── */
/* ── Validation ───────────────────────────────────────── */
function validateRecipient() {
    const val = (recipientName.value || '').trim();
    if (!val) { errors.value.recipient_name = 'Recipient name is required.'; return false; }
    if (val.length < 2) { errors.value.recipient_name = 'Recipient name must be at least 2 characters.'; return false; }
    if (val.length > 100) { errors.value.recipient_name = 'Recipient name cannot exceed 100 characters.'; return false; }
    errors.value.recipient_name = '';
    return true;
}

function validatePhoto() {
    if (photoUploadRef.value) return photoUploadRef.value.validate();
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

function handlePhotoChange() { errors.value.proof_photo = ''; }
function handlePhotoClear() {
    if (errors.value.proof_photo) errors.value.proof_photo = 'Proof of delivery photo is required.';
}

/* ── Form submit ──────────────────────────────────────── */
function handleDeliverySubmit(event) {
    const isRecipientValid = validateRecipient();
    const isPhotoValid = validatePhoto();
    const isNotesValid = validateNotes();

    if (!isRecipientValid) { event.preventDefault(); recipientInputRef.value?.focus(); return; }
    if (!isPhotoValid) { event.preventDefault(); photoUploadRef.value?.focus(); return; }
    if (!isNotesValid) { event.preventDefault(); notesTextareaRef.value?.focus(); return; }

    try {
        sessionStorage.setItem(storageKey, JSON.stringify({
            recipient_name: recipientName.value,
            note: deliveryNotes.value
        }));
    } catch { /* ignore */ }
    submitting.value = true;
}

function handlePickupSubmit() { pickupSubmitting.value = true; }

/* ── Location sharing ─────────────────────────────────── */
async function postLocation(payload) {
    const form = new URLSearchParams({ csrf_token: props.csrfToken, ...payload });
    const response = await fetch(props.locationUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
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
        locationMessage.value = 'Could not share location. Check your connection; sharing will retry with your next location update.';
    }
}

async function toggleLocation() {
    if (locationBusy.value) return;
    locationBusy.value = true;
    try {
        await toggleLocationState();
    } finally {
        locationBusy.value = false;
    }
}

async function toggleLocationState() {
    if (locationSharing.value) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
        locationSharing.value = false;
        try {
            await postLocation({ action: 'stop' });
            locationMessage.value = 'Location sharing is off.';
        } catch {
            locationMessage.value = 'Sharing stopped locally, but server could not confirm.';
        }
        return;
    }
    if (!navigator.geolocation) {
        locationMessage.value = 'Location sharing is not supported by this browser.';
        return;
    }
    watchId = navigator.geolocation.watchPosition(
        sendLocation,
        (error) => {
            const messages = {
                1: 'Location permission blocked. Allow location access in browser settings, then try again.',
                2: 'Could not determine your location. Check GPS or network, then retry.',
                3: 'Location request timed out. Move to an area with better signal and retry.'
            };
            locationMessage.value = messages[error?.code] || 'Location unavailable. Check browser permission and connection.';
        },
        { enableHighAccuracy: true, maximumAge: 15000, timeout: 20000 }
    );
    locationSharing.value = true;
    locationMessage.value = 'Waiting for location permission…';
}

/* ── Helpers ──────────────────────────────────────────── */
function mapsUrl(address) {
    return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(address || '')}`;
}
function cleanPhone(phone) {
    return String(phone || '').replace(/[^+0-9]/g, '');
}

/* ── Cleanup ──────────────────────────────────────────── */
onUnmounted(() => {
    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
});
</script>

<template>
    <div
        class="container page-shell py-4 ds-page rider-detail-page"
    >
        <div v-for="(flash, index) in flashes" :key="index" class="alert alert-dismissible fade show mb-3" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
            <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
            {{ flash.message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <!-- ─── Breadcrumb ───────────────────────────────── -->
        <nav aria-label="Breadcrumb" class="rider-breadcrumb mb-3">
            <a :href="`${baseUrl}rider`" class="rider-breadcrumb-link">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>My deliveries
            </a>
            <span class="text-muted"> / {{ order.order_no }}</span>
        </nav>

        <!-- ─── Header ───────────────────────────────────── -->
        <header class="rider-detail-header mb-3">
            <div>
                <p class="ds-eyebrow">Delivery</p>
                <h1 class="page-title mb-1">Delivery {{ order.order_no }}</h1>
                <p class="page-description mb-0">Follow pickup and drop-off instructions.</p>
            </div>
            <span :class="statusChipClass">{{ statusLabel }}</span>
        </header>

        <!-- ─── Progress Stepper ─────────────────────────── -->
        <div class="rider-stepper mb-4" role="group" aria-label="Delivery progress">
            <template v-for="(step, i) in stepperSteps" :key="i">
                <div v-if="i > 0" class="rider-stepper-line" :class="{ 'rider-stepper-line--complete': step.complete }"></div>
                <div
                    class="rider-stepper-step"
                    :class="{
                        'rider-stepper-step--complete': step.complete && !step.active,
                        'rider-stepper-step--active': step.active
                    }"
                >
                    <span class="rider-stepper-dot">
                        <i v-if="step.complete" :class="step.icon" aria-hidden="true"></i>
                        <template v-else>{{ i + 1 }}</template>
                    </span>
                    <span class="rider-stepper-label">{{ step.label }}</span>
                </div>
            </template>
        </div>

        <!-- ─── Main Content Grid ────────────────────────── -->
        <div class="row g-4">
            <!-- Left: Route info -->
            <div class="col-12 col-lg-7">
                <section class="surface p-3 p-md-4 rider-section" aria-labelledby="route-heading">
                    <h2 id="route-heading" class="rider-section-title">
                        <i class="bi bi-signpost-split me-2" aria-hidden="true"></i>Route &amp; contacts
                    </h2>

                    <div class="rider-route-timeline">
                        <!-- Pickup Stop -->
                        <div class="rider-route-stop rider-route-stop--pickup">
                            <span class="rider-route-marker rider-route-marker--pickup">
                                <i class="bi bi-box-arrow-up" aria-hidden="true"></i>
                            </span>
                            <h3>Pickup</h3>
                            <p class="rider-route-address">{{ order.pickup_address }}</p>
                            <p class="rider-route-contact mb-0">
                                {{ order.pickup_contact_name }}
                            </p>
                            <div class="rider-contact-actions">
                                <a
                                    v-if="order.pickup_phone"
                                    :href="`tel:${cleanPhone(order.pickup_phone)}`"
                                    class="rider-contact-btn rider-contact-btn--call"
                                >
                                    <i class="bi bi-telephone-fill" aria-hidden="true"></i>
                                    {{ order.pickup_phone }}
                                </a>
                                <a
                                    :href="mapsUrl(order.pickup_address)"
                                    target="_blank"
                                    rel="noopener"
                                    class="rider-contact-btn rider-contact-btn--navigate"
                                >
                                    <i class="bi bi-cursor-fill" aria-hidden="true"></i>
                                    Navigate
                                </a>
                            </div>
                            <div v-if="order.pickup_notes" class="rider-route-notes">
                                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                                {{ order.pickup_notes }}
                            </div>
                        </div>

                        <!-- Drop-off Stop -->
                        <div class="rider-route-stop rider-route-stop--dropoff">
                            <span class="rider-route-marker rider-route-marker--dropoff">
                                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                            </span>
                            <h3>Drop-off</h3>
                            <p class="rider-route-address">{{ order.dropoff_address }}</p>
                            <p class="rider-route-contact mb-0">
                                {{ order.dropoff_contact_name }}
                            </p>
                            <div class="rider-contact-actions">
                                <a
                                    v-if="order.dropoff_phone"
                                    :href="`tel:${cleanPhone(order.dropoff_phone)}`"
                                    class="rider-contact-btn rider-contact-btn--call"
                                >
                                    <i class="bi bi-telephone-fill" aria-hidden="true"></i>
                                    {{ order.dropoff_phone }}
                                </a>
                                <a
                                    :href="mapsUrl(order.dropoff_address)"
                                    target="_blank"
                                    rel="noopener"
                                    class="rider-contact-btn rider-contact-btn--navigate"
                                >
                                    <i class="bi bi-cursor-fill" aria-hidden="true"></i>
                                    Navigate
                                </a>
                            </div>
                            <div v-if="order.dropoff_notes" class="rider-route-notes">
                                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                                {{ order.dropoff_notes }}
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Right: Action panel -->
            <aside class="col-12 col-lg-5">
                <section class="surface p-3 p-md-4 rider-section">
                    <!-- ── Pickup Confirmation ────────────── -->
                    <template v-if="order.status === 'rider_assigned'">
                        <div class="rider-action-card rider-action-card--pickup">
                            <div class="rider-action-card-icon">
                                <i class="bi bi-box-arrow-up" aria-hidden="true"></i>
                            </div>
                            <h2 class="h5 mb-1">Ready for pickup?</h2>
                            <p class="text-muted small mb-3">Confirm after you've received the parcel from the sender.</p>
                            <form method="post" :action="`${baseUrl}rider/orders/${order.order_id}/pickup`" @submit="handlePickupSubmit">
                                <div v-html="csrfField"></div>
                                <button
                                    type="submit"
                                    class="btn btn-primary w-100 rider-action-btn"
                                    :disabled="pickupSubmitting"
                                >
                                    <i v-if="!pickupSubmitting" class="bi bi-check-circle me-2" aria-hidden="true"></i>
                                    <span v-if="pickupSubmitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                    {{ pickupSubmitting ? 'Confirming…' : 'Confirm pickup' }}
                                </button>
                            </form>
                        </div>
                    </template>

                    <!-- ── Delivery Completion Form ───────── -->
                    <template v-else-if="order.status === 'picked_up' && !proofRecorded">
                        <div class="rider-action-card">
                            <h2 class="h5 mb-1">
                                <i class="bi bi-camera me-2" aria-hidden="true"></i>Complete delivery
                            </h2>
                            <p class="text-muted small mb-3">Submit recipient details and proof of delivery.</p>

                            <!-- form progress indicator -->
                            <div class="rider-form-progress mb-3" aria-hidden="true">
                                <div
                                    v-for="(done, idx) in formProgress"
                                    :key="idx"
                                    class="rider-form-progress-step"
                                    :class="{ 'rider-form-progress-step--done': done }"
                                ></div>
                            </div>

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
                                    :show-preview="false"
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

                                <!-- Desktop submit button (hidden on mobile where sticky bar takes over) -->
                                <button
                                    type="submit"
                                    class="btn btn-primary w-100 rider-action-btn d-none d-lg-flex"
                                    :disabled="submitting"
                                >
                                    <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                    <i v-else class="bi bi-check-circle me-2" aria-hidden="true"></i>
                                    {{ submitting ? 'Submitting…' : 'Submit proof and complete delivery' }}
                                </button>
                                <!-- Mobile submit button (always visible inline on mobile too, sticky bar is supplemental) -->
                                <button
                                    type="submit"
                                    class="btn btn-primary w-100 rider-action-btn d-lg-none"
                                    :disabled="submitting"
                                >
                                    <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                    <i v-else class="bi bi-check-circle me-2" aria-hidden="true"></i>
                                    {{ submitting ? 'Submitting…' : 'Submit proof and complete delivery' }}
                                </button>
                            </form>
                        </div>
                    </template>

                    <!-- ── Delivery Complete ──────────────── -->
                    <template v-else-if="order.status === 'delivered' && proofRecorded">
                        <div class="rider-action-card rider-action-card--success text-center py-4">
                            <div class="rider-complete-icon mb-3">
                                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                            </div>
                            <h2 class="h5 mb-2">Delivery complete</h2>
                            <p class="text-muted small mb-3">Proof has been submitted and this delivery is complete.</p>
                            <a :href="`${baseUrl}rider`" class="btn btn-outline-primary rider-action-btn">
                                <i class="bi bi-arrow-left me-2" aria-hidden="true"></i>
                                Back to deliveries
                            </a>
                        </div>
                    </template>

                    <!-- ── Location Sharing Panel ─────────── -->
                    <div v-if="['rider_assigned', 'picked_up'].includes(order.status)" class="rider-location-panel">
                        <h3 class="h6 mb-2">
                            <i class="bi bi-broadcast me-1" aria-hidden="true"></i>
                            Live location
                        </h3>
                        <div class="rider-location-status" role="status" aria-live="polite">
                            <span v-if="locationSharing" class="rider-location-pulse">
                                <i class="bi bi-geo-alt-fill text-danger" aria-hidden="true"></i>
                            </span>
                            <i v-else class="bi bi-geo-alt text-muted" aria-hidden="true"></i>
                            <span>{{ locationMessage }}</span>
                        </div>
                        <button
                            type="button"
                            class="btn w-100 rider-action-btn"
                            :class="locationSharing ? 'btn-outline-danger' : 'btn-outline-primary'"
                            :aria-pressed="locationSharing"
                            :disabled="locationBusy"
                            @click="toggleLocation"
                        >
                            <span v-if="locationBusy" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                            <i v-else :class="locationSharing ? 'bi bi-stop-circle me-2' : 'bi bi-cursor me-2'" aria-hidden="true"></i>
                            {{ locationBusy ? 'Updating…' : (locationSharing ? 'Stop sharing' : 'Share my location') }}
                        </button>
                    </div>
                </section>
            </aside>
        </div>
    </div>

    <!-- ─── Mobile Sticky Action Bar ─────────────────── -->
    <div
        v-if="['rider_assigned', 'picked_up'].includes(order.status) && !proofRecorded"
        class="rider-sticky-bar d-lg-none"
    >
        <div class="rider-sticky-bar-info">
            <div class="rider-sticky-bar-order">{{ order.order_no }}</div>
            <div class="rider-sticky-bar-status">{{ statusLabel }}</div>
        </div>
        <template v-if="order.status === 'rider_assigned'">
            <form method="post" :action="`${baseUrl}rider/orders/${order.order_id}/pickup`" @submit="handlePickupSubmit" class="d-inline">
                <div v-html="csrfField"></div>
                <button type="submit" class="btn btn-primary rider-action-btn px-3" :disabled="pickupSubmitting">
                    <i class="bi bi-check-circle me-1" aria-hidden="true"></i>
                    Pickup
                </button>
            </form>
        </template>
        <template v-else-if="order.status === 'picked_up'">
            <a href="#proof-photo" class="btn btn-primary rider-action-btn px-3">
                <i class="bi bi-camera me-1" aria-hidden="true"></i>
                Complete
            </a>
        </template>
    </div>
</template>

<style scoped>
/* ── Page readiness ────────────────────────────────────── */
.rider-detail-page {
    opacity: 1;
}

/* ── Breadcrumb ───────────────────────────────────────── */
.rider-breadcrumb-link {
    text-decoration: none;
    color: var(--route);
    font-size: .875rem;
    font-weight: 500;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
}
.rider-breadcrumb-link:hover { text-decoration: underline; }

/* ── Header ───────────────────────────────────────────── */
.rider-detail-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .75rem;
    flex-wrap: wrap;
}

/* ── Sections ─────────────────────────────────────────── */
.rider-section {
    border-radius: .75rem;
    border: 1px solid var(--line);
}
.rider-section-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
}

/* ── Route Timeline ───────────────────────────────────── */
.rider-route-timeline {
    position: relative;
    padding-left: 2.25rem;
}
.rider-route-timeline::before {
    content: '';
    position: absolute;
    left: 13px;
    top: 32px;
    bottom: 32px;
    width: 2px;
    background: repeating-linear-gradient(
        to bottom,
        var(--line) 0px,
        var(--line) 4px,
        transparent 4px,
        transparent 8px
    );
}
.rider-route-stop {
    position: relative;
    padding-bottom: 1.75rem;
}
.rider-route-stop:last-child { padding-bottom: 0; }

.rider-route-marker {
    position: absolute;
    left: -2.25rem;
    top: 0;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .8rem;
    z-index: 1;
}
.rider-route-marker--pickup {
    background: var(--status-success-bg);
    color: var(--success);
    border: 2px solid color-mix(in srgb, var(--success) 35%, var(--line));
}
.rider-route-marker--dropoff {
    background: var(--status-blue-bg);
    color: var(--route);
    border: 2px solid color-mix(in srgb, var(--route) 35%, var(--line));
}

.rider-route-stop h3 {
    font-size: .8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: .375rem;
}
.rider-route-stop--pickup h3 { color: var(--success); }
.rider-route-stop--dropoff h3 { color: var(--route); }

.rider-route-address {
    font-size: .95rem;
    font-weight: 500;
    margin-bottom: .25rem;
}
.rider-route-contact {
    font-size: .85rem;
    color: var(--ink-2);
}

/* ── Contact Buttons ──────────────────────────────────── */
.rider-contact-actions {
    display: flex;
    gap: .5rem;
    margin-top: .5rem;
    flex-wrap: wrap;
}
.rider-contact-btn {
    min-height: 44px;
    min-width: 44px;
    display: inline-flex;
    align-items: center;
    gap: .375rem;
    padding: .375rem .75rem;
    border-radius: .5rem;
    font-size: .8rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid var(--line);
    background: var(--surface);
    color: var(--ink);
    transition: all .2s ease;
}
.rider-contact-btn:hover {
    background: var(--paper);
    border-color: var(--ink-3);
    color: var(--ink);
}
.rider-contact-btn--call {
    border-color: color-mix(in srgb, var(--success) 35%, var(--line));
    color: var(--success);
}
.rider-contact-btn--call:hover { background: var(--status-success-bg); color: var(--success); }
.rider-contact-btn--navigate {
    border-color: color-mix(in srgb, var(--route) 35%, var(--line));
    color: var(--route);
}
.rider-contact-btn--navigate:hover { background: var(--status-blue-bg); color: var(--route); }

/* ── Route Notes ──────────────────────────────────────── */
.rider-route-notes {
    background: var(--paper);
    border-inline-start: 2px solid var(--line);
    border-radius: 0 .375rem .375rem 0;
    padding: .5rem .75rem;
    margin-top: .5rem;
    font-size: .8rem;
    color: var(--ink-2);
}
.rider-route-stop--pickup .rider-route-notes { border-left-color: color-mix(in srgb, var(--success) 35%, var(--line)); }
.rider-route-stop--dropoff .rider-route-notes { border-left-color: color-mix(in srgb, var(--route) 35%, var(--line)); }

/* ── Action Cards ─────────────────────────────────────── */
.rider-action-card {
    padding: .25rem 0;
}
.rider-action-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--status-blue-bg);
    color: var(--route);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-bottom: .75rem;
}
.rider-action-card--pickup .rider-action-card-icon {
    background: var(--status-success-bg);
    color: var(--success);
}

/* ── Stepper ──────────────────────────────────────────── */
.rider-stepper {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: .5rem 0;
}
.rider-stepper-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .25rem;
    min-width: 60px;
}
.rider-stepper-dot {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .75rem;
    font-weight: 700;
    border: 2px solid var(--line);
    background: var(--surface);
    color: var(--ink-3);
    transition: all .25s ease;
    z-index: 1;
}
.rider-stepper-step--complete .rider-stepper-dot {
    background: var(--success);
    border-color: var(--success);
    color: var(--surface);
}
.rider-stepper-step--active .rider-stepper-dot {
    background: var(--route);
    border-color: var(--route);
    color: var(--surface);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--route) 20%, transparent);
}
.rider-stepper-label {
    font-size: .65rem;
    font-weight: 600;
    color: var(--ink-3);
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
}
.rider-stepper-step--complete .rider-stepper-label,
.rider-stepper-step--active .rider-stepper-label {
    color: var(--ink);
}
.rider-stepper-line {
    flex: 1 1 auto;
    height: 2px;
    min-width: 24px;
    max-width: 80px;
    background: var(--line);
    transition: background .25s ease;
}
.rider-stepper-line--complete { background: var(--success); }

/* ── Form Progress ────────────────────────────────────── */
.rider-form-progress {
    display: flex;
    gap: .25rem;
}
.rider-form-progress-step {
    flex: 1 1 0;
    height: 4px;
    border-radius: 2px;
    background: var(--line);
    transition: background .25s ease;
}
.rider-form-progress-step--done { background: var(--success); }

/* ── Location Panel ───────────────────────────────────── */
.rider-location-panel {
    border-radius: .75rem;
    border: 1px solid var(--line);
    background: var(--surface);
    padding: 1rem;
    margin-top: 1rem;
}
.rider-location-status {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .8rem;
    color: var(--ink-2);
    margin-bottom: .75rem;
}

/* ── Location Pulse Animation ─────────────────────────── */
@keyframes riderPulse {
    0%   { box-shadow: 0 0 0 0 color-mix(in srgb, var(--danger) 35%, transparent); }
    70%  { box-shadow: 0 0 0 10px transparent; }
    100% { box-shadow: 0 0 0 0 transparent; }
}
.rider-location-pulse {
    animation: riderPulse 2s infinite;
    border-radius: 50%;
    display: inline-flex;
}

/* ── Completion Success ───────────────────────────────── */
@keyframes checkConfirm {
    from { transform: scale(.92); opacity: .65; }
    to { transform: scale(1); opacity: 1; }
}
.rider-complete-icon {
    font-size: 3rem;
    color: var(--success);
    animation: checkConfirm .24s ease-out;
}

/* ── Mobile Sticky Bar ────────────────────────────────── */
.rider-sticky-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--surface);
    box-shadow: 0 -2px 12px rgba(0,0,0,.08);
    padding: .75rem 1rem;
    z-index: 1030;
    display: flex;
    align-items: center;
    gap: .75rem;
    border-top: 1px solid var(--line);
}
.rider-sticky-bar-info { flex: 1 1 auto; min-width: 0; }
.rider-sticky-bar-order {
    font-size: .8rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rider-sticky-bar-status { font-size: .7rem; color: var(--ink-2); }

/* ── Touch Targets ────────────────────────────────────── */
.rider-action-btn,
:deep(.shared-input),
:deep(.shared-textarea),
:deep(.form-control),
:deep(.btn) {
    min-height: 44px;
    font-size: 16px;
}
:deep(.file-drop-area) { min-height: 72px; }

.rider-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

/* ── Bottom padding on mobile for sticky bar ──────────── */
@media (max-width: 991.98px) {
    .rider-detail-page { padding-bottom: 5.5rem; }
}

/* ── Responsive Stepper ───────────────────────────────── */
@media (max-width: 575.98px) {
    .rider-stepper-step { min-width: 48px; }
    .rider-stepper-label { font-size: .6rem; }
    .rider-stepper-line { min-width: 16px; }
}

@media (prefers-reduced-motion: reduce) {
    .rider-detail-page,
    .rider-location-pulse,
    .rider-complete-icon { animation: none; transition: none; }
}
</style>
