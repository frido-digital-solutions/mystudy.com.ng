<?php
/**
 * Redirect to a specific URL
 * 
 * @param string $url URL to redirect to
 * @param int $statusCode HTTP status code (default: 302)
 */
function redirect($url, $statusCode = 302) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

/**
 * Sanitize input data
 * 
 * @param mixed $data Data to be sanitized
 * @return mixed Sanitized data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Hash a password
 * 
 * @param string $password Password to hash
 * @return string Hashed password
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_BCRYPT_COST]);
}

/**
 * Verify a password against a hash
 * 
 * @param string $password Password to verify
 * @param string $hash Hash to verify against
 * @return bool True if password matches, false otherwise
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate a random token
 * 
 * @param int $length Length of the token
 * @return string Random token
 */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Set a flash message
 * 
 * @param string $type Type of message (success, error, warning, info)
 * @param string $message Message content
 */
function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Display flash message if exists
 */
function display_flash_message() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return "<div class='alert alert-{$flash['type']}'>{$flash['message']}</div>";
    }
    return '';
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function is_logged_in() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Enforce the server-side expiry written at login
    if (isset($_SESSION['expire_time']) && time() > (int)$_SESSION['expire_time']) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        return false;
    }

    return true;
}

/**
 * Re-read the role and super admin flag from the database so that a demotion or
 * revocation takes effect without waiting for the user to log out.
 *
 * Throttled to one query per 60 seconds per session: this site runs on shared
 * hosting where per-request privilege queries exhaust the connection limit.
 */
function refresh_session_privileges() {
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $now = time();
    if (isset($_SESSION['privileges_checked_at']) && ($now - (int)$_SESSION['privileges_checked_at']) < 60) {
        return;
    }

    global $pdo;
    if (!isset($pdo)) {
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT user_type, is_super_admin FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $_SESSION['user_type'] = $row['user_type'];
            $_SESSION['is_super_admin'] = ((int)$row['is_super_admin'] === 1);
        } else {
            // Account no longer exists
            unset($_SESSION['user_id'], $_SESSION['user_type'], $_SESSION['is_super_admin']);
        }
        $_SESSION['privileges_checked_at'] = $now;
    } catch (Exception $e) {
        // Keep the cached values and retry on the next request rather than
        // locking everybody out during a transient database failure.
    }
}

/**
 * Check if user has a specific role
 * 
 * @param string $role Role to check (admin, university, secondary)
 * @return bool True if user has the role, false otherwise
 */
function has_role($role) {
    if ($role === 'admin') {
        refresh_session_privileges();
    }
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === $role;
}

/**
 * Require user to be logged in
 * 
 * @param string $redirect URL to redirect to if not logged in
 */
function require_login($redirect = null) {
    if (!is_logged_in()) {
        set_flash_message('error', 'Please log in to access this page.');
        $dest = $redirect ?? (defined('SITE_URL') ? (SITE_URL . '/login.php') : '/login.php');
        redirect($dest);
    }
    
    // Check if user is frozen
    global $pdo;
    if (isset($pdo) && isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT is_frozen FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $status = $stmt->fetchColumn();
        if ($status && (int)$status === 1) {
            // Allow them to visit frozen.php or logout.php, block everything else
            $currentName = basename($_SERVER['PHP_SELF'] ?? '');
            if ($currentName !== 'frozen.php' && $currentName !== 'logout.php') {
                redirect(defined('SITE_URL') ? (SITE_URL . '/frozen.php') : '/frozen.php');
            }
        }
    }
}

/**
 * Require user to have a specific role
 * 
 * @param string $role Required role
 * @param string $redirect URL to redirect to if user doesn't have the role
 */
function require_role($role, $redirect = null) {
    require_login();
    
    if (!has_role($role)) {
        set_flash_message('error', 'You do not have permission to access this page.');
        $dest = $redirect ?? (defined('SITE_URL') ? SITE_URL . '/' : '/');
        redirect($dest);
    }
}

/**
 * Check if user is a super admin
 * 
 * @return bool True if user is super admin
 */
