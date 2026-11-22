<?php
/**
 * Mentor - the assistant inside the app.
 *
 * Three ways it answers, in this order, because each is more certain than the
 * next and the app should never be more creative than it has to be:
 *
 *   1. YOUR OWN NUMBERS come out of the database. "How many points do I have"
 *      is a SELECT, not a guess, so the figure is today's, not one a model
 *      remembers from a week ago. These replies never touch the AI at all:
 *      they are exact, they cost nothing, and they come back instantly.
 *   2. THE KNOWLEDGE FILE (config/mentor_knowledge.php) is scored against the
 *      question. It was written from this codebase, so it cannot describe a
 *      button that does not exist.
 *   3. CLOUDFLARE WORKERS AI turns the two above into plain language and
 *      handles the questions no list could anticipate - a follow-up, a mixed
 *      "how am I doing and what should I fix", a study topic. The model is
 *      given the matched entries and the student's real figures as the only
 *      permitted source, which is what stops it inventing a settings page.
 *
 * If Cloudflare is not configured, or the day's allowance is spent, the reply
 * falls back to the knowledge entry word for word. Mentor is never the reason a
 * student gets a dead end: the answer just arrives with less padding.
 *
 * The Cloudflare token lives in config/secrets.local.php and never leaves this
 * server. The phone only ever talks to this file, so it cannot read the key,
 * and the quota below is what stops one account spending the whole day.
 */

require_once __DIR__ . '/_bootstrap.php';

$KNOWLEDGE = require __DIR__ . '/../../config/mentor_knowledge.php';

/** The model that runs on the free allowance. Swap it here if Cloudflare retires it. */
define('MENTOR_MODEL', '@cf/meta/llama-3.1-8b-instruct');
/** A day of chatting per student, and per site, before replies come from the knowledge file. */
define('MENTOR_USER_DAY', 15);
define('MENTOR_GLOBAL_DAY', 1000);

api_require_post();
$user = api_auth();
$userId = (int)$user['id'];

$in = api_input();
$question = trim(api_field($in, 'question'));

if ($question === '') {
    api_err('question_needed', 'Type what you want to ask Mentor.');
}
if (mb_strlen($question) > 500) {
    api_err('question_too_long', 'Ask in 500 characters or less so Mentor can read all of it.');
}

// The last few turns, so "and my streak?" after another question still makes
// sense. Only the model sees them; the number lookups below always re-read the
// database, so a remembered figure can never be served as a stale one.
$history = [];
foreach ((array)($in['history'] ?? []) as $turn) {
    if (!is_array($turn)) {
        continue;
    }
    $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $text = mb_substr(trim((string)($turn['content'] ?? '')), 0, 400);
    if ($text !== '') {
        $history[] = ['role' => $role, 'content' => $text];
    }
}
$history = array_slice($history, -6);

$facts = mentor_facts($pdo, $user);
$norm = mentor_norm($question);
$personal = mentor_is_personal($norm);

// Read once here because every reply has to carry it: the chat bar shows how
// many questions are left today, and that number is only honest if it comes
// back with the answer rather than in a second call the phone must make.
$quota = mentor_quota($pdo, $userId);
$aiAllowed = $quota['ai_available'];

// Fifteen questions a day, and the counter starts here rather than at the
// model, because the student's promise is about questions, not about which
// part of the server answered them. The spend is taken before the answer is
// composed so a slow or failed reply cannot be asked twice for free.
if ((int)$quota['left'] <= 0) {
    api_err('quota_used', 'You have used all ' . MENTOR_USER_DAY . ' questions for today. Mentor starts again at 12:00 am tomorrow.', 429, [
        'quota' => $quota,
    ]);
}
mentor_spend($pdo, $userId, 0);
$quota = mentor_quota($pdo, $userId);
$quota['ai_available'] = $aiAllowed;

$matched = mentor_match($norm, $KNOWLEDGE['entries'], 3);
$knowledge = mentor_knowledge_block($matched);
$fallback = $matched ? mentor_best_answer($norm, $KNOWLEDGE) : null;

// "How do I upload my notes" contains my, but it is not a request for a
// number - it is a request for steps. Which half comes first is decided by
// that, not by the possessive. The privacy wording belongs here for the same
// reason: "who can see my quiz history" wants the switch, not their own stats.
$asksHow = (bool)preg_match(
    '/\b(how do i|how to|how can i|where do i|where can i|what do i|why is my|why are my'
    . '|why does my|why cant i|steps?|guide|who can see|who sees|can people see|can anyone see'
    . '|can others see|hides?|hidden|visible|visibility|private|privacy)\b/',
    $norm
);

