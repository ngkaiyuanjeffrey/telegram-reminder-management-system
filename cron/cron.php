<?php
/**
 * Telegram Reminder Management System
 * 1-Minute Cron Job Execution Entry Point
 * Compatible with cPanel CLI, cURL, Wget, and Web Browser
 */

declare(strict_types=1);

require_once __DIR__ . '/cron_engine.php';

$isCli = (php_sapi_name() === 'cli' || empty($_SERVER['REMOTE_ADDR']));

// If triggered via HTTP / Web, check security key
if (!$isCli) {
    $providedKey = trim($_GET['key'] ?? $_POST['key'] ?? '');
    $configuredKey = get_setting('cron_secret_key', '');

    if (empty($configuredKey) || !hash_equals($configuredKey, $providedKey)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Access Denied: Invalid or missing cron secret key (?key=YOUR_SECRET_KEY).'
        ], JSON_PRETTY_PRINT);
        exit;
    }
}

// Execute cron run
$result = execute_cron_batch(false);

// Format output
if ($isCli) {
    echo "=======================================================\n";
    echo "Telegram Reminder Cron Job - " . $result['timestamp'] . "\n";
    echo "=======================================================\n";
    echo "Reminders Processed: " . $result['processed_reminders'] . "\n";
    echo "Messages Sent:       " . $result['messages_sent'] . "\n";
    echo "Messages Failed:     " . $result['messages_failed'] . "\n";
    echo "Execution Time:      " . $result['execution_time_seconds'] . "s\n";
    if (!empty($result['error'])) {
        echo "Error:               " . $result['error'] . "\n";
    }
    echo "=======================================================\n";
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => empty($result['error']),
        'report' => $result
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
