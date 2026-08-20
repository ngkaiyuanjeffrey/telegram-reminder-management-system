<?php
/**
 * Telegram Reminder Management System
 * System & Bot Configuration Settings
 */

declare(strict_types=1);

$pageTitle = 'Bot & System Settings';
$activeMenu = 'settings';

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/header.php';

$error = null;
$success = null;
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please submit the form again.';
    } else {
        $bot_token = trim($_POST['bot_token'] ?? '');
        $bot_username = trim($_POST['bot_username'] ?? '');
        $default_delay = max(1, (int)($_POST['default_delay_seconds'] ?? 2));
        $timezone = trim($_POST['timezone'] ?? 'Asia/Kolkata');
        $cron_key = trim($_POST['cron_secret_key'] ?? 'cron_sec_' . bin2hex(random_bytes(6)));
        $app_name = trim($_POST['app_name'] ?? 'Telegram Reminder Management System');

        set_setting('bot_token', $bot_token);
        set_setting('bot_username', $bot_username);
        set_setting('default_delay_seconds', (string)$default_delay);
        set_setting('timezone', $timezone);
        set_setting('cron_secret_key', $cron_key);
        set_setting('app_name', $app_name);

        // Auto verify bot token and fetch bot username if token provided
        if (!empty($bot_token)) {
            $tele = new TelegramService($bot_token);
            $botInfo = $tele->getMe();
            if ($botInfo['success']) {
                $fetchedUsername = $botInfo['username'] ?? '';
                if (!empty($fetchedUsername)) {
                    set_setting('bot_username', $fetchedUsername);
                }
                set_flash('success', "Settings saved! Bot verified successfully: @" . ($fetchedUsername ?: 'Bot'));
            } else {
                set_flash('warning', "Settings saved, but Telegram verification returned: " . ($botInfo['error'] ?? 'Unknown error'));
            }
        } else {
            set_flash('success', 'System settings updated successfully.');
        }

        redirect(BASE_URL . '/admin/settings.php');
    }
}

$botToken = get_setting('bot_token', '');
$botUsername = get_setting('bot_username', '');
$defaultDelay = (int)get_setting('default_delay_seconds', '2');
$currentTimezone = get_setting('timezone', 'Asia/Kolkata');
$cronKey = get_setting('cron_secret_key', 'cron_sec_' . bin2hex(random_bytes(6)));
$appName = get_setting('app_name', 'Telegram Reminder Management System');