// 1 - the account's own figures. Composed here, never by the model.
if ($personal) {
    $direct = mentor_personal($norm, $facts);
    if ($direct !== null) {
        if ($asksHow && $fallback !== null) {
            $direct['answer'] = $fallback['answer'] . "\n\n" . $direct['answer'];
            $direct['link'] = $direct['link'] ?: $fallback['link'];
        } elseif ($fallback !== null && preg_match('/\b(improve|increase|get more|faster|better|reach|raise)\b/', $norm)) {
            // A question that both asks for a number and asks how to move it
            // gets the guidance underneath the figure, because that is the real
            // question.
            $direct['answer'] .= "\n\n" . $fallback['answer'];
            $direct['link'] = $direct['link'] ?: $fallback['link'];
        }
        $direct['facts'] = mentor_fact_lines($facts);
        $direct['quota'] = $quota;
        api_out($direct + ['ok' => true]);
    }
}

// 2 and 3 - knowledge, then the model on top of it.
$aiAnswer = null;

if ($aiAllowed && mentor_configured()) {
    $aiAnswer = mentor_generate($KNOWLEDGE['persona'], $knowledge, mentor_fact_lines($facts), $history, $question);
    if ($aiAnswer !== null) {
        // The question itself was already counted above; only the neurons the
        // model actually used are added here.
        mentor_spend_neurons($pdo, $userId, $aiAnswer['neurons']);

        // The model sometimes hedges on a question the knowledge file does
        // answer. Its wording is nicer and its facts are not - so when it
        // admits it is unsure and a real entry is sitting right there, the
        // entry is used instead. This is what makes "Mentor could not answer"
        // a rare reply rather than the ordinary one.
        if ($fallback !== null && preg_match(
            '/\b(not sure|no information|do not have|don\'?t have|not written|i cannot|i can\'?t answer|not able)\b/i',
            $aiAnswer['text']
        )) {
            $aiAnswer = null;
        }

        // The other failure is over-compression: handed a full entry, the model
        // sometimes answers "Tap My Quizzes." - true, but it drops the button
        // and the rule the student asked for. Under this many characters, with a
        // real entry available, the entry is published in its place.
        if ($fallback !== null && $aiAnswer !== null
            && mb_strlen(trim((string)$aiAnswer['text'])) < 90
            && mb_strlen((string)$fallback['answer']) > 180) {
            $aiAnswer = null;
        }
    }
}

if ($aiAnswer !== null) {
    api_out([
        'ok' => true,
        'source' => 'ai',
        'answer' => $aiAnswer['text'],
        'link' => $fallback['link'] ?? null,
        'facts' => mentor_fact_lines($facts),
    'quota' => $quota,]);
}

if ($fallback !== null) {
    api_out([
        'ok' => true,
        'source' => 'knowledge',
        'answer' => $fallback['answer'],
        'link' => $fallback['link'],
        'facts' => mentor_fact_lines($facts),
    'quota' => $quota,]);
}

// Nothing in the knowledge file covered it. Two very different cases hide here.
// If an entry did match, the question is about the app and the written answer
// already went out above, so this is genuinely something nobody has told me -
// hand it to support. If nothing matched at all, it is most likely a school
// subject, and sending a student who asked about biology to a support inbox
// would be the wrong answer, so it only failed to reach the model. Say so and
// invite the retry.
if (!$matched) {
    // Past the day's allowance the model is never called, so the honest reply
    // is that it is spent - telling a student to ask again would send them
    // round the same wall.
    if (!$quota['ai_available']) {
        api_out([
            'ok' => true,
            'source' => 'none',
            'answer' => 'My study answers are all used up for today, so just now I only know what is written in '
                . 'my guide - quizzes, points, materials, battles, payments and settings, and your own record. '
                . 'Ask me one of those, or try the subject again tomorrow.',
            'link' => null,
            'facts' => mentor_fact_lines($facts),
        'quota' => $quota,]);
    }

    api_out([
        'ok' => true,
        'source' => 'none',
        'answer' => 'That one slipped past me on the way through, not because I do not know it. I answer school '
            . 'subjects as well as anything in this app, so ask it once more and I will explain it properly.',
        'link' => null,
        'facts' => mentor_fact_lines($facts),
    'quota' => $quota,]);
}

api_out([
    'ok' => true,
    'source' => 'none',
    'answer' => 'I do not have that one written down yet, and I would rather say so than guess. '
        . 'Tell a person what you were trying to do from More, Help, Contact support and they will answer it. '
        . 'I can still help with quizzes, materials, battles, points, your own record, payments and settings.',
    'link' => ['type' => 'screen', 'target' => 'Support', 'label' => 'Contact support'],
    'facts' => mentor_fact_lines($facts),
'quota' => $quota,]);

// ─────────────────────────────────────────────────────────────── the account ────

/**
 * Everything Mentor is allowed to state as fact about this student.
 *
 * Each line is a fresh read. A badge list, a battle record and a rank are pulled
 * from the tables the app itself draws from, so the number in a reply is the
 * number on the screen - not a copy the model once saw.
 */
