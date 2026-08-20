<?php
/**
 * Telegram Reminder Management System
 * Admin Accounts Management (Multiple Admin Support)
 */

declare(strict_types=1);

$pageTitle = 'Administrator Accounts';
$activeMenu = 'admins';

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/header.php';

// Superadmin access required
require_superadmin();

$db = get_db();
$error = null;

// Handle Add / Edit / Delete Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please submit the form again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $username = trim($_POST['username'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $role = ($_POST['role'] ?? 'admin') === 'superadmin' ? 'superadmin' : 'admin';

            if (empty($username) || empty($email) || empty($password)) {
                $error = 'Please fill in all required fields.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                try {
                    $stmt = $db->prepare("SELECT id FROM admins WHERE username = :u OR email = :e LIMIT 1");
                    $stmt->execute(['u' => $username, 'e' => $email]);
                    if ($stmt->fetch()) {
                        $error = 'An admin with this username or email already exists.';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $insert = $db->prepare("INSERT INTO admins (username, password, email, full_name, role) VALUES (:u, :p, :e, :fn, :r)");
                        $insert->execute([
                            'u' => $username,
                            'p' => $hash,
                            'e' => $email,
                            'fn' => $fullName ?: $username,
                            'r' => $role
                        ]);
                        set_flash('success', "Administrator '{$username}' created successfully.");
                        redirect(BASE_URL . '/admin/admins.php');
                    }
                } catch (Throwable $e) {
                    $error = 'Error creating admin: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $adminId = (int)($_POST['admin_id'] ?? 0);
            if ($adminId === (int)$_SESSION['admin_id']) {
                $error = 'You cannot delete your own account.';
            } else {
                try {
                    $stmt = $db->prepare("DELETE FROM admins WHERE id = :id");
                    $stmt->execute(['id' => $adminId]);
                    set_flash('success', 'Admin deleted successfully.');
                    redirect(BASE_URL . '/admin/admins.php');
                } catch (Throwable $e) {
                    $error = 'Error deleting admin: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all administrators
$admins = [];
if ($db) {
    try {
        $stmt = $db->query("SELECT id, username, email, full_name, role, created_at FROM admins ORDER BY id ASC");
        $admins = $stmt->fetchAll();
    } catch (Throwable $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-shield-lock-fill text-primary"></i> Administrator Accounts
        </h1>
        <p class="page-subtitle">Manage portal administrators and role-based permissions</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary rounded-pill px-3.5 shadow-sm d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#modalAddAdmin">
            <i class="bi bi-person-plus-fill"></i> Add New Admin
        </button>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
        <div><?= e($error) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card-custom">
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th># ID</th>
                    <th>Administrator</th>
                    <th>Username</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Registered</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td class="text-muted">#<?= $admin['id'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px;">
                                    <?= strtoupper(substr($admin['full_name'] ?: $admin['username'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?= e($admin['full_name'] ?: $admin['username']) ?></div>
                                    <?php if ($admin['id'] == $_SESSION['admin_id']): ?>
                                        <span class="badge bg-light text-primary border small">You (Current)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><code><?= e($admin['username']) ?></code></td>
                        <td><?= e($admin['email']) ?></td>
                        <td>
                            <?php if ($admin['role'] === 'superadmin'): ?>
                                <span class="badge bg-purple px-2.5 py-1 rounded-pill">Super Admin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary px-2.5 py-1 rounded-pill">Admin</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= format_datetime($admin['created_at']) ?></td>
                        <td class="text-end">
                            <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                <form method="POST" action="<?= BASE_URL ?>/admin/admins.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete administrator <?= e($admin['username']) ?>?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="Delete Admin">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add New Admin -->
<div class="modal fade" id="modalAddAdmin" tabindex="-1" aria-labelledby="modalAddAdminLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form method="POST" action="<?= BASE_URL ?>/admin/admins.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header bg-primary text-white py-3 px-4">
                    <h5 class="modal-title fs-6 fw-bold" id="modalAddAdminLabel">
                        <i class="bi bi-person-plus-fill me-1"></i> Add Administrator Account
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Full Name</label>
                        <input type="text" class="form-control" name="full_name" required placeholder="e.g. Jane Doe">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" required placeholder="e.g. janedoe">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required placeholder="janedoe@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Initial Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="addAdminPass" name="password" minlength="6" required placeholder="At least 6 characters">
                            <button type="button" class="btn btn-light border password-toggle-btn" data-target="#addAdminPass">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Role</label>
                        <select class="form-select" name="role">
                            <option value="admin" selected>Standard Admin</option>
                            <option value="superadmin">Super Administrator</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2.5 px-4 border-top">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Create Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
