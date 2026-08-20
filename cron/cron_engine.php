<?php
/**
 * Telegram Reminder Management System
 * Core Cron Processing Engine
 */

declare(strict_types=1);

// Prevent script from aborting during large batch delivery
set_time_limit(300);

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/telegram.php';

/**
 * Execute cron batch processing for all pending reminders due for delivery
 * 
 * @param bool $isInternal Whether invoked internally or via CLI/HTTP
 * @return array Execution summary report
 */
function execute_cron_batch(bool $isInternal = false): array {
    $db = get_db();
    $startTime = microtime(true);
    
    $report = [
        'timestamp' => date('Y-m-d H:i:s'),
        'processed_reminders' => 0,
        'messages_sent' => 0,
        'messages_failed' => 0,
        'reminders_detail' => [],
        'execution_time_seconds' => 0
    ];

    if (!$db) {
        $report['error'] = 'Database connection unavailable.';
        return $report;
    }

    try {
        // Step 1: Find all due pending reminders where scheduled_time <= NOW()
        $stmt = $db->prepare("
            SELECT id, title, scheduled_time, delay_seconds 
            FROM reminders 
            WHERE scheduled_time <= NOW() AND status = 'pending'
            ORDER BY scheduled_time ASC
        ");
        $stmt->execute();
        $dueReminders = $stmt->fetchAll();

        if (empty($dueReminders)) {
            $report['execution_time_seconds'] = round(microtime(true) - $startTime, 4);
            log_cron_execution($report);
            return $report;
        }

        $telegram = new TelegramService();
        $logInsertStmt = $db->prepare("
            INSERT INTO message_logs (reminder_id, chat_id, recipient_name, message_text, status, error_message, telegram_message_id, sent_time)
            VALUES (:rid, :cid, :rname, :msg, :status, :err, :tmid, NOW())
        ");

        foreach ($dueReminders as $reminder) {
            $reminderId = (int)$reminder['id'];
            $reminderTitle = $reminder['title'];
            $delaySeconds = max(1, (int)($reminder['delay_seconds'] ?: 2));

            // Step 2: Lock reminder status to in_progress to prevent duplicate processing
            $db->prepare("UPDATE reminders SET status = 'in_progress' WHERE id = :id")->execute(['id' => $reminderId]);

            // Step 3: Fetch messages ordered by sort_order
            $msgStmt = $db->prepare("SELECT * FROM reminder_messages WHERE reminder_id = :id ORDER BY sort_order ASC");
            $msgStmt->execute(['id' => $reminderId]);
            $messages = $msgStmt->fetchAll();

            // Step 4: Fetch recipients
            $recStmt = $db->prepare("
                SELECT rr.*, u.name as user_name
                FROM reminder_recipients rr
                LEFT JOIN users u ON rr.user_id = u.id
                WHERE rr.reminder_id = :id
            ");
            $recStmt->execute(['id' => $reminderId]);
            $recipients = $recStmt->fetchAll();

            $reminderSuccess = 0;
            $reminderFailed = 0;

            if (empty($messages) || empty($recipients)) {
                $db->prepare("UPDATE reminders SET status = 'failed' WHERE id = :id")->execute(['id' => $reminderId]);
                $report['reminders_detail'][] = [
                    'id' => $reminderId,
                    'title' => $reminderTitle,
                    'status' => 'failed',
                    'reason' => empty($messages) ? 'No messages in reminder' : 'No recipients assigned'
                ];
                $report['processed_reminders']++;
                continue;
            }

            // Step 5: Sequential Delivery Loop
            foreach ($recipients as $rec) {
                $chatId = $rec['chat_id'];
                $recipientName = $rec['user_name'] ?: 'Recipient';

                foreach ($messages as $mIdx => $msg) {
                    $msgText = $msg['message_text'];

                    // Send Telegram Message
                    $sendRes = $telegram->sendMessage($chatId, $msgText, 'HTML');

                    if ($sendRes['success']) {
                        $reminderSuccess++;
                        $report['messages_sent']++;
                        $logInsertStmt->execute([
                            'rid' => $reminderId,
                            'cid' => $chatId,
                            'rname' => $recipientName,
                            'msg' => $msgText,
                            'status' => 'sent',
                            'err' => null,
                            'tmid' => $sendRes['message_id']
                        ]);
                    } else {
                        $reminderFailed++;
                        $report['messages_failed']++;
                        $logInsertStmt->execute([
                            'rid' => $reminderId,
                            'cid' => $chatId,
                            'rname' => $recipientName,
                            'msg' => $msgText,
                            'status' => 'failed',
                            'err' => $sendRes['error'],
                            'tmid' => null
                        ]);
                    }

                    // Anti-flood pause between sequential messages (except after last message)
                    if ($mIdx < count($messages) - 1 && $delaySeconds > 0) {
                        sleep($delaySeconds);
                    }
                }
            }

            // Step 6: Compute final reminder status
            $finalStatus = 'sent';
            if ($reminderFailed > 0 && $reminderSuccess > 0) {
                $finalStatus = 'partially_sent';
            } elseif ($reminderFailed > 0 && $reminderSuccess === 0) {
                $finalStatus = 'failed';
            }

            $db->prepare("UPDATE reminders SET status = :st WHERE id = :id")->execute([
                'st' => $finalStatus,
                'id' => $reminderId
            ]);

            $report['processed_reminders']++;
            $report['reminders_detail'][] = [
                'id' => $reminderId,
                'title' => $reminderTitle,
                'status' => $finalStatus,
                'sent' => $reminderSuccess,
                'failed' => $reminderFailed
            ];
        }

    } catch (Throwable $e) {
        $report['error'] = $e->getMessage();
        error_log("Cron Execution Exception: " . $e->getMessage());
    }

    $report['execution_time_seconds'] = round(microtime(true) - $startTime, 4);
    log_cron_execution($report);

    return $report;
}

/**
 * Write summary entry to logs/cron.log
 */
function log_cron_execution(array $report): void {
    $logFile = LOGS_PATH . '/cron.log';
    $logLine = sprintf(
        "[%s] Processed: %d reminders | Sent: %d msgs | Failed: %d msgs | Time: %.4fs%s\n",
        $report['timestamp'],
        $report['processed_reminders'],
        $report['messages_sent'],
        $report['messages_failed'],
        $report['execution_time_seconds'],
        !empty($report['error']) ? " | ERROR: {$report['error']}" : ""
    );

    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}
