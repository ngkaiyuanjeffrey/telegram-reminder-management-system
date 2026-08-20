<?php
/**
 * Telegram Reminder Management System
 * Automated Web Installer & Database Setup
 */

declare(strict_types=1);

session_start();

$step = (int)($_GET['step'] ?? 1);
$error = null;
$success = null;

// Requirements check
$requirements = [
    'PHP Version >= 8.0' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO Extension' => extension_loaded('pdo'),
    'PDO MySQL Driver' => extension_loaded('pdo_mysql'),
    'cURL Extension' => extension_loaded('curl'),
    'OpenSSL Extension' => extension_loaded('openssl'),
    'JSON Extension' => extension_loaded('json'),
    'MBString Extension' => extension_loaded('mbstring'),
    'Logs Directory Writable' => is_writable(__DIR__ . '/logs') || @mkdir(__DIR__ . '/logs', 0777, true)
];

$allPassed = !in_array(false, $requirements, true);

// Handle installation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    $db_host = trim($_POST['db_host'] ?? '127.0.0.1');
    $db_port = (int)($_POST['db_port'] ?? 3306);
    $db_name = trim($_POST['db_name'] ?? 'telegram_reminder_db');
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = (string)($_POST['db_pass'] ?? '');

    $admin_user = trim($_POST['admin_user'] ?? 'admin');
    $admin_email = trim($_POST['admin_email'] ?? 'admin@example.com');
    $admin_pass = (string)($_POST['admin_pass'] ?? 'admin123');

    $bot_token = trim($_POST['bot_token'] ?? '');
    $timezone = trim($_POST['timezone'] ?? 'Asia/Kolkata');

    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $error = "Please fill in all required database connection fields.";
    } elseif (empty($admin_user) || empty($admin_pass) || empty($admin_email)) {
        $error = "Please fill in all required administrator account fields.";
    } else {
        try {
            // Step 1: Connect to MySQL Server (without selecting DB first)
            $dsn = "mysql:host={$db_host};port={$db_port};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // Step 2: Create Database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$db_name}`");

            // Step 3: Run Database Schema
            $sqlFile = __DIR__ . '/database.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
            } else {
                throw new Exception("database.sql file not found in root directory.");
            }

            // Step 4: Insert / Update Admin User with custom password
            $hashedPass = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO `admins` (`id`, `username`, `password`, `email`, `full_name`, `role`, `created_at`)
                                   VALUES (1, :u, :p, :e, 'System Administrator', 'superadmin', NOW())
                                   ON DUPLICATE KEY UPDATE `username` = :u2, `password` = :p2, `email` = :e2");
            $stmt->execute([
                'u' => $admin_user,
                'p' => $hashedPass,
                'e' => $admin_email,
                'u2' => $admin_user,
                'p2' => $hashedPass,
                'e2' => $admin_email
            ]);

            // Step 5: Save Settings
            if (!empty($bot_token)) {
                $stmt = $pdo->prepare("UPDATE `settings` SET `setting_value` = :v WHERE `setting_key` = 'bot_token'");
                $stmt->execute(['v' => $bot_token]);
            }
            if (!empty($timezone)) {
                $stmt = $pdo->prepare("UPDATE `settings` SET `setting_value` = :v WHERE `setting_key` = 'timezone'");
                $stmt->execute(['v' => $timezone]);
            }

            // Step 6: Update database config file if user provided custom connection credentials
            $configFile = __DIR__ . '/config/config.php';
            if (file_exists($configFile)) {
                $configContent = file_get_contents($configFile);
                $configContent = preg_replace("/define\('DB_HOST',\s*'.*?'\);/", "define('DB_HOST', '{$db_host}');", $configContent);
                $configContent = preg_replace("/define\('DB_NAME',\s*'.*?'\);/", "define('DB_NAME', '{$db_name}');", $configContent);
                $configContent = preg_replace("/define\('DB_USER',\s*'.*?'\);/", "define('DB_USER', '{$db_user}');", $configContent);
                $configContent = preg_replace("/define\('DB_PASS',\s*'.*?'\);/", "define('DB_PASS', '{$db_pass}');", $configContent);
                $configContent = preg_replace("/define\('DB_PORT',\s*\d+\);/", "define('DB_PORT', {$db_port});", $configContent);
                file_put_contents($configFile, $configContent);
            }

            $success = true;
            $step = 2; // Installation complete step
        } catch (Throwable $e) {
            $error = "Installation Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup & Installer | Telegram Reminder Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0369a1 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .installer-card {
            background: #ffffff;
            border-radius: 1.25rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            max-width: 650px;
            width: 100%;
            overflow: hidden;
        }
        .installer-header {
            background: #0f172a;
            color: #ffffff;
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        .installer-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: #ffffff;
            margin-bottom: 0.75rem;
            box-shadow: 0 8px 16px rgba(2, 132, 199, 0.3);
        }
    </style>
