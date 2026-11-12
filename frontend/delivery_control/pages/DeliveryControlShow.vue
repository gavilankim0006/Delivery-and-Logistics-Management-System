<script setup>
import { computed } from 'vue';

const props = defineProps({
    order: { type: Object, default: () => ({}) },
    token: { type: String, default: '' },
    eta_minutes: { type: [Number, String, null], default: null },
    baseUrl: { type: String, default: '/' },
    csrfToken: { type: String, default: '' },
    flashes: { type: Array, default: () => [] }
});

const statusLabels = {
    draft: 'Draft',
    pending_payment: 'Awaiting payment',
    paid: 'Paid',
    booking: 'Booking courier',
    rider_assigned: 'Rider assigned',
    picked_up: 'Picked up',
    delivered: 'Delivered',
    failed: 'Needs attention',
    cancelled: 'Cancelled'
};

const cleanBaseUrl = computed(() => {
    let url = props.baseUrl || '/';
    if (!url.endsWith('/')) url += '/';
    return url;
});

function url(path = '') {
    return cleanBaseUrl.value + String(path).replace(/^\/+/, '');
}

const currentStatusLabel = computed(() => {
    return statusLabels[props.order?.status] || 'Status update';
});

const canChange = computed(() => {
    return ['paid', 'booking', 'rider_assigned'].includes(props.order?.status);
});

function formatDate(dateStr) {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    } catch {
        return dateStr;
    }
}
</script>

<template>
    <div class="container page-shell py-4" data-control-pass="true">
        <div v-for="(flash, index) in flashes" :key="index" class="alert alert-dismissible fade show mb-3" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
            <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
            {{ flash.message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <header class="page-header mb-4">
            <div>
                <p class="ds-eyebrow">Delivery control pass</p>
                <h1 class="page-title">Order {{ order.order_no }}</h1>
                <p class="page-description">Shareable delivery status and customer requests.</p>
            </div>
        </header>

        <!-- Current Status -->
        <section class="surface p-3 p-md-4 mb-4">
            <h2 class="h5">Current status</h2>
            <p class="fs-4 fw-semibold mb-1">{{ currentStatusLabel }}</p>
            <p class="text-muted mb-2">Status refreshes when you reload this page.</p>
            <p v-if="eta_minutes !== null" class="mb-0">
                Approximate remaining route time: <strong>{{ eta_minutes }} minutes</strong>. This is a route estimate, not live GPS or a guaranteed arrival time.
            </p>
        </section>

        <!-- Delivery Progress Timeline -->
        <section v-if="order.timeline && order.timeline.length" class="surface p-3 p-md-4 mb-4">
            <h2 class="h5">Delivery progress</h2>
            <ol class="list-group list-group-numbered">
                <li
                    v-for="(entry, index) in order.timeline"
                    :key="index"
                    class="list-group-item d-flex justify-content-between gap-2"
                >
                    <span>{{ statusLabels[entry.to_status] || 'Update' }}</span>
                    <time class="small text-muted" :datetime="entry.created_at">
                        {{ formatDate(entry.created_at) }}
                    </time>
                </li>
            </ol>
        </section>

        <!-- Instructions & Hold Forms -->
        <template v-if="canChange">
            <section class="surface p-3 p-md-4 mb-4">
                <h2 class="h5">Update drop-off instructions</h2>
                <p class="text-muted">New instructions are attached to this order only and shared with dispatch.</p>
                <form :action="url(`delivery-control/${encodeURIComponent(token)}/submit`)" method="post" novalidate>
                    <input v-if="csrfToken" type="hidden" name="csrf_token" :value="csrfToken" />
                    <input type="hidden" name="request_type" value="instruction" />
                    <div class="mb-3 shared-field-wrap">
                        <label class="form-label shared-label" for="instruction">
                            <span>Drop-off instruction</span>
                            <span class="text-danger ms-1" aria-hidden="true">*</span>
                        </label>
                        <textarea
                            class="form-control shared-textarea"
                            id="instruction"
                            name="request_text"
                            rows="3"
                            maxlength="1000"
                            required
                            placeholder="Provide clear instructions for the courier upon arrival"
                            :defaultValue="order.dropoff_notes || ''"
                        ></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Update instructions</button>
                </form>
            </section>

            <section class="surface p-3 p-md-4 mb-4">
                <h2 class="h5">Request a delivery hold</h2>
                <p class="text-muted">Dispatch will review your request. Submitting it does not pause the courier.</p>
                <form :action="url(`delivery-control/${encodeURIComponent(token)}/submit`)" method="post" novalidate>
                    <input v-if="csrfToken" type="hidden" name="csrf_token" :value="csrfToken" />
                    <input type="hidden" name="request_type" value="hold" />
                    <div class="mb-3 shared-field-wrap">
                        <label class="form-label shared-label" for="hold-note">
                            <span>Optional note</span>
                            <span class="text-muted fw-normal ms-1">(optional)</span>
                        </label>
                        <textarea
                            class="form-control shared-textarea"
                            id="hold-note"
                            name="request_text"
                            rows="2"
                            maxlength="1000"
                            placeholder="e.g. Please hold delivery until 3 PM, currently in a meeting"
                        ></textarea>
                    </div>
                    <button class="btn btn-outline-primary" type="submit">Send hold request</button>
                </form>
            </section>
        </template>
        <div v-else class="alert alert-secondary mb-4">
            This delivery is at a stage where changes can no longer be submitted.
        </div>

        <!-- Recent Requests -->
        <section v-if="order.requests && order.requests.length" class="surface p-3 p-md-4">
            <h2 class="h5">Recent requests</h2>
            <ul class="list-group list-group-flush">
                <li
                    v-for="(req, idx) in order.requests"
                    :key="idx"
                    class="list-group-item"
                >
                    <strong>{{ req.request_type === 'hold' ? 'Hold request' : 'Drop-off instruction' }}</strong> · <span class="text-capitalize">{{ req.status }}</span>
                    <p v-if="req.request_text" class="pre-wrap mb-0 mt-1">{{ req.request_text }}</p>
                </li>
            </ul>
        </section>
    </div>
</template>
