<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Apply to join Dispatch']);
$careersProps = [
    'formData' => $form_data ?? [],
    'errors' => $errors ?? [],
    'authEmail' => (string)(auth_user()['email'] ?? ''),
    'baseUrl' => base_url(),
    'csrfToken' => csrf_token(),
    'pendingApplication' => $pending_application ?? null,
];
?>
<div id="vue-careers-root" data-props="<?= e(json_encode($careersProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>"></div>
<script type="module" src="<?= e(base_url('build/careers.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
