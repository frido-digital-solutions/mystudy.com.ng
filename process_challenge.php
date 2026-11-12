<?php
// process_challenge.php - Handles Challenge Modal Submission
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Battle.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$challengerId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opponentId = isset($_POST['opponent_id']) ? (int)$_POST['opponent_id'] : 0;
    $quizId = isset($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : 0;
    $matchType = isset($_POST['match_type']) && $_POST['match_type'] === 'ranked' ? 'ranked' : 'friendly';
    $xpWager = isset($_POST['xp_wager']) ? (int)$_POST['xp_wager'] : 0;
    
    // Capture Timer and Start Date if provided
    $customTimer = isset($_POST['custom_timer_seconds']) && (int)$_POST['custom_timer_seconds'] > 0 ? (int)$_POST['custom_timer_seconds'] : null;
    $startTime = !empty($_POST['start_time']) ? date('Y-m-d H:i:s', strtotime($_POST['start_time'])) : null;

    // Basic Validation
    if ($opponentId <= 0 || $quizId <= 0 || $challengerId === $opponentId) {
        redirect_with_notice('profile_public.php?id=' . $opponentId, 'Invalid challenge data. Nothing was sent.', 'error');
    }

    // Valid wager bounds: 0 for friendly, or between the limits set on the admin Settings page.
    if ($xpWager > 0 && ($xpWager < site_battle_wager_min() || $xpWager > site_battle_wager_max())) {
        redirect_with_notice('profile_public.php?id=' . $opponentId, 'Wager must be between ' . site_battle_wager_min() . ' and ' . site_battle_wager_max() . ' XP', 'error');
    }

    $battleModel = new Battle($pdo);
    $battleId = $battleModel->createChallenge($challengerId, $opponentId, $quizId, $matchType, $xpWager, $customTimer, $startTime);

    if ($battleId === false) {
        // Most likely means Challenger doesn't have enough XP for their wager
        header("Location: profile_public.php?id=" . $opponentId . "&modal=insufficient_xp&wager=" . $xpWager);
        exit();
    }

    // --- Send Notification to Opponent ---
    require_once __DIR__ . '/models/Notification.php';
    $notif = new Notification($pdo);

    // Fetch challenger name and quiz title for the notification
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$challengerId]);
    $challengerName = $stmt->fetchColumn() ?: 'Someone';

    $stmt = $pdo->prepare("SELECT title FROM quizzes WHERE id = ?");
    $stmt->execute([$quizId]);
    $quizTitle = $stmt->fetchColumn() ?: 'a quiz';

    $wagerText = $xpWager > 0 ? " with a wager of {$xpWager} XP" : '';
    $typeText = $matchType === 'ranked' ? '⚡ Ranked' : '🤝 Friendly';

    $notif->create(
        $opponentId,
        'battle_challenge',
        "⚔️ New Battle Challenge!",
        "{$challengerName} has challenged you to a {$typeText} battle on \"{$quizTitle}\"{$wagerText}. Head to your profile to accept or decline!",
        SITE_URL . "/profile.php"
    );

    // Send battle email notification
    sendBattleEmail($pdo, $opponentId, "⚔️ New Battle Challenge!", "{$challengerName} has challenged you to a {$typeText} battle on \"{$quizTitle}\"{$wagerText}. Head to your profile to accept or decline!", SITE_URL . "/profile.php", 'challenge');

    redirect_with_notice('profile_public.php?id=' . $opponentId, 'Challenge Sent! Waiting for your opponent to respond.', 'success');
}

header("Location: index.php");
exit();
