<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$messengerCssPath = dirname(__DIR__, 3) . '/public/css/messenger.css';
$messengerCssVersion = is_file($messengerCssPath) ? '?v=' . (string)filemtime($messengerCssPath) : '';
$extraCss = '<link rel="stylesheet" href="' . e(base_url('css/messenger.css')) . e($messengerCssVersion) . '">';
$this->call->view('templates/header', [
    'page_title' => $page_title ?? 'Package Messenger',
    'extra_css' => $extraCss
]);

$myId = (int)($customer_id ?? 0);
$active = $active_handoff ?? null;
$messages = $active_messages ?? [];

$partnerName = '';
$isRecipient = false;
$canMessage = false;
$isConfirmed = false;

if ($active) {
    $isRecipient = (int)$active['recipient_customer_id'] === $myId;
    $partnerName = $isRecipient ? $active['sender_name'] : $active['recipient_name'];
    $isConfirmed = $active['status'] === 'confirmed';
    $canMessage = $active['status'] === 'pending' && strtotime($active['expires_at']) > time();
}

$messengerProps = [
    'customerId'       => $myId,
    'csrfToken'        => csrf_token(),
    'baseUrl'          => base_url(),
    'conversations'    => $conversations ?? [],
    'activeHandoff'    => $active,
    'messages'         => $messages,
    'eligibleOrders'   => $eligible_orders ?? [],
    'presetOrderId'    => (int)($preset_order_id ?? 0),
];
$messengerPropsJson = json_encode($messengerProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
?>

<div id="messenger-vue-root" data-props="<?= e($messengerPropsJson) ?>">
<div class="container-fluid page-shell messenger-wrapper">
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= e($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($token) && !empty($token_handoff)): ?>
        <div class="messenger-token-alert">
            <div class="token-alert-content">
                <i class="bi bi-box2-heart-fill"></i>
                <div>
                    <strong>Incoming Package Transfer Request</strong>
                    <div class="small text-dark">
                        <strong><?= e($token_handoff['sender_name']) ?></strong> sent you custody of Order <strong>#<?= e($token_handoff['order_no']) ?></strong>.
                    </div>
                </div>
            </div>
            <form method="post" action="<?= e(base_url('package-workflow/confirm')) ?>" class="d-inline m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <button type="submit" class="btn btn-success fw-bold">
                    <i class="bi bi-check-lg me-1"></i> Confirm Package Receipt
                </button>
            </form>
        </div>
    <?php elseif (!empty($token)): ?>
        <div class="messenger-token-alert">
            <div class="token-alert-content">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div>
                    <strong>Handoff Confirmation Link</strong>
                    <div class="small text-dark">Click below to verify and accept custody for this package.</div>
                </div>
            </div>
            <form method="post" action="<?= e(base_url('package-workflow/confirm')) ?>" class="d-inline m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <button type="submit" class="btn btn-primary fw-bold">
                    <i class="bi bi-check-lg me-1"></i> Accept Receipt
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Main Messenger Shell -->
    <div class="messenger-shell<?= $active ? ' chat-open' : '' ?>" id="messengerShell">
        <!-- Sidebar -->
        <aside class="messenger-sidebar" aria-label="Conversations">
            <div class="messenger-sidebar-header">
                <div class="messenger-title-row">
                    <h2 class="messenger-title">
                        <i class="bi bi-chat-dots-fill text-primary"></i>
                        <span>Messages</span>
                        <span class="messenger-status-dot" title="Messenger online"></span>
                    </h2>
                    <button type="button" class="btn btn-primary btn-new-handoff" data-bs-toggle="modal" data-bs-target="#newHandoffModal">
                        <i class="bi bi-plus-lg"></i>
                        <span>New Handoff</span>
                    </button>
                </div>
                <div class="messenger-search-wrap">
                    <i class="bi bi-search messenger-search-icon"></i>
                    <input type="text" class="messenger-search-input" id="sidebarSearch" placeholder="Search chats or orders..." autocomplete="off">
                    <button type="button" class="messenger-search-clear" id="sidebarSearchClear" aria-label="Clear search">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
                <div class="messenger-filter-tabs" role="tablist">
                    <button type="button" class="messenger-tab-btn active" data-filter="all">All</button>
                    <button type="button" class="messenger-tab-btn" data-filter="pending">Active</button>
                    <button type="button" class="messenger-tab-btn" data-filter="confirmed">Confirmed</button>
                </div>
            </div>

            <div class="messenger-conv-list" id="messengerConvList" role="navigation" aria-label="Conversations list">
                <?php if (empty($conversations)): ?>
                    <div class="messenger-empty-sidebar">
                        <i class="bi bi-chat-square-text text-muted"></i>
                        <p class="fw-semibold mb-1">No handoff chats yet</p>
                        <small class="text-muted">Start a handoff above to coordinate with recipients.</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <?php
                            $cIsRecipient = (int)$conv['recipient_customer_id'] === $myId;
                            $cPartner = $cIsRecipient ? $conv['sender_name'] : $conv['recipient_name'];
                            $isActiveItem = $active && (int)$conv['id'] === (int)$active['id'];
                            $cStatus = $conv['status'];
                            $cUnread = (int)($conv['unread_count'] ?? 0);
                            $latestTime = !empty($conv['latest_message_at']) ? $conv['latest_message_at'] : $conv['created_at'];
                            $timeFormatted = date('M j', strtotime($latestTime));
                            if (date('Y-m-d', strtotime($latestTime)) === date('Y-m-d')) {
                                $timeFormatted = date('g:i a', strtotime($latestTime));
                            }
                        ?>
                        <a href="<?= e(base_url('package-workflow/conversation/' . $conv['id'])) ?>"
                           class="messenger-conv-item<?= $isActiveItem ? ' active' : '' ?>"
                           data-handoff-id="<?= (int)$conv['id'] ?>"
                           data-name="<?= e($cPartner) ?>"
                           data-order="<?= e($conv['order_no']) ?>"
                           data-snippet="<?= e($conv['latest_message'] ?? '') ?>"
                           data-status="<?= e($cStatus) ?>">
                            <div class="messenger-avatar" data-avatar-name="<?= e($cPartner) ?>"></div>
                            <div class="messenger-conv-details">
                                <div class="messenger-conv-top">
                                    <span class="messenger-conv-name"><?= e($cPartner) ?></span>
                                    <span class="messenger-conv-time"><?= e($timeFormatted) ?></span>
                                </div>
                                <div class="messenger-conv-badges">
                                    <span class="messenger-order-badge">ORD <?= e($conv['order_no']) ?></span>
                                    <?php if ($cStatus === 'confirmed'): ?>
                                        <span class="messenger-status-pill status-pill-confirmed">Confirmed</span>
                                    <?php elseif ($cStatus === 'pending'): ?>
                                        <span class="messenger-status-pill status-pill-pending">Active</span>
                                    <?php else: ?>
                                        <span class="messenger-status-pill status-pill-expired"><?= e(ucfirst($cStatus)) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="messenger-conv-bottom">
                                    <p class="messenger-conv-preview">
                                        <?= e($conv['latest_message'] ?? 'No messages yet') ?>
                                    </p>
                                    <?php if ($cUnread > 0): ?>
                                        <span class="messenger-unread-badge"><?= $cUnread ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Main Chat Area -->
        <main class="messenger-main" id="messengerMainArea" aria-label="Current conversation">
            <?php if ($active): ?>
                <!-- Active Chat Header -->
                <div class="messenger-chat-header">
                    <div class="messenger-header-left">
                        <button type="button" class="btn-chat-back" id="btnChatBack" aria-label="Back to conversations">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <div class="messenger-avatar" id="chatHeaderAvatar" data-avatar-name="<?= e($partnerName) ?>"></div>
                        <div class="messenger-header-info">
                            <h3 class="messenger-header-title">
                                <span id="chatPartnerName"><?= e($partnerName) ?></span>
                                <?php if ($isConfirmed): ?>
                                    <span class="messenger-status-pill status-pill-confirmed" id="chatStatusBadge">
                                        <i class="bi bi-check-circle-fill me-1"></i> Receipt Confirmed
                                    </span>
                                <?php elseif ($canMessage): ?>
                                    <span class="messenger-status-pill status-pill-pending" id="chatStatusBadge">
                                        <i class="bi bi-clock-fill me-1"></i> Active Handoff
                                    </span>
                                <?php else: ?>
                                    <span class="messenger-status-pill status-pill-expired" id="chatStatusBadge">
                                        <i class="bi bi-lock-fill me-1"></i> Closed
                                    </span>
                                <?php endif; ?>
                            </h3>
                            <div class="messenger-header-sub" id="chatPartnerSub">
                                <span><?= $isRecipient ? 'Sender' : 'Recipient' ?></span>
                                <span>&middot;</span>
                                <a href="<?= e(base_url('orders/' . $active['order_id'])) ?>" target="_blank" class="text-decoration-none">
                                    <span class="messenger-order-badge">Order <?= e($active['order_no']) ?></span>
                                </a>
                                <?php if (!empty($active['destination_address'])): ?>
                                    <span>&middot;</span>
                                    <span class="text-truncate" style="max-width: 200px;" title="<?= e($active['destination_address']) ?>">
                                        <i class="bi bi-geo-alt me-1"></i><?= e($active['destination_address']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="messenger-header-actions" id="chatHeaderActions">
                        <?php if ($isRecipient && $canMessage): ?>
                            <button type="button" class="btn btn-success btn-sm btn-header-action" id="btnConfirmReceipt">
                                <i class="bi bi-check2-circle"></i> Confirm Receipt
                            </button>
                        <?php endif; ?>
                        <a href="<?= e(base_url('orders/' . $active['order_id'])) ?>" class="btn btn-outline-secondary btn-sm btn-header-action" target="_blank" title="View full order details">
                            <i class="bi bi-box-arrow-up-right"></i> Order
                        </a>
                    </div>
                </div>

                <!-- Chat Messages Feed -->
                <div class="messenger-chat-feed" id="chatFeed" role="log" aria-live="polite">
                    <div class="chat-date-divider">
                        <span><?= e(date('M j, Y', strtotime($active['created_at']))) ?></span>
                    </div>

                    <div class="chat-system-banner">
                        <i class="bi bi-shield-lock-fill"></i>
                        <div>
                            <strong>Package handoff created for Order <?= e($active['order_no']) ?></strong><br>
                            <span>Coordinate directly below. <?= $isConfirmed ? 'Custody has been confirmed.' : 'Custody confirmation expires ' . e(date('M j, g:i a', strtotime($active['expires_at']))) . '.' ?></span>
                        </div>
                    </div>

                    <?php foreach ($messages as $msg): ?>
                        <?php
                            $isMe = (int)$msg['sender_customer_id'] === $myId;
                            $msgTime = date('g:i a', strtotime($msg['created_at']));
                        ?>
                        <div class="chat-msg-row <?= $isMe ? 'chat-msg-outgoing' : 'chat-msg-incoming' ?>" data-msg-id="<?= (int)$msg['id'] ?>">
                            <?php if (!$isMe): ?>
                                <div class="chat-bubble-avatar" data-avatar-name="<?= e($msg['sender_name']) ?>"></div>
                            <?php endif; ?>
                            <div class="chat-msg-content">
                                <?php if (!$isMe): ?>
                                    <span class="chat-sender-label"><?= e($msg['sender_name']) ?></span>
                                <?php endif; ?>
                                <div class="chat-bubble"><div class="chat-bubble-text"><?= e(trim($msg['body'])) ?></div><div class="chat-bubble-footer"><span><?= e($msgTime) ?></span><?php if ($isMe): ?><i class="bi bi-check2-all chat-check-icon" title="Delivered"></i><?php endif; ?></div></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Chat Composer Footer -->
                <div class="messenger-chat-footer" id="chatComposerWrap">
                    <?php if ($canMessage): ?>
                        <div class="chat-quick-chips">
                            <button type="button" class="quick-chip-btn" data-text="📍 I'm outside the lobby."><i class="bi bi-geo-alt"></i> Outside lobby</button>
                            <button type="button" class="quick-chip-btn" data-text="⏳ On my way down now."><i class="bi bi-clock"></i> On my way</button>
                            <button type="button" class="quick-chip-btn" data-text="📦 Package received safely, thank you!"><i class="bi bi-box-seam"></i> Received safely</button>
                            <button type="button" class="quick-chip-btn" data-text="👋 Ready for handoff?"><i class="bi bi-hand-thumbs-up"></i> Ready now</button>
                        </div>
                        <form id="chatForm" class="chat-input-form" data-no-disable="true" data-no-validate="true">
                            <textarea id="chatInput" class="chat-textarea" placeholder="Type a message... (Press Enter to send, Shift+Enter for new line)" rows="1" maxlength="4000" data-no-validate="true"></textarea>
                            <button type="submit" id="btnSend" class="btn-send-msg" disabled title="Send message" aria-label="Send message">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="chat-closed-banner">
                            <i class="bi bi-lock-fill"></i>
                            <span>This package handoff is <?= e($active['status']) ?>. Message history is preserved for reference.</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="messenger-empty-state">
                    <div class="empty-state-icon-wrap">
                        <i class="bi bi-chat-quote-fill"></i>
                    </div>
                    <h3 class="empty-state-title">Your Package Messenger</h3>
                    <p class="empty-state-desc">
                        Coordinate package handoffs with senders and recipients, confirm package transfers securely, and exchange real-time updates.
                    </p>
                    <button type="button" class="btn btn-primary px-4 py-2 fw-semibold rounded-pill" data-bs-toggle="modal" data-bs-target="#newHandoffModal">
                        <i class="bi bi-plus-lg me-1"></i> Start New Handoff
                    </button>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Modal: Start New Handoff -->
<div class="modal fade" id="newHandoffModal" tabindex="-1" aria-labelledby="newHandoffModalLabel" aria-hidden="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="newHandoffModalLabel">
                    <i class="bi bi-box-arrow-right text-primary me-2"></i>Start Package Handoff
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="newHandoffForm" data-no-disable="true">
                <div class="modal-body p-4">
                    <div id="newHandoffAlert" class="alert d-none"></div>

                    <div class="mb-3">
                        <label for="newHandoffOrderSelect" class="form-label fw-semibold">Select Eligible Order</label>
                        <select id="newHandoffOrderSelect" class="form-select" required <?= empty($eligible_orders) ? 'disabled' : '' ?>>
                            <?php if (empty($eligible_orders)): ?>
                                <option value="" disabled selected>No eligible orders</option>
                            <?php else: ?>
                                <option value="" disabled selected>-- Choose an active order --</option>
                                <?php foreach (($eligible_orders ?? []) as $ord): ?>
                                    <option value="<?= (int)$ord['id'] ?>" <?= ($preset_order_id ?? 0) === (int)$ord['id'] ? 'selected' : '' ?>>
                                        Order <?= e($ord['order_no'] ?: '#' . $ord['id']) ?> (<?= e(ucfirst(str_replace('_', ' ', $ord['status']))) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($eligible_orders)): ?>
                            <div class="form-text text-warning">
                                <i class="bi bi-info-circle me-1"></i>You currently have no active orders eligible for custody handoff.
                            </div>
                        <?php else: ?>
                            <div class="form-text">Only active orders awaiting delivery or pickup can be handed off.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="handoffCustomerSearch" class="form-label fw-semibold">Search Recipient Customer</label>
                        <div class="input-group position-relative">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="handoffCustomerSearch" class="form-control" placeholder="Search recipient by name, company, or email..." autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary d-none position-absolute end-0 top-50 translate-middle-y me-2 border-0 bg-transparent text-muted p-0 d-flex align-items-center justify-content-center" id="handoffCustomerSearchClear" aria-label="Clear search" style="width: 24px; height: 24px; z-index: 5;">
                                <i class="bi bi-x-circle-fill"></i>
                            </button>
                        </div>
                        <input type="hidden" id="newHandoffRecipientId" value="">
                        <div id="newHandoffRecipientDisplay" class="badge bg-primary-subtle text-primary border border-primary-subtle mt-2 p-2 d-none"></div>
                        <div id="handoffCustomerResults" class="border rounded mt-2 bg-white d-none shadow-sm" style="max-height: 180px; overflow-y: auto;"></div>
                    </div>

                    <div class="p-3 bg-light rounded small text-muted">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        A secure 48-hour recipient confirmation token will be created and an instant messenger conversation will open for coordination.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" <?= empty($eligible_orders) ? 'disabled' : '' ?>>
                        <i class="bi bi-arrow-right-circle me-1"></i> Initiate Handoff &amp; Chat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<?php
$messageIds = array_map(static fn($m) => (int)$m['id'], $messages);
$initialHandoffJson = $active ? json_encode($active, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null';
$messengerBundlePath = dirname(__DIR__, 3) . '/public/build/messenger.js';
$messengerVersion = is_file($messengerBundlePath) ? '?v=' . (string)filemtime($messengerBundlePath) : '';

$extraJs = '
<script src="' . e(base_url('js/messenger.js')) . '"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (!window.__VUE_MESSENGER_MOUNTED__ && window.PackageMessenger && window.PackageMessenger.init) {
        PackageMessenger.init({
            baseUrl: ' . json_encode(base_url()) . ',
            customerId: ' . (int)$myId . ',
            csrfToken: ' . json_encode(csrf_token()) . ',
            hasInitialHandoff: ' . ($active ? 'true' : 'false') . ',
            activeHandoff: ' . $initialHandoffJson . ',
            initialMessageIds: ' . json_encode($messageIds) . ',
            presetOrderId: ' . (int)($preset_order_id ?? 0) . '
        });
    }
});
</script>
<script type="module" src="' . e(base_url('build/messenger.js')) . e($messengerVersion) . '"></script>
';
$this->call->view('templates/footer', ['extra_js' => $extraJs]);
?>