</head>
<body>

<div class="installer-card">
    <div class="installer-header">
        <div class="installer-logo">
            <i class="bi bi-telegram"></i>
        </div>
        <h4 class="fw-bold mb-1">Telegram Reminder Management System</h4>
        <p class="text-white-50 small mb-0">Automated Installation & System Configuration Wizard</p>
    </div>

    <div class="p-4 p-md-5">
        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($step === 2 && $success): ?>
            <div class="text-center py-4">
                <div class="text-success mb-3" style="font-size: 3.5rem;">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h3 class="fw-bold text-dark mb-2">Installation Successful!</h3>
                <p class="text-muted mb-4">The database tables, initial admin account, and core settings have been configured successfully.</p>

                <div class="card bg-light border-0 text-start p-3 mb-4 rounded-3">
                    <div class="small fw-bold text-uppercase text-muted mb-2">Admin Login Credentials</div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Username:</span>
                        <strong class="text-dark font-monospace"><?= htmlspecialchars($admin_user ?? 'admin') ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Password:</span>
                        <strong class="text-dark font-monospace"><?= htmlspecialchars($admin_pass ?? 'admin123') ?></strong>
                    </div>
                </div>

                <div class="d-grid">
                    <a href="admin/login.php" class="btn btn-primary btn-lg rounded-pill py-2.5 fw-bold shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Proceed to Admin Login
                    </a>
                </div>
            </div>

        <?php else: ?>

            <!-- System Requirements Checklist -->
            <div class="mb-4">
                <h6 class="fw-bold text-dark text-uppercase small mb-3">
                    <i class="bi bi-check2-square text-primary me-1"></i> 1. System Requirements Check
                </h6>
                <div class="row g-2">
                    <?php foreach ($requirements as $name => $passed): ?>
                        <div class="col-sm-6">
                            <div class="p-2 border rounded-3 d-flex align-items-center justify-content-between small <?= $passed ? 'bg-light' : 'bg-danger-subtle text-danger' ?>">
                                <span><?= htmlspecialchars($name) ?></span>
                                <?php if ($passed): ?>
                                    <i class="bi bi-check-circle-fill text-success fs-6"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill text-danger fs-6"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <hr class="my-4">

            <!-- Installation Form -->
            <form method="POST" action="install.php">
                <input type="hidden" name="action" value="install">

                <h6 class="fw-bold text-dark text-uppercase small mb-3">
                    <i class="bi bi-database text-primary me-1"></i> 2. MySQL Database Connection
                </h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">Database Host</label>
                        <input type="text" class="form-control" name="db_host" value="127.0.0.1" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Port</label>
                        <input type="number" class="form-control" name="db_port" value="3306" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold">Database Name</label>
                        <input type="text" class="form-control" name="db_name" value="telegram_reminder_db" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">DB Username</label>
                        <input type="text" class="form-control" name="db_user" value="root" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">DB Password</label>
                        <input type="password" class="form-control" name="db_pass" placeholder="Empty for default XAMPP">
                    </div>
                </div>

                <h6 class="fw-bold text-dark text-uppercase small mb-3">
                    <i class="bi bi-person-badge text-primary me-1"></i> 3. Super Admin Account
                </h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Admin Username</label>
                        <input type="text" class="form-control" name="admin_user" value="admin" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Admin Password</label>
                        <input type="text" class="form-control" name="admin_pass" value="admin123" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold">Admin Email</label>
                        <input type="email" class="form-control" name="admin_email" value="admin@example.com" required>
                    </div>
                </div>

                <h6 class="fw-bold text-dark text-uppercase small mb-3">
                    <i class="bi bi-gear-wide-connected text-primary me-1"></i> 4. Bot & Environment (Optional)
                </h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">Telegram Bot Token (from @BotFather)</label>
                        <input type="text" class="form-control" name="bot_token" placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Timezone</label>
                        <select class="form-select" name="timezone">
                            <option value="Asia/Kolkata" selected>Asia/Kolkata (+05:30)</option>
                            <option value="UTC">UTC (+00:00)</option>
                            <option value="America/New_York">America/New_York (-05:00)</option>
                            <option value="Europe/London">Europe/London (+00:00)</option>
                            <option value="Asia/Dubai">Asia/Dubai (+04:00)</option>
                            <option value="Asia/Singapore">Asia/Singapore (+08:00)</option>
                        </select>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill py-2.5 fw-bold shadow-sm" <?= !$allPassed ? 'disabled' : '' ?>>
                        <i class="bi bi-box-arrow-in-down me-1"></i> Install System & Database
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
