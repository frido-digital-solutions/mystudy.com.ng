<?php
require_once __DIR__ . '/config/config.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$partnerId = (int)($_GET['with'] ?? 0);

if ($partnerId <= 0 || $partnerId === $userId) {
    header("Location: " . SITE_URL . "/messages.php");
    exit;
}

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Message.php';

$userModel = new User($pdo);
if (!$userModel->getById($partnerId)) {
    header("Location: " . SITE_URL . "/messages.php");
    exit;
}

$msgModel = new Message($pdo);
$conv = $msgModel->getOrCreateConversation($userId, $partnerId);
$convId = (int)$conv['id'];

// Check block status
$iBlockedThem = $msgModel->hasBlocked($userId, $partnerId);
$theyBlockedMe = $msgModel->hasBlocked($partnerId, $userId);
$isBlocked = $iBlockedThem || $theyBlockedMe;

// Check privacy
$canMsg = $msgModel->canMessage($userId, $partnerId);

// Fetch initial messages
$messages = $msgModel->getMessages($convId, $userId, 50);

// Fetch partner read status
$partnerReadId = $msgModel->getPartnerReadId($convId, $partnerId);

// Mark as read
if (!empty($messages)) {
    $lastMsg = end($messages);
    $msgModel->markAsRead($convId, $userId, (int)$lastMsg['id']);
}

// Last seen logic, from the helper that owns the rule. Whoever switched off
// their active status reads as plain Offline here, in the message list and on
// the phone, instead of a timestamp they never agreed to share.
$presState = presence_for_users([$partnerId], $pdo)[$partnerId] ?? ['last_active' => null];
$lastActiveTs = !empty($presState['last_active']) ? strtotime($presState['last_active']) : 0;
$lastSeenText = '<span class="text-muted small" style="font-size:0.75rem;">Offline</span>';
if ($lastActiveTs) {
    if (time() - $lastActiveTs < 300) {
        $lastSeenText = '<span class="text-success small fw-medium" style="font-size:0.75rem;"><i class="fas fa-circle me-1" style="font-size:0.5rem;"></i>Online</span>';
    } else {
        $diff = time() - $lastActiveTs;
        if ($diff < 3600) $lastSeenText = '<span class="text-muted small" style="font-size:0.75rem;">Last seen ' . floor($diff/60) . 'm ago</span>';
        elseif ($diff < 86400) $lastSeenText = '<span class="text-muted small" style="font-size:0.75rem;">Last seen ' . floor($diff/3600) . 'h ago</span>';
        else $lastSeenText = '<span class="text-muted small" style="font-size:0.75rem;">Last seen ' . date('M j', $lastActiveTs) . '</span>';
    }
}

$partnerName = htmlspecialchars($userModel->name ?? '');
$partnerAvatar = !empty($userModel->profile_picture)
    ? SITE_URL . '/' . ltrim($userModel->profile_picture, '/')
    : SITE_URL . '/assets/img/default-avatar.png';

$page_title = 'Chat with ' . $partnerName;
$userModel->getById($userId);
$myName = $userModel->name ?? '';
$userModel->getById($partnerId); // restore partner data
include __DIR__ . '/includes/header.php';
?>

