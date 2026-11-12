<?php
/**
 * COMPREHENSIVE MAINTENANCE MODE SYSTEM
 * This blocks ALL access to the platform when maintenance is enabled
 * Including admin access, logged-in users, and direct links
 */

function check_maintenance_mode() {
    global $pdo;
    
    // Get current page path (REQUEST_URI can be unset on CLI/cron/health-check requests)
    $current_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $current_file = basename($current_path);
    
    // CRITICAL: Only allow maintenance page, admin control, admin login, and setup script
    $allowed_paths = [
        '/maintenance.php',
        '/admin/maintenance-control.php',
        '/admin/login.php',
        '/setup-admin-tables.php',
        '/debug-maintenance.php',
        '/maintenance',
        '/admin/maintenance-control',
        '/admin/maintenance-control.php'
    ];
    
    $is_allowed = false;
    foreach ($allowed_paths as $allowed) {
        if ($current_path === $allowed || strpos($current_path, $allowed) === 0) {
            $is_allowed = true;
            break;
        }
    }
    
    // Special check: If this is admin maintenance control, admin login, or debug page, always allow
    if (strpos($current_path, '/admin/maintenance-control') !== false || 
        strpos($current_path, '/admin/login') !== false || 
        strpos($current_path, '/debug-maintenance') !== false) {
        $is_allowed = true;
    }
    
    // If accessing allowed page, continue
    if ($is_allowed) {
        return;
    }
    
    // Check maintenance status
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'maintenance_mode'");
        $stmt->execute();
        $maintenance_mode = $stmt->fetchColumn();
        
        if ($maintenance_mode == '1') {
            // FORCE MAINTENANCE PAGE - No exceptions!
            http_response_code(503);
            
            // Clear any existing session/cookies
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            
            // Include maintenance page and exit
            include __DIR__ . '/../maintenance.php';
            exit;
        }
    } catch (Exception $e) {
        // If settings table doesn't exist, continue normally
        // But log the error for debugging
        error_log('Maintenance check error: ' . $e->getMessage());
    }
}

/**
 * Force logout and redirect to maintenance
 * This is called when maintenance is enabled while users are active
 */
function force_maintenance_redirect() {
    // Destroy session completely
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    // Clear all cookies
    if (isset($_SERVER['HTTP_COOKIE'])) {
        $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
        foreach ($cookies as $cookie) {
            $parts = explode('=', $cookie);
            $name = trim($parts[0]);
            setcookie($name, '', time() - 1000);
            setcookie($name, '', time() - 1000, '/');
        }
    }
    
    // Redirect to maintenance page
    header('Location: ' . SITE_URL . '/maintenance.php');
    exit;
}

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

// Auto-execute maintenance check if this file is included
if (basename($_SERVER['PHP_SELF'] ?? '') !== 'maintenance-check.php') {
    check_maintenance_mode();
}

?>
