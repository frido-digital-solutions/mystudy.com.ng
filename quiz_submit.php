<?php
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/models/QuizAttempt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/quizzes.php');
}

$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    set_flash_message('error', 'Invalid form token.');
    redirect(SITE_URL . '/quizzes.php');
}

$quizId = (int)($_POST['quiz_id'] ?? 0);
$mode = $_POST['mode'] ?? 'normal';
if ($mode !== 'multi' && $quizId <= 0) {
    set_flash_message('error', 'Invalid quiz.');
    redirect(SITE_URL . '/quizzes.php');
}

$quizModel = new Quiz($pdo);
$allowedQuizIds = [];
$presentedQuestionIds = [];

if ($mode === 'multi') {
    if (empty($_SESSION['custom_quiz']) || time() > $_SESSION['custom_quiz']['expires']) {
        unset($_SESSION['custom_quiz']);
        set_flash_message('error', 'Custom quiz session expired or not found.');
        redirect(SITE_URL . '/quiz_multi.php');
    }
    $customData = $_SESSION['custom_quiz'];

    // The carrier quiz comes from the session that generated the questions, never from POST,
    // so a free multi-subject set can never be written onto a paid/challenge leaderboard.
    $quizId = (int)($customData['quiz_id'] ?? 0);
    $carrier = $quizId > 0 ? $quizModel->getById($quizId) : null;
    if (!$carrier || ($carrier['mode'] ?? 'normal') === 'challenge' || !empty($carrier['is_paid'])) {
        unset($_SESSION['custom_quiz']);
        set_flash_message('error', 'Custom quiz session expired or not found.');
        redirect(SITE_URL . '/quiz_multi.php');
    }

    $quiz = [
        'id' => $quizId,
        'duration_seconds' => $customData['duration'],
        'mode' => 'multi'
    ];
    $questions = $customData['questions'];
    // A multi-subject set legitimately spans several source quizzes
    $allowedQuizIds = array_map(fn($q) => (int)($q['quiz_id'] ?? 0), $questions);
    $presentedQuestionIds = array_map(fn($q) => (int)($q['id'] ?? 0), $questions);
} else {
    $quiz = $quizModel->getById($quizId);
    if (!$quiz) {
        set_flash_message('error', 'Quiz not found.');
        redirect(SITE_URL . '/quizzes.php');
    }

    // Server-side anti-cheat: prevent duplicate submissions for challenge/paid quizzes
    $isSpecialQuiz = !empty($quiz['is_paid']) || ($quiz['mode'] ?? 'normal') === 'challenge';
    if ($isSpecialQuiz) {
        $dupCheck = new QuizAttempt($pdo);
        if ($dupCheck->hasAttempted($quizId, (int)$_SESSION['user_id'])) {
            set_flash_message('error', 'You have already submitted this quiz.');
            redirect(SITE_URL . '/quizzes.php');
        }
    }

    $questions = $quizModel->getQuestions($quizId);
    $allowedQuizIds = [$quizId];
    $presentedQuestionIds = array_map('intval', array_column($questions, 'id'));
}
$answers = [];
$postedAnswers = isset($_POST['q']) && is_array($_POST['q']) ? $_POST['q'] : [];
foreach ($questions as $q) {
    $qid = (int)$q['id'];
    $raw = $postedAnswers[$qid] ?? null;
    // For subjective: raw is a string. For objective: raw is a numeric option ID string.
    // Pass as-is; submitAttempt handles both types.
    $answers[$qid] = $raw;
}

// Compute duration used from session start
$duration = (int)($quiz['duration_seconds'] ?? 900);
$isChallenge = ($quiz['mode'] ?? 'normal') === 'challenge';
$eventEndTs = $isChallenge && !empty($quiz['event_end']) ? strtotime($quiz['event_end']) : null;
$startKey = 'quiz_start_' . $quizId;

$now = time();
$durationUsed = $duration; // Default to full duration if session is missing/tampered

if (isset($_SESSION[$startKey])) {
    $started = (int)$_SESSION[$startKey];
    $calculatedDuration = max(1, $now - $started); // Must be at least 1 second
    
    // Clamp at maximum allowed duration + 5s grace period for lag
    if ($calculatedDuration <= ($duration + 5)) {
        $durationUsed = $calculatedDuration; 
    }
}

// Additionally clamp by remaining time until event end if challenge
if ($isChallenge && $eventEndTs) {
    // If started key exists, calculate time until end, otherwise use raw duration limit
    $startedTime = isset($_SESSION[$startKey]) ? (int)$_SESSION[$startKey] : ($now - $durationUsed);
    $untilEnd = max(1, $eventEndTs - $startedTime);
    if ($durationUsed > $untilEnd) { 
        $durationUsed = $untilEnd; 
    }
}

// clear start key for next run
unset($_SESSION[$startKey]);

$attemptModel = new QuizAttempt($pdo);
try {
    $attemptId = $attemptModel->submitAttempt($quizId, (int)$_SESSION['user_id'], $answers, $durationUsed, $allowedQuizIds, $presentedQuestionIds);

    // Anti-cheat: flag the quiz as just-submitted so the result page can clear localStorage
    $_SESSION['quiz_just_submitted'] = $quizId;

    // The custom set is single-use: consuming it here stops the same session being replayed.
    if ($mode === 'multi') {
        unset($_SESSION['custom_quiz']);
    }

    // --- BATTLE SYSTEM INTERCEPTOR ---
    $activeBattleId = (int)($_SESSION['active_battle_id'] ?? 0);
    unset($_SESSION['active_battle_id']);
    if ($activeBattleId > 0) {
        require_once __DIR__ . '/models/Battle.php';
        $battleModel = new Battle($pdo);
        $activeBattle = $battleModel->getBattle($activeBattleId);
        $uid = (int)$_SESSION['user_id'];
        // Only credit a battle that is actually this user's and actually this quiz's
        if ($activeBattle && (int)$activeBattle['quiz_id'] === $quizId
            && ((int)$activeBattle['challenger_id'] === $uid || (int)$activeBattle['opponent_id'] === $uid)) {
            $stmt = $pdo->prepare("SELECT score FROM quiz_attempts WHERE id = ?");
            $stmt->execute([$attemptId]);
            $score = (int)$stmt->fetchColumn();

            $battleModel->recordAttempt($activeBattleId, $uid, $score, $durationUsed);
        }
    }

    redirect(SITE_URL . '/quiz_result.php?attempt=' . $attemptId . '&t=' . time());
} catch (Throwable $e) {
    unset($_SESSION['active_battle_id']);
    log_error('quiz_submit failed', ['quiz_id' => $quizId, 'user_id' => (int)($_SESSION['user_id'] ?? 0), 'err' => $e->getMessage()]);
    set_flash_message('error', 'Could not submit quiz.');
    redirect(SITE_URL . '/quizzes.php');
}
