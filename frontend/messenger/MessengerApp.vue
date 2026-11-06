<template>
  <div class="page-shell messenger-wrapper">
    <!-- Main Messenger Shell -->
    <div
      class="messenger-shell"
      :class="{
        'chat-open': isChatOpen,
        'ai-panel-open': isAiPanelOpen,
      }"
      id="messengerShell"
    >
      <!-- Sidebar -->
      <MessengerSidebar
        :conversations="conversations"
        :active-handoff-id="activeHandoffId"
        :search-query="searchQuery"
        :status-filter="statusFilter"
        :my-id="customerId"
        :base-url="baseUrl"
        @select-conversation="onSelectConversation"
        @open-new-handoff="openNewHandoffModal"
        @update:search-query="searchQuery = $event"
        @update:status-filter="statusFilter = $event"
      />

      <!-- Main Chat Area or Dedicated AI Screen -->
      <template v-if="activeHandoffId === 'ai'">
        <!-- Dedicated AI Assistant Full View inside messenger -->
        <main class="messenger-main messenger-ai-main" aria-label="Dispatch AI Assistant">
          <div class="messenger-chat-header">
            <div class="messenger-header-left">
              <button
                type="button"
                class="btn-chat-back"
                aria-label="Back to conversations"
                @click="isChatOpen = false"
              >
                <i class="bi bi-chevron-left"></i>
              </button>
              <div class="messenger-avatar avatar-ai">
                <i class="bi bi-robot"></i>
              </div>
              <div class="messenger-header-info">
                <h3 class="messenger-header-title">
                  <span>Dispatch AI Assistant</span>
                  <span class="messenger-status-pill status-pill-ai">
                    <i class="bi bi-stars me-1"></i>Built-in AI
                  </span>
                </h3>
                <div class="messenger-header-sub">
                  <span>Logistics, package custody, orders &amp; support assistant</span>
                </div>
              </div>
            </div>
            <div class="messenger-header-actions">
              <button
                type="button"
                class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
                title="Clear AI Conversation"
                @click="aiPanelRef?.resetConversation()"
              >
                <i class="bi bi-arrow-counterclockwise"></i>
                <span class="d-none d-sm-inline">Clear</span>
              </button>
            </div>
          </div>

          <!-- Embedded AI panel content stretched as main chat -->
          <AiChatPanel
            ref="aiPanelRef"
            :is-open="true"
            :base-url="baseUrl"
            :order-context="null"
            @close="isChatOpen = false"
            style="width: 100%; max-width: 100%; border: none; flex: 1;"
          />
        </main>
      </template>

      <!-- Active Handoff Chat Area -->
      <template v-else-if="activeHandoff">
        <MessengerChat
          :handoff="activeHandoff"
          :messages="messages"
          :my-id="customerId"
          :base-url="baseUrl"
          :is-ai-open="isAiPanelOpen"
          :is-sending="isSending"
          @send-message="onSendMessage"
          @confirm-receipt="onConfirmReceipt"
          @toggle-ai="toggleAiPanel"
          @back-to-list="isChatOpen = false"
        />

        <!-- Collapsible Embedded Right-side AI Assistant Panel -->
        <AiChatPanel
          :is-open="isAiPanelOpen"
          :base-url="baseUrl"
          :order-context="activeHandoff"
          @close="isAiPanelOpen = false"
        />
      </template>

      <!-- Empty State -->
      <template v-else>
        <main class="messenger-main">
          <div class="messenger-empty-state">
            <div class="empty-state-icon-wrap">
              <i class="bi bi-chat-quote-fill"></i>
            </div>
            <h3 class="empty-state-title">Your Package Messenger</h3>
            <p class="empty-state-desc">
              Coordinate package handoffs with senders and recipients, confirm package transfers securely, or chat with Dispatch AI.
            </p>
            <div class="d-flex gap-2 flex-wrap justify-content-center">
              <button
                type="button"
                class="btn btn-primary px-4 py-2 fw-semibold rounded-pill"
                @click="openNewHandoffModal"
              >
                <i class="bi bi-plus-lg me-1"></i> Start New Handoff
              </button>
              <button
                type="button"
                class="btn btn-outline-primary px-4 py-2 fw-semibold rounded-pill"
                @click="onSelectConversation('ai')"
              >
                <i class="bi bi-robot me-1"></i> Chat with Dispatch AI
              </button>
            </div>
          </div>
        </main>
      </template>
    </div>

    <!-- New Handoff Modal Component -->
    <NewHandoffModal
      ref="newHandoffModalRef"
      :eligible-orders="eligibleOrders"
      :preset-order-id="presetOrderId"
      :base-url="baseUrl"
      :csrf-token="csrfToken"
      @handoff-created="onHandoffCreated"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import MessengerSidebar from './components/MessengerSidebar.vue';
