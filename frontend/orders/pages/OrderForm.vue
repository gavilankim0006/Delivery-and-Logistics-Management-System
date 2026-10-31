<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({ mode: { type: String, default: 'create' }, orderId: { type: Number, default: null }, formData: { type: Object, default: () => ({}) }, errors: { type: Object, default: () => ({}) }, formError: { type: String, default: '' }, csrfField: { type: String, default: '' }, csrfToken: { type: String, default: '' }, user: { type: Object, default: () => ({}) }, rates: { type: Object, default: () => ({}) }, mapsKey: { type: String, default: '' }, routeApiEndpoint: { type: String, default: '' }, baseUrl: { type: String, default: '/' } });
const isEdit = props.mode === 'edit';
const formAction = isEdit ? `${props.baseUrl}orders/${props.orderId}/edit` : `${props.baseUrl}orders`;
const pickupAddress = document.getElementById('pickup_address')?.value || '';
const dropoffAddress = document.getElementById('dropoff_address')?.value || '';
const initialDistance = document.getElementById('distance_km')?.value || props.formData.distance_km || '';
const initialCoords = Object.fromEntries(['pickup_lat', 'pickup_lng', 'dropoff_lat', 'dropoff_lng'].map((key) => [key, document.getElementById(key)?.value || props.formData[key] || '']));
const stops = [
    { title: 'Pickup', fields: [['pickup_address', 'Address', 'text', 500], ['pickup_contact_name', 'Contact name', 'text', 100], ['pickup_contact_phone', 'Contact phone', 'tel', 30], ['pickup_notes', 'Pickup notes (optional)', 'textarea', 1000]] },
    { title: 'Drop-off', fields: [['dropoff_address', 'Address', 'text', 500], ['dropoff_contact_name', 'Contact name', 'text', 100], ['dropoff_contact_phone', 'Contact phone', 'tel', 30], ['dropoff_notes', 'Drop-off notes (optional)', 'textarea', 1000]] }
];
function value(key) {
    if (key === 'pickup_address' && pickupAddress) return pickupAddress;
    if (key === 'dropoff_address' && dropoffAddress) return dropoffAddress;
    if (key === 'distance_km' && initialDistance) return initialDistance;
    if (props.formData[key] != null) return props.formData[key];
    if (key.endsWith('_contact_name')) return props.user.name || '';
    if (key.endsWith('_contact_phone')) return props.user.phone || '';
    if (key === 'parcel_weight') return '1.00';
    return '';
}
function error(key) { return props.errors[key] || ''; }
let initMap;
let updateEstimate;
onMounted(() => {
    if (isEdit) return;
    const distanceInput = document.getElementById('distance_km');
    const weightInput = document.getElementById('parcel_weight');
    updateEstimate = () => {
        const distance = Number(distanceInput?.value || 0);
        const weight = Number(weightInput?.value || 1);
        const r = props.rates;
        const fee = Number(r.base_delivery_fee || 60) + Math.max(0, distance - Number(r.free_distance_km || 2)) * Number(r.per_km_rate || 12.5) + Math.max(0, weight - Number(r.free_weight_kg || 1)) * Number(r.per_kg_rate || 5);
        const distanceEl = document.getElementById('summary-distance');
        const durationEl = document.getElementById('summary-duration');
        const feeEl = document.getElementById('summary-fee');
        if (distanceEl) distanceEl.textContent = distance ? `${distance.toFixed(2)} km` : 'Not calculated';
        if (durationEl) durationEl.textContent = distance ? `~${Math.ceil(distance * 2.8)} min` : '—';
        if (feeEl) feeEl.textContent = distance ? `₱${fee.toFixed(2)}` : '—';
    };
    distanceInput?.addEventListener('input', updateEstimate);
    weightInput?.addEventListener('input', updateEstimate);
    updateEstimate();
    if (!props.mapsKey) return;
    initMap = () => {
        if (!window.DeliveryMapManager || !document.getElementById('delivery-map') || initMap.done) return;
        initMap.done = true;
        new window.DeliveryMapManager({ calculateEndpoint: props.routeApiEndpoint });
    };
    window.initDeliveryMap = initMap;
    let attempts = 0;
    if (window.google?.maps) initMap();
    else {
        const waitForMaps = () => {
            if (window.google?.maps) initMap();
            else if (attempts++ < 60) window.setTimeout(waitForMaps, 100);
        };
        waitForMaps();
    }
});
onUnmounted(() => {
    if (initMap && window.initDeliveryMap === initMap) delete window.initDeliveryMap;
    document.getElementById('distance_km')?.removeEventListener('input', updateEstimate);
    document.getElementById('parcel_weight')?.removeEventListener('input', updateEstimate);
});
function feeEstimate() {
    const distance = Number(props.formData.distance_km) || 0;
    const weight = Number(props.formData.parcel_weight) || 1;
    if (!distance) return 'Calculated securely when saved';
    const r = props.rates;
    const fee = Number(r.base_delivery_fee || 60) + Math.max(0, distance - Number(r.free_distance_km || 2)) * Number(r.per_km_rate || 12.5) + Math.max(0, weight - Number(r.free_weight_kg || 1)) * Number(r.per_kg_rate || 5);
    return `Estimated ₱${fee.toFixed(2)} · final fee confirmed on save`;
}
</script>

