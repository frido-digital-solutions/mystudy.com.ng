<?php
/**
 * Maintenance Mode Functions
 * These functions can be called without triggering the maintenance check
 */

/**
 * Check if maintenance mode is enabled (for admin panel)
 */
if (!function_exists('is_maintenance_mode')) {
function is_maintenance_mode() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'maintenance_mode'");
        $stmt->execute();
        return $stmt->fetchColumn() == '1';
    } catch (Exception $e) {
        return false;
    }
}
}

/**
 * Enable maintenance mode immediately
 */
if (!function_exists('enable_maintenance_mode')) {
function enable_maintenance_mode($message = '') {
    global $pdo;
    
    try {
        // Ensure settings table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Enable maintenance mode
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('maintenance_mode', '1') 
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute();
        
        // Set custom message if provided
        if ($message) {
            $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('maintenance_message', ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$message]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log('Failed to enable maintenance mode: ' . $e->getMessage());
        return false;
    }
}
}

/**
 * Disable maintenance mode immediately
 */
if (!function_exists('disable_maintenance_mode')) {
function disable_maintenance_mode() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('maintenance_mode', '0') 
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute();
        return true;
    } catch (Exception $e) {
        error_log('Failed to disable maintenance mode: ' . $e->getMessage());
        return false;
    }
}
}

?>