import MessengerChat from './components/MessengerChat.vue';
import AiChatPanel from './components/AiChatPanel.vue';
import NewHandoffModal from './components/NewHandoffModal.vue';

const props = defineProps({
  customerId: {
    type: Number,
    required: true,
  },
  csrfToken: {
    type: String,
    required: true,
  },
  baseUrl: {
    type: String,
    required: true,
  },
  conversations: {
    type: Array,
    default: () => [],
  },
  activeHandoff: {
    type: Object,
    default: null,
  },
  messages: {
    type: Array,
    default: () => [],
  },
  eligibleOrders: {
    type: Array,
    default: () => [],
  },
  presetOrderId: {
    type: Number,
    default: 0,
  },
});

const conversations = ref([...props.conversations]);
const activeHandoff = ref(props.activeHandoff);
const activeHandoffId = ref(props.activeHandoff ? props.activeHandoff.id : null);
const messages = ref([...props.messages]);
const isChatOpen = ref(!!props.activeHandoff);
const isAiPanelOpen = ref(false);
const searchQuery = ref('');
const statusFilter = ref('all');
const isSending = ref(false);

const newHandoffModalRef = ref(null);
const aiPanelRef = ref(null);
const renderedMessageIds = new Set(props.messages.map((m) => Number(m.id)));
let pollingTimer = null;

function toggleAiPanel() {
  isAiPanelOpen.value = !isAiPanelOpen.value;
}

function openNewHandoffModal() {
  if (newHandoffModalRef.value) {
    newHandoffModalRef.value.showModal();
  }
}

async function onSelectConversation(id, updateUrl = true) {
  if (id === 'ai') {
    activeHandoffId.value = 'ai';
    isChatOpen.value = true;
    isAiPanelOpen.value = true;
    stopPolling();
    return;
  }

  activeHandoffId.value = Number(id);
  isChatOpen.value = true;
  stopPolling();

  if (updateUrl) {
    const nextUrl = `${props.baseUrl}package-workflow/conversation/${id}`;
    window.history.pushState({ handoffId: id }, '', nextUrl);
  }

  try {
    const res = await fetch(`${props.baseUrl}package-workflow/conversation/${id}`, {
      headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    if (!data || !data.success) {
      alert(data?.error || 'Unable to load conversation');
      return;
    }

    activeHandoff.value = data.handoff;
    messages.value = data.messages || [];
    renderedMessageIds.clear();
    messages.value.forEach((m) => renderedMessageIds.add(Number(m.id)));

    // Clear unread badge in local list
    const found = conversations.value.find((c) => Number(c.id) === Number(id));
    if (found) {
      found.unread_count = 0;
    }

    startPolling();
  } catch (err) {
    console.error('Failed to load conversation:', err);
  }
}

async function onSendMessage(text) {
  if (!activeHandoff.value || isSending.value) return;

  const handoffId = activeHandoff.value.id;
  const tempId = `temp_${Date.now()}`;

  // Optimistic update
  messages.value.push({
    id: tempId,
    sender_customer_id: props.customerId,
    sender_name: 'Me',
    body: text,
    created_at: new Date().toISOString(),
  });

  // Update preview snippet in sidebar
  const conv = conversations.value.find((c) => Number(c.id) === Number(handoffId));
  if (conv) {
    conv.latest_message = text;
    conv.latest_message_at = new Date().toISOString();
  }

  isSending.value = true;

  try {
    const res = await fetch(`${props.baseUrl}package-workflow/conversation/${handoffId}/send`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': props.csrfToken,
      },
      body: JSON.stringify({
        body: text,
        csrf_token: props.csrfToken,
      }),
    });

    const data = await res.json();
    if (data && data.success && data.message) {
      renderedMessageIds.add(Number(data.message.id));
      const idx = messages.value.findIndex((m) => m.id === tempId);
      if (idx !== -1) {
        messages.value[idx] = data.message;
      }
    } else {
      throw new Error(data?.error || 'Failed to send message');
    }
  } catch (err) {
    console.error('Send error:', err);
    const idx = messages.value.findIndex((m) => m.id === tempId);
    if (idx !== -1) {
      messages.value[idx].failed = true;
    }
  } finally {
    isSending.value = false;
  }
}

