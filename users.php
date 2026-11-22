<?php
require_once __DIR__ . '/config/config.php';
// No require_login() — guests can browse users

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Gamification.php';

$userModel = new User($pdo);
$currentUserId = is_logged_in() ? (int)$_SESSION['user_id'] : 0;

// Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'online';

// Build WHERE + ORDER
$whereExtra = "";
$orderBy = "last_active DESC";

if ($filter === 'newest') {
    $orderBy = "created_at DESC";
} elseif ($filter === 'oldest') {
    $orderBy = "created_at ASC";
} elseif ($filter === 'university') {
    $whereExtra = " AND user_type = 'university'";
} elseif ($filter === 'secondary') {
    $whereExtra = " AND user_type = 'secondary'";
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Fetch total users count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id != ?{$whereExtra}");
$countStmt->execute([$currentUserId]);
$totalUsers = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalUsers / $limit));

// Fetch users for current page
$stmt = $pdo->prepare("SELECT id, name, profile_picture, last_active, user_type, points, show_level_on_users_page FROM users WHERE id != ?{$whereExtra} ORDER BY {$orderBy} LIMIT ? OFFSET ?");
$stmt->bindValue(1, $currentUserId, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// The directory shows presence as well, so the same switch that hides an active
// status in chats hides it here: no timestamp to format, and the neutral word
// that formatUserStatus() already returns for a person nobody has just seen.
$presence = presence_for_users(array_column($users, 'id'), $pdo);

// Helper function to format last active status
function formatUserStatus($lastActive)
{
    if (!$lastActive)
        return ['text' => 'Offline', 'class' => 'status-offline', 'dot' => '#9ca3af'];

    $last = strtotime($lastActive);
    $now = time();
    $diff = $now - $last;

    if ($diff < 120) {
        return ['text' => 'Online', 'class' => 'status-online', 'dot' => '#10b981'];
    }
    if ($diff < 3600) {
        $mins = floor($diff / 60);
        return ['text' => "Active {$mins}m ago", 'class' => 'status-recent', 'dot' => '#f59e0b'];
    }
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return ['text' => "Active {$hours}h ago", 'class' => 'status-offline', 'dot' => '#9ca3af'];
    }

    $days = floor($diff / 86400);
    if ($days === 1)
        return ['text' => "Active yesterday", 'class' => 'status-offline', 'dot' => '#9ca3af'];

    if ($days > 10) {
        $dateStr = date("M j", $last);
        return ['text' => "Active $dateStr", 'class' => 'status-offline', 'dot' => '#9ca3af'];
    }

    return ['text' => "Active {$days}d ago", 'class' => 'status-offline', 'dot' => '#9ca3af'];
}

$pageTitle = "Find Friends - MyStudy";
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* Container Layout */
.users-page-wrapper {
    min-height: 80vh;
    width: 100%;
    padding-bottom: 2rem;
    overflow-x: hidden;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 1.25rem 1.5rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    flex-wrap: wrap;
    gap: 0.75rem;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.header-msg-btn {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 0.55rem 1.25rem;
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    transition: all 0.2s;
    box-shadow: 0 2px 6px rgba(59,130,246,0.3);
}
.header-msg-btn:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    box-shadow: 0 4px 10px rgba(59,130,246,0.4);
}

/* Filter Pills */
.filter-pills {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
}

.filter-pill {
    padding: 0.4rem 0.85rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #64748b;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.filter-pill:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: #eff6ff;
}

.filter-pill.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

/* User Grid */
.user-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1rem;
    align-items: start;
}

/* User Card */
.user-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    transition: all 0.2s ease;
}

.user-card:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

/* Avatar & Status */
.avatar-wrapper {
    position: relative;
    flex-shrink: 0;
    width: 48px;
    height: 48px;
}

.user-avatar {
    display: block;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e2e8f0;
    background-color: #f1f5f9;
}

