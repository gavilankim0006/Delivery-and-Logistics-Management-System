<template>
  <main class="messenger-main" aria-label="Current conversation">
    <!-- Active Chat Header -->
    <div class="messenger-chat-header">
      <div class="messenger-header-left">
        <button
          type="button"
          class="btn-chat-back"
          aria-label="Back to conversations"
          @click="$emit('back-to-list')"
        >
          <i class="bi bi-chevron-left"></i>
        </button>

        <div
          class="messenger-avatar"
          :style="{ background: getAvatarGradient(partnerName) }"
        >
          {{ getInitials(partnerName) }}
        </div>

        <div class="messenger-header-info">
          <h3 class="messenger-header-title">
            <span>{{ partnerName }}</span>
            <span
              v-if="isConfirmed"
              class="messenger-status-pill status-pill-confirmed"
            >
              <i class="bi bi-check-circle-fill"></i> Receipt Confirmed
            </span>
            <span
              v-else-if="canMessage"
              class="messenger-status-pill status-pill-pending"
            >
              <span class="status-pulse-dot"></span> Active Handoff
            </span>
            <span v-else class="messenger-status-pill status-pill-expired">
              <i class="bi bi-lock-fill"></i> Closed
            </span>
          </h3>

          <div class="messenger-header-sub">
            <span class="messenger-role-tag">{{ isRecipient ? 'Sender' : 'Recipient' }}</span>
            <span class="messenger-sub-dot">&middot;</span>
            <a
              :href="`${baseUrl}orders/${handoff.order_id}`"
              target="_blank"
              class="messenger-order-badge-link"
              title="View full order details"
            >
              <span class="messenger-order-badge">ORD {{ handoff.order_no }}</span>
            </a>
            <template v-if="handoff.destination_address">
              <span class="messenger-sub-dot">&middot;</span>
              <span class="messenger-address-text" :title="handoff.destination_address">
                <i class="bi bi-geo-alt-fill me-1 text-muted"></i>{{ handoff.destination_address }}
              </span>
            </template>
          </div>
        </div>
      </div>

      <div class="messenger-header-actions">
        <!-- AI Assistant toggle button -->
        <button
          type="button"
          class="btn-action-pill btn-ai-toggle"
          :class="{ active: isAiOpen }"
          @click="$emit('toggle-ai')"
          title="Toggle Dispatch AI Assistant"
        >
          <i class="bi bi-robot"></i>
          <span class="d-none d-sm-inline ms-1">Dispatch AI</span>
        </button>

        <!-- Receipt confirmation button (Recipient only, while pending) -->
        <button
          v-if="isRecipient && canMessage && !isConfirmed"
          type="button"
          class="btn-action-pill btn-confirm-handoff"
          @click="$emit('confirm-receipt')"
        >
          <i class="bi bi-check2-circle me-1"></i>
          <span>Confirm Custody</span>
        </button>
      </div>
    </div>

    <!-- Messages Feed -->
    <div class="messenger-chat-feed messenger-feed" ref="feedEl" role="log" aria-label="Messages" aria-live="polite" aria-relevant="additions text">
      <!-- Expiry notice banner if pending -->
      <div v-if="canMessage && !isConfirmed" class="chat-system-banner banner-handoff-pending">
        <div class="banner-icon-badge">
          <i class="bi bi-shield-lock-fill"></i>
        </div>
        <div class="banner-text">
          <div class="banner-title">Active Package Handoff Coordination</div>
          <div class="banner-subtitle">
            Handoff confirmation expires: <strong>{{ formatExpiry(handoff.expires_at) }}</strong>. Coordinate custody transfer below.
          </div>
        </div>
      </div>

      <!-- Confirmed banner -->
      <div v-if="isConfirmed" class="chat-system-banner banner-handoff-confirmed">
        <div class="banner-icon-badge badge-confirmed">
          <i class="bi bi-check-circle-fill"></i>
        </div>
        <div class="banner-text">
          <div class="banner-title">Package Received &amp; Custody Confirmed</div>
          <div class="banner-subtitle">Custody was officially acknowledged by recipient.</div>
        </div>
      </div>

      <!-- Empty messages state -->
      <div v-if="messages.length === 0" class="chat-feed-empty">
        <div class="chat-feed-empty-icon">
          <i class="bi bi-chat-quote-fill"></i>
        </div>
        <h6>No messages yet</h6>
        <p>Start coordinating your package handoff directly with {{ partnerName }}.</p>
      </div>

      <!-- Feed items with Date Dividers & Smart Sender Grouping -->
      <template v-for="(msg, index) in messages" :key="msg.id || index">
        <!-- Date Divider when day changes -->
        <div v-if="shouldShowDateDivider(index)" class="chat-date-divider">
          <span>{{ getDateDividerText(msg.created_at) }}</span>
        </div>

        <!-- Message Row -->
        <div :class="getMsgRowClasses(msg, index)">
          <!-- Incoming avatar (only shown on the last message in a sender group) -->
          <template v-if="Number(msg.sender_customer_id) !== myId">
            <div
              v-if="isLastInGroup(index)"
              class="chat-bubble-avatar"
              :style="{ background: getAvatarGradient(msg.sender_name) }"
              :title="msg.sender_name"
            >
              {{ getInitials(msg.sender_name) }}
            </div>
            <div v-else class="chat-bubble-avatar-spacer" aria-hidden="true"></div>
          </template>

          <div class="chat-msg-content">
            <!-- Sender label ONLY on the first message of a consecutive group -->
            <span
              v-if="Number(msg.sender_customer_id) !== myId && isFirstInGroup(index)"
              class="chat-sender-label"
            >
              {{ msg.sender_name }}
            </span>

            <div class="chat-bubble">
              <div class="chat-bubble-text">{{ msg.body }}</div>
              <div class="chat-bubble-footer">
                <span>{{ formatMsgTime(msg.created_at) }}</span>
                <i
                  v-if="Number(msg.sender_customer_id) === myId"
                  class="bi bi-check2-all chat-check-icon"
                  title="Delivered"
                ></i>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Chat Composer -->
    <div class="messenger-chat-footer">
      <template v-if="canMessage">
        <!-- Quick action suggestion chips -->
        <div class="chat-quick-chips">
          <button
            type="button"
            class="quick-chip-btn"
            @click="useChip('📍 I\'m outside the lobby.')"
          >
            <i class="bi bi-geo-alt"></i> Outside lobby
          </button>
          <button
            type="button"
            class="quick-chip-btn"
            @click="useChip('⏳ On my way down now.')"
          >
            <i class="bi bi-clock"></i> On my way
          </button>
          <button
            type="button"
            class="quick-chip-btn"
            @click="useChip('📦 Package received safely, thank you!')"
          >
            <i class="bi bi-box-seam"></i> Received safely
          </button>
          <button
            type="button"
            class="quick-chip-btn"
            @click="useChip('👋 Ready for handoff?')"
          >
            <i class="bi bi-hand-thumbs-up"></i> Ready now
          </button>
        </div>

        <form class="chat-input-form" data-no-validate="true" @submit.prevent="submitMessage">
          <textarea
            ref="textareaEl"
            v-model="inputBody"
            class="chat-textarea"
            placeholder="Write a message…"
            aria-label="Message"
            rows="1"
            maxlength="4000"
            data-no-validate="true"
            @keydown="handleKeydown"
            @input="handleInput"
          ></textarea>
          <button
            type="submit"
            class="btn-send-msg"
            :disabled="!inputBody.trim() || isSending"
            aria-label="Send message"
          >
            <i class="bi bi-send-fill" v-if="!isSending"></i>
            <span class="spinner-border spinner-border-sm" v-else></span>
          </button>
        </form>
      </template>

      <div v-else class="chat-closed-banner">
        <i class="bi bi-lock-fill"></i>
        <span>
          This package handoff is {{ handoff.status }}. Message history is preserved for reference.
        </span>
      </div>
    </div>
  </main>
