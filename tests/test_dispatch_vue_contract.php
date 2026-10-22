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
$entry = file_get_contents($root . '/frontend/dispatch/main.js');
$manifest = json_decode(file_get_contents($root . '/package.json'), true);

assert_dispatch_vue(str_contains($routes, '$router->get(\'/dispatch/data\', \'Dispatch::data\');'), 'Missing dispatch data route.');
assert_dispatch_vue(str_contains($controller, "require_role(['dispatcher', 'admin']);"), 'Dispatcher data route must remain role-protected.');
assert_dispatch_vue(str_contains($controller, '\'orders\' => $orders'), 'JSON response must contain dispatch orders.');
assert_dispatch_vue(str_contains($controller, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT'), 'JSON must be safe to embed and consume.');
assert_dispatch_vue(str_contains($view, 'data-csrf-token="<?= e($csrf_token) ?>"'), 'Vue mount must receive the session CSRF token.');
assert_dispatch_vue(str_contains($view, "base_url('public/build/dispatch.js')"), 'PHP view must load the built Vue entry bundle.');
assert_dispatch_vue(str_contains($component, 'setInterval(loadBoard, 30000)'), 'Vue board must poll for live updates.');
assert_dispatch_vue(str_contains($component, 'name="csrf_token" :value="csrfToken"'), 'Recovery action must preserve CSRF protection.');
assert_dispatch_vue(isset($manifest['dependencies']['vue']), 'Vue must be an application dependency.');
assert_dispatch_vue(is_file($root . '/vite.config.js'), 'Missing Vite build configuration.');

echo "Vue dispatch integration contract passed.\n";
