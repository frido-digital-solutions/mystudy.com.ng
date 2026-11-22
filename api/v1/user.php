<?php
/**
 * One person's public profile, for the app.
 *
 * Everything comes from the same tables profile_public.php reads, and every
 * section still obeys that person's privacy switches, so the app cannot show
 * what the website would hide. Blocked or deleted accounts answer 404.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Gamification.php';
require_once __DIR__ . '/../../models/Battle.php';
require_once __DIR__ . '/../../models/User.php';

$viewer = api_auth();
$viewerId = (int)$viewer['id'];

$targetId = (int)($_GET['id'] ?? 0);
if ($targetId <= 0) api_err('missing_id', 'No profile was asked for.', 422);

// The switches do not live on the users row - six of the seven are user_meta
// rows - so reading them straight out of SELECT * made every one of them look
// "on" no matter what the owner chose. The model is the only thing that knows
// where each one is kept, so the profile is built from it.
$personModel = new User($pdo);
if (!$personModel->getById($targetId)) api_err('not_found', 'That profile is not available.', 404);
if ((string)$personModel->status !== 'active') api_err('not_found', 'That profile is not available.', 404);

$game = new Gamification($pdo);
$points = (int)$personModel->points;
$level = Gamification::getLevel($points);
$progress = $game->getLevelProgress($points);

$rank = 'Beginner';
$type = (string)$personModel->user_type;
if ($type === 'university') $rank = Gamification::UNI_LEVEL_NAMES[$level] ?? 'Scholar';
elseif ($type === 'secondary') $rank = Gamification::SEC_LEVEL_NAMES[$level] ?? 'Student';
elseif ($type === 'admin') $rank = 'Administrator';

$showSchool = (int)$personModel->privacy_show_school === 1;
$showStats = (int)$personModel->privacy_show_stats === 1;
$showDiscussions = (int)$personModel->privacy_show_discussions === 1;
$showBadges = (int)$personModel->privacy_show_badges === 1;
$showBattles = (int)$personModel->privacy_show_battles === 1;
$showStudies = (int)$personModel->privacy_show_studies === 1;
$showQuizzes = (int)$personModel->privacy_show_quizzes === 1;

// Quiz history in three numbers a classmate can actually compare against.
// Kept private means the answer is null, not a row of zeros: a zero reads like
// the person has never opened a quiz, which is not what they chose to say.
$quizStats = null;
if ($showQuizzes) {
    $quizStats = ['attempts' => 0, 'best' => null, 'average' => null, 'quizzes' => 0];
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS tries,
                   COUNT(DISTINCT quiz_id) AS quizzes,
                   MAX(ROUND((score / NULLIF(total,0)) * 100)) AS best,
                   ROUND(AVG((score / NULLIF(total,0)) * 100)) AS average
              FROM quiz_attempts WHERE user_id = ? AND total > 0
        ");
        $stmt->execute([$targetId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $quizStats = [
            'attempts' => (int)($row['tries'] ?? 0),
            'quizzes' => (int)($row['quizzes'] ?? 0),
            'best' => $row['best'] === null ? null : (int)$row['best'],
            'average' => $row['average'] === null ? null : (int)$row['average'],
        ];
    } catch (Throwable $e) {
        error_log('api user quiz stats: ' . $e->getMessage());
    }
}

$badges = [];
if ($showBadges) {
    try {
        $badges = $game->getUserBadges($targetId);
    } catch (Throwable $e) {
        error_log('api user badges: ' . $e->getMessage());
    }
}

$posts = [];
if ($showDiscussions) {
    try {
        $stmt = $pdo->prepare('SELECT id, title, created_at, views, score FROM discussions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
        $stmt->execute([$targetId]);
        $posts = array_map(static function ($d) {
            return ['id' => (int)$d['id'], 'title' => (string)$d['title'], 'created_at' => $d['created_at'], 'views' => (int)$d['views'], 'score' => (int)$d['score']];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        error_log('api user posts: ' . $e->getMessage());
    }
}

$battle = new Battle($pdo);
$record = $battle->getUserStats($targetId) ?: [];
$wins = (int)($record['wins'] ?? 0);
$losses = (int)($record['losses'] ?? 0);
$draws = (int)($record['draws'] ?? 0);
$played = $wins + $losses + $draws;

// Top subjects by how much this person has actually finished.
$subjects = null;
if ($showStudies) {
    $subjects = [];
    try {
        $stmt = $pdo->prepare("
            SELECT q.subject, COUNT(*) AS n
              FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id
             WHERE qa.user_id = ? AND q.subject IS NOT NULL AND q.subject != ''
             GROUP BY q.subject ORDER BY n DESC LIMIT 5
        ");
        $stmt->execute([$targetId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $subjects[] = ['subject' => (string)$r['subject'], 'count' => (int)$r['n']];
        }
    } catch (Throwable $e) {
        error_log('api user subjects: ' . $e->getMessage());
    }
}

$allowed = null;
try {
    require_once __DIR__ . '/../../models/Message.php';
    $allowed = (new Message($pdo))->canMessage($viewerId, $targetId);
} catch (Throwable $e) {
    error_log('api user can message: ' . $e->getMessage());
}

// Presence comes from the one helper that owns the rule, so a person who
// switched off their active status is Offline on the phone too.
$pState = presence_for_users([$targetId], $pdo)[$targetId] ?? ['online' => false, 'last_active' => null, 'share' => true];

api_out([
    'ok' => true,
    'is_me' => $targetId === $viewerId,
    'profile' => [
        'id' => (int)$personModel->id,
        'name' => (string)$personModel->name,
        'picture' => api_url($personModel->profile_picture),
        'user_type' => $type,
        'university' => $showSchool ? (string)($personModel->university ?? '') : null,
        'department' => $showSchool ? (string)($personModel->department ?? '') : null,
        'level_of_study' => $showSchool ? (string)($personModel->level ?? '') : null,
        'member_since' => $personModel->created_at,
        'online' => $pState['online'],
        'status_hidden' => !$pState['share'],
    ],
    'gamification' => $showStats ? [
        'points' => $points,
        'level' => $level,
        'rank' => $rank,
        'progress' => (int)($progress['progress'] ?? 0),
        'points_needed' => (int)($progress['points_needed'] ?? 0),
    ] : null,
    'quiz_stats' => $quizStats,
    'badges' => $badges,
    'battle' => $showBattles ? [
        'rating' => (int)($record['rating'] ?? 1000),
        'wins' => $wins,
        'losses' => $losses,
        'draws' => $draws,
        'played' => $played,
        'win_rate' => $played > 0 ? (int)round($wins / $played * 100) : 0,
        'streak' => (int)($record['current_streak'] ?? 0),
    ] : null,
    'subjects' => $subjects,
    'discussions' => $posts,
    // What each section's owner chose, so the app can draw a locked card for a
    // private one instead of guessing from a zero that means something else.
    'privacy' => [
        'school' => $showSchool,
        'stats' => $showStats,
        'discussions' => $showDiscussions,
        'badges' => $showBadges,
        'battles' => $showBattles,
        'studies' => $showStudies,
        'quizzes' => $showQuizzes,
    ],
    // canMessage() answers with 'allowed', not 'can' - reading the wrong key
    // left the Message button permanently greyed on every profile.
    'can_message' => $targetId !== $viewerId && !empty($allowed['allowed']),
    'message_reason' => $targetId !== $viewerId && empty($allowed['allowed']) ? (string)($allowed['reason'] ?? 'You cannot message this person.') : null,
    'message_reason_code' => $targetId !== $viewerId && empty($allowed['allowed']) ? message_gate_code($allowed['reason'] ?? '') : 'allowed',
    'viewer_points' => (int)($viewer['points'] ?? 0),
]);
