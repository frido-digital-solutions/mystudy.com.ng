<?php
// Include configuration and initialize database connection
require_once 'config/config.php';

// Require login to access this page
require_login();

// Get user information using PDO handle from config
$user = new User($pdo);
$user->getById($_SESSION['user_id']);
$userId = (int)$_SESSION['user_id'];

// --- Fetch real dashboard stats ---

// Quizzes completed (distinct quiz_id from quiz_attempts)
$stmtQuiz = $pdo->prepare("SELECT COUNT(DISTINCT quiz_id) AS cnt FROM quiz_attempts WHERE user_id = ?");
$stmtQuiz->execute([$userId]);
$quizzesCompleted = (int)$stmtQuiz->fetchColumn();

// Total quiz attempts
$stmtAttempts = $pdo->prepare("SELECT COUNT(*) AS cnt FROM quiz_attempts WHERE user_id = ?");
$stmtAttempts->execute([$userId]);
$totalAttempts = (int)$stmtAttempts->fetchColumn();

// Best score percentage
$stmtBest = $pdo->prepare("SELECT MAX(ROUND(score / total * 100, 0)) AS best FROM quiz_attempts WHERE user_id = ? AND total > 0");
$stmtBest->execute([$userId]);
$bestScore = (int)($stmtBest->fetchColumn() ?? 0);

// Materials available for this user's level
$materialsCount = 0;
try {
    $stmtMat = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE status = 'approved'");
    $stmtMat->execute();
    $materialsCount = (int)$stmtMat->fetchColumn();
} catch (Exception $e) {
    $materialsCount = 0;
}

