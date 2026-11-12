<template>
  <div class="container page-shell py-4">
    <header class="page-header mb-4">
      <div>
        <p class="ds-eyebrow">Administration</p>
        <h1 class="page-title">Delivery service areas</h1>
        <p class="page-description">Draw named polygon zones and customize their delivery fee rates.</p>
      </div>
    </header>

    <div v-for="(flash, index) in flashes" :key="index" class="alert" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
      {{ flash.message }}
    </div>

    <div v-if="!mapsJsKey" class="alert alert-warning" role="alert">
      The map editor is unavailable until GOOGLE_MAPS_MAPS_JS_API_KEY is configured.
    </div>

    <section class="surface p-3 p-md-4 mb-4" aria-labelledby="zone-form-title">
      <h2 class="h5 mb-3" id="zone-form-title">Create or edit a zone</h2>
      <form method="post" :action="`${baseUrl}admin/service-areas/save`" id="service-area-form" novalidate>
        <input type="hidden" name="csrf_token" :value="csrfToken">
        <input type="hidden" name="id" id="area-id" value="">
        <input type="hidden" name="polygon" id="area-polygon" value="">
        <div class="row g-3">
          <div class="col-12 col-md-8 shared-field-wrap">
            <label class="form-label shared-label" for="area-name">Zone name <span class="text-danger">*</span></label>
            <input class="form-control" name="name" id="area-name" minlength="2" maxlength="100" placeholder="e.g. Metro Manila Hub, North Sector" required>
          </div>
          <div class="col-12 col-md-4 d-flex align-items-center mt-md-4 pt-md-2">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_active" id="area-is-active" value="1" checked>
              <label class="form-check-label fw-semibold" for="area-is-active">Active service area</label>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 my-3 align-items-center">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="zone-undo">Undo point</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="zone-clear">Clear polygon</button>
          <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="zone-cancel-edit">Cancel editing (new zone)</button>
          <span class="small text-muted align-self-center ms-auto" id="zone-point-count">Add at least 3 points by clicking the map.</span>
        </div>

        <div id="service-area-map" class="service-area-map"></div>

        <fieldset class="border rounded p-3 mt-4">
          <legend class="float-none w-auto px-2 h6 fw-bold">Zone fee rates</legend>
          <div class="row g-3">
            <div class="col-12 col-sm-6 col-lg shared-field-wrap">
              <label class="form-label shared-label" for="base_delivery_fee">Base fee <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">₱</span>
                <input class="form-control" type="number" min="0" max="100000" step="0.01" name="base_delivery_fee" id="base_delivery_fee" :value="rates.base_delivery_fee || '60.00'" required>
                <span class="input-group-text">PHP</span>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-lg shared-field-wrap">
              <label class="form-label shared-label" for="free_distance_km">Free distance <span class="text-danger">*</span></label>
              <div class="input-group">
                <input class="form-control" type="number" min="0" max="10000" step="0.1" name="free_distance_km" id="free_distance_km" :value="rates.free_distance_km || '2.0'" required>
                <span class="input-group-text">km</span>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-lg shared-field-wrap">
              <label class="form-label shared-label" for="per_km_rate">Rate per km <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">₱</span>
                <input class="form-control" type="number" min="0" max="10000" step="0.01" name="per_km_rate" id="per_km_rate" :value="rates.per_km_rate || '10.00'" required>
                <span class="input-group-text">PHP</span>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-lg shared-field-wrap">
              <label class="form-label shared-label" for="free_weight_kg">Free weight <span class="text-danger">*</span></label>
              <div class="input-group">
                <input class="form-control" type="number" min="0" max="1000" step="0.1" name="free_weight_kg" id="free_weight_kg" :value="rates.free_weight_kg || '5.0'" required>
                <span class="input-group-text">kg</span>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-lg shared-field-wrap">
              <label class="form-label shared-label" for="per_kg_rate">Rate per kg <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">₱</span>
                <input class="form-control" type="number" min="0" max="10000" step="0.01" name="per_kg_rate" id="per_kg_rate" :value="rates.per_kg_rate || '5.00'" required>
                <span class="input-group-text">PHP</span>
              </div>
            </div>
          </div>
        </fieldset>

        <div class="d-flex gap-2 justify-content-end mt-4">
          <button class="btn btn-primary fw-semibold" type="submit" :disabled="!mapsJsKey">Save service area</button>
        </div>
      </form>
    </section>

    <section class="surface p-3 p-md-4" aria-labelledby="saved-zones-title">
      <h2 class="h5 mb-3" id="saved-zones-title">Saved zones</h2>
      <p v-if="!areas.length" class="text-muted mb-0">No service areas created yet.</p>
      <div v-else class="table-responsive">
        <table class="table table-clean align-middle mb-0">
          <thead>
            <tr>
              <th>Name</th>
              <th>Status</th>
              <th>Vertices</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="area in areas" :key="area.id">
              <td class="fw-semibold">{{ area.name }}</td>
              <td>
                <span class="ds-status-chip" :class="area.is_active ? 'ds-status-chip--active' : 'ds-status-chip--inactive'">{{ area.is_active ? 'Active' : 'Inactive' }}</span>
              </td>
              <td>{{ vertexCount(area) }} points</td>
              <td>
                <div class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-primary zone-edit" type="button" :data-zone="JSON.stringify(area)" @click="handleEdit(area)">Edit</button>
                  <form method="post" :action="`${baseUrl}admin/service-areas/delete/${area.id}`" onsubmit="return confirm('Delete this service area?')" class="d-inline">
                    <input type="hidden" name="csrf_token" :value="csrfToken">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div class="mt-4">
      <a class="btn btn-primary" :href="`${baseUrl}admin/command-simulator`">Open command simulator</a>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';

const props = defineProps({
  areas: { type: Array, default: () => [] },
  mapsJsKey: { type: String, default: '' },
  nonce: { type: String, default: '' },
  rates: { type: Object, default: () => ({}) },
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' },
  flashes: { type: Array, default: () => [] }
});

function handleEdit(area) {
  if (window._serviceAreaEditorInstance && typeof window._serviceAreaEditorInstance.edit === 'function') {
    window._serviceAreaEditorInstance.edit(area);
  }
}

onMounted(() => {
  if (window.initServiceAreaMap && !window._serviceAreaEditorInstance) {
    window.initServiceAreaMap();
  }
});

function vertexCount(area) {
  try {
    const value = JSON.parse(area.polygon_json || '[]');
    return Array.isArray(value) ? value.length : 0;
  } catch {
    return 0;
  }
}
</script>

<style scoped>
.service-area-map {
  min-height: 380px;
  border: 1px solid var(--line);
  border-radius: var(--r-md);
  overflow: hidden;
  background: var(--paper);
}
</style>