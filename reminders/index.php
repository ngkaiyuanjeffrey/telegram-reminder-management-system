<?php
/**
 * Telegram Reminder Management System
 * Reminders List & Tracking Overview
 */

declare(strict_types=1);

$pageTitle = 'All Reminders';
$activeMenu = 'reminders';

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/header.php';

$db = get_db();
$filter = trim($_GET['filter'] ?? 'all');
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT r.*,
           COUNT(DISTINCT rm.id) as message_count,
           COUNT(DISTINCT rr.id) as recipient_count,
           a.username as author_name
    FROM reminders r
    LEFT JOIN reminder_messages rm ON r.id = rm.reminder_id
    LEFT JOIN reminder_recipients rr ON r.id = rr.reminder_id
    LEFT JOIN admins a ON r.created_by = a.id
    WHERE 1=1
";
$params = [];

// Handle preset filters
if ($filter === 'today') {
    $sql .= " AND DATE(r.scheduled_time) = CURDATE()";
} elseif ($filter === 'past7days') {
    $sql .= " AND r.scheduled_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
}

// Handle status filter
if (!empty($statusFilter) && in_array($statusFilter, ['pending', 'in_progress', 'sent', 'failed', 'partially_sent'], true)) {
    $sql .= " AND r.status = :st";
    $params['st'] = $statusFilter;
}

// Handle search (by title, message content, recipient chat_id or name)
if (!empty($search)) {
    $sql .= " AND (
        r.title LIKE :s 
        OR r.id IN (SELECT reminder_id FROM reminder_messages WHERE message_text LIKE :s)
        OR r.id IN (SELECT reminder_id FROM reminder_recipients WHERE chat_id LIKE :s)
        OR r.id IN (SELECT rr2.reminder_id FROM reminder_recipients rr2 JOIN users u2 ON rr2.user_id = u2.id WHERE u2.name LIKE :s)
    )";
    $params['s'] = "%{$search}%";
}

$sql .= " GROUP BY r.id ORDER BY r.scheduled_time DESC";

$reminders = [];
if ($db) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $reminders = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log("Error fetching reminders: " . $e->getMessage());
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-alarm-fill text-primary"></i> Reminder Management
        </h1>
        <p class="page-subtitle">Schedule, track, and monitor sequential Telegram messages</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/reminders/create.php" class="btn btn-primary rounded-pill px-3.5 shadow-sm d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-plus-lg"></i> Create Reminder
        </a>
    </div>
</div>

<!-- Filter Tabs -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="<?= BASE_URL ?>/reminders/index.php" class="btn btn-sm rounded-pill <?= ($filter === 'all' && empty($statusFilter)) ? 'btn-dark' : 'btn-light border' ?>">
        All Reminders
    </a>
    <a href="<?= BASE_URL ?>/reminders/index.php?filter=today" class="btn btn-sm rounded-pill <?= ($filter === 'today') ? 'btn-dark' : 'btn-light border' ?>">
        <i class="bi bi-calendar-event me-1"></i> Today's Reminders
    </a>
    <a href="<?= BASE_URL ?>/reminders/index.php?status=pending" class="btn btn-sm rounded-pill <?= ($statusFilter === 'pending') ? 'btn-warning text-dark fw-bold' : 'btn-light border' ?>">
        <i class="bi bi-clock me-1"></i> Pending
    </a>
    <a href="<?= BASE_URL ?>/reminders/index.php?status=sent" class="btn btn-sm rounded-pill <?= ($statusFilter === 'sent') ? 'btn-success fw-bold' : 'btn-light border' ?>">
        <i class="bi bi-check-circle me-1"></i> Sent
    </a>
    <a href="<?= BASE_URL ?>/reminders/index.php?status=failed" class="btn btn-sm rounded-pill <?= ($statusFilter === 'failed') ? 'btn-danger fw-bold' : 'btn-light border' ?>">
        <i class="bi bi-x-circle me-1"></i> Failed
    </a>
    <a href="<?= BASE_URL ?>/reminders/index.php?filter=past7days" class="btn btn-sm rounded-pill <?= ($filter === 'past7days') ? 'btn-dark' : 'btn-light border' ?>">
        <i class="bi bi-calendar-range me-1"></i> Past 7 Days
    </a>
</div>

