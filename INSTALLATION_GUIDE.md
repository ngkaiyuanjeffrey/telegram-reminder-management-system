# 📘 Telegram Reminder Management System - Installation Guide

A comprehensive step-by-step setup guide for **Local XAMPP / WAMP / LAMP** and **cPanel Shared Hosting**.

---

## 📋 System Requirements
- **PHP Version**: PHP 8.0 or higher (PHP 8.1, 8.2, 8.3, 8.4, 8.5+ fully supported)
- **PHP Extensions**:
  - `PDO` & `pdo_mysql`
  - `curl` (Required for Telegram Bot API communication)
  - `openssl` (Required for HTTPS and secure tokens)
  - `mbstring` & `json`
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Cron Service**: Linux Crontab, cPanel Cron Jobs, or Windows Task Scheduler

---

## 💻 Method 1: Local XAMPP / WAMP Installation

### Step 1: Clone or Copy the Files
Place the project folder into your web root:
- **XAMPP Windows**: `C:\xampp\htdocs\Telegram Reminder Management System`
- **WAMP**: `C:\wamp64\www\Telegram Reminder Management System`
- **Linux LAMP**: `/var/www/html/telegram-reminder`

### Step 2: Start Apache & MySQL
Open your XAMPP Control Panel and start **Apache** and **MySQL** services.

### Step 3: Automated 1-Click Web Installer
1. Open your browser and navigate to:
   ```
   http://localhost/Telegram%20Reminder%20Management%20System/install.php
   ```
2. The installer will automatically:
   - Verify all required PHP extensions.
   - Create the MySQL database `telegram_reminder_db`.
   - Import all database tables and seed data.
   - Setup the default administrator credentials.
3. Click **"Install System & Database"**.

### Step 4: Login to Admin Portal
- **URL**: `http://localhost/Telegram%20Reminder%20Management%20System/admin/login.php`
- **Default Username**: `admin`
- **Default Password**: `admin123`

---

## 🌐 Method 2: cPanel Shared Hosting Deployment

### Step 1: Upload Files
1. Log in to your **cPanel**.
2. Open **File Manager** &rarr; `public_html` (or your subdomain directory).
3. Upload all project files and folders.

### Step 2: Create MySQL Database & User
1. In cPanel, click **MySQL® Databases**.
2. Create a new database (e.g. `youruser_reminderdb`).
3. Create a new database user and assign a strong password.
4. Add the user to the database with **ALL PRIVILEGES**.

### Step 3: Import Database Schema
1. In cPanel, open **phpMyAdmin**.
2. Select your newly created database.
3. Click **Import** tab &rarr; Choose `database.sql` &rarr; Click **Go**.

### Step 4: Update Database Configuration
Open `config/config.php` (or use the web installer at `https://yourdomain.com/install.php`) and enter your database details:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'youruser_reminderdb');
define('DB_USER', 'youruser_dbuser');
define('DB_PASS', 'your_strong_password');
define('DB_PORT', 3306);
```

---

## ⏰ Step 3: Setting Up the 1-Minute Cron Job (CRITICAL)

The automated message scheduler relies on a cron job running every 1 minute.

### Option A: Standard cPanel CLI Cron (Recommended)
1. In cPanel, go to **Cron Jobs** (under the *Advanced* section).
2. Under **Add New Cron Job**:
   - Common Settings: **Once Per Minute (* * * * *)**
   - Minute: `*`
   - Hour: `*`
   - Day: `*`
   - Month: `*`
   - Weekday: `*`
3. Enter the command (adjust path to your cPanel username):
   ```bash
   * * * * * php /home/YOUR_CPANEL_USER/public_html/cron/cron.php >/dev/null 2>&1
   ```
4. Click **Add New Cron Job**.

### Option B: Web URL / cURL Cron (For Shared Hosts or External Cron Services like cron-job.org)
If your host restricts CLI execution, use the protected Web Cron URL:
```bash
* * * * * curl -s "https://yourdomain.com/cron/cron.php?key=YOUR_CRON_SECRET_KEY" >/dev/null 2>&1
```
*(You can copy your unique key and ready-to-use command directly from **Admin &rarr; Bot & Settings &rarr; Cron Job Setup**).*

---

## 🤖 Step 4: Connecting your Telegram Bot
1. Follow the [TELEGRAM_BOT_GUIDE.md](file:///c:/xampp/htdocs/Telegram%20Reminder%20Management%20System/TELEGRAM_BOT_GUIDE.md) to create your bot and get the Bot Token.
2. Sign in to your Admin Dashboard &rarr; Click **Bot & Settings**.
3. Paste your **Telegram Bot Token** and click **"Test Bot"**.
4. Click **Save All Settings**.

---

## 🔒 Security Best Practices
1. **Change Default Password**: Navigate to **Admin Profile** immediately and update your password.
2. **Remove or Protect `install.php`**: After installation is verified, you can delete or rename `install.php`.
3. **Keep Logs Secure**: The `logs/.htaccess` file automatically blocks browser access to log files.
4. **HTTPS / SSL**: Ensure your domain runs over HTTPS for secure admin sessions.

---

## 🛠️ Troubleshooting
| Issue | Solution |
|---|---|
| **"Telegram Bot Token Not Configured"** | Go to *Bot & Settings*, enter the token from @BotFather, and click *Save All Settings*. |
| **"Forbidden: bot was blocked by the user"** | The recipient must open the Telegram app, search your bot, and click `/start`. Telegram privacy policies prevent bots from initiating chats first. |
| **Cron Job not executing** | Run `php cron/cron.php` manually from terminal or click **Run Cron Now** in the top navbar to verify execution and inspect `logs/cron.log`. |
| **Timezone mismatch** | Select your correct local timezone in *Bot & Settings* (e.g. `Asia/Kolkata`, `America/New_York`, `UTC`). |
