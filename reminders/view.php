<?php
/**
 * Telegram Reminder Management System
 * Reminder Tracking & Execution Details View
 */

declare(strict_types=1);

$pageTitle = 'Reminder Details & Tracking';
$activeMenu = 'reminders';

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/header.php';

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

if (!$db || $id <= 0) {
    set_flash('danger', 'Invalid reminder requested.');
    redirect(BASE_URL . '/reminders/index.php');
}

// 1. Fetch Reminder
$stmt = $db->prepare("
    SELECT r.*, a.full_name as author_name, a.username as author_username
    FROM reminders r
    LEFT JOIN admins a ON r.created_by = a.id
    WHERE r.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$reminder = $stmt->fetch();

if (!$reminder) {
    set_flash('danger', 'Reminder not found.');
    redirect(BASE_URL . '/reminders/index.php');
}

// 2. Fetch Messages Sequence
$msgStmt = $db->prepare("SELECT * FROM reminder_messages WHERE reminder_id = :id ORDER BY sort_order ASC");
$msgStmt->execute(['id' => $id]);
$messages = $msgStmt->fetchAll();

// 3. Fetch Recipients
$recStmt = $db->prepare("
    SELECT rr.*, u.name as user_name, u.username as tg_username, u.status as user_status
    FROM reminder_recipients rr
    LEFT JOIN users u ON rr.user_id = u.id
    WHERE rr.reminder_id = :id
    ORDER BY u.name ASC
");
$recStmt->execute(['id' => $id]);
$recipients = $recStmt->fetchAll();

// 4. Fetch Message Logs for this Reminder
$logStmt = $db->prepare("SELECT * FROM message_logs WHERE reminder_id = :id ORDER BY id DESC");
$logStmt->execute(['id' => $id]);
$logs = $logStmt->fetchAll();

$totalLogs = count($logs);
$sentLogs = 0;
$failedLogs = 0;
foreach ($logs as $l) {
    if ($l['status'] === 'sent') $sentLogs++;
    else if ($l['status'] === 'failed') $failedLogs++;
}
?>

<!-- Page Header & Action Buttons -->
<div class="page-header">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="page-title">
                <?= e($reminder['title']) ?>
            </h1>
            <?= get_status_badge($reminder['status']) ?>
        </div>
        <p class="page-subtitle">
            Scheduled for: <strong><?= format_datetime($reminder['scheduled_time']) ?></strong> (<?= time_ago($reminder['scheduled_time']) ?>)
        </p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/reminders/send_now.php?id=<?= $reminder['id'] ?>&token=<?= csrf_token() ?>" class="btn btn-primary rounded-pill px-3.5 shadow-sm d-inline-flex align-items-center gap-1.5" onclick="return confirm('Send this reminder sequence right now?');">
            <i class="bi bi-send-fill"></i> Send Now
        </a>
        <?php if ($reminder['status'] === 'pending'): ?>
            <a href="<?= BASE_URL ?>/reminders/edit.php?id=<?= $reminder['id'] ?>" class="btn btn-light border rounded-pill px-3">
                <i class="bi bi-pencil me-1 text-warning"></i> Edit
            </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/reminders/clone.php?id=<?= $reminder['id'] ?>&token=<?= csrf_token() ?>" class="btn btn-light border rounded-pill px-3">
            <i class="bi bi-copy me-1"></i> Clone
        </a>
        <a href="<?= BASE_URL ?>/reminders/delete.php?id=<?= $reminder['id'] ?>&token=<?= csrf_token() ?>" class="btn btn-outline-danger rounded-pill px-3" onclick="return confirm('Are you sure you want to delete this reminder and its logs?');">
            <i class="bi bi-trash"></i>
        </a>
        <a href="<?= BASE_URL ?>/reminders/index.php" class="btn btn-light border rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<!-- Metadata Info Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="p-3 bg-white border rounded-3 shadow-sm">
            <div class="text-muted small fw-bold text-uppercase">Execution Status</div>
            <div class="fs-5 fw-bold text-dark mt-1"><?= ucfirst($reminder['status']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="p-3 bg-white border rounded-3 shadow-sm">
            <div class="text-muted small fw-bold text-uppercase">Sequential Messages</div>
            <div class="fs-5 fw-bold text-primary mt-1"><?= count($messages) ?> Messages</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="p-3 bg-white border rounded-3 shadow-sm">
            <div class="text-muted small fw-bold text-uppercase">Assigned Recipients</div>
            <div class="fs-5 fw-bold text-info mt-1"><?= count($recipients) ?> Users</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="p-3 bg-white border rounded-3 shadow-sm">
            <div class="text-muted small fw-bold text-uppercase">Sequential Delay</div>
            <div class="fs-5 fw-bold text-dark mt-1"><?= (int)$reminder['delay_seconds'] ?> Seconds</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Message Sequence Timeline -->
    <div class="col-lg-7">
        <div class="card-custom mb-4">
            <div class="card-header-custom">
                <h5 class="card-title-custom">
                    <i class="bi bi-list-ol text-primary"></i> Message Sequence (<?= count($messages) ?>)
                </h5>
                <span class="badge bg-light text-muted border">Order of Dispatch</span>
            </div>
            <div class="p-4">
                <?php if (empty($messages)): ?>
                    <div class="text-center py-3 text-muted">No messages found in this reminder.</div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($messages as $idx => $msg): ?>
                            <div class="border rounded-3 p-3 bg-light position-relative">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="badge bg-primary rounded-pill px-2.5 py-1">
                                        <i class="bi bi-chat-text me-1"></i> Message #<?= (int)$msg['sort_order'] ?>
                                    </span>
                                    <?php if ($idx < count($messages) - 1): ?>
                                        <span class="text-muted small">
                                            <i class="bi bi-hourglass-split me-1 text-warning"></i> +<?= (int)$reminder['delay_seconds'] ?>s delay before next
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="bg-white p-3 rounded-2 border text-dark font-sans" style="white-space: pre-wrap;"><?= nl2br(e($msg['message_text'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($reminder['description'])): ?>
            <div class="card-custom">
                <div class="card-header-custom">
                    <h5 class="card-title-custom">
                        <i class="bi bi-file-text text-secondary"></i> Internal Notes
                    </h5>
                </div>
                <div class="p-3 text-muted">
                    <?= nl2br(e($reminder['description'])) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recipients List -->
    <div class="col-lg-5">
        <div class="card-custom">
            <div class="card-header-custom">
                <h5 class="card-title-custom">
                    <i class="bi bi-people-fill text-info"></i> Target Recipients (<?= count($recipients) ?>)
                </h5>
            </div>
            <div class="p-0">
                <?php if (empty($recipients)): ?>
                    <div class="text-center py-4 text-muted">No recipients assigned.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recipients as $rec): ?>
                            <div class="list-group-item p-3 d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-bold text-dark mb-0.5"><?= e($rec['user_name'] ?: 'Recipient') ?></div>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <code class="bg-light px-2 py-0.5 rounded text-dark small font-monospace"><?= e($rec['chat_id']) ?></code>
                                        <?php if (!empty($rec['tg_username'])): ?>
                                            <span class="text-muted small">(@<?= e($rec['tg_username']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill btn-test-chat" data-chat-id="<?= e($rec['chat_id']) ?>" data-name="<?= e($rec['user_name'] ?: 'Recipient') ?>" title="Send Direct Test Message">
                                    <i class="bi bi-send"></i> Test
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Execution Logs Section -->
<div class="card-custom">
    <div class="card-header-custom">
        <div>
            <h5 class="card-title-custom">
                <i class="bi bi-journal-check text-success"></i> Delivery Dispatch Logs for this Reminder
            </h5>
            <div class="text-muted small">
                <?= $sentLogs ?> sent successfully, <?= $failedLogs ?> failed out of <?= $totalLogs ?> total attempts
            </div>
        </div>
        <?php if ($reminder['status'] === 'pending'): ?>
            <span class="badge bg-warning text-dark px-3 py-1.5 rounded-pill">
                <i class="bi bi-clock me-1"></i> Awaiting Cron Schedule
            </span>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Recipient</th>
                    <th>Chat ID</th>
                    <th>Message Preview</th>
                    <th>Sent At</th>
                    <th>Telegram Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-hourglass-top fs-1 d-block mb-2 text-secondary"></i>
                            No dispatch logs generated yet. Messages will appear here once the cron job runs.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <?php if ($log['status'] === 'sent'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                        <i class="bi bi-check-circle-fill me-1"></i>Sent
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1">
                                        <i class="bi bi-x-circle-fill me-1"></i>Failed
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold text-dark"><?= e($log['recipient_name'] ?: 'Recipient') ?></td>
                            <td><code><?= e($log['chat_id']) ?></code></td>
                            <td class="text-truncate" style="max-width: 250px;">
                                <span class="text-muted small"><?= strip_tags($log['message_text']) ?></span>
                            </td>
                            <td class="text-muted small"><?= format_datetime($log['sent_time']) ?></td>
                            <td class="small">
                                <?php if ($log['status'] === 'sent'): ?>
                                    <span class="text-muted">Msg ID: <?= e($log['telegram_message_id'] ?: '-') ?></span>
                                <?php else: ?>
                                    <span class="text-danger fw-semibold"><?= e($log['error_message'] ?: 'Unknown Error') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
