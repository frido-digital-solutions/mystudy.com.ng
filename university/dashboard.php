<?php
// University Student Dashboard
require_once __DIR__ . '/../config/config.php';

// Require login
require_login();

// Ensure only university student can access
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'university') {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

// Get user information
$user = new User($pdo);
$user->getById($_SESSION['user_id']);
$userId = (int)$_SESSION['user_id'];

$page_title = 'University Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    /* Dashboard Specific Styles */
    :root {
        --primary-color: #10b981;
        --secondary-color: #6366f1;
        --accent-color: #f59e0b;
        --bg-warm: #fefdfb;
        --card-bg: #ffffff;
        --text-dark: #1e293b;
        --text-muted: #64748b;
    }

    body {
        background-color: var(--bg-warm);
        font-family: 'Poppins', sans-serif;
    }

    .dashboard-container {
        padding: 2rem 0;
    }

    /* Welcome Banner */
    .welcome-banner {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-radius: 20px;
        padding: 2.5rem;
        color: white;
        position: relative;
        overflow: hidden;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.2);
    }

    .welcome-banner::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .welcome-banner::after {
        content: '';
        position: absolute;
        bottom: -30%;
        right: 10%;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .welcome-content {
        position: relative;
        z-index: 1;
    }

    .welcome-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .welcome-subtitle {
        font-size: 1.1rem;
        opacity: 0.9;
        max-width: 600px;
    }

    /* Cards */
    .dash-card {
        background: var(--card-bg);
        border-radius: 20px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        padding: 1.5rem;
        height: 100%;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .dash-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.1);
    }

    /* Profile Card */
    .profile-card {
        text-align: center;
    }

    .profile-img-container {
        position: relative;
        display: inline-block;
        margin-bottom: 1rem;
    }

    .profile-img {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .profile-badge {
        position: absolute;
        bottom: 5px;
        right: 5px;
        background: var(--primary-color);
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
    }

    .profile-name {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.25rem;
    }

    .profile-role {
        font-size: 0.9rem;
        color: var(--primary-color);
        font-weight: 600;
        margin-bottom: 0.5rem;
        background: #ecfdf5;
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
    }

    .profile-details {
        font-size: 0.9rem;
        color: var(--text-muted);
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #f1f5f9;
    }

    /* Stats Cards */
    .stat-card {
        display: flex;
        align-items: center;
        padding: 1.5rem;
        border-radius: 16px;
        margin-bottom: 1rem;
        border: 1px solid transparent;
        transition: all 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-right: 1.25rem;
        color: white;
    }

    .stat-info h3 {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0 0 0.25rem;
        line-height: 1.1;
    }

    .stat-info p {
        margin: 0;
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    .stat-cgpa { background: #fdf2f8; border-color: #fce7f3; }
    .stat-cgpa .stat-icon { background: linear-gradient(135deg, #db2777 0%, #be185d 100%); }
    .stat-cgpa:hover { box-shadow: 0 10px 20px rgba(219, 39, 119, 0.1); }

    .stat-level { background: #eff6ff; border-color: #dbeafe; }
    .stat-level .stat-icon { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
    .stat-level:hover { box-shadow: 0 10px 20px rgba(59, 130, 246, 0.1); }

    /* Quick Links Grid */
    .quick-links-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }

    .quick-link-item {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.25rem;
        text-align: center;
        text-decoration: none;
        color: var(--text-dark);
        transition: all 0.3s;
    }

    .quick-link-item:hover {
        border-color: var(--primary-color);
        transform: translateY(-3px);
        background: #f0fdf4;
        color: var(--primary-color);
    }

    .quick-link-icon {
        font-size: 1.75rem;
        margin-bottom: 0.75rem;
        color: var(--primary-color);
    }

    .quick-link-title {
        font-size: 0.9rem;
        font-weight: 600;
        display: block;
    }

    /* Activity List */
    .activity-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .activity-item {
        display: flex;
        gap: 1rem;
        padding-bottom: 1.25rem;
        margin-bottom: 1.25rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .activity-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--secondary-color);
        flex-shrink: 0;
    }

    .activity-content h6 {
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: var(--text-dark);
    }

    .activity-content p {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-bottom: 0.25rem;
    }

    .activity-time {
        font-size: 0.75rem;
        color: #94a3b8;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-dark);
        margin: 0;
    }

    /* Mobile Responsiveness */
    @media (max-width: 768px) {
        .dashboard-container {
            padding: 1rem 0;
        }
        
        .welcome-banner {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .welcome-title {
            font-size: 1.5rem;
        }
        
        .section-header {
            margin-bottom: 1rem;
        }
        
        .stat-card {
            padding: 1rem;
        }
        
        .stat-icon {
            width: 45px;
            height: 45px;
            font-size: 1.2rem;
            margin-right: 1rem;
        }
    }
</style>

<?php
// --- Fetch gamification stats ---
require_once __DIR__ . '/../models/Gamification.php';
$gamification = new Gamification($pdo);
$gamStats = $gamification->getUserStats($userId);

// Pending Reward Claims for University students
$stmtPending = $pdo->prepare("
    SELECT n.*, q.title as quiz_title, q.id as quiz_id 
    FROM notifications n
    JOIN quizzes q ON n.link LIKE CONCAT('%quiz_id=', q.id)
    LEFT JOIN reward_claims rc ON rc.quiz_id = q.id AND rc.user_id = n.user_id
    WHERE n.user_id = ? 
      AND n.type = 'challenge_winner'
      AND rc.id IS NULL
    ORDER BY n.created_at DESC
");
$stmtPending->execute([$userId]);
$pendingClaims = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="dashboard-container">
    <div class="container">
        <!-- Gamified Welcome Banner -->
        <div class="welcome-banner" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 1.5rem; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); margin-bottom: 2rem; border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -50%; right: -10%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(245,158,11,0.15) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; z-index: 0;"></div>
            
            <div class="row align-items-center position-relative" style="z-index: 1;">
                <!-- Left: Profile & Level Badge -->
                <div class="col-md-5 mb-3 mb-md-0 d-flex align-items-center gap-3">
                    <div style="position: relative;">
                        <img src="<?php echo !empty($user->profile_picture) 
                            ? (SITE_URL . '/' . ltrim(htmlspecialchars($user->profile_picture), '/')) 
                            : (SITE_URL . '/assets/img/default-avatar.png'); ?>" 
                             alt="Profile" 
                             style="width: 70px; height: 70px; border-radius: 50%; border: 3px solid #fbbf24; object-fit: cover; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);">
                        <div style="position: absolute; bottom: -5px; right: -5px; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; border: 2px solid #1e293b;" title="Current Level">
                            <?php echo $gamStats['level']; ?>
                        </div>
                    </div>
                    <div>
                        <h2 style="color: #f8fafc; font-weight: 800; font-size: 1.4rem; margin: 0; letter-spacing: -0.5px;">
                            <?php echo htmlspecialchars(explode(' ', $user->name)[0]); ?>
                        </h2>
                        <div style="color: #fbbf24; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 0.4rem;">
                            <?php 
                                $uniRankNames = [
                                    1 => 'Freshman', 2 => 'Sophomore', 3 => 'Junior Analyst', 
                                    4 => 'Senior Analyst', 5 => 'Research Assistant', 6 => 'Graduate Scholar', 
                                    7 => 'Master\'s Candidate', 8 => 'Ph.D. Researcher', 9 => 'Associate Fellow', 10 => 'Tenured Professor'
                                ];
                                $userRankName = $uniRankNames[$gamStats['level']] ?? 'Freshman';
                            ?>
                            <i class="fas fa-university"></i> Lvl <?php echo $gamStats['level']; ?> <?php echo $userRankName; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Center: XP Progress -->
                <div class="col-md-4 mb-3 mb-md-0">
                    <?php $progress = $gamStats['level_progress']; ?>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span style="font-size: 0.75rem; color: #cbd5e1; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Degree Progress</span>
                        <span style="font-size: 0.8rem; color: #fbbf24; font-weight: 800;"><?php echo number_format($gamStats['points']); ?> XP</span>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); border-radius: 10px; height: 8px; overflow: hidden; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);">
                        <div style="background: linear-gradient(90deg, #fbbf24, #f59e0b); height: 100%; border-radius: 10px; width: <?php echo $progress['progress']; ?>%; box-shadow: 0 0 10px rgba(245,158,11,0.5);"></div>
                    </div>
                    <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.3rem; text-align: right;">
                        <?php if ($progress['points_needed'] > 0): ?>
                            <?php echo $progress['points_needed']; ?> XP to reach Level <?php echo $progress['current'] + 1; ?>
                        <?php else: ?>
                            Max Academic Rank Reached! 🏆
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right: Stats blocks -->
                <div class="col-md-3 d-flex justify-content-md-end gap-2">
                    <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 0.5rem 0.75rem; text-align: center; flex: 1;">
                        <div style="font-size: 1rem; color: #f59e0b; margin-bottom: -2px;"><i class="fas fa-fire-alt"></i></div>
                        <div style="font-size: 1.1rem; font-weight: 800; color: #f8fafc; line-height: 1;"><?php echo $gamStats['streak']; ?></div>
                        <div style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase;">Streak</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 0.5rem 0.75rem; text-align: center; flex: 1;">
                        <div style="font-size: 1rem; color: #10b981; margin-bottom: -2px;"><i class="fas fa-medal"></i></div>
                        <div style="font-size: 1.1rem; font-weight: 800; color: #f8fafc; line-height: 1;"><?php echo count($gamStats['badges']); ?></div>
                        <div style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase;">Badges</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Reward Claims Alerts -->
        <?php if (!empty($pendingClaims)): ?>
            <div class="mb-4">
                <?php foreach ($pendingClaims as $claim): ?>
                    <div class="alert alert-warning border-0 shadow-sm d-flex justify-content-between align-items-center rounded-3 p-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-warning text-dark"><i class="fas fa-gift me-1"></i> Reward Pending</span>
                                <small class="text-muted"><?php echo date('M j, Y', strtotime($claim['created_at'])); ?></small>
                            </div>
                            <h6 class="mb-0 fw-bold">You haven't claimed your prize for "<?php echo htmlspecialchars($claim['quiz_title']); ?>"!</h6>
                            <p class="mb-0 text-muted" style="font-size:0.85rem;">Submit your details so we can process your reward.</p>
                        </div>
                        <a href="<?php echo SITE_URL; ?>/claim_reward.php?quiz_id=<?php echo $claim['quiz_id']; ?>" class="btn btn-warning btn-sm shadow-sm fw-bold">
                            Claim Now <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left Column: Profile & Navigation -->
            <div class="col-lg-4 order-2 order-lg-1">
                <!-- User Profile Card -->
                <div class="dash-card profile-card mb-4">
                    <div class="profile-img-container">
                        <img src="<?php echo !empty($user->profile_picture) 
                            ? (SITE_URL . '/' . ltrim(htmlspecialchars($user->profile_picture), '/')) 
                            : (SITE_URL . '/assets/img/default-avatar.png'); ?>" 
                             alt="Profile" 
                             class="profile-img">
                        <div class="profile-badge">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                    <h3 class="profile-name"><?php echo htmlspecialchars($user->name); ?></h3>
                    <div class="profile-role">
                        <?php echo $userRankName; ?>
                    </div>
                    <div class="profile-details">
                        <p class="mb-2"><i class="fas fa-university me-2 text-muted"></i><?php echo htmlspecialchars($user->university ?? ''); ?></p>
                        <p class="mb-2"><i class="fas fa-graduation-cap me-2 text-muted"></i><?php echo htmlspecialchars($user->department ?? ''); ?></p>
                        <div class="badge bg-light text-dark border p-2"><?php echo htmlspecialchars($user->level ?? ''); ?></div>
                    </div>
                    
                    <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-outline-primary w-100 mt-4 rounded-pill">
                        Edit Profile
                    </a>
                </div>

                <!-- Info Cards Mobile (Visible on mobile, in standard flow) -->
                <!-- We can actually keep these here or move them. Let's keep a Level/CGPA hint here -->
            </div>

            <!-- Right Column: Stats & Content -->
            <div class="col-lg-8 order-1 order-lg-2">
                <!-- Gamification Progress (University Adjusted) -->
                <div class="dash-card mb-4" style="height: auto;">
                    <div class="section-header">
                        <h4 class="section-title"><i class="fas fa-fire-alt me-2" style="color: #f59e0b;"></i>Degree Progress</h4>
                    </div>
                    <?php $progress = $gamStats['level_progress']; ?>
                    <div class="row g-3">
                        <!-- Level & XP Bar -->
                        <div class="col-12">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 900; color: #fff; flex-shrink: 0; box-shadow: 0 4px 12px rgba(16,185,129,0.25);">
                                    <?php echo $gamStats['level']; ?>
                                </div>
                                <div style="flex: 1;">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span style="font-weight: 700; font-size: 0.9rem; color: #1e293b;">Level <?php echo $gamStats['level']; ?></span>
                                        <span style="font-weight: 700; font-size: 0.85rem; color: #10b981;"><?php echo number_format($gamStats['points']); ?> XP</span>
                                    </div>
                                    <div style="background: #e2e8f0; border-radius: 10px; height: 8px; overflow: hidden;">
                                        <div style="background: linear-gradient(90deg, #10b981, #059669); height: 100%; border-radius: 10px; width: <?php echo $progress['progress']; ?>%; transition: width 0.5s;"></div>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">
                                        <?php if ($progress['points_needed'] > 0): ?>
                                            <?php echo $progress['points_needed']; ?> XP to Level <?php echo $progress['current'] + 1; ?>
                                        <?php else: ?>
                                            Max Academic Rank Reached! 🏆
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Upcoming Level Tiers Roadmap -->
                        <div class="col-12 mt-3">
                            <div class="p-3" style="background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1px solid #e2e8f0; border-radius: 14px;">
                                <h6 style="font-size: 0.75rem; text-transform: uppercase; font-weight: 800; color: #64748b; margin-bottom: 0.75rem; letter-spacing: 0.5px;">Upcoming Academic Tiers</h6>
                                <div class="d-flex flex-column gap-2">
                                    <?php 
                                    $currentLevel = $gamStats['level'];
                                    $levelsShown = 0;
                                    $icons = [
                                        1 => 'fas fa-seedling', 2 => 'fas fa-leaf', 3 => 'fas fa-star', 
                                        4 => 'fas fa-shield-alt', 5 => 'fas fa-fire', 6 => 'fas fa-gem', 
                                        7 => 'fas fa-crown', 8 => 'fas fa-dragon', 9 => 'fas fa-rocket', 10 => 'fas fa-trophy'
                                    ];
                                    $colors = [
                                        1 => '#94a3b8', 2 => '#cbd5e1', 3 => '#fcd34d', 
                                        4 => '#60a5fa', 5 => '#f87171', 6 => '#c084fc', 
                                        7 => '#fbbf24', 8 => '#ef4444', 9 => '#8b5cf6', 10 => '#eab308'
                                    ];
                                    
                                    foreach (Gamification::LEVELS as $lvl => $reqPoints) {
                                        if ($lvl <= $currentLevel) continue; // Skip unlocked
                                        if ($levelsShown >= 3) break; // Only show next 3
                                        
                                        $icon = $icons[$lvl] ?? 'fas fa-star';
                                        $color = $colors[$lvl] ?? '#ccc';
                                        $remaining = $reqPoints - $gamStats['points'];
                                        
                                        echo '
                                        <div class="d-flex align-items-center justify-content-between p-2" style="background: #fff; border-radius: 8px; border: 1px solid rgba(0,0,0,0.05);">
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width:28px; height:28px; border-radius:6px; background:'.$color.'15; color:'.$color.'; display:flex; align-items:center; justify-content:center; font-size:0.8rem;">
                                                    <i class="'.$icon.'"></i>
                                                </div>
                                                <div>
                                                    <div style="font-size: 0.8rem; font-weight: 800; color: #334155; line-height: 1.2;">'.$uniRankNames[$lvl].'</div>
                                                    <div style="font-size: 0.65rem; font-weight: 600; color: #94a3b8; text-transform: uppercase;">Level '.$lvl.'</div>
                                                </div>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="font-size: 0.75rem; font-weight: 800; color: #10b981;">'.number_format($remaining).' XP</div>
                                                <div style="font-size: 0.6rem; color: #94a3b8; text-transform: uppercase;">Needed</div>
                                            </div>
                                        </div>';
                                        $levelsShown++;
                                    }
                                    
                                    if ($levelsShown === 0) {
                                        echo '<div class="text-center py-3">
                                            <i class="fas fa-crown mb-2" style="font-size: 1.5rem; color: #fbbf24;"></i>
                                            <div style="font-size: 0.8rem; font-weight: 700; color: #f59e0b;">Maximum Academic Tier Reached!</div>
                                        </div>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Badges Row -->
                    <?php if (!empty($gamStats['badges'])): ?>
                    <div style="margin-top: 1rem; border-top: 1px solid #e2e8f0; padding-top: 0.75rem;">
                        <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 0.5rem;">Earned Badges</div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach (array_slice($gamStats['badges'], 0, 6) as $badge): ?>
                            <div title="<?php echo htmlspecialchars($badge['label']); ?>" style="background: <?php echo $badge['color']; ?>15; border: 1px solid <?php echo $badge['color']; ?>33; border-radius: 20px; padding: 0.3rem 0.65rem; display: flex; align-items: center; gap: 0.3rem;">
                                <span style="font-size: 0.85rem;"><?php echo $badge['icon']; ?></span>
                                <span style="color: <?php echo $badge['color']; ?>; font-weight: 600; font-size: 0.7rem;"><?php echo htmlspecialchars($badge['label']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- University Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="stat-card stat-cgpa h-100" style="justify-content: center; text-align: center; flex-direction: column; padding: 2rem;">
                            <div class="stat-icon mb-3" style="margin-right: 0; width: 80px; height: 80px; font-size: 2rem;"><i class="fas fa-calculator"></i></div>
                            <div class="stat-info">
                                <h3>Calculate</h3>
                                <p>CGPA & Goals</p>                                
                            </div>
                            <a href="<?php echo SITE_URL; ?>/cgpa-calculator.php" class="stretched-link"></a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mb-4">
                    <div class="section-header">
                        <h4 class="section-title">Quick Actions</h4>
                    </div>
                    <div class="quick-links-grid">
                        <a href="<?php echo SITE_URL; ?>/materials.php" class="quick-link-item">
                            <i class="fas fa-book quick-link-icon"></i>
                            <span class="quick-link-title">Course Materials</span>
                        </a>
                        <a href="<?php echo SITE_URL; ?>/quizzes.php" class="quick-link-item">
                            <i class="fas fa-question-circle quick-link-icon"></i>
                            <span class="quick-link-title">Practice Quizzes</span>
                        </a>
                        <a href="<?php echo SITE_URL; ?>/timetable.php" class="quick-link-item">
                            <i class="fas fa-calendar-alt quick-link-icon"></i>
                            <span class="quick-link-title">My Timetable</span>
                        </a>
                        <a href="<?php echo SITE_URL; ?>/tools.php" class="quick-link-item">
                            <i class="fas fa-toolbox quick-link-icon"></i>
                            <span class="quick-link-title">Student Tools</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="dash-card">
                    <div class="section-header">
                        <h4 class="section-title">Recent Activity</h4>
                    </div>
                    <ul class="activity-list">
                        <li class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-university"></i>
                            </div>
                            <div class="activity-content">
                                <h6>Welcome to University Dashboard</h6>
                                <p>Access all your materials and tools in one place.</p>
                                <span class="activity-time">Just now</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['is_first_login']) && $_SESSION['is_first_login'] === true): ?>
<!-- First-Time Login Welcome Modal -->
<div class="modal fade" id="welcomeModal" tabindex="-1" aria-labelledby="welcomeModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.15);">
            <!-- Header -->
            <div class="modal-header border-bottom-0 pb-0" style="background: linear-gradient(135deg, #fef7ed 0%, #ffffff 100%);">
                <div class="text-center w-100 pt-4 pb-2">
                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.5rem; margin-bottom: 1.5rem; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);">
                        <i class="fas fa-hand-sparkles"></i>
                    </div>
                    <h3 class="modal-title fw-bold" id="welcomeModalLabel" style="color: #1e293b;">Welcome to <?php echo SITE_NAME; ?>!</h3>
                    <p class="text-muted mb-0" style="font-size: 1.1rem;">We're thrilled to have you here, <?php echo htmlspecialchars($user->name); ?>.</p>
                </div>
                <button type="button" class="btn-close position-absolute top-0 end-0 m-4" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Body -->
            <div class="modal-body px-4 px-md-5 py-4 custom-scrollbar" style="overflow-x: hidden;">
                <p class="text-center mb-4 text-secondary" style="font-size: 1.05rem; line-height: 1.6;">
                    Your account is set up and ready to go! Here is a quick guide on how to get the most out of our platform:
                </p>

                <div class="row g-4 mt-2">
                    <!-- Feature 1 -->
                    <div class="col-12">
                        <div class="d-flex align-items-start gap-3 p-4 rounded-4" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="flex-shrink-0" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                                <i class="fas fa-brain"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-2">Practice Quizzes</h5>
                                <p class="text-muted mb-0" style="font-size: 0.95rem; line-height: 1.6;">
                                    Sharpen your skills with our extensive library of General Quizzes. They are entirely free, un-timed, and perfect for studying at your own pace.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 2 -->
                    <div class="col-12">
                        <div class="d-flex align-items-start gap-3 p-4 rounded-4" style="background: #fffbeb; border: 1px solid #fef3c7;">
                            <div class="flex-shrink-0" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-2">Special Challenges & Rewards</h5>
                                <p class="text-muted mb-0" style="font-size: 0.95rem; line-height: 1.6;">
                                    Compete against other students in timed Special Challenges! If you score high and rank on the leaderboard, you can instantly earn and claim real rewards like <strong>Cash (₦)</strong>, <strong>Data (GB)</strong>, and <strong>Airtime</strong>.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 3 -->
                    <div class="col-12">
                        <div class="d-flex align-items-start gap-3 p-4 rounded-4" style="background: #f0fdf4; border: 1px solid #dcfce7;">
                            <div class="flex-shrink-0" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                                <i class="fas fa-book-open"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-2">Study Materials</h5>
                                <p class="text-muted mb-0" style="font-size: 0.95rem; line-height: 1.6;">
                                    Browse through our curated collection of notes and past questions. You can also actively contribute to the community by uploading and sharing your own useful study materials!
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 4 -->
                    <div class="col-12">
                        <div class="d-flex align-items-start gap-3 p-4 rounded-4" style="background: #eff6ff; border: 1px solid #dbeafe;">
                            <div class="flex-shrink-0" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                                <i class="fas fa-khanda"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-2">Battle Arena</h5>
                                <p class="text-muted mb-0" style="font-size: 0.95rem; line-height: 1.6;">
                                    Challenge friends (or other students) to head‑to‑head battles, take the battle quiz, and prove your skills. Win more battles to boost your rank and reputation.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 5 -->
                    <div class="col-12">
                        <div class="d-flex align-items-start gap-3 p-4 rounded-4" style="background: #fdf2f8; border: 1px solid #fce7f3;">
                            <div class="flex-shrink-0" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(236, 72, 153, 0.12); color: #ec4899; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-2">Community Hub</h5>
                                <p class="text-muted mb-0" style="font-size: 0.95rem; line-height: 1.6;">
                                    Ask questions, share answers, and learn faster with other students. Great for explanations, past questions, and study tips.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature 6 -->
                    <div class="col-12">
                        <div class="d-flex align-items-start gap-3 p-4 rounded-4" style="background: #fff7ed; border: 1px solid #ffedd5;">
                            <div class="flex-shrink-0" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                                <i class="fas fa-comment-dots"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-2">Messaging</h5>
                                <p class="text-muted mb-0" style="font-size: 0.95rem; line-height: 1.6;">
                                    Chat privately with other users, share quick updates, and stay connected while you study.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-top-0 pt-0 pb-4 px-4 px-md-5 justify-content-center bg-white">
                <button type="button" class="btn btn-primary btn-lg w-100 py-3 rounded-pill fw-bold" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; box-shadow: 0 8px 20px rgba(16, 185, 129, 0.25);" data-bs-dismiss="modal">
                    Let's Get Started <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var welcomeModalEl = document.getElementById('welcomeModal');
    if (welcomeModalEl && typeof bootstrap !== 'undefined') {
        var welcomeModal = new bootstrap.Modal(welcomeModalEl);
        welcomeModal.show();
    }
});
</script>
<?php 
    // Unset the flag immediately so the modal never shows again
    unset($_SESSION['is_first_login']); 
endif; 
?>

<?php 
// Fix for included footer path which expects to be in root usually or handles relative paths
// If footer uses relative paths like 'assets/', they might break if included from a subdir without base URL adjustment
// But SITE_URL is used in footer generally, so it should be fine.
include __DIR__ . '/../includes/footer.php'; 
?>
