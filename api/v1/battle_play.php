<?php
/**
 * The battle arena for the app - the same engine quiz_battle_play.php,
 * quiz_battle_submit.php and quiz_battle_result.php run in a browser.
 *
 *   GET  ?battle_id=7      the row: whose turn it is, the score board, the
 *                          rewards and everything the shareable card needs
 *   POST action=start      the gates, then the question paper
 *   POST action=submit     scored by the web's own QuizAttempt model, then
 *                          handed to Battle::recordAttempt() exactly as
 *                          quiz_battle_submit.php does
 *
 * Two things here cannot be left to the phone. The question and option order is
 * drawn from the battle id, so both fighters get the identical paper - that is
 * why the shuffle runs on the server. And the clock is the server's: it starts
 * the first time a turn is opened and is read back on every later call, so
 * closing the app cannot hand a student a fresh timer.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_paper.php';
require_once __DIR__ . '/../../models/Quiz.php';
require_once __DIR__ . '/../../models/QuizAttempt.php';
require_once __DIR__ . '/../../models/Battle.php';
require_once __DIR__ . '/../../models/Gamification.php';
require_once __DIR__ . '/../../includes/quiz_sessions.php';

$user = api_auth();
$userId = (int)$user['id'];
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$in = $isPost ? api_input() : [];
$action = $isPost ? api_field($in, 'action') : '';

$battleModel = new Battle($pdo);
$quizModel = new Quiz($pdo);
$attemptModel = new QuizAttempt($pdo);

$battleId = $isPost
    ? (int)(api_field($in, 'battle_id', '') ?: api_field($in, 'id', ''))
    : (int)($_GET['battle_id'] ?? 0);

if ($battleId <= 0) {
    api_err('invalid_battle', 'Nothing to open - that battle was not named.', 422);
}

$battle = $battleModel->getBattle($battleId);
if (!$battle) {
    api_err('not_found', 'That battle does not exist any more.', 404);
}

$isChallenger = (int)$battle['challenger_id'] === $userId;
$isOpponent = (int)($battle['opponent_id'] ?? 0) === $userId;
if (!$isChallenger && !$isOpponent) {
    api_err('not_your_battle', 'You are not one of the two fighters in this battle.', 403);
}

$myScore = $isChallenger ? $battle['challenger_score'] : $battle['opponent_score'];
$hasPlayed = $myScore !== null;

// ───────────────────────────────────────────────────────── the paper ────────
if ($isPost && $action === 'start') {
    // quiz_battle.php refuses an arena that is not open, then refuses a student
    // who has already struck, and only then looks at the schedule.
    if ($battle['status'] === 'open') {
        api_err('invite_not_joined', 'This is the invite link itself. Share it, and a real battle opens when a classmate takes the seat.', 409, ['battle_id' => $battleId]);
    }
    if (in_array($battle['status'], ['pending', 'declined', 'completed', 'negotiating'], true)) {
        api_err('not_active', 'This battle is not currently active.', 409, ['battle_id' => $battleId]);
    }
    if ($hasPlayed) {
        api_err('already_played', 'You have already completed your turn.', 409, [
            'battle_id' => $battleId,
            'result' => battle_result($battle, $userId, [
                'is_challenger' => $isChallenger,
                'has_played' => true,
            ], $pdo)['result'],
        ]);
    }
    if (!empty($battle['start_time']) && strtotime((string)$battle['start_time']) > time()) {
        $label = date('M j, Y g:i A', strtotime((string)$battle['start_time']));
        api_err('not_started', "This battle is strictly scheduled to begin on {$label}. Please wait!", 409, ['battle_id' => $battleId]);
    }

    $quiz = $quizModel->getById((int)$battle['quiz_id']);
    if (!$quiz) {
        api_err('quiz_missing', 'The quiz this battle was built on is gone.', 404);
    }

    $questions = battle_paper_questions($pdo, $quizModel, (int)$battle['quiz_id'], $battleId);
    if (!$questions) {
        api_err('empty_quiz', 'This battle quiz has no questions yet.', 404);
    }

    $duration = battle_duration($quiz, $battle);
    $key = battle_clock_key($battleId, $userId);
    $now = time();

    $started = battle_clock_read($pdo, $userId, $key);
    if ($started > 0) {
        $remaining = max(0, $duration - ($now - $started));
    } else {
        battle_clock_write($pdo, $userId, $key, $now);
        $remaining = $duration;
    }

    // A clock that ran out while the app was shut is not a closed door. On the
    // website the sitting's clock lives in the PHP session, so a student who
    // comes back after their session has gone is handed the paper again with the
    // full allowance; this is the same thing, and refusing here would strand the
    // duel forever with one fighter locked out and no turn ever recorded.
    if ($remaining <= 0) {
        battle_clock_clear($pdo, $userId, $key);
        battle_clock_write($pdo, $userId, $key, $now);
        $remaining = $duration;
    }

    // The duel is under way: no Mentor for this student until the turn is in.
    quiz_session_open($pdo, $userId, 'battle', $battleId);

    api_out([
        'ok' => true,
        'battle' => battle_brief($battle, $userId, $isChallenger),
        'quiz' => [
            'id' => (int)$quiz['id'],
            'title' => (string)$quiz['title'],
            'subject' => (string)($quiz['subject'] ?? ''),
        ],
        'remaining' => $remaining,
        'duration' => $duration,
        'total_questions' => count($questions),
        'questions' => quiz_paper($questions),
    ]);
}

// ────────────────────────────────────────────────────── submit the turn ─────
if ($isPost && $action === 'submit') {
    if ($battle['status'] === 'open') {
        api_err('invite_not_joined', 'That invite has not become a battle yet.', 409, ['battle_id' => $battleId]);
    }
    // quiz_battle_submit.php sends a student who has already struck, or whose
    // arena is closed, straight to the result page instead of scoring twice.
    if ($hasPlayed || !in_array($battle['status'], ['p1_turn', 'p2_turn'], true)) {
        api_out(['ok' => true, 'already' => true] + battle_result($battle, $userId, [
            'is_challenger' => $isChallenger,
            'has_played' => $hasPlayed,
        ], $pdo));
    }

    // The quiz is read from the battle row, never from the phone.
    $quizId = (int)$battle['quiz_id'];
    $key = battle_clock_key($battleId, $userId);
    $started = battle_clock_read($pdo, $userId, $key);
    $durationSeconds = $started > 0 ? max(0, time() - $started) : 0;
    battle_clock_clear($pdo, $userId, $key);

    $questions = $quizModel->getQuestions($quizId);
    if (!$questions) {
        api_err('empty_quiz', 'This battle quiz has no questions yet.', 404);
    }

    $posted = isset($in['answers']) && is_array($in['answers']) ? $in['answers'] : [];
    $answers = [];
    foreach ($questions as $q) {
        $qid = (int)$q['id'];
        if (!array_key_exists($qid, $posted) && !array_key_exists((string)$qid, $posted)) continue;
        $raw = $posted[$qid] ?? $posted[(string)$qid] ?? null;
        if ($raw === null || $raw === '') continue;
        $answers[$qid] = is_scalar($raw) ? (string)$raw : null;
    }

    try {
        // The same call the web makes, with the same defaults: the whole quiz is
        // the scope, and the base practice XP is still paid out.
        $attemptId = $attemptModel->submitAttempt($quizId, $userId, $answers, $durationSeconds);
    } catch (Throwable $e) {
        log_error('api/v1 battle submit failed', ['battle_id' => $battleId, 'user_id' => $userId, 'err' => $e->getMessage()]);
        api_err('submit_failed', 'Could not record your turn. Try again.', 500);
    }

    // The turn is in: the student is out of the duel and Mentor is theirs again.
    quiz_session_close($pdo, $userId, 'battle', $battleId);

    $attempt = $attemptModel->getAttempt($attemptId, $userId);
    $score = $attempt ? (int)$attempt['score'] : 0;

    if (!$battleModel->recordAttempt($battleId, $userId, $score, $durationSeconds)) {
        api_err('turn_not_recorded', 'Your turn could not be recorded in this battle.', 409);
    }

    // Re-read the row: recordAttempt may have closed the battle and settled the
    // pot, and the result page must show what actually happened.
    $fresh = $battleModel->getBattle($battleId) ?: $battle;
    api_out(['ok' => true] + battle_result($fresh, $userId, [
        'is_challenger' => (int)$fresh['challenger_id'] === $userId,
        'has_played' => true,
        'duration_used' => $durationSeconds,
        'attempt_id' => (int)$attemptId,
    ], $pdo));
}

// ─────────────────────────────────────────────────────── the row itself ─────
if ($isPost) {
    api_err('unknown_action', 'That battle action is not recognised.', 422);
}

api_out([
    'ok' => true,
    'battle' => battle_brief($battle, $userId, $isChallenger),
    'can_play' => $battle['status'] !== 'open'
        && in_array($battle['status'], ['p1_turn', 'p2_turn'], true)
        && !$hasPlayed
        && (empty($battle['start_time']) || strtotime((string)$battle['start_time']) <= time()),
    'blocked' => battle_blocked($battle, $hasPlayed),
    // A student who has not struck yet has no result page to look at; the arena
    // sends them to the turn instead, which is what the web's badge machine does.
    'result' => $hasPlayed ? battle_result($battle, $userId, [
        'is_challenger' => $isChallenger,
        'has_played' => true,
    ], $pdo)['result'] : null,
]);

/**
 * ──────────────────────────────────────────────────────────────────────────
 * The engine, shared by all three calls.
 */

