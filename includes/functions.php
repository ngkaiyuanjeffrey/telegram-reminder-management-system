<?php
/**
 * Telegram Reminder Management System
 * Core Helper Functions & Utilities
 */

declare(strict_types=1);

/**
 * Escape HTML output to prevent XSS attacks
 */
function e(?string $string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize input data (recursively if array)
 */
function sanitize($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize($value);
        }
        return $data;
    }
    return is_string($data) ? trim($data) : $data;
}

/**
 * Generate or get current CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden CSRF input field
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify submitted CSRF token
 */
function verify_csrf(?string $token = null): bool {
    $token = $token ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash message for next page view
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Check if flash message exists
 */
function has_flash(): bool {
    return !empty($_SESSION['flash']);
}

/**
 * Get and clear flash message
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Display flash message HTML alert
 */
function display_flash(): string {
    $flash = get_flash();
    if (!$flash) {
        return '';
    }

    $type = e($flash['type']);
    $message = e($flash['message']);
    $icon = match($flash['type']) {
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-exclamation-triangle-fill',
        'warning' => 'bi-exclamation-circle-fill',
        default   => 'bi-info-circle-fill'
    };

    return <<<HTML
    <div class="alert alert-{$type} alert-dismissible fade show shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
        <i class="bi {$icon} fs-5 me-2 flex-shrink-0"></i>
        <div class="flex-grow-1">{$message}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
HTML;
}

/**
 * In-memory settings cache
 */
$GLOBALS['system_settings_cache'] = [];

/**
 * Get system setting value by key
 */
function get_setting(string $key, string $default = ''): string {
    global $system_settings_cache;

    if (isset($system_settings_cache[$key])) {
        return $system_settings_cache[$key];
    }

    $db = get_db();
    if (!$db) return $default;

    try {
        $stmt = $db->prepare("SELECT `setting_value` FROM `settings` WHERE `setting_key` = :key LIMIT 1");
        $stmt->execute(['key' => $key]);
        $val = $stmt->fetchColumn();
        $result = ($val !== false && $val !== null) ? (string)$val : $default;
        $system_settings_cache[$key] = $result;
        return $result;
    } catch (Throwable $e) {
        return $default;
    }
}

/**
 * Update or insert a system setting
 */
function set_setting(string $key, string $value): bool {
    global $system_settings_cache;
    $db = get_db();
    if (!$db) return false;

    try {
        $stmt = $db->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) 
                              VALUES (:key, :val) 
                              ON DUPLICATE KEY UPDATE `setting_value` = :val_update");
        $success = $stmt->execute([
            'key' => $key,
            'val' => $value,
            'val_update' => $value
        ]);
        if ($success) {
            $system_settings_cache[$key] = $value;
        }
        return $success;
    } catch (Throwable $e) {
        error_log("Failed to save setting [{$key}]: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all system settings as key-value pairs
 */
function get_all_settings(): array {
    $db = get_db();
    if (!$db) return [];

    try {
        $stmt = $db->query("SELECT `setting_key`, `setting_value` FROM `settings`");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Format standard SQL datetime to display format
 */
function format_datetime(?string $datetime, ?string $format = null): string {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }

    try {
        $dt = new DateTime($datetime);
        $fmt = $format ?: get_setting('date_format', 'Y-m-d H:i');
        return $dt->format($fmt);
    } catch (Exception $e) {
        return (string)$datetime;
    }
}

/**
 * Human-readable relative time (e.g. "5 mins ago", "in 2 hours")
 */
function time_ago(?string $datetime): string {
    if (!$datetime) return '-';

    try {
        $time = strtotime($datetime);
        $now = time();
        $diff = $time - $now;

        if (abs($diff) < 60) {
            return ($diff >= 0) ? 'in a few seconds' : 'just now';
        }

        $isFuture = $diff > 0;
        $diff = abs($diff);

        $units = [
            31536000 => 'year',
            2592000  => 'month',
            604800   => 'week',
            86400    => 'day',
            3600     => 'hour',
            60       => 'minute',
        ];

        foreach ($units as $secs => $unit) {
            if ($diff >= $secs) {
                $val = (int)floor($diff / $secs);
                $plural = $val > 1 ? 's' : '';
                return $isFuture ? "in {$val} {$unit}{$plural}" : "{$val} {$unit}{$plural} ago";
            }
        }
        return date('Y-m-d H:i', $time);
    } catch (Throwable $e) {
        return (string)$datetime;
    }
}

/**
 * Safe redirect
 */
function redirect(string $url): void {
    if (!headers_sent()) {
        header("Location: {$url}");
        exit;
    }
    // Fallback to JavaScript/Meta refresh if headers already sent
    echo "<script>window.location.href=" . json_encode($url) . ";</script>";
    echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "'></noscript>";
    exit;
}

/**
 * Check if request is AJAX
 */
function is_ajax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Generate stylish HTML status badge for reminders
 */
function get_status_badge(string $status): string {
    return match(strtolower($status)) {
        'pending' => '<span class="badge bg-warning text-dark px-2.5 py-1.5 rounded-pill"><i class="bi bi-clock me-1"></i>Pending</span>',
        'in_progress' => '<span class="badge bg-info text-dark px-2.5 py-1.5 rounded-pill"><i class="bi bi-arrow-repeat spin me-1"></i>Sending...</span>',
        'sent' => '<span class="badge bg-success px-2.5 py-1.5 rounded-pill"><i class="bi bi-check-circle me-1"></i>Sent</span>',
        'failed' => '<span class="badge bg-danger px-2.5 py-1.5 rounded-pill"><i class="bi bi-x-circle me-1"></i>Failed</span>',
        'partially_sent' => '<span class="badge bg-purple px-2.5 py-1.5 rounded-pill"><i class="bi bi-exclamation-circle me-1"></i>Partially Sent</span>',
        default => '<span class="badge bg-secondary px-2.5 py-1.5 rounded-pill">' . e(ucfirst($status)) . '</span>'
    };
}

/**
 * Get comprehensive system statistics
 */
function get_system_stats(): array {
    $db = get_db();
    $stats = [
        'total_reminders' => 0,
        'pending_reminders' => 0,
        'sent_reminders' => 0,
        'failed_reminders' => 0,
        'partially_sent_reminders' => 0,
        'total_users' => 0,
        'total_logs' => 0,
        'messages_sent_today' => 0,
        'messages_failed_today' => 0,
    ];

    if (!$db) return $stats;

    try {
        // Reminders status breakdown
        $stmt = $db->query("SELECT status, COUNT(*) as cnt FROM reminders GROUP BY status");
        while ($row = $stmt->fetch()) {
            $st = strtolower($row['status']);
            if (isset($stats[$st . '_reminders'])) {
                $stats[$st . '_reminders'] = (int)$row['cnt'];
            }
            $stats['total_reminders'] += (int)$row['cnt'];
        }

        // Total recipients
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
        $stats['total_users'] = (int)$stmt->fetchColumn();

        // Total logs
        $stmt = $db->query("SELECT COUNT(*) FROM message_logs");
        $stats['total_logs'] = (int)$stmt->fetchColumn();

        // Today's message logs
        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM message_logs WHERE DATE(sent_time) = :today GROUP BY status");
        $stmt->execute(['today' => $today]);
        while ($row = $stmt->fetch()) {
            if ($row['status'] === 'sent') {
                $stats['messages_sent_today'] = (int)$row['cnt'];
            } else if ($row['status'] === 'failed') {
                $stats['messages_failed_today'] = (int)$row['cnt'];
            }
        }
    } catch (Throwable $e) {
        error_log("Error calculating stats: " . $e->getMessage());
    }

    return $stats;
}
