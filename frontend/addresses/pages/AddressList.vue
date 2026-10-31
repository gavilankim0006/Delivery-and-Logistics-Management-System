<script setup>
import { ref } from 'vue';
const props = defineProps({ baseUrl: String, csrfToken: String, addresses: { type: Array, default: () => [] } });
const deleting = ref(null);
function removeAddress(id) {
    if (deleting.value !== id) { deleting.value = id; return; }
    const form = document.getElementById(`delete-address-${id}`);
    form?.requestSubmit();
}
</script>

<template>
    <div class="container py-4 ds-page page-shell">
        <header class="page-header ds-page-header"><div><h1 class="h3 fw-bold mb-1 page-title">Saved addresses</h1><p class="text-muted mb-0 page-description">Pickup and drop-off locations saved to your customer account.</p></div><a class="btn btn-primary" :href="`${baseUrl}addresses/new`">Add address</a></header>
        <section v-if="!addresses.length" class="surface ds-panel p-4" aria-labelledby="empty-addresses-title"><h2 id="empty-addresses-title" class="h5">No saved addresses yet</h2><p>Save pickup and drop-off details once, then reuse them when you create an order.</p><a class="btn btn-primary" :href="`${baseUrl}addresses/new`">Add address</a></section>
        <div v-else class="surface ds-panel">
            <article v-for="(address, index) in addresses" :key="address.id" class="p-3 p-md-4" :class="{ 'border-top': index > 0 }">
                <div class="d-flex justify-content-between align-items-start gap-3"><h2 class="h5 mb-2">{{ address.label || 'Address' }}</h2><div class="address-actions d-flex gap-2"><a class="btn btn-outline-secondary" :href="`${baseUrl}addresses/${address.id}/edit`">Edit</a><button class="btn btn-link text-danger" type="button" @click="removeAddress(address.id)">{{ deleting === address.id ? 'Confirm delete' : 'Delete' }}</button></div></div>
                <p class="mb-1">{{ address.address_line }}</p><p class="small text-muted mb-0">{{ address.contact_name }} · {{ address.contact_phone }}</p><p v-if="address.notes" class="small text-muted mt-3 mb-0">{{ address.notes }}</p>
                <p v-if="deleting === address.id" class="small text-danger mt-2" role="alert">Delete this saved address? Addresses used by an order cannot be deleted.</p>
                <form :id="`delete-address-${address.id}`" class="d-none" method="post" :action="`${baseUrl}addresses/${address.id}/delete`"><input type="hidden" name="csrf_token" :value="csrfToken"></form>
            </article>
        </div>
    </div>
</template>
