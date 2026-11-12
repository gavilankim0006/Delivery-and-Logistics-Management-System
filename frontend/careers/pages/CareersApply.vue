<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    formData: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    authEmail: { type: String, default: '' },
    baseUrl: { type: String, default: '/' },
    csrfToken: { type: String, default: '' },
    flashes: { type: Object, default: () => ({}) },
    pendingApplication: { type: Object, default: null }
});

const cleanBaseUrl = computed(() => {
    let url = props.baseUrl || '/';
    if (!url.endsWith('/')) url += '/';
    return url;
});

function url(path = '') {
    return cleanBaseUrl.value + String(path).replace(/^\/+/, '');
}

const form = ref({
    full_name: props.formData.full_name || '',
    email: props.formData.email || '',
    phone: props.formData.phone || '',
    role_applied: props.formData.role_applied || '',
    location: props.formData.location || '',
    experience: props.formData.experience || '',
    availability: props.formData.availability || '',
    id_type: props.formData.id_type || '',
    id_number: props.formData.id_number || ''
});

const idTypes = [
    "Driver’s License",
    'PhilID / National ID',
    'UMID',
    'Passport',
    'Postal ID',
    'Voter’s ID',
    'Other government-issued ID'
];

const availabilities = ['Full-time', 'Part-time', 'Weekdays', 'Weekends', 'Flexible'];
const roles = [
    { value: 'rider', label: 'Rider (Delivery courier)' },
    { value: 'dispatcher', label: 'Dispatcher (Operations team)' }
];
</script>

