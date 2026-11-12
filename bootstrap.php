<?php
/**
 * Bootstrap file for the application
 * 
 * This file initializes the application and handles routing
 */

// Define the application root directory
define('APP_ROOT', __DIR__);

// Define the base URL (auto-detect subdirectory)
$isLocal = strpos($_SERVER['HTTP_HOST'], 'localhost') !== false 
           || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false;

if ($isLocal) {
    // Local XAMPP: include subdirectory (e.g., /mystudy.com.ng)
    $base_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . rtrim($base_path, '/'));
} else {
    // Production: use root path only (no htdocs in URL)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST']);
}

// Include the configuration file
require_once APP_ROOT . '/config/config.php';

// Set error reporting based on environment
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = APP_ROOT . '/app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Session is already started in config.php with proper configuration
// No need to start it again here

// Handle the request
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script_name = dirname($_SERVER['SCRIPT_NAME']);
$path = substr($request_uri, strlen($script_name) ?: 0);

// Remove query string
$path = strtok($path, '?');

// Define routes
$routes = [
    '/' => 'index.php',
    '/login' => 'login.php',
    '/register' => 'register.php',
    '/dashboard' => 'dashboard.php',
    '/profile' => 'profile.php',
    '/logout' => 'logout.php',
    '/admin' => '/admin/index.php',
    '/admin/dashboard' => '/admin/dashboard.php',
    // Add more routes as needed
];

// Check if the route exists
if (array_key_exists($path, $routes)) {
    $file = $routes[$path];
    
    // Check if the file exists
    if (file_exists(APP_ROOT . '/' . $file)) {
        require APP_ROOT . '/' . $file;
    } else if (file_exists(APP_ROOT . $file)) {
        require APP_ROOT . $file;
    } else {
        // 404 Not Found
        http_response_code(404);
        require APP_ROOT . '/views/errors/404.php';
        exit;
    }
} else {
    // 404 Not Found
    http_response_code(404);
    require APP_ROOT . '/views/errors/404.php';
    exit;
}
