<?php
/**
 * Telegram Reminder Management System
 * Admin Dashboard & Overview
 */

declare(strict_types=1);

$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/header.php';

$stats = get_system_stats();
$db = get_db();

// Check Telegram Bot Token
$botToken = get_setting('bot_token', '');
$botUsername = get_setting('bot_username', '');

// Fetch Upcoming Pending Reminders
$upcomingReminders = [];
if ($db) {
    try {
        $stmt = $db->query("
            SELECT r.*, 
                   COUNT(DISTINCT rm.id) as message_count,
                   COUNT(DISTINCT rr.id) as recipient_count
            FROM reminders r
            LEFT JOIN reminder_messages rm ON r.id = rm.reminder_id
            LEFT JOIN reminder_recipients rr ON r.id = rr.reminder_id
            WHERE r.status = 'pending'
            GROUP BY r.id
            ORDER BY r.scheduled_time ASC
            LIMIT 5
        ");
        $upcomingReminders = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log("Error fetching upcoming reminders: " . $e->getMessage());
    }
}

// Fetch Recent Logs
$recentLogs = [];
if ($db) {
    try {
        $stmt = $db->query("
            SELECT l.*, r.title as reminder_title
            FROM message_logs l
            LEFT JOIN reminders r ON l.reminder_id = r.id
            ORDER BY l.id DESC
            LIMIT 7
        ");
        $recentLogs = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log("Error fetching recent logs: " . $e->getMessage());
    }
}

// Fetch 7-Day message stats for chart
$chartLabels = [];
$chartSentData = [];
$chartFailedData = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('M d', strtotime($date));
    $chartSentData[$date] = 0;
    $chartFailedData[$date] = 0;
}

if ($db) {
    try {
        $startDate = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $stmt = $db->prepare("
            SELECT DATE(sent_time) as log_date, status, COUNT(*) as cnt
            FROM message_logs
            WHERE sent_time >= :start
            GROUP BY DATE(sent_time), status
        ");
        $stmt->execute(['start' => $startDate]);
        while ($row = $stmt->fetch()) {
            $d = $row['log_date'];
            if ($row['status'] === 'sent' && isset($chartSentData[$d])) {
                $chartSentData[$d] = (int)$row['cnt'];
            } elseif ($row['status'] === 'failed' && isset($chartFailedData[$d])) {
                $chartFailedData[$d] = (int)$row['cnt'];
            }
        }
    } catch (Throwable $e) {}
}
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-grid-1x2-fill text-primary"></i> Dashboard Overview
        </h1>
        <p class="page-subtitle">Real-time metrics, scheduled tasks, and Telegram delivery activity</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/reminders/create.php" class="btn btn-primary rounded-pill px-3.5 shadow-sm d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-plus-circle"></i> Create Reminder
        </a>
    </div>
</div>

<!-- Telegram Bot Alert / Status Banner -->
<?php if (empty($botToken)): ?>
    <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 rounded-3 p-3">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-warning text-dark p-2 rounded-circle d-flex align-items-center justify-content-center">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            </div>
            <div>
                <strong class="text-dark">Telegram Bot Token Not Configured!</strong>
                <div class="text-muted small">Reminders cannot be delivered to users until you connect your Telegram Bot API token.</div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/admin/settings.php" class="btn btn-warning btn-sm fw-bold rounded-pill px-3">
            <i class="bi bi-gear-fill me-1"></i> Configure Bot Now
        </a>
    </div>
<?php endif; ?>

<!-- Metric Statistics Cards -->
<div class="row g-3 mb-4">
    <!-- Total Reminders -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="stat-card primary">
            <div>
                <div class="stat-title">Total Scheduled</div>
                <div class="stat-value"><?= number_format($stats['total_reminders']) ?></div>
            </div>
            <div class="stat-icon primary">
                <i class="bi bi-alarm"></i>
            </div>
        </div>
    </div>

    <!-- Pending Reminders -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="stat-card warning">
            <div>
                <div class="stat-title">Pending</div>
                <div class="stat-value"><?= number_format($stats['pending_reminders']) ?></div>
            </div>
            <div class="stat-icon warning">
                <i class="bi bi-clock-history"></i>
            </div>
        </div>
    </div>

    <!-- Sent Reminders -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="stat-card success">
            <div>
                <div class="stat-title">Sent</div>
                <div class="stat-value"><?= number_format($stats['sent_reminders']) ?></div>
            </div>
            <div class="stat-icon success">
                <i class="bi bi-check2-circle"></i>
            </div>
        </div>
    </div>

    <!-- Failed Reminders -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="stat-card danger">
            <div>
                <div class="stat-title">Failed</div>
                <div class="stat-value"><?= number_format($stats['failed_reminders']) ?></div>
            </div>
            <div class="stat-icon danger">
                <i class="bi bi-x-circle"></i>
            </div>
        </div>
    </div>

    <!-- Total Recipients -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="stat-card purple">
            <div>
                <div class="stat-title">Recipients</div>
                <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
            </div>
            <div class="stat-icon purple">
                <i class="bi bi-people"></i>
            </div>
        </div>
    </div>

    <!-- Messages Sent Today -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="stat-card info">
            <div>
                <div class="stat-title">Today's Sent</div>
                <div class="stat-value"><?= number_format($stats['messages_sent_today']) ?></div>
            </div>
            <div class="stat-icon info">
                <i class="bi bi-send-check"></i>
            </div>
        </div>
    </div>
</div>

<!-- Main Row: Chart & Upcoming Reminders -->
<div class="row g-4 mb-4">
    <!-- 7-Day Activity Chart -->
    <div class="col-lg-7">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <h5 class="card-title-custom">
                    <i class="bi bi-graph-up-arrow text-primary"></i> 7-Day Message Dispatch Activity
                </h5>
                <span class="badge bg-light text-muted border">Daily Trend</span>
            </div>
            <div class="p-3">
                <div style="height: 280px; position: relative;">
                    <canvas id="dispatchChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Pending Reminders -->
    <div class="col-lg-5">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <h5 class="card-title-custom">
                    <i class="bi bi-hourglass-split text-warning"></i> Next Pending Reminders
                </h5>
                <a href="<?= BASE_URL ?>/reminders/index.php?status=pending" class="btn btn-sm btn-light border small">View All</a>
            </div>
            <div class="p-0">
                <?php if (empty($upcomingReminders)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-check fs-1 d-block mb-2 text-secondary"></i>
                        No pending reminders scheduled.
                        <div class="mt-2">
                            <a href="<?= BASE_URL ?>/reminders/create.php" class="btn btn-sm btn-primary rounded-pill">Create New</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcomingReminders as $reminder): ?>
                            <div class="list-group-item p-3 d-flex align-items-center justify-content-between hover-bg">
                                <div class="me-2 text-truncate">
                                    <div class="fw-bold text-dark text-truncate mb-1">
                                        <a href="<?= BASE_URL ?>/reminders/view.php?id=<?= $reminder['id'] ?>" class="text-decoration-none text-dark">
                                            <?= e($reminder['title']) ?>
                                        </a>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 text-muted small flex-wrap">
                                        <span><i class="bi bi-calendar-event text-primary me-1"></i><?= format_datetime($reminder['scheduled_time']) ?></span>
                                        <span>&bull;</span>
                                        <span class="badge bg-light text-dark border"><?= (int)$reminder['message_count'] ?> msgs</span>
                                        <span>&bull;</span>
                                        <span class="badge bg-light text-dark border"><?= (int)$reminder['recipient_count'] ?> users</span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
                                    <a href="<?= BASE_URL ?>/reminders/view.php?id=<?= $reminder['id'] ?>" class="btn btn-sm btn-light border" title="View details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>/reminders/send_now.php?id=<?= $reminder['id'] ?>&token=<?= csrf_token() ?>" class="btn btn-sm btn-outline-primary" title="Send immediately" onclick="return confirm('Send this reminder sequence now?');">
                                        <i class="bi bi-send-fill"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Dispatch Logs -->
<div class="card-custom">
    <div class="card-header-custom">
        <h5 class="card-title-custom">
            <i class="bi bi-journal-text text-info"></i> Recent Message Dispatch Logs
        </h5>
        <a href="<?= BASE_URL ?>/messages/logs.php" class="btn btn-sm btn-light border small">
            <i class="bi bi-list-ul me-1"></i> Full Log History
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Recipient</th>
                    <th>Telegram Chat ID</th>
                    <th>Message Preview</th>
                    <th>Reminder</th>
                    <th>Sent At</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentLogs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-1 text-secondary"></i>
                            No messages have been dispatched yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td>
                                <?php if ($log['status'] === 'sent'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                        <i class="bi bi-check-circle-fill me-1"></i>Sent
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1" title="<?= e($log['error_message'] ?? '') ?>">
                                        <i class="bi bi-x-circle-fill me-1"></i>Failed
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold text-dark">
                                <?= e($log['recipient_name'] ?: 'Recipient') ?>
                            </td>
                            <td>
                                <code class="bg-light px-2 py-1 rounded text-dark"><?= e($log['chat_id']) ?></code>
                            </td>
                            <td style="max-width: 260px;" class="text-truncate">
                                <span class="text-muted small"><?= strip_tags($log['message_text']) ?></span>
                            </td>
                            <td class="text-truncate" style="max-width: 180px;">
                                <?= e($log['reminder_title'] ?: 'Direct / Test') ?>
                            </td>
                            <td class="text-muted small">
                                <?= time_ago($log['sent_time']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('dispatchChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [
                    {
                        label: 'Sent Successfully',
                        data: <?= json_encode(array_values($chartSentData)) ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 6
                    },
                    {
                        label: 'Failed Messages',
                        data: <?= json_encode(array_values($chartFailedData)) ?>,
                        backgroundColor: '#ef4444',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: '#f1f5f9'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