/** The same battle quiz with the same order both fighters must see. */
function battle_paper_questions(PDO $pdo, Quiz $quizModel, int $quizId, int $battleId): array
{
    $questions = $quizModel->getQuestions($quizId);

    // Deterministic shuffle: both players in the battle must see the exact same
    // randomized order, because the same paper is what makes a duel fair.
    $seed = crc32('battle_engine_' . $battleId);
    mt_srand($seed);

    for ($i = count($questions) - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$questions[$i], $questions[$j]] = [$questions[$j], $questions[$i]];
    }

    foreach ($questions as &$q) {
        if (!empty($q['options']) && count($q['options']) > 1) {
            $opts = $q['options'];
            for ($i = count($opts) - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                [$opts[$i], $opts[$j]] = [$opts[$j], $opts[$i]];
            }
            $q['options'] = array_values($opts);
        }
    }
    unset($q);

    mt_srand(); // Reset the PRNG, exactly as the web does when the paper is drawn.
    return array_values($questions);
}

/** The quiz clock, unless the battle was given its own timer. */
function battle_duration(array $quiz, array $battle): int
{
    $duration = (int)($quiz['duration_seconds'] ?? 900);
    if (!empty($battle['custom_timer_seconds'])) {
        return (int)$battle['custom_timer_seconds'];
    }
    return $duration;
}

