<?php
/**
 * Telegram Reminder Management System
 * Telegram Bot API Client
 */

declare(strict_types=1);

class TelegramService {
    private string $botToken;
    private string $apiUrl = 'https://api.telegram.org/bot';

    public function __construct(?string $token = null) {
        $this->botToken = trim($token ?: get_setting('bot_token', ''));
    }

    /**
     * Set active bot token
     */
    public function setToken(string $token): void {
        $this->botToken = trim($token);
    }

    /**
     * Get active bot token
     */
    public function getToken(): string {
        return $this->botToken;
    }

    /**
     * Check if bot token is configured
     */
    public function hasToken(): bool {
        return !empty($this->botToken);
    }

    /**
     * Send a text message to a specific Telegram Chat ID
     * 
     * @param string|int $chatId Telegram Chat ID
     * @param string $message Text message content (supports HTML formatting)
     * @param string $parseMode 'HTML', 'Markdown', 'MarkdownV2' or empty
     * @param bool $disableWebPreview Disable link previews
     * @return array ['success' => bool, 'message_id' => ?string, 'error' => ?string, 'raw' => array]
     */
    public function sendMessage($chatId, string $message, string $parseMode = 'HTML', bool $disableWebPreview = false): array {
        if (!$this->hasToken()) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Telegram Bot Token is not configured. Please add it in Settings.',
                'raw' => []
            ];
        }

        $chatId = trim((string)$chatId);
        if (empty($chatId)) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Recipient Telegram Chat ID is missing.',
                'raw' => []
            ];
        }

        $params = [
            'chat_id' => $chatId,
            'text' => $message,
            'disable_web_page_preview' => $disableWebPreview
        ];

        if (!empty($parseMode)) {
            $params['parse_mode'] = $parseMode;
        }

        $response = $this->makeRequest('sendMessage', $params);

        if (!empty($response['ok']) && !empty($response['result']['message_id'])) {
            return [
                'success' => true,
                'message_id' => (string)$response['result']['message_id'],
                'error' => null,
                'raw' => $response
            ];
        }

        // If HTML parsing failed due to invalid tag, retry once as plain text
        if (!empty($response['description']) && str_contains($response['description'], "can't parse entities")) {
            unset($params['parse_mode']);
            $plainResponse = $this->makeRequest('sendMessage', $params);
            if (!empty($plainResponse['ok'])) {
                return [
                    'success' => true,
                    'message_id' => (string)$plainResponse['result']['message_id'],
                    'error' => null,
                    'raw' => $plainResponse
                ];
            }
        }

        $errorMsg = $response['description'] ?? ($response['error'] ?? 'Unknown Telegram API error');
        return [
            'success' => false,
            'message_id' => null,
            'error' => $errorMsg,
            'raw' => $response
        ];
    }

    /**
     * Verify Bot Token and retrieve bot identity details
     */
    public function getMe(): array {
        if (!$this->hasToken()) {
            return ['success' => false, 'error' => 'Bot Token is not configured.'];
        }

        $res = $this->makeRequest('getMe');
        if (!empty($res['ok']) && !empty($res['result'])) {
            return [
                'success' => true,
                'bot' => $res['result'],
                'username' => $res['result']['username'] ?? '',
                'first_name' => $res['result']['first_name'] ?? '',
                'id' => $res['result']['id'] ?? ''
            ];
        }

        return [
            'success' => false,
            'error' => $res['description'] ?? 'Failed to connect to Telegram Bot API.'
        ];
    }

    /**
     * Get recent incoming updates (messages, commands like /start)
     */
    public function getUpdates(int $offset = 0, int $limit = 30): array {
        if (!$this->hasToken()) {
            return ['success' => false, 'error' => 'Bot Token is not configured.', 'updates' => []];
        }

        $params = ['limit' => $limit];
        if ($offset > 0) {
            $params['offset'] = $offset;
        }

        $res = $this->makeRequest('getUpdates', $params);
        if (!empty($res['ok']) && isset($res['result'])) {
            return [
                'success' => true,
                'updates' => $res['result']
            ];
        }

        return [
            'success' => false,
            'error' => $res['description'] ?? 'Failed to fetch updates.',
            'updates' => []
        ];
    }

    /**
     * Set webhook URL for the bot
     */
    public function setWebhook(string $url): array {
        if (!$this->hasToken()) {
            return ['success' => false, 'error' => 'Bot Token missing.'];
        }
        $res = $this->makeRequest('setWebhook', ['url' => $url]);
        return [
            'success' => !empty($res['ok']),
            'description' => $res['description'] ?? ''
        ];
    }

    /**
     * Delete webhook (to enable getUpdates / polling mode)
     */
    public function deleteWebhook(): array {
        if (!$this->hasToken()) {
            return ['success' => false, 'error' => 'Bot Token missing.'];
        }
        $res = $this->makeRequest('deleteWebhook');
        return [
            'success' => !empty($res['ok']),
            'description' => $res['description'] ?? ''
        ];
    }

    /**
     * Low-level cURL execution helper
     */
    private function makeRequest(string $method, array $params = []): array {
        $url = $this->apiUrl . $this->botToken . '/' . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            return [
                'ok' => false,
                'error' => 'Network / cURL Error: ' . $curlError,
                'http_code' => $httpCode
            ];
        }

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'error' => 'Invalid JSON response from Telegram API (HTTP ' . $httpCode . ')',
                'raw_response' => $result
            ];
        }

        return $decoded;
    }
}

/**
 * Global helper function to send Telegram message as specified in requirement
 * sendTelegramMessage($chat_id, $message)
 */
function sendTelegramMessage($chat_id, string $message, string $parseMode = 'HTML', ?string $botToken = null): array {
    $service = new TelegramService($botToken);
    return $service->sendMessage($chat_id, $message, $parseMode);
}
