<?php
/**
 * Sitting an exam from the app.
 *
 * Four calls, in the order a student meets them:
 *
 *   GET  ?id=12            the card the "Ready to start?" modal shows
 *   POST action=start      the gates, then the question paper (never the answers)
 *   POST action=submit     scored through the web's own QuizAttempt model
 *   GET  ?attempt_id=9     the result page: marks, corrections, certificate, advice
 *
 * Scoring, XP, badges, streaks and notifications all live in
 * QuizAttempt::submitAttempt(), which is what the website posts to as well. This
 * file adds no rules of its own - only the same access gates quiz.php applies,
 * so a quiz a student cannot open in a browser cannot be opened here either.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_tutorial.php';
require_once __DIR__ . '/../../models/Quiz.php';
require_once __DIR__ . '/../../models/QuizAttempt.php';
require_once __DIR__ . '/../../models/Gamification.php';
require_once __DIR__ . '/../../models/Payment.php';
require_once __DIR__ . '/../../models/AccessCode.php';
require_once __DIR__ . '/../../models/Material.php';
require_once __DIR__ . '/../../models/Tutorial.php';
require_once __DIR__ . '/_challenge.php';
require_once __DIR__ . '/_paper.php';
require_once __DIR__ . '/_gates.php';

$user = api_auth();
$userId = (int)$user['id'];
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$in = $isPost ? api_input() : [];
$action = $isPost ? api_field($in, 'action') : '';

$quizModel = new Quiz($pdo);
$attemptModel = new QuizAttempt($pdo);

// The gates themselves live in _gates.php, shared with offline.php, and the
// words a paper travels in live in _paper.php, shared with battle_play.php.
function quiz_start_key(int $quizId): string
{
    return 'quiz_start_' . $quizId;
}

/**
 * What one set pays out, in the same words the challenge card and the claim
 * page use. The exam screen shows it because a challenge paper is worth
 * something a practice set is not, and the figure must come from the row, not
 * from a number the phone remembers.
 */
function quiz_prize_block(array $quiz): array
{
    $pool = (float)($quiz['prize_amount'] ?? 0);
    $type = (string)($quiz['prize_type'] ?? '');
    $text = trim((string)($quiz['prize_text'] ?? ''));
    return [
        'prize_amount' => $pool,
        'prize_type' => $type !== '' ? $type : null,
        'prize_text' => $text !== '' ? $text : null,
        'prize_label' => $pool > 0 || $text !== ''
            ? format_prize($pool, $type !== '' ? $type : 'money') ?: $text
            : null,
        'prize_shares' => $pool > 0 ? [
            ['rank' => 1, 'amount' => Quiz::prizeShare($pool, 1)],
            ['rank' => 2, 'amount' => Quiz::prizeShare($pool, 2)],
            ['rank' => 3, 'amount' => Quiz::prizeShare($pool, 3)],
        ] : [],
    ];
}

// ─────────────────────────────────────────────────────────── the paper ──────
if ($isPost && $action === 'start') {
    $quizId = (int)api_field($in, 'quiz_id');
    $quiz = $quizId > 0 ? $quizModel->getById($quizId) : null;
    if (!$quiz) api_err('not_found', 'That quiz does not exist.', 404);

    $gate = quiz_gate($pdo, $quiz, $user);
    if ($gate[0] === 'no') {
        api_err($gate[1], $gate[2], $gate[1] === 'paywall' ? 402 : 403, ['quiz_id' => $quizId]);
    }
    $cgate = quiz_challenge_gate($pdo, $quiz, $userId);
    if ($cgate[0] === 'no') {
        api_err($cgate[1], $cgate[2], 403, ['quiz_id' => $quizId]);
    }

    // A paid or challenge set is sat once. The web bounces that case to
    // quiz_special.php; the app gets the reason and shows the result instead.
    $isPaid = !empty($quiz['is_paid']);
    $isChallenge = ($quiz['mode'] ?? 'normal') === 'challenge';
    if (($isPaid || $isChallenge) && $attemptModel->hasAttempted($quizId, $userId)) {
        api_err('already_taken', 'You have already sat this quiz. Its result is in My Quizzes.', 409);
    }

    $duration = (int)($quiz['duration_seconds'] ?? 900);
    $key = quiz_start_key($quizId);
    $now = time();

    // Resumed rather than restarted: quitting the app mid-exam used to hand the
    // student the full clock again.
    $stmt = $pdo->prepare('SELECT meta_value FROM user_meta WHERE user_id = ? AND meta_key = ? LIMIT 1');
    $stmt->execute([$userId, $key]);
    $started = (int)$stmt->fetchColumn();
    if ($started > 0) {
        $remaining = max(0, $duration - ($now - $started));
    } else {
        $pdo->prepare('INSERT INTO user_meta (user_id, meta_key, meta_value) VALUES (?, ?, ?)')
            ->execute([$userId, $key, (string)$now]);
        $remaining = $duration;
    }

    $questions = $quizModel->getQuestions($quizId);
    if (!$questions) api_err('empty_quiz', 'This quiz has no questions yet.', 404);

    // A clock that has run out is not a quiz; the student is sent to the result
    // they already have rather than a paper they cannot finish.
    if ($remaining <= 0) {
        $pdo->prepare('DELETE FROM user_meta WHERE user_id = ? AND meta_key = ?')->execute([$userId, $key]);
        api_err('time_up', 'The time for this quiz ran out on the last attempt.', 409);
    }

    api_out([
        'ok' => true,
        'quiz' => [
            'id' => $quizId,
            'title' => (string)$quiz['title'],
            'subject' => (string)($quiz['subject'] ?? ''),
            'level' => (string)($quiz['level'] ?? ''),
            'tags' => quiz_tags_of($quiz),
            'total_phases' => (int)$quiz['total_phases'],
            'current_phase' => (int)$quiz['current_phase'],
        ],
        'remaining' => $remaining,
        'duration' => $duration,
        'questions' => quiz_paper($questions),
    ]);
}

