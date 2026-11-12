<?php
// profile_public.php - Public User Profile Hub
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Gamification.php';
require_once __DIR__ . '/models/Discussion.php';

$target_user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($target_user_id <= 0) {
    die("Invalid User ID");
}

$user = new User($pdo);
if (!$user->getById($target_user_id)) {
    die("User not found or has been deactivated.");
}

// Fetch Gamification Stats
$gamification = new Gamification($pdo);

$numericLevel = Gamification::getLevel((int)$user->points);
$totalXP = (int)$user->points;

$rankName = 'Beginner';
if ($user->user_type === 'university') {
    $rankName = Gamification::UNI_LEVEL_NAMES[$numericLevel] ?? 'Scholar';
} elseif ($user->user_type === 'secondary') {
    $rankName = Gamification::SEC_LEVEL_NAMES[$numericLevel] ?? 'Student';
} elseif ($user->user_type === 'admin') {
    $rankName = 'Administrator';
}

// Format the Joining Date to Month and Year only
$joinDate = new DateTime($user->created_at);
$memberSince = $joinDate->format('F Y');

// Prepare data arrays for the View to consume if Privacy allows
$recentDiscussions = [];
// Answer fetching will be skipped since the privacy rule was removed

if ($user->privacy_show_discussions) {
    $discussionModel = new Discussion($pdo);
    // Fetch top 5 recent discussions authored by this user
    $recentResult = $discussionModel->getDiscussions($target_user_id, 0, 'newest', 'All', '', ''); 
    
    // We filter the generic feed to only those strictly authored by the target user 
    // (since getDiscussions handles the feed, we can just grab them filtering by ID here, 
    // or build a tiny optimized query)
    $stmt = $pdo->prepare("SELECT id, title, created_at, views, upvotes, downvotes, score FROM discussions WHERE user_id = :uid ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([':uid' => $target_user_id]);
    $recentDiscussions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Badges / Certificates if Privacy allows
$earnedBadges = [];
if ($user->privacy_show_badges) {
    // We can pull completed quizzes that have certificates
    $stmt = $pdo->prepare("
        SELECT qa.id, q.title, ROUND((qa.score / qa.total) * 100) AS score_percentage, qa.completed_at AS created_at 
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        WHERE qa.user_id = :uid AND qa.total > 0 AND ROUND((qa.score / qa.total) * 100) >= 50
        ORDER BY qa.completed_at DESC LIMIT 6
    ");
    $stmt->execute([':uid' => $target_user_id]);
    $earnedBadges = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Battle Stats
require_once __DIR__ . '/models/Battle.php';
$battleModel = new Battle($pdo);
$battleStats = $battleModel->getUserStats($target_user_id);
if (!$battleStats) {
    // defaults
    $battleStats = ['rating' => 1000, 'wins' => 0, 'losses' => 0, 'draws' => 0, 'current_streak' => 0, 'highest_streak' => 0];
}

$totalBattles = $battleStats['wins'] + $battleStats['losses'] + $battleStats['draws'];
$winRate = $totalBattles > 0 ? round(($battleStats['wins'] / $totalBattles) * 100) : 0;

$battleHistory = $battleModel->getUserBattles($target_user_id, 10);

// Fetch available subjects/quizzes for the Challenge Modal
$stmt = $pdo->prepare("SELECT id, title FROM quizzes WHERE mode = 'normal' AND is_paid = 0 ORDER BY title ASC");
$stmt->execute();
$availableQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$loggedInUserXP = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $loggedInUserXP = (int)$stmt->fetchColumn();
}

// Include the beautiful frontend view
include __DIR__ . '/views/user/profile_public.php';
