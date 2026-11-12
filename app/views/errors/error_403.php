<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$user = function_exists('auth_user') ? auth_user() : null;
$role = function_exists('auth_role') ? auth_role() : 'guest';
$appShellData = [
    'user' => $user ?: new stdClass(),
    'role' => $role ?: 'guest',
    'baseUrl' => base_url(),
    'currentUri' => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/',
    'csrfToken' => function_exists('csrf_token') ? csrf_token() : '',
    'flashes' => [],
    'showCourierSimulator' => false,
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>403 · Access denied · Dispatch</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(base_url('dispatch-mark.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="<?= base_url('css/app.css') ?>" rel="stylesheet">
    <link href="<?= base_url('css/customer-mobile.css') ?>" rel="stylesheet">
    <?php
    $mountPageCss = glob(dirname(__DIR__, 3) . '/public/build/assets/mountPage-*.css');
    if (!empty($mountPageCss)):
        $latestMountCss = end($mountPageCss);
    ?>
    <link href="<?= e(base_url('build/assets/' . basename($latestMountCss))) ?>" rel="stylesheet">
    <?php endif; ?>
    <script nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>">
        window.__APP_SHELL__ = <?= json_encode($appShellData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
    </script>
</head>
<body class="role-<?= e($role) ?>">
    <div id="static-page-vue-root" data-page-kind="forbidden" data-base-url="<?= e(base_url()) ?>"></div>
    <script type="module" src="<?= e(base_url('build/static_pages.js')) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
</body>
</html>
