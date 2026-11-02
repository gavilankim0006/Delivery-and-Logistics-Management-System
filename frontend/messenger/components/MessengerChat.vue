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
              <i class="bi bi-check-circle-fill me-1"></i> Receipt Confirmed
            </span>
            <span
              v-else-if="canMessage"
              class="messenger-status-pill status-pill-pending"
            >
              <i class="bi bi-clock-fill me-1"></i> Active Handoff
            </span>
            <span v-else class="messenger-status-pill status-pill-expired">
              <i class="bi bi-lock-fill me-1"></i> Closed
            </span>
          </h3>

          <div class="messenger-header-sub">
            <span>{{ isRecipient ? 'Sender' : 'Recipient' }}</span>
            <span>&middot;</span>
            <a
              :href="`${baseUrl}orders/${handoff.order_id}`"
              target="_blank"
              class="text-decoration-none"
            >
              <span class="messenger-order-badge">Order {{ handoff.order_no }}</span>
            </a>
            <template v-if="handoff.destination_address">
              <span>&middot;</span>
              <span
                class="text-truncate"
                style="max-width: 220px;"
                :title="handoff.destination_address"
              >
                <i class="bi bi-geo-alt me-1"></i>{{ handoff.destination_address }}
              </span>
            </template>
          </div>
        </div>
      </div>

      <div class="messenger-header-actions">
        <!-- AI Assistant toggle button right in the header -->
        <button
          type="button"
          class="btn btn-outline-primary btn-sm btn-header-action btn-header-ai"
          :class="{ 'btn-ai-active': isAiOpen }"
          @click="$emit('toggle-ai')"
          title="Toggle Dispatch AI Assistant"
        >
          <i class="bi bi-stars"></i>
          <span>Dispatch AI</span>
        </button>

        <!-- Recipient confirmation action -->
        <button
          v-if="isRecipient && canMessage"
          type="button"
          class="btn btn-success btn-sm btn-header-action"
          @click="$emit('confirm-receipt', handoff.id)"
        >
          <i class="bi bi-check2-circle"></i> Confirm Receipt
        </button>

        <!-- View order action -->
        <a
          :href="`${baseUrl}orders/${handoff.order_id}`"
          class="btn btn-outline-secondary btn-sm btn-header-action"
          target="_blank"
          title="View full order details"
        >
          <i class="bi bi-box-arrow-up-right"></i> Order
        </a>
      </div>
    </div>

    <!-- Chat Messages Feed -->
    <div class="messenger-chat-feed" ref="feedEl" role="log" aria-live="polite">
      <div class="chat-date-divider">
        <span>{{ formatRelativeDate(handoff.created_at) }}</span>
      </div>

      <div class="chat-system-banner">
        <i class="bi bi-shield-lock-fill"></i>
        <div>
          <strong>Package handoff created for Order {{ handoff.order_no }}</strong><br />
          <span>
            Coordinate directly below.
            {{
              isConfirmed
                ? 'Custody has been safely confirmed.'
                : `Custody confirmation expires ${formatExpiry(handoff.expires_at)}.`
            }}
          </span>
        </div>
      </div>

      <!-- Messages list -->
      <div
        v-for="msg in messages"
        :key="msg.id"
        class="chat-msg-row"
        :class="Number(msg.sender_customer_id) === myId ? 'chat-msg-outgoing' : 'chat-msg-incoming'"
      >
        <div
          v-if="Number(msg.sender_customer_id) !== myId"
          class="chat-bubble-avatar"
          :style="{ background: getAvatarGradient(msg.sender_name) }"
        >
          {{ getInitials(msg.sender_name) }}
        </div>

        <div class="chat-msg-content">
          <span
            v-if="Number(msg.sender_customer_id) !== myId"
            class="chat-sender-label"
          >
            {{ msg.sender_name }}
          </span>
          <div class="chat-bubble">
            <div class="chat-bubble-text">{{ msg.body?.trim() }}</div>
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
    </div>

    <!-- Chat Composer Footer -->
    <div class="messenger-chat-footer" id="chatComposerWrap">
      <template v-if="canMessage">
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

        <form class="chat-input-form" @submit.prevent="submitMessage">
          <textarea
            ref="textareaEl"
            v-model="inputBody"
            class="chat-textarea"
            placeholder="Type a message... (Press Enter to send)"
            rows="1"
            maxlength="4000"
            required
            @keydown="handleKeydown"
            @input="handleInput"
          ></textarea>
          <button
            type="submit"
            class="btn-send-msg"
            :disabled="!inputBody.trim() || isSending"
            title="Send message"
          >
            <i class="bi bi-send-fill"></i>
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
  textareaEl.value.style.height = '40px';
  textareaEl.value.style.height = Math.min(textareaEl.value.scrollHeight, 120) + 'px';
}

function submitMessage() {
  const text = inputBody.value.trim();
  if (!text || props.isSending) return;

  emit('send-message', text);
  inputBody.value = '';
  if (textareaEl.value) {
    textareaEl.value.style.height = '40px';
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