function mentor_facts(PDO $pdo, array $user): array
{
    $uid = (int)$user['id'];
    $f = [
        'name' => (string)($user['name'] ?? 'there'),
        'track' => (($user['user_type'] ?? '') === 'university') ? 'university' : 'secondary',
        'points' => (int)($user['points'] ?? 0),
        'member_since' => substr((string)($user['created_at'] ?? ''), 0, 10),
        'level' => 1,
        'level_name' => '',
        'level_progress' => 0,
        'points_to_next' => 0,
        'streak' => 0,
        'longest_streak' => 0,
        'last_quiz_date' => null,
        'attempts' => 0,
        'sets' => 0,
        'average' => null,
        'best' => null,
        'certificates' => 0,
        'badges' => 0,
        'badge_names' => [],
        'read_materials' => 0,
        'purchased' => 0,
        'unread' => 0,
        'posts' => 0,
        'wins' => 0,
        'losses' => 0,
        'draws' => 0,
        'rating' => 1000,
        'rank' => null,
        'claims' => [],
    ];

    try {
        $g = new Gamification($pdo);
        $stats = $g->getUserStats($uid);
        $f['level'] = (int)($stats['level'] ?? 1);
        $f['level_name'] = Gamification::getLevelName($f['level'], $f['track']);
        $f['level_progress'] = (int)($stats['level_progress']['progress'] ?? 0);
        $f['points_to_next'] = (int)($stats['level_progress']['points_needed'] ?? 0);
        $f['streak'] = (int)($stats['streak'] ?? 0);
        $f['longest_streak'] = (int)($stats['longest_streak'] ?? 0);
        $f['last_quiz_date'] = $stats['last_quiz_date'] ?? null;
        $f['badges'] = count($stats['badges'] ?? []);
        foreach (array_slice($stats['badges'] ?? [], 0, 6) as $b) {
            $f['badge_names'][] = $b['label'] ?? $b['name'];
        }
    } catch (Throwable $e) {
        error_log('mentor stats: ' . $e->getMessage());
    }

    try {
        $row = $pdo->prepare('SELECT COUNT(*) attempts,
                                      COUNT(DISTINCT quiz_id) sets,
                                      ROUND(AVG(score * 100 / NULLIF(total, 0))) average,
                                      ROUND(MAX(score * 100 / NULLIF(total, 0))) best
                                 FROM quiz_attempts WHERE user_id = ?');
        $row->execute([$uid]);
        $r = $row->fetch(PDO::FETCH_ASSOC) ?: [];
        $f['attempts'] = (int)($r['attempts'] ?? 0);
        $f['sets'] = (int)($r['sets'] ?? 0);
        $f['average'] = $r['average'] === null ? null : (int)$r['average'];
        $f['best'] = $r['best'] === null ? null : (int)$r['best'];
    } catch (Throwable $e) {
        error_log('mentor attempts: ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ? AND total > 0 AND score * 100 / total >= 50');
        $stmt->execute([$uid]);
        $f['certificates'] = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('mentor certificates: ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT material_id) FROM material_progress WHERE user_id = ?');
        $stmt->execute([$uid]);
        $f['read_materials'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM material_payments WHERE user_id = ? AND status = 'successful'");
        $stmt->execute([$uid]);
        $f['purchased'] = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('mentor materials: ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$uid]);
        $f['unread'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE author_id = ?');
        $stmt->execute([$uid]);
        $f['posts'] = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('mentor counts: ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare('SELECT rating, wins, losses, draws FROM user_battle_stats WHERE user_id = ?');
        $stmt->execute([$uid]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($b) {
            $f['rating'] = (int)$b['rating'];
            $f['wins'] = (int)$b['wins'];
            $f['losses'] = (int)$b['losses'];
            $f['draws'] = (int)$b['draws'];
        }
    } catch (Throwable $e) {
        error_log('mentor battles: ' . $e->getMessage());
    }

    // The same ORDER BY points the leaderboard page uses, so a rank Mentor states
    // is the rank the board would show.
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) + 1 FROM users WHERE status = \'active\' AND points > ?');
        $stmt->execute([$f['points']]);
        $f['rank'] = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('mentor rank: ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare("SELECT status, amount FROM reward_claims WHERE user_id = ? ORDER BY id DESC LIMIT 3");
        $stmt->execute([$uid]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $f['claims'][] = ['status' => $c['status'], 'amount' => (float)$c['amount']];
        }
    } catch (Throwable $e) {
        error_log('mentor claims: ' . $e->getMessage());
    }

    return $f;
}

/** Does this question ask about the student's own record? */
function mentor_is_personal(string $norm): bool
{
    // Both orders of "i have" and "have i", because "how many quizzes have i
    // done" is the same lookup as "how many quizzes do i have".
    return (bool)preg_match(
        '/\b(my|mine|i\s+have|i\s+ve|ive\s+got|have\s+i|has\s+i|do\s+i|did\s+i|am\s+i|was\s+i|me|myself|account|record|profile)\b/u',
        $norm
    );
}

/**
 * The number answers, worded as a person would say them.
 *
 * Every branch allows the plural, because students write "points", "badges" and
 * "scores" far more often than the singular, and a word boundary on its own
 * turns those into a missed lookup.
 *
 * Returning null means "this is not a figure I hold", and the caller then lets
 * the knowledge file and the model have it.
 */
function mentor_personal(string $norm, array $f): ?array
{
    $who = mentor_first_name($f);
    $answer = null;
    $link = null;

    $has = static function (string $re) use ($norm): bool {
        return (bool)preg_match($re, $norm);
    };

    // Ordered by how specific the words are: "what is my level and points"
    // should not be swallowed by the general progress answer.
    if ($has('/\bstreaks?\b/')) {
        $answer = $f['streak'] > 0
            ? 'Your streak is ' . $f['streak'] . ' day' . ($f['streak'] === 1 ? '' : 's') . ' right now, with '
                . $f['longest_streak'] . ' as your best run. Finish one quiz a day to keep it going - at 3, 7, 14 and 30 days it gives you badges.'
            : 'You have no streak running at the moment; your best so far is ' . $f['longest_streak'] . ' day' . ($f['longest_streak'] === 1 ? '' : 's')
                . '. Finishing a quiz today starts it again from one.';
        $link = ['type' => 'screen', 'target' => 'Quizzes', 'label' => 'Open CBT'];
    } elseif ($has('/\b(ranks?|positions?|standings?|leaderboard)\b/') || $has('/where\s+am\s+i/')) {
        $answer = $f['rank'] !== null
            ? 'You are number ' . $f['rank'] . ' on the points board with ' . $f['points'] . ' points, level '
                . $f['level'] . ($f['level_name'] !== '' ? ' (' . $f['level_name'] . ')' : '') . '.'
            : 'You have ' . $f['points'] . ' points right now. The board only lists students with points, so your place appears as soon as you have some.';
        $link = ['type' => 'screen', 'target' => 'Leaderboard', 'label' => 'Open Leaderboard'];
    } elseif ($has('/\b(points?|xp)\b/')) {
        $answer = 'You have ' . $f['points'] . ' point' . ($f['points'] === 1 ? '' : 's') . '. '
            . ($f['points_to_next'] > 0
                ? 'Another ' . $f['points_to_next'] . ' takes you to level ' . ($f['level'] + 1) . '.'
                : 'You are at the top level, ' . $f['level'] . '.');
        $link = ['type' => 'screen', 'target' => 'Leaderboard', 'label' => 'Open Leaderboard'];
    } elseif ($has('/\blevels?\b/') && !$has('/quiz|material|subject/')) {
        $answer = 'You are level ' . $f['level'] . ($f['level_name'] !== '' ? ', ' . $f['level_name'] : '') . ', at '
            . $f['points'] . ' points - ' . $f['level_progress'] . '% of the way to level ' . ($f['level'] + 1) . '.';
        $link = ['type' => 'screen', 'target' => 'Achievements', 'label' => 'Open Achievements'];
    } elseif ($has('/\b(badges?|achievements?|troph(y|ies))\b/')) {
        $answer = $f['badges'] > 0
            ? 'You have ' . $f['badges'] . ' badge' . ($f['badges'] === 1 ? '' : 's')
                . ($f['badge_names'] ? ': ' . implode(', ', $f['badge_names']) . '.' : '.')
            : 'No badges yet. Your first quiz finish gives one, and a perfect paper gives another.';
        $link = ['type' => 'screen', 'target' => 'Achievements', 'label' => 'Open Achievements'];
    } elseif ($has('/\bcertificat(e|es)\b/')) {
        $answer = $f['certificates'] > 0
            ? 'You have earned ' . $f['certificates'] . ' certificate' . ($f['certificates'] === 1 ? '' : 's')
                . ' - any sitting at 50% or above. Press Download certificate on that result to save it as an '
                . 'image or a PDF.'
            : 'No certificate yet. A sitting at 50% or above earns one, and the Download certificate button then '
                . 'shows under that set on My Quizzes and on its result page.';
        $link = ['type' => 'screen', 'target' => 'Quizzes', 'label' => 'Open CBT'];
    } elseif ($has('/\b(wins?|won|los|battles?|ratings?|elo)/')) {
        $played = $f['wins'] + $f['losses'] + $f['draws'];
        $answer = $played > 0
            ? 'In battles you are ' . $f['wins'] . ' won, ' . $f['losses'] . ' lost, ' . $f['draws'] . ' drawn, rated '
                . $f['rating'] . '. Rating starts at 1000 and moves with each result.'
            : 'You have not fought a battle yet, so your record is empty and your rating starts at 1000 when you do. '
                . 'Open the Battle tab, pick someone in the Arena or post a challenge in the Hub.';
        $link = ['type' => 'screen', 'target' => 'Battles', 'label' => 'Open Battle'];
    } elseif ($has('/\b(materials?|downloads?|reading|notes?)\b/') && !$has('/quiz/')) {
        $answer = 'You have opened ' . $f['read_materials'] . ' material' . ($f['read_materials'] === 1 ? '' : 's')
            . ' and bought ' . $f['purchased'] . '. Your reading position is saved, so My Study offers Continue on each one.';
        $link = ['type' => 'screen', 'target' => 'Learn', 'label' => 'Open My Study'];
    } elseif ($has('/\b(unread|notifications?|alerts?)\b/')) {
        $answer = 'You have ' . $f['unread'] . ' unread notification' . ($f['unread'] === 1 ? '' : 's')
            . '. The bell at the top of the app opens them.';
        $link = ['type' => 'screen', 'target' => 'Notifications', 'label' => 'Open Notifications'];
    } elseif ($has('/\b(rewards?|claims?|prizes?|money|paid)\b/')) {
        if ($f['claims']) {
            $lines = [];
            foreach ($f['claims'] as $c) {
                $lines[] = number_format($c['amount'], 2) . ' (' . $c['status'] . ')';
            }
            $answer = 'Your prize claims: ' . implode(', ', $lines) . '. An admin moves each one from pending to processing to paid.';
        } else {
            $answer = 'You have no prize claim on record yet. Claims come from challenges with a prize pool - enter one, place in the winners list, then give your bank details on the claim page.';
        }
        $link = ['type' => 'screen', 'target' => 'RewardClaim', 'label' => 'Open Rewards'];
    } elseif ($has('/\b(quiz(zes)?|attempts?|scores?|marks?|average|results?)\b/') && $has('/how\s+many|average|best|my|total|many/')) {
        $answer = 'You have sat ' . $f['attempts'] . ' attempt' . ($f['attempts'] === 1 ? '' : 's') . ' across '
            . $f['sets'] . ' quiz set' . ($f['sets'] === 1 ? '' : 's') . '.'
            . ($f['average'] !== null ? ' Your average mark is ' . $f['average'] . '% and your best is ' . (int)$f['best'] . '%.' : '')
            . ($f['last_quiz_date'] ? ' Last paper: ' . $f['last_quiz_date'] . '.' : '');
        $link = ['type' => 'screen', 'target' => 'Quizzes', 'label' => 'Open CBT'];
    } elseif ($has('/\b(since|joined|registered|how long)\b/') || $has('/new\s+(to|account)/')) {
        $answer = 'The account has been here since ' . $f['member_since'] . ', as a ' . $f['track']
            . ' student' . ($f['rank'] !== null ? ', now number ' . $f['rank'] . ' on the points board' : '') . '.';
    }

    // Whether the student is passing is a question about two real numbers -
    // their own average and the 50% certificate mark - so it never goes to the
    // model, which has no way to know either.
    if ($answer === null && $has('/\b(pass|passing|failing|failed|fail|dropping|bottom)\b/')) {
        $answer = $f['average'] === null
            ? 'There is no quiz mark on your record yet, so there is nothing to judge. Finish one free quiz and the app starts tracking your average, which is the number to watch.'
            : 'Your average across ' . $f['attempts'] . ' quiz attempt' . ($f['attempts'] === 1 ? '' : 's')
                . ' is ' . $f['average'] . '%, and your best sitting is ' . (int)$f['best'] . '%. Fifty percent is the pass mark for a certificate, so you are '
                . ((int)$f['average'] >= 50 ? 'already above it - keep every sitting over fifty and each one earns a certificate.'
                    : 'below it for now. ' . $f['best'] . '% shows you can reach it; two or three more papers will move the average up.');
        $link = ['type' => 'screen', 'target' => 'Quizzes', 'label' => 'Open CBT'];
    }

    if ($answer !== null) {
        return ['source' => 'account', 'answer' => $answer, 'link' => $link];
    }

    // A general "how am I doing" gets the whole card, in the order a student reads it.
    if ($has('/\b(how\s+am\s+i|how\s+are\s+things|progress|doing|report|summary|stats|statistics|overview|everything|about\s+me)\b/')) {
        $answer = $who . ', you are level ' . $f['level']
            . ($f['level_name'] !== '' ? ' (' . $f['level_name'] . ')' : '') . ' with ' . $f['points']
            . ' point' . ($f['points'] === 1 ? '' : 's')
            . ($f['rank'] !== null ? ', ranked ' . $f['rank'] : '')
            . ', a ' . $f['streak'] . ' day streak, ' . $f['attempts'] . ' quiz attempt' . ($f['attempts'] === 1 ? '' : 's')
            . ($f['average'] !== null ? ' averaging ' . $f['average'] . '%' : '')
            . ', ' . $f['wins'] . ' battle win' . ($f['wins'] === 1 ? '' : 's') . ' and ' . $f['badges'] . ' badge' . ($f['badges'] === 1 ? '' : 's') . '.'
            . ($f['points_to_next'] > 0 ? ' Next level needs ' . $f['points_to_next'] . ' more.' : '');
        return ['source' => 'account', 'answer' => $answer, 'link' => ['type' => 'screen', 'target' => 'Home', 'label' => 'Open Home']];
    }

    return null;
}

/** The block the model is allowed to quote numbers from. */
function mentor_fact_lines(array $f): string
{
    $lines = [
        'name: ' . $f['name'],
        'account type: ' . $f['track'],
        'points: ' . $f['points'],
        'level: ' . $f['level'] . ($f['level_name'] !== '' ? ' (' . $f['level_name'] . ')' : ''),
        'points to next level: ' . $f['points_to_next'],
        'level progress: ' . $f['level_progress'] . '%',
        'current streak: ' . $f['streak'] . ' days',
        'longest streak: ' . $f['longest_streak'] . ' days',
        'quiz attempts: ' . $f['attempts'] . ' across ' . $f['sets'] . ' sets',
        'average mark: ' . ($f['average'] === null ? 'none yet' : $f['average'] . '%'),
        'best mark: ' . ($f['best'] === null ? 'none yet' : $f['best'] . '%'),
        'certificates earned: ' . $f['certificates'],
        'badges: ' . $f['badges'] . ($f['badge_names'] ? ' (' . implode(', ', $f['badge_names']) . ')' : ''),
        'materials opened: ' . $f['read_materials'],
        'materials bought: ' . $f['purchased'],
        'unread notifications: ' . $f['unread'],
        'community posts: ' . $f['posts'],
        'battles: ' . $f['wins'] . ' wins, ' . $f['losses'] . ' losses, ' . $f['draws'] . ' draws, rating ' . $f['rating'],
        'leaderboard rank: ' . ($f['rank'] === null ? 'not listed' : $f['rank']),
        'member since: ' . $f['member_since'],
    ];

    return implode("\n", $lines);
}

function mentor_first_name(array $f): string
{
    $first = preg_split('/\s+/u', trim((string)$f['name']))[0] ?? '';
    return $first !== '' ? $first : 'you';
}

// ─────────────────────────────────────────────────────────────── knowledge ──────

/**
 * One reading of what the student typed.
 *
 * Lower-cased, with the two shapes of an English contraction folded into one:
 * "can't" and "can t" are the same words to a matcher, and so is "student's".
 * Without this the keywords written with a space never meet the question an
 * actual person types.
 */
function mentor_norm(string $s): string
{
    $s = mb_strtolower($s);
    $s = str_replace(['’', '‘', '`', '´'], "'", $s);
    $s = preg_replace("/'/u", ' ', $s);
    $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

/**
 * Does $phrase appear as its own word or group of words?
 *
 * A plain substring test is not good enough here: "photo" sits inside
 * "photosynthesis", and a student asking about plants was being handed the
 * profile page. Nor does strict whole-word matching work on its own, because
 * nobody writes "sync" when they mean "syncing". So each word of the phrase
 * matches itself plus its own endings, and nothing longer - the word still has
 * to stop there.
 */
function mentor_hits(string $norm, string $phrase): bool
{
    $phrase = mentor_norm($phrase);
    if ($phrase === '') {
        return false;
    }
    $words = array_map(
        // The z group is "quiz" meeting its own plural - quizzes, quizzing,
        // quizzed - which is the one doubled consonant students type often.
        static fn(string $w): string => preg_quote($w, '/') . '(?:s|es|ed|ing|ly|z(?:es|ed|ing))?',
        preg_split('/\s+/u', $phrase, -1, PREG_SPLIT_NO_EMPTY)
    );
    $pattern = '/(?<![\\p{L}\\p{N}])' . implode('[\\s]+', $words) . '(?![\\p{L}\\p{N}])/u';
    return (bool)preg_match($pattern, $norm);
}

/**
 * Score the entries.
 *
 * A keyword is worth its length, so "how do i get points" lands on the entry
 * that says "how do i get points" rather than one that merely mentions points.
 * Topic words count for less. The result is small and predictable, which matters
 * more than cleverness here - a wrong entry sends the model off with the wrong
 * source.
 */
function mentor_match(string $norm, array $entries, int $limit): array
{
    $scored = [];
    foreach ($entries as $e) {
        $score = 0;
        $matchedKeys = [];
        foreach ((array)($e['keywords'] ?? []) as $kw) {
            if (!mentor_hits($norm, (string)$kw)) {
                continue;
            }
            $matchedKeys[] = $kw;
            $score += mb_strlen(mentor_norm((string)$kw)) >= 8 ? 6 : (mb_strlen(mentor_norm((string)$kw)) >= 5 ? 4 : 2);
        }
        $topic = mb_strtolower((string)($e['topic'] ?? ''));
        if ($topic !== '') {
            foreach (preg_split('/[\s,]+/u', $topic, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                if (mb_strlen($word) > 3 && mentor_hits($norm, $word)) {
                    $score += 2;
                }
            }
        }
        // An entry only enters the ring if one of its own keywords landed.
        // Topic words are a tie-breaker, nothing more: without this rule the
        // word "what" in a topic like "What MyStudyBase is" pulled three
        // unrelated entries up for every "what is photosynthesis" question, and
        // a study question looked like an app question the guide had missed.
        if ($matchedKeys) {
            // Two entries can reach the same total by different routes, and a
            // tie is decided by whichever file order came first. So the number
            // of the student's own words that landed is added on top: the entry
            // they described is preferred over one that merely mentions a word.
            $scored[] = ['entry' => $e, 'score' => $score + max(0, count($matchedKeys) - 1) * 3];
        }
    }

    usort($scored, static fn($a, $b) => $b['score'] <=> $a['score']);

    return array_slice($scored, 0, $limit);
}

/** The matched entries, as the model sees them. */
function mentor_knowledge_block(array $matched): string
{
    if (!$matched) {
        return '(No entry in the knowledge file matched this question.)';
    }
    $out = [];
    foreach ($matched as $m) {
        $out[] = '- ' . $m['entry']['topic'] . ': ' . preg_replace('/\s+/', ' ', (string)$m['entry']['text']);
    }
    return implode("\n", $out);
}

/** The best entry as a stand-alone answer, when there is no model to phrase it. */
function mentor_best_answer(string $norm, array $knowledge): ?array
{
    $best = mentor_match($norm, $knowledge['entries'], 1);
    if (!$best || $best[0]['score'] < 4) {
        return null;
    }
    $e = $best[0]['entry'];

    return [
        'answer' => (string)$e['text'],
        'link' => mentor_link($e['go'] ?? null),
    ];
}

function mentor_link(?array $go): ?array
{
    if (!$go) {
        return null;
    }
    if (!empty($go['screen'])) {
        return ['type' => 'screen', 'target' => (string)$go['screen'], 'label' => (string)($go['label'] ?? 'Open')];
    }
    if (!empty($go['web'])) {
        return ['type' => 'web', 'target' => (string)$go['web'], 'label' => (string)($go['label'] ?? 'Open')];
    }
    return null;
}

// ──────────────────────────────────────────────────────────── the model ─────────

function mentor_configured(): bool
{
    return defined('CLOUDFLARE_ACCOUNT_ID') && defined('CLOUDFLARE_AI_TOKEN')
        && CLOUDFLARE_ACCOUNT_ID !== '' && CLOUDFLARE_AI_TOKEN !== '';
}

/**
 * Whether the day still has room.
 *
 * Per student first, so one person cannot spend the site's allowance, then the
 * site's own cap. Past either one Mentor keeps replying from the knowledge file,
 * which is why the caps are set with room rather than to the last neuron: the
 * free allowance is spent only if the whole school chats all day, and the reply
 * is still correct when that happens.
 */
function mentor_quota(PDO $pdo, int $userId): array
{
    $ready = mentor_usage_ready($pdo);
    if (!$ready) {
        return ['ai_available' => mentor_configured(), 'user' => 0, 'global' => 0];
    }

    $user = 0;
    $global = 0;
    try {
        $stmt = $pdo->prepare('SELECT calls FROM mentor_usage WHERE user_id = ? AND day = CURDATE()');
        $stmt->execute([$userId]);
        $user = (int)$stmt->fetchColumn();

        $stmt = $pdo->query('SELECT COALESCE(SUM(calls), 0) FROM mentor_usage WHERE day = CURDATE()');
        $global = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('mentor quota: ' . $e->getMessage());
    }

    return [
        'ai_available' => mentor_configured() && $user < MENTOR_USER_DAY && $global < MENTOR_GLOBAL_DAY,
        'user' => $user,
        'global' => $global,
        'limit' => MENTOR_USER_DAY,
        'left' => max(0, MENTOR_USER_DAY - $user),
    ];
}

function mentor_usage_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS mentor_usage (
            user_id INT NOT NULL,
            day DATE NOT NULL,
            calls INT NOT NULL DEFAULT 0,
            neurons DECIMAL(10,3) NOT NULL DEFAULT 0,
            PRIMARY KEY (user_id, day),
            KEY day (day)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $ready = true;
    } catch (Throwable $e) {
        error_log('mentor_usage unavailable: ' . $e->getMessage());
        $ready = false;
    }
    return $ready;
}

function mentor_spend(PDO $pdo, int $userId, float $neurons): void
{
    try {
        $pdo->prepare('INSERT INTO mentor_usage (user_id, day, calls, neurons)
                        VALUES (?, CURDATE(), 1, ?)
                        ON DUPLICATE KEY UPDATE calls = calls + 1, neurons = neurons + VALUES(neurons)')
            ->execute([$userId, $neurons]);
    } catch (Throwable $e) {
        error_log('mentor spend: ' . $e->getMessage());
    }
}

/**
 * The model's share of a question's cost.
 *
 * The question was already counted when it was asked, so this adds the neurons
 * and nothing else. Counting the model as another question would quietly halve
 * the allowance for every student whose answer came from the model, while the
 * phone went on promising fifteen questions a day - the promise is about
 * questions, not about which part of the server answered them.
 */
function mentor_spend_neurons(PDO $pdo, int $userId, float $neurons): void
{
    try {
        $pdo->prepare('UPDATE mentor_usage SET neurons = neurons + ?
                        WHERE user_id = ? AND day = CURDATE()')
            ->execute([$neurons, $userId]);
    } catch (Throwable $e) {
        error_log('mentor neurons: ' . $e->getMessage());
    }
}

/**
 * One Cloudflare Workers AI call.
 *
 * The whole prompt is the persona, the matched entries and the student's own
 * figures - the model is allowed to phrase, never to source. max_tokens stays
 * small because a study helper that writes three paragraphs is not helping.
 */
function mentor_generate(string $persona, string $knowledge, string $facts, array $history, string $question): ?array
{
    // The split matters more than the tone. Left without it, the model reads
    // "KNOWLEDGE is the only source" too broadly and sends a biology question to
    // the support page, which is the one failure a student notices.
    $system = $persona
        . "\n\nTwo kinds of question. About this app: say only what KNOWLEDGE states, and when KNOWLEDGE covers it, answer it straight out - never tell a student you are unsure of something that is written there. Keep the exact button names KNOWLEDGE gives; a one-line reply that drops them is no help. A price that is not written there is set by the school on that item, so say the price shows on the item itself."
        . "\n\nAbout a school subject - maths, biology, chemistry, economics, anything: that is not in KNOWLEDGE because it is not about the app, so answer it yourself from what you know, simply, with one example a Nigerian student can picture. Never send a study question to Contact support."
        . "\n\nHow to answer. Say the thing that was actually asked, in the first sentence - if the question has two parts, answer both parts. Then give the step, the number or the reason that makes it usable. Write two to five short sentences: a reply of one line has almost always left the student still asking. Never reply with only a pointer to a page ('go to Settings') without saying what to do once there. If you genuinely do not know, say so in one sentence and stop."
        . "\n\nKNOWLEDGE (the only thing you may state about the app):\n" . $knowledge
        . "\n\nSTUDENT NUMBERS (the only figures you may quote):\n" . $facts;

    $messages = [['role' => 'system', 'content' => $system]];
    foreach ($history as $turn) {
        $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
    }
    $messages[] = ['role' => 'user', 'content' => mb_substr($question, 0, 500)];

    // The model name carries slashes that must survive into the path, so it is
    // put in the URL as written. Both halves are constants, never user input.
    $url = 'https://api.cloudflare.com/client/v4/accounts/' . CLOUDFLARE_ACCOUNT_ID . '/ai/run/' . MENTOR_MODEL;
    $payload = json_encode([
        'messages' => $messages,
        'max_tokens' => 420,
        'temperature' => 0.2,
    ]);

    // A model that has not been called for a while starts slowly, and the TLS
    // handshake from this machine fails now and then, so the call gets a real
    // timeout and one retry. Waiting two more seconds for a proper answer beats
    // falling back too early; the knowledge file is the last resort, not the
    // first answer.
    $lastErr = '';
    // The app stops waiting at 60 seconds, so the whole call budget is capped
    // well inside that. Each try then gets its ladder time or whatever is left.
    $by = microtime(true) + 44;
    for ($try = 0; $try < 2; $try++) {
        $left = (int)ceil($by - microtime(true));
        if ($left < 5) {
            $lastErr = 'gave up inside the app deadline';
            break;
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . CLOUDFLARE_AI_TOKEN,
            'Content-Type: application/json',
            'Expect:',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        // The two tries together must finish inside the app's own 60 second
        // wait, or the phone gives up while the server still has an answer to
        // give it. Hence 24 then 28, never past what the budget leaves.
        curl_setopt($ch, CURLOPT_TIMEOUT, min($try === 0 ? 24 : 28, $left));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        // Same convention as the rest of the server's API calls: XAMPP ships no
        // CA store, so peer checks are off rather than every call failing here.
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        // Local OpenSSL stumbles on TLS 1.3 against the edge; 1.2 is what the
        // email and payment calls from this box already use without trouble.
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $http < 200 || $http >= 300) {
            $lastErr = trim('http=' . $http . ' errno=' . $errno . ' ' . substr((string)$body, 0, 200));
            continue;
        }

        $json = json_decode((string)$body, true);
        if (!is_array($json) || !($json['success'] ?? false)) {
            error_log('mentor ai refused: ' . substr((string)$body, 0, 400));
            return null;
        }

        $text = $json['result']['response']
            ?? ($json['result']['choices'][0]['message']['content'] ?? null);
        // An empty completion happens now and then, and it is worth the second
        // try rather than a wrong trip to the support page.
        if (!is_string($text) || trim($text) === '') {
            $lastErr = 'empty completion ' . substr((string)$body, 0, 200);
            continue;
        }

        return [
            'text' => mentor_clean($text),
            'neurons' => (float)($json['result']['usage']['neurons'] ?? 0),
        ];
    }

    error_log('mentor ai, both tries failed: ' . $lastErr);
    return null;
}

/**
 * What comes back is spoken to a phone screen.
 *
 * A model asked not to use markdown still reaches for it, and a stray asterisk
 * in the app looks like a bug, so the shape is corrected rather than trusted.
 */
function mentor_clean(string $text): string
{
    $text = str_replace(['**', '__', '###', '####'], '', $text);
    $text = preg_replace('/^\s*[-*]\s+/m', '', $text) ?? $text;
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    $text = trim(preg_replace('/\n{3,}/', "\n\n", $text) ?? $text);

    return mb_substr($text, 0, 1400);
}
