<?php
/**
 * Home feed for the app. Same models, same rows and the same cache rules as the
 * website homepage — the payload is only re-shaped for a phone screen.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_tutorial.php';
require_once __DIR__ . '/_blog.php';
require_once __DIR__ . '/../../models/Tutorial.php';
require_once __DIR__ . '/../../models/Post.php';
require_once __DIR__ . '/../../models/Quiz.php';
require_once __DIR__ . '/../../models/Gamification.php';
require_once __DIR__ . '/_challenge.php';

$user = api_auth();
$userId = (int)$user['id'];

$tutorialModel = new Tutorial($pdo);
$postModel = new Post($pdo);
$quizModel = new Quiz($pdo);

// Reuse the website's own homepage cache instead of duplicating it, so web and
// app cannot drift and a refresh costs the same either way.
$cacheFile = __DIR__ . '/../../cache/home_app_v2.json';
$cacheTtl = 300;
$summary = api_home_summary($pdo, $userId);
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    $cached = json_decode((string)file_get_contents($cacheFile), true);
    if (is_array($cached) && isset($cached['sections']['posts'])) {
        // The blob is shared by every account, so anything that depends on WHO is
        // asking or on the current second is added after the cache, never inside it.
        $cached['sections']['challenges'] = api_home_challenge_live($pdo, $userId, $cached['sections']['challenges'] ?? []);
        api_out(['ok' => true, 'cached' => true, 'now' => time(), 'sections' => $cached['sections'],
                 'summary' => $summary, 'user' => api_user_payload($user, $pdo)]);
    }
}

$latestPosts = $postModel->list(6, 0);
$featuredTutorials = $tutorialModel->searchTutorials(null, null, null, 4, 0);
$latestChallenges = $quizModel->listChallenges(null, 4);
$topStudents = api_home_top_students($pdo);

$challengeIds = array_column($latestChallenges, 'id');
$challengeMaterials = api_home_challenge_materials($pdo, $challengeIds);
$challengeQuestions = api_home_challenge_questions($pdo, $challengeIds);

$postAuthors = api_blog_authors($pdo, $latestPosts);

$sections = [
    // The blog rail carries a picture, a writer and a reading time, because on a
    // phone an article is chosen by its cover before it is chosen by its title.
    'posts' => array_map(function ($p) use ($pdo, $postAuthors) {
        return api_blog_card_payload($pdo, $p, $postAuthors[(int)($p['author_id'] ?? 0)] ?? null);
    }, $latestPosts),

    'tutorials' => array_map(function ($t) {
        return api_tutorial_payload($t);
    }, $featuredTutorials),

    'challenges' => api_home_challenge_map($latestChallenges, $challengeMaterials, $challengeQuestions),

    // The same ORDER BY points as the leaderboard page, so the ten names on the
    // front page are the ten names at the top of the board the button opens.
    'top_students' => $topStudents,
];

$dir = dirname($cacheFile);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
file_put_contents($cacheFile, json_encode(['sections' => $sections]), LOCK_EX);

$sections['challenges'] = api_home_challenge_live($pdo, $userId, $sections['challenges']);

api_out(['ok' => true, 'now' => time(), 'sections' => $sections, 'summary' => $summary, 'user' => api_user_payload($user, $pdo)]);

/**
 * The ten highest XP accounts, which is the top of the leaderboard page cut to
 * what fits on a phone screen. The number is the site's own points column, so the
 * front page, the board and the profile can never tell three different stories.
 *
 * A student who turned "show my level" off keeps their place in line but not
 * their number on screen, exactly as on the board.
 */
function api_home_top_students(PDO $pdo): array {
    $out = [];
    try {
        $me = (int)($_SESSION['user_id'] ?? 0);
        $rows = $pdo->query("SELECT id, name, profile_picture, points, user_type, show_level_on_users_page
                               FROM users
                              WHERE status = 'active' AND points > 0
                              ORDER BY points DESC, id ASC
                              LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $i => $r) {
            $out[] = [
                'rank' => $i + 1,
                'id' => (int)$r['id'],
                'name' => (string)$r['name'],
                'picture' => api_url($r['profile_picture'] ?? null),
                'points' => (int)$r['points'],
                'user_type' => $r['user_type'],
                'hidden' => (int)($r['show_level_on_users_page'] ?? 1) === 0,
                'me' => (int)$r['id'] === $me,
            ];
        }
    } catch (Throwable $e) {
        error_log('api home top students: ' . $e->getMessage());
    }
    return $out;
}

/**
 * The figures the app's hero and stat tiles show. Each one is the same query the
 * website dashboard runs, so the phone can never report different progress than
 * the web for the same account.
 */
