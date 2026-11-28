<?php
// process_battle_action.php - Handles Accepting or Declining a pending Battle
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Battle.php';
require_once __DIR__ . '/models/Notification.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "/login.php?return=" . rawurlencode(msb_return_path()));
    exit();
}

$userId = (int)$_SESSION['user_id'];

// Helper: Get user display name
function getBattleUserName($pdo, $id) {
    $s = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $s->execute([$id]);
    return $s->fetchColumn() ?: 'A user';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $battleId = isset($_POST['battle_id']) ? (int)$_POST['battle_id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($battleId <= 0 || !in_array($action, ['accept', 'decline', 'negotiate', 'accept_negotiation', 'decline_negotiation'])) {
        redirect_with_notice('battle_arena.php', 'Invalid action. Nothing was changed.', 'error');
    }

    $battleModel = new Battle($pdo);
    $notif = new Notification($pdo);

    // Pre-fetch battle details for notifications
    $battle = $battleModel->getBattle($battleId);

    if ($action === 'accept') {
        // Block acceptance if battle has a future start_time
        if (!empty($battle['start_time']) && strtotime($battle['start_time']) > time()) {
            $startDate = date('M j, Y g:i A', strtotime($battle['start_time']));
            redirect_with_notice('battle_arena.php', "This battle is scheduled to start on {$startDate}. You can accept it then!", 'error');
        }
        try {
            $success = $battleModel->acceptChallenge($battleId, $userId);
            if ($success && $battle) {
                $userName = getBattleUserName($pdo, $userId);
                // Notify the challenger that their challenge was accepted
                $notif->create(
                    (int)$battle['challenger_id'],
                    'battle_accepted',
                    "✅ Challenge Accepted!",
                    "{$userName} has accepted your battle challenge on \"{$battle['quiz_title']}\"! The arena is now open — may the best mind win!",
                    SITE_URL . "/battle_arena.php#battle-{$battleId}"
                );
                sendBattleEmail($pdo, (int)$battle['challenger_id'], "✅ Challenge Accepted!", "{$userName} has accepted your battle challenge on \"{$battle['quiz_title']}\"! The arena is now open — may the best mind win!", SITE_URL . "/battle_arena.php#battle-{$battleId}", 'accepted');
            }
            redirect_with_notice('battle_arena.php', $success ? "Challenge Accepted! Prepare for battle." : "Failed to accept challenge.", $success ? 'success' : 'error');
        } catch (Exception $e) {
            if ($e->getMessage() === 'insufficient_xp') {
                header("Location: battle_arena.php?modal=insufficient_xp_accept");
                exit();
            }
            redirect_with_notice('battle_arena.php', "Failed to accept challenge.", 'error');
        }
    } else if ($action === 'decline') {
        $reason = isset($_POST['decline_reason']) ? trim($_POST['decline_reason']) : 'No reason provided';
        $success = $battleModel->declineChallenge($battleId, $userId, $reason);
        if ($success && $battle) {
            $userName = getBattleUserName($pdo, $userId);
            $wager = (int)$battle['xp_wager'];
            $refundText = $wager > 0 ? " Your escrowed {$wager} XP has been refunded." : '';
            // Notify the challenger that their challenge was declined
            $notif->create(
                (int)$battle['challenger_id'],
                'battle_declined',
                "❌ Challenge Declined",
                "{$userName} declined your battle challenge on \"{$battle['quiz_title']}\". Reason: {$reason}.{$refundText}",
                SITE_URL . "/battle_arena.php#battle-{$battleId}"
            );
            sendBattleEmail($pdo, (int)$battle['challenger_id'], "❌ Challenge Declined", "{$userName} declined your battle challenge on \"{$battle['quiz_title']}\". Reason: {$reason}.{$refundText}", SITE_URL . "/battle_arena.php#battle-{$battleId}", 'declined');
        }
        redirect_with_notice('battle_arena.php', $success ? "Challenge Declined. Escrow refunded to challenger." : "Failed to decline challenge.", $success ? 'success' : 'error');
    } else if ($action === 'negotiate') {
        // Collect proposed changes into JSON
        $proposed = [
            'custom_timer_seconds' => isset($_POST['custom_timer_seconds']) ? (int)$_POST['custom_timer_seconds'] : null,
            'xp_wager' => isset($_POST['xp_wager']) ? (int)$_POST['xp_wager'] : null
        ];
        $success = $battleModel->proposeChange($battleId, $userId, json_encode($proposed));
        if ($success && $battle) {
            $userName = getBattleUserName($pdo, $userId);
            $newWager = $proposed['xp_wager'] !== null ? $proposed['xp_wager'] : (int)$battle['xp_wager'];
            $newTimer = $proposed['custom_timer_seconds'] !== null ? floor($proposed['custom_timer_seconds'] / 60) . ' min' : 'default';
            // Notify the challenger of the counter-offer
            $notif->create(
                (int)$battle['challenger_id'],
                'battle_negotiate',
                "🤝 Counter-Offer Received",
                "{$userName} has proposed new terms for the battle on \"{$battle['quiz_title']}\": Wager {$newWager} XP, Timer {$newTimer}. Review and respond on your profile!",
                SITE_URL . "/battle_arena.php#battle-{$battleId}"
            );
            sendBattleEmail($pdo, (int)$battle['challenger_id'], "🤝 Counter-Offer Received", "{$userName} has proposed new terms for the battle on \"{$battle['quiz_title']}\": Wager {$newWager} XP, Timer {$newTimer}. Review and respond on your profile!", SITE_URL . "/battle_arena.php#battle-{$battleId}", 'negotiate');
        }
        redirect_with_notice('battle_arena.php', $success ? "Negotiation Proposed! Awaiting Challenger's response." : "Failed to propose changes.", $success ? 'success' : 'error');
    } else if ($action === 'accept_negotiation') {
        try {
            $success = $battleModel->acceptNegotiation($battleId, $userId);
            if ($success && $battle) {
                // Notify the opponent that the counter-offer was accepted
                $notif->create(
                    (int)$battle['opponent_id'],
                    'battle_accepted',
                    "✅ Counter-Offer Accepted!",
                    "Your counter-offer for the battle on \"{$battle['quiz_title']}\" has been accepted! The arena is now open — Enter and show your strength!",
                    SITE_URL . "/battle_arena.php#battle-{$battleId}"
                );
                sendBattleEmail($pdo, (int)$battle['opponent_id'], "✅ Counter-Offer Accepted!", "Your counter-offer for the battle on \"{$battle['quiz_title']}\" has been accepted! The arena is now open — Enter and show your strength!", SITE_URL . "/battle_arena.php#battle-{$battleId}", 'accepted');
            }
            redirect_with_notice('battle_arena.php', $success ? "Counter-offer Accepted! The battle is on." : "Failed to accept counter-offer.", $success ? 'success' : 'error');
        } catch (Exception $e) {
            if (in_array($e->getMessage(), ['insufficient_xp', 'opponent_insufficient_xp'])) {
                header("Location: battle_arena.php?modal=insufficient_xp_accept");
                exit();
            }
            redirect_with_notice('battle_arena.php', "Failed to accept counter-offer.", 'error');
        }
    } else if ($action === 'decline_negotiation') {
        $success = $battleModel->declineNegotiation($battleId, $userId);
        if ($success && $battle) {
            $wager = (int)$battle['xp_wager'];
            $refundText = $wager > 0 ? " The escrowed {$wager} XP has been refunded." : '';
            // Notify the opponent that the counter-offer was rejected
            $notif->create(
                (int)$battle['opponent_id'],
                'battle_declined',
                "❌ Counter-Offer Rejected",
                "Your counter-offer for the battle on \"{$battle['quiz_title']}\" was rejected by the challenger. The battle has been cancelled.{$refundText}",
                SITE_URL . "/battle_arena.php#battle-{$battleId}"
            );
            sendBattleEmail($pdo, (int)$battle['opponent_id'], "❌ Counter-Offer Rejected", "Your counter-offer for the battle on \"{$battle['quiz_title']}\" was rejected by the challenger. The battle has been cancelled.{$refundText}", SITE_URL . "/battle_arena.php#battle-{$battleId}", 'declined');
        }
        redirect_with_notice('battle_arena.php', $success ? "Counter-offer Rejected. Challenge was cancelled and escrow refunded." : "Failed to reject counter-offer.", $success ? 'success' : 'error');
    }
}

header("Location: profile.php");
exit();
