<?php
/**
 * Telegram Reminder Management System
 * Edit Telegram Recipient
 */

declare(strict_types=1);

$pageTitle = 'Edit Recipient';
$activeMenu = 'users';

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/header.php';

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$error = null;

if (!$db || $id <= 0) {
    set_flash('danger', 'Invalid recipient requested.');
    redirect(BASE_URL . '/users/index.php');
}

// Fetch recipient
$stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('danger', 'Recipient not found.');
    redirect(BASE_URL . '/users/index.php');
}

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
            $error = 'Name and Telegram Chat ID are required.';
        } else {
            try {
                // Check if chat_id in use by another user
                $check = $db->prepare("SELECT id FROM users WHERE chat_id = :c AND id != :id LIMIT 1");
                $check->execute(['c' => $chatId, 'id' => $id]);
                if ($check->fetch()) {
                    $error = "Another recipient is already using Chat ID '{$chatId}'.";
                } else {
                    $update = $db->prepare("UPDATE users SET name = :n, chat_id = :c, username = :u, phone = :p, status = :s WHERE id = :id");
                    $update->execute([
                        'n' => $name,
                        'c' => $chatId,
                        'u' => ltrim($username, '@'),
                        'p' => $phone,
                        's' => $status,
                        'id' => $id
                    ]);

                    set_flash('success', "Recipient '{$name}' updated successfully.");
                    redirect(BASE_URL . '/users/index.php');
                }
            } catch (Throwable $e) {
                $error = 'Error updating recipient: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-pencil-square text-primary"></i> Edit Recipient
        </h1>
        <p class="page-subtitle">Update recipient profile and Telegram connection details</p>
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
                    <i class="bi bi-person-gear text-primary"></i> Edit: <?= e($user['name']) ?>
                </h5>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill btn-test-chat" data-chat-id="<?= e($user['chat_id']) ?>" data-name="<?= e($user['name']) ?>">
                    <i class="bi bi-send-fill me-1"></i> Send Test Message
                </button>
            </div>
            <div class="p-4">
                <form method="POST" action="<?= BASE_URL ?>/users/edit.php?id=<?= $id ?>">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="inputName" class="form-label small fw-bold text-muted">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="inputName" name="name" value="<?= e($user['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="inputChatId" class="form-label small fw-bold text-muted">Telegram Chat ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="inputChatId" name="chat_id" value="<?= e($user['chat_id']) ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="inputUsername" class="form-label small fw-bold text-muted">Telegram Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">@</span>
                                <input type="text" class="form-control" id="inputUsername" name="username" value="<?= e($user['username'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="inputPhone" class="form-label small fw-bold text-muted">Phone Number</label>
                            <input type="text" class="form-control" id="inputPhone" name="phone" value="<?= e($user['phone'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">Status</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="statusActive" value="active" <?= ($user['status'] === 'active') ? 'checked' : '' ?>>
                                <label class="form-check-label text-dark" for="statusActive">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2">Active</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="statusInactive" value="inactive" <?= ($user['status'] === 'inactive') ? 'checked' : '' ?>>
                                <label class="form-check-label text-dark" for="statusInactive">
                                    <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-2">Inactive</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-light border rounded-pill px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">
                            <i class="bi bi-check-lg me-1"></i> Update Recipient
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
