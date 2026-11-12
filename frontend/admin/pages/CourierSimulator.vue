<template>
  <div class="container py-4 ds-page page-shell">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4 page-header">
      <div>
        <p class="ds-eyebrow">Administration</p>
        <h1 class="h3 fw-bold mb-2 page-title">Courier simulator</h1>
        <p class="page-description">Simulate courier assignment, capacity and workload against live orders.</p>
        <p class="text-muted mb-0">Simulated Lalamove-shaped events go through the same webhook processor used by inbound deliveries.</p>
      </div>
      <a class="btn btn-outline-secondary" :href="`${baseUrl}admin`">Back to admin</a>
    </header>

    <div class="alert alert-warning" role="note">
      <strong>Local development only.</strong> No courier credentials or real booking requests are used here. A mock order must be booked before its status events can be simulated.
    </div>

    <div v-for="(flash, index) in flashes" :key="index" class="alert alert-dismissible fade show" :class="flash.type === 'error' ? 'alert-danger' : `alert-${flash.type}`" role="status">
      <i class="bi me-2" :class="flash.type === 'error' ? 'bi-exclamation-circle-fill' : (flash.type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')" aria-hidden="true"></i>
      {{ flash.message }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <div v-if="!orders || !orders.length" class="empty-state surface p-4 text-center rounded">
      <h2 class="h5">No eligible orders</h2>
      <p class="mb-0 text-muted">Create an order and use the mock payment flow or prepare a paid test order before simulating a courier delivery.</p>
    </div>

    <div v-else class="table-responsive surface rounded">
      <table class="table table-clean align-middle mb-0">
        <thead>
          <tr>
            <th scope="col">Order</th>
            <th scope="col">Customer</th>
            <th scope="col">Status</th>
            <th scope="col">Mock driver</th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td>
              <a :href="`${baseUrl}orders/${order.id}`" class="fw-bold text-decoration-none">
                {{ order.order_no }}
              </a>
              <div v-if="order.lalamove_order_id" class="small font-monospace text-muted">
                {{ order.lalamove_order_id }}
              </div>
            </td>
            <td>
              <div class="fw-medium">{{ order.customer_name }}</div>
              <div v-if="order.phone" class="small text-muted">{{ order.phone }}</div>
            </td>
            <td>
              <span class="badge" :class="getStatusBadgeClass(order.status)">
                {{ statusLabels[order.status] || capitalize(order.status) }}
              </span>
            </td>
            <td>
              <template v-if="order.courier_driver_name">
                <div class="fw-medium">{{ order.courier_driver_name }}</div>
                <div class="small text-muted">
                  {{ order.courier_plate_number || '—' }} &middot; {{ order.courier_vehicle_type || 'Motorcycle' }}
                </div>
              </template>
              <span v-else class="text-muted">—</span>
            </td>
            <td>
              <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- Book mock courier button -->
                <form
                  v-if="canBook(order)"
                  :action="`${baseUrl}admin/courier-simulator/book/${order.id}`"
                  method="POST"
                  class="d-inline"
                >
                  <input type="hidden" name="csrf_token" :value="csrfToken">
                  <button class="btn btn-sm btn-primary fw-semibold" type="submit">
                    <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Book mock courier
                  </button>
                </form>

                <!-- Status transition event simulation form -->
                <form
                  v-if="hasAvailableEvents(order)"
                  :action="`${baseUrl}admin/courier-simulator/${order.id}`"
                  method="POST"
                  class="d-inline-flex align-items-center gap-2"
                >
                  <input type="hidden" name="csrf_token" :value="csrfToken">
                  <select name="event_type" class="form-select form-select-sm d-inline w-auto" required>
                    <option v-for="evt in getAvailableEvents(order.status)" :key="evt" :value="evt">
                      {{ eventLabels[evt] || capitalize(evt) }}
                    </option>
                  </select>
                  <button class="btn btn-sm btn-outline-primary fw-semibold" type="submit">
                    Simulate event
                  </button>
                </form>

                <span v-if="!canBook(order) && !hasAvailableEvents(order)" class="text-muted small">
                  Terminal state
                </span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  orders: { type: Array, default: () => [] },
  driverName: { type: String, default: '' },
  baseUrl: { type: String, default: '/' },
  csrfToken: { type: String, default: '' },
  flashes: { type: Array, default: () => [] }
});

const statusLabels = {
  paid: 'Paid — ready to book',
  booking: 'Booking',
  rider_assigned: 'Rider assigned',
  picked_up: 'Picked up',
  failed: 'Failed — can retry',
  delivered: 'Delivered',
  cancelled: 'Cancelled'
};

const availableEvents = {
  booking: ['rider_assigned', 'failed', 'cancelled'],
  rider_assigned: ['picked_up', 'failed', 'cancelled'],
  picked_up: ['delivered', 'failed'],
  failed: [],
  paid: ['failed', 'cancelled']
};

const eventLabels = {
  rider_assigned: 'Rider assigned',
  picked_up: 'Picked up',
  delivered: 'Delivered',
  failed: 'Failed',
  cancelled: 'Cancelled'
};

function capitalize(str) {
  if (!str) return '';
  return String(str).charAt(0).toUpperCase() + String(str).slice(1);
}

function canBook(order) {
  return order.status === 'paid' || (order.status === 'failed' && !order.lalamove_order_id);
}

function hasAvailableEvents(order) {
  const events = availableEvents[order.status] || [];
  return events.length > 0;
}

function getAvailableEvents(status) {
  return availableEvents[status] || [];
}

function getStatusBadgeClass(status) {
  switch (status) {
    case 'delivered':
      return 'bg-success';
    case 'picked_up':
    case 'rider_assigned':
      return 'bg-info text-dark';
    case 'booking':
    case 'paid':
      return 'bg-primary';
    case 'failed':
      return 'bg-danger';
    case 'cancelled':
      return 'bg-secondary';
    default:
      return 'bg-light text-dark border';
  }
}
</script>
