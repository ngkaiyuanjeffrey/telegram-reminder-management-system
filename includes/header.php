<?php
/**
 * Telegram Reminder Management System
 * Admin Header & Topbar Layout
 */

declare(strict_types=1);

// Ensure configuration is loaded
if (!defined('ROOT_PATH')) {
    require_once dirname(__DIR__) . '/config/config.php';
}

// Require authentication
require_login();

$currentAdmin = current_admin();
$pageTitle = $pageTitle ?? 'Dashboard';
$activeMenu = $activeMenu ?? 'dashboard';

// Fetch quick pending reminder count for sidebar badge
$db = get_db();
$pendingCount = 0;
if ($db) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM reminders WHERE status = 'pending'");
        $pendingCount = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom Theme Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <script>
        window.APP_BASE_URL = "<?= BASE_URL ?>";
        window.CSRF_TOKEN = "<?= csrf_token() ?>";
    </script>
</head>
<body>

<div class="app-wrapper">
    <!-- Sidebar Backdrop for Mobile -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Include Sidebar Navigation -->
    <?php require_once INCLUDES_PATH . '/sidebar.php'; ?>

    <!-- Main Content Container -->
    <div class="app-main">
        <!-- Top Navbar -->
        <header class="app-topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation">
                    <i class="bi bi-list"></i>
                </button>
                <div class="d-none d-md-flex align-items-center gap-2">
                    <span class="text-muted small">System Status:</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                        <i class="bi bi-circle-fill badge-pulse me-1"></i>Active & Running
                    </span>
                </div>
            </div>

            <div class="topbar-right">
                <!-- Quick Manual Cron Trigger Button -->
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 d-none d-sm-inline-flex align-items-center gap-1.5" id="btnRunCronNow" title="Trigger pending reminders immediately">
                    <i class="bi bi-play-circle-fill text-primary"></i>
                    <span>Run Cron Now</span>
                </button>

                <!-- Quick Reminder Create Button -->
                <a href="<?= BASE_URL ?>/reminders/create.php" class="btn btn-sm btn-primary rounded-pill px-3 d-inline-flex align-items-center gap-1.5 shadow-sm">
                    <i class="bi bi-plus-lg"></i>
                    <span class="d-none d-sm-inline">New Reminder</span>
                </a>

                <!-- User Dropdown Menu -->
                <div class="dropdown">
                    <button class="btn btn-light border-0 dropdown-toggle d-flex align-items-center gap-2 p-1.5 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; font-weight: 700;">
                            <?= strtoupper(substr($currentAdmin['full_name'] ?? $currentAdmin['username'] ?? 'A', 0, 1)) ?>
                        </div>
                        <span class="d-none d-md-inline fw-semibold small text-dark me-1">
                            <?= e($currentAdmin['full_name'] ?? $currentAdmin['username'] ?? 'Admin') ?>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 mt-2">
                        <li class="px-3 py-2 border-bottom">
                            <p class="mb-0 fw-bold text-dark"><?= e($currentAdmin['full_name'] ?? 'Admin') ?></p>
                            <span class="badge bg-primary-subtle text-primary small text-uppercase"><?= e($currentAdmin['role'] ?? 'admin') ?></span>
                        </li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/admin/profile.php"><i class="bi bi-person-gear me-2 text-muted"></i>My Profile</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/admin/settings.php"><i class="bi bi-sliders me-2 text-muted"></i>Bot & System Settings</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>/admin/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Page Main Content Wrap -->
        <main class="app-content">
            <!-- Flash Message Banner -->
            <?= display_flash() ?>