</template>

<script setup>
import { ref, computed, nextTick, watch, onMounted } from 'vue';
import {
  getAvatarGradient,
  getInitials,
  formatTime,
  formatRelativeDate,
} from '../utils.js';

const props = defineProps({
  handoff: {
    type: Object,
    required: true,
  },
  messages: {
    type: Array,
    default: () => [],
  },
  myId: {
    type: Number,
    required: true,
  },
  baseUrl: {
    type: String,
    default: '',
  },
  isAiOpen: {
    type: Boolean,
    default: false,
  },
  isSending: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  'send-message',
  'confirm-receipt',
  'toggle-ai',
  'back-to-list',
]);

const inputBody = ref('');
const textareaEl = ref(null);
const feedEl = ref(null);

const isRecipient = computed(() => {
  return Number(props.handoff.recipient_customer_id) === props.myId;
});

const partnerName = computed(() => {
  return isRecipient.value ? props.handoff.sender_name : props.handoff.recipient_name;
});

const isConfirmed = computed(() => {
  return props.handoff.status === 'confirmed';
});

const canMessage = computed(() => {
  if (props.handoff.status !== 'pending') return false;
  if (!props.handoff.expires_at) return true;
  return new Date(props.handoff.expires_at.replace(' ', 'T')).getTime() > Date.now();
});

