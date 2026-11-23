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
require_once __DIR__ . '/../../includes/mentor_web.php';

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

// A student in a paper does not get an assistant. On the phone the button is
// not there at all; this is the rule behind it, so it holds even if the phone
// is patched or the question is asked from another device.
require_once __DIR__ . '/../../includes/quiz_sessions.php';
$inQuiz = quiz_session_active($pdo, $userId);
if ($inQuiz) {
    api_err(
        'quiz_in_progress',
        'Mentor is switched off while you are taking a quiz. It comes back when your paper is handed in.',
        423
    );
}

// Read once here because every reply has to carry it: the chat bar shows how
// many of the day's answers are left, and that number is only honest if it
// comes back with the answer rather than in a second call the phone must make.
$quota = mentor_quota($pdo, $userId);
$aiAllowed = $quota['ai_available'];

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

// 1 - the account's own figures. Composed here, never by the model, and free:
// the numbers are read out of the database, so nothing was spent to give them.
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

// 2 - the guide covers it. The written answer is the answer, so it goes out as
// it was written, and it is free: no model, no search, no day spent. This is
// the rule the student was promised - what the app already knows does not
// count against the day's answers.
if ($fallback !== null) {
    api_out([
        'ok' => true,
        'source' => 'knowledge',
        'answer' => $fallback['answer'],
        'link' => $fallback['link'],
        'facts' => mentor_fact_lines($facts),
        'quota' => $quota,
    ]);
}

// 3 - not in the guide. This is the question the model exists for, so this is
// the only path that counts against the day's answers - and it counts only if
// the model really answered.
$answer = mentor_ask_model(
    $KNOWLEDGE['persona'],
    $knowledge,
    mentor_fact_lines($facts),
    $history,
    $question,
    $norm,
    $matched
);

if ($answer !== null) {
    // The model answered, so this question is charged for - once, for the
    // question, and for the neurons it used.
    mentor_spend_ai($pdo, $userId, (float)$answer['neurons']);
    $quota = mentor_quota($pdo, $userId);

    api_out([
        'ok' => true,
        'source' => 'ai',
        'answer' => $answer['text'],
        'link' => $answer['link'],
        'facts' => mentor_fact_lines($facts),
        'quota' => $quota,
        'searched' => !empty($answer['searched']),
    ]);
}

// 4 - the model is not available: its allowance for the day is spent, or the
// service is down. The web is still open, and what it says is better than
// nothing, so the results are read out as they stand - with the address under
// each one, so the student can see where it came from.
$search = mentor_web_search($question, 3);
if ($search) {
    api_out([
        'ok' => true,
        'source' => 'web',
        'answer' => mentor_web_answer($search),
        'link' => null,
        'facts' => mentor_fact_lines($facts),
        'quota' => $quota,
        'searched' => true,
    ]);
}

