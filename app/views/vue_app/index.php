<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title ?? 'Vue Application') ?></title>
    <?php $appCssPath = dirname(__DIR__, 3) . '/public/css/app.css'; ?>
    <link href="<?= base_url('css/app.css') ?><?= is_file($appCssPath) ? '?v=' . (string)filemtime($appCssPath) : '' ?>" rel="stylesheet">
</head>
<body>
    <div id="app"></div>
    <script>
        window.__INITIAL_STATE__ = <?= json_encode($initial_state ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <?php $appBundlePath = dirname(__DIR__, 3) . '/public/build/app.js'; $appBundleVersion = is_file($appBundlePath) ? '?v=' . (string)filemtime($appBundlePath) : ''; ?>
    <script type="module" src="<?= e(base_url('build/app.js' . $appBundleVersion)) ?>" nonce="<?= defined('CSP_NONCE') ? e(CSP_NONCE) : '' ?>"></script>
</body>
</html>