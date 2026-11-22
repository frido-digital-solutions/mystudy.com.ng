<?php
/**
 * "My Quizzes" for the app: one card per quiz the student has actually sat,
 * with the four numbers that matter - best, worst, how the whole subject is
 * going, and how many tries it took to get there.
 *
 * Percentages are rounded the way the website rounds them (score / total), so
 * the number on the phone is the number on the certificate.
 */

require_once __DIR__ . '/_bootstrap.php';

$user = api_auth();
$userId = (int)$user['id'];

$sql = "SELECT q.id AS quiz_id, q.title, q.subject, q.level, q.mode, q.is_paid, q.duration_seconds,
               q.total_phases, q.current_phase,
               COUNT(*) AS attempts,
               MAX(ROUND(qa.score / NULLIF(qa.total, 0) * 100)) AS best_pct,
               MIN(ROUND(qa.score / NULLIF(qa.total, 0) * 100)) AS worst_pct,
               SUM(qa.score) AS sum_score,
               SUM(qa.total) AS sum_total,
               MAX(qa.completed_at) AS last_attempt,
               (SELECT id FROM quiz_attempts WHERE quiz_id = q.id AND user_id = :u1
                  ORDER BY score DESC, id DESC LIMIT 1) AS best_attempt_id,
               (SELECT id FROM quiz_attempts WHERE quiz_id = q.id AND user_id = :u2
                  ORDER BY score ASC, id DESC LIMIT 1) AS worst_attempt_id
          FROM quiz_attempts qa
          JOIN quizzes q ON q.id = qa.quiz_id
         WHERE qa.user_id = :u3
         GROUP BY q.id, q.title, q.subject, q.level, q.mode, q.is_paid, q.duration_seconds,
                  q.total_phases, q.current_phase
         ORDER BY last_attempt DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':u1' => $userId, ':u2' => $userId, ':u3' => $userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Subject performance is every mark the student has made in that subject,
// pooled - one strong quiz should not carry a weak subject.
$subStmt = $pdo->prepare("SELECT q.subject, SUM(qa.score) s, SUM(qa.total) t, COUNT(DISTINCT qa.quiz_id) quizzes
                            FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id
                           WHERE qa.user_id = ? AND q.subject IS NOT NULL AND q.subject != ''
                           GROUP BY q.subject");
$subStmt->execute([$userId]);
$subjectStats = [];
$poolScore = 0;
$poolTotal = 0;
foreach ($subStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
    $percent = ((int)$s['t']) > 0 ? (int)round((int)$s['s'] / (int)$s['t'] * 100) : 0;
    $subjectStats[(string)$s['subject']] = [
        'percent' => $percent,
        'quizzes' => (int)$s['quizzes'],
        'answered' => (int)$s['t'],
        'correct' => (int)$s['s'],
    ];
    $poolScore += (int)$s['s'];
    $poolTotal += (int)$s['t'];
}

$items = [];
foreach ($rows as $r) {
    $best = $r['best_pct'] === null ? null : (int)$r['best_pct'];
    $worst = $r['worst_pct'] === null ? null : (int)$r['worst_pct'];
    $attempts = (int)$r['attempts'];
    $isPaid = !empty($r['is_paid']);
    $subject = (string)($r['subject'] ?? '');

    $items[] = [
        'quiz_id' => (int)$r['quiz_id'],
        'title' => (string)$r['title'],
        'subject' => $subject,
        'level' => (string)($r['level'] ?? ''),
        'mode' => (string)($r['mode'] ?? 'normal'),
        'is_paid' => $isPaid,
        'attempts' => $attempts,
        'best_percent' => $best,
        'best_attempt_id' => (int)$r['best_attempt_id'],
        'worst_percent' => $worst,
        'worst_attempt_id' => (int)$r['worst_attempt_id'],
        // The pass mark certificate.php uses: there is no pass_mark column.
        'certificate' => $best !== null && $best >= 50,
        // A paid or challenge set is single-sitting; "Retake" must not be
        // offered for something the server would refuse anyway.
        'can_retake' => !$isPaid && (string)($r['mode'] ?? 'normal') !== 'challenge',
        'last_attempt' => (string)$r['last_attempt'],
        'answered' => (int)$r['sum_total'],
        'correct' => (int)$r['sum_score'],
        'subject_percent' => $subjectStats[$subject]['percent'] ?? null,
        'minutes' => (int)ceil(((int)$r['duration_seconds']) / 60),
    ];
}

// The best and worst sitting across every quiz, with the set each came from -
// the performance card names the subject, not just a number.
$bestRow = null;
$worstRow = null;
foreach ($items as $i) {
    if ($i['best_percent'] !== null && ($bestRow === null || $i['best_percent'] > $bestRow['best_percent'])) {
        $bestRow = $i;
    }
    if ($i['worst_percent'] !== null && ($worstRow === null || $i['worst_percent'] < $worstRow['worst_percent'])) {
        $worstRow = $i;
    }
}

api_out([
    'ok' => true,
    'items' => $items,
    'total' => count($items),
    'summary' => [
        'quizzes' => count($items),
        'attempts' => array_sum(array_column($items, 'attempts')),
        'overall_percent' => $poolTotal > 0 ? (int)round($poolScore / $poolTotal * 100) : null,
        'certificates' => count(array_filter($items, static fn($i) => $i['certificate'])),
        'answered' => $poolTotal,
        'correct' => $poolScore,
        'best' => $bestRow ? [
            'percent' => $bestRow['best_percent'],
            'quiz_id' => $bestRow['quiz_id'],
            'title' => $bestRow['title'],
            'subject' => $bestRow['subject'],
            'attempt_id' => $bestRow['best_attempt_id'],
        ] : null,
        'worst' => $worstRow ? [
            'percent' => $worstRow['worst_percent'],
            'quiz_id' => $worstRow['quiz_id'],
            'title' => $worstRow['title'],
            'subject' => $worstRow['subject'],
            'attempt_id' => $worstRow['worst_attempt_id'],
        ] : null,
    ],
    'subjects' => $subjectStats,
]);
