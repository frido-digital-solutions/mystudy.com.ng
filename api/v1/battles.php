<?php
/**
 * Battles for the app. Every read comes from the Battle model the website's
 * battle_hub.php already uses, and every write goes through the same
 * createChallenge() / acceptChallenge() calls process_battle_action.php makes,
 * so a challenge listed here is a challenge that really exists and the wager
 * rules (admin minimum, admin maximum, escrow) cannot be argued with from a
 * phone. The actions are the website's five, plus joining an invite link, plus
 * taking back your own - unsending a challenge nobody answered, editing or
 * deleting an open invite nobody has joined - and each one tells the other side
 * in the same words and by the same channels (in-app notification + email) the
 * website uses.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Battle.php';
require_once __DIR__ . '/../../models/Notification.php';

$user = api_auth();
$userId = (int)$user['id'];

$battleModel = new Battle($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    api_require_post();
    battle_post($battleModel, $pdo, $user);
}

/**
 * The rows the tabs need, shaped once so a challenge in "mine" and the same
 * challenge in "open" cannot arrive with different keys. Everything the website
 * badge machine reads - who is on the row, whether the viewer has struck, what
 * a counter-offer contains - travels with it, so the phone paints the same
 * badge without guessing.
 */
$shape = function ($b) use ($userId) {
    $challenger = (int)($b['challenger_id'] ?? 0);
    $opponent = !empty($b['opponent_id']) ? (int)$b['opponent_id'] : 0;
    $mine = $challenger === $userId;
    $iAm = $mine ? 'challenger' : ($opponent === $userId ? 'opponent' : null);
    $myScore = $mine ? ($b['challenger_score'] ?? null) : ($b['opponent_score'] ?? null);
    $proposed = json_decode((string)($b['proposed_changes'] ?? ''), true) ?: [];
    $start = !empty($b['start_time']) ? strtotime((string)$b['start_time']) : false;
    $expires = !empty($b['expires_at']) ? strtotime((string)$b['expires_at']) : false;

    return [
        'id' => (int)$b['id'],
        'quiz_id' => (int)($b['quiz_id'] ?? 0),
        'quiz_title' => $b['quiz_title'] ?? null,
        'status' => $b['status'] ?? null,
        'match_type' => $b['match_type'] ?? 'friendly',
        'xp_wager' => (int)($b['xp_wager'] ?? 0),
        'timer_seconds' => isset($b['custom_timer_seconds']) && $b['custom_timer_seconds'] !== null
            ? (int)$b['custom_timer_seconds'] : null,
        'winner_id' => !empty($b['winner_id']) ? (int)$b['winner_id'] : null,
        'decline_reason' => !empty($b['decline_reason']) ? (string)$b['decline_reason'] : null,
        'invite_code' => $b['invite_code'] ?? null,
        'invite_url' => !empty($b['invite_code'])
            ? SITE_URL . '/battle_invite.php?code=' . $b['invite_code'] : null,
        'is_public' => (int)($b['is_public'] ?? 0) === 1,
        'accepted_count' => (int)($b['accepted_count'] ?? 0),
        'max_participants' => (int)($b['max_participants'] ?? 1),
        'start_ts' => $start === false ? null : $start,
        'start_label' => $start === false ? null : date('M j, g:i A', $start),
        'starts_later' => $start !== false && $start > time(),
        'ends_ts' => $expires === false ? null : $expires,
        'ends_label' => $expires === false ? null : date('M j, g:i A', $expires),
        'created_ts' => !empty($b['created_at']) ? strtotime((string)$b['created_at']) : null,
        // Whose seat this row is, and whether that seat has already fired.
        'i_am' => $iAm,
        'seat_taken' => $opponent > 0,
        'i_have_played' => $myScore !== null,
        'proposed' => [
            'timer_seconds' => isset($proposed['custom_timer_seconds']) && $proposed['custom_timer_seconds'] !== null
                ? (int)$proposed['custom_timer_seconds'] : null,
            'xp_wager' => isset($proposed['xp_wager']) && $proposed['xp_wager'] !== null
                ? (int)$proposed['xp_wager'] : null,
        ],
        // Whoever the viewer would be facing, whichever side of the row they sit on.
        'other_id' => $mine ? $opponent : $challenger,
        'other_name' => $mine ? ($b['opponent_name'] ?? null) : ($b['challenger_name'] ?? null),
        'other_picture' => api_url($mine ? ($b['opponent_avatar'] ?? null) : ($b['challenger_avatar'] ?? null)),
        'challenger_id' => $challenger,
        'challenger_name' => $b['challenger_name'] ?? null,
        'challenger_rating' => isset($b['challenger_rating']) ? (int)$b['challenger_rating'] : null,
    ];
};