<template>
    <div class="container py-4 ds-page page-shell">
        <nav aria-label="Breadcrumb" class="breadcrumb-line mb-3"><a :href="`${baseUrl}orders`">My orders</a><span aria-hidden="true"> / </span><span aria-current="page">{{ isEdit ? 'Edit draft order' : 'New delivery' }}</span></nav>
        <header class="page-header"><div><h1 class="page-title">{{ isEdit ? 'Edit draft order' : 'New delivery' }}</h1><p class="page-description">{{ isEdit ? 'Update delivery details while this order remains a draft. The delivery fee is recalculated when saved.' : 'Enter both stops and parcel details. Your delivery fee is calculated securely on save.' }}</p></div></header>
        <div v-if="formError" class="alert alert-danger" role="alert">{{ formError }}</div>
        <form method="post" :action="formAction" novalidate>
            <div v-html="csrfField"></div>
            <template v-if="!isEdit"><input v-for="key in ['pickup_lat', 'pickup_lng', 'dropoff_lat', 'dropoff_lng', 'hidden_distance_km', 'hidden_estimated_duration_min', 'hidden_delivery_fee', 'hidden_pickup_lat', 'hidden_pickup_lng', 'hidden_dropoff_lat', 'hidden_dropoff_lng']" :id="key" :key="key" type="hidden" :name="key.startsWith('hidden_') ? key.slice(7) : key" :value="initialCoords[key] || formData[key.replace(/^hidden_/, '')] || ''"></template>
            <div class="row g-4"><div class="col-12 col-lg-7">
                <section v-for="stop in stops" :key="stop.title" class="surface surface-section form-group-section mb-3"><h2 class="h5 mb-3">{{ stop.title }}</h2>
                    <div v-for="[key, label, type, max] in stop.fields" :key="key" class="mb-3"><label class="form-label" :for="key">{{ label }}</label>
                        <textarea v-if="type === 'textarea'" :id="key" class="form-control" :class="{ 'is-invalid': error(key) }" :name="key" :maxlength="max" rows="2">{{ value(key) }}</textarea>
                        <input v-else :id="key" class="form-control" :class="{ 'is-invalid': error(key) }" :type="type" :name="key" :maxlength="max" :value="value(key)" :placeholder="key.endsWith('_address') ? (key.startsWith('pickup') ? 'e.g. Makati CBD, Ayala Avenue' : 'e.g. BGC High Street, Taguig') : ''" :required="!key.endsWith('_notes')">
                        <div v-if="error(key)" class="invalid-feedback">{{ error(key) }}</div>
                    </div>
                </section>
                <section class="surface surface-section"><h2 class="h5 mb-3">Parcel</h2>
                    <div class="mb-3"><label class="form-label" for="parcel_description">Description</label><input id="parcel_description" name="parcel_description" class="form-control" :class="{ 'is-invalid': error('parcel_description') }" maxlength="255" required :value="value('parcel_description')"><div v-if="error('parcel_description')" class="invalid-feedback">{{ error('parcel_description') }}</div></div>
                    <div class="row g-3"><div class="col-12 col-sm-4"><label class="form-label" for="parcel_weight">Weight (kg)</label><input id="parcel_weight" name="parcel_weight" type="number" min="0.01" max="1000" step="0.01" required class="form-control" :class="{ 'is-invalid': error('parcel_weight') }" :value="value('parcel_weight')"><div v-if="error('parcel_weight')" class="invalid-feedback">{{ error('parcel_weight') }}</div></div>
                    <div class="col-12 col-sm-4"><label class="form-label" for="parcel_size">Size</label><select id="parcel_size" name="parcel_size" required class="form-select" :class="{ 'is-invalid': error('parcel_size') }"><option value="">Choose</option><option v-for="size in ['S', 'M', 'L']" :key="size" :value="size" :selected="value('parcel_size') === size || (!value('parcel_size') && size === 'M')">{{ size }}</option></select><div v-if="error('parcel_size')" class="invalid-feedback">{{ error('parcel_size') }}</div></div><div class="col-12 col-sm-4 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" id="is_fragile" name="is_fragile" type="checkbox" value="1" :checked="Boolean(Number(value('is_fragile')))"><label class="form-check-label" for="is_fragile">Fragile parcel</label></div></div></div>
                </section>
            </div><aside class="col-12 col-lg-5"><section class="surface surface-section mb-3"><h2 class="h5">Route estimate</h2><p class="text-muted">{{ isEdit ? 'The delivery fee is recalculated on save.' : 'Choose both addresses from suggestions to calculate a driving route and fee.' }}</p>
                <template v-if="!isEdit"><div v-if="mapsKey" class="btn-group btn-group-sm mb-2" role="group" aria-label="Choose which stop to place on the map"><button class="btn btn-outline-primary active" type="button" data-map-select="pickup" aria-pressed="true">Place pickup pin</button><button class="btn btn-outline-primary" type="button" data-map-select="dropoff" aria-pressed="false">Place drop-off pin</button></div><p v-if="mapsKey" class="form-text mt-0">Search an address suggestion or select a stop above and click map to place its pin.</p><div v-if="mapsKey" id="delivery-map" class="delivery-map mb-3" role="region" aria-label="Delivery route map"></div><div v-else class="alert alert-warning" role="status">Interactive map unavailable. Configure a Google Maps JavaScript key; you can still enter road distance manually.</div><div id="route-error-alert" class="d-none" role="alert" aria-live="polite"></div><p id="route-source-note" class="form-text mb-2" aria-live="polite"></p><div class="route-summary small mb-3" aria-live="polite"><div><span class="text-muted">Distance:</span> <strong id="summary-distance">Not calculated</strong></div><div><span class="text-muted">Travel time:</span> <strong id="summary-duration">—</strong></div><div><span class="text-muted">Estimated fee:</span> <strong id="summary-fee">—</strong></div></div><div class="mb-3"><label class="form-label" for="distance_km">Road distance (km)</label><input class="form-control" :class="{ 'is-invalid': error('distance_km') }" id="distance_km" name="distance_km" type="number" min="0.01" max="10000" step="0.01" :value="value('distance_km')" required><div v-if="error('distance_km')" class="invalid-feedback">{{ error('distance_km') }}</div><div class="form-text">Filled from Google route when both map locations are selected; otherwise enter estimated road distance.</div></div></template>
                <p class="mb-0 fw-semibold">{{ feeEstimate() }}</p>
            </section><div class="d-flex flex-wrap justify-content-end gap-2"><a class="btn btn-outline-secondary" :href="`${baseUrl}orders`">Cancel</a><button class="btn btn-primary" id="btn-confirm-order" type="submit">{{ isEdit ? 'Save changes' : 'Save draft order' }}</button></div></aside></div>
        </form>
    </div>
</template>
