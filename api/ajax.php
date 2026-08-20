<?php
/**
 * Telegram Reminder Management System
 * AJAX Request Handler & API Endpoints
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/telegram.php';

// Authentication check for AJAX
if (!is_logged_in()) {
    json_response(['success' => false, 'error' => 'Unauthorized access. Please log in.'], 401);
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');
$db = get_db();

switch ($action) {
    // -------------------------------------------------------------------------
    // 1. Test Bot API Token
    // -------------------------------------------------------------------------
    case 'test_bot':
        $token = trim($_POST['bot_token'] ?? '');
        $tele = new TelegramService($token ?: null);
        $res = $tele->getMe();

        if ($res['success']) {
            if (!empty($token)) {
                set_setting('bot_token', $token);
                if (!empty($res['username'])) {
                    set_setting('bot_username', $res['username']);
                }
            }
            json_response([
                'success' => true,
                'bot' => [
                    'id' => $res['id'],
                    'username' => $res['username'],
                    'first_name' => $res['first_name']
                ]
            ]);
        } else {
            json_response(['success' => false, 'error' => $res['error']]);
        }
        break;

    // -------------------------------------------------------------------------
    // 2. Send Instant Test Message
    // -------------------------------------------------------------------------
    case 'send_test_message':
        $chatId = trim($_POST['chat_id'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($chatId) || empty($message)) {
            json_response(['success' => false, 'error' => 'Chat ID and Message cannot be empty.']);
        }

        $tele = new TelegramService();
        $res = $tele->sendMessage($chatId, $message, 'HTML');

        // Log this test attempt
        if ($db) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO message_logs (reminder_id, chat_id, recipient_name, message_text, status, error_message, telegram_message_id, sent_time)
                    VALUES (NULL, :cid, 'Direct Test', :msg, :status, :err, :tmid, NOW())
                ");
                $stmt->execute([
                    'cid' => $chatId,
                    'msg' => $message,
                    'status' => $res['success'] ? 'sent' : 'failed',
                    'err' => $res['error'] ?? null,
                    'tmid' => $res['message_id'] ?? null
                ]);
            } catch (Throwable $e) {}
        }

        if ($res['success']) {
            json_response([
                'success' => true,
                'message_id' => $res['message_id'],
                'message' => 'Message sent successfully!'
            ]);
        } else {
            json_response([
                'success' => false,
                'error' => $res['error']
            ]);
        }
        break;

    // -------------------------------------------------------------------------
    // 3. Discover Chat IDs from Recent Bot Incoming Updates
    // -------------------------------------------------------------------------
    case 'get_recent_updates':
        $tele = new TelegramService();
        $res = $tele->getUpdates();

        if (!$res['success']) {
            json_response(['success' => false, 'error' => $res['error']]);
        }

        $discoveredUsers = [];
        $seenChatIds = [];

        foreach ($res['updates'] as $update) {
            $msg = $update['message'] ?? $update['edited_message'] ?? null;
            if ($msg && !empty($msg['chat']['id'])) {
                $cId = (string)$msg['chat']['id'];
                if (!isset($seenChatIds[$cId])) {
                    $seenChatIds[$cId] = true;
                    $fullName = trim(($msg['from']['first_name'] ?? '') . ' ' . ($msg['from']['last_name'] ?? ''));
                    $discoveredUsers[] = [
                        'chat_id' => $cId,
                        'name' => $fullName ?: ($msg['from']['username'] ?? 'Telegram User'),
                        'username' => $msg['from']['username'] ?? '',
                        'last_text' => $msg['text'] ?? ''
                    ];
                }
            }
        }

        if (empty($discoveredUsers)) {
            json_response([
                'success' => true,
                'users' => [],
                'message' => 'No incoming messages found yet. Ensure users have clicked /start in your bot chat.'
            ]);
        } else {
            json_response([
                'success' => true,
                'users' => $discoveredUsers
            ]);
        }
        break;

    // -------------------------------------------------------------------------
    // 4. Run Cron Job Immediately via AJAX
    // -------------------------------------------------------------------------
    case 'run_cron':
        // Execute internal cron runner logic
        require_once ROOT_PATH . '/cron/cron_engine.php';
        $cronResult = execute_cron_batch(true);
        json_response([
            'success' => true,
            'processed' => $cronResult['processed_reminders'] ?? 0,
            'messages_sent' => $cronResult['messages_sent'] ?? 0,
            'messages_failed' => $cronResult['messages_failed'] ?? 0,
            'message' => "Cron execution completed: {$cronResult['processed_reminders']} reminders processed ({$cronResult['messages_sent']} msgs sent, {$cronResult['messages_failed']} failed)."
        ]);
        break;

    default:
        json_response(['success' => false, 'error' => 'Unknown action requested.'], 400);
}
