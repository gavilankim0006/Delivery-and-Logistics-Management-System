<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$user = auth_user();
$role = auth_role();
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . ' - ' : '' ?>Delivery & Logistics System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- App Design System -->
    <link href="<?= base_url('public/css/app.css') ?>" rel="stylesheet">
    <?php if (isset($extra_css)): ?>
        <?= $extra_css ?>
    <?php endif; ?>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand navbar-brand-custom" href="<?= base_url() ?>">
                <i class="bi bi-box-seam-fill text-primary"></i>
                <span>SwiftLogix</span>
                <?php if ($role === 'admin'): ?>
                    <span class="badge bg-danger ms-1" style="font-size: 0.65rem;">ADMIN</span>
                <?php elseif ($role === 'dispatcher'): ?>
                    <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">DISPATCH</span>
                <?php endif; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php if ($role === 'customer'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= str_contains($currentUri, '/orders/new') ? 'active' : '' ?>" href="<?= base_url('orders/new') ?>">
                                <i class="bi bi-plus-circle me-1"></i>New Order
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= (str_contains($currentUri, '/orders') && !str_contains($currentUri, '/orders/new')) ? 'active' : '' ?>" href="<?= base_url('orders') ?>">
                                <i class="bi bi-clock-history me-1"></i>My Orders
                            </a>
                        </li>
                    <?php elseif ($role === 'dispatcher'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= str_contains($currentUri, '/dispatch') ? 'active' : '' ?>" href="<?= base_url('dispatch') ?>">
                                <i class="bi bi-kanban me-1"></i>Dispatch Board
                            </a>
                        </li>
                    <?php elseif ($role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($currentUri === '/SIAproject/admin' || str_ends_with($currentUri, '/admin')) ? 'active' : '' ?>" href="<?= base_url('admin') ?>">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= str_contains($currentUri, '/dispatch') ? 'active' : '' ?>" href="<?= base_url('dispatch') ?>">
                                <i class="bi bi-kanban me-1"></i>Dispatch
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= str_contains($currentUri, '/admin/users') ? 'active' : '' ?>" href="<?= base_url('admin/users') ?>">
                                <i class="bi bi-people me-1"></i>Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= str_contains($currentUri, '/admin/riders') ? 'active' : '' ?>" href="<?= base_url('admin/riders') ?>">
                                <i class="bi bi-bicycle me-1"></i>Riders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= str_contains($currentUri, '/admin/settings') ? 'active' : '' ?>" href="<?= base_url('admin/settings') ?>">
                                <i class="bi bi-gear me-1"></i>Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= str_contains($currentUri, '/admin/reports') ? 'active' : '' ?>" href="<?= base_url('admin/reports') ?>">
                                <i class="bi bi-file-earmark-bar-graph me-1"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= str_contains($currentUri, '/admin/logs') ? 'active' : '' ?>" href="<?= base_url('admin/logs') ?>">
                                <i class="bi bi-journal-code me-1"></i>API Logs
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <ul class="navbar-nav ms-auto align-items-center">
                    <?php if (auth_check()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="badge rounded-pill bg-light text-dark border px-2 py-1">
                                    <i class="bi bi-person-circle text-primary me-1"></i>
                                    <?= e($user['name']) ?>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userDropdown">
                                <li><h6 class="dropdown-header"><?= e($user['email']) ?> (<?= ucfirst($role) ?>)</h6></li>
                                <li><a class="dropdown-item" href="<?= base_url('profile') ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?= base_url('logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item me-2">
                            <a class="nav-link <?= str_contains($currentUri, '/login') ? 'active' : '' ?>" href="<?= base_url('login') ?>">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Log In
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary btn-sm px-3" href="<?= base_url('register') ?>">
                                Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Global Flash Messages -->
    <div class="container mt-3">
        <?php if ($flashSuccess = flash('success')): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= e($flashSuccess) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($flashError = flash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($flashError) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($flashWarning = flash('warning')): ?>
            <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i><?= e($flashWarning) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Content Container -->
    <main class="flex-grow-1 py-4">