function api_home_summary(PDO $pdo, int $userId): array {
    $gamification = new Gamification($pdo);
    $stats = $gamification->getUserStats($userId);
    $progress = $stats['level_progress'] ?? [];

    // The whole badge catalogue, flagged with what this account has. The app
    // shows the unearned ones too, so "what is left" is not a guess.
    $earned = [];
    foreach ($stats['badges'] as $b) {
        $earned[$b['name']] = true;
    }
    $catalogue = [];
    // Where the student has got to. The badges are in the order they are won,
    // so the first one that is not earned yet is the one in front of them -
    // the next thing to aim for. The phone needs to know that, because
    // "the badges you have" and "the badge you are working towards" are two
    // different things and look different on purpose.
    $nextKey = null;
    foreach (Gamification::BADGES as $slug => $meta) {
        if (!isset($earned[$slug]) && $nextKey === null) $nextKey = $slug;
    }
    $rank = 0;
    foreach (Gamification::BADGES as $slug => $meta) {
        $rank++;
        $catalogue[] = [
            'key' => $slug,
            'icon' => $meta[0],
            'label' => $meta[1],
            'color' => $meta[2],
            'earned' => isset($earned[$slug]),
            // 'earned' - in the bag. 'next' - the one in front of them.
            // 'locked' - still to come.
            'state' => isset($earned[$slug]) ? 'earned' : ($slug === $nextKey ? 'next' : 'locked'),
            'rank' => $rank,
        ];
    }

    // The rank titles the website prints next to a level number.
    $ranks = [1 => 'Novice', 2 => 'Apprentice', 3 => 'Scholar', 4 => 'Guardian', 5 => 'Prefect',
              6 => 'Elite Student', 7 => 'Commander', 8 => 'Master', 9 => 'Grandmaster', 10 => 'Legend'];

    $quizzesDone = 0;
    $bestScore = 0;
    $materialsCount = 0;
    $certificates = 0;
    $materialsOpened = 0;
    $materialsRead = 0;
    try {
        $s = $pdo->prepare('SELECT COUNT(DISTINCT quiz_id) FROM quiz_attempts WHERE user_id = ?');
        $s->execute([$userId]);
        $quizzesDone = (int)$s->fetchColumn();

        $s = $pdo->prepare('SELECT MAX(ROUND(score / total * 100, 0)) FROM quiz_attempts WHERE user_id = ? AND total > 0');
        $s->execute([$userId]);
        $bestScore = (int)($s->fetchColumn() ?? 0);

        $materialsCount = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'approved'")->fetchColumn();

        // A certificate is a finished paper: every attempt is one certificate
        // the student can open and print, so this is the number of papers
        // behind them, not the number of different quizzes.
        $s = $pdo->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ?');
        $s->execute([$userId]);
        $certificates = (int)$s->fetchColumn();

        // The materials this student has opened, and the ones they have read
        // to the end. Read means at least half of it, which is the same line
        // the website draws.
        $s = $pdo->prepare('SELECT COUNT(DISTINCT material_id) FROM material_progress WHERE user_id = ?');
        $s->execute([$userId]);
        $materialsOpened = (int)$s->fetchColumn();

        $s = $pdo->prepare('SELECT COUNT(DISTINCT material_id) FROM material_progress WHERE user_id = ? AND percent >= 50');
        $s->execute([$userId]);
        $materialsRead = (int)$s->fetchColumn();
    } catch (Throwable $e) {
        error_log('api home summary: ' . $e->getMessage());
    }

    $weekly = api_home_week($pdo, $userId);

    return [
        'points' => (int)$stats['points'],
        'level' => (int)$stats['level'],
        'rank' => $ranks[(int)$stats['level']] ?? 'Scholar',
        'level_progress' => (int)($progress['progress'] ?? 0),
        'xp_to_next_level' => (int)($progress['points_needed'] ?? 0),
        'next_level' => (int)$stats['level'] + 1,
        'streak' => (int)$stats['streak'],
        'longest_streak' => (int)$stats['longest_streak'],
        'badges' => count($stats['badges']),
        'badges_total' => count(Gamification::BADGES),
        'badges_catalogue' => $catalogue,
        'badge_list' => array_map(static function ($b) {
            return ['name' => $b['name'], 'icon' => $b['icon'], 'label' => $b['label'], 'color' => $b['color']];
        }, array_slice($stats['badges'], 0, 6)),
        'quizzes_completed' => $quizzesDone,
        'best_score' => $bestScore,
        'materials_count' => $materialsCount,
        'materials_opened' => $materialsOpened,
        'materials_read' => $materialsRead,
        'certificates' => $certificates,
        'weekly' => $weekly,
        'motivation' => $gamification->getMotivationalMessage($userId),
    ];
}

/**
 * This week's real activity, Monday to Sunday. The XP figure re-runs Gamification
 * ::awardQuizPoints()'s own formula over the week's attempts, because the site
 * keeps no separate XP ledger - so the week's total can never disagree with the
 * points the account actually gained.
 */
function api_home_week(PDO $pdo, int $userId): array {
    $monday = date('Y-m-d', strtotime('monday this week'));
    $byDay = [];
    $xpWeek = 0;
    $quizWeek = 0;

    try {
        $stmt = $pdo->prepare("SELECT DATE(qa.completed_at) AS d, qa.score, qa.total,
                                      CASE WHEN q.mode = 'challenge' OR q.is_paid = 1 THEN 1 ELSE 0 END AS is_challenge
                                 FROM quiz_attempts qa
                                 LEFT JOIN quizzes q ON q.id = qa.quiz_id
                                WHERE qa.user_id = :u AND qa.completed_at >= :from
                                ORDER BY qa.id ASC");
        $stmt->execute([':u' => $userId, ':from' => $monday . ' 00:00:00']);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $score = (int)$row['score'];
            $total = (int)$row['total'];
            $percentage = $total > 0 ? ($score / $total) * 100 : 0;
            $points = 10 + (int)round($percentage / 10) + ((int)$row['is_challenge'] === 1 ? 15 : 0)
                                 + (($total > 0 && $score === $total) ? 20 : 0);

            $day = (string)$row['d'];
            $byDay[$day] = ($byDay[$day] ?? 0) + $points;
            $xpWeek += $points;
            $quizWeek++;
        }
    } catch (Throwable $e) {
        error_log('api home week: ' . $e->getMessage());
    }

    $today = date('Y-m-d');
    $letters = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime($monday . ' +' . $i . ' day'));
        $days[] = [
            'date' => $date,
            'letter' => $letters[$i],
            'xp' => $byDay[$date] ?? 0,
            'future' => $date > $today,
            'today' => $date === $today,
        ];
    }

    return [
        'days' => $days,
        'xp' => $xpWeek,
        'quizzes' => $quizWeek,
        'active_days' => count($byDay),
        'week_start' => $monday,
    ];
}
