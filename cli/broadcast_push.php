<?php
/**
 * Broadcast a push notification to every phone that has the app installed.
 *
 * The admin page (admin/app_push.php) sends the same thing. This one is here
 * for the moments when a phone is the quickest way to reach everybody: from
 * an SSH session, from a cron job, or from the keyboard.
 *
 *   php cli/broadcast_push.php --title="New quizzes are live" \
 *       --body="We just added 20 new WAEC and JAMB practice quizzes." \
 *       --screen=Quizzes
 *
 * Options:
 *   --title=TEXT     The headline on the phone. Required.
 *   --body=TEXT      The sentence under it. Required.
 *   --screen=NAME    The app screen a tap opens. Default Home.
 *   --no-inapp       Only send the push; do not write the in-app notice.
 *   --dry-run        Report what would happen without sending anything.
 *   --host=NAME      Host to treat the site as. Useful on a server with no
 *                    web request, e.g. --host=mystudy.com.ng
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script runs from the terminal only.\n");
}

// The site normally learns its own address from the request that is being
// served. There is no request here, so the host is taken from the command
// line instead - otherwise every link in the notice would point at
// http://localhost.
$options = getopt('', ['title:', 'body:', 'screen::', 'host::', 'no-inapp', 'dry-run', 'help']);
if (isset($options['help'])) {
    echo "Usage: php cli/broadcast_push.php --title=\"...\" --body=\"...\" [--screen=Home] [--dry-run]\n";
    exit(0);
}
if (!empty($options['host'])) {
    $_SERVER['HTTP_HOST'] = (string)$options['host'];
}
$_SERVER['REQUEST_METHOD'] = 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Broadcast.php';

$title = trim((string)($options['title'] ?? ''));
$body = trim((string)($options['body'] ?? ''));
$screen = trim((string)($options['screen'] ?? 'Home'));
$dryRun = array_key_exists('dry-run', $options);
$alsoInApp = !array_key_exists('no-inapp', $options);

if ($title === '' || $body === '') {
    fwrite(STDERR, "Both --title and --body are required.\n");
    fwrite(STDERR, "Try: php cli/broadcast_push.php --help\n");
    exit(1);
}

$allowed = ['Home', 'Quizzes', 'Chats', 'Community', 'Battles', 'Learn', 'Notifications'];
if (!in_array($screen, $allowed, true)) {
    fwrite(STDERR, "Unknown screen \"$screen\"; using Home instead.\n");
    $screen = 'Home';
}

try {
    $row = $pdo->query('SELECT COUNT(*) AS devices, COUNT(DISTINCT user_id) AS users FROM device_push_tokens')
        ->fetch(PDO::FETCH_ASSOC);
    $devices = (int)($row['devices'] ?? 0);
    $users = (int)($row['users'] ?? 0);
} catch (Throwable $e) {
    fwrite(STDERR, "Could not reach the database: " . $e->getMessage() . "\n");
    exit(1);
}

echo "MyStudy Base - broadcast to the app\n";
echo str_repeat('-', 40) . "\n";
echo "Title  : $title\n";
echo "Body   : $body\n";
echo "Opens  : $screen\n";
echo "Devices: $devices (across $users account(s))\n";
echo "In-app : " . ($alsoInApp ? 'yes' : 'no') . "\n";

if ($dryRun) {
    echo "\nDry run - nothing was sent.\n";
    exit(0);
}

if ($devices === 0) {
    echo "\nNo phone has registered with the app yet, so there is nobody to reach.\n";
    echo "The notice will still appear in the app's Notifications list.\n";
}

$result = msb_broadcast_all($pdo, $title, $body, [
    'screen' => $screen,
    'inapp' => $alsoInApp,
]);

echo "\n" . str_repeat('-', 40) . "\n";
echo "Delivered to : {$result['sent']} device(s)\n";
echo "Failed       : {$result['failed']}\n";
echo "Stale removed: {$result['dead']}\n";
echo "In-app notices: {$result['inapp']}\n";

if (!empty($result['push_error'])) {
    fwrite(STDERR, "Push problem: {$result['push_error']}\n");
    exit(1);
}

echo "\nDone.\n";
