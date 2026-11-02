<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { phPhone, normalizePhPhone } from '../../shared/validators.js';

const props = defineProps({
    mode: { type: String, default: 'create' },
    orderId: { type: Number, default: null },
    formData: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' },
    csrfField: { type: String, default: '' },
    csrfToken: { type: String, default: '' },
    user: { type: Object, default: () => ({}) },
    rates: { type: Object, default: () => ({}) },
    mapsKey: { type: String, default: '' },
    routeApiEndpoint: { type: String, default: '' },
    baseUrl: { type: String, default: '/' }
});

const isEdit = props.mode === 'edit';
const formAction = isEdit ? `${props.baseUrl}orders/${props.orderId}/edit` : `${props.baseUrl}orders`;

const submitting = ref(false);
const formErrors = ref({ ...props.errors });

// Form field states
const pickupNotes = ref(props.formData.pickup_notes || '');
const dropoffNotes = ref(props.formData.dropoff_notes || '');
const parcelDesc = ref(props.formData.parcel_description || '');
const parcelWeight = ref(props.formData.parcel_weight || '1.0');
const parcelSize = ref(props.formData.parcel_size || 'M');

const pickupContactPhone = ref(props.formData.pickup_contact_phone || props.user.phone || '');
const dropoffContactPhone = ref(props.formData.dropoff_contact_phone || props.user.phone || '');

const initialDistance = document.getElementById('distance_km')?.value || props.formData.distance_km || '';
const initialCoords = Object.fromEntries(
    ['pickup_lat', 'pickup_lng', 'dropoff_lat', 'dropoff_lng'].map((key) => [
        key,
        document.getElementById(key)?.value || props.formData[key] || ''
    ])
);

