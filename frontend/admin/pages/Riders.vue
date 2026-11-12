<template>
  <div class="container page-shell py-4">
    <div v-for="(flash, index) in flashes" :key="index" class="alert" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
      {{ flash.message }}
    </div>

    <header class="page-header mb-4">
      <div>
        <p class="ds-eyebrow">Administration</p>
        <h1 class="page-title">Rider fleet</h1>
        <p class="page-description">Manage courier contacts, vehicle details, and availability.</p>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRiderModal">Add rider</button>
    </header>

    <section class="surface surface-section mb-4" aria-label="Filter riders">
      <form :action="`${baseUrl}admin/riders`" method="GET" class="row g-3 align-items-end">
        <div class="col-12 col-md-5">
          <label for="rider-status" class="form-label">Rider status</label>
          <select id="rider-status" name="status" class="form-select">
            <option value="">All statuses</option>
            <option value="available" :selected="statusFilter === 'available'">Available</option>
            <option value="busy" :selected="statusFilter === 'busy'">Busy (on delivery)</option>
            <option value="offline" :selected="statusFilter === 'offline'">Offline</option>
          </select>
        </div>
        <div class="col-12 col-md-7 d-flex flex-wrap align-items-center gap-2">
          <button type="submit" class="btn btn-secondary">Apply filter</button>
          <a v-if="statusFilter !== null && statusFilter !== undefined && statusFilter !== ''" :href="`${baseUrl}admin/riders`" class="btn btn-secondary">Clear</a>
          <span class="small text-muted ms-md-auto">{{ riders.length.toLocaleString() }} riders in this list</span>
        </div>
      </form>
    </section>

    <section class="surface" aria-label="Rider list">
      <div class="table-responsive">
        <table class="table table-clean table-responsive-stack align-middle mb-0">
          <thead>
            <tr>
              <th scope="col">ID</th>
              <th scope="col">Rider</th>
              <th scope="col">Phone</th>
              <th scope="col">Vehicle</th>
              <th scope="col">Plate number</th>
              <th scope="col">Status</th>
              <th scope="col">Portal account</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!riders || !riders.length">
              <td colspan="8" class="text-center py-4 text-muted">No riders match this filter.</td>
            </tr>
            <tr v-for="rider in riders" v-else :key="rider.id">
              <td data-label="ID" class="text-muted">#{{ rider.id }}</td>
              <td data-label="Rider" class="fw-medium">{{ rider.name }}</td>
              <td data-label="Phone">{{ rider.phone }}</td>
              <td data-label="Vehicle"><i class="bi bi-bicycle me-1" aria-hidden="true"></i>{{ rider.vehicle_type }}</td>
              <td data-label="Plate number"><span class="font-monospace">{{ rider.plate_number }}</span></td>
              <td data-label="Status">
                <span v-if="rider.status === 'available'" class="availability-chip availability-chip--available">Available</span>
                <span v-else-if="rider.status === 'busy'" class="availability-chip availability-chip--busy">Busy</span>
                <span v-else class="availability-chip availability-chip--offline">Offline</span>
                <div v-if="rider.has_active_assignment" class="small text-muted mt-1">Active delivery</div>
              </td>
              <td data-label="Portal account">
                <template v-if="rider.user_id">
                  <div class="fw-medium">{{ rider.account_email || 'Linked account' }}</div>
                  <div class="small text-muted">Account {{ rider.account_status || 'unknown' }}</div>
                  <form v-if="!rider.has_active_assignment" :action="`${baseUrl}admin/riders/${rider.id}/unlink-account`" method="POST" class="mt-2" onsubmit="return confirm('Unlink this account? The rider will no longer be able to log into the delivery portal until linked again.');">
                    <input type="hidden" name="csrf_token" :value="csrfToken">
                    <button type="submit" class="btn btn-sm btn-outline-danger" data-busy-text="Unlinking…">Unlink account</button>
                  </form>
                  <span v-else class="small text-muted">Cannot unlink during active delivery</span>
                </template>
                <form v-else :action="`${baseUrl}admin/riders/${rider.id}/link-account`" method="POST" class="dispatch-rider-link-form">
                  <input type="hidden" name="csrf_token" :value="csrfToken">
                  <label class="visually-hidden" :for="`rider-account-${rider.id}`">Rider portal account for {{ rider.name }}</label>
                  <select :id="`rider-account-${rider.id}`" name="user_id" class="form-select form-select-sm" required>
                    <option value="" disabled selected>Select rider account</option>
                    <option v-for="account in unlinkedRiderUsers" :key="account.id" :value="account.id">
                      {{ account.name }} · {{ account.email }}
                    </option>
                  </select>
                  <button type="submit" class="btn btn-sm btn-primary" :disabled="!unlinkedRiderUsers || !unlinkedRiderUsers.length">Link account</button>
                </form>
              </td>
              <td data-label="Actions" class="text-end">
                <button type="button" class="btn btn-secondary" data-bs-toggle="modal" :data-bs-target="`#editRiderModal${rider.id}`">Edit</button>
                <button type="button" class="btn btn-link text-danger" data-bs-toggle="modal" :data-bs-target="`#deleteRiderModal${rider.id}`">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <template v-for="rider in riders" :key="`modals-${rider.id}`">
      <div class="modal fade" :id="`editRiderModal${rider.id}`" tabindex="-1" :aria-labelledby="`editRiderModalLabel${rider.id}`" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <form :action="`${baseUrl}admin/update_rider/${rider.id}`" method="POST">
              <input type="hidden" name="csrf_token" :value="csrfToken">
              <div class="modal-header">
                <h2 class="modal-title h5" :id="`editRiderModalLabel${rider.id}`">Edit rider #{{ rider.id }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label :for="`rider-name-${rider.id}`" class="form-label">Full name</label>
                  <input :id="`rider-name-${rider.id}`" type="text" name="name" class="form-control" :value="rider.name" required>
                </div>
                <div class="mb-3">
                  <label :for="`rider-phone-${rider.id}`" class="form-label">Phone</label>
                  <input :id="`rider-phone-${rider.id}`" type="tel" name="phone" class="form-control" :value="rider.phone" required>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label :for="`rider-vehicle-${rider.id}`" class="form-label">Vehicle type</label>
                    <select :id="`rider-vehicle-${rider.id}`" name="vehicle_type" class="form-select">
                      <option value="Motorcycle" :selected="rider.vehicle_type === 'Motorcycle'">Motorcycle</option>
                      <option value="Sedan" :selected="rider.vehicle_type === 'Sedan'">Sedan</option>
                      <option value="MPV" :selected="rider.vehicle_type === 'MPV'">MPV</option>
                      <option value="Van" :selected="rider.vehicle_type === 'Van'">Van</option>
                    </select>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label :for="`rider-plate-${rider.id}`" class="form-label">Plate number</label>
                    <input :id="`rider-plate-${rider.id}`" type="text" name="plate_number" class="form-control" :value="rider.plate_number" required>
                  </div>
                </div>
                <div>
                  <label :for="`rider-status-${rider.id}`" class="form-label">Status</label>
                  <select :id="`rider-status-${rider.id}`" name="status" class="form-select">
                    <option value="available" :selected="rider.status === 'available'" :disabled="!!rider.has_active_assignment">Available</option>
                    <option value="busy" :selected="rider.status === 'busy'">Busy</option>
                    <option value="offline" :selected="rider.status === 'offline'" :disabled="!!rider.has_active_assignment">Offline</option>
                  </select>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="modal fade" :id="`deleteRiderModal${rider.id}`" tabindex="-1" :aria-labelledby="`deleteRiderModalLabel${rider.id}`" :aria-describedby="`deleteRiderModalDescription${rider.id}`" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <form :action="`${baseUrl}admin/delete_rider/${rider.id}`" method="POST">
              <input type="hidden" name="csrf_token" :value="csrfToken">
              <div class="modal-header">
                <h2 class="modal-title h5" :id="`deleteRiderModalLabel${rider.id}`">Delete this rider?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <p class="mb-0" :id="`deleteRiderModalDescription${rider.id}`">This will remove the rider record for <strong>{{ rider.name }}</strong> ({{ rider.plate_number }}). This action cannot be undone.</p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger" data-busy-text="Deleting…">Delete rider</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </template>

    <div class="modal fade" id="createRiderModal" tabindex="-1" aria-labelledby="createRiderModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form :action="`${baseUrl}admin/create_rider`" method="POST">
            <input type="hidden" name="csrf_token" :value="csrfToken">
            <div class="modal-header">
              <h2 class="modal-title h5" id="createRiderModalLabel">Register new rider</h2>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label for="new-rider-name" class="form-label">Full name <span class="text-danger">(required)</span></label>
                <input id="new-rider-name" type="text" name="name" class="form-control" required autocomplete="name" placeholder="e.g. Mario Batungbacal">
              </div>
              <div class="mb-3">
                <label for="new-rider-phone" class="form-label">Phone number <span class="text-danger">(required)</span></label>
                <input id="new-rider-phone" type="tel" name="phone" class="form-control" required autocomplete="tel" placeholder="+639181112233">
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="new-rider-vehicle" class="form-label">Vehicle type</label>
                  <select id="new-rider-vehicle" name="vehicle_type" class="form-select">
                    <option value="Motorcycle" selected>Motorcycle</option>
                    <option value="Sedan">Sedan</option>
                    <option value="MPV">MPV</option>
                    <option value="Van">Van</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="new-rider-plate" class="form-label">Plate number <span class="text-danger">(required)</span></label>
                  <input id="new-rider-plate" type="text" name="plate_number" class="form-control" required placeholder="ABC-1234">
                </div>
              </div>
              <div>
                <label for="new-rider-status" class="form-label">Initial status</label>
                <select id="new-rider-status" name="status" class="form-select">
                  <option value="available" selected>Available</option>
                  <option value="busy">Busy</option>
                  <option value="offline">Offline</option>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save rider</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  riders: { type: Array, default: () => [] },
  unlinkedRiderUsers: { type: Array, default: () => [] },
  statusFilter: { type: [String, null], default: '' },
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' },
  flashes: { type: Array, default: () => [] },
});
</script>