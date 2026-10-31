<script setup>
import { ref } from 'vue';
const props = defineProps({ baseUrl: String, csrfToken: String, addressId: Number, initial: Object, errors: Object, formError: String });
const submitting = ref(false);
const fields = [
    { key: 'label', label: 'Label', max: 100, optional: true },
    { key: 'address_line', label: 'Address', max: 500 },
    { key: 'contact_name', label: 'Contact name', max: 100 },
    { key: 'contact_phone', label: 'Contact phone', max: 30, type: 'tel' }
];
const action = props.addressId ? `${props.baseUrl}addresses/${props.addressId}/update` : `${props.baseUrl}addresses`;
function submit() { submitting.value = true; }
</script>

<template>
    <div class="container py-4 ds-page page-shell">
        <nav aria-label="Breadcrumb" class="breadcrumb-line mb-3"><a :href="`${baseUrl}addresses`">Saved addresses</a><span aria-hidden="true"> / </span><span aria-current="page">{{ addressId ? 'Edit address' : 'Add address' }}</span></nav>
        <header class="page-header"><div><h1 class="page-title">{{ addressId ? 'Edit saved address' : 'Add saved address' }}</h1><p class="page-description">{{ addressId ? 'Update this saved location and its contact details.' : 'Save a pickup or drop-off location to your customer account.' }}</p></div></header>
        <div v-if="formError" class="alert alert-danger" role="alert">{{ formError }}</div>
        <form class="address-form" method="post" :action="action" novalidate @submit="submit">
            <input type="hidden" name="csrf_token" :value="csrfToken">
            <section class="surface surface-section">
                <div v-for="field in fields" :key="field.key" class="mb-3">
                    <label class="form-label" :for="field.key">{{ field.label }} <span v-if="field.optional" class="text-muted">(optional)</span></label>
                    <input :id="field.key" class="form-control" :class="{ 'is-invalid': errors?.[field.key] }" :name="field.key" :type="field.type || 'text'" :maxlength="field.max" :value="initial?.[field.key] || ''" :required="!field.optional">
                    <div v-if="errors?.[field.key]" class="invalid-feedback">{{ errors[field.key] }}</div>
                </div>
                <div><label class="form-label" for="notes">Notes <span class="text-muted">(optional)</span></label><textarea id="notes" class="form-control" :class="{ 'is-invalid': errors?.notes }" name="notes" rows="3" maxlength="1000">{{ initial?.notes || '' }}</textarea><div v-if="errors?.notes" class="invalid-feedback">{{ errors.notes }}</div></div>
            </section>
            <div class="d-flex flex-wrap justify-content-end gap-2 mt-3"><a class="btn btn-outline-secondary" :href="`${baseUrl}addresses`">Cancel</a><button class="btn btn-primary" type="submit" :disabled="submitting">{{ submitting ? 'Saving…' : 'Save address' }}</button></div>
        </form>
    </div>
</template>