// ───────────────────────────────────────────────────────── submit the paper ──
if ($isPost && $action === 'submit') {
    $quizId = (int)api_field($in, 'quiz_id');
    $quiz = $quizId > 0 ? $quizModel->getById($quizId) : null;
    if (!$quiz) api_err('not_found', 'That quiz does not exist.', 404);

    $gate = quiz_gate($pdo, $quiz, $user);
    if ($gate[0] === 'no') api_err($gate[1], $gate[2], 403);

    $duration = (int)($quiz['duration_seconds'] ?? 900);
    $key = quiz_start_key($quizId);
    $now = time();

    // The clock is the server's, not the phone's: elapsed is measured from the
    // moment start was answered, and a long submission is clamped to the paper
    // length plus the same 5 second grace the web allows.
    $stmt = $pdo->prepare('SELECT meta_value FROM user_meta WHERE user_id = ? AND meta_key = ? LIMIT 1');
    $stmt->execute([$userId, $key]);
    $started = (int)$stmt->fetchColumn();
    $durationUsed = $duration;
    if ($started > 0) {
        $elapsed = max(1, $now - $started);
        $durationUsed = $elapsed <= $duration + 5 ? $elapsed : $duration;
    }
    $pdo->prepare('DELETE FROM user_meta WHERE user_id = ? AND meta_key = ?')->execute([$userId, $key]);

    $questions = $quizModel->getQuestions($quizId);
    if (!$questions) api_err('empty_quiz', 'This quiz has no questions yet.', 404);

    $posted = isset($in['answers']) && is_array($in['answers']) ? $in['answers'] : [];
    $answers = [];
    foreach ($questions as $q) {
        $qid = (int)$q['id'];
        if (!array_key_exists($qid, $posted) && !array_key_exists((string)$qid, $posted)) continue;
        $raw = $posted[$qid] ?? $posted[(string)$qid] ?? null;
        if ($raw === null || $raw === '') continue;
        $answers[$qid] = is_scalar($raw) ? (string)$raw : null;
    }

    $allowed = [$quizId];
    $presented = array_map('intval', array_column($questions, 'id'));
    try {
        $attemptId = $attemptModel->submitAttempt($quizId, $userId, $answers, $durationUsed, $allowed, $presented);
    } catch (Throwable $e) {
        log_error('api/v1 quiz submit failed', ['quiz_id' => $quizId, 'user_id' => $userId, 'err' => $e->getMessage()]);
        api_err('submit_failed', 'Could not record your answers. Try again.', 500);
    }

    api_out(['ok' => true, 'attempt_id' => $attemptId] + quiz_result_payload($pdo, $attemptModel, $quizModel, $attemptId, $userId)['summary']);
}

// ───────────────────────────────────────────────────────────── the result ────
if (!$isPost && (int)($_GET['attempt_id'] ?? 0) > 0) {
    $attemptId = (int)$_GET['attempt_id'];
    api_out(quiz_result_payload($pdo, $attemptModel, $quizModel, $attemptId, $userId));
}

// ─────────────────────────────────────────────────── the card before the exam ─
$quizId = (int)($_GET['id'] ?? 0);
$quiz = $quizId > 0 ? $quizModel->getById($quizId) : null;
if (!$quiz) api_err('not_found', 'That quiz does not exist.', 404);

$gate = quiz_gate($pdo, $quiz, $user);
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ? AND user_id = ?');
$countStmt->execute([$quizId, $userId]);
$attempts = (int)$countStmt->fetchColumn();

