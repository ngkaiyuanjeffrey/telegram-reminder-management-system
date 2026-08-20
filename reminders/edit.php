<?php
/**
 * Telegram Reminder Management System
 * Edit Scheduled Reminder & Messages
 */

declare(strict_types=1);

$pageTitle = 'Edit Reminder';
$activeMenu = 'reminders';

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/header.php';

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$error = null;

if (!$db || $id <= 0) {
    set_flash('danger', 'Invalid reminder requested.');
    redirect(BASE_URL . '/reminders/index.php');
}

// Fetch Reminder
$stmt = $db->prepare("SELECT * FROM reminders WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$reminder = $stmt->fetch();

if (!$reminder) {
    set_flash('danger', 'Reminder not found.');
    redirect(BASE_URL . '/reminders/index.php');
}

// Fetch Active Users
$usersStmt = $db->query("SELECT * FROM users WHERE status = 'active' ORDER BY name ASC");
$activeUsers = $usersStmt->fetchAll();

// Fetch Existing Messages
$msgStmt = $db->prepare("SELECT * FROM reminder_messages WHERE reminder_id = :id ORDER BY sort_order ASC");
$msgStmt->execute(['id' => $id]);
$existingMessages = $msgStmt->fetchAll();

// Fetch Assigned Recipient User IDs
$recStmt = $db->prepare("SELECT user_id FROM reminder_recipients WHERE reminder_id = :id AND user_id IS NOT NULL");
$recStmt->execute(['id' => $id]);
$selectedRecipientIds = $recStmt->fetchAll(PDO::FETCH_COLUMN);

// Format datetime for datetime-local input
$formattedScheduledTime = date('Y-m-d\TH:i', strtotime($reminder['scheduled_time']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please submit again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $scheduledTime = trim($_POST['scheduled_time'] ?? '');
        $delaySeconds = max(1, (int)($_POST['delay_seconds'] ?? 2));
        $status = in_array($_POST['status'] ?? '', ['pending', 'sent', 'failed', 'partially_sent']) ? $_POST['status'] : 'pending';
        $selectedRecipients = $_POST['recipients'] ?? [];
        $submittedMessages = $_POST['messages'] ?? [];

        if (empty($title)) {
            $error = 'Reminder title is required.';
        } elseif (empty($scheduledTime)) {
            $error = 'Scheduled date and time are required.';
        } elseif (empty($selectedRecipients)) {
            $error = 'Please select at least one recipient.';
        } else {
            $validMessages = [];
            foreach ($submittedMessages as $msg) {
                $text = trim($msg['text'] ?? '');
                if (!empty($text)) {
                    $validMessages[] = $text;
                }
            }

            if (empty($validMessages)) {
                $error = 'Please enter at least one message.';
            } else {
                try {
                    $db->beginTransaction();

                    $dt = new DateTime($scheduledTime);
                    $sqlScheduledTime = $dt->format('Y-m-d H:i:s');

                    // Update Reminder
                    $update = $db->prepare("
                        UPDATE reminders 
                        SET title = :title, description = :description, scheduled_time = :scheduled_time, delay_seconds = :delay, status = :status
                        WHERE id = :id
                    ");
                    $update->execute([
                        'title' => $title,
                        'description' => $description,
                        'scheduled_time' => $sqlScheduledTime,
                        'delay' => $delaySeconds,
                        'status' => $status,
                        'id' => $id
                    ]);

                    // Replace Messages
                    $delMsg = $db->prepare("DELETE FROM reminder_messages WHERE reminder_id = :id");
                    $delMsg->execute(['id' => $id]);

                    $insMsg = $db->prepare("INSERT INTO reminder_messages (reminder_id, message_text, sort_order) VALUES (:rid, :text, :order)");
                    foreach ($validMessages as $idx => $msgText) {
                        $insMsg->execute([
                            'rid' => $id,
                            'text' => $msgText,
                            'order' => $idx + 1
                        ]);
                    }

                    // Replace Recipients
                    $delRec = $db->prepare("DELETE FROM reminder_recipients WHERE reminder_id = :id");
                    $delRec->execute(['id' => $id]);

                    $userMap = [];
                    foreach ($activeUsers as $u) {
                        $userMap[$u['id']] = $u['chat_id'];
                    }

                    $insRec = $db->prepare("INSERT INTO reminder_recipients (reminder_id, user_id, chat_id) VALUES (:rid, :uid, :chat_id)");
                    foreach ($selectedRecipients as $uid) {
                        $uid = (int)$uid;
                        if (isset($userMap[$uid])) {
                            $insRec->execute([
                                'rid' => $id,
                                'uid' => $uid,
                                'chat_id' => $userMap[$uid]
                            ]);
                        }
                    }

                    $db->commit();

                    set_flash('success', "Reminder '{$title}' updated successfully.");
                    redirect(BASE_URL . "/reminders/view.php?id={$id}");
                } catch (Throwable $e) {
                    $db->rollBack();
                    $error = 'Failed to update reminder: ' . $e->getMessage();
                }
            }
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-pencil-square text-primary"></i> Edit Reminder
        </h1>
        <p class="page-subtitle">Modify schedule, recipients, and sequence</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/reminders/view.php?id=<?= $id ?>" class="btn btn-light border rounded-pill px-3.5">
            <i class="bi bi-arrow-left me-1"></i> Back to Tracking
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
        <div><?= e($error) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/reminders/edit.php?id=<?= $id ?>">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- Left: Details & Messages -->
        <div class="col-lg-7">
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <h5 class="card-title-custom">
                        <i class="bi bi-card-heading text-primary"></i> 1. Reminder Configuration
                    </h5>
                </div>
                <div class="p-4">
                    <div class="mb-3">
                        <label for="reminderTitle" class="form-label small fw-bold text-muted">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="reminderTitle" name="title" value="<?= e($reminder['title']) ?>" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label for="scheduledTimeInput" class="form-label small fw-bold text-muted">Scheduled Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="scheduledTimeInput" name="scheduled_time" value="<?= e($formattedScheduledTime) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="delaySecondsInput" class="form-label small fw-bold text-muted">Delay</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="delaySecondsInput" name="delay_seconds" min="1" max="10" value="<?= (int)$reminder['delay_seconds'] ?>" required>
                                <span class="input-group-text bg-light text-muted">sec</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="statusSelect" class="form-label small fw-bold text-muted">Status</label>
                            <select class="form-select" id="statusSelect" name="status">
                                <option value="pending" <?= ($reminder['status'] === 'pending') ? 'selected' : '' ?>>Pending</option>
                                <option value="sent" <?= ($reminder['status'] === 'sent') ? 'selected' : '' ?>>Sent</option>
                                <option value="failed" <?= ($reminder['status'] === 'failed') ? 'selected' : '' ?>>Failed</option>
                                <option value="partially_sent" <?= ($reminder['status'] === 'partially_sent') ? 'selected' : '' ?>>Partially Sent</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="reminderDesc" class="form-label small fw-bold text-muted">Notes (Optional)</label>
                        <textarea class="form-control" id="reminderDesc" name="description" rows="2"><?= e($reminder['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Messages Box -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <div>
                        <h5 class="card-title-custom">
                            <i class="bi bi-chat-square-text-fill text-primary"></i> 2. Message Sequence
                        </h5>
                        <p class="text-muted small mb-0">Messages sent in this sequential order</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" id="btnAddMessage">
                        <i class="bi bi-plus-lg me-1"></i> Add Message
                    </button>
                </div>
                <div class="p-4">
                    <div id="messagesContainer">
                        <?php foreach ($existingMessages as $mIdx => $msg): ?>
                            <div class="message-item-card">
                                <div class="message-item-header">
                                    <div class="message-sequence-badge">
                                        <i class="bi bi-chat-text text-primary"></i>
                                        <span>Message #<span class="seq-num-display"><?= $mIdx + 1 ?></span> in sequence</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-light border btn-move-up" title="Move Up" <?= ($mIdx === 0) ? 'disabled' : '' ?>><i class="bi bi-arrow-up"></i></button>
                                        <button type="button" class="btn btn-sm btn-light border btn-move-down" title="Move Down" <?= ($mIdx === count($existingMessages) - 1) ? 'disabled' : '' ?>><i class="bi bi-arrow-down"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-msg" title="Remove Message" <?= (count($existingMessages) <= 1) ? 'disabled' : '' ?>><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>
                                <div class="message-item-body">
                                    <div class="formatting-helpers">
                                        <span class="text-muted small me-1">Formatting:</span>
                                        <button type="button" class="btn-tag" data-tag="b">&lt;b&gt;Bold&lt;/b&gt;</button>
                                        <button type="button" class="btn-tag" data-tag="i">&lt;i&gt;Italic&lt;/i&gt;</button>
                                        <button type="button" class="btn-tag" data-tag="code">&lt;code&gt;Code&lt;/code&gt;</button>
                                        <button type="button" class="btn-tag" data-tag="a">&lt;a href=""&gt;Link&lt;/a&gt;</button>
                                    </div>
                                    <textarea class="form-control message-textarea" name="messages[<?= $mIdx ?>][text]" rows="3" required><?= e($msg['message_text']) ?></textarea>
                                    <input type="hidden" class="message-sort-order" name="messages[<?= $mIdx ?>][sort_order]" value="<?= $mIdx + 1 ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Recipients -->
        <div class="col-lg-5">
            <div class="card-custom position-sticky" style="top: 85px;">
                <div class="card-header-custom">
                    <div>
                        <h5 class="card-title-custom">
                            <i class="bi bi-people-fill text-primary"></i> 3. Target Recipients
                        </h5>
                        <div class="text-muted small">
                            <span id="selectedCountBadge" class="badge bg-primary rounded-pill font-monospace"><?= count($selectedRecipientIds) ?></span> selected
                        </div>
                    </div>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="selectAllRecipients">
                        <label class="form-check-label small fw-bold text-dark" for="selectAllRecipients">Select All</label>
                    </div>
                </div>
                <div class="p-3 border-bottom bg-light">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="searchRecipientsInput" placeholder="Filter recipients...">
                    </div>
                </div>
                <div class="p-3" style="max-height: 420px; overflow-y: auto;">
                    <div class="list-group list-group-flush" id="recipientsList">
                        <?php foreach ($activeUsers as $u): ?>
                            <?php $isChecked = in_array($u['id'], $selectedRecipientIds); ?>
                            <label class="list-group-item d-flex align-items-center gap-3 p-2.5 rounded-3 mb-1 border hover-bg cursor-pointer recipient-item-row" data-name="<?= e($u['name']) ?>" data-chat="<?= e($u['chat_id']) ?>">
                                <input class="form-check-input flex-shrink-0 recipient-checkbox" type="checkbox" name="recipients[]" value="<?= $u['id'] ?>" <?= $isChecked ? 'checked' : '' ?>>
                                <div class="flex-grow-1 text-truncate">
                                    <div class="fw-bold text-dark small text-truncate"><?= e($u['name']) ?></div>
                                    <div class="text-muted font-monospace" style="font-size: 0.78rem;">
                                        Chat ID: <?= e($u['chat_id']) ?> <?= !empty($u['username']) ? "(@{$u['username']})" : "" ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="p-4 bg-light border-top">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Update Reminder
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
