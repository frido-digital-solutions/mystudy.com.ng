<?php
require_once __DIR__ . '/config/config.php';
require_login();

$page_title = 'Messages';
$userId = (int)$_SESSION['user_id'];

require_once __DIR__ . '/models/Message.php';
$msgModel = new Message($pdo);
$conversations = $msgModel->getConversations($userId);

include __DIR__ . '/includes/header.php';
?>

<style>
    .msg-page { max-width: 720px; margin: 0 auto; }
    .msg-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .msg-header h1 { font-weight: 800; font-size: 1.8rem; color: #0f172a; margin: 0; }

    .msg-search { position: relative; margin-bottom: 1.5rem; }
    .msg-search input {
        width: 100%; padding: 0.85rem 1.25rem 0.85rem 3rem;
        border-radius: 14px; border: 2px solid #e2e8f0;
        font-size: 0.95rem; background: #f8fafc; transition: all 0.3s;
    }
    .msg-search input:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,0.1); outline: none; background: #fff; }
    .msg-search i { position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; }

    .conv-list { display: flex; flex-direction: column; gap: 0; }

    .conv-item {
        display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem;
        border-radius: 16px; cursor: pointer; transition: all 0.2s; text-decoration: none; color: inherit;
        border: 1px solid transparent; position: relative;
    }
    .conv-item:hover { background: #f1f5f9; border-color: #e2e8f0; }
    .conv-item.unread { background: #eff6ff; border-color: #bfdbfe; }

    .conv-avatar-wrap {
        position: relative; flex-shrink: 0;
    }
    .conv-avatar { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 2px solid #e2e8f0; }
    .conv-status-dot {
        position: absolute; bottom: 1px; right: 1px;
        width: 14px; height: 14px; border-radius: 50%;
        border: 2.5px solid #fff; background: #9ca3af;
    }
    .conv-status-dot.online { background: #22c55e; }
    .conv-status-dot.offline { background: #9ca3af; }
    .conv-last-seen {
        font-size: 0.68rem; color: #94a3b8; white-space: nowrap;
    }
    .conv-last-seen.online-text {
        color: #22c55e; font-weight: 600;
    }
    .conv-info { flex: 1; min-width: 0; }
    .conv-name { font-weight: 700; font-size: 0.95rem; color: #0f172a; margin-bottom: 2px; }
    .conv-preview { font-size: 0.85rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 350px; }
    .conv-meta { text-align: right; flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
    .conv-time { font-size: 0.75rem; color: #94a3b8; font-weight: 500; white-space: nowrap; }
    .conv-badge { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; font-size: 0.7rem; font-weight: 800; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }

    .conv-delete-btn {
        position: absolute; right: 8px; top: 8px; background: none; border: none; color: #cbd5e1; cursor: pointer;
        font-size: 0.8rem; padding: 4px 6px; border-radius: 6px; transition: all 0.2s; opacity: 0;
    }
    .conv-item:hover .conv-delete-btn { opacity: 1; }
    .conv-delete-btn:hover { color: #ef4444; background: #fef2f2; }

    .empty-state { text-align: center; padding: 4rem 2rem; }
    .empty-state i { font-size: 4rem; color: #cbd5e1; margin-bottom: 1rem; }
    .empty-state h4 { font-weight: 700; color: #334155; margin-bottom: 0.5rem; }
    .empty-state p { color: #94a3b8; font-size: 0.95rem; }

    @media (max-width: 576px) {
        .conv-preview { max-width: 180px; }
        .msg-header h1 { font-size: 1.4rem; }
    }
</style>

<div class="container py-4">
    <div class="msg-page">
        <div class="msg-header">
            <h1><i class="fas fa-comments text-primary me-2"></i>Messages</h1>
            <a href="users.php" class="btn btn-sm" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 12px; font-weight: 600; padding: 0.5rem 1rem;">
                <i class="fas fa-user-plus me-1"></i> New Chat
            </a>
        </div>

        <?php if (!empty($conversations)): ?>
        <div class="msg-search">
            <i class="fas fa-search"></i>
            <input type="text" id="searchConvInput" placeholder="Search conversations..." oninput="filterConversations()">
        </div>
        <?php endif; ?>

        <div class="conv-list" id="convList">
            <?php if (empty($conversations)): ?>
                <div class="empty-state">
                    <i class="far fa-comment-dots"></i>
                    <h4>No messages yet</h4>
                    <p>Visit the <a href="users.php" class="text-decoration-none fw-bold">Find Friends</a> page to discover users and start a conversation!</p>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conv):
                    $isUnread = (int)$conv['unread_count'] > 0;
                    $preview = $conv['last_message_body'] ?? '';
                    if ((int)$conv['last_message_sender_id'] === $userId) {
                        $preview = 'You: ' . $preview;
                    }
                    $timeAgo = '';
                    if ($conv['last_message_time']) {
                        $diff = time() - strtotime($conv['last_message_time']);
                        if ($diff < 60) $timeAgo = 'Now';
                        elseif ($diff < 3600) $timeAgo = floor($diff/60) . 'm';
                        elseif ($diff < 86400) $timeAgo = floor($diff/3600) . 'h';
                        elseif ($diff < 604800) $timeAgo = floor($diff/86400) . 'd';
                        else $timeAgo = date('M j', strtotime($conv['last_message_time']));
                    }
                    $avatarUrl = !empty($conv['partner_avatar'])
                        ? SITE_URL . '/' . ltrim($conv['partner_avatar'], '/')
                        : SITE_URL . '/assets/img/default-avatar.png';
                ?>
                <a href="<?php echo SITE_URL; ?>/conversation.php?with=<?php echo (int)$conv['partner_id']; ?>"
                   class="conv-item <?php echo $isUnread ? 'unread' : ''; ?>"
                   data-name="<?php echo htmlspecialchars(strtolower($conv['partner_name'])); ?>">
                    <div class="conv-avatar-wrap">
                        <img src="<?php echo $avatarUrl; ?>" class="conv-avatar" alt="">
                        <?php
                        $partnerOnline = false;
                        $partnerLastSeenLabel = '';
                        if (!empty($conv['partner_last_active'])) {
                            $plaDiff = time() - strtotime($conv['partner_last_active']);
                            if ($plaDiff < 300) {
                                $partnerOnline = true;
                                $partnerLastSeenLabel = 'Online';
                            } elseif ($plaDiff < 3600) {
                                $partnerLastSeenLabel = floor($plaDiff / 60) . 'm';
                            } elseif ($plaDiff < 86400) {
                                $partnerLastSeenLabel = floor($plaDiff / 3600) . 'h';
                            } elseif ($plaDiff < 604800) {
                                $partnerLastSeenLabel = floor($plaDiff / 86400) . 'd';
                            } else {
                                $partnerLastSeenLabel = date('M j', strtotime($conv['partner_last_active']));
                            }
                        }
                        ?>
                        <span class="conv-status-dot <?php echo $partnerOnline ? 'online' : 'offline'; ?>"></span>
                    </div>
                    <div class="conv-info">
                        <div class="conv-name">
                            <?php echo htmlspecialchars($conv['partner_name']); ?>
                            <?php if ($partnerLastSeenLabel): ?>
                            <span class="conv-last-seen <?php echo $partnerOnline ? 'online-text' : ''; ?>"><?php echo $partnerLastSeenLabel; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="conv-preview"><?php echo htmlspecialchars(mb_substr($preview, 0, 60)); ?></div>
                    </div>
                    <div class="conv-meta">
                        <span class="conv-time"><?php echo $timeAgo; ?></span>
                        <?php if ($isUnread): ?>
                        <span class="conv-badge"><?php echo $conv['unread_count'] > 9 ? '9+' : $conv['unread_count']; ?></span>
                        <?php endif; ?>
                    </div>
                    <button class="conv-delete-btn" onclick="event.preventDefault(); event.stopPropagation(); deleteConv(<?php echo $conv['id']; ?>, this);" title="Delete conversation">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function filterConversations() {
    var q = document.getElementById('searchConvInput').value.toLowerCase();
    document.querySelectorAll('.conv-item').forEach(function(el) {
        var name = el.getAttribute('data-name') || '';
        el.style.display = name.includes(q) ? '' : 'none';
    });
}

function deleteConv(convId, btn) {
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
            fd.append('conversation_id', convId);
            fetch('<?php echo SITE_URL; ?>/api_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(d => {
                    if (d.success) btn.closest('.conv-item').remove();
                });
        }
    });
}
</script>

<?php
$hide_main_footer = true;
include __DIR__ . '/includes/footer.php'; ?>
