<?php
/**
 * Achievements: every document this student has actually earned, and the one
 * certificate a download button asked for.
 *
 *   GET                  the shelf - quiz certificates and challenges won
 *   GET  ?attempt_id=N   that sitting, laid out exactly as the printer needs it
 *
 * No rule is invented here. The 50% pass mark, the date format, the rank line and
 * the tier names are certificate.php's own; the rank itself still comes from
 * QuizAttempt::topScores(), the only place that knows a multi-phase challenge is
 * scored by the SUM of its phases. The one deliberate difference: the website
 * will print an "Official Challenge Result" winner certificate for an event that
 * is still running, and this file will not. A trophy for a race that has not
 * finished is not an achievement, it is a forecast - so a live challenge stays a
 * normal certificate until its clock closes, and becomes a winner's the moment it
 * does.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Quiz.php';
require_once __DIR__ . '/../../models/QuizAttempt.php';

const ACH_PASS_MARK = 50;
/** topScores() is asked for a thousand places; beyond that the site stops counting. */
const ACH_BOARD = 1000;

$user = api_auth();
$userId = (int)$user['id'];
$attemptModel = new QuizAttempt($pdo);

/** certificate.php's tier names, letter for letter. */
function ach_rank_text(int $rank): string
{
    if ($rank === 1) {
        return '1st Place Champion';
    }
    if ($rank === 2) {
        return '2nd Place Runner Up';
    }
    if ($rank === 3) {
        return '3rd Place Finalist';
    }
    return $rank . 'th Place';
}

/**
 * A title a phone can really write to disk.
 *
 * The student asked for "the user name and the certificate subject" - so the
 * file is self-describing in a Downloads folder full of other people's PDFs.
 * Slashes, colons and the rest of the reserved set become spaces, because
 * Android refuses them and iOS quietly renames behind your back.
 */
function ach_filename(string $student, string $subject): string
{
    $clean = static function (string $s): string {
        $s = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|', "\0"], ' ', $s);
        $s = preg_replace('/\s+/u', ' ', trim($s));
        return trim((string)$s);
    };
    $student = $clean($student);
    $subject = $clean($subject);
    $name = $student !== '' && $subject !== ''
        ? $student . ' - ' . $subject
        : ($student !== '' ? $student : ($subject !== '' ? $subject : 'MyStudyBase certificate'));
    // Long quiz titles exist; filesystems and share sheets do not like them.
    return mb_strlen($name) > 90 ? rtrim(mb_substr($name, 0, 90)) : $name;
}

/**
 * Where this attempt sits on its own board, for every quiz at once.
 *
 * One query instead of one per quiz, because a student with forty sets would
 * otherwise pay forty board scans to open one shelf. The comparison is the exact
 * ORDER BY from topScores() - score down, then time taken up, then whoever got
 * there first - so the number is the site's number, not a near relation.
 */
function ach_ranks(PDO $pdo, array $attemptIds): array
{
    $ids = array_values(array_filter(array_map('intval', $attemptIds)));
    if (!$ids) {
        return [];
    }
    $in = implode(',', $ids);
    try {
        $stmt = $pdo->prepare("SELECT qa.id AS attempt_id, qa.quiz_id,
                                      LEAST((SELECT COUNT(*) FROM quiz_attempts x
                                              WHERE x.quiz_id = qa.quiz_id
                                                AND (x.score > qa.score
                                                  OR (x.score = qa.score AND x.duration_used < qa.duration_used)
                                                  OR (x.score = qa.score AND x.duration_used = qa.duration_used
                                                      AND x.completed_at < qa.completed_at))) + 1, " . ACH_BOARD . ") AS rank_pos,
                                      LEAST((SELECT COUNT(*) FROM quiz_attempts x WHERE x.quiz_id = qa.quiz_id), " . ACH_BOARD . ") AS players
                                 FROM quiz_attempts qa
                                WHERE qa.id IN ($in)");
        $stmt->execute();
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int)$r['attempt_id']] = ['quiz_id' => (int)$r['quiz_id'], 'rank' => (int)$r['rank_pos'], 'players' => (int)$r['players']];
        }
        return $out;
    } catch (Throwable $e) {
        error_log('api achievements ranks: ' . $e->getMessage());
        return [];
    }
}

/**
 * The same two numbers for one quiz, taken from the board model itself.
 *
 * Only multi-phase challenges need this: their standings are the sum of a
 * student's phases, which no single attempt row carries.
 */
