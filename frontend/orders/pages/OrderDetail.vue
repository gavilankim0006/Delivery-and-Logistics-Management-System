<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    order: { type: Object, required: true },
    role: { type: String, default: '' },
    controlPassActive: { type: Boolean, default: false },
    trackingLinkUrl: { type: String, default: '' },
    riderLocationEnabled: { type: Boolean, default: false },
    paymentNotice: { type: String, default: '' },
    demoPaymentSimulationEnabled: { type: Boolean, default: false },
    currentUser: { type: Object, default: () => ({}) },
    csrfToken: { type: String, default: '' },
    baseUrl: { type: String, default: '/' },
    flashes: { type: Array, default: () => [] }
});

const liveOrder = ref(props.order);
const order = computed(() => liveOrder.value);

const statusLabels = {
    draft: 'Draft',
    pending_payment: 'Pending payment',
    paid: 'Paid',
    booking: 'Booking',
    rider_assigned: 'Rider assigned',
    picked_up: 'Picked up',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
    failed: 'Failed'
};

const statusClasses = {
    draft: 'draft',
    pending_payment: 'pending',
    paid: 'paid',
    booking: 'booking',
    rider_assigned: 'assigned',
    picked_up: 'picked',
    delivered: 'delivered',
    cancelled: 'cancelled',
    failed: 'failed'
};

const trackerSteps = [
    { label: 'Placed', status: 'draft' },
    { label: 'Paid', status: 'paid' },
    { label: 'Rider assigned', status: 'rider_assigned' },
    { label: 'Picked up', status: 'picked_up' },
    { label: 'Delivered', status: 'delivered' }
];

const trackerProgress = {
    draft: 0,
    pending_payment: 0,
    paid: 1,
    booking: 1,
    rider_assigned: 2,
    picked_up: 3,
    delivered: 4
};

const currentProgress = computed(() => trackerProgress[order.value.status] ?? -1);

const trackerTimes = computed(() => (order.value.timeline || []).reduce((times, entry) => {
    const stage = trackerProgress[entry.to_status];
    if (stage !== undefined) times[stage] = entry.created_at;
    return times;
}, {}));

const lastReached = computed(() => (order.value.timeline || []).reduce((last, entry) => Math.max(last, trackerProgress[entry.to_status] ?? 0), trackerProgress[order.value.status] ?? 0));

const showCancel = computed(() => !['picked_up', 'delivered', 'cancelled', 'failed'].includes(order.value.status));
const showRiderLocation = computed(() => props.riderLocationEnabled && ['rider_assigned', 'picked_up'].includes(order.value.status));

const canShareTracking = computed(() =>
    props.role === 'customer' && ['paid', 'booking', 'rider_assigned', 'picked_up', 'delivered'].includes(order.value.status)
);
const remainingTrip = computed(() => {
    const pickedUp = (order.value.timeline || []).find((entry) => entry.to_status === 'picked_up');
    return {
        pickedUpAt: pickedUp ? pickedUp.created_at : '',
        durationMin: Number(order.value.estimated_duration_min || 0)
    };
});
const etaText = computed(() => {
    if (order.value.status !== 'picked_up') return '';
    const { pickedUpAt, durationMin } = remainingTrip.value;
    if (!pickedUpAt || durationMin <= 0) return '';
    const eta = new Date(new Date(String(pickedUpAt).replace(' ', 'T')).getTime() + durationMin * 60000);
    if (Number.isNaN(eta.getTime())) return '';
    return `Estimated arrival around ${eta.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}`;
});

const cancelReason = ref('');
const isCancelling = ref(false);

const trackingLink = ref(props.trackingLinkUrl || '');
const linkCopied = ref(false);
const showRatingForm = ref(!props.order.rating_value);
const selectedRating = ref(Number(props.order.rating_value || 0));
const ratingComment = ref(props.order.rating_comment || '');
const isRatingSubmitting = ref(false);

function starDisplay(value) {
    const stars = Math.max(0, Math.min(5, Number(value || 0)));
    return '★'.repeat(stars) + '☆'.repeat(5 - stars);
}

