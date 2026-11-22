<?php
/**
 * Leaderboards for the app. The XP board is the same ORDER BY points the
 * website's users page runs, and the battle board is Battle::getLeaderboard().
 * A student who turned "show my level" off in their profile stays hidden here
 * too - their name ranks, their number does not print.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Battle.php';
require_once __DIR__ . '/../../models/Gamification.php';

$user = api_auth();
$userId = (int)$user['id'];
$limit = 50;

$xpBoard = [];
$myXpRank = null;
try {
    $stmt = $pdo->prepare("SELECT id, name, profile_picture, points, user_type, show_level_on_users_page
                             FROM users
                            WHERE status = 'active' AND points > 0
                            ORDER BY points DESC, id ASC
                            LIMIT :l");
    $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $i => $r) {
        $xpBoard[] = [
            'rank' => $i + 1,
            'id' => (int)$r['id'],
            'name' => (string)$r['name'],
            'picture' => api_url($r['profile_picture'] ?? null),
            'points' => (int)$r['points'],
            'user_type' => $r['user_type'],
            'hidden' => (int)($r['show_level_on_users_page'] ?? 1) === 0,
            'me' => (int)$r['id'] === $userId,
        ];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) + 1 FROM users
                            WHERE status = 'active' AND points > (SELECT points FROM users WHERE id = ?)");
    $stmt->execute([$userId]);
    $myXpRank = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    error_log('api leaderboard xp: ' . $e->getMessage());
}

$battleModel = new Battle($pdo);
$battleBoard = [];
try {
    foreach ($battleModel->getLeaderboard($limit) as $i => $r) {
        $battleBoard[] = [
            'rank' => $i + 1,
            'id' => (int)$r['user_id'],
            'name' => (string)$r['name'],
            'picture' => api_url($r['profile_picture'] ?? null),
            'rating' => (int)($r['rating'] ?? 1000),
            'wins' => (int)($r['wins'] ?? 0),
            'losses' => (int)($r['losses'] ?? 0),
            'draws' => (int)($r['draws'] ?? 0),
            'streak' => (int)($r['current_streak'] ?? 0),
            'me' => (int)$r['user_id'] === $userId,
        ];
    }
} catch (Throwable $e) {
    error_log('api leaderboard battle: ' . $e->getMessage());
}

$stats = (new Gamification($pdo))->getUserStats($userId);

/**
 * The per-quiz board.
 *
 * A student wants to know where they finished in one particular quiz, not in the
 * whole school, so this is the website's own board: the same topScores() call,
 * the same order (score, then the faster time, then whoever finished first), and
 * the same cumulative sum across phases for a multi-phase quiz.
 *
 * The rank is worked out even when the student is outside the top fifty - being
 * told "you are not in the top 50" is not an answer to "what is my number".
 */
$quizList = [];
$quizBoard = null;
$quizId = (int)($_GET['quiz_id'] ?? 0);

try {
    $rows = $pdo->query("SELECT q.id, q.title, q.mode, q.is_paid, q.subject, q.level,
                                (SELECT COUNT(*) FROM quiz_attempts a WHERE a.quiz_id = q.id) AS plays
                           FROM quizzes q
                          ORDER BY q.created_at DESC
                          LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $quizList[] = [
            'id' => (int)$r['id'],
            'title' => (string)$r['title'],
            'subject' => $r['subject'] ?? null,
            'level' => $r['level'] ?? null,
            'is_challenge' => $r['mode'] === 'challenge' || !empty($r['is_paid']),
            'plays' => (int)$r['plays'],
        ];
    }
} catch (Throwable $e) {
    error_log('api leaderboard quiz list: ' . $e->getMessage());
}

