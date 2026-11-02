<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$authCssFiles = glob(dirname(__DIR__, 3) . '/public/build/assets/auth-*.css');
$extraCss = !empty($authCssFiles) ? '<link rel="stylesheet" href="' . base_url('build/assets/' . basename($authCssFiles[0])) . '">' : '';
$this->call->view('templates/header', ['page_title' => $page_title ?? 'Create an account', 'extra_css' => $extraCss]);
$flashError = flash('error');
$flashSuccess = flash('success');
$csrfToken = csrf_token();
$oldRegister = flash('old_register') ?: [];
?>

<div id="vue-auth-root"
    data-page="register"
    data-requires-vue="true"
    data-base-url="<?= e(base_url()) ?>"
    data-csrf-token="<?= e($csrfToken) ?>"
    data-error-message="<?= e($flashError ?: '') ?>"
    data-success-message="<?= e($flashSuccess ?: '') ?>"
    data-old-register="<?= e(json_encode($oldRegister, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
</div>
<script type="module" src="<?= e(base_url('build/auth.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
<?php $this->call->view('templates/footer'); ?>
