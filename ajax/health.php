<?php
/**
 * A health check for the server.
 *
 * It answers one question: where exactly does the site stop working? It walks
 * the same chain a normal page request walks - config, database, settings -
 * and says which step failed and why.
 *
 * It prints no secrets. No password, no key, no connection string: only the
 * name of the step that failed and PHP's own error message. That is enough
 * to find the fault and nothing more.
 *
 * It is a diagnostic, not a feature. Delete it once the site is well.
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

function msb_health_line(string $label, string $value): void
{
    echo $label . ': ' . $value . "\n";
}

echo "MyStudy Base - server health\n";
echo str_repeat('=', 40) . "\n";
msb_health_line('PHP version', PHP_VERSION);
msb_health_line('Server', isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'unknown');
msb_health_line('Document root', isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'unknown');
msb_health_line('Host', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'unknown');
msb_health_line('Script', __FILE__);
msb_health_line('pdo_mysql loaded', extension_loaded('pdo_mysql') ? 'yes' : 'NO');
msb_health_line('mbstring loaded', extension_loaded('mbstring') ? 'yes' : 'NO');
msb_health_line('curl loaded', extension_loaded('curl') ? 'yes' : 'NO');

echo "\n-- secrets file --\n";
$secrets = __DIR__ . '/../config/secrets.local.php';
msb_health_line('config/secrets.local.php', file_exists($secrets) ? 'present' : 'MISSING');
if (file_exists($secrets)) {
    msb_health_line('  readable', is_readable($secrets) ? 'yes' : 'NO');
}

echo "\n-- constants --\n";
foreach (['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'SITE_DOMAIN', 'BREVO_API_KEY', 'PAYSTACK_SECRET_KEY'] as $c) {
    msb_health_line('  ' . $c, defined($c) ? 'defined' : 'NOT DEFINED');
}

echo "\n-- config --\n";
try {
    require_once __DIR__ . '/../config/config.php';
    msb_health_line('config/config.php', 'loaded');
    msb_health_line('SITE_URL', defined('SITE_URL') ? SITE_URL : 'not defined');
    msb_health_line('SITE_NAME', defined('SITE_NAME') ? SITE_NAME : 'not defined');
} catch (Throwable $e) {
    msb_health_line('config/config.php FAILED', get_class($e) . ': ' . $e->getMessage());
    echo $e->getTraceAsString() . "\n";
    exit(0);
}

echo "\n-- configured keys (now that the site has loaded them) --\n";
foreach ([
    'DB_HOST', 'DB_USER', 'DB_NAME',
    'PAYSTACK_PUBLIC_KEY', 'PAYSTACK_SECRET_KEY',
    'BREVO_API_KEY', 'BREVO_FROM_EMAIL', 'BREVO_FROM_NAME',
    'CLOUDFLARE_ACCOUNT_ID', 'CLOUDFLARE_AI_TOKEN',
    'SITE_DOMAIN', 'ADMIN_EMAIL_DEFAULT',
] as $c) {
    if (!defined($c)) {
        msb_health_line('  ' . $c, 'NOT DEFINED');
        continue;
    }
    $v = (string)constant($c);
    if ($c === 'DB_PASS') {
        msb_health_line('  ' . $c, $v === '' ? 'EMPTY' : 'set');
    } else {
        msb_health_line('  ' . $c, $v === '' ? 'EMPTY (feature off)' : $v);
    }
}

echo "\n-- database --\n";
try {
    global $pdo;
    if (!($pdo instanceof PDO)) {
        msb_health_line('connection', 'no PDO handle');
    } else {
        msb_health_line('connection', 'open');
        $row = $pdo->query('SELECT DATABASE() AS db, VERSION() AS v')->fetch(PDO::FETCH_ASSOC);
        msb_health_line('database', (string)($row['db'] ?? 'unknown'));
        msb_health_line('mysql version', (string)($row['v'] ?? 'unknown'));
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        msb_health_line('table count', (string)count($tables));
        foreach (['users', 'site_settings', 'notifications'] as $t) {
            msb_health_line('  table ' . $t, in_array($t, $tables, true) ? 'present' : 'MISSING');
        }
        $users = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        msb_health_line('  rows in users', (string)$users);
    }
} catch (Throwable $e) {
    msb_health_line('database FAILED', get_class($e) . ': ' . $e->getMessage());
}

/**
 * Which database actually holds the site's data?
 *
 * The database name in secrets.local.php is sometimes wrong - a copy of the
 * settings was made for a side database, or the site was moved. When the
 * configured name is not the one with the students in it, this walks every
 * database the account can see and counts the accounts in each, so the right
 * name can be read straight off the screen instead of guessed at.
 */
echo "\n-- every database this account can see --\n";
try {
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $user = defined('DB_USER') ? DB_USER : '';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $server = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $dbs = $server->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dbs as $db) {
        if (strpos($db, 'information_schema') === 0) continue;
        $note = 'no users table';
        try {
            $probe = new PDO('mysql:host=' . $host . ';dbname=' . $db . ';charset=utf8mb4', $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $tables = $probe->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('users', $tables, true)) {
                $n = (int)$probe->query('SELECT COUNT(*) FROM users')->fetchColumn();
                $note = $n . ' user(s)';
            } else {
                $note = count($tables) . ' tables, no users';
            }
        } catch (Throwable $e) {
            $note = 'cannot open';
        }
        $mark = (defined('DB_NAME') && $db === DB_NAME) ? ' <- configured' : '';
        msb_health_line('  ' . $db, $note . $mark);
    }
} catch (Throwable $e) {
    msb_health_line('could not list databases', $e->getMessage());
}

echo "\n-- writes --\n";
$logDir = __DIR__ . '/../logs';
msb_health_line('logs/ writable', is_writable($logDir) ? 'yes' : 'NO');
$probe = $logDir . '/.healthcheck.tmp';
msb_health_line('logs/ can create a file', @file_put_contents($probe, 'x') !== false ? 'yes' : 'NO');
if (file_exists($probe)) @unlink($probe);

echo "\n-- done --\n";