function value(key) {
    if (key === 'pickup_address') return props.formData.pickup_address || '';
    if (key === 'dropoff_address') return props.formData.dropoff_address || '';
    if (key === 'pickup_coords') {
        const lat = initialCoords['pickup_lat'];
        const lng = initialCoords['pickup_lng'];
        if (lat !== '' && lng !== '' && lat != null && lng != null && !isNaN(Number(lat)) && !isNaN(Number(lng))) {
            return `${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;
        }
        return props.formData['pickup_coords'] || '';
    }
    if (key === 'dropoff_coords') {
        const lat = initialCoords['dropoff_lat'];
        const lng = initialCoords['dropoff_lng'];
        if (lat !== '' && lng !== '' && lat != null && lng != null && !isNaN(Number(lat)) && !isNaN(Number(lng))) {
            return `${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;
        }
        return props.formData['dropoff_coords'] || '';
    }
    if (key === 'distance_km' && initialDistance) return initialDistance;
    if (props.formData[key] != null) return props.formData[key];
    if (key.endsWith('_contact_name')) return props.user.name || '';
    if (key === 'pickup_contact_phone') return pickupContactPhone.value;
    if (key === 'dropoff_contact_phone') return dropoffContactPhone.value;
    if (key === 'parcel_weight') return parcelWeight.value;
    return '';
}

function getError(key) {
    return formErrors.value[key] || '';
}

function clearError(key) {
    if (formErrors.value[key]) {
        delete formErrors.value[key];
    }
}

function validateField(id, val, ruleFn, errorMsg) {
    const error = ruleFn ? ruleFn(val) : (!val || !String(val).trim() ? errorMsg : null);
    if (error) {
        formErrors.value[id] = error;
        return false;
    }
    clearError(id);
    return true;
}

function handleSubmit(event) {
    const form = event.target;

    // Read current values directly from DOM inputs to ensure Google Places autocomplete & typed updates are captured
    const pAddressEl = document.getElementById('pickup_address');
    const pNameEl = document.getElementById('pickup_contact_name');
    const pPhoneEl = document.getElementById('pickup_contact_phone');
    const pNotesEl = document.getElementById('pickup_notes');

    const dAddressEl = document.getElementById('dropoff_address');
    const dNameEl = document.getElementById('dropoff_contact_name');
    const dPhoneEl = document.getElementById('dropoff_contact_phone');
    const dNotesEl = document.getElementById('dropoff_notes');

    const descEl = document.getElementById('parcel_description');
    const weightEl = document.getElementById('parcel_weight');
    const sizeEl = document.getElementById('parcel_size');

    // Normalize phone numbers before submission
    if (pPhoneEl && pPhoneEl.value) {
        pPhoneEl.value = normalizePhPhone(pPhoneEl.value);
    }
    if (dPhoneEl && dPhoneEl.value) {
        dPhoneEl.value = normalizePhPhone(dPhoneEl.value);
    }

    const checks = [
        // Pickup section
        { el: pAddressEl, valid: validateField('pickup_address', pAddressEl?.value, null, 'Pickup address is required.') },
        { el: pNameEl, valid: validateField('pickup_contact_name', pNameEl?.value, (v) => (!v || v.trim().length < 2 ? 'Pickup contact name must be at least 2 characters.' : null)) },
        { el: pPhoneEl, valid: validateField('pickup_contact_phone', pPhoneEl?.value, phPhone) },
        { el: pNotesEl, valid: validateField('pickup_notes', pNotesEl?.value, (v) => (v && v.length > 300 ? 'Pickup notes cannot exceed 300 characters.' : null)) },

        // Drop-off section
        { el: dAddressEl, valid: validateField('dropoff_address', dAddressEl?.value, null, 'Drop-off address is required.') },
        { el: dNameEl, valid: validateField('dropoff_contact_name', dNameEl?.value, (v) => (!v || v.trim().length < 2 ? 'Drop-off contact name must be at least 2 characters.' : null)) },
        { el: dPhoneEl, valid: validateField('dropoff_contact_phone', dPhoneEl?.value, phPhone) },
        { el: dNotesEl, valid: validateField('dropoff_notes', dNotesEl?.value, (v) => (v && v.length > 300 ? 'Drop-off notes cannot exceed 300 characters.' : null)) },

        // Parcel section
        { el: descEl, valid: validateField('parcel_description', descEl?.value, (v) => (!v || !v.trim() ? 'Parcel description is required.' : (v.length > 150 ? 'Parcel description cannot exceed 150 characters.' : null))) },
        { el: weightEl, valid: validateField('parcel_weight', weightEl?.value, (v) => {
            const num = parseFloat(v);
            if (isNaN(num) || num < 0.1) return 'Weight must be at least 0.1 kg.';
            if (num > 1000) return 'Weight cannot exceed 1000 kg.';
            return null;
        }) },
        { el: sizeEl, valid: validateField('parcel_size', sizeEl?.value, (v) => (!v ? 'Please select a parcel size.' : null)) }
    ];

    const firstInvalid = checks.find((c) => !c.valid);
    if (firstInvalid) {
        event.preventDefault();
        firstInvalid.el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstInvalid.el?.focus();
        return;
    }

    submitting.value = true;
}

let initMap;
let updateEstimate;
onMounted(() => {
    const distanceInput = document.getElementById('distance_km');
    const weightInput = document.getElementById('parcel_weight');
    const r = props.rates;

    updateEstimate = function () {
        const d = Number(distanceInput?.value) || 0;
        const w = Number(weightInput?.value) || 1;
        const summaryDistance = document.getElementById('summary-distance');
        const summaryFee = document.getElementById('summary-fee');
        if (summaryDistance) summaryDistance.textContent = d ? `${d.toFixed(1)} km` : 'Not calculated';
        if (summaryFee) {
            if (!d) {
                summaryFee.textContent = '—';
            } else {
                const fee = Number(r.base_delivery_fee || 60)
                    + Math.max(0, d - Number(r.free_distance_km || 2)) * Number(r.per_km_rate || 12.5)
                    + Math.max(0, w - Number(r.free_weight_kg || 1)) * Number(r.per_kg_rate || 5);
                summaryFee.textContent = `₱${fee.toFixed(2)}`;
            }
        }
    };

    initMap = function () {
        if (typeof window.DeliveryMapManager === 'function') {
            window.deliveryMapManager = new window.DeliveryMapManager({
                mapElementId: 'delivery-map',
                pickupInputId: 'pickup_address',
                dropoffInputId: 'dropoff_address',
                pickupCoordsId: 'pickup_coords',
                dropoffCoordsId: 'dropoff_coords',
                weightInputId: 'parcel_weight',
                distanceDisplayId: 'summary-distance',
                durationDisplayId: 'summary-duration',
                feeDisplayId: 'summary-fee',
                calculateEndpoint: props.routeApiEndpoint
            });
        }
    };

    window.initDeliveryMap = initMap;
    if (window.google?.maps && typeof window.DeliveryMapManager === 'function') {
        initMap();
    }
    weightInput?.addEventListener('input', updateEstimate);
});

onUnmounted(() => {
    const weightInput = document.getElementById('parcel_weight');
    weightInput?.removeEventListener('input', updateEstimate);
});

function feeEstimate() {
    const distance = Number(props.formData.distance_km) || 0;
    const weight = Number(props.formData.parcel_weight) || 1;
    if (!distance) return 'Calculated securely when saved';
    const r = props.rates;
    const fee = Number(r.base_delivery_fee || 60)
        + Math.max(0, distance - Number(r.free_distance_km || 2)) * Number(r.per_km_rate || 12.5)
        + Math.max(0, weight - Number(r.free_weight_kg || 1)) * Number(r.per_kg_rate || 5);
    return `Estimated ₱${fee.toFixed(2)} · final fee confirmed on save`;
}
</script>

<template>
    <div class="container py-4 ds-page page-shell">
        <nav aria-label="Breadcrumb" class="breadcrumb-line mb-3">
            <a :href="`${baseUrl}orders`">My orders</a>
            <span aria-hidden="true"> / </span>
            <span aria-current="page">{{ isEdit ? 'Edit draft order' : 'New delivery' }}</span>
        </nav>

        <header class="page-header mb-4">
            <div>
                <h1 class="page-title">{{ isEdit ? 'Edit draft order' : 'New delivery' }}</h1>
                <p class="page-description">
                    {{ isEdit ? 'Update delivery details while this order remains a draft. The delivery fee is recalculated when saved.' : 'Enter both stops and parcel details. Your delivery fee is calculated securely on save.' }}
                </p>
            </div>
        </header>

        <div v-if="formError" class="alert alert-danger" role="alert">{{ formError }}</div>

        <form method="post" :action="formAction" novalidate @submit="handleSubmit">
            <div v-html="csrfField"></div>
            <input
                v-for="key in ['pickup_lat', 'pickup_lng', 'dropoff_lat', 'dropoff_lng', 'hidden_distance_km', 'hidden_estimated_duration_min', 'hidden_delivery_fee', 'hidden_pickup_lat', 'hidden_pickup_lng', 'hidden_dropoff_lat', 'hidden_dropoff_lng']"
                :id="key"
                :key="key"
                type="hidden"
                :name="key.startsWith('hidden_') ? key.slice(7) : key"
                :value="initialCoords[key] || formData[key.replace(/^hidden_/, '')] || ''"
            >

            <div class="row g-4">
                <div class="col-12 col-lg-7">
                    <!-- Pickup Section -->
                    <section class="surface surface-section mb-4" aria-labelledby="pickup-heading">
                        <h2 id="pickup-heading" class="h5 mb-3">Pickup</h2>

                        <!-- Pickup Address (Google Places Autocomplete attached to this exact input) -->
                        <div class="mb-3 shared-field-wrap" :class="{ 'has-error': Boolean(getError('pickup_address')) }">
                            <label class="form-label shared-label" for="pickup_address">
                                <span>Address</span>
                                <span class="text-danger ms-1" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="pickup_address"
                                class="form-control shared-input"
                                :class="{ 'is-invalid': Boolean(getError('pickup_address')) }"
                                type="text"
                                name="pickup_address"
                                maxlength="500"
                                :value="value('pickup_address')"
                                placeholder="e.g. Makati CBD, Ayala Avenue"
                                required
                                :aria-invalid="getError('pickup_address') ? 'true' : 'false'"
                                :aria-describedby="getError('pickup_address') ? 'pickup_address-error' : null"
                                @input="clearError('pickup_address')"
                            >
                            <div v-if="getError('pickup_address')" id="pickup_address-error" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                                <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ getError('pickup_address') }}
                            </div>
                        </div>

                        <!-- Pickup Coordinate Address -->
                        <div class="mb-3 shared-field-wrap">
                            <label class="form-label shared-label" for="pickup_coords">
                                <span>Coordinate address</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">
                                    <i class="bi bi-geo-alt-fill text-danger" aria-hidden="true"></i>
                                </span>
                                <input
                                    id="pickup_coords"
                                    class="form-control shared-input bg-light font-monospace"
                                    type="text"
                                    name="pickup_coords"
                                    :value="value('pickup_coords')"
                                    readonly
                                    placeholder="Click map or drag pin to set coordinates"
                                >
                                <button
                                    class="btn btn-outline-secondary"
                                    type="button"
                                    data-clear-stop="pickup"
                                    title="Clear pinpoint"
                                >
                                    Clear pin
                                </button>
                            </div>
                            <div class="form-text shared-helper-text mt-1">
                                Read-only coordinate address. Inputted only by map pinpoints. Coordinates take priority on the map.
                            </div>
                        </div>

                        <!-- Pickup Contact Name -->
                        <div class="mb-3 shared-field-wrap" :class="{ 'has-error': Boolean(getError('pickup_contact_name')) }">
                            <label class="form-label shared-label" for="pickup_contact_name">
                                <span>Contact name</span>
                                <span class="text-danger ms-1" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="pickup_contact_name"
                                class="form-control shared-input"
                                :class="{ 'is-invalid': Boolean(getError('pickup_contact_name')) }"
                                type="text"
                                name="pickup_contact_name"
                                maxlength="100"
                                :value="value('pickup_contact_name')"
                                placeholder="Contact person at pickup"
                                required
                                :aria-invalid="getError('pickup_contact_name') ? 'true' : 'false'"
                                :aria-describedby="getError('pickup_contact_name') ? 'pickup_contact_name-error' : null"
                                @input="clearError('pickup_contact_name')"
                            >
                            <div v-if="getError('pickup_contact_name')" id="pickup_contact_name-error" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                                <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ getError('pickup_contact_name') }}
                            </div>
                        </div>

                        <!-- Pickup Contact Phone -->
                        <div class="mb-3 shared-field-wrap" :class="{ 'has-error': Boolean(getError('pickup_contact_phone')) }">
                            <label class="form-label shared-label" for="pickup_contact_phone">
                                <span>Contact phone</span>
                                <span class="text-danger ms-1" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="pickup_contact_phone"
                                class="form-control shared-input"
                                :class="{ 'is-invalid': Boolean(getError('pickup_contact_phone')) }"
                                type="tel"
                                name="pickup_contact_phone"
                                maxlength="30"
                                autocomplete="tel"
                                :value="value('pickup_contact_phone')"
                                placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                                required
                                :aria-invalid="getError('pickup_contact_phone') ? 'true' : 'false'"
                                aria-describedby="pickup_contact_phone-help"
                                @input="clearError('pickup_contact_phone')"
                            >
                            <div id="pickup_contact_phone-help" class="form-text shared-helper-text mt-1">
                                Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).
                            </div>
                            <div v-if="getError('pickup_contact_phone')" id="pickup_contact_phone-error" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                                <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ getError('pickup_contact_phone') }}
                            </div>
                        </div>

                        <!-- Pickup Notes -->
                        <div class="mb-3 shared-field-wrap" :class="{ 'has-error': Boolean(getError('pickup_notes')) }">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label shared-label mb-0" for="pickup_notes">
                                    <span>Pickup notes <span class="text-muted fw-normal">(optional)</span></span>
                                </label>
                                <span class="textarea-char-counter small text-muted">{{ pickupNotes.length }} / 300</span>
                            </div>
                            <textarea
                                id="pickup_notes"
                                v-model="pickupNotes"
                                class="form-control shared-textarea"
                                :class="{ 'is-invalid': Boolean(getError('pickup_notes')) }"
                                name="pickup_notes"
                                maxlength="300"
                                rows="2"
                                placeholder="e.g. Landmark, building lobby instructions, floor/unit number"
                                @input="clearError('pickup_notes')"
                            ></textarea>
                            <div v-if="getError('pickup_notes')" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                                <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ getError('pickup_notes') }}
                            </div>
                        </div>
                    </section>

                    <!-- Drop-off Section -->
                    <section class="surface surface-section mb-4" aria-labelledby="dropoff-heading">
                        <h2 id="dropoff-heading" class="h5 mb-3">Drop-off</h2>

                        <!-- Drop-off Address (Google Places Autocomplete attached to this exact input) -->
                        <div class="mb-3 shared-field-wrap" :class="{ 'has-error': Boolean(getError('dropoff_address')) }">
                            <label class="form-label shared-label" for="dropoff_address">
                                <span>Address</span>
                                <span class="text-danger ms-1" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="dropoff_address"
                                class="form-control shared-input"
                                :class="{ 'is-invalid': Boolean(getError('dropoff_address')) }"
                                type="text"
                                name="dropoff_address"
                                maxlength="500"
                                :value="value('dropoff_address')"
                                placeholder="e.g. BGC High Street, Taguig"
                                required
                                :aria-invalid="getError('dropoff_address') ? 'true' : 'false'"
                                :aria-describedby="getError('dropoff_address') ? 'dropoff_address-error' : null"
                                @input="clearError('dropoff_address')"
                            >
                            <div v-if="getError('dropoff_address')" id="dropoff_address-error" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                                <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ getError('dropoff_address') }}
                            </div>
                        </div>

                        <!-- Drop-off Coordinate Address -->
                        <div class="mb-3 shared-field-wrap">
                            <label class="form-label shared-label" for="dropoff_coords">
                                <span>Coordinate address</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">
                                    <i class="bi bi-geo-alt-fill text-danger" aria-hidden="true"></i>
                                </span>
                                <input
                                    id="dropoff_coords"
                                    class="form-control shared-input bg-light font-monospace"
                                    type="text"
                                    name="dropoff_coords"
                                    :value="value('dropoff_coords')"
                                    readonly
                                    placeholder="Click map or drag pin to set coordinates"
                                >
                                <button
                                    class="btn btn-outline-secondary"
                                    type="button"
                                    data-clear-stop="dropoff"
                                    title="Clear pinpoint"
                                >
                                    Clear pin
                                </button>
                            </div>
                            <div class="form-text shared-helper-text mt-1">
                                Read-only coordinate address. Inputted only by map pinpoints. Coordinates take priority on the map.
                            </div>
                        </div>

                        <!-- Drop-off Contact Name -->
                        <div class="mb-3 shared-field-wrap" :class="{ 'has-error': Boolean(getError('dropoff_contact_name')) }">
                            <label class="form-label shared-label" for="dropoff_contact_name">
                                <span>Contact name</span>
                                <span class="text-danger ms-1" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="dropoff_contact_name"
                                class="form-control shared-input"
                                :class="{ 'is-invalid': Boolean(getError('dropoff_contact_name')) }"
                                type="text"
                                name="dropoff_contact_name"
                                maxlength="100"
                                :value="value('dropoff_contact_name')"
                                placeholder="Recipient name"
                                required
                                :aria-invalid="getError('dropoff_contact_name') ? 'true' : 'false'"
                                :aria-describedby="getError('dropoff_contact_name') ? 'dropoff_contact_name-error' : null"
                                @input="clearError('dropoff_contact_name')"
                            >
                            <div v-if="getError('dropoff_contact_name')" id="dropoff_contact_name-error" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                                <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ getError('dropoff_contact_name') }}
                            </div>
                        </div>

                        <!-- Drop-off Contact Phone -->
                        <div class="mb-3 shared-field-wrap" :class="{ 'has-error': Boolean(getError('dropoff_contact_phone')) }">
                            <label class="form-label shared-label" for="dropoff_contact_phone">
                                <span>Contact phone</span>
                                <span class="text-danger ms-1" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="dropoff_contact_phone"
                                class="form-control shared-input"
                                :class="{ 'is-invalid': Boolean(getError('dropoff_contact_phone')) }"
                                type="tel"
                                name="dropoff_contact_phone"
                                maxlength="30"
                                autocomplete="tel"
                                :value="value('dropoff_contact_phone')"
                                placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                                required
                                :aria-invalid="getError('dropoff_contact_phone') ? 'true' : 'false'"
                                aria-describedby="dropoff_contact_phone-help"
                                @input="clearError('dropoff_contact_phone')"
                            >
                            <div id="dropoff_contact_phone-help" class="form-text shared-helper-text mt-1">
                                Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).
                            </div>
                            <div v-if="getError('dropoff_contact_phone')" id="dropoff_contact_phone-error" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                                <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ getError('dropoff_contact_phone') }}
                            </div>
                        </div>

                        <!-- Drop-off Notes -->
                        <div class="mb-3 shared-field-wrap" :class="{ 'has-error': Boolean(getError('dropoff_notes')) }">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label shared-label mb-0" for="dropoff_notes">
                                    <span>Drop-off notes <span class="text-muted fw-normal">(optional)</span></span>
                                </label>
                                <span class="textarea-char-counter small text-muted">{{ dropoffNotes.length }} / 300</span>
                            </div>
                            <textarea
                                id="dropoff_notes"
                                v-model="dropoffNotes"
                                class="form-control shared-textarea"
                                :class="{ 'is-invalid': Boolean(getError('dropoff_notes')) }"
                                name="dropoff_notes"
                                maxlength="300"
                                rows="2"
                                placeholder="e.g. Leave with guard, ring doorbell, delivery drop box"
                                @input="clearError('dropoff_notes')"
                            ></textarea>
                            <div v-if="getError('dropoff_notes')" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                                <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ getError('dropoff_notes') }}
                            </div>
                        </div>
                    </section>

                    <!-- Parcel Section -->
                    <section class="surface surface-section mb-4" aria-labelledby="parcel-heading">
                        <h2 id="parcel-heading" class="h5 mb-3">Parcel</h2>

                        <div class="mb-3 shared-field-wrap" :class="{ 'has-error': Boolean(getError('parcel_description')) }">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label shared-label mb-0" for="parcel_description">
                                    <span>Description</span>
                                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                                </label>
                                <span class="textarea-char-counter small text-muted">{{ parcelDesc.length }} / 150</span>
                            </div>
                            <input
                                id="parcel_description"
                                v-model="parcelDesc"
                                name="parcel_description"
                                class="form-control shared-input"
                                :class="{ 'is-invalid': Boolean(getError('parcel_description')) }"
                                maxlength="150"
                                placeholder="e.g. Documents, Electronics, Clothing, Boxed gift"
                                required
                                :aria-invalid="getError('parcel_description') ? 'true' : 'false'"
                                @input="clearError('parcel_description')"
                            >
                            <div v-if="getError('parcel_description')" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                                <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ getError('parcel_description') }}
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-sm-4 shared-field-wrap" :class="{ 'has-error': Boolean(getError('parcel_weight')) }">
                                <label class="form-label shared-label" for="parcel_weight">
                                    <span>Weight</span>
                                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                                </label>
                                <div class="input-group">
                                    <input
                                        id="parcel_weight"
                                        v-model="parcelWeight"
                                        name="parcel_weight"
                                        type="number"
                                        min="0.1"
                                        max="1000"
                                        step="0.1"
                                        required
                                        class="form-control shared-input"
                                        :class="{ 'is-invalid': Boolean(getError('parcel_weight')) }"
                                        @input="clearError('parcel_weight')"
                                    >
                                    <span class="input-group-text shared-suffix">kg</span>
                                </div>
                                <div v-if="getError('parcel_weight')" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                                    <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ getError('parcel_weight') }}
                                </div>
                            </div>

                            <div class="col-12 col-sm-4 shared-field-wrap" :class="{ 'has-error': Boolean(getError('parcel_size')) }">
                                <label class="form-label shared-label" for="parcel_size">
                                    <span>Size</span>
                                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                                </label>
                                <select
                                    id="parcel_size"
                                    v-model="parcelSize"
                                    name="parcel_size"
                                    required
                                    class="form-select shared-select"
                                    :class="{ 'is-invalid': Boolean(getError('parcel_size')) }"
                                    @change="clearError('parcel_size')"
                                >
                                    <option value="" disabled>Choose</option>
                                    <option value="S">S (Small / Document pouch)</option>
                                    <option value="M">M (Medium / Standard box)</option>
                                    <option value="L">L (Large / Bulky package)</option>
                                </select>
                                <div v-if="getError('parcel_size')" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                                    <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ getError('parcel_size') }}
                                </div>
                            </div>

                            <div class="col-12 col-sm-4 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input
                                        id="is_fragile"
                                        class="form-check-input"
                                        name="is_fragile"
                                        type="checkbox"
                                        value="1"
                                        :checked="Boolean(Number(value('is_fragile')))"
                                    >
                                    <label class="form-check-label" for="is_fragile">Fragile parcel</label>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="col-12 col-lg-5">
                    <section class="surface surface-section mb-3" aria-labelledby="estimate-heading">
                        <h2 id="estimate-heading" class="h5">Route estimate</h2>
                        <p class="text-muted">
                            {{ isEdit ? 'The delivery fee is recalculated on save.' : 'Choose both addresses from suggestions to calculate a driving route and fee.' }}
                        </p>

                        <template v-if="!isEdit || mapsKey">
                            <div v-if="mapsKey" class="btn-group btn-group-sm mb-2" role="group" aria-label="Choose which stop to place on the map">
                                <button class="btn btn-outline-primary active" type="button" data-map-select="pickup" aria-pressed="true">Place pickup pin</button>
                                <button class="btn btn-outline-primary" type="button" data-map-select="dropoff" aria-pressed="false">Place drop-off pin</button>
                            </div>
                            <p v-if="mapsKey" class="form-text shared-helper-text mt-0">Type an address, select from suggestions, or click the map to place a pin.</p>
                            <div v-if="mapsKey" id="delivery-map" class="delivery-map mb-3" role="region" aria-label="Delivery route map"></div>
                            <div v-else class="alert alert-warning" role="status">Interactive map unavailable. Configure a Google Maps key to select locations and calculate route distance.</div>
                            <div id="route-error-alert" class="d-none" role="alert" aria-live="polite"></div>
                            <p id="route-source-note" class="form-text shared-helper-text mb-2" aria-live="polite"></p>
                            <div class="route-summary small mb-3" aria-live="polite">
                                <div><span class="text-muted">Distance:</span> <strong id="summary-distance">Not calculated</strong></div>
                                <div><span class="text-muted">Travel time:</span> <strong id="summary-duration">—</strong></div>
                                <div><span class="text-muted">Estimated fee:</span> <strong id="summary-fee">—</strong></div>
                            </div>
                            <input id="distance_km" name="distance_km" type="hidden" :value="value('distance_km')">
                            <div v-if="getError('distance_km')" class="text-danger small mb-3">{{ getError('distance_km') }}</div>
                            <p v-else class="form-text shared-helper-text">Road distance is calculated automatically after selecting both locations.</p>
                        </template>

                        <p class="mb-0 fw-semibold">{{ feeEstimate() }}</p>
                    </section>

                    <div class="d-flex flex-wrap justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" :href="`${baseUrl}orders`">Cancel</a>
                        <button id="btn-confirm-order" class="btn btn-primary" type="submit" :disabled="submitting">
                            <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            <span>{{ submitting ? 'Submitting...' : (isEdit ? 'Save changes' : 'Save draft order') }}</span>
                        </button>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</template>