function formatMsgTime(iso) {
  return formatTime(iso);
}

function formatExpiry(iso) {
  if (!iso) return '';
  const d = new Date(iso.replace(' ', 'T'));
  if (isNaN(d.getTime())) return iso;
  return d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ', ' +
    d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Date Divider Helpers
function isSameDay(dateStrA, dateStrB) {
  if (!dateStrA || !dateStrB) return false;
  const a = new Date(dateStrA.replace(' ', 'T'));
  const b = new Date(dateStrB.replace(' ', 'T'));
  if (isNaN(a.getTime()) || isNaN(b.getTime())) return false;
  return a.getFullYear() === b.getFullYear() &&
         a.getMonth() === b.getMonth() &&
         a.getDate() === b.getDate();
}

function shouldShowDateDivider(index) {
  if (index === 0) return true;
  return !isSameDay(props.messages[index - 1]?.created_at, props.messages[index]?.created_at);
}

function getDateDividerText(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr.replace(' ', 'T'));
  if (isNaN(d.getTime())) return dateStr;
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const target = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const diffDays = Math.round((today - target) / (1000 * 60 * 60 * 24));
  if (diffDays === 0) return 'Today';
  if (diffDays === 1) return 'Yesterday';
  return d.toLocaleDateString([], {
    month: 'short',
    day: 'numeric',
    year: d.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
  });
}

// Message Grouping Helpers
function isSameSender(msgA, msgB) {
  if (!msgA || !msgB) return false;
  return Number(msgA.sender_customer_id) === Number(msgB.sender_customer_id);
}

function isCloseInTime(msgA, msgB, minutes = 5) {
  if (!msgA || !msgB) return false;
  const timeA = new Date(msgA.created_at.replace(' ', 'T')).getTime();
  const timeB = new Date(msgB.created_at.replace(' ', 'T')).getTime();
  if (isNaN(timeA) || isNaN(timeB)) return false;
  return Math.abs(timeA - timeB) <= minutes * 60 * 1000;
}

function isFirstInGroup(index) {
  if (index === 0) return true;
  if (shouldShowDateDivider(index)) return true;
  const prev = props.messages[index - 1];
  const curr = props.messages[index];
  return !isSameSender(prev, curr) || !isCloseInTime(prev, curr);
}

function isLastInGroup(index) {
  if (index === props.messages.length - 1) return true;
  if (shouldShowDateDivider(index + 1)) return true;
  const curr = props.messages[index];
  const next = props.messages[index + 1];
  return !isSameSender(curr, next) || !isCloseInTime(curr, next);
}

function getMsgRowClasses(msg, index) {
  const isOutgoing = Number(msg.sender_customer_id) === props.myId;
  const first = isFirstInGroup(index);
  const last = isLastInGroup(index);
  return {
    'chat-msg-row': true,
    'chat-msg-outgoing': isOutgoing,
    'chat-msg-incoming': !isOutgoing,
    'msg-group-first': first,
    'msg-group-middle': !first && !last,
    'msg-group-last': last,
    'msg-group-single': first && last,
  };
}

function scrollToBottom(smooth = false) {
  nextTick(() => {
    if (feedEl.value) {
      feedEl.value.scrollTo({
        top: feedEl.value.scrollHeight,
        behavior: smooth ? 'smooth' : 'auto',
      });
    }
  });
}

function useChip(text) {
  inputBody.value = text;
  nextTick(() => {
    handleInput();
    textareaEl.value?.focus();
  });
}

function handleKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    submitMessage();
  }
}

function handleInput() {
  if (!textareaEl.value) return;
  textareaEl.value.style.height = '42px';
  textareaEl.value.style.height = Math.min(textareaEl.value.scrollHeight, 120) + 'px';
}

function submitMessage() {
  const text = inputBody.value.trim();
  if (!text || props.isSending) return;

  emit('send-message', text);
  inputBody.value = '';
  if (textareaEl.value) {
    textareaEl.value.style.height = '42px';
  }
  scrollToBottom(true);
}

watch(
  () => props.messages.length,
  () => {
    scrollToBottom(true);
  }
);

watch(
  () => props.handoff.id,
  () => {
    scrollToBottom(false);
  }
);

onMounted(() => {
  scrollToBottom(false);
});
</script>
