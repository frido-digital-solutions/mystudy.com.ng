<?php
/**
 * Script to notify all participants when a challenge ends
 * 
 * Usage: 
 *   php notify_challenge_end.php quiz_id=<id>   (specific quiz)
 *   php notify_challenge_end.php auto            (auto-detect ended challenges)
 *   Or call from admin panel
 * 
 * Can be run as a cron job every 5 minutes:
 *   php /path/to/notify_challenge_end.php auto
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Notification.php';

// Check if running from command line or admin
$isAdmin = is_logged_in() && has_role('admin');
$isCLI = php_sapi_name() === 'cli';

if (!$isAdmin && !$isCLI) {
    die('Unauthorized access');
}

// Parse args
if ($isCLI) {
    parse_str(implode('&', array_slice($argv, 1)), $params);
    $quizId = (int)($params['quiz_id'] ?? 0);
    $autoMode = in_array('auto', $argv);
} else {
    $quizId = (int)($_POST['quiz_id'] ?? $_GET['quiz_id'] ?? 0);
    $autoMode = isset($_GET['auto']);
}

$notificationModel = new Notification($pdo);

if ($autoMode) {
    // Auto-detect challenges that have ended in the last 10 minutes and haven't been notified yet
    $stmt = $pdo->query("
        SELECT id, title FROM quizzes 
        WHERE mode = 'challenge' 
        AND event_end IS NOT NULL 
        AND event_end <= NOW() 
        AND event_end >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        AND id NOT IN (
            SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(link, 'quiz_id=', -1), '&', 1) 
            FROM notifications 
            WHERE type IN ('challenge_complete', 'challenge_winner')
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        )
    ");
    $ended = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($ended)) {
        echo "No newly ended challenges found.\n";
        exit;
    }
    
    foreach ($ended as $q) {
        echo "Processing challenge: {$q['title']} (ID: {$q['id']})...\n";
        try {
            $notificationModel->notifyChallengeEnd((int)$q['id']);
            echo "  ✅ Notifications sent\n";
        } catch (Exception $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
    }
} else {
    // Specific quiz
    if ($quizId <= 0) {
        die("Error: Invalid quiz ID\n");
    }
    
    $stmt = $pdo->prepare("SELECT title FROM quizzes WHERE id = ? AND mode = 'challenge'");
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quiz) {
        die("Error: Challenge quiz not found\n");
    }
    
    try {
        $notificationModel->notifyChallengeEnd($quizId);
        echo "✅ Notifications sent to all participants for: {$quiz['title']} (ID: {$quizId})\n";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

