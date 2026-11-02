<template>
  <aside class="messenger-sidebar" aria-label="Conversations">
    <!-- Header -->
    <div class="messenger-sidebar-header">
      <div class="messenger-title-row">
        <h2 class="messenger-title">
          <i class="bi bi-chat-dots-fill text-primary"></i>
          <span>Messages</span>
          <span class="messenger-status-dot" title="Messenger online"></span>
        </h2>
        <button
          type="button"
          class="btn btn-primary btn-new-handoff"
          @click="$emit('open-new-handoff')"
        >
          <i class="bi bi-plus-lg"></i>
          <span>New Handoff</span>
        </button>
      </div>

      <!-- Search Box -->
      <div class="messenger-search-wrap">
        <label for="sidebarSearchInput" class="visually-hidden">Search chats or orders</label>
        <i class="bi bi-search messenger-search-icon" aria-hidden="true"></i>
        <input
          id="sidebarSearchInput"
          type="text"
          class="messenger-search-input"
          :value="localSearchQuery"
          @input="onSearchInput"
          placeholder="Search chats or orders..."
          autocomplete="off"
        />
        <button
          v-if="localSearchQuery"
          type="button"
          class="messenger-search-clear"
          style="display: block;"
          aria-label="Clear search"
          @click="onClearSearch"
        >
          <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
        </button>
      </div>

      <!-- Filter Tabs -->
      <div class="messenger-filter-tabs" role="tablist" aria-label="Filter conversations by status">
        <button
          type="button"
          class="messenger-tab-btn"
          :class="{ active: statusFilter === 'all' }"
          @click="$emit('update:statusFilter', 'all')"
        >
          All
        </button>
        <button
          type="button"
          class="messenger-tab-btn"
          :class="{ active: statusFilter === 'pending' }"
          @click="$emit('update:statusFilter', 'pending')"
        >
          Active
        </button>
        <button
          type="button"
          class="messenger-tab-btn"
          :class="{ active: statusFilter === 'confirmed' }"
          @click="$emit('update:statusFilter', 'confirmed')"
        >
          Confirmed
        </button>
        <button
          type="button"
          class="messenger-tab-btn tab-ai-shortcut"
          :class="{ active: activeHandoffId === 'ai' }"
          title="Chat directly with Dispatch AI"
          @click="$emit('select-conversation', 'ai')"
        >
          <i class="bi bi-stars text-warning me-1"></i> AI
        </button>
      </div>
    </div>

    <!-- Conversation List -->
    <div class="messenger-conv-list" role="navigation" aria-label="Conversations list">
      <!-- Pinned Dispatch AI conversation item -->
      <a
        href="#ai-assistant"
        class="messenger-conv-item messenger-conv-ai"
        :class="{ active: activeHandoffId === 'ai' }"
        @click.prevent="$emit('select-conversation', 'ai')"
      >
        <div class="messenger-avatar avatar-ai">
          <i class="bi bi-robot"></i>
        </div>
        <div class="messenger-conv-details">
          <div class="messenger-conv-top">
            <span class="messenger-conv-name">Dispatch AI Assistant</span>
            <span class="messenger-conv-time text-success fw-bold">Online</span>
          </div>
          <div class="messenger-conv-badges">
            <span class="messenger-status-pill status-pill-ai">
              <i class="bi bi-stars me-1"></i>Built-in AI
            </span>
          </div>
          <div class="messenger-conv-bottom">
            <p class="messenger-conv-preview">
              Ask about package tracking, logistics, handoffs &amp; policies
            </p>
          </div>
        </div>
      </a>

      <!-- Empty state if no handoff items match -->
      <div v-if="filteredConversations.length === 0" class="messenger-empty-sidebar">
        <i class="bi bi-chat-square-text text-muted"></i>
        <p class="fw-semibold mb-1">No matching handoff chats</p>
        <small class="text-muted">Start a new handoff above to coordinate with recipients.</small>
      </div>

      <!-- Conversations list -->
      <a
        v-for="conv in filteredConversations"
        :key="conv.id"
        :href="`${baseUrl}package-workflow/conversation/${conv.id}`"
        class="messenger-conv-item"
        :class="{ active: Number(activeHandoffId) === Number(conv.id) }"
        @click.prevent="$emit('select-conversation', conv.id)"
      >
        <div
          class="messenger-avatar"
          :style="{ background: getAvatarGradient(getPartnerName(conv)) }"
        >
          {{ getInitials(getPartnerName(conv)) }}
        </div>
        <div class="messenger-conv-details">
          <div class="messenger-conv-top">
            <span class="messenger-conv-name">{{ getPartnerName(conv) }}</span>
            <span class="messenger-conv-time">{{ formatTimeDisplay(conv) }}</span>
          </div>
          <div class="messenger-conv-badges">
            <span class="messenger-order-badge">ORD {{ conv.order_no }}</span>
            <span
              v-if="conv.status === 'confirmed'"
              class="messenger-status-pill status-pill-confirmed"
            >
              Confirmed
            </span>
            <span
              v-else-if="conv.status === 'pending'"
              class="messenger-status-pill status-pill-pending"
            >
              Active
            </span>
            <span v-else class="messenger-status-pill status-pill-expired">
              {{ capitalize(conv.status) }}
            </span>
          </div>
          <div class="messenger-conv-bottom">
            <p class="messenger-conv-preview">
              {{ conv.latest_message || 'No messages yet' }}
            </p>
            <span v-if="Number(conv.unread_count) > 0" class="messenger-unread-badge">
              {{ conv.unread_count }}
            </span>
          </div>
        </div>
      </a>
    </div>
  </aside>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { getAvatarGradient, getInitials } from '../utils.js';

