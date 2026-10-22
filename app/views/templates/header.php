<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$user = auth_user();
$role = auth_role();
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
$navIsActive = static function (string $destination) use ($currentUri): bool {
    $path = rtrim($currentUri, '/') ?: '/';
    $target = '/' . trim($destination, '/');
    return $path === $target || str_ends_with($path, $target) || str_contains($path, $target . '/');
};
$showCourierSimulator = $role === 'admin'
    && config_item('environment') === 'development'
    && in_array(strtolower(getenv('COURIER_DRIVER') ?: 'mock'), ['mock', ''], true);
$adminMoreActive = $navIsActive('admin/settings')
    || $navIsActive('admin/service-areas')
    || $navIsActive('admin/command-simulator')
    || $navIsActive('admin/reports')
    || $navIsActive('admin/logs')
    || ($showCourierSimulator && $navIsActive('admin/courier-simulator'));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= isset($page_title) ? e($page_title) . ' · ' : '' ?>Dispatch</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="<?= base_url('public/css/app.css') ?>" rel="stylesheet">
    <?php if (isset($extra_css)): ?>
        <?= $extra_css ?>
    <?php endif; ?>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand navbar-brand-custom" href="<?= base_url($role === 'admin' ? 'admin' : ($role === 'dispatcher' ? 'dispatch' : ($role === 'customer' ? 'orders' : 'login'))) ?>" aria-label="Dispatch home">
                <i class="bi bi-box-seam" aria-hidden="true"></i>
                <span>Dispatch</span>
                <?php if ($role === 'admin' || $role === 'dispatcher'): ?>
                    <span class="navbar-role"><?= $role === 'admin' ? 'Admin' : 'Dispatch team' ?></span>
                <?php endif; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon" aria-hidden="true"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php if ($role === 'customer'): ?>
                        <li class="nav-item"><a class="nav-link<?= $navIsActive('orders/new') ? ' active' : '' ?>" href="<?= base_url('orders/new') ?>"<?= $navIsActive('orders/new') ? ' aria-current="page"' : '' ?>><i class="bi bi-plus-circle" aria-hidden="true"></i><span>New order</span></a></li>
                        <li class="nav-item"><a class="nav-link<?= $navIsActive('orders') && !$navIsActive('orders/new') ? ' active' : '' ?>" href="<?= base_url('orders') ?>"<?= $navIsActive('orders') && !$navIsActive('orders/new') ? ' aria-current="page"' : '' ?>><i class="bi bi-clock-history" aria-hidden="true"></i><span>My Orders</span></a></li>
                        <li class="nav-item"><a class="nav-link<?= $navIsActive('addresses') ? ' active' : '' ?>" href="<?= base_url('addresses') ?>"<?= $navIsActive('addresses') ? ' aria-current="page"' : '' ?>><i class="bi bi-geo-alt" aria-hidden="true"></i><span>Saved addresses</span></a></li>
                    <?php elseif ($role === 'dispatcher'): ?>
                        <li class="nav-item"><a class="nav-link<?= $navIsActive('dispatch') ? ' active' : '' ?>" href="<?= base_url('dispatch') ?>"<?= $navIsActive('dispatch') ? ' aria-current="page"' : '' ?>><i class="bi bi-kanban" aria-hidden="true"></i><span>Dispatch board</span></a></li>
                        <li class="nav-item"><a class="nav-link<?= $navIsActive('orders') ? ' active' : '' ?>" href="<?= base_url('orders') ?>"<?= $navIsActive('orders') ? ' aria-current="page"' : '' ?>><i class="bi bi-box-seam" aria-hidden="true"></i><span>Orders</span></a></li>
                    <?php elseif ($role === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link<?= $navIsActive('admin') && !$navIsActive('admin/users') && !$navIsActive('admin/riders') && !$navIsActive('admin/service-areas') && !$navIsActive('admin/command-simulator') && !$navIsActive('admin/settings') && !$navIsActive('admin/reports') && !$navIsActive('admin/logs') && !$navIsActive('admin/courier-simulator') && !$navIsActive('admin/service-areas') && !$navIsActive('admin/command-simulator') ? ' active' : '' ?>" href="<?= base_url('admin') ?>"<?= $navIsActive('admin') && !$navIsActive('admin/users') && !$navIsActive('admin/riders') && !$navIsActive('admin/service-areas') && !$navIsActive('admin/command-simulator') && !$navIsActive('admin/settings') && !$navIsActive('admin/reports') && !$navIsActive('admin/logs') && !$navIsActive('admin/courier-simulator') ? ' aria-current="page"' : '' ?>><i class="bi bi-speedometer2" aria-hidden="true"></i><span>Dashboard</span></a></li>
                        <li class="nav-item"><a class="nav-link<?= $navIsActive('dispatch') ? ' active' : '' ?>" href="<?= base_url('dispatch') ?>"<?= $navIsActive('dispatch') ? ' aria-current="page"' : '' ?>><i class="bi bi-kanban" aria-hidden="true"></i><span>Dispatch</span></a></li>
                        <li class="nav-item"><a class="nav-link<?= $navIsActive('orders') ? ' active' : '' ?>" href="<?= base_url('orders') ?>"<?= $navIsActive('orders') ? ' aria-current="page"' : '' ?>><i class="bi bi-box-seam" aria-hidden="true"></i><span>Orders</span></a></li>
                        <li class="nav-item"><a class="nav-link<?= $navIsActive('admin/users') ? ' active' : '' ?>" href="<?= base_url('admin/users') ?>"<?= $navIsActive('admin/users') ? ' aria-current="page"' : '' ?>><i class="bi bi-people" aria-hidden="true"></i><span>Users</span></a></li>
                        <li class="nav-item"><a class="nav-link<?= $navIsActive('admin/riders') ? ' active' : '' ?>" href="<?= base_url('admin/riders') ?>"<?= $navIsActive('admin/riders') ? ' aria-current="page"' : '' ?>><i class="bi bi-bicycle" aria-hidden="true"></i><span>Riders</span></a></li>
                        <li class="nav-item dropdown">
                            <button class="nav-link dropdown-toggle<?= $adminMoreActive ? ' active' : '' ?>" id="adminMoreDropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true">
                                <span>More</span>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="adminMoreDropdown">
                                <li><a class="dropdown-item<?= $navIsActive('admin/settings') ? ' active' : '' ?>" href="<?= base_url('admin/settings') ?>"<?= $navIsActive('admin/settings') ? ' aria-current="page"' : '' ?>><i class="bi bi-gear" aria-hidden="true"></i> Settings</a></li>
                                <li><a class="dropdown-item<?= $navIsActive('admin/service-areas') ? ' active' : '' ?>" href="<?= base_url('admin/service-areas') ?>"<?= $navIsActive('admin/service-areas') ? ' aria-current="page"' : '' ?>><i class="bi bi-bounding-box" aria-hidden="true"></i> Service areas</a></li>
                                <li><a class="dropdown-item<?= $navIsActive('admin/command-simulator') ? ' active' : '' ?>" href="<?= base_url('admin/command-simulator') ?>"<?= $navIsActive('admin/command-simulator') ? ' aria-current="page"' : '' ?>><i class="bi bi-lightning" aria-hidden="true"></i> Command simulator</a></li>
                                <li><a class="dropdown-item<?= $navIsActive('admin/reports') ? ' active' : '' ?>" href="<?= base_url('admin/reports') ?>"<?= $navIsActive('admin/reports') ? ' aria-current="page"' : '' ?>><i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i> Reports</a></li>
                                <li><a class="dropdown-item<?= $navIsActive('admin/logs') ? ' active' : '' ?>" href="<?= base_url('admin/logs') ?>"<?= $navIsActive('admin/logs') ? ' aria-current="page"' : '' ?>><i class="bi bi-journal-code" aria-hidden="true"></i> API logs</a></li>
                                <?php if ($showCourierSimulator): ?>
                                    <li><a class="dropdown-item<?= $navIsActive('admin/courier-simulator') ? ' active' : '' ?>" href="<?= base_url('admin/courier-simulator') ?>"<?= $navIsActive('admin/courier-simulator') ? ' aria-current="page"' : '' ?>><i class="bi bi-truck" aria-hidden="true"></i> Courier simulator</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>

                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <?php if (auth_check()): ?>
                        <li class="nav-item dropdown">
                            <button class="nav-link dropdown-toggle" id="userDropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle" aria-hidden="true"></i>
                                <span><?= e($user['name'] ?? 'Account') ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><span class="dropdown-header"><?= e($user['email'] ?? '') ?> · <?= e(ucfirst((string) $role)) ?></span></li>
                                <li><a class="dropdown-item" href="<?= base_url('profile') ?>"><i class="bi bi-person" aria-hidden="true"></i> Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= base_url('logout') ?>"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sign out</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link<?= $navIsActive('login') ? ' active' : '' ?>" href="<?= base_url('login') ?>"<?= $navIsActive('login') ? ' aria-current="page"' : '' ?>><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i><span>Sign in</span></a></li>
                        <li class="nav-item"><a class="btn btn-primary" href="<?= base_url('register') ?>">Create account</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <?php
    $flashSuccess = flash('success');
    $flashError = flash('error');
    $flashWarning = flash('warning');
    if ($flashSuccess || $flashError || $flashWarning):
    ?>
        <div class="container page-shell" aria-live="polite">
            <div class="mt-3">
                <?php if ($flashSuccess): ?><div class="alert alert-success" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i> <?= e($flashSuccess) ?></div><?php endif; ?>
                <?php if ($flashError): ?><div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?= e($flashError) ?></div><?php endif; ?>
                <?php if ($flashWarning): ?><div class="alert alert-warning" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e($flashWarning) ?></div><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <main class="app-main" id="main-content" tabindex="-1">
