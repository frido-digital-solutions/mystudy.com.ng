<?php
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated. Please log in again.', 'auth' => false]);
    exit;
}

// Same frozen-account block require_login() enforced, without its HTML redirect.
$frozenStmt = $pdo->prepare("SELECT is_frozen FROM users WHERE id = ?");
$frozenStmt->execute([$userId]);
if ((int)$frozenStmt->fetchColumn() === 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Your account is frozen.', 'auth' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

require_once __DIR__ . '/models/Message.php';
$msgModel = new Message($pdo);

switch ($action) {

    // ─── Send a message ───────────────────────────────────────────
    case 'send':
        $recipientId = (int)($_POST['recipient_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        $replyToId = !empty($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;

        if ($recipientId <= 0 || $body === '') {
            echo json_encode(['error' => 'Missing recipient or message body.']);
            exit;
        }

        $canMsg = $msgModel->canMessage($userId, $recipientId);
        if (!$canMsg['allowed']) {
            echo json_encode(['error' => $canMsg['reason']]);
            exit;
        }

        $conv = $msgModel->getOrCreateConversation($userId, $recipientId);
        $msg = $msgModel->sendMessage($conv['id'], $userId, $body, $replyToId);

        if ($msg) {
            $partnerReadId = $msgModel->getPartnerReadId($conv['id'], $recipientId);
            echo json_encode(['success' => true, 'message' => $msg, 'partnerReadId' => $partnerReadId]);

            // Hand the JSON to the client and drop the session lock before the push
            // dispatch: WebPushService allows 15s per subscription and would otherwise
            // stall every concurrent request from this user.
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            while (ob_get_level() > 0) {
                if (!ob_end_flush()) {
                    break;
                }
            }
            flush();
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            // Send Web Push notification to recipient
            try {
                require_once __DIR__ . '/includes/WebPushService.php';
                require_once __DIR__ . '/models/User.php';
                $pushSvc = new WebPushService($pdo);
                $senderUser = new User($pdo);
                $senderUser->getById($userId);
                $senderName = $senderUser->name ?? 'Someone';
                $preview = mb_substr(strip_tags($body), 0, 80);
                $pushSvc->sendToUser(
                    $recipientId,
                    "💬 New message from $senderName",
                    $preview,
                    SITE_URL . '/conversation.php?with=' . $userId
                );
            } catch (\Throwable $e) {
                error_log('Message push failed: ' . $e->getMessage());
            }
        } else {
            echo json_encode(['error' => 'Failed to send message.']);
        }
        break;

    // ─── Get messages for a conversation ──────────────────────────
    case 'get_messages':
        $partnerId = (int)($_POST['partner_id'] ?? 0);
        $beforeId = !empty($_POST['before_id']) ? (int)$_POST['before_id'] : null;
        $limit = (int)($_POST['limit'] ?? 50);

        if ($partnerId <= 0) {
            echo json_encode(['error' => 'Invalid partner.']);
            exit;
        }

        $conv = $msgModel->getOrCreateConversation($userId, $partnerId);
        $messages = $msgModel->getMessages($conv['id'], $userId, $limit, $beforeId);

        $partnerReadId = $msgModel->getPartnerReadId($conv['id'], $partnerId);
        echo json_encode(['success' => true, 'messages' => $messages, 'conversation_id' => $conv['id'], 'partnerReadId' => $partnerReadId]);
        break;

    // ─── Polling: Global Unread Count ─────────────────────────────
    case 'ping_unread':
        // Close session write to prevent blocking other concurrent requests
        session_write_close();
        if ($userId <= 0) {
            echo json_encode(['unread' => 0]);
            exit;
        }
        $unreadMsgCount = $msgModel->getUnreadTotal($userId);
        echo json_encode(['success' => true, 'unread' => $unreadMsgCount]);
        break;

    // ─── Poll for new messages ────────────────────────────────────
    case 'check_new':
        // Close session write to prevent blocking other concurrent requests
        session_write_close();
        $partnerId = (int)($_POST['partner_id'] ?? 0);
        $afterId = (int)($_POST['after_id'] ?? 0);

        if ($partnerId <= 0) {
            echo json_encode(['error' => 'Invalid partner.']);
            exit;
        }

        $conv = $msgModel->getOrCreateConversation($userId, $partnerId);
        $newMsgs = $msgModel->getNewMessages($conv['id'], $userId, $afterId);
        $deletedUpdates = $msgModel->getDeletedForEveryoneUpdates($conv['id'], $afterId);
        $editedUpdates = $msgModel->getEditedMessageUpdates($conv['id'], $afterId);

        // Auto-mark as read
        if (!empty($newMsgs)) {
            $lastMsg = end($newMsgs);
            $msgModel->markAsRead($conv['id'], $userId, (int)$lastMsg['id']);
        }

        $partnerReadId = $msgModel->getPartnerReadId($conv['id'], $partnerId);
        
        // Fetch partner's latest last_active
        $partnerModel = new User($pdo);
        $partnerLastActive = null;
        if ($partnerModel->getById($partnerId)) {
            $partnerLastActive = $partnerModel->last_active;
        }

        $partnerTyping = $msgModel->isPartnerTyping($userId, $partnerId);

        echo json_encode(['success' => true, 'messages' => $newMsgs, 'deleted_updates' => $deletedUpdates, 'edited_updates' => $editedUpdates, 'partnerReadId' => $partnerReadId, 'partner_last_active' => $partnerLastActive, 'partner_typing' => $partnerTyping, 'server_time' => time()]);
        break;

    // ─── Typing indicator ───────────────────────────────────────
    case 'typing':
        $partnerId = (int)($_POST['partner_id'] ?? 0);
        if ($partnerId <= 0) {
            echo json_encode(['error' => 'Invalid partner.']);
            exit;
        }
        $canMsg = $msgModel->canMessage($userId, $partnerId);
        if (!$canMsg['allowed']) {
            echo json_encode(['error' => $canMsg['reason']]);
            exit;
        }
        $msgModel->setTyping($userId, $partnerId);
        echo json_encode(['success' => true]);
        break;

    case 'typing_stop':
        $partnerId = (int)($_POST['partner_id'] ?? 0);
        if ($partnerId > 0) {
            $msgModel->stopTyping($userId, $partnerId);
        }
        echo json_encode(['success' => true]);
        break;

    // ─── Edit a message ───────────────────────────────────────────
    case 'edit':
        $messageId = (int)($_POST['message_id'] ?? 0);
        $newBody = trim($_POST['body'] ?? '');

        if ($messageId <= 0 || $newBody === '') {
            echo json_encode(['error' => 'Invalid message or empty body.']);
            exit;
        }

        if ($msgModel->editMessage($messageId, $userId, $newBody)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Cannot edit this message. You can only edit your own messages within 1 hour.']);
        }
        break;

    // ─── Delete for me ────────────────────────────────────────────
    case 'delete_for_me':
        $messageId = (int)($_POST['message_id'] ?? 0);
        if ($messageId <= 0) {
            echo json_encode(['error' => 'Invalid message.']);
            exit;
        }
        $msgModel->deleteForMe($messageId, $userId);
        echo json_encode(['success' => true]);
        break;

    // ─── Delete for everyone ──────────────────────────────────────
    case 'delete_for_everyone':
        $messageId = (int)($_POST['message_id'] ?? 0);
        if ($messageId <= 0) {
            echo json_encode(['error' => 'Invalid message.']);
            exit;
        }
        if ($msgModel->deleteForEveryone($messageId, $userId)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Cannot delete. You can only delete your own messages within 1 hour.']);
        }
        break;

    // ─── Mark as read ─────────────────────────────────────────────
    case 'mark_read':
        $convId = (int)($_POST['conversation_id'] ?? 0);
        $lastMsgId = (int)($_POST['last_message_id'] ?? 0);
        if ($convId > 0 && $lastMsgId > 0) {
            $msgModel->markAsRead($convId, $userId, $lastMsgId);
        }
        echo json_encode(['success' => true]);
        break;

    // ─── Delete conversation ──────────────────────────────────────
    case 'delete_conversation':
        $convId = (int)($_POST['conversation_id'] ?? 0);
        if ($convId <= 0) {
            echo json_encode(['error' => 'Invalid conversation.']);
            exit;
        }
        $msgModel->deleteConversation($convId, $userId);
        echo json_encode(['success' => true]);
        break;

    // ─── Block user ───────────────────────────────────────────────
    case 'block_user':
        $blockedId = (int)($_POST['blocked_id'] ?? 0);
        if ($blockedId <= 0 || $blockedId === $userId) {
            echo json_encode(['error' => 'Invalid user.']);
            exit;
        }
        $msgModel->blockUser($userId, $blockedId);
        echo json_encode(['success' => true]);
        break;

    // ─── Unblock user ─────────────────────────────────────────────
    case 'unblock_user':
        $blockedId = (int)($_POST['blocked_id'] ?? 0);
        if ($blockedId <= 0) {
            echo json_encode(['error' => 'Invalid user.']);
            exit;
        }
        $msgModel->unblockUser($userId, $blockedId);
        echo json_encode(['success' => true]);
        break;

    // ─── Get blocked users ────────────────────────────────────────
    case 'get_blocked':
        $blocked = $msgModel->getBlockedUsers($userId);
        echo json_encode(['success' => true, 'blocked' => $blocked]);
        break;

    // ─── Get unread count (for navbar polling) ────────────────────
    case 'unread_count':
        $count = $msgModel->getUnreadTotal($userId);
        echo json_encode(['success' => true, 'count' => $count]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action.']);
        break;
}
