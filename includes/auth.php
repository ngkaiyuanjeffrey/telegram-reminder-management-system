<?php
/**
 * Telegram Reminder Management System
 * Authentication & Session Security Handler
 */

declare(strict_types=1);

/**
 * Check if an admin is currently authenticated
 */
function is_logged_in(): bool {
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_username']);
}

/**
 * Require authentication for protected pages
 */
function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        set_flash('warning', 'Please sign in to access the management portal.');
        redirect(BASE_URL . '/admin/login.php');
    }
}

/**
 * Check if the currently logged in admin has superadmin privileges
 */
function is_superadmin(): bool {
    return is_logged_in() && ($_SESSION['admin_role'] ?? '') === 'superadmin';
}

/**
 * Require superadmin access for sensitive administrative actions
 */
function require_superadmin(): void {
    require_login();
    if (!is_superadmin()) {
        set_flash('danger', 'Access denied. You need Super Administrator privileges for this action.');
        redirect(BASE_URL . '/admin/index.php');
    }
}

/**
 * Get the current logged-in admin record
 */
function current_admin(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    $db = get_db();
    if (!$db) return null;

    try {
        $stmt = $db->prepare("SELECT id, username, email, full_name, role, created_at FROM admins WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        return $admin ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Authenticate admin by username/email and password
 */
function login_admin(string $usernameOrEmail, string $password): array {
    $db = get_db();
    if (!$db) {
        return ['success' => false, 'message' => 'Database connection unavailable. Please run the installer.'];
    }

    try {
        $stmt = $db->prepare("SELECT * FROM admins WHERE username = :u OR email = :e LIMIT 1");
        $stmt->execute(['u' => $usernameOrEmail, 'e' => $usernameOrEmail]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password'])) {
            return ['success' => false, 'message' => 'Invalid username/email or password.'];
        }

        // Check if password rehash is required
        if (password_needs_rehash($admin['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $db->prepare("UPDATE admins SET password = :p WHERE id = :id");
            $updateStmt->execute(['p' => $newHash, 'id' => $admin['id']]);
        }

        // Prevent Session Fixation
        session_regenerate_id(true);

        // Store session variables
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_name'] = $admin['full_name'] ?: $admin['username'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['last_activity'] = time();

        return ['success' => true, 'admin' => $admin];
    } catch (Throwable $e) {
        error_log("Login Exception: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during authentication: ' . $e->getMessage()];
    }
}

/**
 * Log out admin and destroy session
 */
function logout_admin(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
