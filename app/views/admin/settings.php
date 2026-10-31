<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Fee Rates and Configuration']);
?>

<div class="container page-shell py-4">
    <header class="page-header mb-4">
        <div>
            <h1 class="page-title">Fee rates and settings</h1>
            <p class="page-description">Configure delivery pricing and customer support contact details.</p>
        </div>
    </header>

    <section class="surface surface-section" aria-label="Delivery pricing and support settings">
        <form action="<?= base_url('admin/settings') ?>" method="POST">
            <?= csrf_field() ?>

            <section class="form-section mb-4" aria-labelledby="pricing-heading">
                <h2 class="h5 mb-3" id="pricing-heading">Delivery fee calculation</h2>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="base-delivery-fee" class="form-label">Base delivery fee (PHP)</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">₱</span>
                            <input id="base-delivery-fee" type="number" min="0" max="100000" step="0.01" class="form-control" name="base_delivery_fee" value="<?= e($settings['base_delivery_fee'] ?? '60.00') ?>" required aria-describedby="base-delivery-fee-help">
                        </div>
                        <div class="form-text" id="base-delivery-fee-help">Standard charge that covers the base distance.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="free-distance" class="form-label">Free distance limit (km)</label>
                        <div class="input-group">
                            <input id="free-distance" type="number" min="0" max="10000" step="0.1" class="form-control" name="free_distance_km" value="<?= e($settings['free_distance_km'] ?? '2.0') ?>" required aria-describedby="free-distance-help">
                            <span class="input-group-text">km</span>
                        </div>
                        <div class="form-text" id="free-distance-help">Distance included in the base fee.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="per-km-rate" class="form-label">Distance rate per km (PHP)</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">₱</span>
                            <input id="per-km-rate" type="number" min="0" max="10000" step="0.01" class="form-control" name="per_km_rate" value="<?= e($settings['per_km_rate'] ?? '12.50') ?>" required aria-describedby="per-km-rate-help">
                        </div>
                        <div class="form-text" id="per-km-rate-help">Applied to each kilometer beyond the free distance limit.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="free-weight" class="form-label">Free weight limit (kg)</label>
                        <div class="input-group">
                            <input id="free-weight" type="number" min="0" max="1000" step="0.1" class="form-control" name="free_weight_kg" value="<?= e($settings['free_weight_kg'] ?? '1.0') ?>" required aria-describedby="free-weight-help">
                            <span class="input-group-text">kg</span>
                        </div>
                        <div class="form-text" id="free-weight-help">Weight included in the base fee.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="per-kg-rate" class="form-label">Weight rate per kg (PHP)</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true">₱</span>
                            <input id="per-kg-rate" type="number" min="0" max="10000" step="0.01" class="form-control" name="per_kg_rate" value="<?= e($settings['per_kg_rate'] ?? '5.00') ?>" required aria-describedby="per-kg-rate-help">
                        </div>
                        <div class="form-text" id="per-kg-rate-help">Applied to each kilogram beyond the free weight limit.</div>
                    </div>
                </div>
            </section>

            <section class="form-section mb-4" aria-labelledby="support-heading">
                <h2 class="h5 mb-3" id="support-heading">Customer support</h2>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="support-email" class="form-label">Support email address</label>
                        <input id="support-email" type="email" class="form-control" name="support_email" value="<?= e($settings['support_email'] ?? 'support@delivery.local') ?>" required autocomplete="email">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="support-phone" class="form-label">Support phone hotline</label>
                        <input id="support-phone" type="tel" class="form-control" name="support_phone" value="<?= e($settings['support_phone'] ?? '+63 2 8123 4567') ?>" required autocomplete="tel">
                    </div>
                </div>
            </section>

            <div class="d-flex flex-wrap justify-content-end gap-2">
                <a href="<?= base_url('admin') ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save settings</button>
            </div>
        </form>
    </section>

    <!-- Notification Gateways & Testing Tools -->
    <section class="surface surface-section mt-4" aria-labelledby="notification-gateways-heading">
        <header class="mb-4">
            <h2 class="h5 mb-1" id="notification-gateways-heading"><i class="bi bi-broadcast me-2 text-primary"></i>Notification gateways (Semaphore & Firebase)</h2>
            <p class="text-muted mb-0 small">Monitor API integration status and dispatch test alerts.</p>
        </header>

        <div class="row g-4 mb-4">
            <!-- Semaphore SMS Gateway Card -->
            <div class="col-12 col-lg-6">
                <div class="card h-100 border">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                        <span class="fw-bold"><i class="bi bi-chat-text text-primary me-2"></i>Semaphore SMS Gateway</span>
                        <?php if ($semaphore_enabled && !empty($semaphore_api_key)): ?>
                            <span class="badge bg-success">Active</span>
                        <?php elseif (!empty($semaphore_api_key)): ?>
                            <span class="badge bg-warning text-dark">Configured (Disabled in .env)</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Missing API Key</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small mb-3">
                            <li class="mb-1"><strong>Sender Name:</strong> <code><?= e($semaphore_sender_name) ?></code></li>
                            <li class="mb-1"><strong>API Key:</strong> <?= !empty($semaphore_api_key) ? '<code>' . e(substr($semaphore_api_key, 0, 6)) . '...' . e(substr($semaphore_api_key, -4)) . '</code>' : '<span class="text-danger">Not set</span>' ?></li>
                            <li><strong>Delivery Events:</strong> Order payment confirmation, rider assigned, picked up, delivered.</li>
                        </ul>

                        <hr>
                        <h3 class="h6 mb-2">Test SMS Dispatch</h3>
                        <form action="<?= base_url('admin/test-sms') ?>" method="POST">
                            <?= csrf_field() ?>
                            <div class="input-group mb-2">
                                <input type="tel" name="phone" class="form-control form-control-sm" placeholder="e.g. 09171234567" required>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-send me-1"></i> Send test SMS
                                </button>
                            </div>
                            <input type="text" name="message" class="form-control form-control-sm" value="Dispatch test SMS: Semaphore integration is operational." required>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Firebase Cloud Messaging Card -->
            <div class="col-12 col-lg-6">
                <div class="card h-100 border">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                        <span class="fw-bold"><i class="bi bi-app-indicator text-primary me-2"></i>Firebase Cloud Messaging (FCM)</span>
                        <?php if ($firebase_enabled && $firebase_has_key): ?>
                            <span class="badge bg-success">Active (HTTP v1)</span>
                        <?php elseif ($firebase_has_key): ?>
                            <span class="badge bg-warning text-dark">Configured (Disabled in .env)</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Credentials Missing</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small mb-3">
                            <li class="mb-1"><strong>Project ID:</strong> <code><?= e($firebase_project_id ?: 'delivery-and-logistics-system') ?></code></li>
                            <li class="mb-1"><strong>Auth Mode:</strong> Google OAuth2 JWT (RS256 Service Account)</li>
                            <li><strong>Target:</strong> Registered customer device tokens &amp; browser service workers.</li>
                        </ul>

                        <hr>
                        <h3 class="h6 mb-2">Test Push Dispatch</h3>
                        <form action="<?= base_url('admin/test-push') ?>" method="POST">
                            <?= csrf_field() ?>
                            <div class="mb-2">
                                <label for="push-user-select" class="form-label small text-muted mb-1">Target registered user:</label>
                                <select id="push-user-select" name="user_id" class="form-select form-select-sm" required>
                                    <?php if (empty($users_with_tokens)): ?>
                                        <option value="" disabled selected>No registered device tokens in database</option>
                                    <?php else: ?>
                                        <?php foreach ($users_with_tokens as $u): ?>
                                            <option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['email']) ?>)</option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary w-100" <?= empty($users_with_tokens) ? 'disabled' : '' ?>>
                                <i class="bi bi-broadcast me-1"></i> Send test push notification
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Outbox Queue Section -->
        <header class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pt-3 border-top">
            <div>
                <h3 class="h6 fw-bold mb-1">Delivery notification outbox queue</h3>
                <p class="text-muted small mb-0">
                    Queue summary:
                    <span class="badge bg-secondary"><?= (int)($outbox_stats['pending'] ?? 0) ?> pending</span>
                    <span class="badge bg-success"><?= (int)($outbox_stats['sent'] ?? 0) ?> sent</span>
                    <span class="badge bg-danger"><?= (int)($outbox_stats['failed'] ?? 0) ?> failed</span>
                </p>
            </div>
            <form action="<?= base_url('admin/dispatch-outbox') ?>" method="POST" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-primary">
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
                        <tr><td colspan="7" class="text-center py-4 text-muted">No outbox notifications queued yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($outbox as $item): ?>
                            <tr>
                                <td class="text-muted">#<?= (int)$item['id'] ?></td>
                                <td>
                                    <?php if (!empty($item['order_no'])): ?>
                                        <a href="<?= base_url('orders/' . (int)$item['order_id']) ?>">#<?= e($item['order_no']) ?></a>
                                    <?php else: ?>
                                        #<?= (int)$item['order_id'] ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['channel'] === 'sms'): ?>
                                        <span class="badge bg-info-subtle text-info border"><i class="bi bi-chat-text me-1"></i>SMS</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary border"><i class="bi bi-app-indicator me-1"></i>Push</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= e(strlen($item['recipient']) > 24 ? substr($item['recipient'], 0, 10) . '...' . substr($item['recipient'], -6) : $item['recipient']) ?></code></td>
                                <td>
                                    <?php
                                    $statusBadges = [
                                        'pending' => 'bg-warning text-dark',
                                        'sending' => 'bg-info',
                                        'sent' => 'bg-success',
                                        'failed' => 'bg-danger'
                                    ];
                                    ?>
                                    <span class="badge <?= $statusBadges[$item['status']] ?? 'bg-secondary' ?>"><?= e(ucfirst($item['status'])) ?></span>
                                </td>
                                <td class="small text-truncate" style="max-width: 280px;" title="<?= e($item['message']) ?>"><?= e($item['message']) ?></td>
                                <td class="small text-nowrap text-muted">
                                    <?= !empty($item['sent_at']) ? date('M j, g:i a', strtotime($item['sent_at'])) : date('M j, g:i a', strtotime($item['created_at'])) ?>
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