const props = defineProps({
  conversations: {
    type: Array,
    default: () => [],
  },
  activeHandoffId: {
    type: [Number, String, null],
    default: null,
  },
  searchQuery: {
    type: String,
    default: '',
  },
  statusFilter: {
    type: String,
    default: 'all',
  },
  myId: {
    type: Number,
    default: 0,
  },
  baseUrl: {
    type: String,
    default: '',
  },
});

defineEmits([
  'select-conversation',
  'open-new-handoff',
  'update:searchQuery',
  'update:statusFilter',
]);

const localSearchQuery = ref(props.searchQuery);
let searchDebounceTimer = null;

watch(
  () => props.searchQuery,
  (newVal) => {
    localSearchQuery.value = newVal || '';
  }
);

function onSearchInput(event) {
  const val = event.target.value;
  localSearchQuery.value = val;
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    emit('update:searchQuery', val);
  }, 300);
}

function onClearSearch() {
  localSearchQuery.value = '';
  clearTimeout(searchDebounceTimer);
  emit('update:searchQuery', '');
  const searchInput = document.getElementById('sidebarSearchInput');
  searchInput?.focus();
}

onUnmounted(() => {
  clearTimeout(searchDebounceTimer);
});

function getPartnerName(conv) {
  const isRecipient = Number(conv.recipient_customer_id) === props.myId;
  return isRecipient ? conv.sender_name : conv.recipient_name;
}

function formatTimeDisplay(conv) {
  const ts = conv.latest_message_at || conv.created_at;
  if (!ts) return '';
  const d = new Date(ts.replace(' ', 'T'));
  if (isNaN(d.getTime())) return ts;
  const now = new Date();
  if (d.toDateString() === now.toDateString()) {
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
  return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function capitalize(s) {
  if (!s) return '';
  return s.charAt(0).toUpperCase() + s.slice(1);
}

const filteredConversations = computed(() => {
  const q = props.searchQuery.trim().toLowerCase();
  const filter = props.statusFilter;

  return props.conversations.filter((conv) => {
    // Status filter
    if (filter === 'pending' && conv.status !== 'pending') return false;
    if (filter === 'confirmed' && conv.status !== 'confirmed') return false;

    // Search query
    if (!q) return true;
    const partner = (getPartnerName(conv) || '').toLowerCase();
    const orderNo = (conv.order_no || '').toLowerCase();
    const snippet = (conv.latest_message || '').toLowerCase();
    return partner.includes(q) || orderNo.includes(q) || snippet.includes(q);
  });
});
</script>