/** Why the arena will not open, in the website's own words, or null. */
function battle_blocked(array $battle, bool $hasPlayed): ?array
{
    if ($hasPlayed) {
        return ['code' => 'already_played', 'message' => 'You have already completed your turn.'];
    }
    if ($battle['status'] === 'open') {
        return ['code' => 'invite_not_joined', 'message' => 'This is the invite link itself. Share it, and a real battle opens when a classmate takes the seat.'];
    }
    if (!empty($battle['start_time']) && strtotime((string)$battle['start_time']) > time()) {
        return ['code' => 'not_started', 'message' => 'This battle is strictly scheduled to begin on '
            . date('M j, Y g:i A', strtotime((string)$battle['start_time'])) . '. Please wait!'];
    }
    if (!in_array($battle['status'], ['p1_turn', 'p2_turn'], true)) {
        return ['code' => 'not_active', 'message' => 'This battle is not currently active.'];
    }
    return null;
}

/** The head-to-head facts a phone needs to paint the VS header. */
function battle_brief(array $battle, int $userId, bool $isChallenger): array
{
    $oppId = $isChallenger ? (int)($battle['opponent_id'] ?? 0) : (int)$battle['challenger_id'];
    return [
        'id' => (int)$battle['id'],
        'quiz_id' => (int)$battle['quiz_id'],
        'quiz_title' => (string)($battle['quiz_title'] ?? ''),
        'subject' => (string)($battle['quiz_subject'] ?? 'General'),
        'status' => (string)$battle['status'],
        'match_type' => (string)($battle['match_type'] ?? 'friendly'),
        'xp_wager' => (int)($battle['xp_wager'] ?? 0),
        'timer_seconds' => !empty($battle['custom_timer_seconds']) ? (int)$battle['custom_timer_seconds'] : null,
        'i_am' => $isChallenger ? 'challenger' : 'opponent',
        'start_ts' => !empty($battle['start_time']) ? strtotime((string)$battle['start_time']) : null,
        'invite_code' => $battle['invite_code'] ?? null,
        'opponent_taken' => $oppId > 0,
        'other' => [
            'id' => $oppId,
            'name' => $isChallenger ? (string)($battle['opponent_name'] ?? 'Them') : (string)$battle['challenger_name'],
            'picture' => api_url($isChallenger ? ($battle['opponent_avatar'] ?? null) : ($battle['challenger_avatar'] ?? null)),
        ],
    ];
}

