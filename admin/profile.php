<?php
/**
 * Telegram Reminder Management System
 * Admin Profile Management
 */

declare(strict_types=1);

$pageTitle = 'Admin Profile';
$activeMenu = 'profile';

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/header.php';

$currentAdmin = current_admin();
$db = get_db();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please submit the form again.';
    } else {
        $action = $_POST['action'] ?? 'update_profile';

        if ($action === 'update_profile') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');

            if (empty($fullName) || empty($email) || empty($username)) {
                $error = 'All fields are required.';
            } else {
                try {
                    // Check username uniqueness
                    $stmt = $db->prepare("SELECT id FROM admins WHERE (username = :u OR email = :e) AND id != :id LIMIT 1");
                    $stmt->execute(['u' => $username, 'e' => $email, 'id' => $currentAdmin['id']]);
                    if ($stmt->fetch()) {
                        $error = 'Username or email is already in use by another admin.';
                    } else {
                        $update = $db->prepare("UPDATE admins SET full_name = :fn, email = :e, username = :u WHERE id = :id");
                        $update->execute(['fn' => $fullName, 'e' => $email, 'u' => $username, 'id' => $currentAdmin['id']]);
                        $_SESSION['admin_name'] = $fullName;
                        $_SESSION['admin_username'] = $username;
                        $_SESSION['admin_email'] = $email;
                        set_flash('success', 'Profile updated successfully.');
                        redirect(BASE_URL . '/admin/profile.php');
                    }
                } catch (Throwable $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'change_password') {
            $currentPass = (string)($_POST['current_password'] ?? '');
            $newPass = (string)($_POST['new_password'] ?? '');
            $confirmPass = (string)($_POST['confirm_password'] ?? '');

            if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
                $error = 'All password fields are required.';
            } elseif (strlen($newPass) < 6) {
                $error = 'New password must be at least 6 characters long.';
            } elseif ($newPass !== $confirmPass) {
                $error = 'New passwords do not match.';
            } else {
                try {
                    $stmt = $db->prepare("SELECT password FROM admins WHERE id = :id");
                    $stmt->execute(['id' => $currentAdmin['id']]);
                    $storedHash = $stmt->fetchColumn();

                    if (!password_verify($currentPass, (string)$storedHash)) {
                        $error = 'Incorrect current password.';
                    } else {
                        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                        $update = $db->prepare("UPDATE admins SET password = :p WHERE id = :id");
                        $update->execute(['p' => $newHash, 'id' => $currentAdmin['id']]);
                        set_flash('success', 'Your password has been changed successfully.');
                        redirect(BASE_URL . '/admin/profile.php');
                    }
                } catch (Throwable $e) {
                    $error = 'Error updating password: ' . $e->getMessage();
                }
            }
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-person-gear text-primary"></i> Admin Profile
        </h1>
        <p class="page-subtitle">Manage your account information and credentials</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
        <div><?= e($error) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Profile Info Form -->
    <div class="col-lg-6">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <h5 class="card-title-custom">
                    <i class="bi bi-person-circle text-primary"></i> Account Details
                </h5>
            </div>
            <div class="p-4">
                <form method="POST" action="<?= BASE_URL ?>/admin/profile.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="mb-3">
                        <label for="fullNameInput" class="form-label small fw-bold text-muted">Full Name</label>
                        <input type="text" class="form-control" id="fullNameInput" name="full_name" value="<?= e($currentAdmin['full_name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="usernameInput" class="form-label small fw-bold text-muted">Username</label>
                        <input type="text" class="form-control" id="usernameInput" name="username" value="<?= e($currentAdmin['username'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="emailInput" class="form-label small fw-bold text-muted">Email Address</label>
                        <input type="email" class="form-control" id="emailInput" name="email" value="<?= e($currentAdmin['email'] ?? '') ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">Role</label>
                        <div>
                            <span class="badge bg-primary px-3 py-2 text-uppercase"><?= e($currentAdmin['role'] ?? 'admin') ?></span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="bi bi-check-lg me-1"></i> Update Details
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Change Form -->
    <div class="col-lg-6">
        <div class="card-custom h-100">
            <div class="card-header-custom">
                <h5 class="card-title-custom">
                    <i class="bi bi-shield-lock text-warning"></i> Change Password
                </h5>
            </div>
            <div class="p-4">
                <form method="POST" action="<?= BASE_URL ?>/admin/profile.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label for="curPassInput" class="form-label small fw-bold text-muted">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="curPassInput" name="current_password" required>
                            <button type="button" class="btn btn-light border password-toggle-btn" data-target="#curPassInput">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="newPassInput" class="form-label small fw-bold text-muted">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="newPassInput" name="new_password" minlength="6" required>
                            <button type="button" class="btn btn-light border password-toggle-btn" data-target="#newPassInput">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="confirmPassInput" class="form-label small fw-bold text-muted">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirmPassInput" name="confirm_password" minlength="6" required>
                            <button type="button" class="btn btn-light border password-toggle-btn" data-target="#confirmPassInput">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold">
                            <i class="bi bi-key-fill me-1"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
