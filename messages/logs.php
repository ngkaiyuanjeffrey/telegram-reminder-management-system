<?php
/**
 * Telegram Reminder Management System
 * Comprehensive Message Logs & Search
 */

declare(strict_types=1);

$pageTitle = 'Message Logs';
$activeMenu = 'logs';

require_once dirname(__DIR__) . '/config/config.php';

$db = get_db();

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    require_login();

    $stmt = $db->query("
        SELECT ml.id, ml.chat_id, ml.recipient_name, ml.message_text, ml.status, ml.error_message, ml.telegram_message_id, ml.sent_time, r.title as reminder_title
        FROM message_logs ml
        LEFT JOIN reminders r ON ml.reminder_id = r.id
        ORDER BY ml.id DESC
    ");
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="telegram_message_logs_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');
    // Add BOM for UTF-8 Excel support
    fputs($output, "\xEF\xBB\xBF");

    fputcsv($output, ['Log ID', 'Sent Time', 'Status', 'Recipient Name', 'Chat ID', 'Reminder Title', 'Message Content', 'Telegram Msg ID', 'Error Details']);

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['id'],
            $row['sent_time'],
            strtoupper($row['status']),
            $row['recipient_name'] ?: 'Recipient',
            $row['chat_id'],
            $row['reminder_title'] ?: 'Direct Message',
            $row['message_text'],
            $row['telegram_message_id'] ?: '',
            $row['error_message'] ?: ''
        ]);
    }
    fclose($output);
    exit;
}

// Handle Purge / Clear Logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    require_login();
    if (!verify_csrf()) {
        set_flash('danger', 'Security session expired.');
    } else {
        try {
            $days = (int)($_POST['older_than_days'] ?? 0);
            if ($days > 0) {
                $stmt = $db->prepare("DELETE FROM message_logs WHERE sent_time < DATE_SUB(NOW(), INTERVAL :days DAY)");
                $stmt->execute(['days' => $days]);
                set_flash('success', "Logs older than {$days} days purged successfully.");
            } else {
                $db->exec("TRUNCATE TABLE message_logs");
                set_flash('success', "All message logs have been cleared.");
            }
        } catch (Throwable $e) {
            set_flash('danger', 'Error clearing logs: ' . $e->getMessage());
        }
    }
    redirect(BASE_URL . '/messages/logs.php');
}

require_once INCLUDES_PATH . '/header.php';

// Search & Filter parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$reminderFilter = (int)($_GET['reminder_id'] ?? 0);
$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$whereClauses = ['1=1'];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(ml.chat_id LIKE :s OR ml.recipient_name LIKE :s OR ml.message_text LIKE :s OR r.title LIKE :s)";
    $params['s'] = "%{$search}%";
}

if (!empty($statusFilter) && in_array($statusFilter, ['sent', 'failed'], true)) {
    $whereClauses[] = "ml.status = :st";
    $params['st'] = $statusFilter;
}

if ($reminderFilter > 0) {
    $whereClauses[] = "ml.reminder_id = :rid";
    $params['rid'] = $reminderFilter;
}

if (!empty($fromDate)) {
    $whereClauses[] = "DATE(ml.sent_time) >= :from_d";
    $params['from_d'] = $fromDate;
}

if (!empty($toDate)) {
    $whereClauses[] = "DATE(ml.sent_time) <= :to_d";
    $params['to_d'] = $toDate;
}

$whereSql = implode(' AND ', $whereClauses);

// Count Total
$totalCount = 0;
if ($db) {
    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM message_logs ml LEFT JOIN reminders r ON ml.reminder_id = r.id WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();
    } catch (Throwable $e) {}
}

$totalPages = max(1, (int)ceil($totalCount / $perPage));

// Fetch Records
$logs = [];
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT ml.*, r.title as reminder_title
            FROM message_logs ml
            LEFT JOIN reminders r ON ml.reminder_id = r.id
            WHERE {$whereSql}
            ORDER BY ml.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $logs = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log("Error querying logs: " . $e->getMessage());
    }
}