// The card promises exactly what start would allow, so the Ready modal never
// offers a Start button that answers 403. Same order as the start branch.
$isChallenge = ($quiz['mode'] ?? 'normal') === 'challenge';
if ($gate[0] === 'yes') {
    $cgate = quiz_challenge_gate($pdo, $quiz, $userId);
    if ($cgate[0] === 'no') {
        $gate = $cgate;
    }
}
if ($gate[0] === 'yes' && ($isChallenge || !empty($quiz['is_paid'])) && $attempts > 0) {
    $gate = ['no', 'already_taken', 'You have already sat this quiz. Its result is in My Quizzes.'];
}

// The study pack attached to this set - the same gated links the challenge page
// uses, so the Ready modal can say "questions come from these files".
$materials = api_home_challenge_materials($pdo, [$quizId])[$quizId] ?? [];

api_out([
    'ok' => true,
    'quiz' => [
        'id' => $quizId,
        'title' => (string)$quiz['title'],
        'subject' => (string)($quiz['subject'] ?? ''),
        'level' => (string)($quiz['level'] ?? ''),
        'tags' => quiz_tags_of($quiz),
        'description' => (string)($quiz['description'] ?? ''),
        'question_count' => $quizModel->getQuestionCount($quizId),
        'minutes' => quiz_minutes_of($quiz),
        'duration_seconds' => (int)($quiz['duration_seconds'] ?? 900),
        'is_paid' => !empty($quiz['is_paid']),
        'entry_fee' => !empty($quiz['is_paid']) ? (float)$quiz['entry_fee'] : null,
        'mode' => (string)($quiz['mode'] ?? 'normal'),
        'is_challenge' => $isChallenge,
        'total_phases' => (int)$quiz['total_phases'],
        'current_phase' => (int)$quiz['current_phase'],
        'event_start' => $quiz['event_start'] ?? null,
        'event_end' => $quiz['event_end'] ?? null,
        'attempts' => $attempts,
        'materials' => $materials,
        'can_start' => $gate[0] === 'yes',
        'blocked_reason' => $gate[0] === 'yes' ? null : $gate[2],
        'blocked_code' => $gate[0] === 'yes' ? null : $gate[1],
    ] + quiz_prize_block($quiz),
]);

/**
 * Everything the result page shows, built in one place so submitting and
 * opening the page later hand back the same shape.
 */
