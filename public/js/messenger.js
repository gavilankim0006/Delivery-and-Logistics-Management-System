/**
 * Modern Built-in Package Messenger JavaScript
 */
(function () {
  'use strict';

  const avatarPalettes = [
    'linear-gradient(135deg, #4f46e5, #3730a3)',
    'linear-gradient(135deg, #0284c7, #0369a1)',
    'linear-gradient(135deg, #059669, #047857)',
    'linear-gradient(135deg, #d97706, #b45309)',
    'linear-gradient(135deg, #db2777, #be185d)',
    'linear-gradient(135deg, #7c3aed, #6d28d9)',
    'linear-gradient(135deg, #ea580c, #c2410c)',
  ];

  function getAvatarGradient(name) {
    if (!name) return avatarPalettes[0];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
      hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const idx = Math.abs(hash) % avatarPalettes.length;
    return avatarPalettes[idx];
  }

  function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
  }

  function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
  }

  function formatTime(isoString) {
    if (!isoString) return '';
    const d = new Date(isoString.replace(' ', 'T'));
    if (isNaN(d.getTime())) return isoString;
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function formatRelativeDate(isoString) {
    if (!isoString) return '';
    const d = new Date(isoString.replace(' ', 'T'));
    if (isNaN(d.getTime())) return isoString;
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const msgDate = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const diffDays = Math.round((today - msgDate) / (1000 * 60 * 60 * 24));
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) {
      return d.toLocaleDateString([], { weekday: 'short' });
    }
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
  }

  window.PackageMessenger = {
    config: {},
    activeHandoff: null,
    renderedMessageIds: new Set(),
    pollingTimer: null,
    isSending: false,

    init: function (config) {
      this.config = config || {};
      this.cacheElements();
      this.bindEvents();
      this.initAvatars();

      if (this.config.initialHandoffId) {
        this.selectConversation(this.config.initialHandoffId, false);
      } else if (this.config.hasInitialHandoff && this.config.activeHandoff) {
        this.activeHandoff = this.config.activeHandoff;
        this.renderedMessageIds = new Set(this.config.initialMessageIds || []);
        this.scrollToBottom(false);
        this.startPolling();
      }

      // Check if user came from an order page to start a new handoff
      if (this.config.presetOrderId) {
        const modalEl = document.getElementById('newHandoffModal');
        if (modalEl && window.bootstrap) {
          const modal = new bootstrap.Modal(modalEl);
          const orderSelect = document.getElementById('newHandoffOrderSelect');
          if (orderSelect) {
            orderSelect.value = String(this.config.presetOrderId);
          }
          modal.show();
        }
      }
    },

    cacheElements: function () {
      this.shell = document.getElementById('messengerShell');
      this.convList = document.getElementById('messengerConvList');
      this.chatFeed = document.getElementById('chatFeed');
      this.chatInput = document.getElementById('chatInput');
      this.chatForm = document.getElementById('chatForm');
      this.btnSend = document.getElementById('btnSend');
      this.searchInput = document.getElementById('sidebarSearch');
      this.btnSearchClear = document.getElementById('sidebarSearchClear');
      this.filterTabs = document.querySelectorAll('.messenger-tab-btn');
      this.btnChatBack = document.getElementById('btnChatBack');
      this.quickChips = document.querySelectorAll('.quick-chip-btn');
    },

    bindEvents: function () {
      const self = this;

      // Sidebar search & filter
      if (this.searchInput) {
        this.searchInput.addEventListener('input', function () {
          self.filterSidebar(this.value.trim());
          if (self.btnSearchClear) {
            self.btnSearchClear.style.display = this.value.length > 0 ? 'block' : 'none';
          }
        });
      }

      if (this.btnSearchClear) {
        this.btnSearchClear.addEventListener('click', function () {
          self.searchInput.value = '';
          self.filterSidebar('');
          this.style.display = 'none';
          self.searchInput.focus();
        });
      }

      // Filter tabs (All, Active, Confirmed)
      this.filterTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          self.filterTabs.forEach(function (t) { t.classList.remove('active'); });
          this.classList.add('active');
          const filter = this.getAttribute('data-filter') || 'all';
          self.applyStatusFilter(filter);
        });
      });

      // Mobile back button
      if (this.btnChatBack) {
        this.btnChatBack.addEventListener('click', function () {
          if (self.shell) self.shell.classList.remove('chat-open');
        });
      }

      // Conversation item click
      if (this.convList) {
        this.convList.addEventListener('click', function (e) {
          const item = e.target.closest('.messenger-conv-item');
          if (item) {
            e.preventDefault();
            const id = parseInt(item.getAttribute('data-handoff-id'), 10);
            if (id) {
              self.selectConversation(id, true);
            }
          }
        });
      }

      // Send form
      if (this.chatForm) {
        this.chatForm.addEventListener('submit', function (e) {
          e.preventDefault();
          self.sendMessage();
        });
      }

      // Auto-expand textarea & Enter to send
      if (this.chatInput) {
        this.chatInput.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            self.sendMessage();
          }
        });

        this.chatInput.addEventListener('input', function () {
          this.style.height = '40px';
          this.style.height = Math.min(this.scrollHeight, 120) + 'px';
          if (self.btnSend) {
            self.btnSend.disabled = this.value.trim().length === 0;
          }
        });
      }

      // Quick suggestion chips
      this.quickChips.forEach(function (chip) {
        chip.addEventListener('click', function () {
          const text = this.getAttribute('data-text');
          if (text && self.chatInput) {
            self.chatInput.value = text;
            self.chatInput.dispatchEvent(new Event('input'));
            self.chatInput.focus();
          }
        });
      });

      // Window popstate for browser navigation (Back / Forward)
      window.addEventListener('popstate', function () {
        const match = window.location.pathname.match(/package-workflow\/conversation\/(\d+)/);
        if (match && match[1]) {
          self.selectConversation(parseInt(match[1], 10), false);
        }
      });

      // Handle visibility change to save polling resources
      document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
          self.stopPolling();
        } else if (self.activeHandoff) {
          self.startPolling();
        }
      });

      // New Handoff live customer search
      const customerSearchInput = document.getElementById('handoffCustomerSearch');
      const customerSearchClear = document.getElementById('handoffCustomerSearchClear');
      if (customerSearchInput) {
        let debounceTimer = null;
        customerSearchInput.addEventListener('input', function () {
          clearTimeout(debounceTimer);
          const q = this.value.trim();
          if (customerSearchClear) {
            customerSearchClear.classList.toggle('d-none', !q);
          }
          if (q.length < 2) {
            const resultsWrap = document.getElementById('handoffCustomerResults');
            if (resultsWrap) {
              resultsWrap.innerHTML = '';
              resultsWrap.classList.add('d-none');
            }
            return;
          }
          debounceTimer = setTimeout(function () {
            self.searchCustomers(q);
          }, 300);
        });

        if (customerSearchClear) {
          customerSearchClear.addEventListener('click', function () {
            customerSearchInput.value = '';
            customerSearchClear.classList.add('d-none');
            const resultsWrap = document.getElementById('handoffCustomerResults');
            if (resultsWrap) {
              resultsWrap.innerHTML = '';
              resultsWrap.classList.add('d-none');
            }
            const inputId = document.getElementById('newHandoffRecipientId');
            const inputDisplay = document.getElementById('newHandoffRecipientDisplay');
            if (inputId) inputId.value = '';
            if (inputDisplay) {
              inputDisplay.textContent = '';
              inputDisplay.classList.add('d-none');
            }
            customerSearchInput.focus();
          });
        }
      }

      // Modal focus on first field and Esc close
      const handoffModalEl = document.getElementById('newHandoffModal');
      if (handoffModalEl) {
        handoffModalEl.addEventListener('shown.bs.modal', function () {
          const firstField = handoffModalEl.querySelector('select:not([disabled]), input:not([disabled])')
            || handoffModalEl.querySelector('select, input');
          firstField?.focus();
        });
      }

      // New Handoff creation form AJAX
      const newHandoffForm = document.getElementById('newHandoffForm');
      if (newHandoffForm) {
        newHandoffForm.addEventListener('submit', function (e) {
          e.preventDefault();
          self.submitNewHandoff(this);
        });
      }
    },

    initAvatars: function () {
      document.querySelectorAll('[data-avatar-name]').forEach(function (el) {
        const name = el.getAttribute('data-avatar-name');
        el.style.background = getAvatarGradient(name);
        el.textContent = getInitials(name);
      });
    },

    selectConversation: function (handoffId, updateUrl) {
      const self = this;
      if (!handoffId) return;

      // Update sidebar visual selection
      document.querySelectorAll('.messenger-conv-item').forEach(function (el) {
        if (parseInt(el.getAttribute('data-handoff-id'), 10) === handoffId) {
          el.classList.add('active');
          const badge = el.querySelector('.messenger-unread-badge');
          if (badge) badge.remove();
        } else {
          el.classList.remove('active');
        }
      });

      if (this.shell) {
        this.shell.classList.add('chat-open');
      }

      if (updateUrl) {
        const nextUrl = this.config.baseUrl + 'package-workflow/conversation/' + handoffId;
        window.history.pushState({ handoffId: handoffId }, '', nextUrl);
      }

      this.stopPolling();

      fetch(this.config.baseUrl + 'package-workflow/conversation/' + handoffId, {
        headers: { 'Accept': 'application/json' },
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data || !data.success) {
            alert(data.error || 'Unable to load conversation');
            return;
          }
          self.activeHandoff = data.handoff;
          self.renderChat(data.handoff, data.messages || []);
          self.startPolling();
        })
        .catch(function (err) {
          console.error('Failed to load conversation:', err);
        });
    },

    renderChat: function (handoff, messages) {
      const self = this;
      const myId = parseInt(this.config.customerId, 10);
      const isRecipient = parseInt(handoff.recipient_customer_id, 10) === myId;
      const partnerName = isRecipient ? handoff.sender_name : handoff.recipient_name;
      const partnerRole = isRecipient ? 'Sender' : 'Recipient';
      const isPending = handoff.status === 'pending' && new Date(handoff.expires_at.replace(' ', 'T')).getTime() > Date.now();
      const isConfirmed = handoff.status === 'confirmed';

      // Header partner title & status
      const titleEl = document.getElementById('chatPartnerName');
      if (titleEl) titleEl.textContent = partnerName;

      const subEl = document.getElementById('chatPartnerSub');
      if (subEl) {
        subEl.innerHTML = `
          <span>${escapeHtml(partnerRole)}</span>
          <span>&middot;</span>
          <a href="${self.config.baseUrl}orders/${encodeURIComponent(handoff.order_id)}" target="_blank" class="text-decoration-none">
            <span class="messenger-order-badge">Order ${escapeHtml(handoff.order_no)}</span>
          </a>
        `;
      }

      const headerAvatar = document.getElementById('chatHeaderAvatar');
      if (headerAvatar) {
        headerAvatar.style.background = getAvatarGradient(partnerName);
        headerAvatar.textContent = getInitials(partnerName);
      }

      const statusBadge = document.getElementById('chatStatusBadge');
      if (statusBadge) {
        if (isConfirmed) {
          statusBadge.className = 'messenger-status-pill status-pill-confirmed';
          statusBadge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Receipt Confirmed';
        } else if (isPending) {
          statusBadge.className = 'messenger-status-pill status-pill-pending';
          statusBadge.innerHTML = '<i class="bi bi-clock-fill me-1"></i> Active Handoff';
        } else {
          statusBadge.className = 'messenger-status-pill status-pill-expired';
          statusBadge.innerHTML = '<i class="bi bi-lock-fill me-1"></i> Closed';
        }
      }

      // Header action buttons
      const actionsEl = document.getElementById('chatHeaderActions');
      if (actionsEl) {
        let html = '';
        if (isRecipient && isPending) {
          html += `
            <button class="btn btn-success btn-sm btn-header-action" id="btnConfirmReceipt">
              <i class="bi bi-check2-circle"></i> Confirm Receipt
            </button>
          `;
        }
        html += `
          <a href="${self.config.baseUrl}orders/${encodeURIComponent(handoff.order_id)}" class="btn btn-outline-secondary btn-sm btn-header-action" target="_blank" title="View Order">
            <i class="bi bi-box-arrow-up-right"></i> Order
          </a>
        `;
        actionsEl.innerHTML = html;

        const confirmBtn = document.getElementById('btnConfirmReceipt');
        if (confirmBtn) {
          confirmBtn.addEventListener('click', function () {
            self.confirmHandoff(handoff.id);
          });
        }
      }

      // Feed messages
      this.renderedMessageIds.clear();
      let feedHtml = '';

      // Date divider & system banner
      feedHtml += `
        <div class="chat-date-divider">
          <span>${formatRelativeDate(handoff.created_at)}</span>
        </div>
        <div class="chat-system-banner">
          <i class="bi bi-shield-lock-fill"></i>
          <div>
            <strong>Package handoff created for Order ${escapeHtml(handoff.order_no)}</strong><br>
            <span>Coordinate directly below. ${isConfirmed ? 'Custody has been confirmed.' : 'Custody confirmation expires ' + escapeHtml(handoff.expires_at) + '.'}</span>
          </div>
        </div>
      `;

      if (messages && messages.length > 0) {
        messages.forEach(function (msg) {
          self.renderedMessageIds.add(parseInt(msg.id, 10));
          feedHtml += self.createMessageHtml(msg, myId);
        });
      }

      if (this.chatFeed) {
        this.chatFeed.innerHTML = feedHtml;
        this.scrollToBottom(false);
      }

      // Composer area
      const composerWrap = document.getElementById('chatComposerWrap');
      if (composerWrap) {
        if (isPending) {
          composerWrap.innerHTML = `
            <div class="chat-quick-chips">
              <button type="button" class="quick-chip-btn" data-text="📍 I'm outside the lobby."><i class="bi bi-geo-alt"></i> Outside lobby</button>
              <button type="button" class="quick-chip-btn" data-text="⏳ On my way down now."><i class="bi bi-clock"></i> On my way</button>
              <button type="button" class="quick-chip-btn" data-text="📦 Package received safely, thank you!"><i class="bi bi-box-seam"></i> Received safely</button>
              <button type="button" class="quick-chip-btn" data-text="👋 Ready for handoff?"><i class="bi bi-hand-thumbs-up"></i> Ready now</button>
            </div>
            <form id="chatForm" class="chat-input-form" data-no-disable="true">
              <textarea id="chatInput" class="chat-textarea" placeholder="Type a message... (Press Enter to send)" rows="1" maxlength="4000" required></textarea>
              <button type="submit" id="btnSend" class="btn-send-msg" disabled title="Send message">
                <i class="bi bi-send-fill"></i>
              </button>
            </form>
          `;
          this.chatInput = document.getElementById('chatInput');
          this.chatForm = document.getElementById('chatForm');
          this.btnSend = document.getElementById('btnSend');
          this.bindComposerEvents();
        } else {
          composerWrap.innerHTML = `
            <div class="chat-closed-banner">
              <i class="bi bi-lock-fill"></i>
              <span>This package handoff is ${escapeHtml(handoff.status)}. Message history is preserved for reference.</span>
            </div>
          `;
        }
      }
    },

    bindComposerEvents: function () {
      const self = this;
      if (this.chatForm) {
        this.chatForm.addEventListener('submit', function (e) {
          e.preventDefault();
          self.sendMessage();
        });
      }
      if (this.chatInput) {
        this.chatInput.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            self.sendMessage();
          }
        });
        this.chatInput.addEventListener('input', function () {
          this.style.height = '40px';
          this.style.height = Math.min(this.scrollHeight, 120) + 'px';
          if (self.btnSend) {
            self.btnSend.disabled = this.value.trim().length === 0;
          }
        });
      }
      document.querySelectorAll('.quick-chip-btn').forEach(function (chip) {
        chip.addEventListener('click', function () {
          const text = this.getAttribute('data-text');
          if (text && self.chatInput) {
            self.chatInput.value = text;
            self.chatInput.dispatchEvent(new Event('input'));
            self.chatInput.focus();
          }
        });
      });
    },

    createMessageHtml: function (msg, myCustomerId) {
      const isMe = parseInt(msg.sender_customer_id, 10) === myCustomerId;
      const rowClass = isMe ? 'chat-msg-outgoing' : 'chat-msg-incoming';
      const senderName = msg.sender_name || 'User';
      const avatarStyle = !isMe ? `style="background:${getAvatarGradient(senderName)}"` : '';

      return `
        <div class="chat-msg-row ${rowClass}" data-msg-id="${msg.id}">
          ${!isMe ? `<div class="chat-bubble-avatar" ${avatarStyle}>${getInitials(senderName)}</div>` : ''}
          <div class="chat-msg-content">
            ${!isMe ? `<span class="chat-sender-label">${escapeHtml(senderName)}</span>` : ''}
            <div class="chat-bubble"><div class="chat-bubble-text">${escapeHtml(msg.body ? msg.body.trim() : '')}</div><div class="chat-bubble-footer"><span>${formatTime(msg.created_at)}</span>${isMe ? '<i class="bi bi-check2-all chat-check-icon"></i>' : ''}</div></div>
          </div>
        </div>
      `;
    },

    sendMessage: function () {
      const self = this;
      if (!this.activeHandoff || !this.chatInput || this.isSending) return;
      const text = this.chatInput.value.trim();
      if (!text) return;

      this.isSending = true;
      const handoffId = this.activeHandoff.id;
      const myId = parseInt(this.config.customerId, 10);

      // Optimistic append
      const tempId = 'temp_' + Date.now();
      const optimisticMsg = {
        id: tempId,
        sender_customer_id: myId,
        sender_name: 'Me',
        body: text,
        created_at: new Date().toISOString(),
      };

      if (this.chatFeed) {
        this.chatFeed.insertAdjacentHTML('beforeend', this.createMessageHtml(optimisticMsg, myId));
        this.scrollToBottom(true);
      }

      this.chatInput.value = '';
      this.chatInput.style.height = '40px';
      if (this.btnSend) this.btnSend.disabled = true;

      // Update preview in sidebar immediately
      this.updateSidebarSnippet(handoffId, text);

      fetch(this.config.baseUrl + 'package-workflow/conversation/' + handoffId + '/send', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': this.config.csrfToken,
        },
        body: JSON.stringify({
          body: text,
          csrf_token: this.config.csrfToken,
        }),
      })
        .then(function (res) {
          return res.json().catch(function () {
            throw new Error('Server error (' + res.status + ')');
          });
        })
        .then(function (data) {
          self.isSending = false;
          if (data && data.success && data.message) {
            const actualMsg = data.message;
            self.renderedMessageIds.add(parseInt(actualMsg.id, 10));
            const tempEl = document.querySelector(`[data-msg-id="${tempId}"]`);
            if (tempEl) {
              tempEl.setAttribute('data-msg-id', actualMsg.id);
            }
          } else {
            throw new Error((data && data.error) || 'Failed to send message');
          }
        })
        .catch(function (err) {
          self.isSending = false;
          console.error('Failed to send message:', err);
          const tempEl = document.querySelector(`[data-msg-id="${tempId}"]`);
          if (tempEl) {
            const footer = tempEl.querySelector('.chat-bubble-footer');
            if (footer) {
              footer.innerHTML = `<span class="text-danger" title="${escapeHtml(err.message)}">Failed to send</span>`;
            }
          }
        });
    },

    updateSidebarSnippet: function (handoffId, snippet) {
      const item = document.querySelector(`.messenger-conv-item[data-handoff-id="${handoffId}"]`);
      if (item) {
        const previewEl = item.querySelector('.messenger-conv-preview');
        if (previewEl) previewEl.textContent = snippet;
        const timeEl = item.querySelector('.messenger-conv-time');
        if (timeEl) timeEl.textContent = 'Just now';
      }
    },

    startPolling: function () {
      const self = this;
      this.stopPolling();
      this.pollingTimer = setInterval(function () {
        self.pollMessages();
      }, 3500);
    },

    stopPolling: function () {
      if (this.pollingTimer) {
        clearInterval(this.pollingTimer);
        this.pollingTimer = null;
      }
    },

    pollMessages: function () {
      const self = this;
      if (!this.activeHandoff) return;

      const handoffId = this.activeHandoff.id;
      const myId = parseInt(this.config.customerId, 10);

      fetch(this.config.baseUrl + 'package-workflow/conversation/' + handoffId + '/messages', {
        headers: { 'Accept': 'application/json' },
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data || !data.success) return;

          // Check if handoff status changed
          if (data.handoff && data.handoff.status !== self.activeHandoff.status) {
            self.activeHandoff = data.handoff;
            self.renderChat(data.handoff, data.messages);
            return;
          }

          const msgs = data.messages || [];
          let newMessagesCount = 0;

          msgs.forEach(function (msg) {
            const msgId = parseInt(msg.id, 10);
            if (!self.renderedMessageIds.has(msgId)) {
              self.renderedMessageIds.add(msgId);
              newMessagesCount++;
              if (self.chatFeed) {
                self.chatFeed.insertAdjacentHTML('beforeend', self.createMessageHtml(msg, myId));
              }
              self.updateSidebarSnippet(handoffId, msg.body);
            }
          });

          if (newMessagesCount > 0) {
            self.scrollToBottom(true);
          }
        })
        .catch(function (err) {
          console.debug('Polling error:', err);
        });
    },

    scrollToBottom: function (smooth) {
      if (!this.chatFeed) return;
      this.chatFeed.scrollTo({
        top: this.chatFeed.scrollHeight,
        behavior: smooth ? 'smooth' : 'auto',
      });
    },

    confirmHandoff: function (handoffId) {
      const self = this;
      if (!confirm('Confirm that you have safely received this package?')) return;

      fetch(this.config.baseUrl + 'package-workflow/conversation/' + handoffId + '/confirm', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': this.config.csrfToken,
        },
        body: JSON.stringify({ csrf_token: this.config.csrfToken }),
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.success) {
            alert('Package receipt confirmed successfully!');
            self.selectConversation(handoffId, false);
          } else {
            alert((data && data.error) || 'Unable to confirm receipt.');
          }
        })
        .catch(function (err) {
          console.error('Confirmation error:', err);
        });
    },

    filterSidebar: function (term) {
      const q = term.toLowerCase();
      document.querySelectorAll('.messenger-conv-item').forEach(function (item) {
        const name = (item.getAttribute('data-name') || '').toLowerCase();
        const order = (item.getAttribute('data-order') || '').toLowerCase();
        const snippet = (item.getAttribute('data-snippet') || '').toLowerCase();
        const matches = !q || name.includes(q) || order.includes(q) || snippet.includes(q);
        item.style.display = matches ? 'flex' : 'none';
      });
    },

    applyStatusFilter: function (status) {
      document.querySelectorAll('.messenger-conv-item').forEach(function (item) {
        const itemStatus = (item.getAttribute('data-status') || '').toLowerCase();
        if (status === 'all') {
          item.style.display = 'flex';
        } else if (status === 'pending') {
          item.style.display = itemStatus === 'pending' ? 'flex' : 'none';
        } else if (status === 'confirmed') {
          item.style.display = itemStatus === 'confirmed' ? 'flex' : 'none';
        }
      });
    },

    searchCustomers: function (query) {
      const self = this;
      const resultsWrap = document.getElementById('handoffCustomerResults');
      if (!resultsWrap) return;

      if (!query || query.length < 2) {
        resultsWrap.innerHTML = '';
        resultsWrap.classList.add('d-none');
        return;
      }

      resultsWrap.classList.remove('d-none');
      resultsWrap.innerHTML = '<p class="text-muted small p-2 m-0"><span class="spinner-border spinner-border-sm me-1"></span> Searching...</p>';

      fetch(this.config.baseUrl + 'package-workflow/api/customers?q=' + encodeURIComponent(query))
        .then(function (res) { return res.json(); })
        .then(function (data) {
          const list = (data && data.customers) || [];
          if (list.length === 0) {
            resultsWrap.innerHTML = '<p class="text-muted small p-2 m-0 text-center">No customers found</p>';
            return;
          }

          let html = '<div class="list-group list-group-flush">';
          list.forEach(function (c) {
            const company = c.company_name ? ` &middot; <span class="text-muted">${escapeHtml(c.company_name)}</span>` : '';
            html += `
              <button type="button" class="list-group-item list-group-item-action py-2 px-3 customer-select-btn" data-customer-id="${c.id}" data-customer-name="${escapeHtml(c.name)}">
                <div class="d-flex align-items-center gap-2">
                  <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width:28px; height:28px; font-size:0.75rem; background:${getAvatarGradient(c.name)}">
                    ${getInitials(c.name)}
                  </div>
                  <div>
                    <strong class="d-block text-dark">${escapeHtml(c.name)}</strong>
                    <small class="text-muted">${escapeHtml(c.email || '')}${company}</small>
                  </div>
                </div>
              </button>
            `;
          });
          html += '</div>';
          resultsWrap.innerHTML = html;

          resultsWrap.querySelectorAll('.customer-select-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
              const cid = this.getAttribute('data-customer-id');
              const cname = this.getAttribute('data-customer-name');
              const inputId = document.getElementById('newHandoffRecipientId');
              const inputDisplay = document.getElementById('newHandoffRecipientDisplay');
              if (inputId) inputId.value = cid;
              if (inputDisplay) {
                inputDisplay.textContent = 'Selected: ' + cname;
                inputDisplay.classList.remove('d-none');
              }
              resultsWrap.innerHTML = '';
              resultsWrap.classList.add('d-none');
              const searchInput = document.getElementById('handoffCustomerSearch');
              if (searchInput) searchInput.value = cname;
            });
          });
        })
        .catch(function (err) {
          resultsWrap.innerHTML = '<p class="text-danger small p-2 m-0">Error searching customers.</p>';
        });
    },

    submitNewHandoff: function (form) {
      const self = this;
      const orderId = document.getElementById('newHandoffOrderSelect').value;
      const recipientId = document.getElementById('newHandoffRecipientId').value;
      const alertBox = document.getElementById('newHandoffAlert');

      if (!orderId || !recipientId) {
        if (alertBox) {
          alertBox.className = 'alert alert-danger';
          alertBox.textContent = 'Please choose an eligible order and select a recipient.';
          alertBox.classList.remove('d-none');
        }
        return;
      }

      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      fetch(this.config.baseUrl + 'package-workflow/handoff', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': this.config.csrfToken,
        },
        body: JSON.stringify({
          order_id: orderId,
          recipient_customer_id: recipientId,
          csrf_token: this.config.csrfToken,
        }),
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (submitBtn) submitBtn.disabled = false;
          if (data && data.success) {
            // Close modal
            const modalEl = document.getElementById('newHandoffModal');
            if (modalEl && window.bootstrap) {
              const modal = bootstrap.Modal.getInstance(modalEl);
              if (modal) modal.hide();
            }

            // Show link alert
            if (data.link) {
              prompt('Package handoff created! Here is the private 48-hour confirmation link to send to the recipient:', data.link);
            }

            // Open the new conversation
            if (data.handoff_id) {
              window.location.href = self.config.baseUrl + 'package-workflow/conversation/' + data.handoff_id;
            } else {
              window.location.reload();
            }
          } else {
            if (alertBox) {
              alertBox.className = 'alert alert-danger';
              alertBox.textContent = (data && data.error) || 'Failed to create handoff.';
              alertBox.classList.remove('d-none');
            }
          }
        })
        .catch(function (err) {
          if (submitBtn) submitBtn.disabled = false;
          if (alertBox) {
            alertBox.className = 'alert alert-danger';
            alertBox.textContent = 'Network error while creating handoff.';
            alertBox.classList.remove('d-none');
          }
        });
    },
  };
})();

