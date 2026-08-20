<?php
/**
 * Telegram Reminder Management System
 * Telegram Webhook Handler
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/telegram.php';

// Fetch raw JSON payload from Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    http_response_code(200);
    echo "OK - No payload";
    exit;
}

$db = get_db();
$message = $update['message'] ?? null;

if ($message && !empty($message['chat']['id'])) {
    $chatId = (string)$message['chat']['id'];
    $text = trim($message['text'] ?? '');
    $firstName = $message['from']['first_name'] ?? '';
    $lastName = $message['from']['last_name'] ?? '';
    $username = $message['from']['username'] ?? '';
    $fullName = trim($firstName . ' ' . $lastName) ?: ($username ?: 'Telegram User');

    // Auto-register user in DB if /start is received
    if (str_starts_with($text, '/start') && $db) {
        try {
            $stmt = $db->prepare("
                INSERT INTO users (name, chat_id, username, status)
                VALUES (:n, :c, :u, 'active')
                ON DUPLICATE KEY UPDATE name = :n2, username = :u2, status = 'active'
            ");
            $stmt->execute([
                'n' => $fullName,
                'c' => $chatId,
                'u' => $username,
                'n2' => $fullName,
                'u2' => $username
            ]);

            // Send confirmation response to user
            $tele = new TelegramService();
            $reply = "👋 <b>Hello " . htmlspecialchars($fullName) . "!</b>\n\n" .
                     "✅ You have successfully connected to the <b>" . htmlspecialchars(APP_NAME) . "</b>.\n\n" .
                     "📌 <b>Your Telegram Chat ID:</b> <code>" . htmlspecialchars($chatId) . "</code>\n\n" .
                     "You are now ready to receive scheduled notifications and reminders!";
            $tele->sendMessage($chatId, $reply, 'HTML');

        } catch (Throwable $e) {
            error_log("Webhook user registration error: " . $e->getMessage());
        }
    }
}

http_response_code(200);
echo "OK";