function quiz_result_payload(PDO $pdo, QuizAttempt $attemptModel, Quiz $quizModel, int $attemptId, int $userId): array
{
    $attempt = $attemptModel->getAttempt($attemptId, $userId);
    if (!$attempt) api_err('not_found', 'That result is not yours, or it has gone.', 404);

    $quiz = $quizModel->getById((int)$attempt['quiz_id']);
    $answers = $attemptModel->getAnswersDetailed($attemptId);
    $score = (int)$attempt['score'];
    $total = (int)$attempt['total'];
    $percent = $total > 0 ? (int)round($score / $total * 100) : 0;

    $isChallenge = (($quiz['mode'] ?? 'normal') === 'challenge');
    $isPaid = !empty($quiz['is_paid']);

    // The web hides corrections while a challenge is still open, so that
    // answers cannot be passed to people still sitting it. Same here.
    $locked = false;
    if ($isChallenge && !empty($quiz['event_end'])) {
        $locked = time() <= strtotime((string)$quiz['event_end']);
    }

    $answered = 0;
    $subjectStats = [];
    foreach ($answers as $a) {
        $given = trim((string)($a['text_answer'] ?? '')) !== '' || !empty($a['selected_option_id']);
        if ($given) $answered++;
        $subj = (string)($a['subject'] ?? '');
        if ($subj === '') continue;
        if (!isset($subjectStats[$subj])) $subjectStats[$subj] = ['total' => 0, 'correct' => 0];
        $subjectStats[$subj]['total']++;
        if ((int)$a['is_correct'] === 1) $subjectStats[$subj]['correct']++;
    }
    foreach ($subjectStats as $s => $v) {
        $subjectStats[$s]['percent'] = $v['total'] > 0 ? (int)round($v['correct'] / $v['total'] * 100) : 0;
    }
    uasort($subjectStats, static fn($a, $b) => $b['percent'] <=> $a['percent']);

    // The options as they were written, now that the paper is in.
    $review = [];
    $qIds = array_values(array_unique(array_map(static fn($a) => (int)$a['question_id'], $answers)));
    $optionMap = [];
    $questionImages = [];
    if ($qIds && !$locked) {
        $in = implode(',', array_fill(0, count($qIds), '?'));
        $stmt = $pdo->prepare("SELECT id, question_id, option_text, is_correct, image_path FROM quiz_options
                                WHERE question_id IN ($in) ORDER BY id ASC");
        $stmt->execute($qIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $o) {
            $optionMap[(int)$o['question_id']][] = [
                'id' => (int)$o['id'],
                'text' => (string)$o['option_text'],
                'image' => api_url($o['image_path']),
                'correct' => (int)$o['is_correct'] === 1,
            ];
        }
        // A question that is really a diagram is useless in the corrections
        // list without the diagram, so the picture travels with the review too.
        $imgStmt = $pdo->prepare("SELECT id, image_path FROM quiz_questions WHERE id IN ($in)");
        $imgStmt->execute($qIds);
        foreach ($imgStmt->fetchAll(PDO::FETCH_ASSOC) as $qi) {
            $url = api_url($qi['image_path']);
            if ($url !== null) { $questionImages[(int)$qi['id']] = $url; }
        }
    }
    foreach ($answers as $i => $a) {
        $qid = (int)$a['question_id'];
        $review[] = [
            'number' => $i + 1,
            'question_id' => $qid,
            'question' => (string)($a['question'] ?? ''),
            'image' => $questionImages[$qid] ?? null,
            'subject' => (string)($a['subject'] ?? ''),
            'correct' => (int)$a['is_correct'] === 1,
            'your_answer' => (string)($a['selected_text'] ?? $a['text_answer'] ?? ''),
            'right_answer' => (string)($a['correct_text'] ?? $a['expected_answer'] ?? ''),
            'explanation' => $locked ? '' : (string)($a['explanation'] ?? ''),
            'options' => $optionMap[$qid] ?? [],
            'chosen_option_id' => (int)($a['selected_option_id'] ?? 0),
        ];
    }

    $rank = null;
    if (($isChallenge || $isPaid) && !$locked) {
        $board = $attemptModel->topScores((int)$attempt['quiz_id'], null, 500);
        foreach ($board as $i => $row) {
            if ((int)$row['user_id'] === $userId) {
                $rank = ['position' => $i + 1, 'of' => count($board)];
                break;
            }
        }
    }

    $materials = [];
    $tutorials = [];
    $keyword = trim((string)($quiz['subject'] ?? ''));
    if ($keyword !== '') {
        try {
            foreach ((new Material($pdo))->searchMaterials($keyword, null, null, null, null, 3, 0, 'approved') as $m) {
                $materials[] = [
                    'id' => (int)$m['id'],
                    'title' => (string)$m['title'],
                    'subject' => (string)($m['subject'] ?? ''),
                    'level' => (string)($m['level'] ?? ''),
                    'is_paid' => !empty($m['price']) && (float)$m['price'] > 0,
                ];
            }
        } catch (Throwable $e) { /* advice is optional, the mark is not */ }
        try {
            $tutorials = array_map(function ($t) {
                return api_tutorial_payload($t);
            }, (new Tutorial($pdo))->searchTutorials($keyword, null, null, 3, 0));
        } catch (Throwable $e) { /* same */ }
    }

    // certificate.php is the only issuer, and it draws a printed page - the app
    // opens that page rather than inventing a second certificate.
    $certUrl = SITE_URL . '/certificate.php?attempt_id=' . $attemptId;

    return [
        'ok' => true,
        'summary' => [
            'attempt_id' => $attemptId,
            'quiz_id' => (int)$attempt['quiz_id'],
            'title' => (string)($quiz['title'] ?? ''),
            'subject' => (string)($quiz['subject'] ?? ''),
            'level' => (string)($quiz['level'] ?? ''),
            'tags' => quiz_tags_of($quiz ?: []),
            'score' => $score,
            'total' => $total,
            'percent' => $percent,
            'correct' => $score,
            'incorrect' => $answered - $score,
            'unanswered' => max(0, $total - $answered),
            'duration_used' => (int)$attempt['duration_used'],
            'completed_at' => (string)$attempt['completed_at'],
            'pass_mark' => 50,
            'passed' => $percent >= 50,
            'certificate' => $percent >= 50,
            'certificate_url' => $certUrl,
            'is_challenge' => $isChallenge,
            'corrections_locked' => $locked,
            'mode' => (string)($quiz['mode'] ?? 'normal'),
            'can_retake' => !$isPaid && !$isChallenge,
        ],
        'subject_stats' => $subjectStats,
        'rank' => $rank,
        // While a challenge is live the whole review stays server-side: even
        // "your answer / right answer" pairs would hand the key to anyone still
        // sitting it. The web hides the section; the API must not ship it.
        'review' => $locked ? [] : $review,
        'materials' => $materials,
        'tutorials' => $tutorials,
    ];
}
