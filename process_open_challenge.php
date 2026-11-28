<?php
// process_open_challenge.php - Handles accepting or negotiating an open battle invite
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Battle.php';
require_once __DIR__ . '/models/Notification.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "/login.php?return=" . rawurlencode(msb_return_path()));
    exit();
}

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $battleId = isset($_POST['battle_id']) ? (int)$_POST['battle_id'] : 0;
    $inviteCode = isset($_POST['invite_code']) ? trim($_POST['invite_code']) : '';
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($battleId <= 0 || empty($inviteCode) || !in_array($action, ['accept', 'negotiate'])) {
        header("Location: battle_invite.php?code=" . urlencode($inviteCode) . "&error=Invalid+Action");
        exit();
    }

    $battleModel = new Battle($pdo);
    $notif = new Notification($pdo);

    // Verify battle exists and matches code
    $battle = $battleModel->getOpenChallenge($inviteCode);
    if (!$battle || (int)$battle['id'] !== $battleId) {
        header("Location: battle_invite.php?code=" . urlencode($inviteCode) . "&error=Invalid+Battle");
        exit();
    }

    // Get user's name for notifications
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userName = $stmt->fetchColumn() ?: 'A user';

    if ($action === 'accept') {
        try {
            $success = $battleModel->acceptOpenChallenge($battleId, $userId);
            if ($success) {
                // Notify the battle creator
                $notif->create(
                    (int)$battle['challenger_id'],
                    'battle_accepted',
                    "⚔️ Open Challenge Accepted!",
                    "{$userName} accepted your open battle invite on \"{$battle['quiz_title']}\"! The arena is now open.",
                    SITE_URL . "/battle_arena.php#battle-{$success}"
                );
                sendBattleEmail($pdo, (int)$battle['challenger_id'], "⚔️ Open Challenge Accepted!", "{$userName} accepted your open battle invite on \"{$battle['quiz_title']}\"! The arena is now open.", SITE_URL . "/battle_arena.php#battle-{$success}", 'accepted');
                redirect_with_notice('battle_arena.php#battle-' . $success, 'Challenge accepted! Head to the arena.', 'success');
            }
            header("Location: battle_invite.php?code=" . urlencode($inviteCode) . "&error=Failed+to+accept");
            exit();
        } catch (Exception $e) {
            if ($e->getMessage() === 'insufficient_xp') {
                header("Location: battle_invite.php?code=" . urlencode($inviteCode) . "&error=" . urlencode("Insufficient XP. You need " . $battle['xp_wager'] . " XP to accept this wager."));
                exit();
            }
            header("Location: battle_invite.php?code=" . urlencode($inviteCode) . "&error=Failed+to+accept");
            exit();
        }
    } else if ($action === 'negotiate') {
        $proposed = [
            'custom_timer_seconds' => isset($_POST['custom_timer_seconds']) ? (int)$_POST['custom_timer_seconds'] : null,
            'xp_wager' => isset($_POST['xp_wager']) ? (int)$_POST['xp_wager'] : null
        ];
        $success = $battleModel->proposeOpenChange($battleId, $userId, json_encode($proposed));
        if ($success) {
            $newWager = $proposed['xp_wager'] !== null ? $proposed['xp_wager'] : (int)$battle['xp_wager'];
            $newTimer = $proposed['custom_timer_seconds'] !== null ? floor($proposed['custom_timer_seconds'] / 60) . ' min' : 'default';
            // Notify the battle creator
            $notif->create(
                (int)$battle['challenger_id'],
                'battle_negotiate',
                "🤝 Counter-Offer on Open Battle",
                "{$userName} wants to battle you on \"{$battle['quiz_title']}\" but proposes: Wager {$newWager} XP, Timer {$newTimer}. Review on your Battle Arena!",
                SITE_URL . "/battle_arena.php#battle-{$battleId}"
            );
            sendBattleEmail($pdo, (int)$battle['challenger_id'], "🤝 Counter-Offer on Open Battle", "{$userName} wants to battle you on \"{$battle['quiz_title']}\" but proposes: Wager {$newWager} XP, Timer {$newTimer}. Review on your Battle Arena!", SITE_URL . "/battle_arena.php#battle-{$battleId}", 'negotiate');
            redirect_with_notice('battle_arena.php', "Counter-offer sent! Waiting for the creator's response.", 'success');
        }
        header("Location: battle_invite.php?code=" . urlencode($inviteCode) . "&error=Failed+to+negotiate");
        exit();
    }
}

header("Location: index.php");
exit();
