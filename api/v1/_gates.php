<?php
/**
 * The doors that stand between a student and a question paper.
 *
 * quiz.php (sitting an exam), offline.php (saving one for a flight) and any
 * future entry point all walk through the same two functions here, in the same
 * order, so a set a student cannot open in a browser cannot be opened anywhere
 * else either. A copy of these rules kept per endpoint is how the app used to
 * fall out of step with the website.
 */

require_once __DIR__ . '/../../models/Gamification.php';

/**
 * The three doors quiz.php walks through before it prints a question: who the
 * set is for, the level it asks for, and whether entry has been paid for.
 */
function quiz_gate(PDO $pdo, array $quiz, array $user): array
{
    $userId = (int)$user['id'];
    $type = (string)($quiz['type'] ?? 'both');
    $userType = (string)($user['user_type'] ?? '');

    if ($type !== 'both' && $userType !== '' && $type !== $userType) {
        return ['no', 'wrong_students', $type === 'university'
            ? 'This quiz is for university students.'
            : 'This quiz is for secondary students.'];
    }

    $reqLevel = (int)($quiz['required_level'] ?? 0);
    if ($reqLevel > 0) {
        $level = (int)((new Gamification($pdo))->getUserStats($userId)['level'] ?? 1);
        if ($level < $reqLevel) {
            return ['no', 'level_locked', "You need to be Level $reqLevel to sit this one. You are Level $level."];
        }
    }

    if (!empty($quiz['is_paid'])) {
        require_once __DIR__ . '/../../models/Payment.php';
        require_once __DIR__ . '/../../models/AccessCode.php';
        $paid = (new Payment($pdo))->hasPaid($userId, (int)$quiz['id'])
            || (new AccessCode($pdo))->hasUserUsedACodeForQuiz($userId, (int)$quiz['id']);
        if (!$paid) {
            return ['no', 'paywall', 'This quiz needs entry payment.', (int)$quiz['id']];
        }
    }

    return ['yes', '', ''];
}

/**
 * The two extra doors a challenge set has, checked only when a sitting begins:
 * the event window, and the seat list a free capped challenge keeps. A paper
 * already in progress is never refused at submit time because the clock struck
 * the closing hour - the answers still go in.
 */
function quiz_challenge_gate(PDO $pdo, array $quiz, int $userId): array
{
    if (($quiz['mode'] ?? 'normal') !== 'challenge') {
        return ['yes', '', ''];
    }
    $now = time();
    $start = !empty($quiz['event_start']) ? strtotime((string)$quiz['event_start']) : false;
    $end = !empty($quiz['event_end']) ? strtotime((string)$quiz['event_end']) : false;
    if ($start !== false && $now < $start) {
        return ['no', 'not_live', 'This challenge has not opened yet. Come back when the countdown ends.'];
    }
    if ($end !== false && $now >= $end) {
        return ['no', 'ended', 'This challenge has ended. Your result is in My Quizzes.'];
    }
    if (empty($quiz['is_paid']) && !empty($quiz['max_participants'])) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM challenge_participants WHERE quiz_id = ? AND user_id = ?');
        $stmt->execute([(int)$quiz['id'], $userId]);
        $joined = (int)$stmt->fetchColumn() > 0;
        if (!$joined) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ? AND user_id = ?');
            $stmt->execute([(int)$quiz['id'], $userId]);
            if ((int)$stmt->fetchColumn() === 0) {
                return ['no', 'not_joined', 'Join the challenge first - it has a limited number of seats.'];
            }
        }
    }
    return ['yes', '', ''];
}