/* CSS default avatar - eliminates broken image shaking */
.default-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.1rem;
    border: 2px solid rgba(255,255,255,0.3);
}
.default-avatar.av-uni { background: linear-gradient(135deg, #3b82f6, #6366f1); }
.default-avatar.av-sec { background: linear-gradient(135deg, #10b981, #059669); }
.default-avatar.av-other { background: linear-gradient(135deg, #94a3b8, #64748b); }

.status-indicator {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 13px;
    height: 13px;
    border-radius: 50%;
    border: 2.5px solid #ffffff;
    z-index: 2;
}

/* User Info */
.user-info {
    flex: 1;
    min-width: 0;
}

.user-name-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.2rem;
}

.user-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.level-tag {
    font-size: 0.6rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
    border-radius: 6px;
    white-space: nowrap;
    flex-shrink: 0;
}
.level-tag-uni { background: rgba(99,102,241,0.1); color: #6366f1; border: 1px solid rgba(99,102,241,0.15); }
.level-tag-sec { background: rgba(16,185,129,0.1); color: #059669; border: 1px solid rgba(16,185,129,0.15); }
.level-tag-rank { background: rgba(245,158,11,0.08); color: #d97706; border: 1px solid rgba(245,158,11,0.15); }

.user-status-text {
    font-size: 0.78rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: #64748b;
}

.status-online { color: #10b981; font-weight: 600; }
.status-recent { color: #f59e0b; font-weight: 500; }
.status-offline { color: #94a3b8; }

/* Card Action Buttons — Desktop: icon circles stacked vertically */
.card-actions-desktop {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    flex-shrink: 0;
}
.btn-card-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    transition: all 0.2s ease;
    text-decoration: none !important;
    border: 1px solid;
}
.btn-card-icon.ic-msg {
    background: #eff6ff;
    color: #3b82f6;
    border-color: #bfdbfe;
}
.btn-card-icon.ic-msg:hover { background: #3b82f6; color: #fff; border-color: #3b82f6; }
.btn-card-icon.ic-profile {
    background: #f8fafc;
    color: #64748b;
    border-color: #e2e8f0;
}
.btn-card-icon.ic-profile:hover { background: #e2e8f0; color: #334155; }

/* Card Action Buttons — Mobile: full-width labeled footer row */
.card-actions-mobile {
    display: none; /* hidden on desktop */
}
.card-actions-mobile .btn-mobile-action {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.5rem 0;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    border: 1px solid;
}
.btn-mobile-action.ma-profile {
    background: #f8fafc;
    color: #475569;
    border-color: #e2e8f0;
}
.btn-mobile-action.ma-profile:hover { background: #e2e8f0; color: #1e293b; }
.btn-mobile-action.ma-msg {
    background: #eff6ff;
    color: #3b82f6;
    border-color: #bfdbfe;
}
.btn-mobile-action.ma-msg:hover { background: #3b82f6; color: #fff; border-color: #3b82f6; }

/* Pagination */
.pagination-wrapper {
    margin-top: 2rem;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
}

.page-btn {
    background: #ffffff;
    color: #3b82f6;
    border: 1px solid #bfdbfe;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.page-btn:hover:not(.disabled) {
    background: #3b82f6;
    border-color: #2563eb;
    color: white;
}

.page-btn.disabled {
    opacity: 0.5;
    pointer-events: none;
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #94a3b8;
}

.page-info {
    color: #64748b;
    font-size: 0.85rem;
    background: #f8fafc;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    font-weight: 500;
}

/* ===== MOBILE ===== */
@media (max-width: 600px) {
    .users-page-wrapper { padding-top: 0.75rem !important; }

    /* Premium mobile header */
    .page-header {
        margin-bottom: 0.85rem;
        padding: 1rem;
        border-radius: 14px;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    .page-title { font-size: 1.2rem; gap: 0.45rem; }
    .page-title i { font-size: 1rem; }
    .header-msg-btn {
        padding: 0.45rem 0.85rem;
        font-size: 0.8rem;
        border-radius: 10px;
        gap: 0.35rem;
    }

    .filter-pills {
        margin-bottom: 0.85rem;
        gap: 0.3rem;
        overflow-x: auto;
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        padding-bottom: 2px;
    }
    .filter-pills::-webkit-scrollbar { display: none; }
    .filter-pill { font-size: 0.72rem; padding: 0.3rem 0.65rem; }

    /* Card layout: stack vertically on mobile */
    .user-grid {
        grid-template-columns: 1fr;
        gap: 0.65rem;
    }

    .user-card {
        flex-direction: column;
        align-items: stretch;
        padding: 1rem;
        gap: 0;
        border-radius: 14px;
    }

    /* Top row: avatar + info (horizontal within vertical card) */
    .card-top-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .avatar-wrapper { width: 44px; height: 44px; }
    .user-avatar, .default-avatar { width: 44px; height: 44px; font-size: 0.95rem; }
    .status-indicator { width: 11px; height: 11px; }
    .user-name { font-size: 0.9rem; }
    .user-status-text { font-size: 0.72rem; }
    .level-tag { font-size: 0.55rem; padding: 0.1rem 0.35rem; }

    /* Hide desktop icon buttons on mobile */
    .card-actions-desktop { display: none; }

    /* Show mobile labeled buttons */
    .card-actions-mobile {
        display: flex !important;
        gap: 0.5rem;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid #f1f5f9;
    }

    .pagination-wrapper { margin-top: 1.5rem; gap: 0.5rem; }
    .page-btn { padding: 0.4rem 0.75rem; font-size: 0.8rem; }
    .page-info { font-size: 0.75rem; padding: 0.3rem 0.75rem; }
}
</style>

<div class="users-page-wrapper bg-light pt-4">
<div class="container">
    <!-- Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-users" style="color: #818cf8;"></i>
            Find Friends
        </h1>
        <a href="<?php echo is_logged_in() ? 'messages.php' : 'login.php?redirect=messages.php'; ?>" class="header-msg-btn">
            <i class="fas fa-comment-dots"></i> <span>Messages</span>
        </a>
    </div>

    <!-- Filter Pills -->
    <div class="filter-pills">
        <a href="?filter=online" class="filter-pill <?php echo $filter === 'online' ? 'active' : ''; ?>"><i class="fas fa-circle" style="font-size:0.45rem;color:#10b981;"></i> Online</a>
        <a href="?filter=newest" class="filter-pill <?php echo $filter === 'newest' ? 'active' : ''; ?>"><i class="fas fa-bolt"></i> Newest</a>
        <a href="?filter=oldest" class="filter-pill <?php echo $filter === 'oldest' ? 'active' : ''; ?>"><i class="fas fa-history"></i> Oldest</a>
        <a href="?filter=university" class="filter-pill <?php echo $filter === 'university' ? 'active' : ''; ?>"><i class="fas fa-university"></i> University</a>
        <a href="?filter=secondary" class="filter-pill <?php echo $filter === 'secondary' ? 'active' : ''; ?>"><i class="fas fa-school"></i> Secondary</a>
    </div>

    <?php if (empty($users)): ?>
    <div class="alert alert-info" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); color: #60a5fa; border-radius: 12px;">
        <i class="fas fa-info-circle me-2"></i> No users found for this filter.
    </div>
    <?php else: ?>

    <div class="user-grid">
        <?php foreach ($users as $u):
            $status = formatUserStatus($presence[(int)$u['id']]['last_active'] ?? $u['last_active']);
            $hasAvatar = !empty($u['profile_picture']);
            $userType = $u['user_type'] ?? '';

            // Level calculation
            $pts = (int)($u['points'] ?? 0);
            $userLvl = Gamification::getLevel($pts);
            if ($userType === 'university') {
                $rankName = Gamification::UNI_LEVEL_NAMES[$userLvl] ?? "Level $userLvl";
            } elseif ($userType === 'secondary') {
                $rankName = Gamification::SEC_LEVEL_NAMES[$userLvl] ?? "Level $userLvl";
            } else {
                $rankName = Gamification::UNI_LEVEL_NAMES[$userLvl] ?? "Level $userLvl";
            }
            $showLevel = (int)($u['show_level_on_users_page'] ?? 1);
        ?>
        <div class="user-card">
            <!-- Top Row: Avatar + Info + Desktop Actions -->
            <div class="card-top-row" style="display:flex; align-items:center; gap:0.85rem; width:100%;">
                <!-- Avatar -->
                <a href="profile_public.php?id=<?php echo $u['id']; ?>" class="text-decoration-none flex-shrink-0">
                    <div class="avatar-wrapper">
                        <?php if ($hasAvatar): ?>
                            <img src="<?php echo htmlspecialchars($u['profile_picture']); ?>" alt="" class="user-avatar">
                        <?php else: ?>
                            <div class="default-avatar <?php echo $userType === 'university' ? 'av-uni' : ($userType === 'secondary' ? 'av-sec' : 'av-other'); ?>">
                                <i class="fas <?php echo $userType === 'university' ? 'fa-user-graduate' : ($userType === 'secondary' ? 'fa-book-reader' : 'fa-user'); ?>"></i>
                            </div>
                        <?php endif; ?>
                        <div class="status-indicator" style="background-color: <?php echo $status['dot']; ?>"></div>
                    </div>
                </a>

                <!-- Info -->
                <div class="user-info">
                    <div class="user-name-row">
                        <a href="profile_public.php?id=<?php echo $u['id']; ?>" class="text-decoration-none" style="min-width:0; flex:1;">
                            <h3 class="user-name"><?php echo htmlspecialchars($u['name']); ?></h3>
                        </a>
                        <?php if ($userType === 'university'): ?>
                            <span class="level-tag level-tag-uni">UNI</span>
                        <?php elseif ($userType === 'secondary'): ?>
                            <span class="level-tag level-tag-sec">SEC</span>
                        <?php endif; ?>
                        <?php if ($showLevel): ?>
                            <span class="level-tag level-tag-rank"><i class="fas fa-star" style="font-size:0.5rem;"></i> Lvl <?php echo $userLvl; ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="user-status-text <?php echo $status['class']; ?>">
                        <i class="fas fa-circle" style="font-size: 0.4rem;"></i>
                        <?php echo $status['text']; ?>
                    </p>
                </div>

                <!-- Desktop Actions (icon buttons) -->
                <div class="card-actions-desktop">
                    <?php if (is_logged_in()): ?>
                    <a href="conversation.php?with=<?php echo $u['id']; ?>" class="btn-card-icon ic-msg" title="Message">
                        <i class="fas fa-paper-plane"></i>
                    </a>
                    <?php else: ?>
                    <a href="login.php?redirect=<?php echo urlencode('users.php'); ?>" class="btn-card-icon ic-msg" title="Login to Message">
                        <i class="fas fa-paper-plane"></i>
                    </a>
                    <?php endif; ?>
                    <a href="profile_public.php?id=<?php echo $u['id']; ?>" class="btn-card-icon ic-profile" title="View Profile">
                        <i class="far fa-user"></i>
                    </a>
                </div>
            </div>

            <!-- Mobile Actions (full labeled buttons, hidden on desktop) -->
            <div class="card-actions-mobile">
                <a href="profile_public.php?id=<?php echo $u['id']; ?>" class="btn-mobile-action ma-profile">
                    <i class="far fa-user"></i> View Profile
                </a>
                <?php if (is_logged_in()): ?>
                <a href="conversation.php?with=<?php echo $u['id']; ?>" class="btn-mobile-action ma-msg">
                    <i class="fas fa-paper-plane"></i> Message
                </a>
                <?php else: ?>
                <a href="login.php?redirect=<?php echo urlencode('users.php'); ?>" class="btn-mobile-action ma-msg">
                    <i class="fas fa-right-to-bracket"></i> Login to Message
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrapper">
        <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo htmlspecialchars($filter); ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
            <i class="fas fa-chevron-left"></i> Prev
        </a>
        <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
        <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo htmlspecialchars($filter); ?>" class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
            Next <i class="fas fa-chevron-right"></i>
        </a>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