<style>
    /* Prevent body scroll entirely for the chat app experience */
    html, body { height: 100%; overflow: hidden; }
    body { background: #f1f5f9; }
    
    .chat-wrapper {
        max-width: 760px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        height: calc(100vh - 56px); /* Fallback */
        height: calc(100dvh - 56px); /* Modern Mobile */
        background: #fff;
        box-shadow: 0 0 40px rgba(0,0,0,0.03);
        position: relative;
    }
    
    /* Hide BackToTop globally on the chat page */
    #backToTop { display: none !important; }
    /* Top Bar */
    .chat-topbar {
        background: #fff; border-radius: 16px 16px 0 0; padding: 1rem 1.25rem;
        display: flex; align-items: center; gap: 1rem;
        border-bottom: 1px solid #e2e8f0; flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .chat-back { color: #64748b; font-size: 1.2rem; text-decoration: none; }
    .chat-back:hover { color: #0f172a; }
    .chat-avatar-wrap {
        position: relative;
        display: inline-block;
    }
    .chat-partner-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0; }
    .chat-status-dot {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid #ffffff;
        background-color: #9ca3af; /* offline by default */
        box-shadow: 0 0 0 2px rgba(15,23,42,0.15);
    }
    .chat-status-dot.online {
        background-color: #22c55e;
    }
    .chat-status-dot.offline {
        background-color: #9ca3af;
    }
    .chat-partner-name { font-weight: 700; font-size: 1rem; color: #0f172a; }
    .chat-actions .btn { border: none; background: none; color: #94a3b8; font-size: 1.1rem; padding: 6px 8px; border-radius: 8px; }
    .chat-actions .btn:hover { color: #0f172a; background: #f1f5f9; }

    /* Message Area */
    .chat-messages {
        flex: 1; overflow-y: auto; padding: 1.5rem 1.25rem;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        display: flex; flex-direction: column; gap: 0.5rem;
        scroll-behavior: smooth;
    }
    .chat-messages::-webkit-scrollbar { width: 5px; }
    .chat-messages::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

    .msg-bubble-wrap { display: flex; flex-direction: column; max-width: 75%; position: relative; }
    .msg-bubble-wrap.sent { align-self: flex-end; align-items: flex-end; }
    .msg-bubble-wrap.received { align-self: flex-start; align-items: flex-start; }

    .msg-bubble {
        padding: 0.65rem 1rem; border-radius: 18px; font-size: 0.92rem; line-height: 1.45;
        word-wrap: break-word; position: relative; max-width: 100%;
        user-select: none; -webkit-user-select: none; /* Prevent text selection on long press */
    }
    .msg-bubble-wrap.sent .msg-bubble {
        background: linear-gradient(135deg, #3b82f6, #2563eb); color: white;
        border-bottom-right-radius: 4px;
    }
    .msg-bubble-wrap.received .msg-bubble {
        background: #fff; color: #1e293b; border: 1px solid #e2e8f0;
        border-bottom-left-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    .msg-meta { font-size: 0.7rem; color: #94a3b8; margin-top: 3px; display: flex; align-items: center; gap: 6px; }
    .msg-edited { font-style: italic; font-size: 0.65rem; opacity: 0.7; }
    .msg-deleted-label { font-style: italic; color: #94a3b8; }

    /* Reply quote block inside bubbles */
    .reply-quote {
        background: rgba(0,0,0,0.08); border-left: 3px solid #3b82f6;
        border-radius: 6px; padding: 4px 8px; margin-bottom: 6px;
        font-size: 0.8rem; line-height: 1.3; max-height: 50px; overflow: hidden;
        cursor: pointer;
    }
    .msg-bubble-wrap.sent .reply-quote { border-left-color: rgba(255,255,255,0.6); background: rgba(255,255,255,0.15); }
    .reply-quote-name { font-weight: 700; font-size: 0.72rem; margin-bottom: 1px; }
    .msg-bubble-wrap.received .reply-quote-name { color: #3b82f6; }
    .msg-bubble-wrap.sent .reply-quote-name { color: rgba(255,255,255,0.85); }

    /* Reply preview bar above input */
    .reply-bar {
        display: none; background: #1e293b; padding: 8px 12px;
        border-top: 1px solid #334155; align-items: center; gap: 8px;
        font-size: 0.82rem; color: #94a3b8;
    }
    .reply-bar.show { display: flex; }
    .reply-bar-text { flex: 1; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
    .reply-bar-text strong { color: #e2e8f0; }
    .reply-bar-close {
        background: none; border: none; color: #94a3b8; font-size: 1rem;
        cursor: pointer; padding: 2px 6px; border-radius: 6px;
    }
    .reply-bar-close:hover { color: #ef4444; background: rgba(239,68,68,0.1); }

    /* Swipe hint arrow */
    .swipe-reply-hint {
        position: absolute; left: -40px; top: 50%; transform: translateY(-50%);
        opacity: 0; transition: opacity 0.15s; pointer-events: none;
        color: #3b82f6; font-size: 1.2rem;
    }

    /* Scroll-to-bottom FAB */
    .scroll-bottom-btn {
        display: none; position: absolute; bottom: 80px; right: 16px;
        width: 40px; height: 40px; border-radius: 50%;
        background: rgba(15,23,42,0.7); color: white; border: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3); cursor: pointer;
        align-items: center; justify-content: center; font-size: 1rem;
        z-index: 20; backdrop-filter: blur(4px);
        transition: opacity 0.2s;
    }
    .scroll-bottom-btn.show { display: flex; }

    /* Inline edit – compact, no extra space */
    .msg-edit-container { display: flex; flex-direction: column; gap: 6px; width: 100%; min-width: 0; }
    .msg-edit-input {
        width: 100%; min-width: 0; padding: 0.5rem 0.75rem; font-size: 0.92rem; line-height: 1.45;
        border: 1px solid rgba(255,255,255,0.4); border-radius: 12px; resize: none; background: rgba(0,0,0,0.1);
        color: inherit; box-sizing: border-box;
    }
    .msg-bubble-wrap.received .msg-edit-input {
        background: #f8fafc; border-color: #e2e8f0; color: #1e293b;
    }
    .msg-edit-input:focus { outline: none; border-color: rgba(255,255,255,0.7); }
    .msg-bubble-wrap.received .msg-edit-input:focus { border-color: #94a3b8; }
    .msg-edit-actions { display: flex; justify-content: flex-end; gap: 6px; margin-top: 4px; flex-shrink: 0; }
    .msg-edit-actions .btn { padding: 4px 10px; font-size: 0.8rem; border-radius: 8px; }

    /* Context menu */
    .msg-ctx-menu {
        position: absolute; background: #fff; border: 1px solid #e2e8f0;
        border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        z-index: 100; min-width: 160px; overflow: hidden; display: none;
    }
    .msg-ctx-menu.show { display: block; }
    .msg-ctx-item {
        display: flex; align-items: center; gap: 8px; padding: 10px 14px;
        font-size: 0.85rem; color: #334155; cursor: pointer; transition: background 0.15s;
        border: none; background: none; width: 100%; text-align: left;
    }
    .msg-ctx-item:hover { background: #f1f5f9; }
    .msg-ctx-item.danger { color: #ef4444; }
    .msg-ctx-item.danger:hover { background: #fef2f2; }

    /* Input Bar */
    .chat-inputbar {
        background: #1e293b; border-radius: 0 0 16px 16px; padding: 0.75rem 1rem;
        display: flex; align-items: flex-end; gap: 0.75rem;
        border-top: 1px solid #334155; flex-shrink: 0;
        box-shadow: 0 -4px 18px rgba(0,0,0,0.25);
    }
    .chat-inputbar textarea {
        flex: 1; border: 1px solid #475569; border-radius: 14px; padding: 0.7rem 1rem;
        font-size: 0.92rem; resize: none; max-height: 120px; min-height: 42px;
        font-family: inherit; transition: all 0.2s; outline: none;
        background: #0f172a; color: #f8fafc;
    }
    .chat-inputbar textarea::placeholder {
        color: #64748b;
    }
    .chat-inputbar textarea:focus { border-color: #3b82f6; background: #1e293b; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
    .chat-send-btn {
        width: 44px; height: 44px; border-radius: 50%; border: none;
        background: linear-gradient(135deg, #3b82f6, #2563eb); color: white;
        font-size: 1.1rem; display: flex; align-items: center; justify-content: center;
        cursor: pointer; transition: all 0.2s; flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(59,130,246,0.3);
    }
    .chat-send-btn:hover { transform: scale(1.05); box-shadow: 0 6px 16px rgba(59,130,246,0.4); }
    .chat-send-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

    .chat-blocked-bar {
        background: #fef2f2; border: 1px solid #fecaca; border-radius: 0 0 16px 16px;
        padding: 1rem 1.25rem; text-align: center; color: #991b1b; font-weight: 600;
        font-size: 0.9rem; flex-shrink: 0;
    }
    .chat-blocked-bar a { color: #2563eb; text-decoration: underline; cursor: pointer; }

    .date-separator { text-align: center; margin: 1rem 0; }
    .date-separator span {
        background: #e2e8f0; color: #64748b; font-size: 0.72rem; font-weight: 700;
        padding: 4px 12px; border-radius: 10px; text-transform: uppercase; letter-spacing: 0.5px;
    }

    /* Simple toast for chat actions */
    .chat-toast {
        position: fixed;
        left: 50%;
        bottom: 20px;
        transform: translateX(-50%);
        background: rgba(15,23,42,0.95);
        color: #e5e7eb;
        padding: 0.5rem 1rem;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 500;
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease, transform 0.2s ease;
        z-index: 9999;
    }
    .chat-toast.show {
        opacity: 1;
        transform: translate(-50%, -5px);
    }

    @media (max-width: 576px) {
        .chat-wrapper { height: calc(100vh - 80px); height: calc(100dvh - 80px); }
        .msg-bubble-wrap { max-width: 85%; }
        .chat-topbar { border-radius: 0; }
        .chat-inputbar { border-radius: 0; }
    }
</style>

<div class="container-fluid px-0 px-sm-3 py-0 py-sm-3">
<div class="chat-wrapper">

    <!-- Top Bar -->
    <div class="chat-topbar">
        <a href="<?php echo SITE_URL; ?>/messages.php" class="chat-back"><i class="fas fa-arrow-left"></i></a>
        <a href="<?php echo SITE_URL; ?>/profile_public.php?id=<?php echo $partnerId; ?>" class="text-decoration-none">
            <div class="chat-avatar-wrap">
                <img src="<?php echo $partnerAvatar; ?>" class="chat-partner-avatar" alt="">
                <?php $isOnline = $lastActiveTs && (time() - $lastActiveTs < 300); ?>
                <span id="chatStatusDot" class="chat-status-dot <?php echo $isOnline ? 'online' : 'offline'; ?>"></span>
            </div>
        </a>
        <div class="flex-grow-1" style="line-height: 1.2;">
            <a href="<?php echo SITE_URL; ?>/profile_public.php?id=<?php echo $partnerId; ?>" class="text-decoration-none">
                <div class="chat-partner-name"><?php echo $partnerName; ?></div>
                <div id="lastSeenContainer"><?php echo $lastSeenText; ?></div>
            </a>
        </div>
        <div class="chat-actions dropdown">
            <button class="btn" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
            <ul class="dropdown-menu dropdown-menu-end" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/profile_public.php?id=<?php echo $partnerId; ?>"><i class="fas fa-user me-2 text-muted"></i>View Profile</a></li>
                <?php if ($iBlockedThem): ?>
                <li><a class="dropdown-item" href="#" onclick="unblockUser(); return false;"><i class="fas fa-unlock me-2 text-success"></i>Unblock User</a></li>
                <?php else: ?>
                <li><a class="dropdown-item text-danger" href="#" onclick="blockUser(); return false;"><i class="fas fa-ban me-2"></i>Block User</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="#" onclick="deleteConversation(); return false;"><i class="fas fa-trash-alt me-2"></i>Delete Conversation</a></li>
            </ul>
        </div>
    </div>

    <!-- Messages -->
    <div class="chat-messages" id="chatMessages">
        <?php if (empty($messages)): ?>
            <div class="text-center py-5">
                <i class="far fa-comment-dots text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="text-muted mt-2" style="font-size: 0.9rem;">Send a message to start the conversation!</p>
            </div>
        <?php else: ?>
            <?php
            $lastDate = '';
            for ($i = 0; $i < count($messages); $i++):
                $msg = $messages[$i];
                $isSent = (int)$msg['sender_id'] === $userId;
                $msgDate = date('M j, Y', strtotime($msg['created_at']));
                $msgTime = date('g:i A', strtotime($msg['created_at']));
                $msgTs = strtotime($msg['created_at']);
                $isEdited = (int)$msg['is_edited'];
                $isDeletedForEveryone = !empty($msg['deleted_by_user_id']);
                $deletedByName = $msg['deleted_by_name'] ?? $msg['sender_name'] ?? 'User';
                $canEdit = !$isDeletedForEveryone && $isSent && (time() - $msgTs <= 3600);

                // Grouping logic
                $nextMsg = $messages[$i + 1] ?? null;
                $isGroupNode = true;
                if ($nextMsg && (int)$nextMsg['sender_id'] === (int)$msg['sender_id']) {
                    if (strtotime($nextMsg['created_at']) - $msgTs < 120) {
                        $isGroupNode = false;
                    }
                }

                // Ticks logic
                $ticksHtml = '';
                if ($isSent) {
                    if ($msg['id'] <= $partnerReadId) {
                        $ticksHtml = '<i class="fas fa-check-double text-info ms-1" style="font-size: 0.65rem;" title="Read"></i>';
                    } else {
                        $ticksHtml = '<i class="fas fa-check-double text-secondary ms-1" style="font-size: 0.65rem;" title="Delivered"></i>';
                    }
                }
            ?>
                <?php if ($msgDate !== $lastDate): $lastDate = $msgDate; ?>
                <div class="date-separator"><span><?php echo $msgDate === date('M j, Y') ? 'Today' : ($msgDate === date('M j, Y', strtotime('-1 day')) ? 'Yesterday' : $msgDate); ?></span></div>
                <?php endif; ?>

                <div class="msg-bubble-wrap <?php echo $isSent ? 'sent' : 'received'; ?>"
                     id="msg-<?php echo $msg['id']; ?>"
                     data-msg-id="<?php echo $msg['id']; ?>"
                     data-is-sent="<?php echo $isSent ? '1' : '0'; ?>"
                     data-can-edit="<?php echo $canEdit ? '1' : '0'; ?>"
                     data-deleted-everyone="<?php echo $isDeletedForEveryone ? '1' : '0'; ?>"
                     <?php if (!empty($msg['reply_to_id'])): ?>data-reply-to-id="<?php echo (int)$msg['reply_to_id']; ?>"<?php endif; ?>
                     oncontextmenu="<?php echo $isDeletedForEveryone ? '' : 'showCtxMenu(event, ' . $msg['id'] . ')'; ?>"
                     onmousedown="startLongPress(event, <?php echo $msg['id']; ?>)" 
                     onmouseup="cancelLongPress()" 
                     onmouseleave="cancelLongPress()" 
                     ontouchstart="startLongPress(event, <?php echo $msg['id']; ?>)" 
                     ontouchend="cancelLongPress()" 
                     ontouchmove="cancelLongPress()">
                     
                    <div class="msg-bubble" style="cursor: pointer;" onclick="toggleMeta(this)">
                        <?php if ($isDeletedForEveryone): ?>
                            <span class="text-muted fst-italic" style="font-size: 0.9rem;">Message deleted by <?php echo htmlspecialchars($deletedByName); ?></span>
                        <?php else: ?>
                            <?php if (!empty($msg['reply_to_body'])): ?>
                            <div class="reply-quote" onclick="event.stopPropagation(); scrollToMsg(<?php echo (int)$msg['reply_to_id']; ?>)">
                                <div class="reply-quote-name"><?php echo htmlspecialchars($msg['reply_to_sender_name'] ?? 'User'); ?></div>
                                <div><?php echo htmlspecialchars(mb_substr($msg['reply_to_body'], 0, 80)); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php echo nl2br(htmlspecialchars($msg['body'])); ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="msg-meta" style="<?php echo $isGroupNode ? '' : 'display: none;'; ?>">
                        <span><?php echo $msgTime; ?></span>
                        <?php if ($isEdited): ?><span class="msg-edited">edited</span><?php endif; ?>
                        <?php echo $ticksHtml; ?>
                    </div>
                </div>
            <?php endfor; ?>
        <?php endif; ?>
    </div>

    <!-- Scroll to bottom button -->
    <button class="scroll-bottom-btn" id="scrollDownBtn" onclick="scrollToBottom()"><i class="fas fa-chevron-down"></i></button>

    <!-- Context Menu -->
    <div class="msg-ctx-menu" id="msgCtxMenu">
        <button class="msg-ctx-item" onclick="replyToMessage()"><i class="fas fa-reply"></i> Reply</button>
        <button class="msg-ctx-item" onclick="copyMessage()"><i class="far fa-copy"></i> Copy Message</button>
        <button class="msg-ctx-item" id="ctxEdit" onclick="editMessage()" style="display:none;"><i class="fas fa-pen"></i> Edit</button>
        <button class="msg-ctx-item danger" onclick="deleteForMe()"><i class="fas fa-eye-slash"></i> Delete for me</button>
        <button class="msg-ctx-item danger" id="ctxDeleteAll" onclick="deleteForEveryone()" style="display:none;"><i class="fas fa-trash-alt"></i> Delete for everyone</button>
    </div>

    <!-- Toast -->
    <div id="chatToast" class="chat-toast"></div>

    <!-- Input Bar / Blocked Bar -->
    <?php if ($isBlocked): ?>
        <div class="chat-blocked-bar">
            <?php if ($iBlockedThem): ?>
                You blocked this user. <a onclick="unblockUser(); return false;">Unblock</a> to send messages.
            <?php else: ?>
                You can't reply to this conversation.
            <?php endif; ?>
        </div>
    <?php elseif (!$canMsg['allowed']): ?>
        <div class="chat-blocked-bar" style="background: #fffbeb; border-color: #fde68a; color: #92400e;">
            <i class="fas fa-lock me-1"></i> <?php echo htmlspecialchars($canMsg['reason']); ?>
        </div>
    <?php else: ?>
        <div class="reply-bar" id="replyBar">
            <i class="fas fa-reply" style="color: #3b82f6;"></i>
            <div class="reply-bar-text"><strong id="replyBarName"></strong> <span id="replyBarBody"></span></div>
            <button class="reply-bar-close" onclick="cancelReply()">&times;</button>
        </div>
        <div class="chat-inputbar">
            <textarea id="msgInput" rows="1" placeholder="Type a message..." onkeydown="handleKey(event)" oninput="autoGrow(this); handleTyping();" onblur="sendTypingStop();"></textarea>
            <button class="chat-send-btn" id="sendBtn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
        </div>
    <?php endif; ?>

</div>
</div>

<script>
var SITE_URL = '<?php echo SITE_URL; ?>';
var PARTNER_ID = <?php echo $partnerId; ?>;
var MY_ID = <?php echo $userId; ?>;
var MY_NAME = <?php echo json_encode($myName); ?>;
var CONV_ID = <?php echo $convId; ?>;
var partnerReadId = <?php echo $partnerReadId; ?>;
var lastMsgId = <?php echo !empty($messages) ? (int)end($messages)['id'] : 0; ?>;
var ctxMsgId = null;
var pollInterval = null;
var typingDebounceTimer = null;
var typingStopTimer = null;
var sendInFlight = false;

// Reply state
var replyToMsgId = null;

// Long Press Logic
var longPressTimer = null;
var isLongPress = false;

function startLongPress(e, msgId) {
    if (e.type === 'touchstart' || e.type === 'mousedown') {
        isLongPress = false;
        longPressTimer = setTimeout(function() {
            isLongPress = true;
            
            // To position menu correctly for touch, we use the first touch coordinate
            if (e.type === 'touchstart' && e.touches.length > 0) {
                e.clientX = e.touches[0].clientX;
                e.clientY = e.touches[0].clientY;
            }
            
            showCtxMenu(e, msgId);
            if (navigator.vibrate) navigator.vibrate(50); // Haptic feedback
        }, 1500); // 1.5 seconds hold
    }
}

function cancelLongPress() {
    if (longPressTimer) {
        clearTimeout(longPressTimer);
        longPressTimer = null;
    }
}

function toggleMeta(el) {
    if (isLongPress) {
        isLongPress = false;
        return; // ignore standard click if we just triggered long press menu
    }
    
    var meta = el.nextElementSibling;
    if (meta.style.display === 'none') meta.style.display = 'flex';
    else meta.style.display = 'none';
}

// Auto-scroll to bottom on load (instant, no animation)
document.addEventListener('DOMContentLoaded', function() {
    var chatEl = document.getElementById('chatMessages');
    chatEl.scrollTop = chatEl.scrollHeight;
    startPolling();

    // Show/hide scroll-to-bottom button based on scroll position
    chatEl.addEventListener('scroll', function() {
        var btn = document.getElementById('scrollDownBtn');
        if (!btn) return;
        var distFromBottom = chatEl.scrollHeight - chatEl.scrollTop - chatEl.clientHeight;
        if (distFromBottom > 200) {
            btn.classList.add('show');
        } else {
            btn.classList.remove('show');
        }
    });
});

// Clear typing status when leaving page
window.addEventListener('pagehide', sendTypingStop);
window.addEventListener('beforeunload', sendTypingStop);

function scrollToBottom() {
    var el = document.getElementById('chatMessages');
    el.scrollTop = el.scrollHeight;
}

function autoGrow(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function handleKey(e) {
    if (e.key === 'Enter') {
        if (window.innerWidth > 768) {
            // Desktop: Enter sends, Shift+Enter new line
            if (!e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        } else {
            // Mobile: Enter always new line, do not prevent default, do not send
        }
    }
}

function sendTyping() {
    var fd = new FormData();
    fd.append('action', 'typing');
    fd.append('partner_id', PARTNER_ID);
    fetch(SITE_URL + '/api_messages.php', { method: 'POST', body: fd }).catch(function(){});
}

function sendTypingStop() {
    if (typingDebounceTimer) { clearTimeout(typingDebounceTimer); typingDebounceTimer = null; }
    if (typingStopTimer) { clearTimeout(typingStopTimer); typingStopTimer = null; }
    var fd = new FormData();
    fd.append('action', 'typing_stop');
    fd.append('partner_id', PARTNER_ID);
    fetch(SITE_URL + '/api_messages.php', { method: 'POST', body: fd }).catch(function(){});
}

function handleTyping() {
    if (typingStopTimer) clearTimeout(typingStopTimer);
    if (typingDebounceTimer) clearTimeout(typingDebounceTimer);
    typingDebounceTimer = setTimeout(function() { sendTyping(); typingDebounceTimer = null; }, 500);
    typingStopTimer = setTimeout(function() { sendTypingStop(); typingStopTimer = null; }, 3000);
}

function sendMessage() {
    var input = document.getElementById('msgInput');
    var body = input.value.trim();
    if (!body || sendInFlight) return;

    sendTypingStop();
    sendInFlight = true;
    // Lock the draft while in flight so a failed send restores it verbatim.
    input.readOnly = true;

    var fd = new FormData();
    fd.append('action', 'send');
    fd.append('recipient_id', PARTNER_ID);
    fd.append('body', body);
    if (replyToMsgId) {
        fd.append('reply_to_id', replyToMsgId);
    }

    function finishSend() {
        sendInFlight = false;
        input.readOnly = false;
    }

    function restoreDraft(msg) {
        finishSend();
        input.value = body;
        autoGrow(input);
        showChatToast(msg);
        input.focus();
    }

    fetch(SITE_URL + '/api_messages.php', { method: 'POST', body: fd })
        .then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function(d) {
            if (d && d.success && d.message) {
                finishSend();
                if (d.partnerReadId !== undefined) {
                    partnerReadId = d.partnerReadId;
                    updateTicks();
                }
                appendMessage(d.message, true);
                lastMsgId = d.message.id;
                cancelReply();
                input.value = '';
                input.style.height = 'auto';
                // Re-focus to keep mobile keyboard open
                setTimeout(function() { input.focus(); }, 50);
            } else {
                restoreDraft((d && d.error) || 'Message not sent. Please try again.');
            }
        })
        .catch(function() {
            if (!sendInFlight) return;
            restoreDraft('Message not sent. Check your connection and try again.');
        });
}

function appendMessage(msg, isSent) {
    var wrap = document.createElement('div');
    wrap.className = 'msg-bubble-wrap ' + (isSent ? 'sent' : 'received');
    wrap.id = 'msg-' + msg.id;
    wrap.setAttribute('data-msg-id', msg.id);
    wrap.setAttribute('data-is-sent', isSent ? '1' : '0');
    wrap.setAttribute('data-can-edit', isSent ? '1' : '0');
    wrap.setAttribute('oncontextmenu', 'showCtxMenu(event, ' + msg.id + ')');

    var time = new Date(msg.created_at).toLocaleTimeString([], {hour: 'numeric', minute:'2-digit'});
    var edited = msg.is_edited == 1 ? '<span class="msg-edited">edited</span>' : '';
    
    var ticks = '';
    if (isSent) {
        if (msg.id <= partnerReadId) {
            ticks = '<i class="fas fa-check-double text-info ms-1" style="font-size: 0.65rem;" title="Read"></i>';
        } else {
            ticks = '<i class="fas fa-check text-secondary ms-1" style="font-size: 0.65rem;" title="Sent"></i>';
        }
    }

    // Hide previous message's meta if sent closely together
    var lastWrap = document.querySelector('.msg-bubble-wrap:last-child');
    if (lastWrap && lastWrap.getAttribute('data-is-sent') === (isSent ? '1' : '0')) {
        var lastMeta = lastWrap.querySelector('.msg-meta');
        if (lastMeta) lastMeta.style.display = 'none';
    }

    // Add long press logic for JS-injected messages
    wrap.onmousedown = function(e) { startLongPress(e, msg.id); };
    wrap.onmouseup = cancelLongPress;
    wrap.onmouseleave = cancelLongPress;
    wrap.ontouchstart = function(e) { startLongPress(e, msg.id); };
    wrap.ontouchend = cancelLongPress;
    wrap.ontouchmove = cancelLongPress;

    // Build reply quote HTML if this is a reply
    var replyHtml = '';
    if (msg.reply_to_body && msg.reply_to_id) {
        var rName = escapeHtml(msg.reply_to_sender_name || 'User');
        var rBody = escapeHtml((msg.reply_to_body || '').substring(0, 80));
        replyHtml = '<div class="reply-quote" onclick="event.stopPropagation(); scrollToMsg(' + msg.reply_to_id + ')">' +
            '<div class="reply-quote-name">' + rName + '</div>' +
            '<div>' + rBody + '</div></div>';
    }

    wrap.innerHTML = '<div class="msg-bubble" style="cursor:pointer;" onclick="toggleMeta(this)">' + replyHtml + escapeHtml(msg.body).replace(/\n/g, '<br>') + '</div>'
        + '<div class="msg-meta" style="display:flex;"><span>' + time + '</span>' + edited + ticks + '</div>';

    // Remove empty state if exists
    var emptyState = document.querySelector('#chatMessages .text-center.py-5');
    if (emptyState) emptyState.remove();

    document.getElementById('chatMessages').appendChild(wrap);
    scrollToBottom();
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ─── Polling for new messages ─────────────────────────
function startPolling() {
    pollInterval = setInterval(pollMessages, 3000);
}

function pollMessages() {
    var fd = new FormData();
    fd.append('action', 'check_new');
    fd.append('partner_id', PARTNER_ID);
    fd.append('after_id', lastMsgId);

    fetch(SITE_URL + '/api_messages.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            if (d.success) {
                if (d.partnerReadId !== undefined && d.partnerReadId > partnerReadId) {
                    partnerReadId = d.partnerReadId;
                    updateTicks();
                }
                
                if (d.partner_typing) {
                    showPartnerTyping();
                } else if (d.partner_last_active !== undefined && d.partner_last_active) {
                    updateLastSeenUI(d.partner_last_active, d.server_time);
                }

                if (d.deleted_updates && d.deleted_updates.length > 0) {
                    d.deleted_updates.forEach(function(du) {
                        var wrap = document.getElementById('msg-' + du.id);
                        if (wrap && wrap.getAttribute('data-deleted-everyone') !== '1') {
                            updateMessageToDeleted(wrap, du.deleted_by_name || 'User');
                        }
                    });
                }

                if (d.edited_updates && d.edited_updates.length > 0) {
                    d.edited_updates.forEach(function(eu) {
                        var wrap = document.getElementById('msg-' + eu.id);
                        if (wrap && wrap.getAttribute('data-editing') !== '1' && wrap.getAttribute('data-deleted-everyone') !== '1') {
                            updateMessageContent(wrap, eu.body, true);
                        }
                    });
                }

                if (d.messages && d.messages.length > 0) {
                    d.messages.forEach(function(msg) {
                        var existing = document.getElementById('msg-' + msg.id);
                        if (msg.deleted_for_everyone && msg.deleted_by_name) {
                            if (existing) {
                                updateMessageToDeleted(existing, msg.deleted_by_name);
                            } else {
                                appendMessage(msg, parseInt(msg.sender_id) === MY_ID, true);
                            }
                        } else if (!existing) {
                            appendMessage(msg, parseInt(msg.sender_id) === MY_ID);
                        }
                        if (msg.id > lastMsgId) lastMsgId = msg.id;
                    });
                }
            }
        }).catch(function(){});
}

function updateTicks() {
    var sentWraps = document.querySelectorAll('.msg-bubble-wrap.sent');
    sentWraps.forEach(function(wrap) {
        var id = parseInt(wrap.getAttribute('data-msg-id'));
        var meta = wrap.querySelector('.msg-meta');
        if (meta) {
            var existing = meta.querySelector('.fa-check, .fa-check-double');
            if (existing) existing.remove();
            
            var icon = document.createElement('i');
            if (id <= partnerReadId) {
                icon.className = 'fas fa-check-double text-info ms-1';
                icon.title = 'Read';
            } else {
                icon.className = 'fas fa-check-double text-secondary ms-1';
                icon.title = 'Delivered';
            }
            icon.style.fontSize = '0.65rem';
            meta.appendChild(icon);
        }
    });
}

function updateMessageToDeleted(wrap, deletedByName) {
    if (!wrap) return;
    wrap.setAttribute('data-deleted-everyone', '1');
    wrap.setAttribute('data-can-edit', '0');
    wrap.removeAttribute('oncontextmenu');
    var bubble = wrap.querySelector('.msg-bubble');
    if (bubble) {
        bubble.innerHTML = '<span class="text-muted fst-italic" style="font-size: 0.9rem;">Message deleted by ' + escapeHtml(deletedByName || 'User') + '</span>';
    }
}

function updateMessageContent(wrap, newBody, addEditedBadge) {
    if (!wrap || !newBody) return;
    var bubble = wrap.querySelector('.msg-bubble');
    if (!bubble) return;
    bubble.innerHTML = escapeHtml(newBody).replace(/\n/g, '<br>');
    if (addEditedBadge) {
        var meta = wrap.querySelector('.msg-meta');
        if (meta && !meta.querySelector('.msg-edited')) {
            var ed = document.createElement('span');
            ed.className = 'msg-edited';
            ed.textContent = 'edited';
            meta.appendChild(ed);
        }
    }
}

function showPartnerTyping() {
    var c = document.getElementById('lastSeenContainer');
    if (!c) return;
    c.innerHTML = '<span class="text-primary small fw-medium" style="font-size:0.75rem;"><i class="fas fa-circle-notch fa-spin me-1" style="font-size:0.5rem;"></i>typing...</span>';
    var dot = document.getElementById('chatStatusDot');
    if (dot) { dot.classList.add('online'); dot.classList.remove('offline'); }
}

function updateLastSeenUI(lastActiveStr, serverTime) {
    if (!lastActiveStr) return;
    
    // Parse the datetime string from the server (Y-m-d H:i:s in server timezone)
    var lastActive = new Date(lastActiveStr.replace(/-/g, '/'));
    if (isNaN(lastActive)) return;
    
    // Use server_time (Unix timestamp) for accurate diff calculation
    // This avoids client vs server timezone mismatch
    var diffSecs;
    if (serverTime) {
        // server_time is a Unix epoch (seconds), lastActive is parsed in client TZ
        // Convert lastActive to Unix epoch via: server treats the string as server-local
        // Since we can't know the TZ offset reliably, compute diff from server timestamps
        var lastActiveEpoch = Math.floor(lastActive.getTime() / 1000);
        // Adjust for client/server timezone offset: the serverTime is the true 'now'
        // and lastActiveStr was produced in the same timezone as serverTime
        // So the diff = serverTime - (what serverTime would be for lastActiveStr)
        // We can compute the client's offset by comparing client Date.now() with serverTime
        var clientNowEpoch = Math.floor(Date.now() / 1000);
        var tzOffset = clientNowEpoch - serverTime; // positive if client ahead of server
        diffSecs = serverTime - (lastActiveEpoch - tzOffset);
    } else {
        // Fallback: use client time (less accurate if timezones differ)
        var now = new Date();
        diffSecs = Math.floor((now.getTime() - lastActive.getTime()) / 1000);
    }
    
    if (diffSecs < 0) diffSecs = 0;
    
    var container = document.getElementById('lastSeenContainer');
    if (!container) return;
    
    if (diffSecs < 300) {
        container.innerHTML = '<span class="text-success small fw-medium" style="font-size:0.75rem;"><i class="fas fa-circle me-1" style="font-size:0.5rem;"></i>Online</span>';
        var dot = document.getElementById('chatStatusDot');
        if (dot) {
            dot.classList.add('online');
            dot.classList.remove('offline');
        }
    } else {
        if (diffSecs < 3600) {
            container.innerHTML = '<span class="text-muted small" style="font-size:0.75rem;">Last seen ' + Math.floor(diffSecs/60) + 'm ago</span>';
        } else if (diffSecs < 86400) {
            container.innerHTML = '<span class="text-muted small" style="font-size:0.75rem;">Last seen ' + Math.floor(diffSecs/3600) + 'h ago</span>';
        } else {
            var options = { month: 'short', day: 'numeric' };
            container.innerHTML = '<span class="text-muted small" style="font-size:0.75rem;">Last seen ' + lastActive.toLocaleDateString('en-US', options) + '</span>';
        }
        var dot = document.getElementById('chatStatusDot');
        if (dot) {
            dot.classList.add('offline');
            dot.classList.remove('online');
        }
    }
}

// ─── Context Menu ─────────────────────────────────────
function showCtxMenu(e, msgId) {
    e.preventDefault();
    ctxMsgId = msgId;
    var menu = document.getElementById('msgCtxMenu');
    var wrap = document.getElementById('msg-' + msgId);
    var isSent = wrap.getAttribute('data-is-sent') === '1';
    var canEdit = wrap.getAttribute('data-can-edit') === '1';

    document.getElementById('ctxEdit').style.display = (isSent && canEdit) ? '' : 'none';
    document.getElementById('ctxDeleteAll').style.display = (isSent && canEdit) ? '' : 'none';

    // Position the menu and make it visible off-screen first to measure it
    menu.style.position = 'fixed';
    menu.style.visibility = 'hidden';
    menu.classList.add('show');

    var menuHeight = menu.offsetHeight;
    var menuWidth = menu.offsetWidth || 180;
    var y = e.clientY;
    var x = Math.min(e.clientX, window.innerWidth - menuWidth - 10);

    // Flip upward if menu would clip below viewport
    if (y + menuHeight > window.innerHeight - 10) {
        y = Math.max(10, y - menuHeight);
    }

    menu.style.top = y + 'px';
    menu.style.left = x + 'px';
    menu.style.visibility = 'visible';

    setTimeout(function() {
        document.addEventListener('click', hideCtxMenu, { once: true });
    }, 10);
}

function hideCtxMenu() {
    var menu = document.getElementById('msgCtxMenu');
    menu.classList.remove('show');
    menu.style.visibility = '';
}

// ─── Reply Functions ──────────────────────────────────
function setReply(msgId, senderName, bodyPreview) {
    replyToMsgId = msgId;
    document.getElementById('replyBarName').textContent = senderName;
    document.getElementById('replyBarBody').textContent = bodyPreview.substring(0, 60);
    document.getElementById('replyBar').classList.add('show');
    document.getElementById('msgInput').focus();
}

function cancelReply() {
    replyToMsgId = null;
    document.getElementById('replyBar').classList.remove('show');
}

function replyToMessage() {
    hideCtxMenu();
    if (!ctxMsgId) return;
    var wrap = document.getElementById('msg-' + ctxMsgId);
    if (!wrap) return;
    var bubble = wrap.querySelector('.msg-bubble');
    if (!bubble) return;
    var isSent = wrap.getAttribute('data-is-sent') === '1';
    var name = isSent ? MY_NAME : '<?php echo addslashes($partnerName); ?>';
    var text = extractMessageText(bubble);
    setReply(ctxMsgId, name, text);
}

function scrollToMsg(msgId) {
    var el = document.getElementById('msg-' + msgId);
    if (!el) return;
    // Scroll within the chatMessages container, not the page
    var container = document.getElementById('chatMessages');
    var elTop = el.offsetTop;
    var containerHeight = container.clientHeight;
    var targetScroll = elTop - (containerHeight / 2) + (el.offsetHeight / 2);
    container.scrollTo({ top: Math.max(0, targetScroll), behavior: 'smooth' });
    el.style.transition = 'box-shadow 0.3s';
    el.style.boxShadow = '0 0 0 3px rgba(59,130,246,0.4)';
    setTimeout(function() { el.style.boxShadow = ''; }, 2000);
}

// ─── Swipe-to-Reply Touch Handler ─────────────────────
function initSwipeReply() {
    var chatContainer = document.getElementById('chatMessages');
    if (!chatContainer) return;
    var startX = 0, startY = 0, currentWrap = null, swiping = false;

    chatContainer.addEventListener('touchstart', function(e) {
        var wrap = e.target.closest('.msg-bubble-wrap');
        if (!wrap || wrap.getAttribute('data-deleted-everyone') === '1') return;
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        currentWrap = wrap;
        swiping = false;
    }, { passive: true });

    chatContainer.addEventListener('touchmove', function(e) {
        if (!currentWrap) return;
        var dx = e.touches[0].clientX - startX;
        var dy = Math.abs(e.touches[0].clientY - startY);
        // Only trigger horizontal swipe mode if horizontal distance dominates
        if (dx > 20 && dy < 40) {
            swiping = true;
            cancelLongPress();
            var offset = Math.min(dx - 20, 70);
            currentWrap.style.transform = 'translateX(' + offset + 'px)';
            currentWrap.style.transition = 'none';
        }
    }, { passive: true });

    chatContainer.addEventListener('touchend', function(e) {
        if (!currentWrap) return;
        if (swiping) {
            var dx = e.changedTouches[0].clientX - startX;
            currentWrap.style.transition = 'transform 0.2s';
            currentWrap.style.transform = '';
            if (dx > 60) {
                // Trigger reply
                var msgId = currentWrap.getAttribute('data-msg-id');
                var bubble = currentWrap.querySelector('.msg-bubble');
                var isSent = currentWrap.getAttribute('data-is-sent') === '1';
                var name = isSent ? MY_NAME : '<?php echo addslashes($partnerName); ?>';
                var text = extractMessageText(bubble);
                setReply(parseInt(msgId), name, text);
                if (navigator.vibrate) navigator.vibrate(30);
            }
        }
        currentWrap = null;
        swiping = false;
    }, { passive: true });
}

document.addEventListener('DOMContentLoaded', initSwipeReply);

function extractMessageText(bubble) {
    if (!bubble) return '';
    var textChunks = [];
    for (var i = 0; i < bubble.childNodes.length; i++) {
        var node = bubble.childNodes[i];
        if (node.nodeType === Node.TEXT_NODE) {
            textChunks.push(node.textContent);
        } else if (node.nodeName === 'BR') {
            textChunks.push('\n');
        }
    }
    return textChunks.join('').trim();
}

function copyMessage() {
    var wrap = document.getElementById('msg-' + ctxMsgId);
    var bubble = wrap.querySelector('.msg-bubble');
    navigator.clipboard.writeText(extractMessageText(bubble));
    hideCtxMenu();
    showChatToast('Message copied');
}

function editMessage() {
    hideCtxMenu();
    var wrap = document.getElementById('msg-' + ctxMsgId);
    if (!wrap) return;
    if (wrap.getAttribute('data-editing') === '1') return;

    var bubble = wrap.querySelector('.msg-bubble');
    if (!bubble) return;

    var currentText = extractMessageText(bubble);
    wrap.setAttribute('data-original-html', bubble.innerHTML);
    wrap.setAttribute('data-editing', '1');

    var lineCount = Math.max(1, Math.min(4, (currentText.match(/\n/g) || []).length + 1));
    var editorHtml = ''
        + '<div class="msg-edit-container">'
        + '<textarea class="msg-edit-input" rows="' + lineCount + '" placeholder="Edit message...">' + escapeHtml(currentText) + '</textarea>'
        + '<div class="msg-edit-actions">'
        + '<button type="button" class="btn btn-sm btn-light" onclick="cancelEditMessage(' + ctxMsgId + ')">Cancel</button>'
        + '<button type="button" class="btn btn-sm btn-primary" onclick="saveEditMessage(' + ctxMsgId + ')">Save</button>'
        + '</div></div>';

    bubble.innerHTML = editorHtml;
    var textarea = bubble.querySelector('.msg-edit-input');
    if (textarea) {
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        textarea.addEventListener('input', function() { autoGrowEdit(this); });
        autoGrowEdit(textarea);
    }
}

function autoGrowEdit(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function cancelEditMessage(msgId) {
    var wrap = document.getElementById('msg-' + msgId);
    if (!wrap) return;
    var bubble = wrap.querySelector('.msg-bubble');
    var original = wrap.getAttribute('data-original-html');
    if (bubble && original !== null) {
        bubble.innerHTML = original;
    }
    wrap.removeAttribute('data-original-html');
    wrap.removeAttribute('data-editing');
}

function saveEditMessage(msgId) {
    var wrap = document.getElementById('msg-' + msgId);
    if (!wrap) return;
    var bubble = wrap.querySelector('.msg-bubble');
    var textarea = bubble ? bubble.querySelector('.msg-edit-input') : null;
    if (!textarea) return;

    var newText = textarea.value.trim();
    if (!newText) {
        showChatToast('Message cannot be empty');
        return;
    }

    var fd = new FormData();
    fd.append('action', 'edit');
    fd.append('message_id', msgId);
    fd.append('body', newText);

    fetch(SITE_URL + '/api_messages.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(function(d) {
            if (d.success) {
                bubble.innerHTML = escapeHtml(newText).replace(/\n/g, '<br>');
                var meta = wrap.querySelector('.msg-meta');
                if (meta && !meta.querySelector('.msg-edited')) {
                    meta.innerHTML += '<span class="msg-edited">edited</span>';
                }
                wrap.removeAttribute('data-original-html');
                wrap.removeAttribute('data-editing');
                showChatToast('Message updated');
            } else {
                showChatToast(d.error || 'Failed to edit message');
            }
        })
        .catch(function() {
            showChatToast('Failed to edit message');
        });
}

function deleteForMe() {
    hideCtxMenu();
    Swal.fire({
        title: 'Delete Message?',
        text: 'It will only be removed for you.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            var fd = new FormData();
            fd.append('action', 'delete_for_me');
            fd.append('message_id', ctxMsgId);
            fetch(SITE_URL + '/api_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(d => {
                    if (d.success) document.getElementById('msg-' + ctxMsgId).remove();
                });
        }
    });
}

function deleteForEveryone() {
    hideCtxMenu();
    Swal.fire({
        title: 'Delete for Everyone?',
        text: 'This cannot be undone. The message will be removed from the chat.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            var fd = new FormData();
            fd.append('action', 'delete_for_everyone');
            fd.append('message_id', ctxMsgId);
            fetch(SITE_URL + '/api_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(d => {
                    if (d.success) {
                        var wrap = document.getElementById('msg-' + ctxMsgId);
                        if (wrap) {
                            wrap.setAttribute('data-deleted-everyone', '1');
                            wrap.setAttribute('data-can-edit', '0');
                            wrap.removeAttribute('oncontextmenu');
                            var bubble = wrap.querySelector('.msg-bubble');
                            if (bubble) {
                                bubble.innerHTML = '<span class="text-muted fst-italic" style="font-size: 0.9rem;">Message deleted by ' + escapeHtml(MY_NAME || 'You') + '</span>';
                            }
                        }
                    } else {
                        Swal.fire('Error', d.error || 'Failed to delete message.', 'error');
                    }
                });
        }
    });
}

// ─── Block / Unblock / Delete ─────────────────────────
function blockUser() {
    Swal.fire({
        title: 'Block User?',
        text: 'They won\'t be able to message you anymore.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Yes, block'
    }).then((result) => {
        if (result.isConfirmed) {
            var fd = new FormData();
            fd.append('action', 'block_user');
            fd.append('blocked_id', PARTNER_ID);
            fetch(SITE_URL + '/api_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(d => { if (d.success) location.reload(); });
        }
    });
}

function unblockUser() {
    var fd = new FormData();
    fd.append('action', 'unblock_user');
    fd.append('blocked_id', PARTNER_ID);
    fetch(SITE_URL + '/api_messages.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => { if (d.success) location.reload(); });
}

function deleteConversation() {
    Swal.fire({
        title: 'Delete Conversation?',
        text: 'It will only be removed for you.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Yes, delete'
    }).then((result) => {
        if (result.isConfirmed) {
            var fd = new FormData();
            fd.append('action', 'delete_conversation');
            fd.append('conversation_id', CONV_ID);
            fetch(SITE_URL + '/api_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(d => { if (d.success) window.location.href = SITE_URL + '/messages.php'; });
        }
    });
}

function showChatToast(text) {
    var el = document.getElementById('chatToast');
    if (!el) return;
    el.textContent = text;
    el.classList.add('show');
    clearTimeout(showChatToast._timer);
    showChatToast._timer = setTimeout(function () {
        el.classList.remove('show');
    }, 2000);
}
</script>

<?php $hide_main_footer = true;
include __DIR__ . '/includes/footer.php'; ?>