function is_super_admin() {
    refresh_session_privileges();

    // Check session first
    if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true) {
        return true;
    }
    
    // Fallback: only when the throttled refresh could not populate the cache
    if (!isset($_SESSION['is_super_admin']) && isset($_SESSION['user_id'])) {
        global $pdo;
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT is_super_admin FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $val = (int)$stmt->fetchColumn();
                $_SESSION['is_super_admin'] = ($val === 1);
                $_SESSION['privileges_checked_at'] = time();
                return $_SESSION['is_super_admin'];
            } catch (Exception $e) {
                // fall through
            }
        }
    }
    return false;
}

/**
 * Check if current admin has permission to view a specific page
 * 
 * @param string $page_key The key for the page (e.g., 'quizzes', 'posts')
 * @return bool True if admin has permission or is super admin
 */
function admin_can($page_key) {
    if (!is_logged_in() || !has_role('admin')) {
        return false;
    }
    
    // Super admin can access everything
    if (is_super_admin()) {
        return true;
    }
    
    // Check specific permissions for regular admins
    global $pdo;
    if (isset($pdo) && isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM admin_permissions WHERE admin_user_id = ? AND page_key = ?");
            $stmt->execute([$_SESSION['user_id'], $page_key]);
            return $stmt->fetchColumn() !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    return false;
}

/**
 * Log an action performed by an admin
 * 
 * @param PDO $pdo Database connection
 * @param string $action Action description
 * @param string $details Additional details (JSON or text)
 */
function log_admin_activity($pdo, $action, $details = null) {
    if (!isset($_SESSION['user_id']) || !has_role('admin')) {
        return;
    }
    
    try {
        $ip = get_client_ip();
        $stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $action, $details, $ip]);
    } catch (Exception $e) {
        // Silently fail logging rather than breaking the application
        error_log("Failed to log admin activity: " . $e->getMessage());
    }
}

/**
 * Require admin access (legacy/general)
 * 
 * @param string $redirect URL to redirect to if user is not an admin
 */
function require_admin($redirect = null) {
    $dest = $redirect ?? (defined('SITE_URL') ? SITE_URL . '/' : '/');
    require_role('admin', $dest);
}

/**
 * Require super admin access exclusively
 * 
 * @param string $redirect URL to redirect to if user is not a super admin
 */
function require_super_admin($redirect = null) {
    require_admin($redirect);
    
    if (!is_super_admin()) {
        set_flash_message('error', 'You need Super Admin privileges to access this area.');
        $dest = $redirect ?? (defined('SITE_URL') ? SITE_URL . '/admin/dashboard.php' : '/admin/dashboard.php');
        redirect($dest);
    }
}

/**
 * Format date
 * 
 * @param string $date Date string
 * @param string $format Date format (default: 'M j, Y')
 * @return string Formatted date
 */
function format_date($date, $format = 'M j, Y') {
    $date = new DateTime($date);
    return $date->format($format);
}

/**
 * Get user's IP address
 * 
 * @return string IP address
 */
function get_client_ip() {
    $ip = '';
    
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Log an error
 * 
 * @param string $message Error message
 * @param array $context Additional context
 */
function log_error($message, $context = []) {
    $log = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    
    if (!empty($context)) {
        $log .= 'Context: ' . json_encode($context, JSON_PRETTY_PRINT) . PHP_EOL;
    }
    
    error_log($log, 3, ROOT_PATH . '/logs/error.log');
}

/**
 * Render quiz text with safe HTML formatting.
 * Allows only basic formatting tags: <u>, <b>, <i>, <em>, <strong>, <sup>, <sub>, <br>.
 * All other HTML is stripped for security. Useful for subjects like English
 * where underlined or bold text is needed in questions/options.
 *
 * @param string $text Raw quiz text (question or option)
 * @return string Safe HTML output
 */
function safe_quiz_html($text) {
    // Strip everything except whitelisted formatting tags
    $allowed = '<u><b><i><em><strong><sup><sub><br>';
    return strip_tags($text, $allowed);
}

/**
 * The picture attached to a question or an answer option, as the exam pages print it.
 *
 * Question text can never carry an <img>, because safe_quiz_html() strips tags that
 * a student could paste into the paper - so a real picture lives in its own column
 * and reaches the page only through this function. It fits the image inside a plate
 * rather than cropping it: a diagram of a cell or a circuit means nothing with the
 * edges cut off, and the admin cannot know which edge a phone would choose.
 */
function quiz_picture_html($path, $alt = '') {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    $src = htmlspecialchars(SITE_URL . '/' . ltrim($path, '/'), ENT_QUOTES, 'UTF-8');
    $altText = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
    return '<div class="quiz-picture"><img src="' . $src . '" alt="' . $altText . '" loading="lazy"></div>';
}

/**
 * Read a value from site_settings, falling back when it is absent or empty.
 *
 * The whole table is loaded once per request, so calling this in a loop is free.
 * A missing table (fresh install) is not an error: every caller gets its default.
 */
function site_setting($key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cache[$row['setting_key']] = $row['setting_value'];
                }
            } catch (Throwable $e) {
                $cache = [];
            }
        }
    }
    $value = $cache[$key] ?? null;
    if ($value === null || trim((string)$value) === '') {
        return $default;
    }
    return $value;
}

