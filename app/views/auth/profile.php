<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'My profile']);
$flashError = flash('error');
$flashSuccess = flash('success');
$csrfToken = csrf_token();
$vueUser = [
    'name' => (string)($user['name'] ?? ''),
    'email' => (string)($user['email'] ?? ''),
    'phone' => (string)($user['phone'] ?? ''),
    'role_name' => (string)($user['role_name'] ?? 'Customer'),
    'sms_opt_in' => (int)($user['sms_opt_in'] ?? 0),
    'delivery_status_sms_opt_in' => (int)($user['delivery_status_sms_opt_in'] ?? 0),
    'push_opt_in' => (int)($user['push_opt_in'] ?? 0)
];
?>

<div id="vue-auth-root"
    data-page="profile"
    data-requires-vue="true"
    data-base-url="<?= e(base_url()) ?>"
    data-csrf-token="<?= e($csrfToken) ?>"
    data-user="<?= e(json_encode($vueUser, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>"
    data-vapid-key="<?= e(getenv('FIREBASE_VAPID_KEY') ?: '') ?>"
    data-error-message="<?= e($flashError ?: '') ?>"
    data-success-message="<?= e($flashSuccess ?: '') ?>">
</div>
<script type="module" src="<?= e(base_url('build/auth.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