function battle_clock_key(int $battleId, int $userId): string
{
    return 'battle_start_' . $battleId . '_user_' . $userId;
}

function battle_clock_read(PDO $pdo, int $userId, string $key): int
{
    $stmt = $pdo->prepare('SELECT meta_value FROM user_meta WHERE user_id = ? AND meta_key = ? LIMIT 1');
    $stmt->execute([$userId, $key]);
    return (int)$stmt->fetchColumn();
}

function battle_clock_write(PDO $pdo, int $userId, string $key, int $when): void
{
    $pdo->prepare('INSERT INTO user_meta (user_id, meta_key, meta_value) VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)')
        ->execute([$userId, $key, (string)$when]);
}

function battle_clock_clear(PDO $pdo, int $userId, string $key): void
{
    $pdo->prepare('DELETE FROM user_meta WHERE user_id = ? AND meta_key = ?')->execute([$userId, $key]);
}

/**
 * The result page: the same four states quiz_battle_result.php shows, the same
 * score board, the same rewards panel and the same shareable card data.
 */
function battle_result(array $battle, int $userId, array $flags, ?PDO $pdo = null): array
{
    $battleId = (int)$battle['id'];
    $isChallenger = !empty($flags['is_challenger']);
    $myScore = $isChallenger ? $battle['challenger_score'] : $battle['opponent_score'];
    $myTime = $isChallenger ? $battle['challenger_time_seconds'] : $battle['opponent_time_seconds'];
    $oppScore = $isChallenger ? $battle['opponent_score'] : $battle['challenger_score'];
    $oppTime = $isChallenger ? $battle['opponent_time_seconds'] : $battle['challenger_time_seconds'];

    $status = (string)$battle['status'];
    $wager = (int)($battle['xp_wager'] ?? 0);
    $ranked = ($battle['match_type'] ?? 'friendly') === 'ranked';

    $isDone = $status === 'completed' && !empty($battle['opponent_id']);
    $isWinner = $isDone && (int)($battle['winner_id'] ?? 0) === $userId;
    $isDraw = $isDone && empty($battle['winner_id']);
    $isLoser = $isDone && !$isWinner && !$isDraw;
    $outcome = !$isDone ? 'pending' : ($isWinner ? 'victory' : ($isDraw ? 'draw' : 'defeat'));

    $oppId = $isChallenger ? (int)($battle['opponent_id'] ?? 0) : (int)$battle['challenger_id'];
    $oppName = $isChallenger ? (string)($battle['opponent_name'] ?? 'Them') : (string)$battle['challenger_name'];

    $headline = [
        'pending' => ['title' => 'Pending', 'text' => "You have completed your turn. Waiting for {$oppName} to strike!"],
        'victory' => ['title' => 'Victory', 'text' => 'Exceptional performance. You dominated the arena!'],
        'draw' => ['title' => 'A Draw', 'text' => 'A perfectly matched battle. Well fought on both sides.'],
        'defeat' => ['title' => 'Defeat', 'text' => 'You fought bravely, but your opponent was too strong this time.'],
    ][$outcome];

    // ELO and XP, spelled out for each seat rather than only the viewer's. The
    // website's panel speaks about you; a phone puts both fighters side by side,
    // so each one's own movement has to be stated - and the rival's is always the
    // mirror of yours, never a second guess. These two figures are the site's own
    // published movement, and the shareable card now reads from here too, so the
    // panel, the fighter rows and the saved image can never disagree.
    $eloWin = 30;
    $eloLose = 20;
    $move = static function (string $text, string $dir): array {
        return ['text' => $text, 'dir' => $dir];
    };
    $myElo = $theirElo = $myXp = $theirXp = null;
    if ($isDone) {
        if ($ranked) {
            // A win and a loss are not equal and opposite: +30 up, -20 down.
            $myElo = $isDraw
                ? $move('+0', 'flat')
                : ($isWinner ? $move("+{$eloWin}", 'up') : $move("-{$eloLose}", 'down'));
            $theirElo = $isDraw
                ? $move('+0', 'flat')
                : ($isWinner ? $move("-{$eloLose}", 'down') : $move("+{$eloWin}", 'up'));
        }
        if ($wager > 0) {
            // The pot is one number, so here the mirror is exact.
            $myXp = $isDraw
                ? $move('Returned', 'flat')
                : ($isWinner ? $move("+{$wager}", 'up') : $move("-{$wager}", 'down'));
            $theirXp = $isDraw
                ? $move('Returned', 'flat')
                : ($isWinner ? $move("-{$wager}", 'down') : $move("+{$wager}", 'up'));
        }
    }

    $ledger = [
        'ranked' => $ranked,
        'wager' => $wager,
        'my' => ['elo' => $myElo, 'xp' => $myXp],
        'theirs' => ['elo' => $theirElo, 'xp' => $theirXp],
    ];

    $note = null;
    if ($isDone && !$ranked) {
        $note = $wager > 0
            ? 'Friendly match. No ELO Rank changes occurred.'
            : 'Friendly match. No ELO Rank or XP changes occurred.';
    }

    $me = battle_side(battle_role($battle, $isChallenger ? 'challenger' : 'opponent'), $userId);
    $them = battle_side(battle_role($battle, $isChallenger ? 'opponent' : 'challenger'), $oppId);

    // The denominator both bars read against. Every battle paper is the whole
    // quiz, shuffled the same way for both seats, so one number is truthful for
    // both fighters - and it is the quiz's own question count, never a guess.
    $total = battle_question_count($pdo, (int)($battle['quiz_id'] ?? 0));

    return [
        'result' => [
            'battle_id' => $battleId,
            'quiz_title' => (string)($battle['quiz_title'] ?? ''),
            'subject' => (string)($battle['quiz_subject'] ?? 'General'),
            'status' => $status,
            'outcome' => $outcome,
            'headline_title' => $headline['title'],
            'headline_text' => $headline['text'],
            'match_type' => $ranked ? 'ranked' : 'friendly',
            'xp_wager' => $wager,
            'ledger' => $ledger,
            'note' => $note,
            'finished_at' => !empty($battle['completed_at']) ? strtotime((string)$battle['completed_at']) : null,
            'waiting' => $outcome === 'pending',
            'refresh_in' => $outcome === 'pending' ? 15 : null,
            'duration_used' => isset($flags['duration_used']) ? (int)$flags['duration_used'] : battle_seconds_of($myTime),
            'attempt_id' => isset($flags['attempt_id']) ? (int)$flags['attempt_id'] : null,
            'board' => [
                'me' => $me['score_label'],
                'them' => $them['score_label'],
                'my_time' => $me['time_label'],
                'their_time' => $them['time_label'],
                'total' => $total,
                'my_score' => $me['score'],
                'their_score' => $them['score'],
                'my_seconds' => $me['time_seconds'],
                'their_seconds' => $them['time_seconds'],
            ],
            'card' => battle_card($battle, $me, $them, $outcome, $ledger, $total),
        ],
    ];
}

