<?php
// CRITICAL: Check maintenance mode FIRST - before anything else
require_once __DIR__ . '/maintenance-check.php';

// Start output buffering
ob_start();

// Track user activity (throttled to once every 5 minutes to reduce DB load)
if (isset($_SESSION['user_id']) && isset($pdo)) {
    $now = time();
    if (!isset($_SESSION['_last_active_update']) || ($now - $_SESSION['_last_active_update']) > 300) {
        try {
            require_once __DIR__ . '/../models/User.php';
            $actUser = new User($pdo);
            $actUser->id = (int)$_SESSION['user_id'];
            $actUser->updateLastActive();
            $_SESSION['_last_active_update'] = $now;
        } catch (Exception $e) {}
    }
}

// Admin broadcast modal data (throttled to once every 5 minutes)
$adminNotice = null;
if (isset($_SESSION['user_id']) && isset($pdo)) {
    $now = time();
    if (!isset($_SESSION['_last_admin_notice_check']) || ($now - $_SESSION['_last_admin_notice_check']) > 300) {
        try {
            require_once __DIR__ . '/../models/Notification.php';
            $adminHeaderNotif = new Notification($pdo);
            $adminNotice = $adminHeaderNotif->getLatestUnreadAdminNotice((int)$_SESSION['user_id']);
            $_SESSION['_cached_admin_notice'] = $adminNotice;
            $_SESSION['_last_admin_notice_check'] = $now;
        } catch (Exception $e) {}
    } else {
        $adminNotice = $_SESSION['_cached_admin_notice'] ?? null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' . SITE_NAME : SITE_NAME; ?></title>
    <?php
      $description = $page_description ?? SITE_DESCRIPTION;
      $canonical = SITE_URL . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
      $keywords = trim((string)site_setting('meta_keywords', ''));
      if ($keywords === '') {
          $keywords = 'Mystudy Base, MyStudy Base, My Study Base, My Studybase, Studybase, MyStudy, My Study, Mystudy B, Study Base, ' . SITE_DOMAIN . ', Nigerian students, WAEC past questions, JAMB CBT practice, university study materials, secondary school notes, online quizzes Nigeria, exam preparation Nigeria';
      }
      $logoUrl = site_logo_url() !== '' ? site_logo_url() : SITE_URL . '/assets/img/favicon.png?v=2';
    ?>
    <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($keywords); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical); ?>">
    <meta property="og:site_name" content="<?php echo SITE_NAME; ?>">
    <meta property="og:title" content="<?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' . SITE_NAME : SITE_NAME; ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($description); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonical); ?>">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' . SITE_NAME : SITE_NAME; ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($description); ?>">
    <link rel="sitemap" type="application/xml" title="Sitemap" href="<?php echo SITE_URL; ?>/sitemap.xml.php">
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "EducationalOrganization",
      "name": "<?php echo htmlspecialchars(SITE_NAME); ?>",
      "alternateName": ["Mystudy Base", "My Study Base", "My Studybase", "Studybase", "MyStudy", "My Study", "Study Base", "Mystudy"],
      "url": "<?php echo SITE_URL; ?>",
      "logo": "<?php echo htmlspecialchars($logoUrl); ?>",
      "description": "<?php echo htmlspecialchars(SITE_DESCRIPTION); ?>",
      "areaServed": "Nigeria",
      "sameAs": <?php echo json_encode(array_values(array_map(static fn($social) => $social['url'], site_socials())), JSON_UNESCAPED_SLASHES); ?>
    }
    </script>
    <meta name="site-url" content="<?php echo SITE_URL; ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logoUrl); ?>">
    
    <!-- PWA Manifest & Theme -->
    <link rel="manifest" href="<?php echo SITE_URL; ?>/manifest.json">
    <meta name="theme-color" content="#10b981">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="MyStudy">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($logoUrl); ?>">
    
    <!-- DNS Prefetch & Preconnect for CDNs (speeds up external resource loading) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    
    <!-- CRITICAL: Inline above-the-fold CSS for instant first paint -->
    <style>
        /* === Font Display Overrides (Fixes Lighthouse 20ms warning) === */
        @font-face{font-family:'Font Awesome 6 Free';font-style:normal;font-weight:400;font-display:swap;src:url("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/webfonts/fa-regular-400.woff2") format("woff2")}
        @font-face{font-family:'Font Awesome 6 Free';font-style:normal;font-weight:900;font-display:swap;src:url("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/webfonts/fa-solid-900.woff2") format("woff2")}
        @font-face{font-family:'Font Awesome 6 Brands';font-style:normal;font-weight:400;font-display:swap;src:url("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/webfonts/fa-brands-400.woff2") format("woff2")}
        
        /* === Critical CSS: Layout, Navbar, Hero === */
        *,*::before,*::after{box-sizing:border-box}
        :root{--primary:#10b981;--primary-dark:#059669;--text-dark:#1e293b;--text-muted:#64748b;--bg-light:#f8fafc}
        body{margin:0;font-family:'Poppins',system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:var(--text-dark);background:#fff;-webkit-font-smoothing:antialiased}
        .container{width:100%;max-width:1200px;margin:0 auto;padding:0 .75rem}
        .row{display:flex;flex-wrap:wrap;--bs-gutter-x:1.5rem;--bs-gutter-y:0;margin-right:calc(-.5 * var(--bs-gutter-x));margin-left:calc(-.5 * var(--bs-gutter-x))}
        .row>*{flex-shrink:0;max-width:100%;padding-right:calc(var(--bs-gutter-x) * .5);padding-left:calc(var(--bs-gutter-x) * .5);margin-top:var(--bs-gutter-y)}
        .row.g-3{--bs-gutter-x:1rem;--bs-gutter-y:1rem}
        .row.g-4{--bs-gutter-x:1.5rem;--bs-gutter-y:1.5rem}
        .col-4{flex:0 0 auto;width:33.333333%}
        .col-6{flex:0 0 auto;width:50%}
        .col-12{flex:0 0 auto;width:100%}
        .col-lg-3,.col-lg-4,.col-lg-6,.col-lg-8{flex:0 0 auto;width:100%}
        @media(min-width:992px){.col-lg-3{width:25%}.col-lg-4{width:33.333333%}.col-lg-6{width:50%}.col-lg-8{width:66.666667%}.d-lg-block{display:block!important}.d-lg-none{display:none!important}.d-none.d-lg-flex{display:flex!important}.order-lg-1{order:1}.order-lg-2{order:2}}
        @media(min-width:768px){.col-md-3{flex:0 0 auto;width:25%}.col-md-4{flex:0 0 auto;width:33.333%}.col-md-5{flex:0 0 auto;width:41.666667%}.col-md-6{flex:0 0 auto;width:50%}.col-md-7{flex:0 0 auto;width:58.333333%}}
        .d-flex{display:flex}.d-none{display:none!important}
        .align-items-center{align-items:center}
        .justify-content-between{justify-content:space-between}
        .text-center{text-align:center}
        .text-decoration-none{text-decoration:none}
        .fw-bold{font-weight:700}
        .mb-2{margin-bottom:.5rem}.mb-3{margin-bottom:1rem}.mb-4{margin-bottom:1.5rem}.mb-5{margin-bottom:3rem}
        .me-2{margin-right:.5rem}.me-3{margin-right:1rem}
        .mt-3{margin-top:1rem}
        .position-relative{position:relative}
        
        /* Navbar critical */
        .navbar{background:#fff;padding:.75rem 0;box-shadow:0 1px 3px rgba(0,0,0,.08);position:sticky;top:0;z-index:1030}
        .navbar .container{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap}
        .navbar-brand{font-weight:800;font-size:1.3rem;color:var(--primary)!important;text-decoration:none;display:flex;align-items:center;gap:.5rem}
        .navbar-nav{list-style:none;display:flex;gap:0;margin:0;padding:0}
        .nav-link{padding:.5rem 1rem;color:var(--text-dark);text-decoration:none;font-weight:500;font-size:.95rem}
        .navbar-toggler{background:none;border:none;padding:.25rem;cursor:pointer}
        /* We remove the custom black ::before/::after lines and let Bootstrap handle the icon, just keeping critical display logic */
        .collapse:not(.show){display:none}
        @media(min-width:992px){.collapse{display:flex!important}.navbar-toggler{display:none}.navbar-nav{align-items:center}}
        
        /* Hero critical */
        .hero-warm{padding:3rem 0;position:relative;overflow:hidden;background:linear-gradient(135deg,#f0fdf4 0%,#ecfdf5 50%,#d1fae5 100%)}
        .hero-tagline{font-size:clamp(1.5rem,4vw,2.8rem);font-weight:800;line-height:1.2;margin-bottom:1rem}
        .hero-tagline .highlight{color:var(--primary);background:linear-gradient(135deg,var(--primary),var(--primary-dark));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .hero-description{color:var(--text-muted);font-size:1rem;line-height:1.7;margin-bottom:1.5rem}
        .btn-start{display:inline-flex;align-items:center;gap:.5rem;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff!important;padding:.75rem 1.5rem;border-radius:50px;text-decoration:none;font-weight:700;font-size:.95rem;border:none;transition:all .3s}
        .btn-outline-hero{display:inline-flex;align-items:center;gap:.5rem;background:transparent;color:var(--primary)!important;padding:.75rem 1.5rem;border-radius:50px;text-decoration:none;font-weight:600;border:2px solid var(--primary);transition:all .3s}
        .hero-cta-group{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:2rem}
        
        /* Trust stats */
        .trust-stats{display:flex;gap:2rem}
        .trust-stat-number{font-weight:800;font-size:1.3rem;color:var(--text-dark)}
        .trust-stat-label{font-size:.8rem;color:var(--text-muted)}
        
        /* Badges */
        .badge{font-size:.75rem;padding:.25rem .5rem;border-radius:50px;font-weight:600}
        .bg-danger{background:#ef4444!important;color:#fff}
        .bg-primary{background:#3b82f6!important;color:#fff}
        .rounded-pill{border-radius:50px}
        .position-absolute{position:absolute}
        
        /* User avatar */
        .user-avatar{width:32px;height:32px;border-radius:50%;object-fit:cover}
        
        /* Button styles */
        .btn-login,.btn-signup{padding:.5rem 1.25rem;border-radius:50px;text-decoration:none;font-weight:600;font-size:.9rem;transition:all .3s}
        .btn-login{color:var(--text-dark);border:1px solid #e2e8f0}
        .btn-signup{background:var(--primary);color:#fff!important;border:none}
        
        /* Dropdown basic */
        .dropdown-menu{display:none;position:absolute;background:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.12);padding:.5rem 0;min-width:200px;z-index:1000;border:1px solid #f1f5f9}
        .dropdown-menu.show{display:block}
        .dropdown-item{display:flex;align-items:center;gap:.5rem;padding:.5rem 1rem;color:var(--text-dark);text-decoration:none;font-size:.9rem;transition:background .2s}
        .dropdown-item:hover{background:#f8fafc}
        
        /* Flash messages */
        .alert{padding:1rem;border-radius:8px;margin-bottom:1rem;position:relative}
        .alert-dismissible .btn-close{position:absolute;right:1rem;top:50%;transform:translateY(-50%);background:none;border:none;font-size:1.2rem;cursor:pointer}
        
        /* Main content wrapper */
        .main-content{min-height:60vh}
        
        /* Modal basics (needed for notification modal) */
        .modal{display:none;position:fixed;inset:0;z-index:1050;overflow:auto}
        .modal.show{display:block}
        .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1040}
        .modal-dialog{margin:1.75rem auto;max-width:500px;position:relative;z-index:1060}
        .modal-content{background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.2)}
        .modal-header{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.5rem}
        .modal-body{padding:0 1.5rem 1.5rem}
        .modal-footer{padding:1rem 1.5rem;display:flex;justify-content:flex-end}
        .btn-close{background:none;border:none;font-size:1.2rem;cursor:pointer;opacity:.5}
        .btn-close:hover{opacity:1}
        
        /* Hide content until full CSS loads to prevent FOUC flash */
        .features-row,.edu-levels-section,.blog-section-warm,.cta-warm{opacity:1}
    </style>
    
    <!-- Primary CSS: design-system (minified for speed) -->
    <?php
        $cssFile = '/assets/css/design-system.min.css';
        if (!file_exists(ROOT_PATH . $cssFile)) {
            $cssFile = '/assets/css/design-system.css'; // fallback
        }
        $cssVersion = filemtime(ROOT_PATH . $cssFile);
    ?>
    <link href="<?php echo SITE_URL . $cssFile; ?>?v=<?php echo $cssVersion; ?>" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?php echo SITE_URL . $cssFile; ?>?v=<?php echo $cssVersion; ?>"></noscript>
    
    <!-- NON-CRITICAL CSS: Load asynchronously (eliminates 3.2s render-blocking!) -->
    <!-- Google Fonts (reduced to 3 weights instead of 5) -->
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    
    <!-- Bootstrap 5 CSS (deferred to prevent render-blocking) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    
    <!-- Font Awesome (single all.min.css instead of 4 files — saves 3 HTTP requests) -->
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    
    <!-- Fallback for browsers with JS disabled -->
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </noscript>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>/">
                <?php if (site_logo_url() !== ''): ?>
                <img src="<?php echo htmlspecialchars(site_logo_url()); ?>" alt="<?php echo htmlspecialchars(SITE_NAME); ?> logo" style="height:34px;width:auto;max-width:160px;object-fit:contain;">
                <?php else: ?>
                <i class="fas fa-graduation-cap"></i>
                <?php endif; ?>
                <?php echo SITE_NAME; ?>
            </a>
            
            <div class="d-flex align-items-center d-lg-none">
                <!-- Notifications Bell - Always visible on mobile -->
                <?php if (is_logged_in()): 
                    // Load notification count
                    require_once __DIR__ . '/../models/Notification.php';
                    $notifModel = new Notification($pdo);
                    $unreadCount = $notifModel->getUnreadCount((int)$_SESSION['user_id']);
                    $recentNotifs = $notifModel->getUserNotifications((int)$_SESSION['user_id'], 5);
                    // Load unread message count
                    require_once __DIR__ . '/../models/Message.php';
                    $msgModelNav = new Message($pdo);
                    $unreadMsgCount = $msgModelNav->getUnreadTotal((int)$_SESSION['user_id']);
                ?>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#notifModal" class="position-relative text-decoration-none me-3" title="Notifications" aria-label="Notifications<?php echo $unreadCount > 0 ? ' (' . $unreadCount . ' unread)' : ''; ?>" style="color: var(--text-dark);">
                        <i class="fas fa-bell" style="font-size:1.3rem;" aria-hidden="true"></i>
                        <?php if ($unreadCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem;">
                            <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <!-- Chat icon - Mobile -->
                    <a href="<?php echo SITE_URL; ?>/messages.php" class="position-relative text-decoration-none me-3" title="Messages" aria-label="Messages<?php echo $unreadMsgCount > 0 ? ' (' . $unreadMsgCount . ' unread)' : ''; ?>" style="color: var(--text-dark);" id="navChatBtnMobile">
                        <i class="fas fa-comment-dots" style="font-size:1.3rem;" aria-hidden="true"></i>
                        <?php if ($unreadMsgCount > 0): ?>
                        <span id="msgBadgeMobile" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:0.65rem;">
                            <?php echo $unreadMsgCount > 9 ? '9+' : $unreadMsgCount; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                
                <button class="navbar-toggler border-0 px-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/">Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Explore</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item fw-bold text-primary" href="<?php echo SITE_URL; ?>/users.php"><i class="fas fa-users"></i> Find Friends</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/materials.php"><i class="fas fa-book-open"></i> Study Materials</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/tutorials.php"><i class="fab fa-youtube"></i> Video Tutorials</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/quizzes.php"><i class="fas fa-brain"></i> Practice Quizzes</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/discussions.php"><i class="fas fa-comments text-info"></i> Community Forum</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/battle_hub.php"><i class="fas fa-khanda text-warning"></i> Battle Hub</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/blog.php"><i class="fas fa-newspaper"></i> Blog Posts</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'leaderboard.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/leaderboard.php">Leaderboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/about.php">About</a>
                    </li>
                    <?php if (is_logged_in()): ?>
                    <li class="nav-item ms-lg-2">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?> fw-bold" href="<?php echo SITE_URL; ?>/dashboard.php" style="color: var(--primary);">Dashboard</a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="d-flex gap-2 align-items-center">
                    <?php if (is_logged_in()): ?>
                        <!-- Desktop Notifications Bell -->
                        <a href="#" data-bs-toggle="modal" data-bs-target="#notifModal" class="position-relative text-decoration-none me-3 d-none d-lg-flex align-items-center" title="Notifications" aria-label="Notifications<?php echo $unreadCount > 0 ? ' (' . $unreadCount . ' unread)' : ''; ?>" style="color: var(--text-dark);">
                            <i class="fas fa-bell" style="font-size:1.3rem;" aria-hidden="true"></i>
                            <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem;">
                                <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                        <!-- Chat icon - Desktop -->
                        <a href="<?php echo SITE_URL; ?>/messages.php" class="position-relative text-decoration-none me-3 d-none d-lg-flex align-items-center" title="Messages" aria-label="Messages<?php echo $unreadMsgCount > 0 ? ' (' . $unreadMsgCount . ' unread)' : ''; ?>" style="color: var(--text-dark);" id="navChatBtnDesktop">
                            <i class="fas fa-comment-dots" style="font-size:1.3rem;" aria-hidden="true"></i>
                            <?php if ($unreadMsgCount > 0): ?>
                            <span id="msgBadgeDesktop" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:0.65rem;">
                                <?php echo $unreadMsgCount > 9 ? '9+' : $unreadMsgCount; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                        
                        <!-- Logged In -->
                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static">
                                <img src="<?php echo !empty($_SESSION['user_picture']) ? (SITE_URL . '/' . ltrim(htmlspecialchars($_SESSION['user_picture']), '/')) : (SITE_URL . '/assets/img/default-avatar.png'); ?>" alt="User" class="user-avatar me-2">
                                <span class="d-none d-md-inline fw-500"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" style="max-height: 70vh; overflow-y: auto;">
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/battle_arena.php"><i class="fas fa-khanda text-warning"></i> Battle Arena</a></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/messages.php"><i class="fas fa-comment-dots text-primary"></i> Messages<?php if ($unreadMsgCount > 0): ?> <span class="badge bg-primary rounded-pill ms-1"><?php echo $unreadMsgCount; ?></span><?php endif; ?></a></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/my_quizzes.php"><i class="fas fa-history"></i> My Quizzes</a></li>
                                <?php if (has_role('admin')): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>/admin/"><i class="fas fa-lock"></i> Admin Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Not Logged In -->
                        <a href="<?php echo SITE_URL; ?>/login.php" class="btn-login">Log in</a>
                        <a href="<?php echo SITE_URL; ?>/register.php" class="btn-signup">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Notification Modal -->
    <?php if (is_logged_in() && isset($recentNotifs)): ?>
    <div class="modal fade" id="notifModal" tabindex="-1" aria-labelledby="notifModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="border-radius:16px; border:none; max-height: 100%;">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="notifModalLabel">
                        <i class="fas fa-bell text-primary me-2"></i>Notifications
                        <?php if ($unreadCount > 0): ?>
                        <span class="badge bg-danger ms-2" style="font-size:0.7rem;"><?php echo $unreadCount; ?> new</span>
                        <?php endif; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 mt-3 custom-scrollbar" style="overflow-x: hidden;">
                    <?php if (empty($recentNotifs)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash text-muted mb-3" style="font-size: 2.5rem;"></i>
                        <p class="text-muted mb-0">No new notifications</p>
                    </div>
                    <?php else: ?>
                    <div class="accordion accordion-flush" id="notifAccordion">
                        <style>
                            .notif-btn::after { display: none; } /* Hide default bootstrap accordion arrow */
                            .notif-btn:focus { box-shadow: none; border-color: rgba(0,0,0,.125); }
                            .notif-btn[aria-expanded="true"] { background-color: transparent; }
                            .notif-btn[aria-expanded="true"] .notif-preview { display: none !important; }
                            .notif-btn[aria-expanded="true"] .notif-chevron { transform: rotate(180deg); }
                            .notif-chevron { transition: transform 0.3s ease; }
                            /* Custom clean scrollbar for the modal body */
                            .custom-scrollbar::-webkit-scrollbar { width: 6px; }
                            .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
                            .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
                            .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
                        </style>
                        <?php 
                        $hasRewardNotif = false;
                        $rewardNotifData = null;

                        foreach ($recentNotifs as $index => $notif): 
                            $isUnread = !(int)$notif['is_read'];
                            
                            // Check if this is an unread reward_win notification to trigger the modal banner
                            if ($isUnread && $notif['type'] === 'reward_win' && !$hasRewardNotif) {
                                // Extract quiz ID, rank, score, total, and amt from the link string
                                if (preg_match('/quiz_id=(\d+)/', $notif['link'], $mId)) {
                                    $hasRewardNotif = true;
                                    
                                    $rank = '1st'; $score = 0; $total = 0; $amt = 0;
                                    if (preg_match('/rank=(\d+)/', $notif['link'], $mRank)) {
                                        $r = (int)$mRank[1];
                                        $rank = ($r === 1) ? '1st' : (($r === 2) ? '2nd' : '3rd');
                                    }
                                    if (preg_match('/score=(\d+)/', $notif['link'], $mScore)) $score = (int)$mScore[1];
                                    if (preg_match('/total=(\d+)/', $notif['link'], $mTotal)) $total = (int)$mTotal[1];
                                    if (preg_match('/amt=([\d\.]+)/', $notif['link'], $mAmt)) $amt = (float)$mAmt[1];
                                    
                                    $pct = $total > 0 ? round(($score / $total) * 100) : 0;
                                    
                                    $rewardNotifData = [
                                        'id' => $notif['id'],
                                        'quiz_id' => $mId[1],
                                        'title' => $notif['title'],
                                        'message' => $notif['message'],
                                        'link' => $notif['link'],
                                        'rank' => $rank,
                                        'score_text' => $total > 0 ? "{$score}/{$total} ({$pct}%)" : "--/--",
                                        'amount_value' => $amt > 0 ? number_format($amt) : 'See Form'
                                    ];
                                }
                            }

                            $icon = 'fa-bell';
                            $iconColor = '#6b7280';
                            switch ($notif['type']) {
                                case 'challenge_complete': $icon = 'fa-trophy'; $iconColor = '#f59e0b'; break;
                                case 'quiz_result': $icon = 'fa-check-circle'; $iconColor = '#10b981'; break;
                                case 'welcome': $icon = 'fa-hand-wave'; $iconColor = '#3b82f6'; break;
                                case 'payment': $icon = 'fa-credit-card'; $iconColor = '#8b5cf6'; break;
                                case 'reward_win': $icon = 'fa-gift'; $iconColor = '#ec4899'; break;
                                case 'reward_claim': $icon = 'fa-file-invoice'; $iconColor = '#3b82f6'; break;
                                case 'reward_sent': $icon = 'fa-check-double'; $iconColor = '#10b981'; break;
                                case 'system': $icon = 'fa-info-circle'; $iconColor = '#6b7280'; break;
                                case 'battle_challenge': $icon = 'fa-khanda'; $iconColor = '#f59e0b'; break;
                                case 'battle_accepted': $icon = 'fa-check-circle'; $iconColor = '#10b981'; break;
                                case 'battle_declined': $icon = 'fa-times-circle'; $iconColor = '#ef4444'; break;
                                case 'battle_negotiate': $icon = 'fa-handshake'; $iconColor = '#3b82f6'; break;
                                case 'battle_result': $icon = 'fa-trophy'; $iconColor = '#8b5cf6'; break;
                            }
                        ?>
                        <div class="accordion-item position-relative border-0 border-bottom <?php echo $isUnread ? 'bg-light' : ''; ?>" id="notif-item-<?php echo $notif['id']; ?>" style="<?php echo $isUnread ? 'border-left: 4px solid #10b981 !important;' : ''; ?>">
                            <?php if ($isUnread): ?>
                            <button class="btn btn-sm position-absolute mark-read-btn" onclick="event.stopPropagation(); markNotificationReadAjax(<?php echo $notif['id']; ?>);" style="right: 3rem; top: 1.15rem; z-index: 10; color: #10b981; padding: 0.15rem 0.4rem; background: rgba(16,185,129,0.1); border-radius: 6px; border: 1px solid rgba(16,185,129,0.2);" title="Mark as Read">
                                <i class="fas fa-check-double"></i>
                            </button>
                            <?php endif; ?>
                            <h2 class="accordion-header" id="headingNotif<?php echo $index; ?>">
                                <button class="accordion-button collapsed notif-btn px-4 py-3 d-flex align-items-start gap-3 w-100 text-start border-0 bg-transparent" type="button" data-bs-toggle="collapse" data-bs-target="#collapseNotif<?php echo $index; ?>" aria-expanded="false" aria-controls="collapseNotif<?php echo $index; ?>">
                                    <div class="flex-shrink-0 mt-1" style="width:36px; height:36px; border-radius:10px; background:<?php echo $iconColor; ?>15; color:<?php echo $iconColor; ?>; display:flex; align-items:center; justify-content:center;">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1" style="min-width: 0; overflow: hidden; padding-right: 0.5rem;">
                                        <div class="d-flex justify-content-between align-items-start mb-1 gap-2">
                                            <h6 class="mb-0 text-truncate <?php echo $isUnread ? 'fw-bold' : 'fw-normal'; ?> notif-title-text" style="font-size:0.9rem; padding-right: 25px;">
                                                <?php echo htmlspecialchars($notif['title']); ?>
                                            </h6>
                                            <i class="fas fa-chevron-down text-muted notif-chevron mt-1 flex-shrink-0" style="font-size: 0.75rem;"></i>
                                        </div>
                                        <p class="mb-0 text-muted text-truncate notif-preview w-100" style="font-size:0.8rem;"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapseNotif<?php echo $index; ?>" class="accordion-collapse collapse" aria-labelledby="headingNotif<?php echo $index; ?>" data-bs-parent="#notifAccordion">
                                <div class="accordion-body pt-0 pb-3" style="padding-left: 4.5rem; padding-right: 1.5rem;">
                                    <p class="text-muted mb-2 pe-2" style="font-size:0.85rem; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                                    <?php if (!empty($notif['link']) && $notif['link'] !== '#'): ?>
                                        <div class="mt-2 text-end">
                                            <a href="<?php echo htmlspecialchars($notif['link']); ?>" onclick="event.preventDefault(); markAndRedirect(<?php echo $notif['id']; ?>, '<?php echo htmlspecialchars(addslashes($notif['link'])); ?>');" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="font-size: 0.75rem; font-weight: 600;">View Details</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-top-0 pt-3 pb-3 justify-content-center" style="background: #fff; box-shadow: 0 -4px 10px rgba(0,0,0,0.02); z-index: 5;">
                    <a href="<?php echo SITE_URL; ?>/notifications.php" class="btn btn-link text-decoration-none fw-bold" style="color: #10b981;">View All Notifications <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function markNotificationReadAjax(id) {
        var fd = new FormData();
        fd.append('id', id);
        fetch('<?php echo SITE_URL; ?>/ajax/mark_notifications_read.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                const itemEl = document.getElementById('notif-item-' + id);
                if (itemEl) {
                    itemEl.classList.remove('bg-light');
                    itemEl.style.borderLeft = '';
                    const title = itemEl.querySelector('.notif-title-text');
                    if (title) { title.classList.remove('fw-bold'); title.classList.add('fw-normal'); }
                    const btn = itemEl.querySelector('.mark-read-btn');
                    if (btn) btn.remove();
                    
                    // update master badges
                    const badges = document.querySelectorAll('.fa-bell + .badge');
                    badges.forEach(b => {
                        let text = b.innerText.replace('new', '').trim();
                        let count = parseInt(text);
                        if (!isNaN(count)) {
                            count = count - 1;
                            if (count > 0) b.innerText = count + " new";
                            else b.style.display = 'none';
                        }
                    });
                }
            }
        });
    }

        function markAndRedirect(id, url) {
            var fd = new FormData();
            fd.append('id', id);
            fetch('<?php echo SITE_URL; ?>/ajax/mark_notifications_read.php', {
                method: 'POST',
                body: fd
            }).then(() => {
                if (url && url.startsWith('#view-reply-')) {
                    var msgId = url.split('-')[2];
                    var notifEl = document.getElementById('notifModal');
                    if (notifEl && typeof bootstrap !== 'undefined') {
                        var m = bootstrap.Modal.getInstance(notifEl);
                        if (m) m.hide();
                    }
                    if (window.viewContactReply) {
                        setTimeout(() => window.viewContactReply(msgId), 350);
                    }
                } else {
                    window.location.href = url;
                }
            }).catch(() => {
                if (!url.startsWith('#view-reply-')) {
                    window.location.href = url;
                }
            });
        }
    </script>
    </div>
    
    <!-- User Contact Reply Modal -->
    <div class="modal fade" id="userReplyModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-light border-bottom p-4">
                    <h5 class="modal-title fw-bold text-gray-800"><i class="fas fa-headset text-success me-2"></i> Support Reply</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="userReplyContent">
                </div>
                <div class="modal-footer bg-light p-3 border-top">
                    <button type="button" class="btn btn-light border shadow-sm rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    window.viewContactReply = function(msgId) {
        var modalEl = document.getElementById('userReplyModal');
        var contentEl = document.getElementById('userReplyContent');
        if (!modalEl || !contentEl) return;
        
        contentEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 text-muted fw-bold">Loading reply...</div></div>';
        
        if (typeof bootstrap !== 'undefined') {
            let m = bootstrap.Modal.getInstance(modalEl);
            if (!m) m = new bootstrap.Modal(modalEl);
            m.show();
        } else if (typeof window.jQuery !== 'undefined') {
            window.jQuery('#userReplyModal').modal('show');
        }

        fetch('<?php echo SITE_URL; ?>/ajax/get_contact_reply.php?id=' + msgId)
            .then(r => r.json())
            .then(data => {
                const esc = str => String(str || '').replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");
                if (data.success && data.data) {
                    const reply = data.data;
                    const dateStr = reply.replied_at ? new Date(reply.replied_at).toLocaleString([], { dateStyle: 'long', timeStyle: 'short' }) : '';
                    contentEl.innerHTML = `
                        <div class="border-start border-4 border-info bg-light p-3 mb-4 rounded-end">
                            <h6 class="fw-bold text-muted small text-uppercase mb-2">You wrote:</h6>
                            <div class="text-dark fst-italic" style="font-size: 0.95rem;">"${esc(reply.message)}"</div>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="message-avatar bg-success text-white shadow-sm flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; border-radius: 50%; font-size: 1.2rem;">
                                    <i class="fas fa-headset"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-success">Admin Support Team</h6>
                                    <span class="text-muted small">${dateStr}</span>
                                </div>
                            </div>
                            <div class="bg-success bg-opacity-10 p-4 rounded-3 text-dark" style="white-space: pre-wrap; line-height: 1.6; font-size: 0.95rem;">${esc(reply.admin_reply)}</div>
                        </div>
                    `;
                } else {
                    contentEl.innerHTML = `<div class="alert alert-danger border-0 mb-0 shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i>${esc(data.error || 'Failed to load reply.')}</div>`;
                }
            })
            .catch(() => {
                contentEl.innerHTML = `<div class="alert alert-danger border-0 mb-0 shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i>Error communicating with server.</div>`;
            });
    };
    </script>
    <?php endif; ?>

    <?php if (isset($hasRewardNotif) && $hasRewardNotif && $rewardNotifData): ?>
    
    <style>
    /* ═══ Premium Reward Claim Modal ═══ */
    .reward-modal-overlay {
        background: rgba(0, 0, 0, 0.75) !important;
    }
    .reward-modal-content {
        background: #ffffff;
        border-radius: 24px;
        box-shadow: 0 25px 60px rgba(0,0,0,0.3);
        border: none;
        position: relative;
        padding: 0;
        color: #1e293b;
        margin: auto;
        max-width: 420px;
        overflow: visible;
    }
    /* Green gradient header zone */
    .reward-header-zone {
        background: linear-gradient(135deg, #059669, #10b981, #34d399);
        border-radius: 24px 24px 0 0;
        padding: 2.5rem 1.5rem 3.5rem;
        text-align: center;
        position: relative;
    }
    .reward-body-zone {
        padding: 1.5rem 1.5rem 2rem;
        text-align: center;
        position: relative;
        margin-top: -2rem;
    }

    /* Close button */
    .reward-close-btn {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: rgba(255,255,255,0.25);
        border: none;
        color: white;
        font-size: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        transition: background 0.2s;
    }
    .reward-close-btn:hover { background: rgba(255,255,255,0.4); }

    /* Title */
    .reward-title {
        font-family: 'Poppins', sans-serif;
        font-weight: 800;
        font-size: clamp(1.6rem, 5vw, 2rem);
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin: 0;
        text-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .reward-title-sub {
        color: rgba(255,255,255,0.85);
        font-size: 0.9rem;
        margin-top: 0.5rem;
        font-weight: 500;
    }

    /* Stars row on header */
    .reward-stars {
        display: flex;
        justify-content: center;
        align-items: flex-end;
        gap: 8px;
        margin-bottom: 1rem;
    }
    .reward-star {
        color: #fde047;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
    }
    .reward-star.sm { font-size: 1.8rem; }
    .reward-star.lg { font-size: 2.6rem; transform: translateY(-4px); }

    /* Stats card (floats over the header/body junction) */
    .reward-stats-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    .r-stat {
        padding: 1rem 0.5rem;
        text-align: center;
        border-right: 1px solid #f1f5f9;
    }
    .r-stat:last-child { border-right: none; }
    .r-stat-label {
        font-size: 0.65rem;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .r-stat-value {
        font-weight: 800;
        font-size: 1.1rem;
        color: #1e293b;
    }
    .r-stat-value.prize-val {
        color: #059669;
        font-size: 1.2rem;
    }

    /* Challenge name pill */
    .reward-challenge-name {
        display: inline-block;
        background: #f0fdf4;
        color: #059669;
        font-size: 0.8rem;
        font-weight: 600;
        padding: 6px 16px;
        border-radius: 20px;
        border: 1px solid #bbf7d0;
        margin: 1.2rem 0 1.5rem;
    }

    /* CTA Button */
    .reward-btn-claim {
        background: linear-gradient(135deg, #059669, #10b981);
        border: none;
        color: white !important;
        font-weight: 800;
        font-size: 1.1rem;
        padding: 14px 48px;
        border-radius: 50px;
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.35);
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-block;
        letter-spacing: 0.5px;
    }
    .reward-btn-claim:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(16, 185, 129, 0.45);
        background: linear-gradient(135deg, #047857, #059669);
    }
    .reward-btn-claim:active {
        transform: translateY(1px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    @media (max-width: 576px) {
        .reward-modal-content { max-width: 92vw; }
        .reward-header-zone { padding: 2rem 1rem 3rem; }
        .reward-body-zone { padding: 1rem 1rem 1.5rem; }
        .reward-star.sm { font-size: 1.5rem; }
        .reward-star.lg { font-size: 2.1rem; }
        .reward-title { font-size: 1.5rem; }
        .r-stat { padding: 0.8rem 0.3rem; }
        .r-stat-value { font-size: 0.95rem; }
        .r-stat-value.prize-val { font-size: 1rem; }
        .reward-btn-claim { padding: 12px 36px; font-size: 1rem; }
    }
    </style>

    <!-- ═══ Celebratory Reward Modal ═══ -->
    <div class="modal fade" id="rewardWinModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
            <div class="modal-content reward-modal-content">

                <!-- ── Green Header ── -->
                <div class="reward-header-zone">
                    <button type="button" class="reward-close-btn" data-bs-dismiss="modal" onclick="markRewardNotifRead(<?php echo $rewardNotifData['id']; ?>)">
                        <i class="fas fa-times"></i>
                    </button>

                    <!-- Stars -->
                    <div class="reward-stars">
                        <i class="fas fa-star reward-star sm" style="transform: rotate(-12deg);"></i>
                        <i class="fas fa-star reward-star lg"></i>
                        <i class="fas fa-star reward-star sm" style="transform: rotate(12deg);"></i>
                    </div>

                    <h2 class="reward-title">Congratulations!</h2>
                    <p class="reward-title-sub">You've earned a reward 🎉</p>
                </div>

                <!-- ── White Body ── -->
                <div class="reward-body-zone">
                    <!-- Stats Card (overlaps header) -->
                    <div class="reward-stats-card">
                        <div class="r-stat">
                            <div class="r-stat-label">Position</div>
                            <div class="r-stat-value"><i class="fas fa-trophy" style="color:#f59e0b;"></i> <?php echo htmlspecialchars($rewardNotifData['rank']); ?></div>
                        </div>
                        <div class="r-stat">
                            <div class="r-stat-label">Score</div>
                            <div class="r-stat-value"><?php echo htmlspecialchars($rewardNotifData['score_text']); ?></div>
                        </div>
                        <div class="r-stat">
                            <div class="r-stat-label">Your Prize</div>
                            <div class="r-stat-value prize-val"><?php echo htmlspecialchars($rewardNotifData['amount_value']); ?></div>
                        </div>
                    </div>

                    <!-- Challenge Name -->
                    <div class="reward-challenge-name">
                        <i class="fas fa-gamepad me-1"></i> <?php echo htmlspecialchars(str_replace('🏆 Claim Your Reward: ', '', $rewardNotifData['title'])); ?>
                    </div>

                    <!-- CTA -->
                    <div>
                        <a href="<?php echo htmlspecialchars($rewardNotifData['link']); ?>" class="reward-btn-claim" onclick="markRewardNotifRead(<?php echo $rewardNotifData['id']; ?>)">
                            <i class="fas fa-gift me-2"></i>Claim Your Prize
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var rewardModalEl = document.getElementById('rewardWinModal');
            rewardModalEl.addEventListener('show.bs.modal', function () {
                // Ensure the backdrop is dark like a game overlay
                setTimeout(() => {
                    var backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) { backdrop.classList.add('reward-modal-overlay'); }
                }, 50);
            });
            var rewardModal = new bootstrap.Modal(rewardModalEl);
            rewardModal.show();
        });
        
        function markRewardNotifRead(id) {
            fetch('<?php echo SITE_URL; ?>/ajax/mark_notifications_read.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id
            });
        }
    </script>
    <?php endif; ?>

    <?php if (is_logged_in()): ?>
    <script>
        // Global background polling for unread messages (runs every 10 seconds)
        document.addEventListener('DOMContentLoaded', function() {
            setInterval(function() {
                var fd = new FormData();
                fd.append('action', 'ping_unread');
                fetch('<?php echo SITE_URL; ?>/api_messages.php', { method: 'POST', body: fd })
                    .then(r => r.json()).then(d => {
                        if (d.success !== undefined) {
                            var count = parseInt(d.unread) || 0;
                            var badgeText = count > 9 ? '9+' : count;
                            
                            // Update or create mobile badge
                            var navMobile = document.getElementById('navChatBtnMobile');
                            if (navMobile) {
                                var badgeMobile = document.getElementById('msgBadgeMobile');
                                if (count > 0) {
                                    if (badgeMobile) { badgeMobile.innerText = badgeText; }
                                    else {
                                        navMobile.insertAdjacentHTML('beforeend', '<span id="msgBadgeMobile" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:0.65rem;">' + badgeText + '</span>');
                                    }
                                } else if (badgeMobile) { badgeMobile.remove(); }
                            }

                            // Update or create desktop badge
                            var navDesktop = document.getElementById('navChatBtnDesktop');
                            if (navDesktop) {
                                var badgeDesktop = document.getElementById('msgBadgeDesktop');
                                if (count > 0) {
                                    if (badgeDesktop) { badgeDesktop.innerText = badgeText; }
                                    else {
                                        navDesktop.insertAdjacentHTML('beforeend', '<span id="msgBadgeDesktop" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:0.65rem;">' + badgeText + '</span>');
                                    }
                                } else if (badgeDesktop) { badgeDesktop.remove(); }
                            }
                        }
                    }).catch(e => {}); // Silent catch 
            }, 30000); // Poll every 30 seconds (was 3s - too aggressive for mobile)
        });

        // --- Web Push Notifications ---
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        async function subscribeToPush() {
            try {
                const reg = await navigator.serviceWorker.ready;
                console.log('[WebPush] SW ready, fetching VAPID public key...');
                const res = await fetch('<?php echo SITE_URL; ?>/ajax/push_subscribe.php?action=get_public_key');
                const data = await res.json();
                console.log('[WebPush] Public key response:', data);
                
                if (!data.publicKey) {
                    console.error('[WebPush] No public key returned from server');
                    return;
                }
                
                const sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(data.publicKey)
                });
                console.log('[WebPush] Subscribed:', JSON.stringify(sub));
                
                // Extract subscription data explicitly
                const subJson = sub.toJSON();
                const payload = {
                    endpoint: subJson.endpoint,
                    p256dh: subJson.keys.p256dh,
                    auth: subJson.keys.auth
                };
                
                const saveRes = await fetch('<?php echo SITE_URL; ?>/ajax/push_subscribe.php?action=subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const saveData = await saveRes.json();
                console.log('[WebPush] Subscription saved:', saveData);
                
                const banner = document.getElementById('webPushBanner');
                if (banner) banner.remove();
            } catch (e) {
                console.error('[WebPush] Subscription failed:', e);
            }
        }

        document.addEventListener('DOMContentLoaded', async function() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
            
            try {
                await navigator.serviceWorker.register('<?php echo SITE_URL; ?>/sw.js');
                
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                
                if (!sub && (Notification.permission === 'default')) {
                    // Check if dismissed previously
                    const dismissedExpiry = localStorage.getItem('pushPromptDismissed');
                    if (!dismissedExpiry || Date.now() > parseInt(dismissedExpiry)) {
                        const bannerHtml = `
                            <div id="webPushBanner" class="alert alert-primary alert-dismissible fade show m-3 shadow-lg border-0" role="alert" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; max-width: 350px; border-radius: 12px;">
                                <strong><i class="fa-solid fa-bell me-2 text-primary"></i> Stay Updated!</strong><br>
                                <span style="font-size: 0.9rem;" class="text-secondary">Enable notifications to get real-time alerts for messages, challenges, and rewards—even when you're away!</span>
                                <div class="mt-3 text-end">
                                    <button type="button" class="btn btn-sm btn-light me-2 fw-semibold border" onclick="dismissPushBanner()">Not Now</button>
                                    <button type="button" class="btn btn-sm btn-primary fw-bold px-3" onclick="enablePushNotifications()">Enable</button>
                                </div>
                            </div>
                        `;
                        document.body.insertAdjacentHTML('beforeend', bannerHtml);
                    }
                } else if (sub && Notification.permission === 'granted') {
                    // Always refresh subscription state with backend on load silently
                    const subJson = sub.toJSON();
                    fetch('<?php echo SITE_URL; ?>/ajax/push_subscribe.php?action=subscribe', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            endpoint: subJson.endpoint,
                            p256dh: subJson.keys.p256dh,
                            auth: subJson.keys.auth
                        })
                    }).catch(e=>{});
                }
            } catch (e) {
               console.error('SW registration failed:', e); 
            }
        });
        
        window.dismissPushBanner = function() {
            // Dismiss for 7 days
            localStorage.setItem('pushPromptDismissed', Date.now() + (7 * 24 * 60 * 60 * 1000));
            var b = document.getElementById('webPushBanner');
            if(b) b.remove();
        }
        
        window.enablePushNotifications = async function() {
            // Hide banner immediately so user doesn't have to dismiss twice
            var existing = document.getElementById('webPushBanner');
            if (existing) existing.remove();
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                subscribeToPush();
            } else {
                dismissPushBanner();
            }
        }
    </script>
    <?php endif; ?>

    <!-- Admin Broadcast Modal (unique, non-conflicting) -->
    <?php if (!empty($adminNotice)): ?>
    <div class="modal fade" id="adminBroadcastModal" tabindex="-1" aria-labelledby="adminBroadcastModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-header border-0 pb-0">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:36px;height:36px;border-radius:12px;background:#0f172a10;display:flex;align-items:center;justify-content:center;color:#0f172a;">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" id="adminBroadcastModalLabel">Admin Announcement</h5>
                            <small class="text-muted">Sent from MyStudy Admin Panel</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="adminBroadcastCloseBtn"></button>
                </div>
                <div class="modal-body pt-3">
                    <h6 class="fw-semibold mb-2"><?php echo htmlspecialchars($adminNotice['title']); ?></h6>
                    <p class="mb-0" style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($adminNotice['message'])); ?></p>
                </div>
                <div class="modal-footer border-0 pt-0 pb-3 justify-content-end">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var mEl = document.getElementById('adminBroadcastModal');
        if (!mEl || typeof bootstrap === 'undefined') return;
        var m = new bootstrap.Modal(mEl);
        m.show();

        var closeBtn = document.getElementById('adminBroadcastCloseBtn');
        function markClosed() {
            fetch('<?php echo SITE_URL; ?>/ajax/mark_admin_broadcast_read.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=<?php echo (int)$adminNotice['id']; ?>'
            }).catch(function(){});
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', markClosed);
        }
        mEl.addEventListener('hidden.bs.modal', markClosed);
    });
    </script>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php 
        // Display flash messages
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            $alert_class = 'alert-' . ($flash['type'] === 'error' ? 'danger' : $flash['type']);
            echo '<div class="container mt-3"><div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
            echo htmlspecialchars($flash['message']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            echo '</div></div>';
            unset($_SESSION['flash']);
        }
        ?>
