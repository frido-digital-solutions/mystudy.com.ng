<?php
// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch pending counts globally for the sidebar
$pendingCounts = [
    'materials' => 0,
    'payments' => 0,
    'rewards' => 0,
    'reports' => 0,
    'contact_messages' => 0
];
try {
    if (isset($pdo)) {
        try {
            $pendingCounts['materials'] = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'pending'")->fetchColumn();
        } catch (Exception $e) {
            $pendingCounts['materials'] = 0;
        }
        
        try {
            $pendingCounts['payments'] = (int)$pdo->query("SELECT COUNT(*) FROM quiz_payments WHERE status = 'pending'")->fetchColumn();
        } catch (Exception $e) {
            $pendingCounts['payments'] = 0;
        }
        
        try {
            $pendingCounts['rewards'] = (int)$pdo->query("SELECT COUNT(*) FROM reward_claims WHERE status = 'pending'")->fetchColumn();
        } catch (Exception $e) {
            $pendingCounts['rewards'] = 0;
        }
        
        try {
            $pendingCounts['reports'] = (int)$pdo->query("SELECT COUNT(*) FROM discussion_reports WHERE status = 'pending'")->fetchColumn();
        } catch (Exception $e) {
            $pendingCounts['reports'] = 0;
        }
        
        try {
            $pendingCounts['contact_messages'] = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
        } catch (Exception $e) {
            $pendingCounts['contact_messages'] = 0;
        }
    }
} catch (Exception $e) {
    // Fail silently if database connection fails
}
?>

<!-- Sidebar -->
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-2">
        <div class="px-3 pb-2 mb-2" style="border-bottom: 1px solid rgba(255,255,255,0.08);">
            <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="sidebar-brand">
                <i class="fas fa-graduation-cap"></i>
                <span class="fs-6 fw-bold"><?php echo SITE_NAME; ?></span>
            </a>
            <small class="section" style="margin-top: 0; padding-left: 1.25rem;">Admin Panel</small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['index.php','dashboard.php']) ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/dashboard.php">
                    <i class="fas fa-th-large me-2"></i>
                    Dashboard
                </a>
            </li>
            
            <li class="nav-item mt-2">
                <small class="section px-3">CONTENT</small>
            </li>
            
            <?php if (admin_can('posts')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'posts.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/posts.php">
                    <i class="far fa-newspaper me-2"></i>
                    Posts
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (admin_can('materials')): ?>
            <li class="nav-item">
                <a class="nav-link d-flex justify-content-between align-items-center flex-wrap <?php echo $current_page === 'materials.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/materials.php">
                    <span><i class="fas fa-book-open me-2"></i> Study Materials</span>
                    <?php if ($pendingCounts['materials'] > 0): ?>
                        <span class="badge bg-danger rounded-pill" style="font-size: 0.75rem;"><?php echo $pendingCounts['materials']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (admin_can('tutorials')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'tutorials.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/tutorials.php">
                    <i class="fab fa-youtube me-2"></i>
                    Tutorials
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (admin_can('quizzes')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['quizzes.php','quiz_edit.php']) ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/quizzes.php">
                    <i class="fas fa-brain me-2"></i>
                    Quizzes
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (admin_can('discussions')): ?>
            <li class="nav-item">
                <a class="nav-link d-flex justify-content-between align-items-center flex-wrap <?php echo $current_page === 'discussions.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/discussions.php">
                    <span><i class="fas fa-users-cog me-2"></i> Forum Moderation</span>
                    <?php if ($pendingCounts['reports'] > 0): ?>
                        <span class="badge bg-danger rounded-pill" style="font-size: 0.75rem;"><?php echo $pendingCounts['reports']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (admin_can('contact_messages')): ?>
            <li class="nav-item mt-2">
                <small class="section px-3">COMMUNICATION</small>
            </li>
            
            <li class="nav-item">
                <a class="nav-link d-flex justify-content-between align-items-center flex-wrap <?php echo $current_page === 'contact-messages.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/contact-messages.php">
                    <span><i class="fas fa-envelope me-2"></i> Contact Messages</span>
                    <?php if ($pendingCounts['contact_messages'] > 0): ?>
                        <span class="badge bg-danger rounded-pill" style="font-size: 0.75rem;"><?php echo $pendingCounts['contact_messages']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (admin_can('payments') || admin_can('rewards') || admin_can('access_codes')): ?>
            <li class="nav-item mt-2">
                <small class="section px-3">FINANCE</small>
            </li>
            <?php endif; ?>
            
            <?php if (admin_can('payments')): ?>
            <li class="nav-item">
                <a class="nav-link d-flex justify-content-between align-items-center flex-wrap <?php echo $current_page === 'payments.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/payments.php">
                    <span><i class="fas fa-credit-card me-2"></i> Payments</span>
                    <?php if ($pendingCounts['payments'] > 0): ?>
                        <span class="badge bg-danger rounded-pill" style="font-size: 0.75rem;"><?php echo $pendingCounts['payments']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (admin_can('rewards')): ?>
            <li class="nav-item">
                <a class="nav-link d-flex justify-content-between align-items-center flex-wrap <?php echo $current_page === 'rewards.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/rewards.php">
                    <span><i class="fas fa-gift me-2"></i> Rewards</span>
                    <?php if ($pendingCounts['rewards'] > 0): ?>
                        <span class="badge bg-danger rounded-pill" style="font-size: 0.75rem;"><?php echo $pendingCounts['rewards']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (admin_can('access_codes')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'access_codes.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/access_codes.php">
                    <i class="fas fa-key me-2"></i>
                    Access Codes
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (admin_can('users') || is_super_admin()): ?>
            <li class="nav-item mt-2">
                <small class="section px-3">USERS</small>
            </li>
            <?php endif; ?>
            
            <?php if (admin_can('users')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/users.php">
                    <i class="fas fa-users me-2"></i>
                    All Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'admin_notifications.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/admin_notifications.php">
                    <i class="fas fa-bullhorn me-2"></i>
                    Admin Broadcasts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'app_push.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/app_push.php">
                    <i class="fas fa-mobile-screen-button me-2"></i>
                    App Push Broadcast
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (is_super_admin()): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'admins.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/admins.php">
                    <i class="fas fa-user-shield me-2"></i>
                    Admins
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (admin_can('settings') || admin_can('maintenance') || is_super_admin()): ?>
            <li class="nav-item mt-2">
                <small class="section px-3">SYSTEM</small>
            </li>
            <?php endif; ?>
            
            <?php if (admin_can('settings')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/settings.php">
                    <i class="fas fa-sliders-h me-2"></i>
                    Settings
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (admin_can('maintenance')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'maintenance-control.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/maintenance-control.php">
                    <i class="fas fa-tools me-2"></i>
                    Maintenance Control
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (is_super_admin()): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'backup.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/backup.php">
                    <i class="fas fa-database me-2"></i>
                    Backup
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (is_super_admin()): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'migrate_domain.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/migrate_domain.php">
                    <i class="fas fa-right-left me-2"></i>
                    Domain Migration
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (is_super_admin()): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'activity-log.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/activity-log.php">
                    <i class="fas fa-history me-2"></i>
                    Activity Log
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item mt-3 mb-3">
                <a class="nav-link text-danger" href="<?php echo SITE_URL; ?>/logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Logout
                </a>
            </li>
        </ul>
    </div>
</nav>
