<?php
// views/user/profile_public.php - Public User Profile Template
// Variables incoming: $user, $gamification, $currentLevelObj, $nextLevelObj, $totalXP, $rankName, $memberSince, $recentDiscussions, $earnedBadges

$page_title = htmlspecialchars($user->name) . "'s Profile";
include __DIR__ . '/../../includes/header.php';
?>

<style>
    /* Public Profile Syles */
    .profile-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-radius: 1rem;
        padding: 3rem 2.5rem;
        position: relative;
        overflow: hidden;
        margin-bottom: 2rem;
        border: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        gap: 2.5rem;
    }
    
    .profile-hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: url('<?php echo SITE_URL; ?>/assets/img/pattern-dots.svg') repeat;
        opacity: 0.1;
    }
    
    .profile-avatar-container {
        position: relative;
        z-index: 10;
        width: 140px;
        height: 140px;
        border-radius: 50%;
        padding: 5px;
        background: rgba(255, 255, 255, 0.1);
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        flex-shrink: 0;
    }
    
    .profile-avatar {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .profile-rank-badge {
        position: absolute;
        bottom: 0;
        right: 0;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        border: 2px solid #0f172a;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);
    }
    
    .profile-header-info {
        position: relative;
        z-index: 10;
        color: white;
    }
    
    .profile-name {
        font-size: 2.2rem;
        font-weight: 800;
        color: white;
        margin-bottom: 0.5rem;
    }
    
    .profile-meta {
        font-size: 1.05rem;
        color: #cbd5e1;
        display: flex;
        align-items: center;
        gap: 1.5rem;
        flex-wrap: wrap;
    }
    
    .meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    /* --- Premium Rank & XP Cards --- */
    .premium-stat-card {
        border-radius: 16px;
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        gap: 1.25rem;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid rgba(255, 255, 255, 0.4);
    }
    .premium-stat-card:hover {
        transform: translateY(-5px);
    }
    
    .premium-xp-card {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.15);
        border: 1px solid rgba(59, 130, 246, 0.2);
    }
    .premium-xp-card:hover { box-shadow: 0 15px 25px -5px rgba(59, 130, 246, 0.25); }
    
    .premium-rank-card {
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        box-shadow: 0 10px 20px -5px rgba(245, 158, 11, 0.15);
        border: 1px solid rgba(245, 158, 11, 0.2);
    }
    .premium-rank-card:hover { box-shadow: 0 15px 25px -5px rgba(245, 158, 11, 0.25); }
    
    .stat-icon-wrapper {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        flex-shrink: 0;
        box-shadow: inset 0 2px 4px rgba(255,255,255,0.5), 0 4px 10px rgba(0,0,0,0.05);
    }
    
    .xp-icon { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
    .rank-icon { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
    
    .stat-content {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: 900;
        margin-bottom: 0;
        line-height: 1.1;
        letter-spacing: -0.5px;
    }
    
    .xp-value { color: #1e3a8a; }
    .rank-value { color: #92400e; font-size: 1.5rem !important; }
    
    .stat-label {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 0.25rem;
    }
    
    .xp-label { color: #3b82f6; }
    .rank-label { color: #d97706; }
    
    /* Decorative element inside cards */
    .stat-deco {
        position: absolute;
        right: -10px;
        bottom: -20px;
        font-size: 6rem;
        opacity: 0.05;
        transform: rotate(-15deg);
        pointer-events: none;
    }
    
    .badge-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .badge-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    
    .discussion-card {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.2s;
    }
    
    .discussion-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }
    
    .discussion-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #0f172a;
        text-decoration: none;
        margin-bottom: 0.5rem;
        display: block;
    }
    
    .discussion-title:hover {
        color: var(--primary);
    }
    
    .discussion-meta {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        font-size: 0.9rem;
        color: #64748b;
    }
    
    @media (max-width: 768px) {
        .profile-hero { 
            flex-direction: column;
            text-align: center;
            padding: 2.5rem 1.5rem;
            gap: 1.5rem;
        }
        .profile-avatar-container { margin: 0 auto; }
        .profile-meta { justify-content: center; gap: 1rem; }
    }
</style>

<div class="container py-5">
    
    <!-- Hero Banner & Avatar Overlap -->
    <div class="profile-hero mb-4">
        <div class="profile-avatar-container">
            <img src="<?php echo !empty($user->profile_picture)
    ? (SITE_URL . '/' . ltrim(htmlspecialchars($user->profile_picture), '/'))
    : (SITE_URL . '/assets/img/default-avatar.png'); ?>" 
                 alt="<?php echo htmlspecialchars($user->name); ?>'s Avatar" class="profile-avatar">
            
            <?php if ($user->privacy_show_stats): ?>
                <div class="profile-rank-badge" title="<?php echo htmlspecialchars($rankName); ?>" style="bottom: auto; top: 0rem; right: 0rem; box-shadow: 0 4px 10px rgba(0,0,0,0.4);">
                    <i class="fas fa-medal"></i>
                </div>
                <div style="position: absolute; bottom: -18px; left: 50%; transform: translateX(-50%); white-space: nowrap; z-index: 20;">
                    <span class="badge rounded-pill shadow-lg" style="background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); color: #fff; border: 2px solid #0f172a; padding: 0.45rem 1rem; font-size: 0.85rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;">
                        <i class="fas fa-star text-light me-1" style="font-size:0.75rem;"></i> Lvl <?php echo (int)$numericLevel; ?> &bull; <?php echo htmlspecialchars($rankName); ?>
                    </span>
                </div>
            <?php
endif; ?>
        </div>
        
        <!-- Basic Info Header -->
        <div class="profile-header-info">
            <h1 class="profile-name d-flex align-items-center flex-wrap gap-3">
                <span>
                    <?php echo htmlspecialchars($user->name); ?>
                    <?php if ($user->user_type === 'admin'): ?>
                        <i class="fas fa-shield-alt text-danger ms-2" style="font-size: 1.2rem;" title="Admin"></i>
                    <?php
endif; ?>
                </span>
                
                <?php if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] !== $target_user_id):
    // Check messaging permissions
    require_once __DIR__ . '/../../models/Message.php';
    $msgModelPub = new Message($pdo);
    $canMsgPub = $msgModelPub->canMessage((int)$_SESSION['user_id'], $target_user_id);
?>
                <div class="d-flex gap-2 flex-wrap mt-1">
                    <a href="<?php echo SITE_URL; ?>/conversation.php?with=<?php echo $target_user_id; ?>" 
                       class="btn btn-sm rounded-pill px-4 fw-bold shadow-sm <?php echo $canMsgPub['allowed'] ? '' : 'disabled'; ?>"
                       style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; border: none; font-size: 0.85rem;"
                       <?php if (!$canMsgPub['allowed']): ?>title="<?php echo htmlspecialchars($canMsgPub['reason']); ?>"<?php
    endif; ?>>
                        <i class="fas fa-envelope me-1"></i> Message
                    </a>
                    <button type="button" class="btn btn-sm rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#challengeModal"
                            style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border: none; font-size: 0.85rem;">
                        <i class="fas fa-bolt me-1"></i> Challenge
                    </button>
                </div>
                <?php
endif; ?>
            </h1>
            
            <div class="profile-meta">
                <div class="meta-item">
                    <i class="fas fa-calendar-alt text-info"></i> 
                    Member Since <?php echo htmlspecialchars($memberSince); ?>
                </div>
                
                <?php if ($user->privacy_show_school && $user->user_type === 'university'): ?>
                    <div class="meta-item">
                        <i class="fas fa-university text-info"></i> 
                        <?php echo htmlspecialchars($user->university ?? 'University Student'); ?>
                    </div>
                    <?php if (!empty($user->department)): ?>
                        <div class="meta-item">
                            <i class="fas fa-book text-info"></i> 
                            <?php echo htmlspecialchars($user->department); ?>
                        </div>
                    <?php
    endif; ?>
                    <?php if (!empty($user->level)): ?>
                        <div class="meta-item">
                            <i class="fas fa-graduation-cap text-info"></i> 
                            <?php echo htmlspecialchars($user->level); ?>
                        </div>
                    <?php
    endif; ?>
                <?php
elseif ($user->user_type === 'secondary'): ?>
                     <div class="meta-item">
                        <i class="fas fa-school text-info"></i> 
                        Secondary School Student
                    </div>
                <?php
endif; ?>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        
        <!-- Left Sidebar: Stats & Badges -->
        <div class="col-lg-4">
            
            <?php if ($user->privacy_show_stats): ?>
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6 col-lg-12"> <!-- Stack nicely on large sidebars and very small mobile -->
                    <div class="premium-stat-card premium-xp-card">
                        <i class="fas fa-bolt stat-deco text-primary"></i>
                        <div class="stat-icon-wrapper xp-icon">
                            <i class="fas fa-hat-wizard"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value xp-value"><?php echo number_format($totalXP); ?></div>
                            <div class="stat-label xp-label">Total XP Hub</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-12">
                    <div class="premium-stat-card premium-rank-card">
                        <i class="fas fa-crown stat-deco text-warning"></i>
                        <div class="stat-icon-wrapper rank-icon">
                            <i class="fas fa-medal"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value rank-value" style="word-break: break-word;"><?php echo htmlspecialchars($rankName); ?></div>
                            <div class="stat-label rank-label">Global Rank</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
endif; ?>

            <?php if ($user->privacy_show_battles): ?>
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px; overflow: hidden; background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);">
                <div class="card-header border-0 pt-4 bg-transparent">
                    <h6 class="fw-bold mb-0" style="color: #0f172a;"><i class="fas fa-khanda text-danger me-2"></i>Battle Record</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted fw-bold" style="font-size: 0.9rem;">Global ELO Rating</span>
                        <span class="badge bg-dark rounded-pill px-3 py-2 shadow-sm fs-6"><i class="fas fa-fire text-warning me-1"></i> <?php echo number_format($battleStats['rating']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted fw-bold" style="font-size: 0.9rem;">Win Rate</span>
                        <span class="fw-bold text-success fs-5"><?php echo $winRate; ?>%</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted fw-bold" style="font-size: 0.9rem;">Current Streak</span>
                        <span class="fw-bold fs-5 text-primary"><?php echo (int)$battleStats['current_streak']; ?> <i class="fas fa-burn"></i></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted fw-bold" style="font-size: 0.9rem;">W / L / D</span>
                        <span class="fw-bold text-secondary"><?php echo (int)$battleStats['wins']; ?> - <?php echo (int)$battleStats['losses']; ?> - <?php echo (int)$battleStats['draws']; ?></span>
                    </div>
                </div>
            </div>
            <?php
endif; ?>

            <?php if (!$user->privacy_show_battles): ?>
            <!-- Switched off is not the same as never done, so the card says
                 who chose to hide it instead of printing a row of zeros. -->
            <div class="alert alert-light border d-flex align-items-center gap-3 p-4 mb-4">
                <i class="fas fa-lock fs-3 text-muted"></i>
                <div>
                    <h6 class="fw-bold mb-1">Battle record private</h6>
                    <p class="mb-0 text-muted small"><?php echo htmlspecialchars($user->name); ?> chose to keep their battles off this page.</p>
                </div>
            </div>
            <?php
endif; ?>

            <?php if ($user->privacy_show_badges): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom pb-0 pt-4">
                    <h5 class="fw-bold"><i class="fas fa-certificate text-primary me-2"></i>Recent Certificates</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($earnedBadges)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-medal fs-1 mb-2 opacity-25"></i>
                            <p class="mb-0">No certificates earned yet.</p>
                        </div>
                    <?php
    else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($earnedBadges as $badge): ?>
                                <div class="badge-card">
                                    <div class="badge-icon">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="line-height: 1.2; margin-bottom: 0.25rem;">
                                            <?php echo htmlspecialchars(strlen($badge['title']) > 30 ? substr($badge['title'], 0, 30) . '...' : $badge['title']); ?>
                                        </div>
                                        <div class="small text-muted">
                                            Score: <?php echo number_format($badge['score_percentage']); ?>% • <?php echo date('M j, Y', strtotime($badge['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php
        endforeach; ?>
                        </div>
                    <?php
    endif; ?>
                </div>
            </div>
            <?php
endif; ?>
            
            <?php if (!$user->privacy_show_stats && !$user->privacy_show_badges && !$user->privacy_show_discussions && !$user->privacy_show_school && !$user->privacy_show_battles): ?>
                <div class="alert alert-light text-center border">
                    <i class="fas fa-user-secret fs-1 text-muted mb-3 d-block mt-3"></i>
                    This user prefers to keep their profile details completely private.
                </div>
            <?php
endif; ?>
        </div>
        
        <!-- Right Main Content: Activity -->
        <div class="col-lg-8">
            
            <?php if ($user->privacy_show_discussions): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom pb-0 pt-4">
                    <h5 class="fw-bold"><i class="fas fa-comments text-primary me-2"></i>Recent Discussions</h5>
                </div>
                <div class="card-body p-4">
                    
                    <?php if (empty($recentDiscussions)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-comment-slash fs-1 mb-3 opacity-25"></i>
                            <p class="mb-0">This user hasn't started any discussions yet.</p>
                        </div>
                    <?php
    else: ?>
                        <div class="discussion-list">
                            <?php foreach ($recentDiscussions as $disc): ?>
                                <div class="discussion-card">
                                    <a href="<?php echo SITE_URL; ?>/discussion_view.php?id=<?php echo $disc['id']; ?>" class="discussion-title">
                                        <?php echo htmlspecialchars($disc['title']); ?>
                                    </a>
                                    
                                    <div class="discussion-meta">
                                        <span><i class="fas fa-clock me-1"></i> <?php echo date('M j, Y', strtotime($disc['created_at'])); ?></span>
                                        <span class="<?php echo $disc['score'] > 0 ? 'text-success' : ($disc['score'] < 0 ? 'text-danger' : ''); ?>">
                                            <i class="fas <?php echo $disc['score'] >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?> me-1"></i> 
                                            <?php echo number_format(abs($disc['score'])); ?> Votes
                                        </span>
                                        <span><i class="fas fa-eye me-1"></i> <?php echo number_format($disc['views']); ?> Views</span>
                                    </div>
                                </div>
                            <?php
        endforeach; ?>
                        </div>
                        
                        <?php if (count($recentDiscussions) === 5): ?>
                            <div class="text-center mt-3">
                                <a href="<?php echo SITE_URL; ?>/discussions.php" class="btn btn-outline-primary btn-sm rounded-pill px-4">See all in Community Hub</a>
                            </div>
                        <?php
        endif; ?>
                    <?php
    endif; ?>
                    
                </div>
            </div>
            <?php
else: ?>
                <div class="alert alert-light border d-flex align-items-center gap-3 p-4 mb-4">
                    <i class="fas fa-lock fs-3 text-muted"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Activity Hidden</h6>
                        <p class="mb-0 text-muted small">This user has chosen to hide their community forum activity.</p>
                    </div>
                </div>
            <?php
endif; ?>
            
            <?php if (isset($user->privacy_show_battles) ? $user->privacy_show_battles : 1): ?>
            <div class="card border-0 shadow-sm mt-2 mb-4">
                <div class="card-header bg-white border-bottom pb-0 pt-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-3"><i class="fas fa-history text-danger me-2"></i>Match History</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($battleHistory)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-flag-checkered fs-1 mb-3 opacity-25"></i>
                            <p class="mb-0">No completed battles yet.</p>
                        </div>
                    <?php
    else: ?>
                        <div class="list-group list-group-flush rounded-bottom">
                            <?php foreach ($battleHistory as $match):
            // Only display completed matches to the public
            if ($match['status'] !== 'completed')
                continue;

            $isChallenger = ((int)$match['challenger_id'] === $target_user_id);
            $opponentName = $isChallenger ? $match['opponent_name'] : $match['challenger_name'];
            $opponentAvatar = $isChallenger ? $match['opponent_avatar'] : $match['challenger_avatar'];
            $userScore = $isChallenger ? $match['challenger_score'] : $match['opponent_score'];
            $oppScore = $isChallenger ? $match['opponent_score'] : $match['challenger_score'];

            $isWinner = ((int)$match['winner_id'] === $target_user_id);
            $isDraw = empty($match['winner_id']);
            $resultIcon = $isWinner ? '<i class="fas fa-trophy text-warning"></i> Victory' : ($isDraw ? '<i class="fas fa-handshake text-info"></i> Draw' : '<i class="fas fa-times-circle text-danger"></i> Defeat');
?>
                            <div class="list-group-item d-flex justify-content-between align-items-center p-4">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?php echo !empty($opponentAvatar) ? SITE_URL . '/' . htmlspecialchars($opponentAvatar) : SITE_URL . '/assets/img/default-avatar.png'; ?>" class="rounded-circle shadow-sm" style="width: 48px; height: 48px; object-fit: cover;">
                                    <div>
                                        <h6 class="fw-bold mb-1 text-dark">vs <?php echo htmlspecialchars($opponentName ?? ''); ?></h6>
                                        <div class="small fw-bold text-muted"><?php echo htmlspecialchars($match['quiz_title']); ?></div>
                                        <div class="small text-muted mt-1"><i class="far fa-clock"></i> <?php echo !empty($match['completed_at']) ? date('M j, Y', strtotime($match['completed_at'])) : 'In progress'; ?></div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <h6 class="mb-1 fw-bold"><?php echo $resultIcon; ?></h6>
                                    <span class="badge bg-light text-dark border px-3 py-2 fs-6 mt-1 shadow-sm"><?php echo $userScore; ?> - <?php echo $oppScore; ?></span>
                                </div>
                            </div>
                            <?php
        endforeach; ?>
                        </div>
                    <?php
    endif; ?>
                </div>
            </div>
            <?php
endif; ?>

        </div>
        
    </div>
</div>

<?php if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] !== $target_user_id): ?>
<!-- Challenge Modal -->
<div class="modal fade" id="challengeModal" tabindex="-1" aria-labelledby="challengeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header text-white" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 1.5rem;">
        <h5 class="modal-title fw-bold" id="challengeModalLabel"><i class="fas fa-bolt text-warning me-2"></i> Challenge <?php echo htmlspecialchars($user->name); ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="<?php echo SITE_URL; ?>/process_challenge.php" method="POST">
        <div class="modal-body p-4 bg-light">
          <input type="hidden" name="opponent_id" value="<?php echo $target_user_id; ?>">
          
          <div class="mb-4">
            <label class="form-label fw-bold text-dark"><i class="fas fa-book-open text-primary me-2"></i>Select Quiz Subject</label>
            <select class="form-select border-0 shadow-sm" name="quiz_id" required style="border-radius: 8px; padding: 0.75rem;">
              <option value="" disabled selected>-- Choose a Quiz --</option>
              <?php foreach ($availableQuizzes as $q): ?>
                  <option value="<?php echo $q['id']; ?>"><?php echo htmlspecialchars($q['title']); ?></option>
              <?php
    endforeach; ?>
            </select>
          </div>
          
          <div class="mb-4">
            <label class="form-label fw-bold text-dark"><i class="fas fa-trophy text-warning me-2"></i>Match Type</label>
            <div class="d-flex gap-3">
                <div class="form-check bg-white p-3 rounded shadow-sm border flex-fill">
                  <input class="form-check-input ms-1 mt-1" type="radio" name="match_type" id="typeFriendly" value="friendly" checked>
                  <label class="form-check-label ms-2 fw-bold" for="typeFriendly">
                    Friendly <span class="d-block text-muted small fw-normal">No Rank changes</span>
                  </label>
                </div>
                <div class="form-check bg-white p-3 rounded shadow-sm border flex-fill">
                  <input class="form-check-input ms-1 mt-1" type="radio" name="match_type" id="typeRanked" value="ranked">
                  <label class="form-check-label ms-2 fw-bold text-danger" for="typeRanked">
                    Ranked <span class="d-block text-muted small fw-normal">Affects Global ELO</span>
                  </label>
                </div>
            </div>
          </div>
          
          <div class="mb-4">
            <label class="form-label fw-bold text-dark"><i class="fas fa-stopwatch text-primary me-2"></i>Custom Time Limit (Optional)</label>
            <select class="form-select border-0 shadow-sm" name="custom_timer_seconds" style="border-radius: 8px; padding: 0.75rem;">
              <option value="0" selected>Use Default Quiz Time</option>
              <option value="300">5 Minutes (Blitz)</option>
              <option value="600">10 Minutes (Rapid)</option>
              <option value="900">15 Minutes (Standard)</option>
              <option value="1800">30 Minutes (Deep Think)</option>
            </select>
          </div>
          
          <div class="mb-4">
            <label class="form-label fw-bold text-dark"><i class="fas fa-calendar-alt text-success me-2"></i>Scheduled Start (Optional)</label>
            <input type="datetime-local" name="start_time" class="form-control border-0 shadow-sm" style="border-radius: 8px; padding: 0.75rem;">
            <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i> Leave blank to allow battle to start immediately upon acceptance.</div>
          </div>

          <div class="mb-2">
            <label class="form-label fw-bold text-dark"><i class="fas fa-coins text-warning me-2"></i>XP Wager (Optional)</label>
            <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
              <span class="input-group-text bg-white border-0 text-warning"><i class="fas fa-star"></i></span>
              <input type="number" name="xp_wager" class="form-control border-0" min="0" max="<?php echo site_battle_wager_max(); ?>" step="10" placeholder="0" style="padding: 0.75rem;">
            </div>
            <div class="form-text mt-2 text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> Wager is deducted into escrow immediately. Winner takes the combined pot!</div>
          </div>
          
        </div>
        <div class="modal-footer bg-white border-0 p-3">
          <button type="button" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border: none;">Send Challenge <i class="fas fa-paper-plane ms-2"></i></button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php
endif; ?>

<?php if (isset($_GET['modal']) && $_GET['modal'] === 'insufficient_xp'): ?>
<!-- Insufficient XP Modal -->
<div class="modal fade" id="insufficientXpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-body p-5 text-center bg-white">
        <div class="mb-4">
            <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
        </div>
        <h3 class="fw-bold mb-3 text-dark">Insufficient XP</h3>
        <p class="text-muted mb-4 fs-5">You do not have enough XP to cover the wager for this battle. Your current balance is <strong class="text-primary"><?php echo number_format($loggedInUserXP); ?> XP</strong>.</p>
        <button type="button" class="btn btn-warning rounded-pill px-5 py-2 fw-bold text-dark shadow-sm" data-bs-dismiss="modal">Got It</button>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var myModal = new bootstrap.Modal(document.getElementById('insufficientXpModal'));
    myModal.show();
});
</script>
<?php
endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
