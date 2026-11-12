<?php
// Secrets load first: database.php connects the moment it is included, so DB_*
// must already be defined. This file is gitignored and never deployed over.
$__localSecrets = __DIR__ . '/secrets.local.php';
if (!file_exists($__localSecrets)) {
    http_response_code(500);
    die('Configuration missing: create config/secrets.local.php from config/secrets.sample.php');
}
require_once $__localSecrets;

// Site configuration fallbacks. The admin Settings page is authoritative at
// runtime; these apply on a fresh install or if the database is unreachable.
define('SITE_NAME_DEFAULT', 'MyStudy Base');
define('SITE_DESCRIPTION_DEFAULT', 'MyStudy Base is your all-in-one hub for exams, quizzes, study materials, tutorials and competitive challenges for students.');
define('WHATSAPP_NUMBER_DEFAULT', '+2348001234567');

/**
 * Format prize display based on type and amount
 * @param mixed $amount The numeric prize amount
 * @param string $type The prize type (money, data, airtime, text, none)
 * @return string Formatted prize string
 */
function format_prize($amount, $type) {
    if (empty($amount) || (float)$amount <= 0) return '';
    if ($type === 'data') {
        $gb = rtrim(rtrim(number_format((float)$amount, 2, '.', ''), '0'), '.');
        return $gb . ' GB';
    } elseif ($type === 'money' || $type === 'airtime') {
        return '₦' . number_format((float)$amount, 0);
    }
    return $amount;
}

// Build SITE_URL based on environment
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Check if we're on localhost (development) or production
$isLocalhost = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;

if ($isLocalhost) {
    // Local development: include subdirectory (e.g., /mystudy.com.ng)
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $rootPath = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $subdir = '';
    if ($docRoot !== '' && str_starts_with($rootPath, $docRoot)) {
        $subdir = substr($rootPath, strlen($docRoot)); // like /mystudy.com.ng
    }
    if ($subdir === '') {
        $subdir = '/' . basename($rootPath);
    }
    define('SITE_URL', $scheme . '://' . $host . $subdir);
} else {
    // Production: use root URL only (no htdocs in URL)
    define('SITE_URL', $scheme . '://' . $host);
}
// Path configuration
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads');

// Security configuration
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_EXPIRY', 86400); // Match session lifetime so long-open forms stay valid

// Password configuration
define('PASSWORD_BCRYPT_COST', 12);

// Session configuration
define('SESSION_LIFETIME', 86400); // 24 hours
define('SESSION_NAME', 'mystudy_session');

// Set custom session parameters before starting the session
if (session_status() === PHP_SESSION_NONE) {
    // Set custom session save path (writable directory for InfinityFree)
    $session_path = ROOT_PATH . '/logs/sessions';
    if (!file_exists($session_path)) {
        @mkdir($session_path, 0700, true);
    }
    if (is_writable($session_path)) {
        ini_set('session.save_path', $session_path);
    }
    
    // Set session configuration
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    
    // Configure session cookie parameters
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '', // Empty for better compatibility with free hosts
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Start the session
    session_start();
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Error reporting
error_reporting(E_ALL);
// Disable display_errors on production to prevent 500 errors
ini_set('display_errors', strpos($_SERVER['HTTP_HOST'] ?? 'localhost', 'localhost') !== false ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', ROOT_PATH . '/logs/error.log');

// Timezone
date_default_timezone_set('Africa/Lagos');

// Autoload classes
spl_autoload_register(function ($class_name) {
    $paths = [
        ROOT_PATH . '/models/',
        ROOT_PATH . '/controllers/',
        ROOT_PATH . '/includes/'
    ];
    
    foreach ($paths as $path) {
        $file = $path . $class_name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Include required files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Load email configuration (BREVO constants are set by secrets.local.php)
require_once __DIR__ . '/../includes/email-config.php';

// Initialize database connection using the singleton pattern
$pdo = getDbConnection();

// Site identity, resolved after the connection opens so the admin Settings page
// actually changes the live site. WHATSAPP_NUMBER is stored digits-only because
// wa.me rejects a leading '+'.
define('SITE_NAME', site_setting('site_name', SITE_NAME_DEFAULT));
define('SITE_DESCRIPTION', site_setting('site_description', SITE_DESCRIPTION_DEFAULT));
define('ADMIN_EMAIL', site_setting('admin_email', defined('ADMIN_EMAIL_DEFAULT') ? ADMIN_EMAIL_DEFAULT : ('admin@' . SITE_DOMAIN)));
define('WHATSAPP_NUMBER', preg_replace('/\D+/', '', (string)site_setting('whatsapp_number', WHATSAPP_NUMBER_DEFAULT)));

// ======================================================================
// DATABASE MIGRATIONS
// Note: Migrations have been removed from config.php to prevent severe 
// performance issues on shared hosting (where file permission errors 
// would cause migrations to run on every single page load).
// ======================================================================
// Initialize CSRF token if not exists
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = [
        'token' => bin2hex(random_bytes(32)),
        'expiry' => time() + CSRF_TOKEN_EXPIRY
    ];
}

// Function to generate CSRF token
function csrf_token() {
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || $_SESSION[CSRF_TOKEN_NAME]['expiry'] < time()) {
        $_SESSION[CSRF_TOKEN_NAME] = [
            'token' => bin2hex(random_bytes(32)),
            'expiry' => time() + CSRF_TOKEN_EXPIRY
        ];
    }
    return $_SESSION[CSRF_TOKEN_NAME]['token'];
}

// Function to validate CSRF token
function validate_csrf_token($token) {
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || 
        $token !== $_SESSION[CSRF_TOKEN_NAME]['token'] ||
        $_SESSION[CSRF_TOKEN_NAME]['expiry'] < time()) {
        return false;
    }
    return true;
}
?>
