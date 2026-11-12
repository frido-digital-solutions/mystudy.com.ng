<?php
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Quiz.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid token']);
    exit;
}

$quizId = (int)($_POST['quiz_id'] ?? 0);
if ($quizId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid quiz']);
    exit;
}

try {
    $mode = $_POST['mode'] ?? 'normal';

    if ($mode === 'multi' && !empty($_SESSION['custom_quiz'])) {
        $duration = (int)$_SESSION['custom_quiz']['duration'];
        $quiz = [
            'id' => $quizId,
            'duration_seconds' => $duration,
            'mode' => 'multi'
        ];
        $isChallenge = false;
        $eventStartTs = null;
        $eventEndTs = null;
    } else {
        $quizModel = new Quiz($pdo);
        $quiz = $quizModel->getById($quizId);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Quiz not found']);
            exit;
        }

        $duration = (int)($quiz['duration_seconds'] ?? 900);
        $isChallenge = isset($quiz['mode']) && $quiz['mode'] === 'challenge';
        $eventStartTs = $isChallenge && !empty($quiz['event_start']) ? strtotime($quiz['event_start']) : null;
        $eventEndTs = $isChallenge && !empty($quiz['event_end']) ? strtotime($quiz['event_end']) : null;
    }

    $now = time();

    if ($isChallenge) {
        if ($eventStartTs && $now < $eventStartTs) {
            $startIn = max(0, $eventStartTs - $now);
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Not started yet', 'code' => 'not_started', 'start_in' => $startIn]);
            exit;
        }
        if ($eventEndTs && $now >= $eventEndTs) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Event ended', 'code' => 'ended']);
            exit;
        }
    }
    $startKey = 'quiz_start_' . $quizId;

    if (empty($_SESSION[$startKey])) {
        $_SESSION[$startKey] = $now;
        $remaining = $duration;
    } else {
        $elapsed = max(0, $now - (int)$_SESSION[$startKey]);
        $remaining = max(0, $duration - $elapsed);
    }

    // Cap by event end if challenge
    if ($isChallenge && $eventEndTs) {
        $untilEnd = max(0, $eventEndTs - $now);
        if ($untilEnd < $remaining) { $remaining = $untilEnd; }
    }

    echo json_encode(['ok' => true, 'remaining' => $remaining]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