/** One fighter's row, read out of the joined battle by side. */
function battle_role(array $battle, string $role): array
{
    $c = $role === 'challenger';
    return [
        'name' => $c ? ($battle['challenger_name'] ?? null) : ($battle['opponent_name'] ?? null),
        'avatar' => $c ? ($battle['challenger_avatar'] ?? null) : ($battle['opponent_avatar'] ?? null),
        'points' => (int)($c ? ($battle['challenger_points'] ?? 0) : ($battle['opponent_points'] ?? 0)),
        'type' => (string)($c ? ($battle['challenger_user_type'] ?? '') : ($battle['opponent_user_type'] ?? '')),
        'elo' => (int)($c ? ($battle['challenger_rating'] ?? 1000) : ($battle['opponent_rating'] ?? 1000)),
        'score' => $c ? ($battle['challenger_score'] ?? null) : ($battle['opponent_score'] ?? null),
        'time' => $c ? ($battle['challenger_time_seconds'] ?? null) : ($battle['opponent_time_seconds'] ?? null),
    ];
}

/** One fighter as the result page and the card both need them. */
function battle_side(array $row, int $id): array
{
    $name = (string)($row['name'] ?? '');
    $level = Gamification::getLevel((int)$row['points']);
    // The website's own rank name for the track this student signed up on.
    $rankName = $row['type'] === 'university'
        ? (Gamification::UNI_LEVEL_NAMES[$level] ?? "Level $level")
        : (Gamification::SEC_LEVEL_NAMES[$level] ?? Gamification::UNI_LEVEL_NAMES[$level] ?? "Level $level");

    $scoreSet = $row['score'] !== null;
    return [
        'id' => $id,
        'name' => $name !== '' ? $name : 'A student',
        'initials' => battle_initials($name),
        'picture' => api_url($row['avatar']),
        'score' => $scoreSet ? (int)$row['score'] : null,
        'score_label' => $scoreSet ? (string)(int)$row['score'] : '?',
        'time_seconds' => battle_seconds_of($row['time']),
        'time_label' => $row['time'] !== null ? gmdate('i:s', (int)$row['time']) : '--:--',
        'level' => $level,
        'rank_name' => $rankName,
        'elo' => (int)$row['elo'],
        'points' => (int)$row['points'],
    ];
}