// --- Fetch recent activity (last 5 quiz attempts) ---
$recentActivity = [];
try {
    $stmtActivity = $pdo->prepare("
        SELECT qa.id AS attempt_id, qa.score, qa.total, qa.completed_at, qa.quiz_id, q.title AS quiz_title, q.mode
        FROM quiz_attempts qa
        LEFT JOIN quizzes q ON qa.quiz_id = q.id
        WHERE qa.user_id = ?
        ORDER BY qa.completed_at DESC
        LIMIT 5
    ");
    $stmtActivity->execute([$userId]);
    $recentActivity = $stmtActivity->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentActivity = [];
}

// --- Gamification stats ---
require_once __DIR__ . '/models/Gamification.php';
$gamification = new Gamification($pdo);
$gamStats = $gamification->getUserStats($userId);
$motivationalMsg = $gamification->getMotivationalMessage($userId);

// --- Pending Reward Claims ---
// Find 'challenge_winner' notifications where no reward_claim exists yet
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

// Set page title
$page_title = 'Dashboard';

// Include header
include 'includes/header.php';
?>

<!-- Styles loaded from design-system.css -->

<div class="dashboard-container">
    <div class="container">
        <div class="welcome-banner" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 1.5rem; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); margin-bottom: 2rem; border: 1px solid rgba(255,255,255,0.05); position: relative; overflow: hidden;">
            <!-- Decorative background elements -->
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
                                $rankNames = [
                                    1 => 'Novice', 2 => 'Apprentice', 3 => 'Scholar', 
                                    4 => 'Guardian', 5 => 'Prefect', 6 => 'Elite Student', 
                                    7 => 'Commander', 8 => 'Master', 9 => 'Grandmaster', 10 => 'Legend'
                                ];
                                $userRankName = $rankNames[$gamStats['level']] ?? 'Scholar';
                            ?>
                            <i class="fas fa-star"></i> Lvl <?php echo $gamStats['level']; ?> <?php echo $userRankName; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Center: XP Progress -->
                <div class="col-md-4 mb-3 mb-md-0">
                    <?php $progress = $gamStats['level_progress']; ?>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span style="font-size: 0.75rem; color: #cbd5e1; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Level Progress</span>
                        <span style="font-size: 0.8rem; color: #fbbf24; font-weight: 800;"><?php echo number_format($gamStats['points']); ?> XP</span>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); border-radius: 10px; height: 8px; overflow: hidden; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);">
                        <div style="background: linear-gradient(90deg, #fbbf24, #f59e0b); height: 100%; border-radius: 10px; width: <?php echo $progress['progress']; ?>%; box-shadow: 0 0 10px rgba(245,158,11,0.5);"></div>
                    </div>
                    <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.3rem; text-align: right;">
                        <?php if ($progress['points_needed'] > 0): ?>
                            <?php echo $progress['points_needed']; ?> XP to reach Level <?php echo $progress['current'] + 1; ?>
                        <?php else: ?>
                            Max Level Reached! 🏆
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

        <!-- Stats Row — visible on mobile/tablet, hidden on desktop -->
        <div class="row g-3 mb-4 d-lg-none">
            <div class="col-4">
                <div class="dash-card stat-card stat-materials border-0 text-center flex-column p-3">
                    <div class="stat-icon mx-auto mb-2"><i class="fas fa-book-open"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $materialsCount; ?></h3>
                        <p>Materials</p>
                    </div>
                </div>
            </div>
            <div class="col-4">
                <div class="dash-card stat-card stat-quizzes border-0 text-center flex-column p-3">
                    <div class="stat-icon mx-auto mb-2"><i class="fas fa-tasks"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $quizzesCompleted; ?></h3>
                        <p>Quizzes</p>
                    </div>
                </div>
            </div>
            <div class="col-4">
                <div class="dash-card stat-card stat-quizzes border-0 text-center flex-column p-3">
                    <div class="stat-icon mx-auto mb-2"><i class="fas fa-trophy"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $bestScore; ?>%</h3>
                        <p>Best Score</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Profile -->
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
                        <?php echo ($user->user_type === 'university') ? 'University Student' : 'Secondary Student'; ?>
                    </div>
                    
                    <?php if ($user->user_type === 'university'): ?>
                    <div class="profile-details">
                        <p class="mb-1"><i class="fas fa-university me-2 text-muted"></i><?php echo htmlspecialchars($user->university ?? ''); ?></p>
                        <p class="mb-0"><i class="fas fa-graduation-cap me-2 text-muted"></i><?php echo htmlspecialchars($user->department ?? ''); ?> • <?php echo htmlspecialchars($user->level ?? ''); ?></p>
                    </div>
                    <?php else: ?>
                    <div class="profile-details">
                        <p class="mb-0"><i class="fas fa-school me-2 text-muted"></i>Secondary School Student</p>
                    </div>
                    <?php endif; ?>
                    
                    <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-outline-primary w-100 mt-3 rounded-pill">
                        Edit Profile
                    </a>
                </div>
            </div>

            <!-- Right Column: Stats & Content -->
            <div class="col-lg-8 order-1 order-lg-2">
                <!-- Desktop Stats (hidden on mobile) -->
                <div class="row g-4 mb-4 d-none d-lg-flex">
                    <div class="col-md-4">
                        <div class="dash-card stat-card stat-materials h-100 border-0">
                            <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                            <div class="stat-info">
                                <h3><?php echo $materialsCount; ?></h3>
                                <p>Study Materials</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dash-card stat-card stat-quizzes h-100 border-0">
                            <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                            <div class="stat-info">
                                <h3><?php echo $quizzesCompleted; ?></h3>
                                <p>Quizzes Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dash-card stat-card stat-quizzes h-100 border-0">
                            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                            <div class="stat-info">
                                <h3><?php echo $bestScore; ?>%</h3>
                                <p>Best Score</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gamification Progress -->
                <div class="dash-card mb-4" style="height: auto;">
                    <div class="section-header">
                        <h4 class="section-title"><i class="fas fa-fire-alt me-2" style="color: #f59e0b;"></i>Your Progress</h4>
                    </div>
                    <?php $progress = $gamStats['level_progress']; ?>
                    <div class="row g-3">
                        <!-- Level & XP Bar -->
                        <div class="col-md-7">
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
                                            Max Level Reached! 🏆
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($motivationalMsg): ?>
                            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 0.6rem 0.85rem; font-size: 0.85rem; color: #92400e; font-weight: 600; margin-top: 0.5rem;">
                                <?php echo htmlspecialchars($motivationalMsg); ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Upcoming Level Tiers Roadmap -->
                        <div class="col-md-5">
                            <div class="h-100 p-3" style="background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1px solid #e2e8f0; border-radius: 14px;">
                                <h6 style="font-size: 0.75rem; text-transform: uppercase; font-weight: 800; color: #64748b; margin-bottom: 0.75rem; letter-spacing: 0.5px;">Upcoming Tiers</h6>
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
                                                    <div style="font-size: 0.8rem; font-weight: 800; color: #334155; line-height: 1.2;">'.$rankNames[$lvl].'</div>
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
                                            <div style="font-size: 0.8rem; font-weight: 700; color: #f59e0b;">Maximum Level Reached!</div>
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

                <!-- Quick Actions -->
                <div class="mb-4">
                    <div class="section-header">
                        <h4 class="section-title">Quick Actions</h4>
                    </div>
                    <div class="quick-links-grid">
                        <a href="<?php echo SITE_URL; ?>/materials.php" class="quick-link-item">
                            <i class="fas fa-book quick-link-icon"></i>
                            <span class="quick-link-title">Browse Materials</span>
                        </a>
                        <a href="<?php echo SITE_URL; ?>/quizzes.php" class="quick-link-item">
                            <i class="fas fa-question-circle quick-link-icon"></i>
                            <span class="quick-link-title">Take a Quiz</span>
                        </a>
                        <a href="<?php echo SITE_URL; ?>/tutorials.php" class="quick-link-item">
                            <i class="fas fa-play-circle quick-link-icon"></i>
                            <span class="quick-link-title">Video Tutorials</span>
                        </a>
                        <a href="<?php echo SITE_URL; ?>/timetable.php" class="quick-link-item">
                            <i class="fas fa-calendar-alt quick-link-icon"></i>
                            <span class="quick-link-title">My Timetable</span>
                        </a>
                        <a href="<?php echo SITE_URL; ?>/my_quizzes.php" class="quick-link-item" style="border: 2px solid rgba(16, 185, 129, 0.2); background: rgba(16, 185, 129, 0.03);">
                            <i class="fas fa-history quick-link-icon" style="color: #10b981;"></i>
                            <span class="quick-link-title" style="color: #059669; font-weight: 700;">My Quizzes</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="dash-card" style="height: auto;">
                    <div class="section-header">
                        <h4 class="section-title">Recent Activity</h4>
                        <?php if (!empty($recentActivity)): ?>
                        <a href="<?php echo SITE_URL; ?>/my_quizzes.php" class="btn btn-sm btn-link text-decoration-none">View All</a>
                        <?php endif; ?>
                    </div>
                    <ul class="activity-list" style="max-height: 350px; overflow-y: auto;">
                        <?php if (!empty($recentActivity)): ?>
                            <?php foreach ($recentActivity as $activity): 
                                $pct = ($activity['total'] > 0) 
                                    ? round(($activity['score'] / $activity['total']) * 100) 
                                    : 0;
                                $timeAgo = '';
                                $diff = time() - strtotime($activity['completed_at']);
                                if ($diff < 60) $timeAgo = 'Just now';
                                elseif ($diff < 3600) $timeAgo = floor($diff / 60) . ' min ago';
                                elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . ' hours ago';
                                elseif ($diff < 604800) $timeAgo = floor($diff / 86400) . ' days ago';
                                else $timeAgo = date('M j, Y', strtotime($activity['completed_at']));
                                
                                $isChallenge = ($activity['mode'] ?? '') === 'challenge';
                            ?>
                            <li class="activity-item">
                                <a href="<?php echo SITE_URL; ?>/quiz_result.php?attempt=<?php echo $activity['attempt_id']; ?>" class="d-flex gap-3 text-decoration-none w-100">
                                    <div class="activity-icon">
                                        <i class="fas <?php echo $isChallenge ? 'fa-trophy' : 'fa-check-circle'; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6>
                                            <?php echo $isChallenge ? 'Challenge: ' : ''; ?>
                                            <?php echo htmlspecialchars($activity['quiz_title'] ?? 'Quiz'); ?>
                                        </h6>
                                        <p>Scored <?php echo $activity['score']; ?>/<?php echo $activity['total']; ?> (<?php echo $pct; ?>%)</p>
                                        <span class="activity-time"><?php echo $timeAgo; ?></span>
                                    </div>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-rocket"></i>
                                </div>
                                <div class="activity-content">
                                    <h6>Welcome to <?php echo SITE_NAME; ?>!</h6>
                                    <p>Start taking quizzes and browsing materials to see your activity here.</p>
                                    <span class="activity-time">Get started today!</span>
                                </div>
                            </li>
                        <?php endif; ?>
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
// Include footer
include 'includes/footer.php';
?>
