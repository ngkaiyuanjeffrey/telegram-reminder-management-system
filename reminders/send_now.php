<?php
/**
 * Telegram Reminder Management System
 * Manual Immediate Dispatch Action
 */

declare(strict_types=1);

// Prevent script from aborting early on long message batches
set_time_limit(300);

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/telegram.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
$token = $_GET['token'] ?? '';

if (!verify_csrf($token) || $id <= 0) {
    set_flash('danger', 'Invalid request or expired security token.');
    redirect(BASE_URL . '/reminders/index.php');
}

$db = get_db();
if (!$db) {
    set_flash('danger', 'Database connection error.');
    redirect(BASE_URL . '/reminders/index.php');
}

try {
    // 1. Fetch Reminder
    $stmt = $db->prepare("SELECT * FROM reminders WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $reminder = $stmt->fetch();

    if (!$reminder) {
        set_flash('danger', 'Reminder not found.');
        redirect(BASE_URL . '/reminders/index.php');
    }

    // Mark as in_progress
    $db->prepare("UPDATE reminders SET status = 'in_progress' WHERE id = :id")->execute(['id' => $id]);

    // 2. Fetch Messages
    $msgStmt = $db->prepare("SELECT * FROM reminder_messages WHERE reminder_id = :id ORDER BY sort_order ASC");
    $msgStmt->execute(['id' => $id]);
    $messages = $msgStmt->fetchAll();

    if (empty($messages)) {
        $db->prepare("UPDATE reminders SET status = 'failed' WHERE id = :id")->execute(['id' => $id]);
        set_flash('danger', 'This reminder has no messages configured.');
        redirect(BASE_URL . "/reminders/view.php?id={$id}");
    }

    // 3. Fetch Recipients
    $recStmt = $db->prepare("
        SELECT rr.*, u.name as user_name
        FROM reminder_recipients rr
        LEFT JOIN users u ON rr.user_id = u.id
        WHERE rr.reminder_id = :id
    ");
    $recStmt->execute(['id' => $id]);
    $recipients = $recStmt->fetchAll();

    if (empty($recipients)) {
        $db->prepare("UPDATE reminders SET status = 'failed' WHERE id = :id")->execute(['id' => $id]);
        set_flash('danger', 'No recipients are assigned to this reminder.');
        redirect(BASE_URL . "/reminders/view.php?id={$id}");
    }

    $telegram = new TelegramService();
    $delaySeconds = (int)($reminder['delay_seconds'] ?: 2);

    $totalAttempts = 0;
    $successCount = 0;
    $failCount = 0;

    $logStmt = $db->prepare("
        INSERT INTO message_logs (reminder_id, chat_id, recipient_name, message_text, status, error_message, telegram_message_id, sent_time)
        VALUES (:rid, :cid, :rname, :msg, :status, :err, :tmid, NOW())
    ");

    // Loop through each recipient
    foreach ($recipients as $rec) {
        $chatId = $rec['chat_id'];
        $recipientName = $rec['user_name'] ?: 'Recipient';

        // Send messages in exact sequence
        foreach ($messages as $mIdx => $msg) {
            $totalAttempts++;
            $msgText = $msg['message_text'];

            $res = $telegram->sendMessage($chatId, $msgText, 'HTML');

            if ($res['success']) {
                $successCount++;
                $logStmt->execute([
                    'rid' => $id,
                    'cid' => $chatId,
                    'rname' => $recipientName,
                    'msg' => $msgText,
                    'status' => 'sent',
                    'err' => null,
                    'tmid' => $res['message_id']
                ]);
            } else {
                $failCount++;
                $logStmt->execute([
                    'rid' => $id,
                    'cid' => $chatId,
                    'rname' => $recipientName,
                    'msg' => $msgText,
                    'status' => 'failed',
                    'err' => $res['error'],
                    'tmid' => null
                ]);
            }

            // Anti-flood pause between sequential messages (except after last message)
            if ($mIdx < count($messages) - 1 && $delaySeconds > 0) {
                sleep($delaySeconds);
            }
        }
    }

    // Determine overall reminder status
    $finalStatus = 'sent';
    if ($failCount > 0 && $successCount > 0) {
        $finalStatus = 'partially_sent';
    } elseif ($failCount > 0 && $successCount === 0) {
        $finalStatus = 'failed';
    }

    $updateStatus = $db->prepare("UPDATE reminders SET status = :st WHERE id = :id");
    $updateStatus->execute(['st' => $finalStatus, 'id' => $id]);

    if ($finalStatus === 'sent') {
        set_flash('success', "All messages sent successfully! ({$successCount} sent)");
    } elseif ($finalStatus === 'partially_sent') {
        set_flash('warning', "Reminder executed with partial delivery: {$successCount} sent, {$failCount} failed.");
    } else {
        set_flash('danger', "All message deliveries failed ({$failCount} failed). Check Telegram Bot token or Chat IDs.");
    }

} catch (Throwable $e) {
    if ($db) {
        $db->prepare("UPDATE reminders SET status = 'failed' WHERE id = :id")->execute(['id' => $id]);
    }
    set_flash('danger', 'Error sending reminder: ' . $e->getMessage());
}

redirect(BASE_URL . "/reminders/view.php?id={$id}");
