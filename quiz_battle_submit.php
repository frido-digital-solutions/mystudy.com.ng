<?php
// quiz_battle_submit.php - The Standalone Premium Battle Submitter
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/models/QuizAttempt.php';
require_once __DIR__ . '/models/Battle.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Error: Invalid request method.");
}

$userId = (int)$_SESSION['user_id'];
$battleId = (int)($_SESSION['active_battle_id'] ?? 0);
$startKey = 'battle_start_' . $battleId . '_user_' . $userId;

// No exemption for auto_submit: quiz_battle_play.php keeps the token fresh while the timer runs.
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    die("Error: Invalid CSRF token. The form session expired.");
}

if ($battleId <= 0) {
    redirect_with_notice('profile.php', 'Invalid Battle Submission', 'error');
}

$battleModel = new Battle($pdo);
$battle = $battleModel->getBattle($battleId);

if (!$battle) {
    unset($_SESSION[$startKey], $_SESSION['active_battle_id']);
    redirect_with_notice('profile.php', 'Battle Not Found', 'error');
}

$isChallenger = ((int)$battle['challenger_id'] === $userId);
$isOpponent = ((int)$battle['opponent_id'] === $userId);

if (!$isChallenger && !$isOpponent) {
    unset($_SESSION[$startKey], $_SESSION['active_battle_id']);
    redirect_with_notice('profile.php', 'Invalid Battle Submission', 'error');
}

// A turn may only be recorded once, and only while the arena is actually open.
$alreadyPlayed = ($isChallenger && $battle['challenger_score'] !== null)
    || ($isOpponent && $battle['opponent_score'] !== null);
if ($alreadyPlayed || !in_array($battle['status'], ['p1_turn', 'p2_turn'], true)) {
    unset($_SESSION[$startKey], $_SESSION['active_battle_id']);
    header("Location: quiz_battle_result.php?id=" . $battleId);
    exit();
}

// The quiz is taken from the battle row, never from the posted form.
$quizId = (int)$battle['quiz_id'];

// 1. Calculate Time Elapsed securely using the Battle session timestamp
$durationSeconds = isset($_SESSION[$startKey]) ? time() - (int)$_SESSION[$startKey] : 0;

if ($durationSeconds < 0) {
    $durationSeconds = 0; // Sanity check
}

// 2. Log General Quiz Attempt (User still earns regular base XP for just practicing)
// $attemptModel->submitAttempt handles score calculation internally based on the chosen option IDs!
$answers = isset($_POST['q']) && is_array($_POST['q']) ? $_POST['q'] : [];
$attemptModel = new QuizAttempt($pdo);

try {
    $attemptId = $attemptModel->submitAttempt($quizId, $userId, $answers, $durationSeconds);
} catch (Throwable $e) {
    log_error('Battle attempt save failed', ['battle_id' => $battleId, 'quiz_id' => $quizId, 'user_id' => $userId, 'err' => $e->getMessage()]);
    unset($_SESSION[$startKey], $_SESSION['active_battle_id']);
    redirect_with_notice('profile.php', 'Could not save your battle turn. Please try again.', 'error');
}

// We need the score to log it in the Battle schema.
$attemptRecord = $attemptModel->getAttempt($attemptId, $userId);
$score = $attemptRecord ? (int)$attemptRecord['score'] : 0;

// 4. Send the Data Explicitly to the Battle System!
$recorded = $battleModel->recordAttempt($battleId, $userId, $score, $durationSeconds);

// 5. Clean up Battle Sessions
unset($_SESSION[$startKey]);
unset($_SESSION['active_battle_id']);

// 6. Hard route to the Custom Premium Battle Results Page
if (!$recorded) {
    redirect_with_notice('profile.php', 'Your turn could not be recorded in this battle.', 'error');
}
header("Location: quiz_battle_result.php?id=" . $battleId);
exit();