// Nothing at all: not in the guide, not on the web, and no model left for the
// day. Say that, and stop. No support page, no "More, Help" - a student who
// asked a question deserves an answer or a straight "I do not know this one",
// not a hand-off to a mailbox.
api_out([
    'ok' => true,
    'source' => 'none',
    'answer' => 'I do not have that one, and my answers for today are used up. '
        . 'Try me again tomorrow, or ask me something about your quizzes, points, materials, battles or settings any time - those are free.',
    'link' => null,
    'facts' => mentor_fact_lines($facts),
    'quota' => $quota,
]);

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
    // "How do I ..." is a request for the steps, not for a figure of the
    // student's own, and it is the most common way anybody asks for anything
    // in this app. Read as a question about their own record it answered
    // half the app with somebody else's answer: "how do I upload materials"
    // was answered with a page about the materials they had already saved.
    //
    // A how-to that really is about their own record is still one - "how many
    // quizzes have I done", "how do I find my streak" - so those words still
    // count.
    $asksHow = (bool)preg_match(
        '/\b(how\s+(do|can|should|would|could)\s+i|how\s+to|where\s+(do|can)\s+i)\b/u',
        $norm
    );

    if ($asksHow) {
        // "my" alone is not enough: in "how do I change my password" and
        // "where do I upload my notes" it names the thing the student is
        // asking about, not a figure of their record.
        return (bool)preg_match(
            '/\b(how\s+(many|much)|mine|myself|have\s+i|do\s+i\s+have|is\s+my|am\s+i)\b/u',
            $norm
        );
    }

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
    } elseif ($has('/\b(materials?|downloads?|reading|notes?)\b/')
        && !$has('/quiz|upload|share|submit|contribute|add/')) {
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
    $filler = '(?:a|an|the|my|our|this|that|some)';
    $pattern = '/(?<![\\p{L}\\p{N}])'
        . implode('[\\s]+(?:' . $filler . '[\\s]+)?', $words)
        . '(?![\\p{L}\\p{N}])/u';
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
        $out[] = '- [' . (string)($m['entry']['id'] ?? '?') . '] ' . $m['entry']['topic'] . ': ' . preg_replace('/\s+/', ' ', (string)$m['entry']['text']);
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
        'entry' => $e,
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

/**
 * Charge one question to the day's allowance.
 *
 * This is called only when the model really answered. A question the guide
 * covered, a question about the student's own record, a question answered from
 * the web while the model was unavailable - none of those reach this function,
 * so none of them costs the student anything. That is the whole promise: the
 * day's allowance is for answers the app could not give by itself.
 */
function mentor_spend_ai(PDO $pdo, int $userId, float $neurons): void
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
/**
 * Ask the model, with the web beside it.
 *
 * Two questions arrive at the model at once:
 *
 *   · the student's question, and
 *   · the answer to "has anybody already looked this up?"
 *
 * The web is asked first for the questions whose answer changes with time -
 * when a registration opens, what a fee is, what was announced today. A model
 * that has never heard of JAMB 2027 will answer JAMB 2027 confidently and
 * wrongly, and a student cannot tell the difference. So for those questions
 * the search happens before the model speaks, and the model writes its answer
 * from what the search said.
 *
 * For everything else - a school subject, a piece of the app the guide did not
 * cover - the model is offered the search as a tool and may use it, and the
 * student is charged for the answer either way.
 *
 * @return array{text:string, link:?array, searched:bool, neurons:float}|null
 */
function mentor_ask_model(string $persona, string $knowledge, string $facts, array $history, string $question, string $norm, array $matched): ?array
{
    if (!mentor_configured()) return null;

    $tools = [[
        'type' => 'function',
        'function' => [
            'name' => 'web_search',
            'description' => 'Search the web for current information. Use it when the answer depends on facts that change: dates, registration windows, prices, announcements, results.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'What to look for'],
                ],
                'required' => ['query'],
            ],
        ],
    ]];

    // A question about the world is looked up before it is answered.
    $results = [];
    if (mentor_is_current_question($norm)) {
        $results = mentor_web_search($question, 4);
    }
    $searched = !empty($results);

    $system = $persona
        . "\n\nTwo kinds of question. About this app: say only what KNOWLEDGE states, and when KNOWLEDGE covers it, answer it straight out - never tell a student you are unsure of something that is written there. Keep the exact button names KNOWLEDGE gives; a one-line reply that drops them is no help. A price that is not written there is set by the school on that item, so say the price shows on the item itself."
        . "\n\nAbout a school subject - maths, biology, chemistry, economics, anything: that is not in KNOWLEDGE because it is not about the app, so answer it yourself from what you know, simply, with one example a Nigerian student can picture. Never send a study question to Contact support."
        . "\n\nHow to answer. Say the thing that was actually asked, in the first sentence - if the question has two parts, answer both parts. Then give the step, the number or the reason that makes it usable. Write two to five short sentences: a reply of one line has almost always left the student still asking. Never reply with only a pointer to a page ('go to Settings') without saying what to do once there. Do not invent a 'tap ...' step that the entry does not state: if the entry explains a rule rather than a route, say the rule, because 'tap' in front of a sentence that is not a route makes the whole reply read as broken."
        . "\n\nIf WEB RESULTS are given, they are the truth for this question: build your answer on them, and give the date, the fee or the name exactly as they state it. If they say the thing has not been announced yet, say that it has not been announced yet - do not fill the gap with a guess. Name the sources in one short line at the end when you used them."
        . "\n\nKNOWLEDGE (the only thing you may state about the app):\n" . $knowledge
        . "\n\nSTUDENT NUMBERS (the only figures you may quote):\n" . $facts;

    // The entry the answer is really about is what decides the button the
    // student presses, so the model is asked to name it.
    $system .= "\n\nWhich entry. Begin your reply with a line that reads exactly ENTRY: <id>, "
        . "where <id> is the id in square brackets of the ONE entry above that answers the "
        . "question - the one the student actually meant. If none of them answers it, write "
        . "ENTRY: none and then answer from what you know or from WEB RESULTS. Name only that "
        . "one entry, and do not write the ENTRY line again afterwards.";

    $messages = [['role' => 'system', 'content' => $system]];
    foreach ($history as $turn) {
        $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
    }

    $ask = mb_substr($question, 0, 500);
    if ($searched) {
        $ask .= "\n\nWEB RESULTS (from the web, use these):\n" . mentor_search_block($results);
    }
    $messages[] = ['role' => 'user', 'content' => $ask];

    // When the search has already been done there is no reason to offer the
    // tool again; when it has not, the model may ask for it.
    $out = mentor_model_call($messages, $searched ? [] : $tools);
    if ($out === null) return null;

    // The model asked for a search of its own accord: do it and ask again.
    if (!$searched && !empty($out['tool_query'])) {
        $results = mentor_web_search((string)$out['tool_query'], 4);
        if ($results) {
            $searched = true;
            $messages[count($messages) - 1]['content'] = mb_substr($question, 0, 500)
                . "\n\nWEB RESULTS (from the web, use these):\n" . mentor_search_block($results);
            $out = mentor_model_call($messages, []);
            if ($out === null) return null;
        }
    }

    $text = trim((string)($out['text'] ?? ''));
    if ($text === '') return null;

    // The entry the model named comes off the front of its reply: the student
    // reads the answer, not the bookkeeping that decided the button.
    $entryId = null;
    if (preg_match('/^\s*ENTRY:\s*\[?([a-z0-9_-]+)\]?/mi', $text, $m)) {
        $entryId = strtolower(trim($m[1]));
        $text = preg_replace('/^\s*ENTRY:\s*\[?[a-z0-9_-]+\]?\s*/i', '', $text);
    }

    $link = null;
    if ($entryId !== null && $entryId !== 'none') {
        foreach ($matched as $m) {
            if (strtolower((string)($m['entry']['id'] ?? '')) === $entryId) {
                $link = mentor_link($m['entry']['go'] ?? null);
                break;
            }
        }
    }

    return [
        'text' => mentor_clean($text),
        'link' => $link,
        'searched' => $searched,
        'neurons' => (float)($out['neurons'] ?? 0),
    ];
}