// Two reads exist for one reason: a student must never stare at a spinner while
// a decision sheet builds itself.
//   action=details  everything one challenge is, for the sheet that opens it
//   action=form     only what starting a challenge asks for - the quiz pool,
//                   the admin wager ceiling and the XP this account holds
if (strtolower((string)($_GET['action'] ?? '')) === 'details') {
    battle_details($battleModel, $pdo, $userId, (int)($_GET['id'] ?? 0), $shape);
}
if (strtolower((string)($_GET['action'] ?? '')) === 'form') {
    api_out([
        'ok' => true,
        'me' => battle_points($pdo, $userId),
        'limits' => ['wager_min' => site_battle_wager_min(), 'wager_max' => site_battle_wager_max()],
        'quiz_options' => battle_quiz_options($pdo),
    ]);
}

$mine = $battleModel->getUserBattles($userId, 20);
$pending = $battleModel->getPendingChallenges($userId);
$open = $battleModel->getPublicOpenChallenges(20);
$stats = $battleModel->getUserStats($userId) ?: [];

api_out([
    'ok' => true,
    'me' => [
        'rating' => (int)($stats['rating'] ?? 1000),
        'wins' => (int)($stats['wins'] ?? 0),
        'losses' => (int)($stats['losses'] ?? 0),
        'draws' => (int)($stats['draws'] ?? 0),
        'streak' => (int)($stats['current_streak'] ?? 0),
        'points' => (int)($user['points'] ?? 0),
    ],
    'limits' => ['wager_min' => site_battle_wager_min(), 'wager_max' => site_battle_wager_max()],
    'mine' => array_map($shape, $mine),
    'pending' => array_map($shape, $pending),
    'open' => array_map($shape, $open),
    // The same pool battle_arena.php offers: free, single-sitting quizzes only.
    'quiz_options' => battle_quiz_options($pdo),
    // The fixed list the website's decline dialog offers, so a reason sent from
    // a phone reads the same as one sent from a browser.
    'decline_reasons' => [
        'Not comfortable with the scheduled time.',
        'The XP wager is too high.',
        "I don't play Ranked matches.",
        'Not interested right now.',
        'Other',
    ],
    'timer_options' => [
        ['seconds' => 0, 'label' => 'Default'],
        ['seconds' => 300, 'label' => '5 Min'],
        ['seconds' => 600, 'label' => '10 Min'],
        ['seconds' => 1800, 'label' => '30 Min'],
    ],
]);

/**
 * The lifecycle process_battle_action.php and process_open_challenge.php run for
 * a student on the web, and nothing beyond it. A successful write answers with
 * the caller's fresh record so the app can repaint without a second round trip.
 */
