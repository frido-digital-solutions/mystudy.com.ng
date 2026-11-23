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
    } elseif ($c === 'PAYSTACK_PUBLIC_KEY' || $c === 'PAYSTACK_SECRET_KEY') {
        // A key that does not look like a Paystack key is not one, so it is
        // reported the way the checkout will treat it: off.
        $usable = function_exists('msb_paystack_key')
            ? msb_paystack_key($c === 'PAYSTACK_SECRET_KEY')
            : ($v !== '');
        msb_health_line('  ' . $c, $usable ? $v : ($v === '' ? 'EMPTY (feature off)' : 'not a Paystack key - treated as EMPTY (feature off)'));
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
        msb_health_line('  tables', implode(', ', $tables));

        // The columns, compared against the ones the code expects. A table that
        // exists but is a column short is the quiet kind of wrong: the page
        // that needs the column asks for something the database cannot give it.
        if (function_exists('msb_schema_columns')) {
            $drift = [];
            $expectedTables = 0;
            $expectedColumns = 0;
            foreach (msb_schema_columns() as $table => $definitions) {
                if (!in_array($table, $tables, true)) continue;
                $expectedTables++;
                $expectedColumns += count($definitions);
                try {
                    $have = [];
                    foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                        $have[strtolower((string)$column['Field'])] = true;
                    }
                } catch (Throwable $e) {
                    $drift[] = $table . ': unreadable';
                    continue;
                }
                $missing = [];
                foreach ($definitions as $name => $definition) {
                    if (!isset($have[strtolower((string)$name)])) $missing[] = $name;
                }
                if ($missing) $drift[] = $table . ': missing ' . implode(', ', $missing);
            }
            msb_health_line('  columns expected', (string)$expectedColumns . ' across ' . $expectedTables . ' tables');
            msb_health_line('  column drift', $drift ? implode(' | ', $drift) : 'none - every expected column is present');
        }
    }
} catch (Throwable $e) {
    msb_health_line('database FAILED', get_class($e) . ': ' . $e->getMessage());
}

/**
 * The phones.
 *
 * A push notification only exists if the phone told the server where to send
 * it, so the number of rows here is the number of phones that can hear
 * anything at all. It is the first thing to look at when "no notification
 * arrived" is the complaint.
 */
echo "\n-- phones --\n";
try {
    global $pdo;
    $devices = (int)$pdo->query('SELECT COUNT(*) FROM device_push_tokens')->fetchColumn();
    $users = (int)$pdo->query('SELECT COUNT(DISTINCT user_id) FROM device_push_tokens')->fetchColumn();
    msb_health_line('  phones registered', (string)$devices);
    msb_health_line('  accounts with a phone', (string)$users);
    foreach ($pdo->query('SELECT user_id, platform, rich, last_seen_at FROM device_push_tokens ORDER BY last_seen_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC) as $d) {
        msb_health_line('    device', 'user ' . $d['user_id'] . ' | ' . $d['platform'] . ' | rich=' . $d['rich'] . ' | last seen ' . $d['last_seen_at']);
    }
} catch (Throwable $e) {
    msb_health_line('  phones', 'could not be read: ' . $e->getMessage());
}

/**
 * What the phones have been saying.
 *
 * When a phone cannot register, the reason is on the phone and nowhere else.
 * The phone sends it back, and this is where it is read.
 */
echo "\n-- what the phones reported --\n";
try {
    $rows = $pdo->query('SELECT user_id, step, detail, created_at FROM push_diagnostics ORDER BY id DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        msb_health_line('  nothing reported', 'no phone has reported a problem');
    }
    foreach ($rows as $r) {
        msb_health_line('  ' . $r['created_at'], 'user ' . ($r['user_id'] ?? '?') . ' | ' . $r['step'] . ' | ' . $r['detail']);
    }
} catch (Throwable $e) {
    msb_health_line('  reports', 'could not be read: ' . $e->getMessage());
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

/**
 * The last few lines of the error log. A 401 the phone never recovered from,
 * or a push that was refused, is written here and nowhere else.
 */
echo "\n-- recent errors --\n";
$logFile = __DIR__ . '/../logs/error.log';
if (is_readable($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $tail = array_slice($lines, -12);
    if (!$tail) msb_health_line('  error log', 'empty');
    foreach ($tail as $line) msb_health_line('  ', $line);
} else {
    msb_health_line('  error log', 'not readable');
}

echo "\n-- done --\n";
