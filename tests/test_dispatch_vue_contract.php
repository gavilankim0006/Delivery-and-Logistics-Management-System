<?php

declare(strict_types=1);

function assert_dispatch_vue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$routes = file_get_contents($root . '/app/config/routes.php');
$controller = file_get_contents($root . '/app/controllers/Dispatch.php');
$view = file_get_contents($root . '/app/views/dispatch/index.php');
$component = file_get_contents($root . '/frontend/dispatch/DispatchBoard.vue');
$controls = file_get_contents($root . '/frontend/dispatch/DispatchControls.vue');
$entry = file_get_contents($root . '/frontend/dispatch/main.js');
$manifest = json_decode(file_get_contents($root . '/package.json'), true);

assert_dispatch_vue(str_contains($routes, '$router->get(\'/dispatch/data\', \'Dispatch::data\');'), 'Missing dispatch data route.');
assert_dispatch_vue(str_contains($controller, "require_role(['dispatcher', 'admin']);"), 'Dispatcher data route must remain role-protected.');
assert_dispatch_vue(str_contains($controller, '\'orders\' => $orders'), 'JSON response must contain dispatch orders.');
assert_dispatch_vue(str_contains($controller, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT'), 'JSON must be safe to embed and consume.');
assert_dispatch_vue(str_contains($view, 'data-csrf-token="<?= e($csrf_token) ?>"'), 'Vue mount must receive the session CSRF token.');
assert_dispatch_vue(str_contains($view, 'data-fulfillment-actions='), 'Vue mount must receive the fulfillment-action flag.');
assert_dispatch_vue(str_contains($view, 'data-available-riders='), 'Vue mount must receive available internal riders.');
assert_dispatch_vue(str_contains($component, 'assignInternalUrl(order.id)'), 'Vue dispatch board must offer internal rider assignment.');
assert_dispatch_vue(str_contains($component, 'selectLalamoveUrl(order.id)'), 'Vue dispatch board must offer explicit Lalamove selection.');
assert_dispatch_vue(str_contains($view, 'dispatch-controls-vue-root'), 'Dispatch controls must mount in Vue.');
assert_dispatch_vue(str_contains($controls, 'pendingRequests'), 'Vue dispatch controls must render hold requests.');
assert_dispatch_vue(str_contains($controls, ':name="csrfName"'), 'Hold-review forms must retain the server-provided CSRF field name.');
assert_dispatch_vue(str_contains($component, "dispatch-summary"), 'Dispatch board must publish live summary updates.');
assert_dispatch_vue(str_contains($entry, 'fulfillmentActions: root.dataset.fulfillmentActions === \'true\''), 'Vue entry must pass the fulfillment-action flag.');
assert_dispatch_vue(str_contains($entry, 'JSON.parse(root.dataset.availableRiders'), 'Vue entry must parse available riders safely.');
assert_dispatch_vue(str_contains($component, 'function canChooseFulfillment(order)'), 'Vue board must limit fulfillment choice to eligible orders.');
assert_dispatch_vue(str_contains($component, 'function canReassignInternal(order)'), 'Vue board must expose internal rider reassignment.');
assert_dispatch_vue(str_contains($component, 'function canRetryInternalAssignment(order)'), 'Vue board must expose safe retries for failed internal assignments.');
assert_dispatch_vue(str_contains($controller, "'available_riders' => \$this->Rider_model->get_available_internal()"), 'Dispatch refresh must return the latest available rider list.');
assert_dispatch_vue(str_contains($component, 'order.recovery.kind === \'escalate\''), 'Only escalation recovery actions should offer a customer call.');
assert_dispatch_vue(str_contains($view, "base_url('public/build/dispatch.js')"), 'PHP view must load the built Vue entry bundle.');
assert_dispatch_vue(str_contains($component, 'setInterval(loadBoard, 30000)'), 'Vue board must poll for live updates.');
assert_dispatch_vue(str_contains($component, 'name="csrf_token" :value="csrfToken"'), 'Recovery action must preserve CSRF protection.');
assert_dispatch_vue(isset($manifest['dependencies']['vue']), 'Vue must be an application dependency.');
assert_dispatch_vue(is_file($root . '/vite.config.js'), 'Missing Vite build configuration.');

echo "Vue dispatch integration contract passed.\n";