function battle_post(Battle $battleModel, PDO $pdo, array $user): void {
    $userId = (int)$user['id'];
    $name = $user['name'] ?: 'A user';
    $in = api_input();
    $action = api_field($in, 'action');
    $battleId = (int)(api_field($in, 'battle_id', '') ?: api_field($in, 'id', ''));

    if ($action === 'accept') {
        battle_accept($battleModel, battle_row($battleModel, $battleId), $userId);
    }

    if ($action === 'decline') {
        $battle = battle_row($battleModel, $battleId);
        // process_battle_action.php reads decline_reason and always stores a
        // reason, so a bare decline still tells the challenger something.
        $reason = api_field($in, 'decline_reason') ?: (api_field($in, 'reason') ?: 'No reason provided');
        if (!$battleModel->declineChallenge($battleId, $userId, $reason)) {
            api_err('cannot_decline', 'That challenge is no longer waiting for you.', 409);
        }
        battle_told($pdo, (int)$battle['challenger_id'], 'battle_declined', '❌ Challenge Declined',
            "{$name} declined your battle challenge on \"{$battle['quiz_title']}\". Reason: {$reason}"
            . battle_refund_text((int)$battle['xp_wager']) . '.', $battleId, 'declined');
        battle_feed($battleModel, $userId, ['message' => 'Challenge Declined. Escrow refunded to challenger.']);
    }

    if ($action === 'negotiate') {
        $battle = battle_row($battleModel, $battleId);
        $proposed = battle_proposed($in);
        $ok = $battle['status'] === 'open'
            ? $battleModel->proposeOpenChange($battleId, $userId, json_encode($proposed))
            : $battleModel->proposeChange($battleId, $userId, json_encode($proposed));
        if (!$ok) {
            api_err('cannot_negotiate', 'That challenge is no longer waiting for you.', 409);
        }
        $wager = $proposed['xp_wager'] !== null ? (int)$proposed['xp_wager'] : (int)$battle['xp_wager'];
        $timer = $proposed['custom_timer_seconds'] !== null
            ? floor((int)$proposed['custom_timer_seconds'] / 60) . ' min' : 'default';
        $openInvite = $battle['status'] === 'open';
        battle_told($pdo, (int)$battle['challenger_id'], 'battle_negotiate',
            $openInvite ? '🤝 Counter-Offer on Open Battle' : '🤝 Counter-Offer Received',
            $openInvite
                ? "{$name} wants to battle you on \"{$battle['quiz_title']}\" but proposes: Wager {$wager} XP, Timer {$timer}. Review on your Battle Arena!"
                : "{$name} has proposed new terms for the battle on \"{$battle['quiz_title']}\": Wager {$wager} XP, Timer {$timer}. Review and respond on your profile!",
            $battleId, 'negotiate');
        battle_feed($battleModel, $userId, ['message' => "Negotiation Proposed! Awaiting Challenger's response."]);
    }

    if ($action === 'accept_negotiation' || $action === 'decline_negotiation') {
        $battle = battle_row($battleModel, $battleId);
        $title = $battle['quiz_title'];
        $oppId = (int)$battle['opponent_id'];
        try {
            $ok = $action === 'accept_negotiation'
                ? $battleModel->acceptNegotiation($battleId, $userId)
                : $battleModel->declineNegotiation($battleId, $userId);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'insufficient_xp') {
                api_err('insufficient_xp', 'Accepting these terms stakes XP you do not have yet. Win a few quizzes first.', 409);
            }
            if ($e->getMessage() === 'opponent_insufficient_xp') {
                api_err('opponent_insufficient_xp', 'Your opponent can no longer cover the terms they proposed.', 409);
            }
            throw $e;
        }
        if (!$ok) {
            api_err('cannot_negotiate', 'That counter-offer has already been answered.', 409);
        }
        if ($action === 'accept_negotiation') {
            battle_told($pdo, $oppId, 'battle_accepted', '✅ Counter-Offer Accepted!',
                "Your counter-offer for the battle on \"{$title}\" has been accepted! The arena is now open — Enter and show your strength!",
                $battleId, 'accepted');
            battle_feed($battleModel, $userId, ['message' => 'Counter-offer Accepted! The battle is on.']);
        }
        battle_told($pdo, $oppId, 'battle_declined', '❌ Counter-Offer Rejected',
            "Your counter-offer for the battle on \"{$title}\" was rejected by the challenger. The battle has been cancelled."
            . ((int)$battle['xp_wager'] > 0 ? ' The escrowed ' . (int)$battle['xp_wager'] . ' XP has been refunded.' : ''),
            $battleId, 'declined');
        battle_feed($battleModel, $userId, ['message' => 'Counter-offer Rejected. Challenge was cancelled and escrow refunded.']);
    }

    if ($action === 'join') {
        battle_join($battleModel, $pdo, $userId, $name, $in, $battleId);
    }

    if ($action === 'cancel') {
        battle_cancel($battleModel, $pdo, $userId, $name, battle_row($battleModel, $battleId), $battleId);
    }

    if ($action === 'update') {
        battle_update($battleModel, $pdo, $userId, $in, battle_row($battleModel, $battleId), $battleId, (int)$user['points']);
    }

    if ($action === 'create' || $action === 'create_open') {
        $open = $action === 'create_open';
        $fields = battle_fields($in, $pdo, $userId, (int)$user['points'], $open);

        $id = $open
            ? $battleModel->createOpenChallenge($userId, $fields['quiz_id'], $fields['match_type'], $fields['wager'], $fields['timer'], $fields['start'], $fields['public'] ? 1 : 0, $fields['expires'], $fields['seats'])
            : $battleModel->createChallenge($userId, $fields['opponent_id'], $fields['quiz_id'], $fields['match_type'], $fields['wager'], $fields['timer'], $fields['start']);

        if ($id === false || $id === null || $id === '') {
            // The model only refuses when the wager cannot be taken from the balance.
            api_err('insufficient_xp', 'You do not have enough XP to stake ' . $fields['wager'] . ' XP. Win a few quizzes first.', 409);
        }

        battle_notify_opponent($pdo, $userId, $open ? null : $fields['opponent_id'], $fields['quiz_id'], $fields['match_type'], $fields['wager']);
        if ($open) {
            // createOpenChallenge answers with the invite code; the arena row is
            // the battle behind that code, so both travel back to the app.
            $row = $battleModel->getOpenChallenge((string)$id);
            battle_feed($battleModel, $userId, [
                'invite_code' => (string)$id,
                'battle_id' => (int)($row['id'] ?? 0),
            ]);
        }
        battle_feed($battleModel, $userId, ['battle_id' => (int)$id]);
    }

    api_err('unknown_action', 'That battle action is not recognised.', 422);
}

