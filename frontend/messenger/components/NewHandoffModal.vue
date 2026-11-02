<template>
  <div
    class="modal fade"
    id="newHandoffModal"
    tabindex="-1"
    aria-labelledby="newHandoffModalLabel"
    aria-hidden="true"
    data-bs-keyboard="true"
    ref="modalEl"
    @keydown="handleKeydown"
  >
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold" id="newHandoffModalLabel">
            <i class="bi bi-box-arrow-right text-primary me-2"></i>Start Package Handoff
          </h5>
          <button
            type="button"
            class="btn-close"
            data-bs-dismiss="modal"
            aria-label="Close"
          ></button>
        </div>

        <form @submit.prevent="submitForm">
          <div class="modal-body p-4">
            <div v-if="alertMessage" :class="`alert ${alertClass}`">
              {{ alertMessage }}
            </div>

            <!-- Order selection -->
            <div class="mb-3">
              <label for="newHandoffOrderSelect" class="form-label fw-semibold">
                Select Eligible Order
              </label>
              <select
                id="newHandoffOrderSelect"
                ref="orderSelectRef"
                v-model="selectedOrderId"
                class="form-select"
                required
                :disabled="eligibleOrders.length === 0"
              >
                <option v-if="eligibleOrders.length === 0" value="" disabled selected>
                  No eligible orders
                </option>
                <option v-else value="" disabled selected>
                  -- Choose an active order --
                </option>
                <option
                  v-for="ord in eligibleOrders"
                  :key="ord.id"
                  :value="ord.id"
                >
                  Order {{ ord.order_no || '#' + ord.id }} ({{ formatStatus(ord.status) }})
                </option>
              </select>

              <div v-if="eligibleOrders.length === 0" class="form-text text-warning">
                <i class="bi bi-info-circle me-1"></i>
                You currently have no active orders eligible for custody handoff.
              </div>
              <div v-else class="form-text">
                Only active orders awaiting delivery or pickup can be handed off.
              </div>
            </div>

            <!-- Customer search -->
            <div class="mb-3">
              <label for="handoffCustomerSearch" class="form-label fw-semibold">
                Search Recipient Customer
              </label>
              <div class="input-group position-relative">
                <span class="input-group-text bg-white">
                  <i class="bi bi-search text-muted"></i>
                </span>
                <input
                  type="text"
                  id="handoffCustomerSearch"
                  ref="customerSearchRef"
                  class="form-control"
                  :class="{ 'pe-5': Boolean(searchQuery) }"
                  v-model="searchQuery"
                  placeholder="Search recipient by name, company, or email..."
                  autocomplete="off"
                  @input="onSearchInput"
                />
                <button
                  v-if="searchQuery"
                  type="button"
                  class="btn-clear-search position-absolute end-0 top-50 translate-middle-y me-2 border-0 bg-transparent text-muted p-0 d-flex align-items-center justify-content-center"
                  style="width: 24px; height: 24px; cursor: pointer; z-index: 5;"
                  aria-label="Clear search"
                  @click="clearCustomer"
                >
                  <i class="bi bi-x-circle-fill"></i>
                </button>
              </div>

              <!-- Selected recipient badge -->
              <div
                v-if="selectedCustomer"
                class="badge bg-primary-subtle text-primary border border-primary-subtle mt-2 p-2 d-flex align-items-center justify-content-between"
              >
                <span>
                  <i class="bi bi-check-circle-fill me-1"></i>
                  Selected: <strong>{{ selectedCustomer.name }}</strong> ({{ selectedCustomer.email }})
                </span>
                <button
                  type="button"
                  class="btn-close ms-2"
                  style="font-size: 0.65rem;"
                  @click="clearCustomer"
                  aria-label="Remove recipient"
                ></button>
              </div>

              <!-- Customer search results list -->
              <div
                v-if="searchQuery.trim().length >= 2 && (searchResults.length > 0 || isSearching || hasSearched)"
                class="border rounded mt-2 bg-white shadow-sm"
                style="max-height: 180px; overflow-y: auto;"
              >
                <div v-if="isSearching" class="p-2 text-muted small">
                  <span class="spinner-border spinner-border-sm me-1"></span> Searching...
                </div>
                <div v-else-if="searchResults.length === 0" class="p-3 text-muted small text-center">
                  No customers found
                </div>
                <div v-else class="list-group list-group-flush">
                  <button
                    v-for="c in searchResults"
                    :key="c.id"
                    type="button"
                    class="list-group-item list-group-item-action py-2 px-3 d-flex align-items-center gap-2"
                    @click="selectCustomer(c)"
                  >
                    <div
                      class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                      style="width: 28px; height: 28px; font-size: 0.75rem;"
                      :style="{ background: getAvatarGradient(c.name) }"
                    >
                      {{ getInitials(c.name) }}
                    </div>
                    <div class="text-start">
                      <strong class="d-block text-dark">{{ c.name }}</strong>
                      <small class="text-muted">
                        {{ c.email }}
                        <span v-if="c.company_name">&middot; {{ c.company_name }}</span>
                      </small>
                    </div>
                  </button>
                </div>
              </div>
            </div>

            <div class="p-3 bg-light rounded small text-muted">
              <i class="bi bi-shield-check text-success me-1"></i>
              A secure 48-hour recipient confirmation token will be created and an instant messenger conversation will open for coordination.
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button
              type="button"
              class="btn btn-outline-secondary"
              data-bs-dismiss="modal"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="btn btn-primary fw-semibold"
              :disabled="eligibleOrders.length === 0 || !selectedOrderId || !selectedCustomer || isSubmitting"
            >
              <i class="bi bi-arrow-right-circle me-1"></i>
              {{ isSubmitting ? 'Initiating...' : 'Initiate Handoff & Chat' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { getAvatarGradient, getInitials } from '../utils.js';

const props = defineProps({
  eligibleOrders: {
    type: Array,
    default: () => [],
  },
  presetOrderId: {
    type: Number,
    default: 0,
  },
  baseUrl: {
    type: String,
    default: '',
  },
  csrfToken: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['handoff-created']);

const modalEl = ref(null);
const orderSelectRef = ref(null);
const customerSearchRef = ref(null);
const selectedOrderId = ref('');
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const hasSearched = ref(false);
const selectedCustomer = ref(null);
const isSubmitting = ref(false);
const alertMessage = ref('');
const alertClass = ref('alert-danger');

let searchTimer = null;
let bsModalInstance = null;

function formatStatus(status) {
  if (!status) return '';
  return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function onSearchInput() {
  clearTimeout(searchTimer);
  const q = searchQuery.value.trim();
  if (q.length < 2) {
    searchResults.value = [];
    isSearching.value = false;
    hasSearched.value = false;
    return;
  }

  isSearching.value = true;
  hasSearched.value = false;
  searchTimer = setTimeout(async () => {
    try {
      const res = await fetch(`${props.baseUrl}package-workflow/api/customers?q=${encodeURIComponent(q)}`);
      const data = await res.json();
      searchResults.value = data?.customers || [];
    } catch {
      searchResults.value = [];
    } finally {
      isSearching.value = false;
      hasSearched.value = true;
    }
  }, 300); // 300ms debounce
}

function selectCustomer(cust) {
  selectedCustomer.value = cust;
  searchQuery.value = cust.name;
  searchResults.value = [];
  hasSearched.value = false;
}

function clearCustomer() {
  selectedCustomer.value = null;
  searchQuery.value = '';
  searchResults.value = [];
  isSearching.value = false;
  hasSearched.value = false;
  clearTimeout(searchTimer);
  customerSearchRef.value?.focus();
}

function handleKeydown(event) {
  if (event.key === 'Escape') {
    closeModal();
  }
}

function closeModal() {
  if (bsModalInstance) {
    bsModalInstance.hide();
  }
}

function onModalShown() {
  const firstField = modalEl.value?.querySelector('select:not([disabled]), input:not([disabled])')
    || modalEl.value?.querySelector('select, input');
  firstField?.focus();
}

async function submitForm() {
  if (!selectedOrderId.value || !selectedCustomer.value) {
    alertMessage.value = 'Please choose an eligible order and select a recipient.';
    alertClass.value = 'alert-danger';
    return;
  }

  isSubmitting.value = true;
  alertMessage.value = '';

  try {
    const res = await fetch(`${props.baseUrl}package-workflow/handoff`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': props.csrfToken,
      },
      body: JSON.stringify({
        order_id: selectedOrderId.value,
        recipient_customer_id: selectedCustomer.value.id,
        csrf_token: props.csrfToken,
      }),
    });

    const data = await res.json();
    if (data && data.success) {
      if (bsModalInstance) {
        bsModalInstance.hide();
      }
      if (data.link) {
        prompt('Package handoff created! Here is the private 48-hour confirmation link to send to the recipient:', data.link);
      }
      emit('handoff-created', data.handoff_id);
    } else {
      alertMessage.value = data?.error || 'Failed to create handoff.';
      alertClass.value = 'alert-danger';
    }
  } catch {
    alertMessage.value = 'Network error while creating handoff.';
    alertClass.value = 'alert-danger';
  } finally {
    isSubmitting.value = false;
  }
}

function showModal() {
  if (!bsModalInstance && modalEl.value && window.bootstrap) {
    bsModalInstance = new window.bootstrap.Modal(modalEl.value, {
      keyboard: true
    });
  }
  if (bsModalInstance) {
    bsModalInstance.show();
  }
}

defineExpose({
  showModal,
});

watch(
  () => props.presetOrderId,
  (newId) => {
    if (newId) {
      selectedOrderId.value = newId;
    }
  },
  { immediate: true }
);

onMounted(() => {
  if (modalEl.value) {
    modalEl.value.addEventListener('shown.bs.modal', onModalShown);
    if (window.bootstrap) {
      bsModalInstance = new window.bootstrap.Modal(modalEl.value, {
        keyboard: true
      });
    }
  }
  if (props.presetOrderId) {
    showModal();
  }
});

onUnmounted(() => {
  clearTimeout(searchTimer);
  if (modalEl.value) {
    modalEl.value.removeEventListener('shown.bs.modal', onModalShown);
  }
});
</script>
