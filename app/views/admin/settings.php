<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$db = $db ?? null;
$settings = $settings ?? [];
$outbox = $outbox ?? [];
$outboxStats = $outbox_stats ?? [];
$usersWithTokens = $users_with_tokens ?? [];
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
}
$props = [
    'settings' => $settings,
    'outbox' => $outbox,
    'outboxStats' => $outboxStats,
    'usersWithTokens' => $usersWithTokens,
    'mail' => [
        'enabled' => !empty($mail_enabled),
        'host' => $mail_host ?? '',
        'port' => (int)($mail_port ?? 587),
        'fromAddress' => $mail_from_address ?? '',
        'fromName' => $mail_from_name ?? '',
    ],
    'semaphore' => [
        'enabled' => !empty($semaphore_enabled),
        'apiKey' => $semaphore_api_key ?? '',
        'senderName' => $semaphore_sender_name ?? '',
    ],
    'firebase' => [
        'enabled' => !empty($firebase_enabled),
        'projectId' => $firebase_project_id ?? '',
        'hasKey' => !empty($firebase_has_key),
    ],
    'semaphoreEnabled' => !empty($semaphore_enabled),
    'firebaseEnabled' => !empty($firebase_enabled),
    'mailEnabled' => !empty($mail_enabled),
    'baseUrl' => base_url(),
    'csrfToken' => csrf_token(),
    'flashes' => $flashes,
];
?>
<?php $this->call->view('templates/header', ['page_title' => $page_title ?? 'Fee Rates and Configuration']); ?>
<div id="vue-admin-root"
    data-page="settings"
    data-props="<?= e(json_encode($props, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>">
</div>
<script type="module" src="<?= e(base_url('build/admin.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>