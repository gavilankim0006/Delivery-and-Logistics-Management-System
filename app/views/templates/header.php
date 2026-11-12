<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$user = auth_user();
$role = auth_role();
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
$showCourierSimulator = $role === 'admin'
    && config_item('environment') === 'development'
    && in_array(strtolower(getenv('COURIER_DRIVER') ?: 'mock'), ['mock', ''], true);

$appShellData = [
    'user' => $user ?: new stdClass(),
    'role' => $role ?: 'guest',
    'baseUrl' => base_url(),
    'currentUri' => $currentUri,
    'csrfToken' => csrf_token(),
    'flashes' => [
        'success' => flash('success'),
        'error' => flash('error'),
        'warning' => flash('warning'),
    ],
    'showCourierSimulator' => $showCourierSimulator,
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= isset($page_title) ? e($page_title) . ' · ' : '' ?>Dispatch</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(base_url('dispatch-mark.svg')) ?>">
    <link rel="alternate icon" type="image/x-icon" href="<?= e(base_url('favicon.ico')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php $appCssPath = dirname(__DIR__, 3) . '/public/css/app.css'; ?>
    <link href="<?= base_url('css/app.css') ?><?= is_file($appCssPath) ? '?v=' . (string)filemtime($appCssPath) : '' ?>" rel="stylesheet">
    <?php $formsCssPath = dirname(__DIR__, 3) . '/public/css/shared-forms.css'; ?>
    <link href="<?= base_url('css/shared-forms.css') ?><?= is_file($formsCssPath) ? '?v=' . (string)filemtime($formsCssPath) : '' ?>" rel="stylesheet">
    <link href="<?= base_url('css/ai-assistant.css') ?>" rel="stylesheet">
    <?php $customerCssPath = dirname(__DIR__, 3) . '/public/css/customer-mobile.css'; ?>
    <link href="<?= base_url('css/customer-mobile.css') ?><?= is_file($customerCssPath) ? '?v=' . (string)filemtime($customerCssPath) : '' ?>" rel="stylesheet">
    <?php
    $mountPageCss = glob(dirname(__DIR__, 3) . '/public/build/assets/mountPage-*.css');
    if (!empty($mountPageCss)):
        $latestMountCss = end($mountPageCss);
    ?>
    <link href="<?= e(base_url('build/assets/' . basename($latestMountCss))) ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (isset($extra_css)): ?>
        <?= $extra_css ?>
    <?php endif; ?>
    <script nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>">
        window.__APP_SHELL__ = <?= json_encode($appShellData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?>;
    </script>
</head>
<body<?= $role ? ' class="role-' . e($role) . ($role === 'customer' ? ' has-customer-bottom-nav' : '') . '"' : '' ?>>
