<?php
/**
 * "Take a Quiz" for the app: every practice set, filtered by level and subject.
 *
 * Filters run in SQL rather than over one fetched page - filtering a page of 15
 * in PHP used to hide quizzes that exist on page 2, so a subject could look
 * empty while it had quizzes. Challenge sets stay out of this list because the
 * Battle tab owns them, exactly as before.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Quiz.php';
require_once __DIR__ . '/../../models/QuizAttempt.php';
require_once __DIR__ . '/../../models/Gamification.php';
require_once __DIR__ . '/../../models/Payment.php';
require_once __DIR__ . '/../../models/AccessCode.php';
require_once __DIR__ . '/_challenge.php';

$user = api_auth();
$userId = (int)$user['id'];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$level = api_field($_GET, 'level');
$level = in_array($level, ['Secondary', 'University'], true) ? $level : null;
$subject = trim(api_field($_GET, 'subject'));

$where = 'WHERE (mode IS NULL OR mode != "challenge")';
$args = [];
if ($level) {
    $where .= ' AND level = :lvl';
    $args[':lvl'] = $level;
}
if ($subject !== '') {
    $where .= ' AND subject = :sub';
    $args[':sub'] = $subject;
}

$sql = "SELECT id, title, subject, level, type, mode, duration_seconds, total_phases, current_phase,
               is_paid, entry_fee, required_level, exam_types, description
        FROM quizzes $where ORDER BY created_at DESC LIMIT :l OFFSET :o";
$stmt = $pdo->prepare($sql);
foreach ($args as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':l', $limit, PDO::PARAM_INT);
$stmt->bindValue(':o', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$countSql = "SELECT COUNT(*) FROM quizzes $where";
$countStmt = $pdo->prepare($countSql);
foreach ($args as $k => $v) {
    $countStmt->bindValue($k, $v);
}
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();

// Alphabetical, always - the dropdown is a lookup list, not a history of
// whatever order the admin added things in.
$subStmt = $pdo->prepare("SELECT DISTINCT subject FROM quizzes
                           WHERE subject IS NOT NULL AND subject != ''
                             AND (mode IS NULL OR mode != 'challenge')
                           ORDER BY subject ASC");
$subStmt->execute();
$subjects = array_column($subStmt->fetchAll(PDO::FETCH_ASSOC), 'subject');

$ids = array_column($rows, 'id');
$questionCount = [];
$stats = [];
$materialCount = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("SELECT quiz_id, COUNT(*) n FROM quiz_questions WHERE quiz_id IN ($in) GROUP BY quiz_id");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $questionCount[(int)$r['quiz_id']] = (int)$r['n'];
    }

    $stmt = $pdo->prepare("SELECT quiz_id, COUNT(*) tries,
                                  MAX(ROUND(score / NULLIF(total,0) * 100)) best_pct,
                                  MIN(ROUND(score / NULLIF(total,0) * 100)) worst_pct
                             FROM quiz_attempts WHERE user_id = ? AND quiz_id IN ($in) GROUP BY quiz_id");
    $stmt->execute(array_merge([$userId], $ids));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $stats[(int)$r['quiz_id']] = $r;
    }

    // The study pack attached to a set, so the card can say "read these first"
    // before the student ever opens it.
    try {
        $stmt = $pdo->prepare("SELECT qm.quiz_id, COUNT(*) n
                                 FROM quiz_materials qm JOIN materials m ON m.id = qm.material_id
                                WHERE qm.quiz_id IN ($in) AND m.status = 'approved' GROUP BY qm.quiz_id");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $materialCount[(int)$r['quiz_id']] = (int)$r['n'];
        }
    } catch (Throwable $e) { /* a missing link table must not empty the library */ }
}

// The same three gates quiz.php applies before it will show a question.
$gamification = new Gamification($pdo);
$userLevel = (int)($gamification->getUserStats($userId)['level'] ?? 1);
$payment = new Payment($pdo);
$codes = new AccessCode($pdo);
$attemptModel = new QuizAttempt($pdo);

$itemUserType = (string)($user['user_type'] ?? '');
$out = [];
foreach ($rows as $q) {
    $id = (int)$q['id'];
    $stat = $stats[$id] ?? null;
    $attempts = (int)($stat['tries'] ?? 0);
    $isPaid = !empty($q['is_paid']);

    $locked = '';
    $quizType = (string)($q['type'] ?? 'both');
    if ($quizType !== 'both' && $itemUserType !== '' && $quizType !== $itemUserType) {
        $locked = $quizType === 'university' ? 'University students only' : 'Secondary students only';
    } elseif ((int)$q['required_level'] > $userLevel) {
        $locked = 'Needs Level ' . (int)$q['required_level'];
    } elseif ($isPaid && !$payment->hasPaid($userId, $id) && !$codes->hasUserUsedACodeForQuiz($userId, $id)) {
        $locked = 'Pay to enter';
    } elseif ($isPaid && $attempts > 0) {
        // A paid set is single-use, the same rule the web enforces.
        $locked = 'Already taken';
    }

    // Secondary sets carry the exam bodies on the card; the web defaults the
    // same three when the column is empty.
    $et = trim((string)($q['exam_types'] ?? ''));
    if ($et === '' && ($q['level'] ?? '') === 'Secondary') {
        $et = 'WAEC,NECO,JAMB';
    }
    $tags = array_values(array_filter(array_map('trim', explode(',', $et))));

    $out[] = [
        'id' => $id,
        'title' => (string)$q['title'],
        'subject' => (string)($q['subject'] ?? ''),
        'level' => (string)($q['level'] ?? ''),
        'tags' => $tags,
        'question_count' => $questionCount[$id] ?? 0,
        'minutes' => (int)ceil(((int)$q['duration_seconds']) / 60),
        'duration_seconds' => (int)$q['duration_seconds'],
        'phases' => (int)$q['total_phases'] > 1 ? (int)$q['total_phases'] : null,
        'current_phase' => (int)$q['current_phase'],
        'is_paid' => $isPaid,
        'entry_fee' => $isPaid ? (float)$q['entry_fee'] : null,
        'required_level' => (int)$q['required_level'] > 0 ? (int)$q['required_level'] : null,
        'locked' => $locked !== '' ? $locked : null,
        'attempts' => $attempts,
        'best_percent' => $stat && $stat['best_pct'] !== null ? (int)$stat['best_pct'] : null,
        'worst_percent' => $stat && $stat['worst_pct'] !== null ? (int)$stat['worst_pct'] : null,
        'materials_count' => $materialCount[$id] ?? 0,
    ];
}

// The same events the home feed shows, in the same shape, so the carousel here
// is the home card - not a second, lesser version of it. Ended arenas drop out.
$challengeRows = (new Quiz($pdo))->listChallenges(null, 6);
$challengeIds = array_column($challengeRows, 'id');
$challenges = api_home_challenge_map(
    $challengeRows,
    api_home_challenge_materials($pdo, $challengeIds),
    api_home_challenge_questions($pdo, $challengeIds)
);
$challenges = api_home_challenge_live($pdo, $userId, $challenges);

api_out([
    'ok' => true,
    'now' => time(),
    'items' => $out,
    'total' => $total,
    'page' => $page,
    'subjects' => $subjects,
    'user_level' => $userLevel,
    'level_default' => $itemUserType === 'university' ? 'University' : ($itemUserType === 'secondary' ? 'Secondary' : ''),
    'challenges' => $challenges,
]);