<template>
    <div class="container page-shell py-4">
        <header class="page-header mb-4">
            <div>
                <a :href="url()" class="small text-decoration-none">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to home
                </a>
                <p class="ds-eyebrow">Careers</p>
                <h1 class="page-title mt-2">Join the Dispatch team</h1>
                <p class="page-description">Apply as a rider or dispatcher. We need your ID details and a document image for verification.</p>
            </div>
        </header>

        <section class="surface surface-section p-4" aria-labelledby="application-form-title">
            <h2 id="application-form-title" class="h4 mb-2">Application details</h2>
            <p class="text-muted mb-3">
                Signed in as <strong>{{ authEmail }}</strong>. Use application email below for decision notifications. If accepted, staff account uses your sign-in email. Required fields are marked with <span class="text-danger" aria-hidden="true">*</span>.
            </p>

            <div v-if="pendingApplication" class="alert alert-info d-flex align-items-center gap-3 mb-4" role="status">
                <i class="bi bi-clock-history fs-3 text-info-emphasis flex-shrink-0" aria-hidden="true"></i>
                <div>
                    <h3 class="h6 mb-1 text-info-emphasis fw-bold">Application submitted &amp; under review</h3>
                    <p class="mb-0 small">
                        You have an active application for <strong>{{ pendingApplication.role_applied === 'dispatcher' ? 'Dispatcher' : 'Rider' }}</strong>
                        <span v-if="pendingApplication.created_at"> (submitted {{ pendingApplication.created_at }})</span>.
                        Our recruitment team is reviewing your documents. You will receive an email once a decision is made.
                    </p>
                </div>
            </div>

            <div v-if="errors && Object.keys(errors).length > 0" class="alert alert-danger" role="alert" tabindex="-1">
                <strong>Please review your application.</strong>
                <p v-if="errors.form" class="mb-0 mt-2">{{ errors.form }}</p>
                <ul class="mb-0 mt-2">
                    <template v-for="(msg, field) in errors" :key="field">
                        <li v-if="field !== 'form'">
                            <a :href="`#${field}`" class="text-danger text-decoration-underline">{{ msg }}</a>
                        </li>
                    </template>
                </ul>
            </div>

            <div class="alert alert-warning mb-4" role="note">
                <strong>Privacy:</strong> ID number and uploaded document are sensitive. They are visible only to authorized administrators. Submit only if comfortable sharing them for recruitment verification.
            </div>

            <form :action="url('careers/apply')" method="POST" enctype="multipart/form-data" class="row g-3">
                <input v-if="csrfToken" type="hidden" name="csrf_token" :value="csrfToken" />

                <!-- Full legal name -->
                <div class="col-12 col-md-6 shared-field-wrap">
                    <label class="form-label shared-label" for="full_name">
                        <span>Full legal name</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <input
                        class="form-control shared-input"
                        :class="{ 'is-invalid': errors.full_name }"
                        id="full_name"
                        name="full_name"
                        type="text"
                        autocomplete="name"
                        minlength="2"
                        maxlength="150"
                        placeholder="Juan Dela Cruz"
                        v-model="form.full_name"
                        required
                    />
                    <div v-if="errors.full_name" class="invalid-feedback d-block mt-1" role="alert" id="full_name-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ errors.full_name }}
                    </div>
                </div>

                <!-- Email address -->
                <div class="col-12 col-md-6 shared-field-wrap">
                    <label class="form-label shared-label" for="email">
                        <span>Email address for application notifications</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <input
                        class="form-control shared-input"
                        :class="{ 'is-invalid': errors.email }"
                        type="email"
                        id="email"
                        name="email"
                        autocomplete="email"
                        inputmode="email"
                        maxlength="254"
                        placeholder="name@example.com"
                        v-model="form.email"
                        required
                    />
                    <div v-if="errors.email" class="invalid-feedback d-block mt-1" role="alert" id="email-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ errors.email }}
                    </div>
                </div>

                <!-- Phone number -->
                <div class="col-12 col-md-6 shared-field-wrap">
                    <label class="form-label shared-label" for="phone">
                        <span>Phone number</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <input
                        class="form-control shared-input"
                        :class="{ 'is-invalid': errors.phone }"
                        type="tel"
                        id="phone"
                        name="phone"
                        autocomplete="tel"
                        inputmode="tel"
                        placeholder="09171234567"
                        v-model="form.phone"
                        required
                    />
                    <div id="phone-help" class="form-text shared-helper-text mt-1">10–15 digits. Philippine numbers (e.g. 09XXXXXXXXX or +639XXXXXXXXX).</div>
                    <div v-if="errors.phone" class="invalid-feedback d-block mt-1" role="alert" id="phone-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ errors.phone }}
                    </div>
                </div>

                <!-- Role applied for -->
                <div class="col-12 col-md-6 shared-field-wrap">
                    <label class="form-label shared-label" for="role_applied">
                        <span>Role applied for</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <select
                        class="form-select shared-input"
                        :class="{ 'is-invalid': errors.role_applied }"
                        id="role_applied"
                        name="role_applied"
                        v-model="form.role_applied"
                        required
                    >
                        <option value="" disabled>Choose role</option>
                        <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                    <div v-if="errors.role_applied" class="invalid-feedback d-block mt-1" role="alert" id="role_applied-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ errors.role_applied }}
                    </div>
                </div>

                <!-- City / Area -->
                <div class="col-12 col-md-6 shared-field-wrap">
                    <label class="form-label shared-label" for="location">
                        <span>City / Service area where you can work</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <input
                        class="form-control shared-input"
                        :class="{ 'is-invalid': errors.location }"
                        type="text"
                        id="location"
                        name="location"
                        autocomplete="address-level2"
                        maxlength="150"
                        placeholder="e.g. Quezon City, Metro Manila"
                        v-model="form.location"
                        required
                    />
                    <div v-if="errors.location" class="invalid-feedback d-block mt-1" role="alert" id="location-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ errors.location }}
                    </div>
                </div>

                <!-- Availability -->
                <div class="col-12 col-md-6 shared-field-wrap">
                    <label class="form-label shared-label" for="availability">
                        <span>Availability</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <select
                        class="form-select shared-input"
                        :class="{ 'is-invalid': errors.availability }"
                        id="availability"
                        name="availability"
                        v-model="form.availability"
                        required
                    >
                        <option value="" disabled>Choose your availability</option>
                        <option v-for="a in availabilities" :key="a" :value="a">{{ a }}</option>
                    </select>
                    <div class="form-text shared-helper-text mt-1">Choose the schedule that best describes when you can work.</div>
                    <div v-if="errors.availability" class="invalid-feedback d-block mt-1" role="alert" id="availability-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ errors.availability }}
                    </div>
                </div>

                <!-- Relevant experience -->
                <div class="col-12 shared-field-wrap">
                    <label class="form-label shared-label" for="experience">
                        <span>Relevant experience</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <textarea
                        class="form-control shared-textarea"
                        :class="{ 'is-invalid': errors.experience }"
                        id="experience"
                        name="experience"
                        rows="4"
                        maxlength="3000"
                        placeholder="Describe your delivery or dispatch experience, skills, and previous work..."
                        v-model="form.experience"
                        required
                    ></textarea>
                    <div v-if="errors.experience" class="invalid-feedback d-block mt-1" role="alert" id="experience-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ errors.experience }}
                    </div>
                </div>

                <!-- ID type -->
                <div class="col-12 col-md-6 shared-field-wrap">
                    <label class="form-label shared-label" for="id_type">
                        <span>ID type</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <select
                        class="form-select shared-input"
                        :class="{ 'is-invalid': errors.id_type }"
                        id="id_type"
                        name="id_type"
                        v-model="form.id_type"
                        required
                    >
                        <option value="" disabled>Choose your ID type</option>
                        <option v-for="t in idTypes" :key="t" :value="t">{{ t }}</option>
                    </select>
                    <div class="form-text shared-helper-text mt-1">Select the same ID type shown on the document you upload.</div>
                    <div v-if="errors.id_type" class="invalid-feedback d-block mt-1" role="alert" id="id_type-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ errors.id_type }}
                    </div>
                </div>

                <!-- ID number -->
                <div class="col-12 col-md-6 shared-field-wrap">
                    <label class="form-label shared-label" for="id_number">
                        <span>ID number</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <input
                        class="form-control shared-input"
                        :class="{ 'is-invalid': errors.id_number }"
                        type="text"
                        id="id_number"
                        name="id_number"
                        maxlength="100"
                        placeholder="e.g. N01-12-345678"
                        v-model="form.id_number"
                        required
                    />
                    <div class="form-text shared-helper-text mt-1">Must match the uploaded ID document.</div>
                    <div v-if="errors.id_number" class="invalid-feedback d-block mt-1" role="alert" id="id_number-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ errors.id_number }}
                    </div>
                </div>

                <!-- Upload ID document -->
                <div class="col-12 shared-field-wrap">
                    <label class="form-label shared-label" for="id_document">
                        <span>Upload ID document</span>
                        <span class="text-danger ms-1" aria-hidden="true">*</span>
                    </label>
                    <input
                        class="form-control shared-input"
                        :class="{ 'is-invalid': errors.id_document }"
                        type="file"
                        id="id_document"
                        name="id_document"
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        data-max-size-mb="8"
                        required
                    />
                    <div class="form-text shared-helper-text mt-1">JPEG, PNG, WebP, or PDF. Maximum 8 MB. Upload a clear copy for verification.</div>
                    <div v-if="errors.id_document" class="invalid-feedback d-block mt-1" role="alert" id="id_document-error">
                        <i class="bi bi-exclamation-circle-fill me-1" aria-hidden="true"></i>{{ errors.id_document }}
                    </div>
                </div>

                <!-- Actions -->
                <div class="col-12 d-flex flex-wrap align-items-center gap-3 pt-2">
                    <button type="submit" class="btn btn-primary">Send application</button>
                    <span class="small text-muted">You can contact our team later if you need your application or document removed.</span>
                </div>
            </form>
        </section>
    </div>
</template>
