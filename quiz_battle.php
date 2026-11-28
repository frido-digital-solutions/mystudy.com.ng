<?php
// quiz_battle.php - The Secure Wrapper Entry Point for Battles
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Battle.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "/login.php?return=" . rawurlencode(msb_return_path()));
    exit();
}

$userId = (int)$_SESSION['user_id'];
$battleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($battleId <= 0) {
    redirect_with_notice('profile.php', 'Invalid Battle ID', 'error');
}

$battleModel = new Battle($pdo);
$battle = $battleModel->getBattle($battleId);

if (!$battle) {
    redirect_with_notice('profile.php', 'Battle Not Found', 'error');
}

// Ensure the battle is actively playable
if ($battle['status'] === 'pending' || $battle['status'] === 'declined' || $battle['status'] === 'completed') {
    redirect_with_notice('profile.php', 'This battle is not currently active', 'error');
}

// Verify User is a participant and hasn't already played
$isChallenger = ((int)$battle['challenger_id'] === $userId);
$isOpponent = ((int)$battle['opponent_id'] === $userId);

if (!$isChallenger && !$isOpponent) {
    header("Location: index.php"); // Not their battle
    exit();
}

if ($isChallenger && $battle['challenger_score'] !== null) {
    redirect_with_notice('profile.php', 'You have already completed your turn.', 'error');
}

if ($isOpponent && $battle['opponent_score'] !== null) {
    redirect_with_notice('profile.php', 'You have already completed your turn.', 'error');
}

// ----------------------------------------------------
// BATTLE WRAPPER LOGIC - The Magic
// ----------------------------------------------------
// We inject the active battle ID into the user's session.
// Then we physically redirect them to the true quiz engine.
// The quiz engine is entirely unaware of the battle while it runs.
$_SESSION['active_battle_id'] = $battleId;

// Start the standalone Battle Arena UI
header("Location: quiz_battle_play.php");
exit();
