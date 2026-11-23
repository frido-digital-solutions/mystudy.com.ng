<?php
/**
 * Private chats for the app, on the same conversation model the website's
 * messages page uses, so a thread started on the web continues on the phone.
 *
 *   GET  chats.php                     -> your threads, newest first
 *   GET  chats.php?id=12&after=340     -> one conversation (and only what is new)
 *   POST {to_user}                     -> open or reuse the thread with that user
 *   POST {conversation_id, body}       -> send
 *   POST {conversation_id, last_read_message_id} -> mark read
 *   POST {action, ...}                 -> edit / delete / block / delete the thread
 *   POST {action: typing|typing_stop}  -> the "typing…" light in the other side's chat
 *
 * Every action here is the same call the website makes in api_messages.php, on
 * the same Message model, so the one-hour edit window and the block rules are
 * decided in exactly one place and cannot drift between the two surfaces.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Message.php';

$user = api_auth();
$userId = (int)$user['id'];
$msg = new Message($pdo);
$msg->ensureTables();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    api_require_post();
    $in = api_input();

    // ─── The actions a long press offers ────────────────────────────────
    $action = (string)api_field($in, 'action', '');
    if ($action !== '') {
        $messageId = (int)api_field($in, 'message_id');

        // A message can only be touched by someone who is standing in its
        // conversation. api_messages.php has never checked this; the app layer
        // will not be the place that keeps the mistake.
        $holder = null;
        if ($messageId > 0) {
            $mStmt = $pdo->prepare('SELECT conversation_id, sender_id, created_at FROM messages WHERE id = ?');
            $mStmt->execute([$messageId]);
            $holder = $mStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($holder && !$msg->getConversationById((int)$holder['conversation_id'], $userId)) {
                $holder = null;
            }
        }

        switch ($action) {
            case 'edit':
                if (!$holder) api_err('not_found', 'That message is not yours to change.', 404);
                $newBody = trim((string)api_field($in, 'body'));
                if ($newBody === '') api_err('empty_body', 'Write something first.', 422);
                if (mb_strlen($newBody) > 4000) api_err('too_long', 'That message is too long.', 422);
                if (!$msg->editMessage($messageId, $userId, $newBody)) {
                    api_err('cannot_edit', 'You can only edit your own messages within 1 hour.', 403);
                }
                api_out(['ok' => true, 'message' => ['id' => $messageId, 'body' => $newBody, 'is_edited' => true]]);

            case 'delete_for_me':
                if (!$holder) api_err('not_found', 'That message is not yours to change.', 404);
                $msg->deleteForMe($messageId, $userId);
                api_out(['ok' => true, 'removed' => $messageId]);

            case 'delete_for_everyone':
                if (!$holder) api_err('not_found', 'That message is not yours to change.', 404);
                if (!$msg->deleteForEveryone($messageId, $userId)) {
                    api_err('cannot_delete', 'You can only delete your own messages within 1 hour.', 403);
                }
                api_out([
                    'ok' => true,
                    'message' => ['id' => $messageId, 'deleted' => true, 'deleted_by' => (string)$user['name']],
                ]);

            case 'block':
                $target = (int)api_field($in, 'user_id');
                if ($target <= 0 || $target === $userId) api_err('bad_target', 'That person cannot be blocked.', 422);
                $msg->blockUser($userId, $target);
                api_out(['ok' => true, 'blocked' => $target]);

            case 'unblock':
                $target = (int)api_field($in, 'user_id');
                if ($target <= 0) api_err('bad_target', 'That person cannot be unblocked.', 422);
                $msg->unblockUser($userId, $target);
                api_out(['ok' => true, 'unblocked' => $target]);

            case 'delete_conversation':
                $convId = (int)api_field($in, 'conversation_id');
                if ($convId <= 0 && $holder) $convId = (int)$holder['conversation_id'];
                if ($convId <= 0 || !$msg->getConversationById($convId, $userId)) {
                    api_err('not_found', 'That conversation is not yours.', 404);
                }
                $msg->deleteConversation($convId, $userId);
                api_out(['ok' => true, 'deleted' => $convId]);

            // The same two calls api_messages.php makes from the website's input
            // box, on the same typing_status rows, so a person reading the thread
            // in a browser sees "typing…" from the phone and vice versa. The row
            // is only trusted for five seconds, which is what makes it stop on
            // its own if the phone dies mid-sentence.
            case 'typing':
            case 'typing_stop':
                $withConv = (int)api_field($in, 'conversation_id');
                $conversation = $withConv > 0 ? $msg->getConversationById($withConv, $userId) : null;
                if (!$conversation) api_err('not_found', 'That conversation is not yours.', 404);
                $otherId = (int)((int)$conversation['user_one_id'] === $userId ? $conversation['user_two_id'] : $conversation['user_one_id']);
                if ($action === 'typing') {
                    $msg->setTyping($userId, $otherId);
                } else {
                    $msg->stopTyping($userId, $otherId);
                }
                api_out(['ok' => true]);

            // The two switches on the chats page. They write the same user_meta
            // rows the website's own privacy form writes, so a setting changed on
            // the phone is the setting the web shows when you next open it.
            case 'share_status':
            case 'allow_messages':
                $key = $action === 'share_status' ? 'privacy_share_status' : 'privacy_allow_messages';
                $value = filter_var(api_field($in, 'value'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                $has = $pdo->prepare('SELECT id FROM user_meta WHERE user_id = ? AND meta_key = ?');
                $has->execute([$userId, $key]);
                if ($has->rowCount() > 0) {
                    $pdo->prepare('UPDATE user_meta SET meta_value = ? WHERE user_id = ? AND meta_key = ?')->execute([$value, $userId, $key]);
                } else {
                    $pdo->prepare('INSERT INTO user_meta (user_id, meta_key, meta_value) VALUES (?, ?, ?)')->execute([$userId, $key, $value]);
                }
                api_out(['ok' => true, 'value' => $value === 1]);

            default:
                api_err('bad_action', 'That action is not known here.', 422);
        }
    }

    // Open a thread from a public profile or the Find People page. The model
    // refuses if either side has blocked the other, or if the person you are
    // reaching for switched off new messages, and that answer is passed straight
    // back so the app can show the reason instead of a silent failure.
    if (isset($in['to_user'])) {
        $partnerId = (int)api_field($in, 'to_user');
        if ($partnerId <= 0 || $partnerId === $userId) api_err('bad_target', 'That person cannot be messaged.', 422);

        $allowed = $msg->canMessage($userId, $partnerId);
        if (empty($allowed['allowed'])) {
            api_err('cannot_message', $allowed['reason'] ?? 'You cannot message this person.', 403, [
                'reason_code' => message_gate_code($allowed['reason'] ?? ''),
            ]);
        }

        $conv = $msg->getOrCreateConversation($userId, $partnerId);
        api_out(['ok' => true, 'conversation_id' => (int)$conv['id']]);
    }

    $convId = (int)api_field($in, 'conversation_id');
    if ($convId <= 0) api_err('missing_id', 'No conversation was given.', 422);
    $conversation = $msg->getConversationById($convId, $userId);
    if (!$conversation) api_err('not_found', 'That conversation is not yours.', 404);

    if (isset($in['last_read_message_id'])) {
        $msg->markAsRead($convId, $userId, (int)$in['last_read_message_id']);
        api_out(['ok' => true]);
    }

    $body = trim((string)api_field($in, 'body'));
    if ($body === '') api_err('empty_body', 'Write something first.', 422);
    if (mb_strlen($body) > 4000) api_err('too_long', 'That message is too long.', 422);

    $partnerId = (int)($conversation['user_one_id'] === $userId ? $conversation['user_two_id'] : $conversation['user_one_id']);
    $allowed = $msg->canMessage($userId, $partnerId);
    if (empty($allowed['allowed'])) {
        api_err('cannot_message', $allowed['reason'] ?? 'You cannot message this person.', 403, [
            'reason_code' => message_gate_code($allowed['reason'] ?? ''),
        ]);
    }

    $sent = $msg->sendMessage($convId, $userId, $body, (int)api_field($in, 'reply_to') ?: null);
    if (!$sent) api_err('send_failed', 'The message could not be sent.', 500);

    api_out(['ok' => true, 'message' => [
        'id' => (int)$sent['id'],
        'body' => (string)$sent['body'],
        'mine' => true,
        'deleted' => false,
        'deleted_by' => '',
        'is_edited' => false,
        // The clock starts now, so the bubble is editable the moment it lands.
        'can_edit' => true,
        'created_at' => $sent['created_at'] ?? date('Y-m-d H:i:s'),
        'reply_to' => isset($sent['reply_to_body']) && $sent['reply_to_body'] !== null
            ? [
                'id' => (int)($sent['reply_to_id'] ?? 0),
                'body' => (string)$sent['reply_to_body'],
                'from' => (string)($sent['reply_to_sender_name'] ?? ''),
            ]
            : null,
    ]]);
}

// ---------------------------------------------------------------- one thread
$convId = (int)($_GET['id'] ?? 0);
if ($convId > 0) {
    $conversation = $msg->getConversationById($convId, $userId);
    if (!$conversation) api_err('not_found', 'That conversation is not yours.', 404);

    $partnerId = (int)($conversation['user_one_id'] === $userId ? $conversation['user_two_id'] : $conversation['user_one_id']);
    $pStmt = $pdo->prepare('SELECT id, name, profile_picture, user_type, points FROM users WHERE id = ?');
    $pStmt->execute([$partnerId]);
    $partner = $pStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    // Presence comes from the same helper the website's own pages use, so a
    // person who switched off their active status is Offline in the phone too,
    // not green here and hidden there.
    $pState = presence_for_users([$partnerId], $pdo)[$partnerId] ?? ['online' => false, 'last_active' => null, 'share' => true];

    $after = (int)($_GET['after'] ?? 0);
    $rows = $after > 0
        ? $msg->getNewMessages($convId, $userId, $after)
        : $msg->getMessages($convId, $userId, max(20, min(80, (int)($_GET['limit'] ?? 50))));

    // Both queries come back oldest-first, so the last row is always the newest.
    $newest = $rows ? (int)end($rows)['id'] : 0;

    // A change to a message the phone already has is invisible to an
    // "everything after id X" poll, so the website asks for those separately and
    // so does this. Without it, the other person deleting for everyone would
    // leave the bubble standing until the thread was reopened.
    $gone = $after > 0 ? $msg->getDeletedForEveryoneUpdates($convId, $after) : [];
    $changed = $after > 0 ? $msg->getEditedMessageUpdates($convId, $after) : [];

    $readStmt = $pdo->prepare('SELECT last_read_message_id FROM message_reads WHERE conversation_id = ? AND user_id = ?');
    $readStmt->execute([$convId, $userId]);
    $myRead = (int)$readStmt->fetchColumn();

    // The partner's own read row gives both halves of the receipt: how far they
    // have got, and the moment they got there. conversation.php only draws the
    // first half as a tick; the phone needs the second for the Info sheet.
    $pReadStmt = $pdo->prepare('SELECT last_read_message_id, read_at FROM message_reads WHERE conversation_id = ? AND user_id = ?');
    $pReadStmt->execute([$convId, $partnerId]);
    $pRead = $pReadStmt->fetch(PDO::FETCH_ASSOC) ?: ['last_read_message_id' => 0, 'read_at' => null];
    $partnerRead = (int)$pRead['last_read_message_id'];

    // This device has the thread up to here. That is what "delivered" means on
    // the other person's screen, so it is written down and read back by the
    // sender's phone.
    $msg->noteFetch($userId, $convId, $newest);
    $partnerFetched = $msg->getFetchPointer($partnerId, $convId);

    // The same two questions conversation.php asks before it draws a bubble:
    // may this message still be changed, and is it a tombstone. One hour, own
    // messages only, and never a message that is already deleted.
    $window = 3600;
    $gate = $msg->canMessage($userId, $partnerId);

    api_out([
        'ok' => true,
        'conversation_id' => $convId,
        'partner' => $partner ? [
            'id' => (int)$partner['id'],
            'name' => (string)$partner['name'],
            'picture' => api_url($partner['profile_picture'] ?? null),
            'user_type' => (string)($partner['user_type'] ?? ''),
            'points' => (int)($partner['points'] ?? 0),
            'online' => $pState['online'],
            'last_active' => $pState['last_active'],
            // Lets the header say "Offline" for someone who chose to hide,
            // rather than inventing a last-seen time the server refused to give.
            'status_hidden' => !$pState['share'],
        ] : null,
        'items' => array_map(static function ($m) use ($userId, $window, $partnerRead, $pRead) {
            $deleted = (int)($m['deleted_for_everyone'] ?? 0) === 1;
            $mine = (int)$m['sender_id'] === $userId;
            $age = time() - strtotime((string)($m['created_at'] ?? 'now'));
            return [
                'id' => (int)$m['id'],
                'body' => $deleted ? '' : (string)$m['body'],
                'deleted' => $deleted,
                'deleted_by' => $deleted ? (string)($m['deleted_by_name'] ?? '') : '',
                'mine' => $mine,
                'created_at' => $m['created_at'] ?? null,
                'is_edited' => (int)($m['is_edited'] ?? 0) === 1,
                // One flag for both, exactly as conversation.php does it: the
                // hour that lets you reword a message is the hour that lets you
                // take it back out of the other person's copy.
                'can_edit' => $mine && !$deleted && $age <= $window,
                // Double grey tick = delivered, double blue = seen, and the
                // phone can now say when. Nothing is invented here: the model
                // only ever stores one read pointer per person per thread, so
                // the time shown is the time they read up to this message.
                'seen' => $mine && !$deleted && (int)$m['id'] <= $partnerRead,
                'seen_at' => $mine && !$deleted && (int)$m['id'] <= $partnerRead ? $pRead['read_at'] : null,
                'reply_to' => isset($m['reply_to_body']) && $m['reply_to_body'] !== null
                    ? [
                        'id' => (int)($m['reply_to_id'] ?? 0),
                        'body' => (string)$m['reply_to_body'],
                        'from' => (string)($m['reply_to_sender_name'] ?? ''),
                    ]
                    : null,
            ];
        }, $rows),
        'read_up_to' => $myRead,
        'partner_read_up_to' => $partnerRead,
        'partner_read_at' => $pRead['read_at'],
        // How far the other person's phone has this thread: two grey ticks.
        'partner_fetched_up_to' => $partnerFetched,
        'partner_typing' => $pState['share'] && $msg->isPartnerTyping($userId, $partnerId),
        // The phone's clock and the server's rarely agree to the second, and
        // "last seen 5m ago" is a subtraction, so the diff is done against this.
        'server_time' => time(),
        'can_message' => !empty($gate['allowed']),
        'message_reason' => (string)($gate['reason'] ?? ''),
        'message_reason_code' => message_gate_code($gate['reason'] ?? ''),
        'i_blocked' => $msg->hasBlocked($userId, $partnerId),
        'they_blocked' => $msg->hasBlocked($partnerId, $userId),
        'newest' => $newest,
        'deleted_updates' => array_map(static function ($m) {
            return ['id' => (int)$m['id'], 'deleted_by' => (string)($m['deleted_by_name'] ?? '')];
        }, $gone),
        'edited_updates' => array_map(static function ($m) {
            return ['id' => (int)$m['id'], 'body' => (string)$m['body'], 'is_edited' => (int)$m['is_edited'] === 1];
        }, $changed),
        'has_more' => count($rows) >= 50,
    ]);
}

// ------------------------------------------------------------------- all threads
$rows = $msg->getConversations($userId);

// One query for the whole list, so a long press can say "Unblock" instead of
// "Block" for the person you already blocked, without fifty round trips.
$blockedStmt = $pdo->prepare('SELECT blocked_id FROM user_blocks WHERE blocker_id = ?');
$blockedStmt->execute([$userId]);
$blockedSet = array_map('intval', $blockedStmt->fetchAll(PDO::FETCH_COLUMN));

// One query for the whole list again: whoever is typing to me in this second.
// The list can then show "typing…" under a name exactly the way the website's
// messages page does, without the phone polling fifty conversations.
$typeStmt = $pdo->prepare('SELECT user_id FROM typing_status WHERE partner_id = ? AND last_typing_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)');
$typeStmt->execute([$userId]);
$typingSet = array_map('intval', $typeStmt->fetchAll(PDO::FETCH_COLUMN));

// Presence for every person on this page in one query, and with it the right to
// show any of it: someone who switched off their active status is reported
// Offline here too, and the typing light that would give them away is dropped.
$presence = presence_for_users(array_column($rows, 'partner_id'), $pdo);

// The two switches the chats page settings sheet draws, read from the same
// user_meta rows the website's privacy form writes.
$privStmt = $pdo->prepare("SELECT meta_key, meta_value FROM user_meta WHERE user_id = ? AND meta_key IN ('privacy_share_status','privacy_allow_messages')");
$privStmt->execute([$userId]);
$privacy = ['share_status' => true, 'allow_messages' => true];
foreach ($privStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $privacy[$row['meta_key'] === 'privacy_share_status' ? 'share_status' : 'allow_messages'] = (int)$row['meta_value'] !== 0;
}

api_out([
    'ok' => true,
    'items' => array_map(static function ($c) use ($userId, $blockedSet, $typingSet, $presence) {
        $pid = (int)$c['partner_id'];
        $state = $presence[$pid] ?? ['online' => false, 'last_active' => null, 'share' => true];
        return [
            'id' => (int)$c['id'],
            'partner_id' => $pid,
            'name' => (string)$c['partner_name'],
            'picture' => api_url($c['partner_avatar'] ?? null),
            'snippet' => mb_substr((string)($c['last_message_body'] ?? ''), 0, 90),
            'mine' => (int)($c['last_message_sender_id'] ?? 0) === $userId,
            'time' => $c['last_message_time'] ?? null,
            'unread' => (int)($c['unread_count'] ?? 0),
            'online' => $state['online'],
            'last_seen' => $state['last_active'],
            'status_hidden' => !$state['share'],
            'typing' => $state['share'] && in_array($pid, $typingSet, true),
            'i_blocked' => in_array($pid, $blockedSet, true),
        ];
    }, $rows),
    'unread' => $msg->getUnreadTotal($userId),
    'privacy' => $privacy,
    'total' => count($rows),
]);