// Generate cron commands
$cronUrl = BASE_URL . "/cron/cron.php?key=" . urlencode($cronKey);
$serverCronPath = ROOT_PATH . "/cron/cron.php";
$cpanelCliCommand = "* * * * * php " . $serverCronPath . " >/dev/null 2>&1";
$cpanelCurlCommand = "* * * * * curl -s \"" . $cronUrl . "\" >/dev/null 2>&1";
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-gear-fill text-primary"></i> Bot & System Settings
        </h1>
        <p class="page-subtitle">Configure Telegram Bot API token, message delays, timezone, and cron scheduler</p>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Settings Form -->
    <div class="col-lg-7">
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <h5 class="card-title-custom">
                    <i class="bi bi-sliders text-primary"></i> General & Bot Configuration
                </h5>
            </div>
            <div class="p-4">
                <form method="POST" action="<?= BASE_URL ?>/admin/settings.php">
                    <?= csrf_field() ?>

                    <!-- Application Name -->
                    <div class="mb-4">
                        <label for="appNameInput" class="form-label small fw-bold text-muted">Application Name</label>
                        <input type="text" class="form-control" id="appNameInput" name="app_name" value="<?= e($appName) ?>" required>
                    </div>

                    <!-- Telegram Bot Token -->
                    <div class="mb-4">
                        <label for="inputBotToken" class="form-label small fw-bold text-muted d-flex align-items-center justify-content-between">
                            <span>Telegram Bot Token <span class="text-danger">*</span></span>
                            <a href="https://t.me/BotFather" target="_blank" class="text-decoration-none small text-primary">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Get from @BotFather
                            </a>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-key"></i></span>
                            <input type="text" class="form-control font-monospace" id="inputBotToken" name="bot_token" value="<?= e($botToken) ?>" placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ">
                            <button type="button" class="btn btn-outline-primary" id="btnTestBotConnection">
                                <i class="bi bi-lightning-charge"></i> Test Bot
                            </button>
                        </div>
                        <div class="form-text small mt-1.5" id="botStatusBadge">
                            <?php if (!empty($botToken)): ?>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-check-circle-fill text-success me-1"></i> Token saved <?= !empty($botUsername) ? "(@{$botUsername})" : "" ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border">Token not set</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Bot Username (Optional) -->
                    <div class="mb-4">
                        <label for="botUsernameInput" class="form-label small fw-bold text-muted">Bot Username (without @)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">@</span>
                            <input type="text" class="form-control" id="botUsernameInput" name="bot_username" value="<?= e($botUsername) ?>" placeholder="MyReminderBot">
                        </div>
                    </div>

                    <!-- Delay Between Messages -->
                    <div class="mb-4">
                        <label for="defaultDelayInput" class="form-label small fw-bold text-muted">Default Anti-Flood Delay Between Sequential Messages (Seconds)</label>
                        <div class="input-group" style="max-width: 260px;">
                            <input type="number" class="form-control" id="defaultDelayInput" name="default_delay_seconds" min="1" max="10" value="<?= $defaultDelay ?>" required>
                            <span class="input-group-text bg-light text-muted">seconds</span>
                        </div>
                        <div class="form-text small text-muted mt-1">
                            Recommended: <strong>2 - 3 seconds</strong>. Prevents Telegram API rate limits when sending multiple messages per reminder.
                        </div>
                    </div>

                    <!-- Timezone -->
                    <div class="mb-4">
                        <label for="timezoneSelect" class="form-label small fw-bold text-muted">System Timezone</label>
                        <select class="form-select" id="timezoneSelect" name="timezone" required>
                            <?php
                            $commonTimezones = [
                                'Asia/Kolkata' => 'Asia/Kolkata (IST +05:30)',
                                'UTC' => 'UTC (+00:00)',
                                'America/New_York' => 'America/New_York (EST/EDT -05:00)',
                                'America/Chicago' => 'America/Chicago (CST -06:00)',
                                'America/Los_Angeles' => 'America/Los_Angeles (PST -08:00)',
                                'Europe/London' => 'Europe/London (GMT/BST +00:00)',
                                'Europe/Paris' => 'Europe/Paris (CET +01:00)',
                                'Europe/Berlin' => 'Europe/Berlin (CET +01:00)',
                                'Asia/Dubai' => 'Asia/Dubai (GST +04:00)',
                                'Asia/Singapore' => 'Asia/Singapore (SGT +08:00)',
                                'Asia/Tokyo' => 'Asia/Tokyo (JST +09:00)',
                                'Australia/Sydney' => 'Australia/Sydney (AEST +10:00)'
                            ];
                            foreach ($commonTimezones as $tzVal => $tzLabel):
                            ?>
                                <option value="<?= $tzVal ?>" <?= ($currentTimezone === $tzVal) ? 'selected' : '' ?>><?= $tzLabel ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small text-muted mt-1">
                            Current Server Time: <strong><?= date('Y-m-d H:i:s') ?></strong>
                        </div>
                    </div>

                    <!-- Cron Secret Key -->
                    <div class="mb-4">
                        <label for="cronKeyInput" class="form-label small fw-bold text-muted">Cron Job Secret Key (Web Trigger Protection)</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" id="cronKeyInput" name="cron_secret_key" value="<?= e($cronKey) ?>" required>
                            <button type="button" class="btn btn-light border" onclick="$('#cronKeyInput').val('cron_sec_' + Math.random().toString(36).substring(2, 12));">
                                <i class="bi bi-arrow-repeat"></i> Generate
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Save All Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Cron Job Commands & Guides -->
    <div class="col-lg-5">
        <!-- Cron Job Integration Box -->
        <div class="card-custom mb-4" id="cron">
            <div class="card-header-custom bg-dark text-white">
                <h5 class="card-title-custom text-white">
                    <i class="bi bi-clock-history text-warning"></i> Cron Job Setup (1-Minute Interval)
                </h5>
                <span class="badge bg-warning text-dark font-monospace">* * * * *</span>
            </div>
            <div class="p-4">
                <p class="small text-muted mb-3">
                    To automate message delivery, configure a cron job on your server or cPanel to execute every <strong>1 minute</strong>.
                </p>

                <!-- cPanel CLI Command -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">Option 1: cPanel Standard CLI Command (Recommended)</label>
                    <div class="cron-code-box mb-2">
                        <?= e($cpanelCliCommand) ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill btn-copy" data-copy="<?= e($cpanelCliCommand) ?>">
                        <i class="bi bi-clipboard me-1"></i> Copy CLI Command
                    </button>
                </div>

                <hr class="my-3">

                <!-- Web Cron / cURL URL -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">Option 2: Web URL / cURL Cron (Shared Hosting / External Cron)</label>
                    <div class="cron-code-box mb-2">
                        <?= e($cpanelCurlCommand) ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill btn-copy" data-copy="<?= e($cpanelCurlCommand) ?>">
                        <i class="bi bi-clipboard me-1"></i> Copy cURL Command
                    </button>
                </div>

                <hr class="my-3">

                <!-- Manual Web Trigger Test -->
                <div>
                    <label class="form-label small fw-bold text-dark">Direct Web URL for Testing</label>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm font-monospace" value="<?= e($cronUrl) ?>" readonly>
                        <a href="<?= e($cronUrl) ?>" target="_blank" class="btn btn-sm btn-primary">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Trigger
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Incoming Updates / Discover Chat IDs Helper -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h5 class="card-title-custom">
                    <i class="bi bi-person-badge text-info"></i> Discover Telegram Chat IDs
                </h5>
            </div>
            <div class="p-4">
                <p class="small text-muted mb-3">
                    Ask your users to start a chat with your bot <strong class="text-dark"><?= !empty($botUsername) ? "@" . e($botUsername) : "(your bot)" ?></strong> and click <code>/start</code>. Then click the button below to retrieve their Chat IDs instantly.
                </p>
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill w-100" id="btnFetchRecentChatIds">
                    <i class="bi bi-search me-1"></i> Check Recent Bot Messages
                </button>

                <div id="recentChatIdsResults" class="mt-3 small" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('#btnFetchRecentChatIds').on('click', function () {
        const btn = $(this);
        const origText = btn.html();
        const resultsBox = $('#recentChatIdsResults');

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Checking Telegram...');
        resultsBox.show().html('<div class="text-center py-2 text-muted">Fetching recent updates from Telegram...</div>');

        $.ajax({
            url: '<?= BASE_URL ?>/api/ajax.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'get_recent_updates',
                csrf_token: window.CSRF_TOKEN
            },
            success: function (res) {
                btn.prop('disabled', false).html(origText);
                if (res.success && res.users && res.users.length > 0) {
                    let html = '<div class="list-group list-group-flush border rounded-3">';
                    res.users.forEach(u => {
                        html += `
                        <div class="list-group-item p-2 d-flex align-items-center justify-content-between">
                            <div>
                                <strong class="text-dark">${u.name}</strong>
                                ${u.username ? `<span class="text-muted small">(@${u.username})</span>` : ''}
                                <div class="font-monospace text-primary small">Chat ID: ${u.chat_id}</div>
                            </div>
                            <a href="<?= BASE_URL ?>/users/add.php?name=${encodeURIComponent(u.name)}&chat_id=${u.chat_id}&username=${encodeURIComponent(u.username || '')}" class="btn btn-sm btn-outline-success rounded-pill py-0.5 px-2">
                                + Add User
                            </a>
                        </div>`;
                    });
                    html += '</div>';
                    resultsBox.html(html);
                } else {
                    resultsBox.html(`
                        <div class="alert alert-info py-2 px-3 mb-0 small">
                            ${res.message || 'No new messages found. Ensure the user texted <code>/start</code> to your bot.'}
                        </div>
                    `);
                }
            },
            error: function () {
                btn.prop('disabled', false).html(origText);
                resultsBox.html('<div class="alert alert-danger py-2 px-3 mb-0 small">Failed to fetch updates from Telegram.</div>');
            }
        });
    });
});
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
