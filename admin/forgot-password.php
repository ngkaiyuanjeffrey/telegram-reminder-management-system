<?php
/**
 * Telegram Reminder Management System
 * Admin Forgot Password
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

if (is_logged_in()) {
    redirect(BASE_URL . '/admin/index.php');
}

$error = null;
$resetLink = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');

        if (empty($username)) {
            $error = 'Please enter your username or registered email address.';
        } else {
            $db = get_db();
            if ($db) {
                try {
                    $stmt = $db->prepare("SELECT id, username, email FROM admins WHERE username = :u OR email = :e LIMIT 1");
                    $stmt->execute(['u' => $username, 'e' => $username]);
                    $admin = $stmt->fetch();

                    if ($admin) {
                        $token = bin2hex(random_bytes(32));
                        $update = $db->prepare("UPDATE admins SET reset_token = :t, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = :id");
                        $update->execute(['t' => $token, 'id' => $admin['id']]);

                        $resetLink = BASE_URL . "/admin/reset-password.php?token=" . $token;
                    } else {
                        // Keep message generic or informative
                        $error = "No admin account was found matching that username or email address.";
                    }
                } catch (Throwable $e) {
                    $error = "An error occurred: " . $e->getMessage();
                }
            } else {
                $error = "Database connection error.";
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
    <title>Forgot Password | <?= e(APP_NAME) ?></title>
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
                <i class="bi bi-key-fill"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1">Reset Password</h4>
            <p class="text-muted small mb-0">Enter your credentials to recover your account</p>
        </div>

        <div class="auth-body">
            <?= display_flash() ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                    <div><?= e($error) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($resetLink): ?>
                <div class="alert alert-success border-0 shadow-sm p-3 mb-4 rounded-3">
                    <div class="d-flex align-items-center gap-2 mb-2 fw-bold text-success">
                        <i class="bi bi-check-circle-fill fs-5"></i> Password Reset Link Generated!
                    </div>
                    <p class="small text-muted mb-3">
                        A secure password reset link has been generated (valid for 60 minutes).
                    </p>
                    <div class="d-grid mb-2">
                        <a href="<?= e($resetLink) ?>" class="btn btn-success fw-bold rounded-pill">
                            <i class="bi bi-shield-check me-1"></i> Proceed to Reset Password
                        </a>
                    </div>
                    <div class="small text-muted text-break mt-2">
                        <code><?= e($resetLink) ?></code>
                    </div>
                </div>

                <div class="text-center">
                    <a href="<?= BASE_URL ?>/admin/login.php" class="text-decoration-none small text-primary fw-semibold">
                        <i class="bi bi-arrow-left me-1"></i> Back to Sign In
                    </a>
                </div>

            <?php else: ?>

                <form method="POST" action="<?= BASE_URL ?>/admin/forgot-password.php">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="usernameInput" class="form-label small fw-bold text-muted">Admin Username or Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" id="usernameInput" name="username" value="<?= e($username) ?>" required autofocus placeholder="admin or email">
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold py-2.5 shadow-sm">
                            <i class="bi bi-send-check me-1"></i> Generate Reset Link
                        </button>
                    </div>

                    <div class="text-center">
                        <a href="<?= BASE_URL ?>/admin/login.php" class="text-decoration-none small text-muted">
                            <i class="bi bi-arrow-left me-1"></i> Back to Sign In
                        </a>
                    </div>
                </form>

            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