/**
 * The account students transfer to when they pay by bank transfer.
 *
 * Admin → Settings → Payments owns all three fields. Nothing is shown on the
 * payment modal and no receipt is accepted until every one of them is filled in,
 * so a half-configured account can never send money to the wrong place.
 *
 * @return array{bank_name:string,account_name:string,account_number:string}|null
 */
function payment_bank_details() {
    $bank_name = trim((string)site_setting('payment_bank_name', ''));
    $account_name = trim((string)site_setting('payment_account_name', ''));
    $account_number = preg_replace('/\D+/', '', (string)site_setting('payment_account_number', ''));
    if ($bank_name === '' || $account_name === '' || strlen((string)$account_number) < 10) {
        return null;
    }
    return [
        'bank_name' => $bank_name,
        'account_name' => $account_name,
        'account_number' => $account_number,
    ];
}

/**
 * The banks a challenge winner can be paid into, and the networks an airtime or
 * data prize can be sent to.
 *
 * Single source of truth: claim_reward.php draws its <select> from here and
 * api/v1/rewards.php validates against it, so a claim submitted on the phone is
 * a bank the rewards team already recognises in admin/rewards.php. The key is
 * what lands in reward_claims.bank_name; the label is what the student reads.
 *
 * @return array<string, string>
 */
function reward_bank_options() {
    return [
        'Access Bank' => 'Access Bank',
        'Citibank' => 'Citibank',
        'Ecobank' => 'Ecobank',
        'Fidelity Bank' => 'Fidelity Bank',
        'First Bank of Nigeria' => 'First Bank of Nigeria',
        'FCMB' => 'First City Monument Bank',
        'Globus Bank' => 'Globus Bank',
        'GTBank' => 'Guaranty Trust Bank',
        'Heritage Bank' => 'Heritage Bank',
        'Keystone Bank' => 'Keystone Bank',
        'Kuda Bank' => 'Kuda Microfinance Bank',
        'Moniepoint' => 'Moniepoint',
        'OPay' => 'OPay',
        'PalmPay' => 'PalmPay',
        'Polaris Bank' => 'Polaris Bank',
        'Providus Bank' => 'Providus Bank',
        'Stanbic IBTC' => 'Stanbic IBTC Bank',
        'Standard Chartered' => 'Standard Chartered',
        'Sterling Bank' => 'Sterling Bank',
        'SunTrust Bank' => 'SunTrust Bank',
        'Titan Trust Bank' => 'Titan Trust Bank',
        'Union Bank' => 'Union Bank',
        'UBA' => 'United Bank for Africa (UBA)',
        'Unity Bank' => 'Unity Bank',
        'Wema Bank' => 'Wema Bank',
        'Zenith Bank' => 'Zenith Bank',
    ];
}

/** @return array<string, string> */
function reward_network_options() {
    return [
        'MTN' => 'MTN',
        'Airtel' => 'Airtel',
        'Glo' => 'Glo',
        '9mobile' => '9mobile',
    ];
}

/**
 * Every social network the admin can fill in, in display order.
 *
 * Single source of truth: the settings form renders one field per entry here and
 * the public pages render one icon per entry that has a value, so the two cannot
 * drift apart. Adding a network takes one line.
 *
 * @return array<string, array{label:string,icon:string,color:string}>
 */
