<?php
// DB_HOST / DB_USER / DB_PASS / DB_NAME are defined in config/secrets.local.php,
// which config.php loads before including this file. That file is never committed.

// Persistent connections exhaust max_user_connections on this shared host; keep disabled
define('DB_PERSISTENT', false);

class Database {
    private static $instance = null;
    private $conn;
    
    /**
     * Get database instance (singleton pattern)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get database connection
     */
    public function getConnection() {
        if ($this->conn === null) {
            $this->connect();
        }
        return $this->conn;
    }
    
    /**
     * Connect to the database
     */
    private function connect() {
        try {
            // Check if we're on localhost (development) or production
            // CLI/cron runs have no HTTP_HOST; keep them on the production path
            $httpHost = $_SERVER['HTTP_HOST'] ?? '';
            $isLocal = strpos($httpHost, 'localhost') !== false
                       || strpos($httpHost, '127.0.0.1') !== false;
            
            if ($isLocal) {
                // Development: Connect without database name to check/create it
                $this->conn = new PDO(
                    "mysql:host=" . DB_HOST . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_PERSISTENT => DB_PERSISTENT,
                    ]
                );
                
                // Check if database exists, if not create it
                $this->initializeDatabase();
                
                // Now connect to the specific database
                $this->conn->exec("USE `" . DB_NAME . "`");
                
                // Set MySQL session timezone to match PHP's Africa/Lagos (WAT = UTC+1)
                $this->conn->exec("SET time_zone = '+01:00'");
            } else {
                // Production: Connect directly to database (no auto-creation)
                $this->conn = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_PERSISTENT => DB_PERSISTENT,
                    ]
                );
                
                // Set MySQL session timezone to match PHP's Africa/Lagos (WAT = UTC+1)
                $this->conn->exec("SET time_zone = '+01:00'");
            }
            
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Failed to connect to database: " . $e->getMessage());
        }
    }
    
    /**
     * Initialize database and tables if they don't exist
     */
    private function initializeDatabase() {
        try {
            // Create database if it doesn't exist
            $this->conn->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->conn->exec("USE `" . DB_NAME . "`");
            
            // Check if users table exists
            $stmt = $this->conn->query("SHOW TABLES LIKE 'users'");
            if ($stmt->rowCount() === 0) {
                // Import schema if table doesn't exist
                $schemaFile = __DIR__ . '/../database/schema.sql';
                if (file_exists($schemaFile)) {
                    $schema = file_get_contents($schemaFile);
                    $this->conn->exec($schema);
                } else {
                    // Create basic tables if schema file doesn't exist
                    $this->createBasicTables();
                }
            }
            
        } catch (PDOException $e) {
            error_log("Database initialization error: " . $e->getMessage());
            throw new Exception("Failed to initialize database: " . $e->getMessage());
        }
    }
    
    /**
     * Create basic database tables
     */
    private function createBasicTables() {
        // Users table
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(100) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `user_type` ENUM('admin', 'university', 'secondary') NOT NULL,
                `profile_picture` VARCHAR(255) DEFAULT NULL,
                `email_verified` TINYINT(1) DEFAULT 0,
                `verification_token` VARCHAR(100) DEFAULT NULL,
                `reset_token` VARCHAR(100) DEFAULT NULL,
                `reset_token_expires` DATETIME DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `last_login` DATETIME DEFAULT NULL,
                `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        
        // User meta table for additional user data
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS `user_meta` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `meta_key` VARCHAR(100) NOT NULL,
                `meta_value` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                UNIQUE KEY `user_meta_key` (`user_id`, `meta_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
    
    // Prevent direct instantiation and cloning
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize database connection");
    }
}

// Initialize and return database connection
function getDbConnection() {
    try {
        return Database::getInstance()->getConnection();
    } catch (Exception $e) {
        // Log error and show user-friendly message
        error_log("Database connection failed: " . $e->getMessage());
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            die("Database error: " . $e->getMessage());
        } else {
            die("A database error occurred. Please try again later.");
        }
    }
}

// Set global database connection
$pdo = getDbConnection();
?>
