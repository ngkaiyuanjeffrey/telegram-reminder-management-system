<?php
/**
 * Telegram Reminder Management System
 * Telegram Recipients List & Management
 */

declare(strict_types=1);

$pageTitle = 'Telegram Recipients';
$activeMenu = 'users';

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/header.php';

$db = get_db();
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT u.*, 
           COUNT(DISTINCT rr.reminder_id) as reminder_count,
           COUNT(DISTINCT ml.id) as log_count
    FROM users u
    LEFT JOIN reminder_recipients rr ON u.id = rr.user_id
    LEFT JOIN message_logs ml ON u.chat_id = ml.chat_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.name LIKE :s OR u.chat_id LIKE :s OR u.username LIKE :s OR u.phone LIKE :s)";
    $params['s'] = "%{$search}%";
}

if (!empty($statusFilter) && in_array($statusFilter, ['active', 'inactive'], true)) {
    $sql .= " AND u.status = :st";
    $params['st'] = $statusFilter;
}

$sql .= " GROUP BY u.id ORDER BY u.id DESC";

$users = [];
if ($db) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log("Error fetching recipients: " . $e->getMessage());
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-people-fill text-primary"></i> Telegram Recipients
        </h1>
        <p class="page-subtitle">Manage registered Telegram users and their Chat IDs</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/users/add.php" class="btn btn-primary rounded-pill px-3.5 shadow-sm d-inline-flex align-items-center gap-1.5">
            <i class="bi bi-person-plus-fill"></i> Add Recipient
        </a>
    </div>
</div>

<!-- Helper Accordion: How to get Telegram Chat ID -->
<div class="accordion mb-4" id="accordionHelp">
    <div class="accordion-item border-0 shadow-sm rounded-3 overflow-hidden">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed bg-light py-2.5 px-3 text-dark fw-semibold small" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHowToChatId">
                <i class="bi bi-info-circle-fill text-primary me-2"></i> How do recipients find their Telegram Chat ID?
            </button>
        </h2>
        <div id="collapseHowToChatId" class="accordion-collapse collapse" data-bs-parent="#accordionHelp">
            <div class="accordion-body small text-muted bg-white p-3">
                <ol class="mb-2 ps-3">
                    <li class="mb-1"><strong>Step 1:</strong> The recipient opens Telegram and searches for <code class="text-primary">@userinfobot</code> or <code class="text-primary">@RawDataBot</code>.</li>
                    <li class="mb-1"><strong>Step 2:</strong> They send any message or click <code>/start</code>.</li>
                    <li class="mb-1"><strong>Step 3:</strong> The bot replies immediately with their numerical <strong>Id</strong> (e.g. <code>987654321</code>).</li>
                    <li><strong>Step 4:</strong> Copy that numerical ID and paste it into the <strong>Chat ID</strong> field below.</li>
                </ol>
                <div class="alert alert-light border mb-0 p-2 text-muted">
                    <i class="bi bi-lightbulb-fill text-warning me-1"></i> <strong>Tip:</strong> Users MUST have opened a chat with your Telegram Bot and clicked <code>/start</code> at least once before the bot can send them scheduled messages.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Search Bar -->
<div class="card-custom mb-4">
    <div class="p-3">
        <form method="GET" action="<?= BASE_URL ?>/users/index.php" class="row g-2 align-items-center">
            <div class="col-md-6 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="search" value="<?= e($search) ?>" placeholder="Search by name, chat ID, username, phone...">
                </div>
            </div>
            <div class="col-md-3 col-lg-3">
                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3 col-lg-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill px-3">Filter</button>
                <?php if (!empty($search) || !empty($statusFilter)): ?>
                    <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-light border rounded-pill px-3">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Recipients Table -->
<div class="card-custom">
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Telegram Chat ID</th>
                    <th>Username</th>
                    <th>Reminders</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                            No Telegram recipients found.
                            <div class="mt-2">
                                <a href="<?= BASE_URL ?>/users/add.php" class="btn btn-sm btn-primary rounded-pill">Add First Recipient</a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $idx => $u): ?>
                        <tr>
                            <td class="text-muted"><?= $idx + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-info-subtle text-info rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px;">
                                        <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <strong class="text-dark"><?= e($u['name']) ?></strong>
                                        <?php if (!empty($u['phone'])): ?>
                                            <div class="text-muted small"><i class="bi bi-telephone me-1"></i><?= e($u['phone']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1.5">
                                    <code class="bg-light px-2 py-1 rounded text-dark font-monospace fw-bold"><?= e($u['chat_id']) ?></code>
                                    <button type="button" class="btn btn-sm btn-light border p-1 rounded btn-copy" data-copy="<?= e($u['chat_id']) ?>" title="Copy Chat ID">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($u['username'])): ?>
                                    <a href="https://t.me/<?= e(ltrim($u['username'], '@')) ?>" target="_blank" class="text-decoration-none text-primary small">
                                        @<?= e(ltrim($u['username'], '@')) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace"><?= (int)$u['reminder_count'] ?> scheduled</span>
                            </td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-2.5 py-1">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= format_datetime($u['created_at']) ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <!-- Test Send Button -->
                                    <button type="button" class="btn btn-outline-primary btn-test-chat" data-chat-id="<?= e($u['chat_id']) ?>" data-name="<?= e($u['name']) ?>" title="Send Test Message">
                                        <i class="bi bi-send-fill"></i>
                                    </button>
                                    <!-- Edit Button -->
                                    <a href="<?= BASE_URL ?>/users/edit.php?id=<?= $u['id'] ?>" class="btn btn-light border" title="Edit recipient">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <!-- Delete Button -->
                                    <a href="<?= BASE_URL ?>/users/delete.php?id=<?= $u['id'] ?>&token=<?= csrf_token() ?>" class="btn btn-outline-danger" title="Delete recipient" onclick="return confirm('Are you sure you want to delete recipient <?= e($u['name']) ?>?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
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
