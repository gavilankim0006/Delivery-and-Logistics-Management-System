<template>
  <aside class="ai-panel" :class="{ 'is-open': isOpen }" aria-label="Dispatch AI Assistant">
    <!-- Header -->
    <div class="ai-panel-header">
      <div class="ai-panel-header-left">
        <span class="ai-header-dot" title="AI Online"></span>
        <div class="ai-panel-title-wrap">
          <h6 class="ai-panel-title">
            <i class="bi bi-robot me-1"></i> Dispatch AI
          </h6>
          <span class="ai-chat-role-tag">{{ roleTag }}</span>
        </div>
      </div>
      <div class="ai-panel-header-actions">
        <button
          type="button"
          class="btn-ai-action"
          title="Clear AI Conversation"
          @click="resetConversation"
        >
          <i class="bi bi-arrow-counterclockwise"></i>
        </button>
        <button
          type="button"
          class="btn-ai-action"
          title="Close AI panel"
          @click="$emit('close')"
        >
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>

    <!-- Active order context hint if order context provided -->
    <div v-if="orderContext" class="ai-panel-order-banner">
      <i class="bi bi-info-circle-fill me-1 text-primary"></i>
      <span>Active context: <strong>Order #{{ orderContext.order_no }}</strong></span>
    </div>

    <!-- Messages feed -->
    <div class="ai-panel-messages" ref="feedEl">
      <div
        v-for="(msg, idx) in messages"
        :key="idx"
        class="ai-msg"
        :class="msg.sender === 'user' ? 'ai-msg-user' : 'ai-msg-assistant'"
      >
        <div v-if="msg.sender === 'assistant'" v-html="formatMarkdown(msg.content)"></div>
        <div v-else>{{ msg.content }}</div>
      </div>

      <!-- Thinking indicator -->
      <div v-if="isThinking" class="ai-typing-indicator active">
        <span class="ai-typing-dot"></span>
        <span class="ai-typing-dot"></span>
        <span class="ai-typing-dot"></span>
      </div>
    </div>

    <!-- Quick prompt suggestions -->
    <div v-if="messages.length <= 2 && !isThinking" class="ai-panel-quick-prompts">
      <button
        type="button"
        class="quick-prompt-chip"
        @click="askQuickPrompt('How does the package custody handoff confirmation work?')"
      >
        <i class="bi bi-shield-check"></i> How does custody handoff work?
      </button>
      <button
        type="button"
        class="quick-prompt-chip"
        v-if="orderContext"
        :key="orderContext.order_no"
        @click="askQuickPrompt(`What is the current status of Order #${orderContext.order_no}?`)"
      >
        <i class="bi bi-box-seam"></i> Status of Order #{{ orderContext.order_no }}
      </button>
      <button
        type="button"
        class="quick-prompt-chip"
        @click="askQuickPrompt('What should I do if a package has missing items or is damaged?')"
      >
        <i class="bi bi-exclamation-triangle"></i> Package issue or report
      </button>
    </div>

    <!-- Input form -->
    <form class="ai-panel-input-wrap" @submit.prevent="sendMessage">
      <input
        type="text"
        class="ai-panel-input"
        v-model="inputQuery"
        placeholder="Ask Dispatch AI anything..."
        :disabled="isThinking"
        autocomplete="off"
        ref="inputEl"
      />
      <button
        type="submit"
        class="ai-panel-send-btn"
        :disabled="!inputQuery.trim() || isThinking"
        title="Send query"
      >
        <i class="bi bi-send-fill"></i>
      </button>
    </form>
  </aside>
</template>

<script setup>
import { ref, onMounted, nextTick, watch } from 'vue';
import { formatMarkdown } from '../utils.js';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  baseUrl: {
    type: String,
    default: '',
  },
  orderContext: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);

const messages = ref([
  {
    sender: 'assistant',
    content: 'Hello! 👋 I am your Dispatch AI assistant, now built directly into Package Messenger. Ask me about orders, custody handoffs, delivery statuses, or courier rules!',
  },
]);

const inputQuery = ref('');
const isThinking = ref(false);
const conversationId = ref(null);
const roleTag = ref('AI ASSISTANT');
const feedEl = ref(null);
const inputEl = ref(null);
let historyLoaded = false;

function scrollToBottom() {
  nextTick(() => {
    if (feedEl.value) {
      feedEl.value.scrollTop = feedEl.value.scrollHeight;
    }
  });
}

async function loadHistory() {
  if (historyLoaded) return;
  try {
    const res = await fetch(`${props.baseUrl}ai/history`);
    const data = await res.json();
    if (data.success && Array.isArray(data.messages) && data.messages.length > 0) {
      conversationId.value = data.conversation_id;
      messages.value = data.messages.map((m) => ({
        sender: m.sender,
        content: m.content,
      }));
      historyLoaded = true;
      scrollToBottom();
    }
  } catch (err) {
    console.debug('Failed to load AI history:', err);
  }
}

async function sendMessage() {
  const text = inputQuery.value.trim();
  if (!text || isThinking.value) return;

  messages.value.push({
    sender: 'user',
    content: text,
  });

  inputQuery.value = '';
  isThinking.value = true;
  scrollToBottom();

  try {
    const res = await fetch(`${props.baseUrl}ai/chat`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        message: text,
        conversation_id: conversationId.value,
      }),
    });

    const responseText = await res.text();
    let data;
    try {
      data = JSON.parse(responseText);
    } catch {
      messages.value.push({
        sender: 'assistant',
        content: `⚠️ Server notice (${res.status}): ${responseText.slice(0, 200)}`,
      });
      return;
    }

    if (data && data.success) {
      conversationId.value = data.conversation_id;
      messages.value.push({
        sender: 'assistant',
        content: data.reply || 'No response generated.',
      });
      if (data.role) {
        roleTag.value = data.role.toUpperCase();
      }
    } else {
      messages.value.push({
        sender: 'assistant',
        content: `⚠️ **Notice**: ${data?.error || 'Could not complete query.'}`,
      });
    }
  } catch (err) {
    messages.value.push({
      sender: 'assistant',
      content: '⚠️ **Connection Error**: Could not reach Dispatch AI service.',
    });
  } finally {
    isThinking.value = false;
    scrollToBottom();
    nextTick(() => {
      inputEl.value?.focus();
    });
  }
}

function askQuickPrompt(promptText) {
  inputQuery.value = promptText;
  sendMessage();
}

function resetConversation() {
  conversationId.value = null;
  messages.value = [
    {
      sender: 'assistant',
      content: 'Conversation reset! How can I assist you with your package or deliveries today?',
    },
  ];
  scrollToBottom();
}

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      loadHistory();
      scrollToBottom();
      nextTick(() => {
        inputEl.value?.focus();
      });
    }
  }
);

onMounted(() => {
  if (props.isOpen) {
    loadHistory();
  }
});
</script>