<!-- Search Bar -->
<div class="card-custom mb-4">
    <div class="p-3">
        <form method="GET" action="<?= BASE_URL ?>/reminders/index.php" class="row g-2 align-items-center">
            <?php if (!empty($filter)): ?>
                <input type="hidden" name="filter" value="<?= e($filter) ?>">
            <?php endif; ?>
            <div class="col-md-7 col-lg-6">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="search" value="<?= e($search) ?>" placeholder="Search by title, message text, recipient name, or chat ID...">
                </div>
            </div>
            <div class="col-md-3 col-lg-3">
                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="">Status: All</option>
                    <option value="pending" <?= ($statusFilter === 'pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="in_progress" <?= ($statusFilter === 'in_progress') ? 'selected' : '' ?>>In Progress</option>
                    <option value="sent" <?= ($statusFilter === 'sent') ? 'selected' : '' ?>>Sent</option>
                    <option value="failed" <?= ($statusFilter === 'failed') ? 'selected' : '' ?>>Failed</option>
                    <option value="partially_sent" <?= ($statusFilter === 'partially_sent') ? 'selected' : '' ?>>Partially Sent</option>
                </select>
            </div>
            <div class="col-md-2 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill px-3">Search</button>
                <?php if (!empty($search) || !empty($statusFilter) || $filter !== 'all'): ?>
                    <a href="<?= BASE_URL ?>/reminders/index.php" class="btn btn-light border rounded-pill px-3">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Reminders Table -->
<div class="card-custom">
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Reminder Title</th>
                    <th>Scheduled For</th>
                    <th>Sequence</th>
                    <th>Recipients</th>
                    <th>Delay</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reminders)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-alarm fs-1 d-block mb-2 text-secondary"></i>
                            No reminders found matching your criteria.
                            <div class="mt-2">
                                <a href="<?= BASE_URL ?>/reminders/create.php" class="btn btn-sm btn-primary rounded-pill">Create New Reminder</a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reminders as $r): ?>
                        <tr>
                            <td>
                                <?= get_status_badge($r['status']) ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark mb-0.5">
                                    <a href="<?= BASE_URL ?>/reminders/view.php?id=<?= $r['id'] ?>" class="text-decoration-none text-dark hover-primary">
                                        <?= e($r['title']) ?>
                                    </a>
                                </div>
                                <?php if (!empty($r['description'])): ?>
                                    <div class="text-muted small text-truncate" style="max-width: 300px;"><?= e($r['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark small">
                                    <i class="bi bi-calendar-event text-primary me-1"></i><?= format_datetime($r['scheduled_time']) ?>
                                </div>
                                <div class="text-muted small">
                                    <?= time_ago($r['scheduled_time']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-chat-dots me-1 text-primary"></i><?= (int)$r['message_count'] ?> messages
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-people me-1 text-info"></i><?= (int)$r['recipient_count'] ?> users
                                </span>
                            </td>
                            <td class="text-muted small font-monospace">
                                <?= (int)$r['delay_seconds'] ?>s
                            </td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li>
                                            <a class="dropdown-item py-2" href="<?= BASE_URL ?>/reminders/view.php?id=<?= $r['id'] ?>">
                                                <i class="bi bi-eye me-2 text-primary"></i> View Tracking
                                            </a>
                                        </li>
                                        <?php if ($r['status'] === 'pending'): ?>
                                            <li>
                                                <a class="dropdown-item py-2" href="<?= BASE_URL ?>/reminders/edit.php?id=<?= $r['id'] ?>">
                                                    <i class="bi bi-pencil me-2 text-warning"></i> Edit Reminder
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <li>
                                            <a class="dropdown-item py-2 text-primary" href="<?= BASE_URL ?>/reminders/send_now.php?id=<?= $r['id'] ?>&token=<?= csrf_token() ?>" onclick="return confirm('Send this reminder sequence now?');">
                                                <i class="bi bi-send-fill me-2 text-primary"></i> Send Immediately
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-2" href="<?= BASE_URL ?>/reminders/clone.php?id=<?= $r['id'] ?>&token=<?= csrf_token() ?>">
                                                <i class="bi bi-copy me-2 text-secondary"></i> Duplicate / Clone
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>/reminders/delete.php?id=<?= $r['id'] ?>&token=<?= csrf_token() ?>" onclick="return confirm('Are you sure you want to delete this reminder?');">
                                                <i class="bi bi-trash me-2"></i> Delete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