async function onConfirmReceipt(handoffId) {
  if (!confirm('Confirm that you have safely received this package?')) return;

  try {
    const res = await fetch(`${props.baseUrl}package-workflow/conversation/${handoffId}/confirm`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': props.csrfToken,
      },
      body: JSON.stringify({ csrf_token: props.csrfToken }),
    });

    const data = await res.json();
    if (data && data.success) {
      alert('Package receipt confirmed successfully!');
      if (activeHandoff.value) {
        activeHandoff.value.status = 'confirmed';
      }
      const conv = conversations.value.find((c) => Number(c.id) === Number(handoffId));
      if (conv) {
        conv.status = 'confirmed';
      }
    } else {
      alert(data?.error || 'Unable to confirm receipt.');
    }
  } catch (err) {
    console.error('Confirmation error:', err);
  }
}

function onHandoffCreated(newHandoffId) {
  if (newHandoffId) {
    window.location.href = `${props.baseUrl}package-workflow/conversation/${newHandoffId}`;
  } else {
    window.location.reload();
  }
}

function startPolling() {
  stopPolling();
  if (activeHandoffId.value === 'ai' || !activeHandoff.value) return;

  pollingTimer = setInterval(pollMessages, 3500);
}

function stopPolling() {
  if (pollingTimer) {
    clearInterval(pollingTimer);
    pollingTimer = null;
  }
}

async function pollMessages() {
  if (!activeHandoff.value || activeHandoffId.value === 'ai') return;
  const handoffId = activeHandoff.value.id;

  try {
    const res = await fetch(`${props.baseUrl}package-workflow/conversation/${handoffId}/messages`, {
      headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    if (!data || !data.success) return;

    if (data.handoff && data.handoff.status !== activeHandoff.value.status) {
      activeHandoff.value = data.handoff;
    }

    const newMsgs = data.messages || [];
    newMsgs.forEach((msg) => {
      const msgId = Number(msg.id);
      if (!renderedMessageIds.has(msgId)) {
        renderedMessageIds.add(msgId);
        messages.value.push(msg);

        const conv = conversations.value.find((c) => Number(c.id) === Number(handoffId));
        if (conv) {
          conv.latest_message = msg.body;
          conv.latest_message_at = msg.created_at;
        }
      }
    });
  } catch (err) {
    console.debug('Polling error:', err);
  }
}

function handleVisibilityChange() {
  if (document.hidden) {
    stopPolling();
  } else if (activeHandoff.value && activeHandoffId.value !== 'ai') {
    startPolling();
  }
}

function handlePopState() {
  const match = window.location.pathname.match(/package-workflow\/conversation\/(\d+)/);
  if (match && match[1]) {
    onSelectConversation(parseInt(match[1], 10), false);
  } else {
    activeHandoffId.value = null;
    activeHandoff.value = null;
    messages.value = [];
    isChatOpen.value = false;
    isAiPanelOpen.value = false;
    stopPolling();
  }
}

// Connect 3D bot floating outside to the messenger AI panel!
function setup3dBotIntegration() {
  // If the user clicks the 3D bot trigger floating outside, open the embedded AI assistant in messenger
  const checkBot = setInterval(() => {
    const botTrigger = document.getElementById('ai-bot-trigger');
    if (botTrigger) {
      clearInterval(checkBot);
      botTrigger.addEventListener('click', () => {
        // Switch to the dedicated AI assistant conversation in messenger
        onSelectConversation('ai');
      });
    }
  }, 300);

  setTimeout(() => clearInterval(checkBot), 5000);
}

onMounted(() => {
  document.addEventListener('visibilitychange', handleVisibilityChange);
  window.addEventListener('popstate', handlePopState);
  setup3dBotIntegration();

  if (activeHandoff.value) {
    startPolling();
  }
});

onUnmounted(() => {
  stopPolling();
  document.removeEventListener('visibilitychange', handleVisibilityChange);
  window.removeEventListener('popstate', handlePopState);
});
</script>

