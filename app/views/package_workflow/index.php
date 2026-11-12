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
$flashes = [];
foreach (['success', 'error', 'warning', 'info'] as $flashType) {
    if ($message = flash($flashType)) {
        $flashes[] = ['type' => $flashType, 'message' => $message];
    }
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
    'flashes'          => $flashes,
];
$messengerPropsJson = json_encode($messengerProps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
?>

<div id="messenger-vue-root" data-props="<?= e($messengerPropsJson) ?>">
    <noscript><div class="container py-4 page-shell"><div class="alert alert-warning" role="status">Enable JavaScript to use Package Messenger.</div></div></noscript>
</div>

<?php
$messengerBundlePath = dirname(__DIR__, 3) . '/public/build/messenger.js';
$messengerVersion = is_file($messengerBundlePath) ? '?v=' . (string)filemtime($messengerBundlePath) : '';
$extraJs = '<script type="module" src="' . e(base_url('build/messenger.js')) . e($messengerVersion) . '" nonce="' . e(defined('CSP_NONCE') ? CSP_NONCE : '') . '"></script>';
$this->call->view('templates/footer', ['extra_js' => $extraJs]);
?>
