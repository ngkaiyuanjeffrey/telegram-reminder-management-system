<?php
/**
 * Telegram Reminder Management System
 * Admin Reset Password Submission
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = null;
$validToken = false;
$adminRecord = null;

$db = get_db();
if ($db && !empty($token)) {
    try {
        $stmt = $db->prepare("SELECT id, username, email FROM admins WHERE reset_token = :t AND reset_expires > NOW() LIMIT 1");
        $stmt->execute(['t' => $token]);
        $adminRecord = $stmt->fetch();
        if ($adminRecord) {
            $validToken = true;
        } else {
            $error = "This password reset token is invalid or has expired. Please request a new one.";
        }
    } catch (Throwable $e) {
        $error = "Database error: " . $e->getMessage();
    }
} else {
    $error = "No reset token was provided.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please submit the form again.';
    } else {
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirmPass = (string)($_POST['confirm_password'] ?? '');

        if (strlen($newPass) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'The passwords do not match. Please verify and re-type.';
        } else {
            try {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $update = $db->prepare("UPDATE admins SET password = :p, reset_token = NULL, reset_expires = NULL WHERE id = :id");
                $update->execute(['p' => $hash, 'id' => $adminRecord['id']]);

                set_flash('success', 'Your password has been updated successfully! You can now log in with your new credentials.');
                redirect(BASE_URL . '/admin/login.php');
            } catch (Throwable $e) {
                $error = "Failed to update password: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password | <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1">Set New Password</h4>
            <p class="text-muted small mb-0">Create a secure password for your account</p>
        </div>

        <div class="auth-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                    <div><?= e($error) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($validToken): ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/reset-password.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div class="p-2.5 bg-light rounded-3 text-muted small mb-3 border">
                        Resetting password for: <strong class="text-dark"><?= e($adminRecord['username']) ?></strong> (<?= e($adminRecord['email']) ?>)
                    </div>

                    <div class="mb-3">
                        <label for="newPassInput" class="form-label small fw-bold text-muted">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control border-start-0 border-end-0 ps-0" id="newPassInput" name="new_password" required minlength="6" placeholder="At least 6 characters">
                            <button type="button" class="btn btn-light border border-start-0 password-toggle-btn text-muted" data-target="#newPassInput">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="confirmPassInput" class="form-label small fw-bold text-muted">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-shield-check"></i></span>
                            <input type="password" class="form-control border-start-0 border-end-0 ps-0" id="confirmPassInput" name="confirm_password" required minlength="6" placeholder="Re-enter password">
                            <button type="button" class="btn btn-light border border-start-0 password-toggle-btn text-muted" data-target="#confirmPassInput">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold py-2.5 shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Update Password & Sign In
                        </button>
                    </div>
                </form>

            <?php else: ?>

                <div class="text-center py-3">
                    <a href="<?= BASE_URL ?>/admin/forgot-password.php" class="btn btn-outline-primary rounded-pill px-4">
                        <i class="bi bi-arrow-repeat me-1"></i> Request New Reset Link
                    </a>
                </div>

            <?php endif; ?>

            <div class="text-center mt-3">
                <a href="<?= BASE_URL ?>/admin/login.php" class="text-decoration-none small text-muted">
                    <i class="bi bi-arrow-left me-1"></i> Back to Sign In
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