/** Accepting a challenge, exactly as the website's accept button does. */
function battle_accept(Battle $battleModel, array $battle, int $userId): void {
    global $pdo;
    $battleId = (int)$battle['id'];
    // The hub lists every public invite including your own, and the arena lists
    // the challenges you sent. Neither is yours to accept - and saying so beats
    // reporting a seat that was never taken as "taken while you were deciding".
    if ((int)$battle['challenger_id'] === $userId) {
        if ($battle['status'] === 'open') {
            api_err('own_invite', 'This is your own challenge. Share the invite link, or change the terms instead.', 409);
        }
        api_err('own_challenge', 'This is the challenge you sent. Only they can answer it.', 409);
    }
    if ($battle['status'] === 'pending' && (int)$battle['opponent_id'] !== $userId) {
        api_err('not_yours', 'This challenge is not addressed to you.', 403);
    }
    battle_scheduled($battle);

    try {
        $ok = $battle['status'] === 'open'
            ? $battleModel->acceptOpenChallenge($battleId, $userId)
            : $battleModel->acceptChallenge($battleId, $userId);
    } catch (Throwable $e) {
        if ($e->getMessage() === 'insufficient_xp') {
            api_err('insufficient_xp', 'Accepting stakes ' . (int)$battle['xp_wager'] . ' XP you do not have yet. Win a few quizzes first.', 409);
        }
        if ($e->getMessage() === 'challenger_insufficient_xp') {
            api_err('challenger_insufficient_xp', 'The challenger can no longer cover this wager.', 409);
        }
        throw $e;
    }

    if (!$ok) {
        api_err('cannot_accept', 'That battle was taken or closed while you were deciding.', 409);
    }

    $name = battle_user_name($userId);
    if ($battle['status'] === 'open') {
        // The web's process_open_challenge.php tells the creator an invite seat
        // was taken, and hands the joiner the new head-to-head battle id.
        $newId = (int)$ok;
        battle_told($pdo, (int)$battle['challenger_id'], 'battle_accepted', '⚔️ Open Challenge Accepted!',
            "{$name} accepted your open battle invite on \"{$battle['quiz_title']}\"! The arena is now open.",
            $newId, 'accepted');
        battle_feed($battleModel, $userId, [
            'battle_id' => $newId,
            'message' => 'Challenge accepted! Head to the arena.',
        ]);
    }

    battle_told($pdo, (int)$battle['challenger_id'], 'battle_accepted', '✅ Challenge Accepted!',
        "{$name} has accepted your battle challenge on \"{$battle['quiz_title']}\"! The arena is now open — may the best mind win!",
        $battleId, 'accepted');
    battle_feed($battleModel, $userId, ['message' => 'Challenge Accepted! Prepare for battle.']);
}

