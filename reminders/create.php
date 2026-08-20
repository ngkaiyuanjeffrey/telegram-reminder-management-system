<?php
/**
 * Telegram Reminder Management System
 * Create New Scheduled Reminder with Sequential Messages
 */

declare(strict_types=1);

$pageTitle = 'Create Reminder';
$activeMenu = 'create_reminder';

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/header.php';

$db = get_db();
$error = null;

// Fetch all active recipients for selection
$activeUsers = [];
if ($db) {
    try {
        $stmt = $db->query("SELECT * FROM users WHERE status = 'active' ORDER BY name ASC");
        $activeUsers = $stmt->fetchAll();
    } catch (Throwable $e) {
        $error = 'Error fetching recipients: ' . $e->getMessage();
    }
}

// Default values
$defaultDelay = (int)get_setting('default_delay_seconds', '2');
$scheduledTimeDefault = date('Y-m-d\TH:i', strtotime('+15 minutes'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please submit the form again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $scheduledTime = trim($_POST['scheduled_time'] ?? '');
        $delaySeconds = max(1, (int)($_POST['delay_seconds'] ?? 2));
        $selectedRecipients = $_POST['recipients'] ?? [];
        $submittedMessages = $_POST['messages'] ?? [];

        // Validation
        if (empty($title)) {
            $error = 'Reminder title is required.';
        } elseif (empty($scheduledTime)) {
            $error = 'Scheduled date and time are required.';
        } elseif (empty($selectedRecipients)) {
            $error = 'Please select at least one recipient (Telegram user).';
        } else {
            // Filter non-empty messages
            $validMessages = [];
            foreach ($submittedMessages as $msg) {
                $text = trim($msg['text'] ?? '');
                if (!empty($text)) {
                    $validMessages[] = [
                        'text' => $text,
                        'sort_order' => (int)($msg['sort_order'] ?? (count($validMessages) + 1))
                    ];
                }
            }

            if (empty($validMessages)) {
                $error = 'Please enter at least one message for this reminder.';
            } else {
                try {
                    $db->beginTransaction();

                    // Convert datetime-local to SQL datetime
                    $dt = new DateTime($scheduledTime);
                    $sqlScheduledTime = $dt->format('Y-m-d H:i:s');
                    $adminId = $_SESSION['admin_id'] ?? null;

                    // 1. Insert Reminder
                    $stmt = $db->prepare("
                        INSERT INTO reminders (title, description, scheduled_time, status, delay_seconds, created_by)
                        VALUES (:title, :description, :scheduled_time, 'pending', :delay, :created_by)
                    ");
                    $stmt->execute([
                        'title' => $title,
                        'description' => $description,
                        'scheduled_time' => $sqlScheduledTime,
                        'delay' => $delaySeconds,
                        'created_by' => $adminId
                    ]);
                    $reminderId = (int)$db->lastInsertId();

                    // 2. Insert Sequential Messages
                    $msgStmt = $db->prepare("
                        INSERT INTO reminder_messages (reminder_id, message_text, sort_order)
                        VALUES (:reminder_id, :message_text, :sort_order)
                    ");
                    foreach ($validMessages as $idx => $vm) {
                        $msgStmt->execute([
                            'reminder_id' => $reminderId,
                            'message_text' => $vm['text'],
                            'sort_order' => $idx + 1
                        ]);
                    }

                    // 3. Insert Recipients
                    $recStmt = $db->prepare("
                        INSERT INTO reminder_recipients (reminder_id, user_id, chat_id)
                        VALUES (:reminder_id, :user_id, :chat_id)
                    ");

                    // Fetch user chat IDs
                    $userMap = [];
                    foreach ($activeUsers as $u) {
                        $userMap[$u['id']] = $u['chat_id'];
                    }

                    foreach ($selectedRecipients as $userId) {
                        $userId = (int)$userId;
                        if (isset($userMap[$userId])) {
                            $recStmt->execute([
                                'reminder_id' => $reminderId,
                                'user_id' => $userId,
                                'chat_id' => $userMap[$userId]
                            ]);
                        }
                    }

                    $db->commit();

                    set_flash('success', "Reminder '{$title}' created successfully! Scheduled for " . format_datetime($sqlScheduledTime));
                    redirect(BASE_URL . "/reminders/view.php?id={$reminderId}");
                } catch (Throwable $e) {
                    $db->rollBack();
                    $error = 'Failed to save reminder: ' . $e->getMessage();
                }
            }
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-calendar-plus-fill text-primary"></i> Schedule New Reminder
        </h1>
        <p class="page-subtitle">Configure message sequence, target recipients, and delivery schedule</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/reminders/index.php" class="btn btn-light border rounded-pill px-3.5">
            <i class="bi bi-arrow-left me-1"></i> Back to Reminders
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

<form method="POST" action="<?= BASE_URL ?>/reminders/create.php" id="formCreateReminder">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- Left Column: Reminder Details & Messages Builder -->
        <div class="col-lg-7">
            <!-- Basic Information Card -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <h5 class="card-title-custom">
                        <i class="bi bi-card-heading text-primary"></i> 1. Reminder Details
                    </h5>
                </div>
                <div class="p-4">
                    <div class="mb-3">
                        <label for="reminderTitle" class="form-label small fw-bold text-muted">Reminder Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg fs-6" id="reminderTitle" name="title" value="<?= e($_POST['title'] ?? '') ?>" required placeholder="e.g. Daily Standup Briefing or Due Invoice Alert">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label for="scheduledTimeInput" class="form-label small fw-bold text-muted">Scheduled Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="scheduledTimeInput" name="scheduled_time" value="<?= e($_POST['scheduled_time'] ?? $scheduledTimeDefault) ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label for="delaySecondsInput" class="form-label small fw-bold text-muted">Delay Between Messages</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="delaySecondsInput" name="delay_seconds" min="1" max="10" value="<?= e((string)($_POST['delay_seconds'] ?? $defaultDelay)) ?>" required>
                                <span class="input-group-text bg-light text-muted">sec</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="reminderDesc" class="form-label small fw-bold text-muted">Internal Notes / Description (Optional)</label>
                        <textarea class="form-control" id="reminderDesc" name="description" rows="2" placeholder="Brief note about the purpose of this reminder..."><?= e($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Dynamic Sequential Message Builder Card -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <div>
                        <h5 class="card-title-custom">
                            <i class="bi bi-chat-square-text-fill text-primary"></i> 2. Message Sequence
                        </h5>
                        <p class="text-muted small mb-0">Messages will be sent one after another in this exact order</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" id="btnAddMessage">
                        <i class="bi bi-plus-lg me-1"></i> Add Next Message
                    </button>
                </div>
                <div class="p-4">
                    <div id="messagesContainer">
                        <!-- Message Row 1 -->
                        <div class="message-item-card">
                            <div class="message-item-header">
                                <div class="message-sequence-badge">
                                    <i class="bi bi-chat-text text-primary"></i>
                                    <span>Message #<span class="seq-num-display">1</span> in sequence</span>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <button type="button" class="btn btn-sm btn-light border btn-move-up" title="Move Up" disabled><i class="bi bi-arrow-up"></i></button>
                                    <button type="button" class="btn btn-sm btn-light border btn-move-down" title="Move Down" disabled><i class="bi bi-arrow-down"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-msg" title="Remove Message" disabled><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <div class="message-item-body">
                                <div class="formatting-helpers">
                                    <span class="text-muted small me-1">Quick formatting:</span>
                                    <button type="button" class="btn-tag" data-tag="b">&lt;b&gt;Bold&lt;/b&gt;</button>
                                    <button type="button" class="btn-tag" data-tag="i">&lt;i&gt;Italic&lt;/i&gt;</button>
                                    <button type="button" class="btn-tag" data-tag="code">&lt;code&gt;Code&lt;/code&gt;</button>
                                    <button type="button" class="btn-tag" data-tag="a">&lt;a href=""&gt;Link&lt;/a&gt;</button>
                                </div>
                                <textarea class="form-control message-textarea" name="messages[0][text]" rows="3" placeholder="Enter message text... (e.g. Good morning! Hope you're having a wonderful day.)" required><?= e($_POST['messages'][0]['text'] ?? '') ?></textarea>
                                <input type="hidden" class="message-sort-order" name="messages[0][sort_order]" value="1">
                            </div>
                        </div>
                    </div>

                    <div class="d-grid mt-3">
                        <button type="button" class="btn btn-outline-primary border-dashed rounded-3 py-2" id="btnAddMessageBottom" onclick="$('#btnAddMessage').click();">
                            <i class="bi bi-plus-circle me-1"></i> + Add Another Message to Sequence
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Target Recipients Selector -->
        <div class="col-lg-5">
            <div class="card-custom position-sticky" style="top: 85px;">
                <div class="card-header-custom">
                    <div>
                        <h5 class="card-title-custom">
                            <i class="bi bi-people-fill text-primary"></i> 3. Target Recipients
                        </h5>
                        <div class="text-muted small">
                            <span id="selectedCountBadge" class="badge bg-primary rounded-pill font-monospace">0</span> users selected
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
                        <input type="text" class="form-control" id="searchRecipientsInput" placeholder="Filter recipients by name or chat ID...">
                    </div>
                </div>
                <div class="p-3" style="max-height: 420px; overflow-y: auto;">
                    <?php if (empty($activeUsers)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-person-x fs-1 d-block mb-1 text-secondary"></i>
                            No active recipients available.
                            <div class="mt-2">
                                <a href="<?= BASE_URL ?>/users/add.php" target="_blank" class="btn btn-sm btn-primary rounded-pill">+ Add Recipient First</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush" id="recipientsList">
                            <?php foreach ($activeUsers as $u): ?>
                                <label class="list-group-item d-flex align-items-center gap-3 p-2.5 rounded-3 mb-1 border hover-bg cursor-pointer recipient-item-row" data-name="<?= e($u['name']) ?>" data-chat="<?= e($u['chat_id']) ?>">
                                    <input class="form-check-input flex-shrink-0 recipient-checkbox" type="checkbox" name="recipients[]" value="<?= $u['id'] ?>">
                                    <div class="flex-grow-1 text-truncate">
                                        <div class="fw-bold text-dark small text-truncate"><?= e($u['name']) ?></div>
                                        <div class="text-muted font-monospace" style="font-size: 0.78rem;">
                                            Chat ID: <?= e($u['chat_id']) ?> <?= !empty($u['username']) ? "(@{$u['username']})" : "" ?>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="p-4 bg-light border-top">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm">
                            <i class="bi bi-calendar-check me-1"></i> Save & Schedule Reminder
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
