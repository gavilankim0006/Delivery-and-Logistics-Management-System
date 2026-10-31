<script setup>
import { ref } from 'vue';

const props = defineProps({ order: { type: Object, required: true }, proofRecorded: { type: Boolean, default: false }, baseUrl: { type: String, default: '/' }, csrfField: { type: String, default: '' }, csrfToken: { type: String, default: '' }, locationUrl: { type: String, default: '' } });
const order = props.order;
const status = order.status === 'picked_up' ? 'Picked up' : 'Awaiting pickup';
const locationSharing = ref(false);
const locationMessage = ref('Location sharing is off. Your location is not tracked in the background.');
let watchId = null;
async function postLocation(payload) {
    const form = new URLSearchParams({ csrf_token: props.csrfToken, ...payload });
    const response = await fetch(props.locationUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: form });
    if (!response.ok) throw new Error();
}
async function sendLocation(position) {
    try {
        await postLocation({ latitude: String(position.coords.latitude), longitude: String(position.coords.longitude), accuracy_m: String(position.coords.accuracy) });
        locationMessage.value = 'Location shared while this page remains open.';
    } catch { locationMessage.value = 'Could not share location. Check your connection and try again.'; }
}
async function toggleLocation() {
    if (locationSharing.value) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
        locationSharing.value = false;
        try {
            await postLocation({ action: 'stop' });
            locationMessage.value = 'Location sharing is off. Your location is not tracked in the background.';
        } catch { locationMessage.value = 'Location sharing stopped on this device, but server could not confirm stop.'; }
        return;
    }
    if (!navigator.geolocation) { locationMessage.value = 'Location sharing is not supported by this browser.'; return; }
    watchId = navigator.geolocation.watchPosition(sendLocation, () => { locationMessage.value = 'Location unavailable. Check browser permission and try again.'; }, { enableHighAccuracy: true, maximumAge: 15000, timeout: 20000 });
    locationSharing.value = true;
    locationMessage.value = 'Waiting for location permission…';
}
</script>

<template>
    <div class="container page-shell py-4 ds-page">
        <nav aria-label="Breadcrumb" class="mb-3"><a :href="`${baseUrl}rider`">My deliveries</a><span class="text-muted"> / {{ order.order_no }}</span></nav>
        <header class="page-header mb-4"><div><h1 class="page-title">Delivery {{ order.order_no }}</h1><p class="page-description">Follow pickup and drop-off instructions. Report issues to dispatch.</p></div><span class="status-chip">{{ status }}</span></header>
        <div class="row g-4"><div class="col-12 col-lg-7"><section class="surface p-3 p-md-4 mb-4" aria-labelledby="route-heading"><h2 id="route-heading" class="h5 mb-3">Route and contacts</h2>
            <div class="rider-stop mb-4"><h3 class="h6">Pickup</h3><p class="mb-1">{{ order.pickup_address }}</p><p class="mb-0 text-muted">{{ order.pickup_contact_name }} · <a :href="`tel:${String(order.pickup_phone || '').replace(/[^+0-9]/g, '')}`">{{ order.pickup_phone }}</a></p><p v-if="order.pickup_notes" class="small mt-2 mb-0"><strong>Instructions:</strong> {{ order.pickup_notes }}</p></div>
            <div class="rider-stop"><h3 class="h6">Drop-off</h3><p class="mb-1">{{ order.dropoff_address }}</p><p class="mb-0 text-muted">{{ order.dropoff_contact_name }} · <a :href="`tel:${String(order.dropoff_phone || '').replace(/[^+0-9]/g, '')}`">{{ order.dropoff_phone }}</a></p><p v-if="order.dropoff_notes" class="small mt-2 mb-0"><strong>Instructions:</strong> {{ order.dropoff_notes }}</p></div>
        </section></div><aside class="col-12 col-lg-5"><section class="surface p-3 p-md-4">
            <template v-if="order.status === 'rider_assigned'"><h2 class="h5">Ready for pickup?</h2><p>Confirm pickup after you have received the parcel.</p><form method="post" :action="`${baseUrl}rider/orders/${order.order_id}/pickup`"><div v-html="csrfField"></div><button type="submit" class="btn btn-primary">Confirm pickup</button></form></template>
            <template v-else-if="order.status === 'picked_up' && !proofRecorded"><h2 class="h5">Complete delivery</h2><p>Submit recipient details and proof of delivery.</p><form method="post" enctype="multipart/form-data" :action="`${baseUrl}rider/orders/${order.order_id}/complete`"><div v-html="csrfField"></div><div class="mb-3"><label class="form-label" for="recipient-name">Recipient name</label><input class="form-control" id="recipient-name" name="recipient_name" type="text" maxlength="100" required autocomplete="name"></div><div class="mb-3"><label class="form-label" for="proof-photo">Proof photo</label><input class="form-control" id="proof-photo" name="proof_photo" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" required></div><div class="mb-3"><label class="form-label" for="delivery-notes">Delivery notes (optional)</label><textarea class="form-control" id="delivery-notes" name="note" rows="2" maxlength="500"></textarea></div><button type="submit" class="btn btn-primary">Submit proof and complete delivery</button></form></template>
            <template v-else-if="order.status === 'delivered' && proofRecorded"><h2 class="h5">Delivery complete</h2><p class="mb-0">Proof has been submitted and this delivery is complete.</p></template>
            <div v-if="['rider_assigned', 'picked_up'].includes(order.status)" class="surface p-3 mt-4"><h3 class="h6">Share location while this page is open</h3><p class="small text-muted" role="status" aria-live="polite">{{ locationMessage }}</p><button type="button" class="btn btn-outline-primary" :aria-pressed="locationSharing" @click="toggleLocation">{{ locationSharing ? 'Stop sharing location' : 'Start sharing location' }}</button></div>
        </section></aside></div>
    </div>
</template>