/** Joining an open invite link, which is how battle_invite.php works on the web. */
function battle_join(Battle $battleModel, PDO $pdo, int $userId, string $name, array $in, int $battleId): void {
    $code = strtoupper(trim(api_field($in, 'code') ?: api_field($in, 'invite_code')));
    $battle = null;
    if ($code !== '') {
        $battle = $battleModel->getOpenChallenge($code);
    }
    if (!$battle && $battleId > 0) {
        $battle = $battleModel->getBattle($battleId);
    }
    if (!$battle) {
        api_err('invite_not_found', 'This battle invite link is invalid or has expired.', 404);
    }
    if (($battle['status'] ?? null) !== 'open') {
        api_err('invite_closed', 'This invite has already been taken or has closed.', 409);
    }
    if ((int)$battle['challenger_id'] === $userId) {
        api_err('own_invite', 'This is your own invite. Share the link with a classmate instead.', 409);
    }
    battle_scheduled($battle);
    if ((int)($battle['accepted_count'] ?? 0) >= (int)($battle['max_participants'] ?? 1)) {
        api_err('invite_full', 'Every seat in this battle has been taken.', 409);
    }
    if (!empty($battle['expires_at']) && strtotime((string)$battle['expires_at']) <= time()) {
        api_err('invite_expired', 'This invite has expired.', 409);
    }

    try {
        $newId = $battleModel->acceptOpenChallenge((int)$battle['id'], $userId);
    } catch (Throwable $e) {
        if ($e->getMessage() === 'insufficient_xp') {
            api_err('insufficient_xp', 'Insufficient XP. You need ' . (int)$battle['xp_wager'] . ' XP to accept this wager.', 409);
        }
        if ($e->getMessage() === 'challenger_insufficient_xp') {
            api_err('challenger_insufficient_xp', 'The challenger can no longer cover this wager.', 409);
        }
        throw $e;
    }
    if (!$newId) {
        api_err('cannot_accept', 'The seat could not be taken. Try again in a moment.', 409);
    }

    battle_told($pdo, (int)$battle['challenger_id'], 'battle_accepted', '⚔️ Open Challenge Accepted!',
        "{$name} accepted your open battle invite on \"{$battle['quiz_title']}\"! The arena is now open.",
        (int)$newId, 'accepted');
    battle_feed($battleModel, $userId, [
        'battle_id' => (int)$newId,
        'message' => 'Challenge accepted! Head to the arena.',
    ]);
}

/**
 * Removing something you posted. An untouched open invite is deleted and its
 * reservation handed back; a direct challenge nobody has answered is unsent the
 * same way the expire sweep cancels it. Once a duel is being fought it belongs
 * to both sides, so neither can pull it away.
 */
function battle_cancel(Battle $battleModel, PDO $pdo, int $userId, string $name, array $battle, int $battleId): void {
    $status = (string)($battle['status'] ?? '');
    $wager = (int)($battle['xp_wager'] ?? 0);
    $title = $battle['quiz_title'] ?? 'a quiz';

    if ($status === 'open') {
        if ((int)$battle['challenger_id'] !== $userId) {
            api_err('not_mine', 'Only the student who posted this can delete it.', 403);
        }
        if ((int)($battle['accepted_count'] ?? 0) > 0 || !empty($battle['opponent_id'])) {
            api_err('already_taken', 'Someone has already taken a seat, so this battle can no longer be deleted.', 409);
        }
        if (!$battleModel->closeOpenInvite($battleId, $userId)) {
            api_err('cannot_cancel', 'That invite changed while you were deciding. Nothing was deleted.', 409);
        }
        battle_feed($battleModel, $userId, [
            'message' => 'Your open battle was deleted.'
                . ($wager > 0 ? " Your {$wager} XP reservation is back in your balance." : ''),
        ]);
    }

    if ($status === 'pending' || $status === 'negotiating') {
        if ((int)$battle['challenger_id'] !== $userId) {
            api_err('not_mine', 'Only the student who sent this can unsend it.', 403);
        }
        $opponentId = (int)($battle['opponent_id'] ?? 0);
        if (!$battleModel->withdrawChallenge($battleId, $userId)) {
            api_err('cannot_cancel', 'That challenge already moved on. Nothing was unsent.', 409);
        }
        battle_told($pdo, $opponentId, 'battle_declined', 'Challenge Withdrawn',
            "{$name} unsent their battle challenge on \"{$title}\". Nothing was ever staked by you.",
            $battleId, 'declined');
        battle_feed($battleModel, $userId, [
            'message' => 'Challenge unsent.'
                . ($wager > 0 ? " Your {$wager} XP is back in your balance." : ''),
        ]);
    }

    // An invite that filled its last seat stops being 'open' before anyone
    // thinks to delete it, so the real reason has to be said here too.
    if ((int)$battle['challenger_id'] === $userId && (int)($battle['accepted_count'] ?? 0) > 0) {
        api_err('already_taken', 'Someone has already taken a seat, so this battle can no longer be deleted.', 409);
    }

    api_err('cannot_cancel', 'A battle that is already being fought cannot be removed.', 409);
}

/**
 * Re-pricing an open invite you posted, only while it sits untouched. The escrow
 * moves with the stake: raising it takes the difference now, lowering it returns
 * the difference now, so the reservation always equals exactly one wager.
 */
