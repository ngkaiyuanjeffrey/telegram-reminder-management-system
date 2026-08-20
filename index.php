<?php
/**
 * Telegram Reminder Management System
 * Root Application Router
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$db = get_db();

// If database is not yet installed or connection failed, route to installer
if (!$db) {
    redirect(BASE_URL . '/install.php');
}

// If authenticated, go to admin dashboard, otherwise go to login
if (is_logged_in()) {
    redirect(BASE_URL . '/admin/index.php');
} else {
    redirect(BASE_URL . '/admin/login.php');
}
