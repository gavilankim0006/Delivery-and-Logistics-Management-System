<template>
  <div class="container page-shell py-4">
    <header class="page-header mb-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <p class="ds-eyebrow">Administration</p>
        <h1 class="page-title">Delivery command simulator</h1>
        <p class="page-description">Explore fleet capacity, courier workload, and pricing scenarios for a saved service zone.</p>
      </div>
      <a class="btn btn-outline-secondary" :href="`${baseUrl}admin/service-areas`">
        <i class="bi bi-map me-1" aria-hidden="true"></i>Manage zones
      </a>
    </header>

    <div class="alert alert-info">
      <i class="bi bi-info-circle-fill me-2" aria-hidden="true"></i>
      Estimates only. This tool uses drop-off coordinates to select zone orders and a rider-throughput model; it does not model live traffic, third-party courier capacity, or dynamic demand surges. Orders missing coordinates are omitted from zone totals.
    </div>

    <div v-if="!areas || !areas.length" class="alert alert-warning" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
      Create an active polygon service area before running a simulation scenario.
    </div>

    <div v-else-if="simulationError" class="alert alert-danger" role="alert">
      <i class="bi bi-exclamation-circle-fill me-2" aria-hidden="true"></i>
      {{ simulationError }}
    </div>

    <div v-for="(flash, index) in flashes" :key="index" class="alert alert-dismissible fade show" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
      <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
      {{ flash.message }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <form :action="`${baseUrl}admin/command-simulator`" method="POST" class="surface p-3 p-md-4" id="command-simulator-form" novalidate>
      <input type="hidden" name="csrf_token" :value="csrfToken">

      <div class="row g-3">
        <!-- Service Area -->
        <div class="col-12 col-md-6 shared-field-wrap">
          <label class="form-label shared-label" for="area-id">
            <span>Service area</span>
            <span class="text-danger ms-1" aria-hidden="true">*</span>
          </label>
          <select
            class="form-select"
            id="area-id"
            name="area_id"
            v-model="currentAreaId"
            @change="onAreaChange"
            required
            aria-describedby="area-id-help"
          >
            <option v-for="area in areas" :key="area.id" :value="Number(area.id)">
              {{ area.name }}
            </option>
          </select>
          <div class="form-text shared-helper-text" id="area-id-help">
            Choose a saved geographic polygon service area for this scenario.
          </div>
        </div>

        <!-- Demand Orders -->
        <div class="col-12 col-md-6 shared-field-wrap">
          <label class="form-label shared-label" for="demand-orders">
            <span>Expected orders</span>
            <span class="text-danger ms-1" aria-hidden="true">*</span>
          </label>
          <input
            class="form-control"
            id="demand-orders"
            name="demand_orders"
            type="number"
            min="1"
            max="100000"
            step="1"
            :value="formData.demand_orders || (zoneData && zoneData.orders ? zoneData.orders.length : '') || '10'"
            required
            aria-describedby="demand-orders-help"
          >
          <div class="form-text shared-helper-text" id="demand-orders-help">
            Simulated package drop-offs needing dispatch. Defaults to orders within this zone.
          </div>
        </div>

        <!-- Scenario Hours -->
        <div class="col-6 col-md-3 shared-field-wrap">
          <label class="form-label shared-label" for="scenario-hours">
            <span>Shift length</span>
            <span class="text-danger ms-1" aria-hidden="true">*</span>
          </label>
          <div class="input-group">
            <input
              class="form-control"
              id="scenario-hours"
              name="scenario_hours"
              type="number"
              min="1"
              max="24"
              step="1"
              :value="formData.scenario_hours || '8'"
              required
              aria-describedby="scenario-hours-help"
            >
            <span class="input-group-text">hrs</span>
          </div>
          <div class="form-text shared-helper-text" id="scenario-hours-help">Simulation shift window in whole hours.</div>
        </div>

        <!-- Average Distance -->
        <div class="col-6 col-md-3 shared-field-wrap">
          <label class="form-label shared-label" for="average-distance">
            <span>Avg distance</span>
            <span class="text-danger ms-1" aria-hidden="true">*</span>
          </label>
          <div class="input-group">
            <input
              class="form-control"
              id="average-distance"
              name="average_distance_km"
              type="number"
              min="0.1"
              max="1000"
              step="0.1"
              :value="formData.average_distance_km || '8'"
              required
              aria-describedby="average-distance-help"
            >
            <span class="input-group-text">km</span>
          </div>
          <div class="form-text shared-helper-text" id="average-distance-help">Mean transit distance per delivery.</div>
        </div>

        <!-- Average Weight -->
        <div class="col-6 col-md-3 shared-field-wrap">
          <label class="form-label shared-label" for="average-weight">
            <span>Avg parcel weight</span>
            <span class="text-danger ms-1" aria-hidden="true">*</span>
          </label>
          <div class="input-group">
            <input
              class="form-control"
              id="average-weight"
              name="average_parcel_weight_kg"
              type="number"
              min="0.1"
              max="500"
              step="0.1"
              :value="formData.average_parcel_weight_kg || '3'"
              required
              aria-describedby="average-weight-help"
            >
            <span class="input-group-text">kg</span>
          </div>
          <div class="form-text shared-helper-text" id="average-weight-help">Assumed parcel weight for fee rating.</div>
        </div>

        <!-- Available Riders -->
        <div class="col-6 col-md-3 shared-field-wrap">
          <label class="form-label shared-label" for="available-riders">
            <span>Available couriers</span>
            <span class="text-danger ms-1" aria-hidden="true">*</span>
          </label>
          <input
            class="form-control"
            id="available-riders"
            name="available_riders"
            type="number"
            min="0"
            max="1000"
            step="1"
            :value="formData.available_riders !== undefined ? formData.available_riders : availableRiders"
            required
            aria-describedby="available-riders-help"
          >
          <div class="form-text shared-helper-text" id="available-riders-help">Active couriers on duty during this shift.</div>
        </div>

        <!-- Courier Speed -->
        <div class="col-6 col-md-3 shared-field-wrap">
          <label class="form-label shared-label" for="rider-speed">
            <span>Courier speed</span>
            <span class="text-danger ms-1" aria-hidden="true">*</span>
          </label>
          <div class="input-group">
            <input
              class="form-control"
              id="rider-speed"
              name="deliveries_per_rider_hour"
              type="number"
              min="0.1"
              max="20"
              step="0.1"
              :value="formData.deliveries_per_rider_hour || '1.5'"
              required
              aria-describedby="rider-speed-help"
            >
            <span class="input-group-text">drops/hr</span>
          </div>
          <div class="form-text shared-helper-text" id="rider-speed-help">Expected deliveries per rider per hour.</div>
        </div>
      </div>

      <!-- Collapsible Rate Overrides -->
      <details class="border rounded p-3 mt-4" :open="hasRateOverrides">
        <summary class="fw-semibold text-primary cursor-pointer">
          Override delivery fee parameters (optional)
        </summary>
        <p class="small text-muted mt-2 mb-3">
          Leave defaults to model the zone rates. Adjust these values to simulate promotional pricing or peak surcharges.
        </p>

        <div class="row g-3">
          <!-- Base Fee -->
          <div class="col-12 col-sm-6 col-lg shared-field-wrap">
            <label class="form-label shared-label small" for="sim-base_delivery_fee">
              <span>Base fee</span>
            </label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">₱</span>
              <input
                class="form-control"
                id="sim-base_delivery_fee"
                name="base_delivery_fee"
                type="number"
                min="0"
                max="100000"
                step="0.01"
                :value="formData.base_delivery_fee || (areaRates && areaRates.base_delivery_fee) || (baselineRates && baselineRates.base_delivery_fee) || '60.00'"
                required
              >
              <span class="input-group-text">PHP</span>
            </div>
            <div class="form-text small">Zone base rate.</div>
          </div>

          <!-- Free Distance -->
          <div class="col-12 col-sm-6 col-lg shared-field-wrap">
            <label class="form-label shared-label small" for="sim-free_distance_km">
              <span>Free distance</span>
            </label>
            <div class="input-group input-group-sm">
              <input
                class="form-control"
                id="sim-free_distance_km"
                name="free_distance_km"
                type="number"
                min="0"
                max="10000"
                step="0.1"
                :value="formData.free_distance_km || (areaRates && areaRates.free_distance_km) || (baselineRates && baselineRates.free_distance_km) || '2.0'"
                required
              >
              <span class="input-group-text">km</span>
            </div>
            <div class="form-text small">Free distance included.</div>
          </div>

          <!-- Rate per km -->
          <div class="col-12 col-sm-6 col-lg shared-field-wrap">
            <label class="form-label shared-label small" for="sim-per_km_rate">
              <span>Rate per km</span>
            </label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">₱</span>
              <input
                class="form-control"
                id="sim-per_km_rate"
                name="per_km_rate"
                type="number"
                min="0"
                max="10000"
                step="0.01"
                :value="formData.per_km_rate || (areaRates && areaRates.per_km_rate) || (baselineRates && baselineRates.per_km_rate) || '12.50'"
                required
              >
              <span class="input-group-text">PHP</span>
            </div>
            <div class="form-text small">Additional rate / km.</div>
          </div>

          <!-- Free Weight -->
          <div class="col-12 col-sm-6 col-lg shared-field-wrap">
            <label class="form-label shared-label small" for="sim-free_weight_kg">
              <span>Free weight</span>
            </label>
            <div class="input-group input-group-sm">
              <input
                class="form-control"
                id="sim-free_weight_kg"
                name="free_weight_kg"
                type="number"
                min="0"
                max="1000"
                step="0.1"
                :value="formData.free_weight_kg || (areaRates && areaRates.free_weight_kg) || (baselineRates && baselineRates.free_weight_kg) || '5.0'"
                required
              >
              <span class="input-group-text">kg</span>
            </div>
            <div class="form-text small">Free weight included.</div>
          </div>

          <!-- Rate per kg -->
          <div class="col-12 col-sm-6 col-lg shared-field-wrap">
            <label class="form-label shared-label small" for="sim-per_kg_rate">
              <span>Rate per kg</span>
            </label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">₱</span>
              <input
                class="form-control"
                id="sim-per_kg_rate"
                name="per_kg_rate"
                type="number"
                min="0"
                max="10000"
                step="0.01"
                :value="formData.per_kg_rate || (areaRates && areaRates.per_kg_rate) || (baselineRates && baselineRates.per_kg_rate) || '5.00'"
                required
              >
              <span class="input-group-text">PHP</span>
            </div>
            <div class="form-text small">Additional rate / kg.</div>
          </div>
        </div>
      </details>

      <div class="d-flex justify-content-end mt-4">
        <button
          class="btn btn-primary fw-semibold"
          type="submit"
          :disabled="!areas || !areas.length"
        >
          <i class="bi bi-play-circle me-1" aria-hidden="true"></i>Run what-if scenario
        </button>
      </div>
    </form>

    <!-- Scenario Results -->
    <section v-if="result" class="surface p-3 p-md-4 mt-4" aria-labelledby="scenario-results">
      <h2 class="h5" id="scenario-results">
        <i class="bi bi-graph-up-arrow text-primary me-2" aria-hidden="true"></i>Scenario results
      </h2>
      <p class="text-muted">
        Zone <strong>{{ (zoneData && zoneData.zone && zoneData.zone.name) || '' }}</strong> &middot;
        {{ Number(result.zone_orders || 0) }} existing orders in zone &middot;
        {{ Number(result.missing_coordinates || 0) }} orders skipped for missing coordinates.
      </p>

      <div class="row g-3">
        <div class="col-6 col-lg-3">
          <div class="border rounded p-3 h-100 bg-light">
            <div class="small text-muted mb-1">Estimated capacity</div>
            <strong class="fs-5">{{ Number(result.capacity || 0).toLocaleString() }} deliveries</strong>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="border rounded p-3 h-100 bg-light">
            <div class="small text-muted mb-1">Projected backlog</div>
            <strong class="fs-5" :class="Number(result.projected_backlog || 0) > 0 ? 'text-danger' : 'text-success'">
              {{ Number(result.projected_backlog || 0).toLocaleString() }} orders
            </strong>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="border rounded p-3 h-100 bg-light">
            <div class="small text-muted mb-1">Baseline average fee</div>
            <strong class="fs-5 money">₱{{ formatMoney(result.baseline_fee) }}</strong>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="border rounded p-3 h-100 bg-light">
            <div class="small text-muted mb-1">Proposed average fee</div>
            <strong class="fs-5 money">₱{{ formatMoney(result.scenario_fee) }}</strong>
          </div>
        </div>
        <div class="col-12">
          <hr>
          <p class="mb-1">
            Gross revenue at baseline fees:
            <strong class="money ms-1">₱{{ formatMoney(result.baseline_gross_revenue) }}</strong>
          </p>
          <p class="mb-1">
            Gross revenue at proposed fees:
            <strong class="money ms-1">₱{{ formatMoney(result.scenario_gross_revenue) }}</strong>
          </p>
          <p class="mb-0">
            Estimated workload at entered rider throughput:
            <strong class="ms-1">
              {{ result.estimated_workload_hours === null || result.estimated_workload_hours === undefined ? 'No riders available' : `${Number(result.estimated_workload_hours).toFixed(1)} rider-hours` }}
            </strong>
          </p>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  areas: { type: Array, default: () => [] },
  selectedAreaId: { type: Number, default: 0 },
  zoneData: { type: Object, default: null },
  baselineRates: { type: Object, default: () => ({}) },
  areaRates: { type: Object, default: () => ({}) },
  availableRiders: { type: Number, default: 0 },
  result: { type: Object, default: null },
  simulationError: { type: String, default: null },
  formData: { type: Object, default: () => ({}) },
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' },
  flashes: { type: Array, default: () => [] }
});

const currentAreaId = ref(props.selectedAreaId || (props.areas[0] ? Number(props.areas[0].id) : 0));

const hasRateOverrides = computed(() => {
  const f = props.formData || {};
  return !!(f.base_delivery_fee || f.free_distance_km || f.per_km_rate || f.free_weight_kg || f.per_kg_rate);
});

function onAreaChange() {
  window.location.href = `${props.baseUrl}admin/command-simulator?area_id=${currentAreaId.value}`;
}

function formatMoney(value) {
  const num = Number(value || 0);
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