if ($quizId > 0) {
    try {
        require_once __DIR__ . '/../../models/QuizAttempt.php';
        $attemptModel = new QuizAttempt($pdo);

        $qStmt = $pdo->prepare("SELECT id, title, subject, level, mode, is_paid, total_phases FROM quizzes WHERE id = ?");
        $qStmt->execute([$quizId]);
        $quiz = $qStmt->fetch(PDO::FETCH_ASSOC);

        if ($quiz) {
            $phases = max(1, (int)($quiz['total_phases'] ?: 1));
            $rows = $attemptModel->topScores($quizId, null, $limit);
            $items = [];
            $myRank = null;
            $myEntry = null;
            foreach ($rows as $i => $r) {
                $score = (int)($r['score'] ?? 0);
                $total = (int)($r['total'] ?? 0);
                $entry = [
                    'rank' => $i + 1,
                    'id' => (int)$r['user_id'],
                    'name' => (string)$r['name'],
                    'picture' => api_url($r['profile_picture'] ?? null),
                    'score' => $score,
                    'total' => $total,
                    'percent' => $total > 0 ? (int)round($score / $total * 100) : 0,
                    'seconds' => (int)($r['duration_used'] ?? 0),
                    'user_type' => $r['user_type'] ?? null,
                    'completed_at' => $r['completed_at'] ?? null,
                    'me' => (int)$r['user_id'] === $userId,
                ];
                $items[] = $entry;
                if ($entry['me'] && $myRank === null) { $myRank = $i + 1; $myEntry = $entry; }
            }

            // Outside the fifty: count whoever is genuinely ahead under the same
            // ordering, so the number printed is the number the board would show.
            if ($myRank === null) {
                if ($phases > 1) {
                    $m = $pdo->prepare("SELECT SUM(score) AS score, SUM(total) AS total, SUM(duration_used) AS duration_used, MAX(completed_at) AS completed_at
                                          FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? GROUP BY user_id");
                    $m->execute([$quizId, $userId]);
                    $mine = $m->fetch(PDO::FETCH_ASSOC);
                    $participants = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM quiz_attempts WHERE quiz_id = " . $quizId)->fetchColumn();
                    if ($mine) {
                        $c = $pdo->prepare("SELECT COUNT(*) FROM (
                                                SELECT user_id, SUM(score) AS s, SUM(duration_used) AS d, MAX(completed_at) AS c
                                                  FROM quiz_attempts WHERE quiz_id = ? GROUP BY user_id
                                            ) t
                                            WHERE t.s > ? OR (t.s = ? AND t.d < ?) OR (t.s = ? AND t.d = ? AND t.c < ?)");
                        $c->execute([$quizId, $mine['score'], $mine['score'], $mine['duration_used'], $mine['score'], $mine['duration_used'], $mine['completed_at']]);
                        $myRank = (int)$c->fetchColumn() + 1;
                    }
                } else {
                    $m = $pdo->prepare("SELECT score, total, duration_used, completed_at FROM quiz_attempts
                                         WHERE quiz_id = ? AND user_id = ?
                                         ORDER BY score DESC, duration_used ASC, completed_at ASC LIMIT 1");
                    $m->execute([$quizId, $userId]);
                    $mine = $m->fetch(PDO::FETCH_ASSOC);
                    $p = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ?");
                    $p->execute([$quizId]);
                    $participants = (int)$p->fetchColumn();
                    if ($mine) {
                        $c = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts
                                             WHERE quiz_id = ?
                                               AND (score > ? OR (score = ? AND duration_used < ?) OR (score = ? AND duration_used = ? AND completed_at < ?))");
                        $c->execute([$quizId, $mine['score'], $mine['score'], $mine['duration_used'], $mine['score'], $mine['duration_used'], $mine['completed_at']]);
                        $myRank = (int)$c->fetchColumn() + 1;
                    }
                }
                if ($mine) {
                    $score = (int)$mine['score'];
                    $total = (int)$mine['total'];
                    $myEntry = [
                        'rank' => $myRank,
                        'id' => $userId,
                        'name' => (string)$user['name'],
                        'picture' => api_url($user['profile_picture'] ?? null),
                        'score' => $score,
                        'total' => $total,
                        'percent' => $total > 0 ? (int)round($score / $total * 100) : 0,
                        'seconds' => (int)($mine['duration_used'] ?? 0),
                        'user_type' => $user['user_type'] ?? null,
                        'completed_at' => $mine['completed_at'] ?? null,
                        'me' => true,
                    ];
                }
            } else {
                $participants = $phases > 1
                    ? (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM quiz_attempts WHERE quiz_id = " . $quizId)->fetchColumn()
                    : (int)$pdo->query("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = " . $quizId)->fetchColumn();
            }

            $quizBoard = [
                'quiz' => [
                    'id' => (int)$quiz['id'],
                    'title' => (string)$quiz['title'],
                    'subject' => $quiz['subject'] ?? null,
                    'level' => $quiz['level'] ?? null,
                    'is_challenge' => $quiz['mode'] === 'challenge' || !empty($quiz['is_paid']),
                    'phases' => $phases,
                ],
                'items' => $items,
                'my_rank' => $myRank,
                'my_entry' => $myEntry,
                'participants' => $participants ?? 0,
            ];
        }
    } catch (Throwable $e) {
        error_log('api leaderboard quiz board: ' . $e->getMessage());
    }
}

api_out([
    'ok' => true,
    'xp' => [
        'items' => $xpBoard,
        'my_rank' => $myXpRank,
        'my_points' => (int)$stats['points'],
        'level' => (int)$stats['level'],
    ],
    'battle' => [
        'items' => $battleBoard,
        'me' => $battleModel->getUserStats($userId) ?: null,
    ],
    'quizzes' => $quizList,
    'quiz' => $quizBoard,
]);
