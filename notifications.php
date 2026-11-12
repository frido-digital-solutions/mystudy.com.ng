<?php
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Notification.php';

$userId = (int)$_SESSION['user_id'];
$notifModel = new Notification($pdo);

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read' && !empty($_POST['notification_id'])) {
        $notifModel->markAsRead((int)$_POST['notification_id'], $userId);
    } elseif ($action === 'mark_all_read') {
        $notifModel->markAllAsRead($userId);
    }
    redirect(SITE_URL . '/notifications.php');
}

$notifications = $notifModel->getUserNotifications($userId, 50);
$unreadCount = $notifModel->getUnreadCount($userId);

$page_title = 'Notifications';
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0">
                    <i class="fas fa-bell me-2 text-primary"></i>Notifications
                    <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-danger ms-2" style="font-size:0.75rem;"><?php echo $unreadCount; ?> new</span>
                    <?php endif; ?>
                </h2>
                <?php if ($unreadCount > 0): ?>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">
                        <i class="fas fa-check-double me-1"></i>Mark all read
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <?php if (empty($notifications)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-bell-slash text-muted mb-3" style="font-size: 3rem;"></i>
                    <h5 class="text-muted">No notifications yet</h5>
                    <p class="text-muted mb-0">You'll see notifications here when something important happens.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="list-group shadow-sm" style="border-radius: 16px; overflow: hidden;">
                <?php foreach ($notifications as $notif): 
                    $isUnread = !(int)$notif['is_read'];
                    $timeAgo = '';
                    $diff = time() - strtotime($notif['created_at']);
                    if ($diff < 60) $timeAgo = 'Just now';
                    elseif ($diff < 3600) $timeAgo = floor($diff / 60) . 'm ago';
                    elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . 'h ago';
                    elseif ($diff < 604800) $timeAgo = floor($diff / 86400) . 'd ago';
                    else $timeAgo = date('M j, Y', strtotime($notif['created_at']));
                    
                    // Icon by type
                    $icon = 'fa-bell';
                    $iconColor = '#6b7280';
                    switch ($notif['type']) {
                        case 'challenge_complete': $icon = 'fa-trophy'; $iconColor = '#f59e0b'; break;
                        case 'quiz_result': $icon = 'fa-check-circle'; $iconColor = '#10b981'; break;
                        case 'welcome': $icon = 'fa-hand-wave'; $iconColor = '#3b82f6'; break;
                        case 'payment': $icon = 'fa-credit-card'; $iconColor = '#8b5cf6'; break;
                        case 'system': $icon = 'fa-info-circle'; $iconColor = '#6b7280'; break;
                    }
                ?>
                <div class="list-group-item border-0 border-bottom px-4 py-3 <?php echo $isUnread ? 'bg-light' : ''; ?>" style="<?php echo $isUnread ? 'border-left: 4px solid #10b981 !important;' : ''; ?>">
                    <div class="d-flex align-items-start gap-3">
                        <div class="flex-shrink-0" style="width:40px; height:40px; border-radius:12px; background:<?php echo $iconColor; ?>15; color:<?php echo $iconColor; ?>; display:flex; align-items:center; justify-content:center; font-size:1.1rem;">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="mb-1 <?php echo $isUnread ? 'fw-bold' : 'fw-normal'; ?>" style="font-size:0.95rem;">
                                    <?php echo htmlspecialchars($notif['title']); ?>
                                </h6>
                                <small class="text-muted flex-shrink-0 ms-2"><?php echo $timeAgo; ?></small>
                            </div>
                            <p class="mb-1 text-muted" style="font-size:0.85rem; white-space:pre-line;"><?php echo htmlspecialchars($notif['message']); ?></p>
                            <div class="d-flex gap-2 mt-2">
                                <?php if (!empty($notif['link'])): ?>
                                <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="font-size:0.8rem;">
                                    View Details
                                </a>
                                <?php endif; ?>
                                <?php if ($isUnread): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-muted p-0" style="font-size:0.8rem;">Mark as read</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