function site_social_networks() {
    // fa-x-twitter needs Font Awesome 6.4 and this site loads 6.0, so the X
    // handle keeps the twitter glyph.
    return [
        'facebook'  => ['label' => 'Facebook',  'icon' => 'fa-facebook-f',  'color' => '#1877F2'],
        'twitter'   => ['label' => 'X',         'icon' => 'fa-twitter',     'color' => '#000000'],
        'instagram' => ['label' => 'Instagram', 'icon' => 'fa-instagram',   'color' => '#E4405F'],
        'youtube'   => ['label' => 'YouTube',   'icon' => 'fa-youtube',     'color' => '#FF0000'],
        'tiktok'    => ['label' => 'TikTok',    'icon' => 'fa-tiktok',      'color' => '#000000'],
        'linkedin'  => ['label' => 'LinkedIn',  'icon' => 'fa-linkedin-in', 'color' => '#0A66C2'],
    ];
}

/**
 * The social networks that are actually configured, ready to render.
 *
 * A blank handle is omitted, so no icon ever links to a dead '#'.
 *
 * @return array<string, array{url:string,label:string,icon:string,color:string}>
 */
function site_socials() {
    $configured = [];
    foreach (site_social_networks() as $key => $meta) {
        $url = trim((string)site_setting('social_' . $key, ''));
        if ($url === '' || $url === '#') {
            continue;
        }
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        $configured[$key] = $meta + ['url' => $url];
    }
    return $configured;
}

/**
 * Redirect to $target and show a toast there.
 *
 * Use this after any action the user triggered, so the result is always visible
 * without hunting for a message that may be above the fold.
 *
 * @param string $target Absolute URL, or a path relative to SITE_URL
 * @param string $message  What to show
 * @param string $type     success | error | info
 */
function redirect_with_notice($target, $message, $type = 'success') {
    if (!preg_match('~^https?://~i', $target)) {
        $target = rtrim(SITE_URL, '/') . '/' . ltrim($target, '/');
    }
    // A #anchor must stay after the query string, not wrap it.
    $fragment = '';
    $hash = strpos($target, '#');
    if ($hash !== false) {
        $fragment = substr($target, $hash);
        $target = substr($target, 0, $hash);
    }
    $query = http_build_query(['msg' => $message, 'msg_type' => $type]);
    $separator = (strpos($target, '?') === false) ? '?' : '&';
    redirect($target . $separator . $query . $fragment);
}

/**
 * The logo the admin uploaded on the Settings page, as an absolute URL.
 *
 * Before this existed the upload only changed certificates and emails while the
 * site kept showing the bundled icon. Empty string means nothing was uploaded.
 */
