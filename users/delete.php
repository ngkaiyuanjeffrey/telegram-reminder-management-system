<?php
/**
 * Telegram Reminder Management System
 * Delete Recipient Action
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
$token = $_GET['token'] ?? '';

if (!verify_csrf($token) || $id <= 0) {
    set_flash('danger', 'Invalid request or expired security token.');
    redirect(BASE_URL . '/users/index.php');
}

$db = get_db();
if ($db) {
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        set_flash('success', 'Recipient deleted successfully.');
    } catch (Throwable $e) {
        set_flash('danger', 'Failed to delete recipient: ' . $e->getMessage());
    }
}

redirect(BASE_URL . '/users/index.php');
