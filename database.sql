-- ============================================================================
-- Telegram Reminder Management System
-- Database Schema & Initial Data
-- Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `telegram_reminder_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `telegram_reminder_db`;

-- ----------------------------------------------------------------------------
-- Table structure for `admins`
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `full_name` VARCHAR(150) DEFAULT 'Administrator',
  `role` ENUM('superadmin', 'admin') DEFAULT 'admin',
  `reset_token` VARCHAR(100) NULL,
  `reset_expires` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_username` (`username`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table structure for `users` (Telegram Recipients)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `chat_id` VARCHAR(100) NOT NULL UNIQUE,
  `username` VARCHAR(100) NULL,
  `phone` VARCHAR(50) NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_chat_id` (`chat_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table structure for `reminders`
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `reminders`;
CREATE TABLE `reminders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `scheduled_time` DATETIME NOT NULL,
  `status` ENUM('pending', 'in_progress', 'sent', 'failed', 'partially_sent') DEFAULT 'pending',
  `delay_seconds` INT DEFAULT 2,
  `created_by` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_scheduled_status` (`scheduled_time`, `status`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_by` (`created_by`),
  CONSTRAINT `fk_reminders_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table structure for `reminder_messages`
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `reminder_messages`;
CREATE TABLE `reminder_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reminder_id` INT NOT NULL,
  `message_text` TEXT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_reminder_order` (`reminder_id`, `sort_order`),
  CONSTRAINT `fk_messages_reminder` FOREIGN KEY (`reminder_id`) REFERENCES `reminders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table structure for `reminder_recipients`
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `reminder_recipients`;
CREATE TABLE `reminder_recipients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reminder_id` INT NOT NULL,
  `user_id` INT NULL,
  `chat_id` VARCHAR(100) NOT NULL,
  INDEX `idx_reminder_recipient` (`reminder_id`, `chat_id`),
  INDEX `idx_user_id` (`user_id`),
  CONSTRAINT `fk_recipients_reminder` FOREIGN KEY (`reminder_id`) REFERENCES `reminders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_recipients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table structure for `message_logs`
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `message_logs`;
CREATE TABLE `message_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reminder_id` INT NULL,
  `chat_id` VARCHAR(100) NOT NULL,
  `recipient_name` VARCHAR(150) NULL,
  `message_text` TEXT NOT NULL,
  `status` ENUM('sent', 'failed') NOT NULL,
  `error_message` TEXT NULL,
  `telegram_message_id` VARCHAR(100) NULL,
  `sent_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_reminder_id` (`reminder_id`),
  INDEX `idx_chat_id` (`chat_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_sent_time` (`sent_time`),
  CONSTRAINT `fk_logs_reminder` FOREIGN KEY (`reminder_id`) REFERENCES `reminders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table structure for `settings`
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Default Seed Data
-- ----------------------------------------------------------------------------

-- Insert Default Superadmin (Username: admin | Password: admin123)
INSERT INTO `admins` (`id`, `username`, `password`, `email`, `full_name`, `role`, `created_at`) 
VALUES (1, 'admin', '$2y$12$4KBu1MQ.Lo7I7KazSK.wH.gYDvy9SvRJi/qijNoaWspWL/8xpuCeS', 'admin@example.com', 'System Administrator', 'superadmin', NOW())
ON DUPLICATE KEY UPDATE `username` = `username`;

-- Insert Default System Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('app_name', 'Telegram Reminder Management System'),
('bot_token', ''),
('bot_username', ''),
('timezone', 'Asia/Kolkata'),
('default_delay_seconds', '2'),
('cron_secret_key', 'cron_sec_8f93a29b4e1c7d6'),
('date_format', 'Y-m-d H:i')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- Insert Sample Demo Recipients for Initial Testing
INSERT INTO `users` (`name`, `chat_id`, `username`, `phone`, `status`, `created_at`) VALUES
('John Doe', '123456789', 'johndoe', '+1234567890', 'active', NOW()),
('Sarah Smith', '987654321', 'sarah_smith', '+1987654321', 'active', NOW()),
('Alex Brown', '555123456', 'alexbrown', '+1555123456', 'active', NOW())
ON DUPLICATE KEY UPDATE `chat_id` = `chat_id`;

-- Insert Sample Reminder with Multi-Message Sequence
INSERT INTO `reminders` (`id`, `title`, `description`, `scheduled_time`, `status`, `delay_seconds`, `created_by`, `created_at`)
VALUES (1, 'Project Deadline & Team Standup Reminder', 'Morning briefing and task review sequence', DATE_ADD(NOW(), INTERVAL 1 HOUR), 'pending', 2, 1, NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Insert Multiple Sequential Messages for Sample Reminder
INSERT INTO `reminder_messages` (`reminder_id`, `message_text`, `sort_order`) VALUES
(1, '🌅 <b>Good Morning Team!</b> Hope you have a productive day ahead.', 1),
(1, '📌 <b>Reminder:</b> The sprint deliverables are due by 5:00 PM today.', 2),
(1, '✅ Please ensure all Git commits and test reports are submitted on time. Reach out if you need assistance!', 3);

-- Link Sample Recipients to Sample Reminder
INSERT INTO `reminder_recipients` (`reminder_id`, `user_id`, `chat_id`) VALUES
(1, 1, '123456789'),
(1, 2, '987654321');