function battle_seconds_of($value): int
{
    return $value === null ? 0 : (int)$value;
}

/** How many questions this quiz actually holds, or null when it cannot be said. */
function battle_question_count(?PDO $pdo, int $quizId): ?int
{
    if ($pdo === null || $quizId <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?');
        $stmt->execute([$quizId]);
        $n = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        log_error('api/v1 battle question count failed', ['quiz_id' => $quizId, 'err' => $e->getMessage()]);
        return null;
    }

    return $n > 0 ? $n : null;
}

function battle_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_strtoupper(mb_substr((string)($parts[0] ?? ''), 0, 1));
    $last = count($parts) > 1 ? mb_strtoupper(mb_substr((string)end($parts), 0, 1)) : '';
    return $first . $last;
}

/**
 * The 1080x1080 card the web draws with html2canvas, reduced to the words and
 * numbers it puts on it. The phone draws the picture; the server decides what
 * is true on it, so a battle result cannot be retouched on a device.
 */
function battle_card(array $battle, array $me, array $them, string $outcome, array $ledger, ?int $total): array
{
    $ranked = !empty($ledger['ranked']);
    $wager = (int)($ledger['wager'] ?? 0);
    $label = ['pending' => 'PENDING', 'victory' => 'WINNER', 'draw' => 'DRAW', 'defeat' => 'DEFEATED'][$outcome];
    $side = ['victory' => 'win', 'defeat' => 'lose'][$outcome] ?? 'draw';
    $otherSide = ['victory' => 'lose', 'defeat' => 'win'][$outcome] ?? 'draw';
    // The other seat holds the opposite verdict, and only the viewer's own state
    // says which one that is.
    $otherLabel = ['victory' => 'DEFEATED', 'defeat' => 'WINNER', 'draw' => 'DRAW', 'pending' => 'PENDING'][$outcome];

    return [
        'site_name' => strtoupper(SITE_NAME),
        'date_label' => date('M j, Y', !empty($battle['completed_at']) ? strtotime((string)$battle['completed_at']) : time()),
        'quiz_title' => (string)($battle['quiz_title'] ?? 'Battle Quiz'),
        'subject' => (string)($battle['quiz_subject'] ?? 'General'),
        'match_label' => $ranked ? 'RANKED MATCH' : 'FRIENDLY MATCH',
        'ranked' => $ranked,
        'wager' => $wager,
        'outcome' => $outcome,
        // The paper's size, so a score on the saved card reads as part of a whole.
        'total' => $total,
        'my' => $me + [
            'label' => $label,
            'side' => $side,
            'elo_move' => $ledger['my']['elo'],
            'xp_move' => $ledger['my']['xp'],
            'delta_text' => battle_move_text($ledger['my']['elo'], 'ELO'),
            'stake_text' => battle_move_text($ledger['my']['xp'], 'XP'),
            'elo_text' => $me['elo'] . ' ELO',
        ],
        'them' => $them + [
            'label' => $otherLabel,
            'side' => $otherSide,
            'elo_move' => $ledger['theirs']['elo'],
            'xp_move' => $ledger['theirs']['xp'],
            'delta_text' => battle_move_text($ledger['theirs']['elo'], 'ELO'),
            'stake_text' => battle_move_text($ledger['theirs']['xp'], 'XP'),
            'elo_text' => $them['elo'] . ' ELO',
        ],
        // Which figure is the gold one, exactly as the web colours the centre box.
        'my_score_leads' => (int)($me['score'] ?? 0) >= (int)($them['score'] ?? 0),
        'their_score_leads' => (int)($them['score'] ?? 0) >= (int)($me['score'] ?? 0),
    ];
}

/** A movement as the card prints it: "+30 ELO", "-150 XP", "XP returned", or nothing. */
function battle_move_text(?array $move, string $unit): ?string
{
    if ($move === null) {
        return null;
    }

    return $move['dir'] === 'flat' && $unit === 'XP'
        ? 'XP returned'
        : $move['text'] . ' ' . $unit;
}