function battle_update(Battle $battleModel, PDO $pdo, int $userId, array $in, array $battle, int $battleId, int $points): void {
    if ((int)$battle['challenger_id'] !== $userId) {
        api_err('not_mine', 'Only the student who posted this can change it.', 403);
    }
    // A seat already taken is the reason the terms are frozen, and it is said
    // that way whether the invite still sits open with a seat spare or has just
    // filled up and closed itself.
    if ((int)($battle['accepted_count'] ?? 0) > 0 || ($battle['status'] === 'open' && !empty($battle['opponent_id']))) {
        api_err('already_taken', 'Someone has already joined this battle, so its terms are locked.', 409);
    }
    if (($battle['status'] ?? '') !== 'open') {
        api_err('cannot_edit', 'Only an open battle you posted can be edited. A challenge sent to someone is fixed until they answer it.', 409);
    }

    // Their balance already carries the reservation on this invite, so the new
    // stake is measured against what they hold plus what comes back from it.
    $holds = $points + (int)($battle['xp_wager'] ?? 0);
    $fields = battle_fields($in, $pdo, $userId, $holds, true);

    try {
        $ok = $battleModel->editOpenInvite($battleId, $userId, [
            'quiz_id' => $fields['quiz_id'],
            'match_type' => $fields['match_type'],
            'xp_wager' => $fields['wager'],
            'custom_timer_seconds' => $fields['timer'],
            'max_participants' => $fields['seats'],
        ]);
    } catch (Throwable $e) {
        if ($e->getMessage() === 'insufficient_xp') {
            api_err('insufficient_xp', 'Raising the stake needs ' . $fields['wager'] . ' XP and you no longer hold the difference.', 409);
        }
        throw $e;
    }

    if (!$ok) {
        api_err('cannot_edit', 'That invite changed while you were editing it. Nothing was saved.', 409);
    }

    battle_feed($battleModel, $userId, ['message' => 'Your open battle is updated. Every student now sees the new terms.']);
}

/** Read and validate the wager the same way the website form does. */
function battle_fields(array $in, PDO $pdo, int $userId, int $points, bool $open): array {
    $quizId = (int)api_field($in, 'quiz_id');
    $opponentId = (int)api_field($in, 'opponent_id');
    $matchType = api_field($in, 'match_type') === 'ranked' ? 'ranked' : 'friendly';
    $wager = max(0, (int)api_field($in, 'xp_wager', '0'));

    // The same bounds the website form enforces, from admin Settings.
    $min = site_battle_wager_min();
    $max = site_battle_wager_max();
    if ($wager > 0 && ($wager < $min || $wager > $max)) {
        api_err('wager_out_of_range', "A stake has to be between {$min} and {$max} XP, or 0 for a friendly battle.", 422);
    }
    if ($wager > $points) {
        api_err('insufficient_xp', "You only have {$points} XP to stake.", 409);
    }

    $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE id = ? AND mode = 'normal' AND is_paid = 0 LIMIT 1");
    $stmt->execute([$quizId]);
    if (!$stmt->fetchColumn()) {
        api_err('quiz_unavailable', 'Pick a free practice quiz - battles cannot be played on a paid or multi-exam quiz.', 422);
    }

    if ($open) {
        $seats = max(1, min(10, (int)(api_field($in, 'max_participants', '1') ?: 1)));
        $start = api_field($in, 'start_time');
        $hours = max(0, (int)api_field($in, 'duration_hours', '0'));
        return [
            'quiz_id' => $quizId,
            'opponent_id' => 0,
            'match_type' => $matchType,
            'wager' => $wager,
            'timer' => battle_timer($in),
            'start' => $start !== '' ? date('Y-m-d H:i:s', strtotime($start)) : null,
            'public' => api_field($in, 'list_on_hub', '1') === '1',
            'seats' => $seats,
            'expires' => $hours > 0 ? date('Y-m-d H:i:s', ($start !== '' ? strtotime($start) : time()) + $hours * 3600) : null,
        ];
    }

    if ($opponentId <= 0 || $opponentId === $userId) {
        api_err('opponent_required', 'Choose the classmate you want to challenge.', 422);
    }
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$opponentId]);
    if (!$stmt->fetchColumn()) {
        api_err('opponent_unavailable', 'That classmate cannot be challenged right now.', 404);
    }

    return [
        'quiz_id' => $quizId,
        'opponent_id' => $opponentId,
        'match_type' => $matchType,
        'wager' => $wager,
        'timer' => battle_timer($in),
        'start' => null,
        'public' => false,
        'seats' => 1,
        'expires' => null,
    ];
}