function handleRatingSubmit(event) {
    if (!selectedRating.value) {
        event.preventDefault();
        return;
    }
    isRatingSubmitting.value = true;
}

async function copyTrackingLink() {
    try {
        await navigator.clipboard.writeText(trackingLink.value);
        linkCopied.value = true;
        window.setTimeout(() => { linkCopied.value = false; }, 2500);
    } catch {
        window.prompt('Copy this tracking link:', trackingLink.value);
    }
}

const locationText = ref('Checking for a recent rider-shared update…');
const locationLink = ref('');
let locationTimer;
let statusTimer;
let statusRefreshing = false;
let stopped = false;

async function refreshOrder() {
    if (stopped || statusRefreshing || document.hidden) return;
    const orderId = Number(order.value?.id);
    if (!orderId) return;
    statusRefreshing = true;
    try {
        const response = await fetch(`${props.baseUrl}orders/${orderId}/data`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store'
        });
        if (response.ok) {
            const payload = await response.json();
            if (payload.success && payload.data?.order) {
                liveOrder.value = payload.data.order;
                trackingLink.value = payload.data.trackingLinkUrl || '';
            }
        }
    } catch {
        // Keep the current view; the next interval will retry.
    } finally {
        statusRefreshing = false;
    }
    if (!stopped && !['delivered', 'cancelled', 'failed'].includes(order.value?.status)) {
        statusTimer = window.setTimeout(refreshOrder, 15000);
    }
}

function handleVisibilityChange() {
    if (stopped || document.hidden) return;
    window.clearTimeout(statusTimer);
    window.clearTimeout(locationTimer);
    refreshOrder();
    if (showRiderLocation.value) refreshLocation();
}

function action(path) {
    return `${props.baseUrl}orders/${Number(order.value.id)}/${path}`;
}

function money(value) {
    return `₱${Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })}`;
}

function date(value, format = 'medium') {
    if (!value) return '';
    const parsed = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(parsed.getTime())
        ? value
        : parsed.toLocaleString(undefined, { dateStyle: format, timeStyle: 'short' });
}

function postForm(path) {
    return `${props.baseUrl}orders/${Number(order.value.id)}/${path}`;
}

function confirmDemoPayment(event) {
    if (!window.confirm('This only simulates payment for a demo. No PayMongo payment will be verified. Continue?')) {
        event.preventDefault();
    }
}

function confirmDuplicate(event) {
    if (!window.confirm('Copy this delivery into a new draft order? You can review and adjust the details before paying.')) {
        event.preventDefault();
    }
}

function confirmRotateTracking(event) {
    if (!window.confirm('Replace the current tracking link? The old link will stop working immediately.')) {
        event.preventDefault();
    }
}

function handleCancelSubmit(event) {
    if (cancelReason.value.length > 200) {
        event.preventDefault();
        return;
    }
    isCancelling.value = true;
}

async function refreshLocation() {
    if (stopped || document.hidden || !showRiderLocation.value) return;
    try {
        const response = await fetch(action('location'), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store'
        });
        if (!response.ok) {
            locationText.value = 'No recent rider-shared location is available. Delivery progress remains available in the status history.';
            locationLink.value = '';
            return;
        }
        const location = await response.json();
        const age = Number(location.age_seconds);
        if (!location.success || !Number.isFinite(Number(location.latitude)) || !Number.isFinite(Number(location.longitude))) {
            throw new Error('No location');
        }
        if (!Number.isFinite(age) || age < 0 || age > 120) {
            locationText.value = 'The latest rider-shared location is stale. Check the delivery status history for updates.';
            locationLink.value = '';
            return;
        }
        const lat = Number(location.latitude).toFixed(5);
        const lng = Number(location.longitude).toFixed(5);
        locationLink.value = `https://www.openstreetmap.org/?mlat=${encodeURIComponent(lat)}&mlon=${encodeURIComponent(lng)}#map=16/${encodeURIComponent(lat)}/${encodeURIComponent(lng)}`;
        locationText.value = `Latest shared location: ${lat}, ${lng}${location.received_at ? ` · ${date(location.received_at)}` : ''}`;
    } catch {
        locationText.value = 'No recent rider-shared location is available. Delivery progress remains available in the status history.';
        locationLink.value = '';
    }
    if (!stopped) locationTimer = window.setTimeout(refreshLocation, 60000);
}

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    refreshOrder();
    if (showRiderLocation.value) {
        refreshLocation();
    }
});

