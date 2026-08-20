<?php
/**
 * Telegram Reminder Management System
 * Admin Sidebar Navigation
 */

declare(strict_types=1);

$activeMenu = $activeMenu ?? 'dashboard';
?>
<aside class="app-sidebar" id="appSidebar">
    <!-- Brand Logo -->
    <a href="<?= BASE_URL ?>/admin/index.php" class="sidebar-brand">
        <i class="bi bi-telegram"></i>
        <span>
            <strong>Telegram</strong> Reminder
            <small class="d-block text-muted" style="font-size: 0.7rem; font-weight: normal;">Management System</small>
        </span>
    </a>

    <!-- Navigation Menu -->
    <ul class="sidebar-menu">
        <li class="sidebar-heading">Core System</li>
        
        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/index.php" class="sidebar-link <?= ($activeMenu === 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="sidebar-heading">Reminder Management</li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/reminders/index.php" class="sidebar-link <?= ($activeMenu === 'reminders') ? 'active' : '' ?>">
                <i class="bi bi-alarm-fill"></i>
                <span>All Reminders</span>
                <?php if (!empty($pendingCount)): ?>
                    <span class="badge bg-warning text-dark ms-auto font-monospace rounded-pill"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/reminders/create.php" class="sidebar-link <?= ($activeMenu === 'create_reminder') ? 'active' : '' ?>">
                <i class="bi bi-calendar-plus-fill"></i>
                <span>Create Reminder</span>
            </a>
        </li>

        <li class="sidebar-heading">Telegram Users</li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/users/index.php" class="sidebar-link <?= ($activeMenu === 'users') ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                <span>Recipients List</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/users/add.php" class="sidebar-link <?= ($activeMenu === 'add_user') ? 'active' : '' ?>">
                <i class="bi bi-person-plus-fill"></i>
                <span>Add Recipient</span>
            </a>
        </li>

        <li class="sidebar-heading">Logs & Monitoring</li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/messages/logs.php" class="sidebar-link <?= ($activeMenu === 'logs') ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i>
                <span>Message Logs</span>
            </a>
        </li>

        <li class="sidebar-heading">Configuration</li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/settings.php" class="sidebar-link <?= ($activeMenu === 'settings') ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i>
                <span>Bot & Settings</span>
            </a>
        </li>

        <?php if (is_superadmin()): ?>
        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/admins.php" class="sidebar-link <?= ($activeMenu === 'admins') ? 'active' : '' ?>">
                <i class="bi bi-shield-lock-fill"></i>
                <span>Admin Accounts</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <!-- Sidebar Footer Widget -->
    <div class="sidebar-footer">
        <div class="d-flex align-items-center justify-content-between text-white-50 small mb-2">
            <span><i class="bi bi-cpu me-1"></i>Cron Status</span>
            <span class="text-success fw-bold">1-Min Interval</span>
        </div>
        <div class="d-grid">
            <a href="<?= BASE_URL ?>/admin/logout.php" class="btn btn-sm btn-outline-danger border-0 text-start d-flex align-items-center gap-2">
                <i class="bi bi-box-arrow-left"></i>
                <span>Sign Out</span>
            </a>
        </div>
    </div>
</aside>
