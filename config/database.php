<?php
/**
 * Telegram Reminder Management System
 * Database Connection (PDO Singleton)
 */

declare(strict_types=1);

class Database {
    private static ?Database $instance = null;
    private ?PDO $conn = null;

    // Database configuration constants
    private string $host = '127.0.0.1';
    private string $db_name = 'telegram_reminder_db';
    private string $username = 'root';
    private string $password = '';
    private string $charset = 'utf8mb4';
    private int $port = 3306;

    private function __construct() {
        // Allow overriding from environment variables or custom config if present
        if (defined('DB_HOST')) $this->host = DB_HOST;
        if (defined('DB_NAME')) $this->db_name = DB_NAME;
        if (defined('DB_USER')) $this->username = DB_USER;
        if (defined('DB_PASS')) $this->password = DB_PASS;
        if (defined('DB_PORT')) $this->port = (int)DB_PORT;

        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ];

        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // Check if database does not exist
            if ($e->getCode() === 1049) {
                // Database does not exist yet
                $this->conn = null;
            } else {
                error_log("Database Connection Error: " . $e->getMessage());
                $this->conn = null;
            }
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): ?PDO {
        return $this->conn;
    }

    // Helper for raw connection without database selected (for installer)
    public static function getRawConnection(string $host, string $user, string $pass, int $port = 3306): PDO {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ];
        return new PDO($dsn, $user, $pass, $options);
    }
}

/**
 * Global helper to quickly get the PDO connection instance
 */
function get_db(): ?PDO {
    return Database::getInstance()->getConnection();
}