onUnmounted(() => {
    stopped = true;
    window.clearTimeout(locationTimer);
    window.clearTimeout(statusTimer);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
    <div class="container py-4 ds-page page-shell">
        <div v-for="(flash, index) in flashes" :key="index" class="alert alert-dismissible fade show mb-3" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
            <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
            {{ flash.message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <nav aria-label="Breadcrumb" class="mb-3">
            <a :href="`${baseUrl}orders`">Orders</a>
            <span class="text-muted"> / {{ order.order_no }}</span>
        </nav>

        <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4 ds-page-header page-header">
            <div>
                <p class="ds-eyebrow">Operations</p>
                <h1 class="h3 fw-bold mb-2 page-title">{{ order.order_no }}</h1>
                <p class="page-description">Delivery details, route timeline and fulfilment actions.</p>
                <span class="status-chip ds-status-chip" :class="`status-chip--${statusClasses[order.status] || 'draft'}`">
                    <span aria-hidden="true">●</span> {{ statusLabels[order.status] || 'Unknown' }}
                </span>
            </div>
            <div class="d-flex flex-wrap gap-2 order-header-actions">
                <template v-if="role === 'customer' && ['draft', 'pending_payment'].includes(order.status)">
                    <span v-if="order.payment_status === 'paid'" class="btn btn-success disabled" aria-disabled="true">Payment received</span>
                    <a v-else-if="order.checkout_url" class="btn btn-primary" :href="order.checkout_url" rel="noopener noreferrer">Continue payment · {{ money(order.delivery_fee) }}</a>
                    <form v-else method="post" :action="postForm('checkout')">
                        <input type="hidden" name="csrf_token" :value="csrfToken">
                        <button class="btn btn-primary" type="submit">Pay now · {{ money(order.delivery_fee) }}</button>
                    </form>
                    <form v-if="demoPaymentSimulationEnabled && order.status === 'pending_payment' && order.payment_status === 'pending'" method="post" :action="postForm('simulate-payment')" @submit="confirmDemoPayment">
                        <input type="hidden" name="csrf_token" :value="csrfToken">
                        <button class="btn btn-outline-warning" type="submit">Simulate payment (demo only)</button>
                    </form>
                </template>
                <template v-if="role === 'customer' && order.status === 'draft'">
                    <a class="btn btn-outline-primary" :href="`${baseUrl}orders/${Number(order.id)}/edit`">Edit draft</a>
                    <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteDraftModal">Delete draft</button>
                </template>
                <a
                    v-if="role === 'customer' && order.payment_status === 'paid'"
                    class="btn btn-outline-secondary"
                    :href="`${baseUrl}orders/${Number(order.id)}/receipt`"
                >
                    <i class="bi bi-receipt me-1" aria-hidden="true"></i>Receipt
                </a>
                <button
                    v-if="role === 'customer'"
                    type="button"
                    class="btn btn-outline-secondary"
                    data-bs-toggle="modal"
                    data-bs-target="#saveTemplateModal"
                >
                    <i class="bi bi-bookmark-plus me-1" aria-hidden="true"></i>Save as template
                </button>
                <template v-if="role === 'customer' && ['paid', 'booking', 'rider_assigned', 'picked_up', 'delivered'].includes(order.status)">
                    <form method="post" :action="postForm('control-pass')">
                        <input type="hidden" name="csrf_token" :value="csrfToken">
                        <button class="btn btn-outline-primary" type="submit">{{ controlPassActive ? 'Rotate control pass' : 'Create control pass' }}</button>
                    </form>
                    <form v-if="controlPassActive" method="post" :action="postForm('control-pass/revoke')">
                        <input type="hidden" name="csrf_token" :value="csrfToken">
                        <button class="btn btn-outline-danger" type="submit">Revoke pass</button>
                    </form>
                </template>
                <form v-if="canShareTracking && !trackingLink" method="post" :action="postForm('tracking-link')">
                    <input type="hidden" name="csrf_token" :value="csrfToken">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="bi bi-share me-1" aria-hidden="true"></i>Share tracking
                    </button>
                </form>
                <template v-if="role === 'customer' && ['delivered', 'cancelled', 'failed'].includes(order.status)">
                    <form method="post" :action="postForm('duplicate')" @submit="confirmDuplicate">
                        <input type="hidden" name="csrf_token" :value="csrfToken">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Send again
                        </button>
                    </form>
                </template>
                <a v-if="role === 'customer'" class="btn btn-outline-primary" :href="`${baseUrl}package-workflow`">
                    <i class="bi bi-chat-dots me-1"></i>Handoff messenger
                </a>
                <a class="btn btn-outline-secondary" :href="`${baseUrl}orders`">Back to orders</a>
            </div>
        </header>

        <!-- Shareable public tracking link -->
        <section v-if="role === 'customer' && trackingLink" class="surface ds-panel p-3 p-md-4 mb-4" aria-labelledby="tracking-share-heading">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <h2 class="h5 mb-0" id="tracking-share-heading">
                    <i class="bi bi-share me-2" aria-hidden="true"></i>Share tracking link
                </h2>
                <a class="small text-decoration-none" :href="trackingLink" target="_blank" rel="noopener noreferrer">Preview page</a>
            </div>
            <p class="text-muted small mb-3">
                Anyone with this link can follow this delivery's progress without an account. Stop sharing at any time.
            </p>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <input
                    class="form-control tracking-link-input"
                    type="text"
                    :value="trackingLink"
                    readonly
                    aria-label="Tracking link"
                    @focus="$event.target.select()"
                >
                <button class="btn btn-primary flex-shrink-0" type="button" @click="copyTrackingLink">
                    <i class="bi me-1" :class="linkCopied ? 'bi-check2' : 'bi-clipboard'" aria-hidden="true"></i>
                    {{ linkCopied ? 'Copied' : 'Copy link' }}
                </button>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form method="post" :action="postForm('tracking-link')" @submit="confirmRotateTracking">
                    <input type="hidden" name="csrf_token" :value="csrfToken">
                    <button class="btn btn-outline-secondary btn-sm" type="submit">Rotate link</button>
                </form>
                <form method="post" :action="postForm('tracking-link/revoke')">
                    <input type="hidden" name="csrf_token" :value="csrfToken">
                    <button class="btn btn-outline-danger btn-sm" type="submit">Stop sharing</button>
                </form>
            </div>
        </section>

        <!-- Missing Package Report Dropdown -->
        <div v-if="role === 'customer' && ['paid', 'booking', 'rider_assigned', 'picked_up', 'delivered'].includes(order.status)" class="mb-4">
            <details class="surface p-3 rounded">
                <summary class="fw-semibold text-danger cursor-pointer">
                    <i class="bi bi-exclamation-octagon me-1"></i>Report missing package
                </summary>
                <form method="post" :action="`${baseUrl}package-workflow/report`" class="mt-3" novalidate>
                    <input type="hidden" name="csrf_token" :value="csrfToken">
                    <input type="hidden" name="order_id" :value="order.id">
                    <div class="mb-3 shared-field-wrap">
                        <label for="missing-description" class="form-label shared-label">
                            <span>Describe missing package</span>
                            <span class="text-danger ms-1" aria-hidden="true">*</span>
                        </label>
                        <textarea
                            class="form-control shared-textarea"
                            id="missing-description"
                            name="description"
                            rows="3"
                            minlength="10"
                            maxlength="4000"
                            placeholder="Provide specific details about the missing package (10–4,000 characters)"
                            required
                        ></textarea>
                        <div class="form-text shared-helper-text">Include package contents, packaging description, and last known status (min 10 characters).</div>
                    </div>
                    <button type="submit" class="btn btn-danger">Submit missing report</button>
                </form>
                <p class="small text-muted mt-3 mb-0">
                    Something else wrong with this delivery?
                    <a :href="`${baseUrl}package-workflow?order_id=${Number(order.id)}`">Message our support team</a>.
                </p>
            </details>
        </div>

        <div v-if="paymentNotice === 'return' && order.status !== 'paid'" class="alert alert-info" role="status">
            PayMongo is confirming your payment. This page will update after our signed webhook confirms it; the return link alone does not mark the order paid.
        </div>
        <div v-else-if="paymentNotice === 'cancelled'" class="alert alert-warning" role="status">
            Checkout was cancelled. Your order is still available for payment.
        </div>

        <section class="surface ds-panel p-3 p-md-4 mb-4" aria-labelledby="progress-heading">
            <h2 class="h5" id="progress-heading">Delivery progress</h2>
            <p v-if="etaText" class="text-muted small mb-3" role="status" aria-live="polite">
                <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ etaText }}
            </p>
            <div class="ds-route-tracker" :class="`ds-route-tracker--progress-${Math.max(0, Math.min(4, lastReached))}`" role="group" :aria-label="`Delivery progress for ${order.order_no}`">
                <ol class="ds-route-tracker__stages">
                    <li
                        v-for="(step, index) in trackerSteps"
                        :key="step.status"
                        class="ds-route-tracker__stage"
                        :class="{
                            'is-complete': index <= lastReached,
                            'is-current': !['cancelled', 'failed'].includes(order.status) && index === currentProgress,
                            'is-outcome': ['cancelled', 'failed'].includes(order.status) && index === lastReached
                        }"
                    >
                        <span class="ds-route-tracker__node" aria-hidden="true">
                            {{ ['cancelled', 'failed'].includes(order.status) && index === lastReached ? '×' : (index <= lastReached ? '✓' : '') }}
                        </span>
                        <span class="ds-route-tracker__label">{{ step.label }}</span>
                        <time class="ds-route-tracker__time">
                            {{ trackerTimes[index] ? date(trackerTimes[index]) : (index <= lastReached ? 'Reached' : 'Pending') }}
                        </time>
                    </li>
                </ol>
                <p v-if="['cancelled', 'failed'].includes(order.status)" class="ds-route-tracker__outcome" role="status">
                    This delivery was {{ order.status }}{{ order.cancel_reason ? `: ${order.cancel_reason}` : '.' }}
                </p>
            </div>
        </section>

        <section class="surface ds-panel p-3 p-md-4" aria-label="Delivery details">
            <div class="row g-4">
                <div class="col-12 col-lg-7">
                    <section aria-labelledby="route-heading">
                        <h2 class="h5 mb-3" id="route-heading">Delivery route</h2>
                        <dl class="row mb-0">
                            <dt class="col-sm-3">Pickup</dt>
                            <dd class="col-sm-9">{{ order.pickup_address }}</dd>
                            <dt class="col-sm-3">Contact</dt>
                            <dd class="col-sm-9">{{ order.pickup_contact_name }} · {{ order.pickup_phone }}</dd>
                            <template v-if="order.pickup_notes">
                                <dt class="col-sm-3">Pickup notes</dt>
                                <dd class="col-sm-9 pre-line">{{ order.pickup_notes }}</dd>
                            </template>
                            <dt class="col-sm-3">Drop-off</dt>
                            <dd class="col-sm-9">{{ order.dropoff_address }}</dd>
                            <dt class="col-sm-3">Contact</dt>
                            <dd class="col-sm-9">{{ order.dropoff_contact_name }} · {{ order.dropoff_phone }}</dd>
                            <template v-if="order.dropoff_notes">
                                <dt class="col-sm-3">Drop-off notes</dt>
                                <dd class="col-sm-9 pre-line">{{ order.dropoff_notes }}</dd>
                            </template>
                        </dl>
                    </section>
                </div>
                <div class="col-12 col-lg-5 border-start-lg">
                    <section aria-labelledby="parcel-heading">
                        <h2 class="h5 mb-3" id="parcel-heading">Parcel details</h2>
                        <p class="mb-1 fw-semibold">{{ order.parcel_description || 'Parcel details unavailable' }}</p>
                        <p class="text-muted mb-2">{{ order.parcel_weight }} kg</p>
                        <dl class="row mb-0">
                            <dt class="col-6">Delivery fee</dt>
                            <dd class="col-6 text-end">{{ money(order.delivery_fee) }}</dd>
                            <dt class="col-6">Fulfillment</dt>
                            <dd class="col-6 text-end text-capitalize">{{ String(order.fulfillment_mode || 'internal').replaceAll('_', ' ') }}</dd>
                        </dl>
                    </section>
                </div>
            </div>
        </section>

        <section v-if="role === 'customer' && currentUser.id" class="surface ds-panel p-3 p-md-4 mt-4" aria-labelledby="notification-status-heading">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0" id="notification-status-heading">
                    <i class="bi bi-bell me-2"></i>Delivery notifications
                </h2>
                <a :href="`${baseUrl}profile`" class="small text-decoration-none">Manage preferences</a>
            </div>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="p-3 bg-light rounded d-flex align-items-start gap-3">
                        <div class="fs-4 text-primary"><i class="bi bi-envelope-fill"></i></div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="fw-semibold">Email Updates</span>
                                <span class="badge" :class="currentUser.email && Number(currentUser.email_notifications_opt_in ?? 1) === 1 ? 'bg-success' : 'bg-secondary'">
                                    {{ currentUser.email && Number(currentUser.email_notifications_opt_in ?? 1) === 1 ? 'Active' : 'Disabled' }}
                                </span>
                            </div>
                            <p class="small text-muted mb-0 text-break">
                                {{ currentUser.email && Number(currentUser.email_notifications_opt_in ?? 1) === 1 ? `Sending payment receipts and milestone alerts to ${currentUser.email}.` : 'Email updates are disabled in your profile preferences.' }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-3 bg-light rounded d-flex align-items-start gap-3">
                        <div class="fs-4 text-primary"><i class="bi bi-chat-dots-fill"></i></div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="fw-semibold">SMS Updates (Semaphore)</span>
                                <span class="badge" :class="currentUser.phone && currentUser.delivery_status_sms_opt_in ? 'bg-success' : 'bg-secondary'">
                                    {{ currentUser.phone && currentUser.delivery_status_sms_opt_in ? 'Active' : 'Disabled' }}
                                </span>
                            </div>
                            <p class="small text-muted mb-0 text-break">
                                {{ currentUser.phone && currentUser.delivery_status_sms_opt_in ? `Sending status updates to ${currentUser.phone} when assigned, picked up, and delivered.` : 'SMS updates are disabled. Add your phone and enable delivery notifications in your profile.' }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-3 bg-light rounded d-flex align-items-start gap-3">
                        <div class="fs-4 text-primary"><i class="bi bi-bell-fill"></i></div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="fw-semibold">Push Notifications (Firebase)</span>
                                <span class="badge" :class="currentUser.push_opt_in && currentUser.fcm_token ? 'bg-success' : (currentUser.push_opt_in ? 'bg-warning text-dark' : 'bg-secondary')">
                                    {{ currentUser.push_opt_in && currentUser.fcm_token ? 'Active' : (currentUser.push_opt_in ? 'Device not paired' : 'Disabled') }}
                                </span>
                            </div>
                            <p class="small text-muted mb-0 text-break">
                                {{ currentUser.push_opt_in && currentUser.fcm_token ? 'Real-time browser status alerts are active on this account.' : 'Web push alerts are disabled. ' }}
                                <a v-if="!currentUser.push_opt_in || !currentUser.fcm_token" :href="`${baseUrl}profile`">Enable browser notifications in your profile</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="showRiderLocation" class="surface p-3 p-md-4 mt-4" aria-labelledby="rider-location-heading">
            <h2 class="h5" id="rider-location-heading">Rider-shared location</h2>
            <p class="mb-0" role="status" aria-live="polite">
                {{ locationText }}
                <a v-if="locationLink" :href="locationLink" target="_blank" rel="noopener noreferrer">Open map</a>
            </p>
            <p class="small text-muted mt-2 mb-0">
                Updates are shared periodically while the rider keeps sharing enabled; this is not continuous GPS tracking.
            </p>
        </section>

        <section v-if="['customer', 'dispatcher', 'admin'].includes(role) && order.status === 'delivered' && order.fulfillment_mode === 'internal'" class="surface p-3 p-md-4 mt-4" aria-labelledby="proof-heading">
            <h2 class="h5" id="proof-heading">Proof of delivery</h2>
            <template v-if="order.delivery_proof_id">
                <p class="mb-2">Received by {{ order.delivery_proof_recipient || 'recipient' }}<span v-if="order.delivery_proof_captured_at"> · {{ date(order.delivery_proof_captured_at) }}</span></p>
                <p v-if="order.delivery_proof_note" class="pre-line">{{ order.delivery_proof_note }}</p>
                <a class="btn btn-outline-primary" :href="`${baseUrl}orders/${Number(order.id)}/proof`">View delivery photo</a>
            </template>
            <p v-else class="mb-0">Delivery proof is not available yet. Contact support if you need help.</p>
        </section>

        <section v-if="role === 'customer' && order.status === 'delivered'" class="surface ds-panel p-3 p-md-4 mt-4" aria-labelledby="rating-heading">
            <h2 class="h5" id="rating-heading">
                <i class="bi bi-star me-2" aria-hidden="true"></i>Rate this delivery
            </h2>
            <template v-if="!showRatingForm">
                <p class="mb-1 rating-display" :aria-label="`You rated this delivery ${order.rating_value} out of 5`">
                    {{ starDisplay(order.rating_value) }}
                </p>
                <p v-if="order.rating_comment" class="mb-3 text-muted">“{{ order.rating_comment }}”</p>
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    @click="selectedRating = Number(order.rating_value || 0); showRatingForm = true"
                >
                    Update rating
                </button>
            </template>
            <form v-else method="post" :action="postForm('rating')" @submit="handleRatingSubmit">
                <input type="hidden" name="csrf_token" :value="csrfToken">
                <input type="hidden" name="rating" :value="selectedRating">
                <div class="rating-picker d-flex gap-1 mb-2" role="radiogroup" aria-label="Rating from 1 to 5 stars">
                    <button
                        v-for="star in 5"
                        :key="star"
                        type="button"
                        class="btn btn-link p-0 rating-star"
                        :class="{ 'is-selected': star <= selectedRating }"
                        role="radio"
                        :aria-checked="star === selectedRating"
                        :aria-label="`${star} star${star === 1 ? '' : 's'}`"
                        @click="selectedRating = star"
                    >
                        <i class="bi" :class="star <= selectedRating ? 'bi-star-fill' : 'bi-star'" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="mb-3 shared-field-wrap">
                    <label for="rating-comment" class="form-label shared-label">
                        <span>Feedback <span class="text-muted fw-normal">(optional)</span></span>
                    </label>
                    <textarea
                        id="rating-comment"
                        name="comment"
                        v-model="ratingComment"
                        class="form-control shared-textarea"
                        rows="2"
                        maxlength="500"
                        placeholder="How was the delivery experience?"
                    ></textarea>
                </div>
                <button class="btn btn-primary" type="submit" :disabled="!selectedRating || isRatingSubmitting">
                    <span v-if="isRatingSubmitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    {{ isRatingSubmitting ? 'Saving...' : (order.rating_value ? 'Update rating' : 'Submit rating') }}
                </button>
            </form>
        </section>

        <section class="mt-4" aria-labelledby="history-heading">
            <h2 class="h5 mb-3" id="history-heading">Delivery history</h2>
            <ul class="list-group list-group-flush">
                <li v-for="(entry, index) in order.timeline || []" :key="`${entry.created_at}-${index}`" class="list-group-item d-flex flex-wrap justify-content-between gap-2">
                    <span><strong>{{ statusLabels[entry.to_status] || entry.to_status }}</strong><template v-if="entry.notes"> · {{ entry.notes }}</template></span>
                    <time class="text-muted small" :datetime="new Date(String(entry.created_at).replace(' ', 'T')).toISOString()">{{ date(entry.created_at) }}</time>
                </li>
                <li v-if="!(order.timeline || []).length" class="list-group-item text-muted">No delivery history yet.</li>
            </ul>
        </section>

        <!-- Save as Template Modal -->
        <div v-if="role === 'customer'" class="modal fade" id="saveTemplateModal" tabindex="-1" aria-labelledby="saveTemplateModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" :action="postForm('template')">
                        <input type="hidden" name="csrf_token" :value="csrfToken">
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="saveTemplateModalLabel">Save as template</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small mb-3">
                                Save this order's route, contacts, and parcel details as a reusable template for future deliveries.
                            </p>
                            <div class="shared-field-wrap">
                                <label for="template-name" class="form-label shared-label"><span>Template name</span></label>
                                <input
                                    id="template-name"
                                    name="name"
                                    type="text"
                                    class="form-control shared-input"
                                    maxlength="100"
                                    :value="order.order_no"
                                    required
                                >
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save template</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Draft Modal -->
        <div v-if="role === 'customer' && order.status === 'draft'" class="modal fade" id="deleteDraftModal" tabindex="-1" aria-labelledby="deleteDraftModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="deleteDraftModalLabel">Delete this draft?</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Order {{ order.order_no }} will be hidden from your order list. Its history will be retained.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep draft</button>
                        <form method="post" :action="postForm('delete')">
                            <input type="hidden" name="csrf_token" :value="csrfToken">
                            <button type="submit" class="btn btn-danger">Delete draft</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancel Order Modal -->
        <template v-if="showCancel">
            <div class="mt-4">
                <button type="button" class="btn btn-link text-danger p-0" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                    Cancel order
                </button>
            </div>
            <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="post" :action="postForm('cancel')" @submit="handleCancelSubmit">
                            <input type="hidden" name="csrf_token" :value="csrfToken">
                            <div class="modal-header">
                                <h2 class="modal-title h5" id="cancelOrderModalLabel">Cancel this order?</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-3">
                                    Order <strong>{{ order.order_no }}</strong> will be marked as cancelled and any assigned courier activity will be stopped. This action cannot be undone.
                                </p>
                                <div class="mb-3 shared-field-wrap">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label for="cancel-reason" class="form-label shared-label mb-0">
                                            <span>Reason for cancellation <span class="text-muted fw-normal">(optional)</span></span>
                                        </label>
                                        <span class="textarea-char-counter small text-muted">{{ cancelReason.length }} / 200</span>
                                    </div>
                                    <textarea
                                        id="cancel-reason"
                                        v-model="cancelReason"
                                        name="cancel_reason"
                                        class="form-control shared-textarea"
                                        rows="2"
                                        maxlength="200"
                                        placeholder="e.g. Changed schedule, entered wrong address, or no longer needed"
                                    ></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep order</button>
                                <button type="submit" class="btn btn-danger" :disabled="isCancelling">
                                    <span v-if="isCancelling" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                    <span>{{ isCancelling ? 'Cancelling...' : 'Cancel order' }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.tracking-link-input {
    flex: 1 1 260px;
    min-width: 200px;
    font-size: var(--font-size-14, 14px);
}

.rating-display {
    font-size: 1.5rem;
    letter-spacing: 2px;
    color: #f0a500;
}

.rating-star {
    min-width: 44px;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    line-height: 1;
    color: var(--ink-3, #617687);
    text-decoration: none;
}

.rating-star:hover,
.rating-star:focus,
.rating-star.is-selected {
    color: #f0a500;
    text-decoration: none;
}
</style>
