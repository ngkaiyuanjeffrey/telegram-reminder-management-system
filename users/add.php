<?php
/**
 * Telegram Reminder Management System
 * Add New Telegram Recipient
 */

declare(strict_types=1);

$pageTitle = 'Add Recipient';
$activeMenu = 'add_user';

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/header.php';

$error = null;
$db = get_db();

// Pre-fill from query params if imported from bot updates
$name = trim($_GET['name'] ?? '');
$chatId = trim($_GET['chat_id'] ?? '');
$username = trim($_GET['username'] ?? '');
$phone = '';
$status = 'active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please submit again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $chatId = trim($_POST['chat_id'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if (empty($name) || empty($chatId)) {
            $error = 'Name and Telegram Chat ID are required fields.';
        } else {
            try {
                // Check if chat_id already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE chat_id = :c LIMIT 1");
                $stmt->execute(['c' => $chatId]);
                if ($stmt->fetch()) {
                    $error = "A recipient with Chat ID '{$chatId}' already exists in the system.";
                } else {
                    $insert = $db->prepare("INSERT INTO users (name, chat_id, username, phone, status) VALUES (:n, :c, :u, :p, :s)");
                    $insert->execute([
                        'n' => $name,
                        'c' => $chatId,
                        'u' => ltrim($username, '@'),
                        'p' => $phone,
                        's' => $status
                    ]);

                    set_flash('success', "Recipient '{$name}' added successfully!");
                    redirect(BASE_URL . '/users/index.php');
                }
            } catch (Throwable $e) {
                $error = 'Database Error: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-person-plus-fill text-primary"></i> Add Telegram Recipient
        </h1>
        <p class="page-subtitle">Register a new Telegram user to receive scheduled notifications</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-light border rounded-pill px-3.5">
            <i class="bi bi-arrow-left me-1"></i> Back to Recipients
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

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-custom">
            <div class="card-header-custom">
                <h5 class="card-title-custom">
                    <i class="bi bi-person-lines-fill text-primary"></i> Recipient Information
                </h5>
            </div>
            <div class="p-4">
                <form method="POST" action="<?= BASE_URL ?>/users/add.php">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="inputName" class="form-label small fw-bold text-muted">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="inputName" name="name" value="<?= e($name) ?>" required placeholder="e.g. John Doe">
                        </div>
                        <div class="col-md-6">
                            <label for="inputChatId" class="form-label small fw-bold text-muted">Telegram Chat ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="inputChatId" name="chat_id" value="<?= e($chatId) ?>" required placeholder="e.g. 123456789">
                            <div class="form-text small">Numerical Telegram ID (from @userinfobot or /start).</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="inputUsername" class="form-label small fw-bold text-muted">Telegram Username (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">@</span>
                                <input type="text" class="form-control" id="inputUsername" name="username" value="<?= e($username) ?>" placeholder="johndoe">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="inputPhone" class="form-label small fw-bold text-muted">Phone Number (Optional)</label>
                            <input type="text" class="form-control" id="inputPhone" name="phone" value="<?= e($phone) ?>" placeholder="+1234567890">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">Status</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="statusActive" value="active" <?= ($status === 'active') ? 'checked' : '' ?>>
                                <label class="form-check-label text-dark" for="statusActive">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2">Active</span>
                                    <span class="small text-muted ms-1">(Can receive scheduled reminders)</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="statusInactive" value="inactive" <?= ($status === 'inactive') ? 'checked' : '' ?>>
                                <label class="form-check-label text-dark" for="statusInactive">
                                    <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-2">Inactive</span>
                                    <span class="small text-muted ms-1">(Temporarily paused)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-light border rounded-pill px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">
                            <i class="bi bi-person-check-fill me-1"></i> Save Recipient
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
