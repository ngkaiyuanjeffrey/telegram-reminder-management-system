<?php
/**
 * Telegram Reminder Management System
 * Admin Authentication / Login Page
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

// Redirect if already authenticated
if (is_logged_in()) {
    redirect(BASE_URL . '/admin/index.php');
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please submit the form again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Please enter both your username/email and password.';
        } else {
            $auth = login_admin($username, $password);
            if ($auth['success']) {
                $redirectUrl = $_SESSION['redirect_after_login'] ?? (BASE_URL . '/admin/index.php');
                unset($_SESSION['redirect_after_login']);
                redirect($redirectUrl);
            } else {
                $error = $auth['message'];
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
    <title>Admin Sign In | <?= e(APP_NAME) ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">
                <i class="bi bi-telegram"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1">Telegram Reminder</h4>
            <p class="text-muted small mb-0">Admin Management Portal</p>
        </div>

        <div class="auth-body">
            <!-- Flash Message Banner -->
            <?= display_flash() ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                    <div><?= e($error) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/admin/login.php">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="usernameInput" class="form-label small fw-bold text-muted">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" id="usernameInput" name="username" value="<?= e($username) ?>" required autofocus placeholder="admin or email">
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label for="passwordInput" class="form-label small fw-bold text-muted mb-0">Password</label>
                        <a href="<?= BASE_URL ?>/admin/forgot-password.php" class="text-decoration-none small text-primary">Forgot?</a>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control border-start-0 border-end-0 ps-0" id="passwordInput" name="password" required placeholder="••••••••">
                        <button type="button" class="btn btn-light border border-start-0 password-toggle-btn text-muted" data-target="#passwordInput" title="Show/Hide Password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="rememberMe" name="remember" checked>
                    <label class="form-check-label small text-muted" for="rememberMe">
                        Keep me signed in on this device
                    </label>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold py-2.5 shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Portal
                    </button>
                </div>

                <div class="p-2.5 bg-light rounded-3 text-center small text-muted border">
                    <i class="bi bi-info-circle text-primary me-1"></i> Default Demo: <strong class="text-dark">admin</strong> / <strong class="text-dark">admin123</strong>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- jQuery & Bootstrap -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