// Fetch reminders for dropdown
$allReminders = [];
if ($db) {
    try {
        $allReminders = $db->query("SELECT id, title FROM reminders ORDER BY id DESC LIMIT 50")->fetchAll();
    } catch (Throwable $e) {}
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-journal-text text-primary"></i> Message Dispatch Logs
        </h1>
        <p class="page-subtitle">Granular history of every Telegram message delivery and API response</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/messages/logs.php?export=csv" class="btn btn-outline-success rounded-pill px-3.5 shadow-sm d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-file-earmark-spreadsheet-fill"></i> Export to CSV
        </a>
        <button type="button" class="btn btn-outline-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalClearLogs">
            <i class="bi bi-trash"></i> Purge Logs
        </button>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card-custom mb-4">
    <div class="p-3">
        <form method="GET" action="<?= BASE_URL ?>/messages/logs.php" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="search" value="<?= e($search) ?>" placeholder="Search chat ID, name, message...">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="">Status: All</option>
                    <option value="sent" <?= ($statusFilter === 'sent') ? 'selected' : '' ?>>Sent</option>
                    <option value="failed" <?= ($statusFilter === 'failed') ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" name="reminder_id" onchange="this.form.submit()">
                    <option value="0">All Reminders</option>
                    <?php foreach ($allReminders as $rem): ?>
                        <option value="<?= $rem['id'] ?>" <?= ($reminderFilter === (int)$rem['id']) ? 'selected' : '' ?>><?= e($rem['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="from_date" value="<?= e($fromDate) ?>" title="From date">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">Filter</button>
                <?php if (!empty($search) || !empty($statusFilter) || $reminderFilter > 0 || !empty($fromDate) || !empty($toDate)): ?>
                    <a href="<?= BASE_URL ?>/messages/logs.php" class="btn btn-sm btn-light border rounded-pill px-3">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table Card -->
<div class="card-custom">
    <div class="card-header-custom">
        <h5 class="card-title-custom">
            <i class="bi bi-list-check text-primary"></i> Dispatch History (<?= number_format($totalCount) ?> Total)
        </h5>
        <span class="badge bg-light text-muted border">Page <?= $page ?> of <?= $totalPages ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th># Log ID</th>
                    <th>Status</th>
                    <th>Recipient</th>
                    <th>Chat ID</th>
                    <th>Message Text</th>
                    <th>Reminder Source</th>
                    <th>Timestamp</th>
                    <th>Telegram Result</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                            No message logs found matching your filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-muted small">#<?= $log['id'] ?></td>
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
                            <td>
                                <strong class="text-dark small"><?= e($log['recipient_name'] ?: 'Recipient') ?></strong>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    <code class="bg-light px-2 py-0.5 rounded text-dark small"><?= e($log['chat_id']) ?></code>
                                    <button type="button" class="btn btn-sm btn-light border p-1 rounded btn-copy" data-copy="<?= e($log['chat_id']) ?>" title="Copy">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </td>
                            <td style="max-width: 280px;">
                                <div class="text-truncate small text-dark" title="<?= e(strip_tags($log['message_text'])) ?>">
                                    <?= strip_tags($log['message_text']) ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($log['reminder_id'])): ?>
                                    <a href="<?= BASE_URL ?>/reminders/view.php?id=<?= $log['reminder_id'] ?>" class="text-decoration-none text-primary small text-truncate d-inline-block" style="max-width: 180px;">
                                        <?= e($log['reminder_title'] ?: "Reminder #{$log['reminder_id']}") ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">Direct / Test</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small text-nowrap">
                                <div><?= format_datetime($log['sent_time']) ?></div>
                                <div style="font-size: 0.75rem;"><?= time_ago($log['sent_time']) ?></div>
                            </td>
                            <td class="small">
                                <?php if ($log['status'] === 'sent'): ?>
                                    <span class="text-muted">Msg ID: <?= e($log['telegram_message_id'] ?: '-') ?></span>
                                <?php else: ?>
                                    <span class="text-danger" title="<?= e($log['error_message']) ?>">
                                        <i class="bi bi-exclamation-triangle me-1"></i><?= e($log['error_message'] ?: 'Failed') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
        <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="small text-muted">Showing <?= min($totalCount, $offset + 1) ?> - <?= min($totalCount, $offset + count($logs)) ?> of <?= $totalCount ?></span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= BASE_URL ?>/messages/logs.php?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&reminder_id=<?= $reminderFilter ?>&from_date=<?= urlencode($fromDate) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Purge Logs -->
<div class="modal fade" id="modalClearLogs" tabindex="-1" aria-labelledby="modalClearLogsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" action="<?= BASE_URL ?>/messages/logs.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear_logs">
                <div class="modal-header bg-danger text-white py-3 px-4">
                    <h5 class="modal-title fs-6 fw-bold" id="modalClearLogsLabel">
                        <i class="bi bi-trash-fill me-1"></i> Purge Message Logs
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Choose which log records you want to delete from the database.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Purge Option</label>
                        <select class="form-select" name="older_than_days">
                            <option value="30">Delete logs older than 30 days</option>
                            <option value="14">Delete logs older than 14 days</option>
                            <option value="7">Delete logs older than 7 days</option>
                            <option value="0">Delete ALL logs (Clear everything)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2.5 px-4 border-top">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4" onclick="return confirm('Confirm purge of selected logs?');">
                        Purge Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
