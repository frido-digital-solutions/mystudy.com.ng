<?php
/**
 * Saving a free quiz for the offline world, and handing its result back.
 *
 * Two calls:
 *
 *   GET  ?quiz_id=12   the whole paper, keys included, for a set the student
 *                      can already sit for nothing
 *   POST action=sync   a result the phone marked itself, re-marked here
 *
 * The pair is deliberately symmetrical: nothing about offline play changes a
 * quiz's rules. The same quiz_gate() that stops a browser from opening a paid
 * or level-locked set is what decides whether a pack may be downloaded, and the
 * mark a student gets when they land is computed by the same
 * QuizAttempt::submitAttempt() the website posts to - not by the phone. So a
 * tampered pack, a remembered answer sheet or a device clock in 2031 can only
 * ever change what is shown on that screen, never what is recorded.
 *
 * client_ref is the device's own receipt number for one sitting. Posting the
 * same one twice - which is exactly what a flaky network does - returns the
 * attempt the first posting created instead of a second one. Two postings that
 * arrive together race for the receipt itself, so the sitting is marked once
 * even when both phones still think they are the first (see offline_claim()).
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_gates.php';
require_once __DIR__ . '/_offline.php';
require_once __DIR__ . '/../../models/Quiz.php';
require_once __DIR__ . '/../../models/QuizAttempt.php';

$user = api_auth();
$userId = (int)$user['id'];
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$in = $isPost ? api_input() : [];
$action = $isPost ? api_field($in, 'action') : '';

if (!offline_table_ready($pdo)) {
    api_err('storage_unavailable', 'Offline quizzes are not ready on the server yet. Please retry shortly.', 503);
}

$quizModel = new Quiz($pdo);

// ───────────────────────────────────────────────────────── the pack to keep ──
if (!$isPost && (int)($_GET['quiz_id'] ?? 0) > 0) {
    $quizId = (int)$_GET['quiz_id'];
    $quiz = $quizModel->getById($quizId);
    if (!$quiz) api_err('not_found', 'That quiz does not exist.', 404);

    $gate = quiz_gate($pdo, $quiz, $user);
    if ($gate[0] === 'no') {
        api_err($gate[1], $gate[2], $gate[1] === 'paywall' ? 402 : 403, ['quiz_id' => $quizId]);
    }
    if (!offline_is_free($quiz)) {
        api_err(
            'not_free',
            ($quiz['mode'] ?? 'normal') === 'challenge'
                ? 'Challenge arenas need the live scoreboard, so they cannot be saved for offline.'
                : 'Paid quizzes are unlocked on the server, so they cannot be saved for offline.',
            403,
            ['quiz_id' => $quizId]
        );
    }

    $questions = $quizModel->getQuestions($quizId);
    if (!$questions) api_err('empty_quiz', 'This quiz has no questions yet.', 404);

    // Saved papers are capped: a 500-question set would fill a cheap phone.
    if (count($questions) > 200) {
        api_err('too_big', 'This set is too large to save for offline. It is still fine to take online.', 413, ['quiz_id' => $quizId]);
    }

    api_out(['ok' => true, 'pack' => offline_pack($pdo, $quiz, $questions)]);
}

// ────────────────────────────────────────────────────────── the way back in ──
if ($isPost && $action === 'sync') {
    $ref = trim(api_field($in, 'client_ref'));
    if ($ref === '' || !preg_match('/^[A-Za-z0-9_-]{8,36}$/', $ref)) {
        api_err('bad_ref', 'That saved result is missing its receipt number.', 400);
    }

    // Booked already? Say so before re-checking the doors: a quiz that turned
    // paid after the result landed is still recorded, and the phone only needs
    // to stop queueing it.
    $stmt = $pdo->prepare('SELECT user_id, attempt_id FROM offline_attempts WHERE client_ref = ? LIMIT 1');
    $stmt->execute([$ref]);
    $seen = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($seen && (int)$seen['user_id'] !== $userId) {
        api_err('ref_conflict', 'That saved result belongs to another account.', 403);
    }
    if ($seen && (int)$seen['attempt_id'] > 0) {
        api_out(['ok' => true, 'already_synced' => true, 'receipt' => offline_receipt($pdo, (int)$seen['attempt_id'], $userId)]);
    }

    $quizId = (int)api_field($in, 'quiz_id');
    $quiz = $quizId > 0 ? $quizModel->getById($quizId) : null;
    if (!$quiz) api_err('not_found', 'That quiz does not exist any more.', 404);

    // The doors are the same ones the pack was let out through. If a set turned
    // paid or an arena while it sat on the phone, the result waits for an owner
    // decision rather than being booked for free.
    $gate = quiz_gate($pdo, $quiz, $user);
    if ($gate[0] === 'no') {
        api_err($gate[1], $gate[2], 403, ['quiz_id' => $quizId, 'client_ref' => $ref]);
    }
    if (!offline_is_free($quiz)) {
        api_err('no_longer_free', 'This quiz is no longer free, so the saved result cannot be recorded.', 403, ['quiz_id' => $quizId, 'client_ref' => $ref]);
    }

    $questions = $quizModel->getQuestions($quizId);
    if (!$questions) api_err('empty_quiz', 'This quiz has no questions yet.', 404);
    $servedIds = array_map('intval', array_column($questions, 'id'));

    $duration = (int)($quiz['duration_seconds'] ?? 900);
    $elapsed = max(1, min($duration, (int)api_field($in, 'duration_used')));

    $posted = isset($in['answers']) && is_array($in['answers']) ? $in['answers'] : [];
    $answers = [];
    foreach ($servedIds as $qid) {
        if (!array_key_exists($qid, $posted) && !array_key_exists((string)$qid, $posted)) continue;
        $raw = $posted[$qid] ?? $posted[(string)$qid] ?? null;
        if ($raw === null || $raw === '') continue;
        if (count($answers) >= count($servedIds)) break;
        $answers[$qid] = is_scalar($raw) ? (string)$raw : null;
    }

    // Own the receipt before creating anything. submitAttempt() opens its own
    // transaction, so the two cannot be wrapped together - the claim is what
    // makes one sitting land once instead of once per posting.
    $claim = offline_claim($pdo, $ref, $userId, $quizId);
    if ($claim['state'] === 'conflict') {
        api_err('ref_conflict', 'That saved result belongs to another account.', 403);
    }
    if ($claim['state'] === 'done') {
        api_out(['ok' => true, 'already_synced' => true, 'receipt' => offline_receipt($pdo, (int)$claim['attempt_id'], $userId)]);
    }
    if ($claim['state'] !== 'mine') {
        api_err('sync_in_progress', 'This saved result is being recorded right now. It stays on this phone - please try again in a minute.', 503);
    }

    $attemptModel = new QuizAttempt($pdo);
    try {
        $attemptId = $attemptModel->submitAttempt($quizId, $userId, $answers, $elapsed, [$quizId], $servedIds);
    } catch (Throwable $e) {
        offline_release_claim($pdo, $ref, $userId);
        log_error('api/v1 offline sync failed', ['quiz_id' => $quizId, 'user_id' => $userId, 'err' => $e->getMessage()]);
        api_err('sync_failed', 'Could not record your saved result. Try again.', 500);
    }

    $mark = $pdo->prepare('UPDATE offline_attempts SET attempt_id = ?, synced_at = NOW() WHERE client_ref = ? AND attempt_id = 0');
    $mark->execute([$attemptId, $ref]);
    if ($mark->rowCount() !== 1) {
        // The attempt exists and is the student's; only the receipt bookkeeping
        // was lost, so say so loudly rather than pretending the sit never was.
        log_error('api/v1 offline receipt not marked', ['quiz_id' => $quizId, 'user_id' => $userId, 'attempt_id' => $attemptId, 'client_ref' => $ref]);
    }

    api_out([
        'ok' => true,
        'already_synced' => false,
        'client_ref' => $ref,
        'receipt' => offline_receipt($pdo, $attemptId, $userId),
    ]);
}

api_err('bad_request', 'Ask for a quiz to save, or post a saved result to sync.', 400);
