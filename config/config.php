<?php
/**
 * Telegram Reminder Management System
 * Main System Configuration & Bootstrap
 */

declare(strict_types=1);

// Start Output Buffering to prevent header already sent issues
if (!ob_get_level()) {
    ob_start();
}

// Error reporting settings (Development / Production toggle)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define directory constants
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Session configuration (Must be before session_start)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    
    // Set custom session name
    session_name('TRMS_SESSION');
    session_start();
}

// Database Connection Constants (can be edited for cPanel or custom environment)
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_NAME')) define('DB_NAME', 'telegram_reminder_db');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_PORT')) define('DB_PORT', 3306);

// Determine Base URL dynamically
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? '') == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Find the relative path from DOCUMENT_ROOT to ROOT_PATH
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
    $appRoot = str_replace('\\', '/', ROOT_PATH);
    
    if (!empty($docRoot) && strpos($appRoot, $docRoot) === 0) {
        $relativePath = substr($appRoot, strlen($docRoot));
        $baseUrl = $protocol . $host . rtrim($relativePath, '/');
    } else {
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $baseUrl = $protocol . $host . rtrim($scriptDir, '/');
    }
    
    define('BASE_URL', rtrim($baseUrl, '/'));
}

// Set Default Timezone (overridden by database setting if available)
date_default_timezone_set('Asia/Kolkata');

// Include Database handler
require_once CONFIG_PATH . '/database.php';

// Include Common Helper Functions
require_once INCLUDES_PATH . '/functions.php';

// Include Authentication Handler
require_once INCLUDES_PATH . '/auth.php';

// Apply system settings from Database if available
try {
    $db = get_db();
    if ($db) {
        $tz = get_setting('timezone');
        if ($tz && in_array($tz, timezone_identifiers_list(), true)) {
            date_default_timezone_set($tz);
        }
        $appName = get_setting('app_name');
        define('APP_NAME', $appName ?: 'Telegram Reminder Management System');
    } else {
        define('APP_NAME', 'Telegram Reminder Management System');
    }
} catch (Throwable $e) {
    define('APP_NAME', 'Telegram Reminder Management System');
}
