<?php
/**
 * Telegram Reminder Management System
 * Clone / Duplicate Reminder Action
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
$token = $_GET['token'] ?? '';

if (!verify_csrf($token) || $id <= 0) {
    set_flash('danger', 'Invalid request or expired security token.');
    redirect(BASE_URL . '/reminders/index.php');
}

$db = get_db();
if (!$db) {
    set_flash('danger', 'Database unavailable.');
    redirect(BASE_URL . '/reminders/index.php');
}

try {
    $db->beginTransaction();

    // 1. Fetch original
    $stmt = $db->prepare("SELECT * FROM reminders WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $orig = $stmt->fetch();

    if (!$orig) {
        throw new Exception("Original reminder not found.");
    }

    // 2. Insert cloned reminder
    $newTitle = "(Copy) " . $orig['title'];
    $newScheduledTime = date('Y-m-d H:i:00', strtotime('+15 minutes'));
    $adminId = $_SESSION['admin_id'] ?? null;

    $ins = $db->prepare("
        INSERT INTO reminders (title, description, scheduled_time, status, delay_seconds, created_by)
        VALUES (:t, :d, :st, 'pending', :del, :cb)
    ");
    $ins->execute([
        't' => $newTitle,
        'd' => $orig['description'],
        'st' => $newScheduledTime,
        'del' => $orig['delay_seconds'],
        'cb' => $adminId
    ]);
    $newReminderId = (int)$db->lastInsertId();

    // 3. Copy messages
    $msgStmt = $db->prepare("SELECT * FROM reminder_messages WHERE reminder_id = :id ORDER BY sort_order ASC");
    $msgStmt->execute(['id' => $id]);
    $origMsgs = $msgStmt->fetchAll();

    $insMsg = $db->prepare("INSERT INTO reminder_messages (reminder_id, message_text, sort_order) VALUES (:rid, :txt, :ord)");
    foreach ($origMsgs as $m) {
        $insMsg->execute([
            'rid' => $newReminderId,
            'txt' => $m['message_text'],
            'ord' => $m['sort_order']
        ]);
    }

    // 4. Copy recipients
    $recStmt = $db->prepare("SELECT * FROM reminder_recipients WHERE reminder_id = :id");
    $recStmt->execute(['id' => $id]);
    $origRecs = $recStmt->fetchAll();

    $insRec = $db->prepare("INSERT INTO reminder_recipients (reminder_id, user_id, chat_id) VALUES (:rid, :uid, :cid)");
    foreach ($origRecs as $r) {
        $insRec->execute([
            'rid' => $newReminderId,
            'uid' => $r['user_id'],
            'cid' => $r['chat_id']
        ]);
    }

    $db->commit();

    set_flash('success', "Reminder duplicated successfully as '{$newTitle}'.");
    redirect(BASE_URL . "/reminders/view.php?id={$newReminderId}");
} catch (Throwable $e) {
    $db->rollBack();
    set_flash('danger', 'Failed to duplicate reminder: ' . $e->getMessage());
    redirect(BASE_URL . '/reminders/index.php');
}
