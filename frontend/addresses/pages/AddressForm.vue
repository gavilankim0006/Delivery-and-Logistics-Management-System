<script setup>
import { ref } from 'vue';
import { phPhone, normalizePhPhone } from '../../shared/validators.js';

const props = defineProps({
    baseUrl: { type: String, default: '/' },
    csrfToken: { type: String, default: '' },
    addressId: { type: Number, default: null },
    initial: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    formError: { type: String, default: '' }
});

const submitting = ref(false);
const formErrors = ref({ ...props.errors });

const label = ref(props.initial?.label || '');
const addressLine = ref(props.initial?.address_line || '');
const contactName = ref(props.initial?.contact_name || '');
const contactPhone = ref(props.initial?.contact_phone || '');
const notes = ref(props.initial?.notes || '');

const action = props.addressId ? `${props.baseUrl}addresses/${props.addressId}/update` : `${props.baseUrl}addresses`;

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
    const labelEl = document.getElementById('label');
    const addressEl = document.getElementById('address_line');
    const nameEl = document.getElementById('contact_name');
    const phoneEl = document.getElementById('contact_phone');
    const notesEl = document.getElementById('notes');

    // Normalize phone before sending
    if (phoneEl && phoneEl.value) {
        phoneEl.value = normalizePhPhone(phoneEl.value);
        contactPhone.value = phoneEl.value;
    }

    const checks = [
        { el: addressEl, valid: validateField('address_line', addressEl?.value, null, 'Address is required.') },
        { el: nameEl, valid: validateField('contact_name', nameEl?.value, (v) => (!v || v.trim().length < 2 ? 'Contact name must be at least 2 characters.' : null)) },
        { el: phoneEl, valid: validateField('contact_phone', phoneEl?.value, phPhone) },
        { el: notesEl, valid: validateField('notes', notesEl?.value, (v) => (v && v.length > 300 ? 'Notes cannot exceed 300 characters.' : null)) }
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
</script>

<template>
    <div class="container py-4 ds-page page-shell">
        <nav aria-label="Breadcrumb" class="breadcrumb-line mb-3">
            <a :href="`${baseUrl}addresses`">Saved addresses</a>
            <span aria-hidden="true"> / </span>
            <span aria-current="page">{{ addressId ? 'Edit address' : 'Add address' }}</span>
        </nav>

        <header class="page-header mb-4">
            <div>
                <p class="ds-eyebrow">Account</p>
                <h1 class="page-title">{{ addressId ? 'Edit saved address' : 'Add saved address' }}</h1>
                <p class="page-description">
                    {{ addressId ? 'Update this saved location and its contact details.' : 'Save a pickup or drop-off location to your customer account.' }}
                </p>
            </div>
        </header>

        <div v-if="formError" class="alert alert-danger" role="alert">{{ formError }}</div>

        <form class="address-form" method="post" :action="action" novalidate @submit="handleSubmit">
            <input type="hidden" name="csrf_token" :value="csrfToken">

            <section class="surface surface-section mb-4">
                <!-- Label (optional) -->
                <div class="mb-3 shared-field-wrap" :class="{ 'has-error': Boolean(formErrors['label']) }">
                    <label class="form-label shared-label" for="label">
                        <span>Label</span>
                        <span class="text-muted fw-normal ms-1">(optional)</span>
                    </label>
                    <input
                        id="label"
                        v-model="label"
                        name="label"
                        type="text"
                        maxlength="100"
                        class="form-control shared-input"
                        :class="{ 'is-invalid': Boolean(formErrors['label']) }"
                        placeholder="e.g. Home, Office, Warehouse"
                        @input="clearError('label')"
                    >
                    <div v-if="formErrors['label']" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ formErrors['label'] }}
                    </div>
                </div>

                <!-- Address Line (Google Places Autocomplete compatible id="address_line") -->
                <div class="mb-3 shared-field-wrap" :class="{ 'has-error': Boolean(formErrors['address_line']) }">
                    <label class="form-label shared-label" for="address_line">
                        <span>Address</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="address_line"
                        v-model="addressLine"
                        name="address_line"
                        type="text"
                        maxlength="500"
                        required
                        class="form-control shared-input"
                        :class="{ 'is-invalid': Boolean(formErrors['address_line']) }"
                        placeholder="Enter street address, building, or landmark"
                        :aria-invalid="formErrors['address_line'] ? 'true' : 'false'"
                        :aria-describedby="formErrors['address_line'] ? 'address_line-error' : null"
                        @input="clearError('address_line')"
                    >
                    <div v-if="formErrors['address_line']" id="address_line-error" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ formErrors['address_line'] }}
                    </div>
                </div>

                <!-- Contact Name -->
                <div class="mb-3 shared-field-wrap" :class="{ 'has-error': Boolean(formErrors['contact_name']) }">
                    <label class="form-label shared-label" for="contact_name">
                        <span>Contact name</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="contact_name"
                        v-model="contactName"
                        name="contact_name"
                        type="text"
                        maxlength="100"
                        required
                        class="form-control shared-input"
                        :class="{ 'is-invalid': Boolean(formErrors['contact_name']) }"
                        placeholder="Contact person at this location"
                        :aria-invalid="formErrors['contact_name'] ? 'true' : 'false'"
                        :aria-describedby="formErrors['contact_name'] ? 'contact_name-error' : null"
                        @input="clearError('contact_name')"
                    >
                    <div v-if="formErrors['contact_name']" id="contact_name-error" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ formErrors['contact_name'] }}
                    </div>
                </div>

                <!-- Contact Phone -->
                <div class="mb-3 shared-field-wrap" :class="{ 'has-error': Boolean(formErrors['contact_phone']) }">
                    <label class="form-label shared-label" for="contact_phone">
                        <span>Contact phone</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="contact_phone"
                        v-model="contactPhone"
                        name="contact_phone"
                        type="tel"
                        maxlength="30"
                        autocomplete="tel"
                        required
                        class="form-control shared-input"
                        :class="{ 'is-invalid': Boolean(formErrors['contact_phone']) }"
                        placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                        :aria-invalid="formErrors['contact_phone'] ? 'true' : 'false'"
                        aria-describedby="contact_phone-help"
                        @input="clearError('contact_phone')"
                    >
                    <div id="contact_phone-help" class="form-text shared-helper-text mt-1">
                        Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).
                    </div>
                    <div v-if="formErrors['contact_phone']" id="contact_phone-error" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ formErrors['contact_phone'] }}
                    </div>
                </div>

                <!-- Notes (optional with counter) -->
                <div class="mb-3 shared-field-wrap" :class="{ 'has-error': Boolean(formErrors['notes']) }">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label shared-label mb-0" for="notes">
                            <span>Notes</span>
                            <span class="text-muted fw-normal ms-1">(optional)</span>
                        </label>
                        <span class="textarea-char-counter small text-muted">{{ notes.length }} / 300</span>
                    </div>
                    <textarea
                        id="notes"
                        v-model="notes"
                        name="notes"
                        rows="3"
                        maxlength="300"
                        class="form-control shared-textarea"
                        :class="{ 'is-invalid': Boolean(formErrors['notes']) }"
                        placeholder="e.g. Unit number, gate code, landmarks, special delivery instructions"
                        @input="clearError('notes')"
                    ></textarea>
                    <div v-if="formErrors['notes']" class="invalid-feedback shared-error-text d-block mt-1" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ formErrors['notes'] }}
                    </div>
                </div>
            </section>

            <div class="d-flex flex-wrap justify-content-end gap-2">
                <a class="btn btn-outline-secondary" :href="`${baseUrl}addresses`">Cancel</a>
                <button class="btn btn-primary" type="submit" :disabled="submitting">
                    <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    <span>{{ submitting ? 'Submitting...' : 'Save address' }}</span>
                </button>
            </div>
        </form>
    </div>
</template>