/**
 * Does this question want an answer that changes with the calendar?
 *
 * Dates, registration windows, prices, announcements - the things a model
 * cannot know because it stopped reading the news at some point. A question
 * about mitosis is not one of them, and searching for it would only slow the
 * answer down and fill it with noise.
 */
function mentor_is_current_question(string $norm): bool
{
    return (bool)preg_match(
        '/\b(date|dates|when|registration|register|opening|closing|deadline|schedule'
        . '|price|prices|cost|costs|fee|fees|amount|latest|news|current|currently'
        . '|announce[ds]?|announcement|form|forms|jamb|waec|neco|post[- ]?utme'
        . '|20[2-9][0-9])\b/u',
        $norm
    );
}

/**
 * The web's results, written out as an answer.
 *
 * Used when the model cannot be called - the day's allowance is spent, or the
 * service is down. The student still gets the facts, with the address under
 * each one, which is more useful than being told to go and look it up.
 */
function mentor_web_answer(array $results): string
{
    $lines = [];
    $i = 0;
    foreach ($results as $r) {
        $i++;
        $lines[] = $i . '. ' . $r['title']
            . ($r['snippet'] !== '' ? ' - ' . mb_substr($r['snippet'], 0, 220) : '')
            . ($r['url'] !== '' ? ' (' . $r['url'] . ')' : '');
    }

    return "I looked this up on the web. Here is what it says:\n\n" . implode("\n", $lines);
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
