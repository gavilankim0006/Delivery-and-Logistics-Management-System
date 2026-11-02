<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Fee Rates and Configuration']);
?>

<div class="container page-shell py-4">
    <header class="page-header mb-4">
        <div>
            <h1 class="page-title">Fee rates and settings</h1>
            <p class="page-description">Configure delivery pricing, support contact channels, and system notification gateways.</p>
        </div>
    </header>

    <?php foreach (['success', 'error', 'warning', 'info'] as $type): ?>
        <?php if ($message = flash($type)): ?>
            <div class="alert alert-<?= $type === 'error' ? 'danger' : e($type) ?> alert-dismissible fade show" role="status">
                <i class="bi bi-<?= $type === 'error' ? 'exclamation-circle-fill' : ($type === 'success' ? 'check-circle-fill' : 'info-circle-fill') ?> me-2" aria-hidden="true"></i>
                <?= e($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- Main Pricing and Contact Settings Form -->
    <section class="surface surface-section mb-4" aria-label="Delivery pricing and support settings">
        <form action="<?= base_url('admin/settings') ?>" method="POST" id="fee-settings-form" novalidate>
            <?= csrf_field() ?>

            <section class="form-section mb-4" aria-labelledby="pricing-heading">
                <h2 class="h5 mb-3" id="pricing-heading">
                    <i class="bi bi-calculator text-primary me-2" aria-hidden="true"></i>Delivery fee calculation
                </h2>
                <div class="row g-3">
                    <div class="col-12 col-md-6 shared-field-wrap">
                        <label for="base-delivery-fee" class="form-label shared-label">
                            <span>Base delivery fee</span>
                            <span class="text-danger ms-1" aria-hidden="true">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">₱</span>
                            <input
                                id="base-delivery-fee"
                                type="number"
                                min="0"
                                max="100000"
                                step="0.01"
                                class="form-control"
                                name="base_delivery_fee"
                                value="<?= e($settings['base_delivery_fee'] ?? '60.00') ?>"
                                required
                                aria-describedby="base-delivery-fee-help"
                            >
                            <span class="input-group-text">PHP</span>
                        </div>
                        <div class="form-text shared-helper-text" id="base-delivery-fee-help">Standard base charge that covers the initial distance limit.</div>
                    </div>

                    <div class="col-12 col-md-6 shared-field-wrap">
                        <label for="free-distance" class="form-label shared-label">
                            <span>Free distance limit</span>
                            <span class="text-danger ms-1" aria-hidden="true">*</span>
                        </label>
                        <div class="input-group">
                            <input
                                id="free-distance"
                                type="number"
                                min="0"
                                max="10000"
                                step="0.1"
                                class="form-control"
                                name="free_distance_km"
                                value="<?= e($settings['free_distance_km'] ?? '2.0') ?>"
                                required
                                aria-describedby="free-distance-help"
                            >
                            <span class="input-group-text">km</span>
                        </div>
                        <div class="form-text shared-helper-text" id="free-distance-help">Transit distance included in the base fee before per-km charges apply.</div>
                    </div>

                    <div class="col-12 col-md-6 shared-field-wrap">
                        <label for="per-km-rate" class="form-label shared-label">
                            <span>Distance rate per km</span>
                            <span class="text-danger ms-1" aria-hidden="true">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">₱</span>
                            <input
                                id="per-km-rate"
                                type="number"
                                min="0"
                                max="10000"
                                step="0.01"
                                class="form-control"
                                name="per_km_rate"
                                value="<?= e($settings['per_km_rate'] ?? '10.00') ?>"
                                required
                                aria-describedby="per-km-rate-help"
                            >
                            <span class="input-group-text">PHP</span>
                        </div>
                        <div class="form-text shared-helper-text" id="per-km-rate-help">Additional charge applied for each kilometer beyond the free distance limit.</div>
                    </div>

                    <div class="col-12 col-md-6 shared-field-wrap">
                        <label for="free-weight" class="form-label shared-label">
                            <span>Free weight limit</span>
                            <span class="text-danger ms-1" aria-hidden="true">*</span>
                        </label>
                        <div class="input-group">
                            <input
                                id="free-weight"
                                type="number"
                                min="0"
                                max="1000"
                                step="0.1"
                                class="form-control"
                                name="free_weight_kg"
                                value="<?= e($settings['free_weight_kg'] ?? '5.0') ?>"
                                required
                                aria-describedby="free-weight-help"
                            >
                            <span class="input-group-text">kg</span>
                        </div>
                        <div class="form-text shared-helper-text" id="free-weight-help">Package weight covered under the base fee before per-kg charges apply.</div>
                    </div>

                    <div class="col-12 col-md-6 shared-field-wrap">
                        <label for="per-kg-rate" class="form-label shared-label">
                            <span>Weight rate per kg</span>
                            <span class="text-danger ms-1" aria-hidden="true">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">₱</span>
                            <input
                                id="per-kg-rate"
                                type="number"
                                min="0"
                                max="10000"
                                step="0.01"
                                class="form-control"
                                name="per_kg_rate"
                                value="<?= e($settings['per_kg_rate'] ?? '5.00') ?>"
                                required
                                aria-describedby="per-kg-rate-help"
                            >
                            <span class="input-group-text">PHP</span>
                        </div>
                        <div class="form-text shared-helper-text" id="per-kg-rate-help">Additional charge applied for each kilogram beyond the free weight limit.</div>
                    </div>
                </div>
            </section>

            <section class="form-section pt-3 border-top mb-4" aria-labelledby="support-heading">
                <h2 class="h5 mb-3" id="support-heading">
                    <i class="bi bi-headset text-primary me-2" aria-hidden="true"></i>Customer support contact channels
                </h2>
                <div class="row g-3">
                    <div class="col-12 col-md-6 shared-field-wrap">
                        <label for="support-email" class="form-label shared-label">
                            <span>Customer support email</span>
                            <span class="text-danger ms-1" aria-hidden="true">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true"><i class="bi bi-envelope"></i></span>
                            <input
                                id="support-email"
                                type="email"
                                class="form-control"
                                name="support_email"
                                value="<?= e($settings['support_email'] ?? 'support@omniroute.local') ?>"
                                placeholder="e.g. support@omniroute.local"
                                required
                                aria-describedby="support-email-help"
                            >
                        </div>
                        <div class="form-text shared-helper-text" id="support-email-help">Official email displayed to customers on tracking pages and notifications.</div>
                    </div>

                    <div class="col-12 col-md-6 shared-field-wrap">
                        <label for="support-phone" class="form-label shared-label">
                            <span>Customer support phone</span>
                            <span class="text-danger ms-1" aria-hidden="true">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true"><i class="bi bi-telephone"></i></span>
                            <input
                                id="support-phone"
                                type="tel"
                                class="form-control"
                                name="support_phone"
                                data-allow-landline="true"
                                value="<?= e($settings['support_phone'] ?? '09171234567') ?>"
                                placeholder="e.g. +63 2 8123 4567 or 09171234567"
                                required
                                aria-describedby="support-phone-help"
                            >
                        </div>
                        <div class="form-text shared-helper-text" id="support-phone-help">Philippine mobile, landline, or customer hotline (e.g., +63 2 8123 4567 or 09171234567).</div>
                    </div>
                </div>
            </section>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary fw-semibold" id="btn-save-settings">
                    <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Save fee rates and support settings
                </button>
            </div>
        </form>
    </section>

    <!-- Notification Gateway Test Consoles -->
    <section class="surface surface-section mb-4" aria-label="Notification gateways configuration and test consoles">
        <header class="mb-3">
            <h2 class="h5 mb-1"><i class="bi bi-send-check text-primary me-2" aria-hidden="true"></i>Notification gateways & test consoles</h2>
            <p class="text-muted small mb-0">Verify external delivery notification channels before dispatching live orders.</p>
        </header>

        <div class="row g-4">
            <!-- Email (SMTP / Mailer) Card -->
            <div class="col-12 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="bi bi-envelope me-1 text-primary"></i> Email (SMTP)</span>
                        <span class="badge <?= !empty($mail_enabled) ? 'bg-success' : 'bg-secondary' ?>">
                            <?= !empty($mail_enabled) ? 'Active' : 'Disabled' ?>
                        </span>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <p class="small text-muted mb-2">
                                <strong>Host:</strong> <?= e($mail_host ?: 'Local PHP mail()') ?><br>
                                <strong>Port:</strong> <?= (int)$mail_port ?><br>
                                <strong>From:</strong> <?= e($mail_from_address) ?>
                            </p>
                            <p class="small text-muted">Sends templated customer order confirmations, status transitions, and delivery notifications.</p>
                        </div>
                        <form action="<?= base_url('admin/test-email') ?>" method="POST" id="test-email-form" class="mt-3 pt-3 border-top" novalidate>
                            <?= csrf_field() ?>
                            <div class="shared-field-wrap mb-2">
                                <label for="test-email-address" class="form-label shared-label small">
                                    <span>Recipient email address</span>
                                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input
                                        type="email"
                                        class="form-control"
                                        id="test-email-address"
                                        name="email"
                                        placeholder="e.g. admin@omniroute.local"
                                        required
                                        aria-describedby="test-email-help"
                                    >
                                </div>
                                <div class="form-text small" id="test-email-help">Valid recipient email address for SMTP check.</div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-send me-1"></i> Send test email
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- SMS (Semaphore) Card -->
            <div class="col-12 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="bi bi-chat-text me-1 text-success"></i> SMS (Semaphore)</span>
                        <span class="badge <?= !empty($semaphore_enabled) ? 'bg-success' : 'bg-warning text-dark' ?>">
                            <?= !empty($semaphore_enabled) ? 'Live API' : 'Simulation mode' ?>
                        </span>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <p class="small text-muted mb-2">
                                <strong>Sender:</strong> <?= e($semaphore_sender_name) ?><br>
                                <strong>API key:</strong> <?= !empty($semaphore_api_key) ? 'Configured (' . substr($semaphore_api_key, 0, 4) . '***)' : 'Not set' ?>
                            </p>
                            <p class="small text-muted">Delivers order update SMS messages to Philippine mobile recipients via Semaphore API.</p>
                        </div>
                        <form action="<?= base_url('admin/test-sms') ?>" method="POST" id="test-sms-form" class="mt-3 pt-3 border-top" novalidate>
                            <?= csrf_field() ?>
                            <div class="shared-field-wrap mb-2">
                                <label for="test-sms-phone" class="form-label shared-label small">
                                    <span>Recipient mobile phone</span>
                                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                    <input
                                        type="tel"
                                        class="form-control"
                                        id="test-sms-phone"
                                        name="phone"
                                        placeholder="09171234567"
                                        required
                                        aria-describedby="test-sms-phone-help"
                                    >
                                </div>
                                <div class="form-text small" id="test-sms-phone-help">Philippine mobile (09XXXXXXXXX or +639XXXXXXXXX).</div>
                            </div>
                            <div class="shared-field-wrap mb-2">
                                <label for="test-sms-message" class="form-label shared-label small">
                                    <span>Message text</span>
                                </label>
                                <textarea
                                    class="form-control form-control-sm"
                                    id="test-sms-message"
                                    name="message"
                                    rows="2"
                                    maxlength="480"
                                    placeholder="Dispatch test message from Admin..."
                                    aria-describedby="test-sms-msg-help"
                                >Dispatch test message from Admin.</textarea>
                                <div class="form-text small" id="test-sms-msg-help">160 characters per SMS chunk (up to 480 total).</div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-chat-dots me-1"></i> Send test SMS
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Push (Firebase FCM) Card -->
            <div class="col-12 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="bi bi-broadcast me-1 text-danger"></i> Push (Firebase FCM)</span>
                        <span class="badge <?= !empty($firebase_enabled) ? 'bg-success' : 'bg-warning text-dark' ?>">
                            <?= !empty($firebase_enabled) ? 'Enabled' : 'Simulation mode' ?>
                        </span>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <p class="small text-muted mb-2">
                                <strong>Project ID:</strong> <?= e($firebase_project_id ?: 'Not set') ?><br>
                                <strong>Admin key:</strong> <?= !empty($firebase_has_key) ? 'Found' : 'Not configured' ?>
                            </p>
                            <p class="small text-muted">Sends high-priority web/mobile push notifications to active drivers and customer devices.</p>
                        </div>
                        <form action="<?= base_url('admin/test-push') ?>" method="POST" id="test-push-form" class="mt-3 pt-3 border-top" novalidate>
                            <?= csrf_field() ?>
                            <div class="shared-field-wrap mb-2">
                                <label for="test-push-user" class="form-label shared-label small">
                                    <span>Target registered user</span>
                                    <span class="text-danger ms-1" aria-hidden="true">*</span>
                                </label>
                                <select
                                    class="form-select form-select-sm"
                                    id="test-push-user"
                                    name="user_id"
                                    required
                                    <?= empty($users_with_tokens) ? 'disabled' : '' ?>
                                    aria-describedby="test-push-help"
                                >
                                    <?php if (empty($users_with_tokens)): ?>
                                        <option value="" disabled selected>No registered users with active push token</option>
                                    <?php else: ?>
                                        <option value="" disabled selected>-- Select recipient user with token --</option>
                                        <?php foreach ($users_with_tokens as $u): ?>
                                            <option value="<?= (int)$u['id'] ?>">
                                                <?= e($u['name']) ?> (<?= e($u['email']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <div class="form-text small" id="test-push-help">Users who registered device notification tokens in the web/mobile app.</div>
                            </div>
                            <button
                                type="submit"
                                class="btn btn-outline-primary btn-sm w-100"
                                <?= empty($users_with_tokens) ? 'disabled' : '' ?>
                            >
                                <i class="bi bi-broadcast me-1"></i> Send test push notification
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Outbox Queue Section -->
    <section class="surface surface-section" aria-label="Delivery notification outbox queue">
        <header class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="h6 fw-bold mb-1">
                    <i class="bi bi-clock-history text-secondary me-2"></i>Delivery notification outbox queue
                </h3>
                <p class="text-muted small mb-0">
                    Queue status summary:
                    <span class="badge bg-secondary"><?= (int)($outbox_stats['pending'] ?? 0) ?> pending</span>
                    <span class="badge bg-success"><?= (int)($outbox_stats['sent'] ?? 0) ?> sent</span>
                    <span class="badge bg-danger"><?= (int)($outbox_stats['failed'] ?? 0) ?> failed</span>
                </p>
            </div>
            <form action="<?= base_url('admin/dispatch-outbox') ?>" method="POST" id="dispatch-outbox-form" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-primary fw-semibold">
                    <i class="bi bi-arrow-clockwise me-1"></i> Process pending queue now
                </button>
            </form>
        </header>

        <div class="table-responsive">
            <table class="table table-clean table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Order</th>
                        <th scope="col">Channel</th>
                        <th scope="col">Recipient</th>
                        <th scope="col">Status</th>
                        <th scope="col">Message</th>
                        <th scope="col">Sent / Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($outbox)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                                Notification outbox queue is currently empty.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($outbox as $item): ?>
                            <tr>
                                <td>#<?= (int)$item['id'] ?></td>
                                <td>
                                    <?php if (!empty($item['order_id'])): ?>
                                        <a href="<?= base_url('orders/show/' . (int)$item['order_id']) ?>" class="fw-semibold text-decoration-none">
                                            <?= e($item['order_no'] ?: '#' . $item['order_id']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= e(strtoupper($item['channel'])) ?>
                                    </span>
                                </td>
                                <td class="small text-truncate" style="max-width: 160px;" title="<?= e($item['recipient']) ?>">
                                    <?= e($item['recipient']) ?>
                                </td>
                                <td>
                                    <?php if ($item['status'] === 'sent'): ?>
                                        <span class="badge bg-success">Sent</span>
                                    <?php elseif ($item['status'] === 'failed'): ?>
                                        <span class="badge bg-danger">Failed</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-truncate" style="max-width: 250px;" title="<?= e($item['message']) ?>">
                                    <?= e($item['message']) ?>
                                </td>
                                <td class="small text-muted">
                                    <?= e($item['sent_at'] ?: $item['created_at']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php $this->call->view('templates/footer'); ?>