function site_logo_url() {
    $path = trim((string)site_setting('site_logo', ''));
    if ($path === '') {
        return '';
    }
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * One settings value with the same fallback used everywhere it is displayed.
 *
 * Keeps a page that shows the value and a form that validates it from drifting
 * apart when the admin leaves the field blank.
 */
function site_contact_email() {
    $email = trim((string)site_setting('support_email', ADMIN_EMAIL));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : ADMIN_EMAIL;
}

function site_contact_phone() {
    return trim((string)site_setting('contact_phone', WHATSAPP_NUMBER));
}

function site_battle_wager_min() {
    return max(0, (int)site_setting('battle_wager_min', '10'));
}

function site_battle_wager_max() {
    return max(site_battle_wager_min(), (int)site_setting('battle_wager_max', '500'));
}

/**
 * Fixed-window abuse counter, stored in the database so it survives a reload
 * and is shared by every server and every visitor.
 *
 * Returns ['limited' => bool, 'retry_seconds' => int]. Callers key it on
 * something they cannot forge (an account address, a session) rather than only
 * on the client IP, which can be spoofed through the proxy headers.
 *
 * A broken limiter never blocks a legitimate user: every failure path returns
 * limited=false, because losing the counter is far cheaper than locking people
 * out of their accounts.
 *
 * @param string $key           What to count, e.g. 'pw_reset_email:sha1...'
 * @param int    $maxHits       Allowed hits inside the window
 * @param int    $windowSeconds Window length in seconds
 */
function rate_limit_check($key, $maxHits, $windowSeconds) {
    global $pdo;
    $open = ['limited' => false, 'retry_seconds' => 0];
    static $ready = false;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return $open;
    }

    if (!$ready) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                rate_key VARCHAR(191) PRIMARY KEY,
                hits INT NOT NULL DEFAULT 1,
                window_start DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $ready = true;
        } catch (Throwable $e) {
            log_error('Rate limit table unavailable', ['err' => $e->getMessage()]);
            return $open;
        }
    }

    try {
        $stmt = $pdo->prepare("SELECT hits, window_start FROM rate_limits WHERE rate_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->prepare("INSERT INTO rate_limits (rate_key, hits, window_start) VALUES (?, 1, NOW())")
                ->execute([$key]);
            return $open;
        }

        $elapsed = time() - strtotime($row['window_start']);
        if ($elapsed >= $windowSeconds) {
            $pdo->prepare("UPDATE rate_limits SET hits = 1, window_start = NOW() WHERE rate_key = ?")
                ->execute([$key]);
            // Keys are reused forever, so clear old ones now and then.
            if (random_int(1, 100) === 1) {
                $pdo->exec("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 DAY)");
            }
            return $open;
        }

        if ((int)$row['hits'] >= $maxHits) {
            return ['limited' => true, 'retry_seconds' => max(1, $windowSeconds - $elapsed)];
        }

        $pdo->prepare("UPDATE rate_limits SET hits = hits + 1 WHERE rate_key = ?")->execute([$key]);
        return $open;
    } catch (Throwable $e) {
        log_error('Rate limit check failed', ['err' => $e->getMessage()]);
        return $open;
    }
}

/**
 * Presence for as many people as you like, in one query.
 *
 * Two rules live here and nowhere else, so the website's message list, a public
 * profile and the phone can never disagree about whether someone is "Online":
 *
 *   · a person counts as online while their last activity is inside $window
 *     seconds. The web marks it in includes/header.php and the token API marks
 *     it in api/v1/_bootstrap.php - one minute apart at the coarsest.
 *   · `privacy_share_status` off means the timestamp is withheld, not faked. The
 *     caller gets null, which every surface already renders as its neutral word
 *     ("Offline"), so a hidden person never looks like they went quiet today.
 *
 * The key is newer than the accounts, so a missing row means "shares".
 *
 * @return array<int, array{online: bool, last_active: ?string, share: bool}>
 */
function presence_for_users(array $ids, $pdo, int $window = 300)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids || !($pdo instanceof PDO)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.last_active, COALESCE(m.meta_value, '1') AS share_status
               FROM users u
               LEFT JOIN user_meta m ON m.user_id = u.id AND m.meta_key = 'privacy_share_status'
              WHERE u.id IN ({$placeholders})"
        );
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        log_error('Presence lookup failed', ['err' => $e->getMessage()]);
        return [];
    }

    $now = time();
    $out = [];
    foreach ($rows as $r) {
        $share = (int)$r['share_status'] !== 0;
        $seen = !empty($r['last_active']) ? strtotime($r['last_active']) : 0;
        $out[(int)$r['id']] = [
            'share' => $share,
            'online' => $share && $seen > 0 && ($now - $seen) < $window,
            'last_active' => $share && $seen > 0 ? $r['last_active'] : null,
        ];
    }
    return $out;
}

/**
 * Message::canMessage() answers with a sentence meant for a person. A phone has
 * to pick a screen from it, so the same five answers get five machine words
 * here - the one place that reads them, so the API cannot drift from the model.
 */
function message_gate_code($reason)
{
    $reason = (string)$reason;
    if ($reason === '') {
        return 'allowed';
    }
    if (stripos($reason, 'disabled direct messages') !== false) {
        return 'dm_disabled';
    }
    if (stripos($reason, 'You have blocked') !== false) {
        return 'you_blocked';
    }
    if (stripos($reason, 'User not found') !== false) {
        return 'no_user';
    }
    if (stripos($reason, 'yourself') !== false) {
        return 'self';
    }
    return 'blocked';
}
?>
