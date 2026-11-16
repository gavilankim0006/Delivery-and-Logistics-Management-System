<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title><?= e($page_title ?? 'Tracking link unavailable') ?> · Dispatch</title>
    <?php $appCssPath = dirname(__DIR__, 3) . '/public/css/app.css'; ?>
    <link href="<?= e(base_url('css/app.css')) ?><?= is_file($appCssPath) ? '?v=' . (string)filemtime($appCssPath) : '' ?>" rel="stylesheet">
    <style>
        body.tracking-missing-page {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: var(--paper, #EEF2F5);
            color: var(--ink, #172B3A);
            font-family: 'Manrope', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        .tracking-missing-card {
            max-width: 460px;
            width: 100%;
            background: var(--surface, #fff);
            border: 1px solid var(--line, #D3DDE5);
            border-radius: 14px;
            padding: 32px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(20, 33, 61, 0.08);
        }
        .tracking-missing-card .icon {
            font-size: 40px;
            line-height: 1;
            display: inline-block;
            margin-bottom: 12px;
        }
        .tracking-missing-card h1 { font-size: 20px; margin: 0 0 8px; }
        .tracking-missing-card p { margin: 0 0 20px; color: var(--ink-2, #4C6070); font-size: 14px; }
        .tracking-missing-card a.home-link {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 8px;
            background: var(--route, #087F98);
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>
<body class="tracking-missing-page">
    <div class="tracking-missing-card">
        <span class="icon" aria-hidden="true">&#128269;</span>
        <h1>Tracking link unavailable</h1>
        <p>This tracking link is invalid, expired, or has been stopped by the sender. Ask the sender to share a new link.</p>
        <a class="home-link" href="<?= e(base_url()) ?>">Go to Dispatch</a>
    </div>
</body>
</html>
