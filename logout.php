<?php
// Include configuration and initialize database connection
require_once 'config/config.php';

// Check if user is logged in
if (is_logged_in()) {
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Set success message
    set_flash_message('success', 'You have been successfully logged out.');
}

// Redirect to homepage
redirect(SITE_URL . '/');
?>