/** The counter-offer body the web form posts: a timer and a wager, or neither. */
function battle_proposed(array $in): array {
    $timer = api_field($in, 'timer_seconds', '');
    if ($timer === '') $timer = api_field($in, 'custom_timer_seconds', '');
    $wager = api_field($in, 'xp_wager', '');

    $proposed = [
        'custom_timer_seconds' => $timer === '' || (int)$timer === 0 ? null : (int)$timer,
        'xp_wager' => $wager === '' ? null : max(0, (int)$wager),
    ];
    if ($proposed['custom_timer_seconds'] !== null) {
        $seconds = $proposed['custom_timer_seconds'];
        if ($seconds < 30 || $seconds > 7200) {
            api_err('timer_out_of_range', 'A battle timer has to be between 30 seconds and 2 hours.', 422);
        }
    }
    if ($proposed['xp_wager'] !== null && $proposed['xp_wager'] > site_battle_wager_max()) {
        // The web's counter-offer field is min=0 max=<site maximum>: a student may
        // cut a stake below the site minimum, since only the arena's create form
        // sets a floor. Only the ceiling is refused here.
        api_err('wager_out_of_range', 'A stake cannot be above ' . site_battle_wager_max() . ' XP.', 422);
    }
    // Whether each side can actually pay the new terms is checked when the
    // challenger accepts them, which is where the web checks it too.
    return $proposed;
}

function battle_timer(array $in): ?int {
    $seconds = (int)api_field($in, 'timer_seconds', '0');
    // A duel shorter than a question or longer than an exam session is a typo.
    return $seconds >= 30 && $seconds <= 7200 ? $seconds : null;
}

/** A challenge is only worth showing once the other side has been told. */
function battle_notify_opponent(PDO $pdo, int $userId, ?int $opponentId, int $quizId, string $matchType, int $wager): void {
    if (!$opponentId) {
        return;
    }
    try {
        $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $name = $stmt->fetchColumn() ?: 'Someone';

        $stmt = $pdo->prepare('SELECT title FROM quizzes WHERE id = ? LIMIT 1');
        $stmt->execute([$quizId]);
        $title = $stmt->fetchColumn() ?: 'a quiz';

        $wagerText = $wager > 0 ? " with a wager of {$wager} XP" : '';
        $typeText = $matchType === 'ranked' ? 'Ranked' : 'Friendly';
        $body = "{$name} has challenged you to a {$typeText} battle on \"{$title}\"{$wagerText}. Open Battle to accept or decline.";

        (new Notification($pdo))->create($opponentId, 'battle_challenge', 'New battle challenge', $body, SITE_URL . '/profile.php');
        sendBattleEmail($pdo, $opponentId, 'New battle challenge', $body, SITE_URL . '/profile.php', 'challenge');
    } catch (Throwable $e) {
        // The challenge exists; a lost notification must not make the app report failure.
        error_log('api battle notify: ' . $e->getMessage());
    }
}

function battle_row(Battle $battleModel, int $battleId): array {
    if ($battleId <= 0) {
        api_err('invalid_action', 'Nothing was changed - that battle was not named.', 422);
    }
    $battle = $battleModel->getBattle($battleId);
    if (!$battle) {
        api_err('not_found', 'That battle no longer exists.', 404);
    }
    return $battle;
}

/** The web refuses to accept a battle whose arena is not open yet. */
function battle_scheduled(array $battle): void {
    if (!empty($battle['start_time']) && strtotime((string)$battle['start_time']) > time()) {
        $label = date('M j, Y g:i A', strtotime((string)$battle['start_time']));
        api_err('not_started', "This battle is scheduled to start on {$label}. You can accept it then!", 409);
    }
}

function battle_refund_text(int $wager): string {
    return $wager > 0 ? ". Your escrowed {$wager} XP has been refunded" : '';
}

/**
 * One channel pair for every battle message: the in-app notification and the
 * email the website's own sendBattleEmail() writes, with the same link so both
 * sides land on the same row wherever they read it.
 */
function battle_told(PDO $pdo, int $userId, string $type, string $title, string $message, int $battleId, string $emailKind): void {
    if ($userId <= 0) {
        return;
    }
    $link = SITE_URL . '/battle_arena.php#battle-' . $battleId;
    try {
        (new Notification($pdo))->create($userId, $type, $title, $message, $link);
    } catch (Throwable $e) {
        error_log('api battle notify: ' . $e->getMessage());
    }
    try {
        sendBattleEmail($pdo, $userId, $title, $message, $link, $emailKind);
    } catch (Throwable $e) {
        error_log('api battle email: ' . $e->getMessage());
    }
}

function battle_user_name(int $userId): string {
    global $pdo;
    $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    return (string)($stmt->fetchColumn() ?: 'A user');
}

