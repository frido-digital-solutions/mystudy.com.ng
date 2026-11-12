<?php
// battle_arena.php - Standalone Battle Arena Hub Controller
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Battle.php';

require_login();

$userId = (int)$_SESSION['user_id'];
$user = new User($pdo);
$user->getById($userId);

$battleModel = new Battle($pdo);

// Fetch all battle data
$pendingChallenges = $battleModel->getPendingChallenges($userId);
$recentBattles = $battleModel->getUserBattles($userId, 20);
$openChallenges = $battleModel->getUserOpenChallenges($userId);
$battleStats = $battleModel->getUserStats($userId);
if (!$battleStats) {
    $battleStats = ['rating' => 1000, 'wins' => 0, 'losses' => 0, 'draws' => 0, 'current_streak' => 0, 'highest_streak' => 0];
}

$totalBattles = $battleStats['wins'] + $battleStats['losses'] + $battleStats['draws'];
$winRate = $totalBattles > 0 ? round(($battleStats['wins'] / $totalBattles) * 100) : 0;

// Fetch available quizzes for the "Create Open Battle" form
$stmt = $pdo->prepare("SELECT id, title FROM quizzes WHERE mode = 'normal' AND is_paid = 0 ORDER BY title ASC");
$stmt->execute();
$availableQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle creating an open challenge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_open_battle'])) {
    $quizId = (int)$_POST['quiz_id'];
    $matchType = isset($_POST['match_type']) && $_POST['match_type'] === 'ranked' ? 'ranked' : 'friendly';
    $xpWager = isset($_POST['xp_wager']) ? (int)$_POST['xp_wager'] : 0;
    $customTimer = isset($_POST['custom_timer_seconds']) && (int)$_POST['custom_timer_seconds'] > 0 ? (int)$_POST['custom_timer_seconds'] : null;
    $startTime = !empty($_POST['start_time']) ? date('Y-m-d H:i:s', strtotime($_POST['start_time'])) : null;
    $isPublic = isset($_POST['list_on_hub']) && $_POST['list_on_hub'] == '1';
    $maxParticipants = isset($_POST['max_participants']) ? max(1, min(10, (int)$_POST['max_participants'])) : 1;

    // Compute expires_at: if start_time is set, add duration_hours to it; otherwise add to now
    $expiresAt = null;
    $durationHours = isset($_POST['duration_hours']) && (int)$_POST['duration_hours'] > 0 ? (int)$_POST['duration_hours'] : 0;
    if ($durationHours > 0) {
        $baseTime = $startTime ? strtotime($startTime) : time();
        $expiresAt = date('Y-m-d H:i:s', $baseTime + ($durationHours * 3600));
    }

    if ($xpWager > 0 && ($xpWager < site_battle_wager_min() || $xpWager > site_battle_wager_max())) {
        redirect_with_notice('battle_arena.php', 'Wager must be between ' . site_battle_wager_min() . ' and ' . site_battle_wager_max() . ' XP', 'error');
    }

    $inviteCode = $battleModel->createOpenChallenge($userId, $quizId, $matchType, $xpWager, $customTimer, $startTime, $isPublic, $expiresAt, $maxParticipants);

    if ($inviteCode === false) {
        header("Location: battle_arena.php?modal=insufficient_xp_accept");
        exit();
    }

    $toastMsg = "Open battle created! Check your Open Invites tab to copy the link.";
    if ($isPublic) $toastMsg .= " It's live on the Battle Hub!";
    redirect_with_notice('battle_arena.php', $toastMsg, 'success');
}

$page_title = "Battle Arena";
include __DIR__ . '/views/user/battle_arena.php';
