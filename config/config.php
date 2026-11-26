<?php
// Secrets load first: database.php connects the moment it is included, so DB_*
// must already be defined. This file is gitignored and never deployed over.
$__localSecrets = __DIR__ . '/secrets.local.php';
if (!file_exists($__localSecrets)) {
    http_response_code(500);
    die('Configuration missing: create config/secrets.local.php from config/secrets.sample.php');
}
require_once $__localSecrets;

/**
 * Every optional key gets a default here, so that a server whose secrets file
 * is missing one loses that one feature instead of losing the whole site.
 *
 * This is not tidiness - it is the difference between one broken checkout and
 * a blank page on every URL. The secrets file is written by hand on a shared
 * host, and a half-finished one is the normal state of a file like that. The
 * code already knows what to do with a missing key: a blank Paystack key
 * refuses to charge anybody, a blank Brevo key skips email and logs why, and
 * a blank Cloudflare key makes the Mentor answer from its built-in knowledge
 * instead of the model. All three are decisions, not accidents.
 */
if (!defined('SITE_DOMAIN')) define('SITE_DOMAIN', 'mystudy.com.ng');
if (!defined('ADMIN_EMAIL_DEFAULT')) define('ADMIN_EMAIL_DEFAULT', 'admin@' . SITE_DOMAIN);
if (!defined('PAYSTACK_PUBLIC_KEY')) define('PAYSTACK_PUBLIC_KEY', '');
if (!defined('PAYSTACK_SECRET_KEY')) define('PAYSTACK_SECRET_KEY', '');
if (!defined('BREVO_API_KEY')) define('BREVO_API_KEY', '');
if (!defined('BREVO_FROM_EMAIL')) define('BREVO_FROM_EMAIL', 'no-reply@' . SITE_DOMAIN);
if (!defined('BREVO_FROM_NAME')) define('BREVO_FROM_NAME', 'MyStudy Base');
if (!defined('BREVO_REPLY_TO_EMAIL')) define('BREVO_REPLY_TO_EMAIL', 'support@' . SITE_DOMAIN);
if (!defined('BREVO_REPLY_TO_NAME')) define('BREVO_REPLY_TO_NAME', 'MyStudy Support');
if (!defined('CLOUDFLARE_ACCOUNT_ID')) define('CLOUDFLARE_ACCOUNT_ID', '');
if (!defined('CLOUDFLARE_AI_TOKEN')) define('CLOUDFLARE_AI_TOKEN', '');

/**
 * The database is the one thing that gets no default.
 *
 * A wrong database name would quietly open a different site - or an empty one -
 * and the site would look alive while serving nothing. So when a database key
 * is missing, the server says exactly which one is missing instead of dying
 * with "Undefined constant DB_HOST", which is a sentence nobody can act on.
 */
foreach (['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $__msbDbKey) {
    if (!defined($__msbDbKey)) {
        http_response_code(500);
        die('Configuration incomplete: config/secrets.local.php does not define ' . $__msbDbKey
            . '. Add it (cPanel -> MySQL Databases has the name, the user and the password).');
    }
}
unset($__msbDbKey);

/**
 * A Paystack key that does not look like a Paystack key is not one.
 *
 * The secrets file is written by hand on a shared host, and a line that still
 * holds a placeholder - "PASTE_SECRET_KEY_HERE" - is the normal state of a
 * half-finished file. Treating that as a configured key is worse than treating
 * it as missing: the checkout opens, the payment is attempted, and Paystack
 * refuses it, so the student is asked for money and then told there was an
 * error. A public key begins "pk_" and a secret key begins "sk_"; anything
 * else is read as not configured, and the paid features say so plainly.
 *
 * @return string the key, or an empty string when it is not usable
 */
function msb_paystack_key(bool $secret): string
{
    $constant = $secret ? 'PAYSTACK_SECRET_KEY' : 'PAYSTACK_PUBLIC_KEY';
    $prefix = $secret ? 'sk_' : 'pk_';
    if (!defined($constant)) return '';
    $value = trim((string)constant($constant));
    return strpos($value, $prefix) === 0 ? $value : '';
}

// Site configuration fallbacks. The admin Settings page is authoritative at
// runtime; these apply on a fresh install or if the database is unreachable.
define('SITE_NAME_DEFAULT', 'MyStudy Base');
define('SITE_DESCRIPTION_DEFAULT', 'MyStudy Base gives Nigerian students free WAEC, NECO and JAMB past questions with answers, free CBT practice tests for every subject, past question downloads, study materials and tutorials.');
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

// A phone testing over the Wi-Fi reaches this copy by its LAN address, not by
// the name localhost, so private ranges count as development too. Anything with
// a real domain name is production.
$bareHost = strtolower(preg_replace('/:\d+$/', '', $host));
$isLocalhost = $bareHost === 'localhost' || $bareHost === '::1' || $bareHost === '[::1]'
    || (bool) preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $bareHost);

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

/**
 * Make sure the tables this site needs exist on this server.
 *
 * A table that is missing is a feature that is quietly broken: the page that
 * needs it either dies or shows nothing, and the cause is on the server
 * rather than in the code that is being read. The tables are shipped with the
 * code and created here if they are absent, so the live site cannot end up
 * with less than the development machine has. Everything it creates is
 * "IF NOT EXISTS", so an existing table is never touched.
 */
require_once __DIR__ . '/../includes/schema-tables.php';
msb_ensure_schema_tables($pdo);

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