/** Answer with the caller's record so the app repaints without a second call. */
function battle_feed(Battle $battleModel, int $userId, array $extra = []): void {
    global $pdo;
    $stats = $battleModel->getUserStats($userId) ?: [];
    $stmt = $pdo->prepare('SELECT points FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);

    api_out($extra + [
        'ok' => true,
        'me' => [
            'rating' => (int)($stats['rating'] ?? 1000),
            'wins' => (int)($stats['wins'] ?? 0),
            'losses' => (int)($stats['losses'] ?? 0),
            'draws' => (int)($stats['draws'] ?? 0),
            'streak' => (int)($stats['current_streak'] ?? 0),
            'points' => (int)$stmt->fetchColumn(),
        ],
        'message' => 'Done.',
    ]);
}

function battle_quiz_options(PDO $pdo): array {
    $stmt = $pdo->query("SELECT id, title, subject FROM quizzes WHERE mode = 'normal' AND is_paid = 0 ORDER BY title ASC LIMIT 200");
    return array_map(static function ($q) {
        return ['id' => (int)$q['id'], 'title' => (string)$q['title'], 'subject' => $q['subject'] ?? null];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/** One fighter as the details sheet introduces them: face, rank, record. */
function battle_person(PDO $pdo, int $id, string $fallbackName, $rating): array {
    $stmt = $pdo->prepare('SELECT name, profile_picture FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare('SELECT wins, losses, draws FROM user_battle_stats WHERE user_id = ? LIMIT 1');
    $stmt->execute([$id]);
    $s = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'id' => $id,
        'name' => (string)($u['name'] ?? $fallbackName),
        'picture' => api_url($u['profile_picture'] ?? null),
        'rating' => (int)($rating ?? 1000),
        'wins' => (int)($s['wins'] ?? 0),
        'losses' => (int)($s['losses'] ?? 0),
        'draws' => (int)($s['draws'] ?? 0),
    ];
}

/** What this account holds, which is the only number that can block an accept. */
function battle_points(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT points FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);

    return ['points' => (int)$stmt->fetchColumn()];
}

/**
 * The whole truth about one challenge, in one small reply.
 *
 * The arena cards carry the badge machine's fields; this adds what a student
 * weighs before putting XP into a duel: what the paper actually is (subject and
 * question count), how long it really runs, both fighters' ranks and records,
 * and their own balance against the stake.
 */
function battle_details(Battle $battleModel, PDO $pdo, int $userId, int $battleId, callable $shape): void {
    $battle = $battleId > 0 ? $battleModel->getBattle($battleId) : null;
    if (!$battle) {
        api_err('not_found', 'That challenge is not here any more.', 404);
    }

    $quizId = (int)$battle['quiz_id'];
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?');
    $stmt->execute([$quizId]);
    $count = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT duration_seconds FROM quizzes WHERE id = ? LIMIT 1');
    $stmt->execute([$quizId]);
    $defaultSeconds = (int)$stmt->fetchColumn();
    if ($defaultSeconds <= 0) {
        $defaultSeconds = 900;
    }

    $challengerId = (int)$battle['challenger_id'];
    $opponentId = !empty($battle['opponent_id']) ? (int)$battle['opponent_id'] : 0;
    $wager = (int)($battle['xp_wager'] ?? 0);

    api_out([
        'ok' => true,
        'battle' => $shape($battle) + [
            'subject' => (string)($battle['quiz_subject'] ?? 'General'),
            'question_count' => $count,
            'default_seconds' => $defaultSeconds,
            // How many seats this invite is still looking for.
            'needs_seats' => max(0, (int)($battle['max_participants'] ?? 1) - (int)($battle['accepted_count'] ?? 0)),
        ],
        'host' => battle_person($pdo, $challengerId, (string)($battle['challenger_name'] ?? 'A student'), $battle['challenger_rating'] ?? null),
        'guest' => $opponentId > 0
            ? battle_person($pdo, $opponentId, (string)($battle['opponent_name'] ?? 'A student'), $battle['opponent_rating'] ?? null)
            : null,
        'me' => battle_points($pdo, $userId),
        'pot' => $wager * 2,
        'limits' => ['wager_min' => site_battle_wager_min(), 'wager_max' => site_battle_wager_max()],
        'timer_options' => [
            ['seconds' => 0, 'label' => 'Default'],
            ['seconds' => 300, 'label' => '5 Min'],
            ['seconds' => 600, 'label' => '10 Min'],
            ['seconds' => 1800, 'label' => '30 Min'],
        ],
    ]);
}
