<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    order: { type: Object, required: true },
    role: { type: String, default: '' },
    controlPassActive: { type: Boolean, default: false },
    riderLocationEnabled: { type: Boolean, default: false },
    paymentNotice: { type: String, default: '' },
    demoPaymentSimulationEnabled: { type: Boolean, default: false },
    currentUser: { type: Object, default: () => ({}) },
    csrfToken: { type: String, default: '' },
    baseUrl: { type: String, default: '/' },
    flashes: { type: Array, default: () => [] }
});

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

const currentProgress = computed(() => trackerProgress[props.order.status] ?? -1);

const trackerTimes = computed(() => (props.order.timeline || []).reduce((times, entry) => {
    const stage = trackerProgress[entry.to_status];
    if (stage !== undefined) times[stage] = entry.created_at;
    return times;
}, {}));

const lastReached = computed(() => (props.order.timeline || []).reduce((last, entry) => Math.max(last, trackerProgress[entry.to_status] ?? 0), trackerProgress[props.order.status] ?? 0));

const showCancel = computed(() => !['picked_up', 'delivered', 'cancelled', 'failed'].includes(props.order.status));

const cancelReason = ref('');
const isCancelling = ref(false);

const locationText = ref('Checking for a recent rider-shared update…');
const locationLink = ref('');
let locationTimer;
let stopped = false;

function action(path) {
    return `${props.baseUrl}orders/${Number(props.order.id)}/${path}`;
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
    return `${props.baseUrl}orders/${Number(props.order.id)}/${path}`;
}

function confirmDemoPayment(event) {
    if (!window.confirm('This only simulates payment for a demo. No PayMongo payment will be verified. Continue?')) {
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
    if (stopped || document.hidden) return;
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
    if (props.riderLocationEnabled) {
        document.addEventListener('visibilitychange', refreshLocation);
        refreshLocation();
    }
});

onUnmounted(() => {
    stopped = true;
    window.clearTimeout(locationTimer);
    document.removeEventListener('visibilitychange', refreshLocation);
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
                <a v-if="role === 'customer'" class="btn btn-outline-primary" :href="`${baseUrl}package-workflow`">
                    <i class="bi bi-chat-dots me-1"></i>Handoff messenger
                </a>
                <a class="btn btn-outline-secondary" :href="`${baseUrl}orders`">Back to orders</a>
            </div>
        </header>

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
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="fw-semibold">Email Updates</span>
                                <span class="badge" :class="currentUser.email && Number(currentUser.email_notifications_opt_in ?? 1) === 1 ? 'bg-success' : 'bg-secondary'">
                                    {{ currentUser.email && Number(currentUser.email_notifications_opt_in ?? 1) === 1 ? 'Active' : 'Disabled' }}
                                </span>
                            </div>
                            <p class="small text-muted mb-0">
                                {{ currentUser.email && Number(currentUser.email_notifications_opt_in ?? 1) === 1 ? `Sending payment receipts and milestone alerts to ${currentUser.email}.` : 'Email updates are disabled in your profile preferences.' }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-3 bg-light rounded d-flex align-items-start gap-3">
                        <div class="fs-4 text-primary"><i class="bi bi-chat-dots-fill"></i></div>
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="fw-semibold">SMS Updates (Semaphore)</span>
                                <span class="badge" :class="currentUser.phone && currentUser.delivery_status_sms_opt_in ? 'bg-success' : 'bg-secondary'">
                                    {{ currentUser.phone && currentUser.delivery_status_sms_opt_in ? 'Active' : 'Disabled' }}
                                </span>
                            </div>
                            <p class="small text-muted mb-0">
                                {{ currentUser.phone && currentUser.delivery_status_sms_opt_in ? `Sending status updates to ${currentUser.phone} when assigned, picked up, and delivered.` : 'SMS updates are disabled. Add your phone and enable delivery notifications in your profile.' }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-3 bg-light rounded d-flex align-items-start gap-3">
                        <div class="fs-4 text-primary"><i class="bi bi-bell-fill"></i></div>
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="fw-semibold">Push Notifications (Firebase)</span>
                                <span class="badge" :class="currentUser.push_opt_in && currentUser.fcm_token ? 'bg-success' : (currentUser.push_opt_in ? 'bg-warning text-dark' : 'bg-secondary')">
                                    {{ currentUser.push_opt_in && currentUser.fcm_token ? 'Active' : (currentUser.push_opt_in ? 'Device not paired' : 'Disabled') }}
                                </span>
                            </div>
                            <p class="small text-muted mb-0">
                                {{ currentUser.push_opt_in && currentUser.fcm_token ? 'Real-time browser status alerts are active on this account.' : 'Web push alerts are disabled. ' }}
                                <a v-if="!currentUser.push_opt_in || !currentUser.fcm_token" :href="`${baseUrl}profile`">Enable browser notifications in your profile</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="riderLocationEnabled" class="surface p-3 p-md-4 mt-4" aria-labelledby="rider-location-heading">
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