function ach_board_place(QuizAttempt $attemptModel, int $quizId, int $userId): array
{
    $board = $attemptModel->topScores($quizId, null, ACH_BOARD);
    $rank = 0;
    foreach ($board as $i => $entry) {
        if ((int)$entry['user_id'] === $userId) {
            $rank = $i + 1;
            break;
        }
    }
    return ['rank' => $rank, 'players' => count($board)];
}

/** The site's percentile line: rank as a share of the board, never below top 1%. */
function ach_percentile(int $rank, int $players): string
{
    if ($players <= 1 || $rank <= 0) {
        return '1%';
    }
    $p = (int)round($rank / $players * 100);
    return max(1, $p) . '%';
}

$attemptId = (int)($_GET['attempt_id'] ?? 0);

// ─────────────────────────────────────────────────── one certificate, in full ─
if ($attemptId > 0) {
    $attempt = $attemptModel->getAttempt($attemptId, $userId);
    if (!$attempt) {
        api_err('not_found', 'That attempt is not on this account.', 404);
    }

    $stmt = $pdo->prepare('SELECT id, title, subject, level, mode, prize_amount, prize_type, prize_text,
                                  event_start, event_end, total_phases
                             FROM quizzes WHERE id = ?');
    $stmt->execute([(int)$attempt['quiz_id']]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$quiz) {
        api_err('not_found', 'The set behind this certificate is gone.', 404);
    }

    $score = (int)$attempt['score'];
    $total = (int)$attempt['total'];
    $percent = $total > 0 ? (int)round($score / $total * 100) : 0;
    if ($percent < ACH_PASS_MARK) {
        api_err('locked', 'Certificates are issued at the ' . ACH_PASS_MARK . '% pass mark or above.', 403, [
            'percent' => $percent,
            'pass_mark' => ACH_PASS_MARK,
        ]);
    }

    $isChallenge = (string)($quiz['mode'] ?? 'normal') === 'challenge';
    $end = !empty($quiz['event_end']) ? strtotime((string)$quiz['event_end']) : false;
    $settled = $end !== false && $end <= time();

    $phases = max(1, (int)($quiz['total_phases'] ?? 1));
    $place = $phases > 1 && $isChallenge
        ? ach_board_place($attemptModel, (int)$quiz['id'], $userId)
        : (ach_ranks($pdo, [$attemptId])[$attemptId] ?? ['rank' => 0, 'players' => 0]);

    $rank = (int)$place['rank'];
    $players = max(1, (int)$place['players']);
    $isWinner = $isChallenge && $settled && $rank >= 1 && $rank <= 3;

    $pool = (float)($quiz['prize_amount'] ?? 0);
    $prize = $isWinner && $pool > 0 ? Quiz::prizeShare($pool, $rank) : 0.0;

    $student = trim((string)($user['name'] ?? ''));
    $title = (string)$quiz['title'];

    api_out([
        'ok' => true,
        'certificate' => [
            'kind' => $isWinner ? 'winner' : 'standard',
            'attempt_id' => $attemptId,
            'quiz_id' => (int)$quiz['id'],
            'site_name' => (string)SITE_NAME,
            'student_name' => $student !== '' ? $student : 'Student',
            'quiz_title' => $title,
            'subject' => trim((string)($quiz['subject'] ?? '')),
            'level' => trim((string)($quiz['level'] ?? '')),
            'score' => $score,
            'total' => $total,
            'percent' => $percent,
            'rank' => $rank,
            'rank_display' => $rank > 0 ? $rank . ' / ' . $players : '—',
            'players' => $players,
            'percentile' => ach_percentile($rank, $players),
            'rank_text' => $isWinner ? ach_rank_text($rank) : '',
            'prize_amount' => $prize,
            'prize_label' => $prize > 0 ? '₦' . number_format($prize, 0)
                : ($isWinner ? (trim((string)($quiz['prize_text'] ?? '')) ?: ach_rank_text($rank)) : ''),
            // certificate.php prints the day the sitting ended, in its own format.
            'date_label' => date('F jS, Y', strtotime((string)$attempt['completed_at'])),
            'completed_at' => (string)$attempt['completed_at'],
            // The set's own title is what the student calls the subject, and it
            // is short enough to read in a Downloads list next to the name.
            'filename' => ach_filename($student, $title),
        ],
    ]);
}

// ───────────────────────────────────────────────────────────── the whole shelf ─
$sql = "SELECT q.id AS quiz_id, q.title, q.subject, q.level, q.mode, q.total_phases,
               q.prize_amount, q.prize_type, q.prize_text, q.event_start, q.event_end,
               COUNT(*) AS attempts,
               MAX(ROUND(qa.score / NULLIF(qa.total, 0) * 100)) AS best_pct,
               MAX(qa.completed_at) AS last_attempt,
               (SELECT id FROM quiz_attempts WHERE quiz_id = q.id AND user_id = :u1
                  ORDER BY score DESC, id DESC LIMIT 1) AS best_attempt_id
          FROM quiz_attempts qa
          JOIN quizzes q ON q.id = qa.quiz_id
         WHERE qa.user_id = :u2
         GROUP BY q.id, q.title, q.subject, q.level, q.mode, q.total_phases,
                  q.prize_amount, q.prize_type, q.prize_text, q.event_start, q.event_end
         ORDER BY last_attempt DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':u1' => $userId, ':u2' => $userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Marks first, so a forty-quiz student costs one query rather than forty.
$single = [];
foreach ($rows as $r) {
    $phases = max(1, (int)($r['total_phases'] ?? 1));
    if ((string)($r['mode'] ?? 'normal') !== 'challenge' || $phases <= 1) {
        $single[(int)$r['best_attempt_id']] = true;
    }
}
$ranks = ach_ranks($pdo, array_keys($single));

$now = time();
$items = [];
foreach ($rows as $r) {
    $percent = $r['best_pct'] === null ? 0 : (int)$r['best_pct'];
    // Below the pass mark there is no document to show, and the shelf is a shelf
    // of documents. The number still lives on My Quizzes.
    if ($percent < ACH_PASS_MARK) {
        continue;
    }

    $id = (int)$r['quiz_id'];
    $attemptId = (int)$r['best_attempt_id'];
    $isChallenge = (string)($r['mode'] ?? 'normal') === 'challenge';
    $phases = max(1, (int)($r['total_phases'] ?? 1));
    $end = !empty($r['event_end']) ? strtotime((string)$r['event_end']) : false;
    $settled = $end !== false && $end <= $now;

    if ($attemptId <= 0) {
        continue;
    }
    $place = $isChallenge && $phases > 1
        ? ach_board_place($attemptModel, $id, $userId)
        : ($ranks[$attemptId] ?? ['rank' => 0, 'players' => 0]);
    $rank = (int)$place['rank'];
    $players = max(1, (int)$place['players']);

    $won = $isChallenge && $settled && $rank >= 1 && $rank <= 3;
    $pool = (float)($r['prize_amount'] ?? 0);
    $prize = $won && $pool > 0 ? Quiz::prizeShare($pool, $rank) : 0.0;

    $items[] = [
        'kind' => $won ? 'win' : 'certificate',
        'quiz_id' => $id,
        'attempt_id' => $attemptId,
        'title' => (string)$r['title'],
        'subject' => trim((string)($r['subject'] ?? '')),
        'level' => trim((string)($r['level'] ?? '')),
        'is_challenge' => $isChallenge,
        'settled' => $settled,
        'percent' => $percent,
        'rank' => $rank,
        'players' => $players,
        'percentile' => ach_percentile($rank, $players),
        'rank_text' => $won ? ach_rank_text($rank) : '',
        'prize_amount' => $prize,
        'attempts' => (int)$r['attempts'],
        'date' => (string)$r['last_attempt'],
        'date_label' => date('j M Y', strtotime((string)$r['last_attempt'])),
    ];
}

// A podium place outranks a pass, however old it is: the shelf opens with the
// trophies, then the most recent certificates.
usort($items, static function ($a, $b) {
    $aw = $a['kind'] === 'win' ? 1 : 0;
    $bw = $b['kind'] === 'win' ? 1 : 0;
    if ($aw !== $bw) {
        return $bw <=> $aw;
    }
    if ($aw === 1 && $a['rank'] !== $b['rank']) {
        return $a['rank'] <=> $b['rank'];
    }
    return strcmp($b['date'], $a['date']);
});

$wins = array_values(array_filter($items, static fn($i) => $i['kind'] === 'win'));

api_out([
    'ok' => true,
    'items' => $items,
    'wins' => $wins,
    'certificates' => array_values(array_filter($items, static fn($i) => $i['kind'] !== 'win')),
    'summary' => [
        'total' => count($items),
        'certificates' => count($items) - count($wins),
        'wins' => count($wins),
        'prize_won' => array_sum(array_column($wins, 'prize_amount')),
        'subjects' => count(array_unique(array_column(array_filter($items, static fn($i) => $i['subject'] !== ''), 'subject'))),
        'best_percent' => $items ? max(array_column($items, 'percent')) : null,
    ],
]);
