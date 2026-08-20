<?php
/**
 * Telegram Reminder Management System
 * Admin Logout Action
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

logout_admin();

set_flash('info', 'You have been signed out safely. Have a great day!');
redirect(BASE_URL . '/admin/login.php');
