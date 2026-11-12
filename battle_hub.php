<?php
// battle_hub.php - Public Battle Hub: Discover & join community battles
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Battle.php';

$battleModel = new Battle($pdo);
$openBattles = $battleModel->getPublicOpenChallenges(30);

// If user is logged in, get their ID for "already battled" checks
$loggedInUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Get list of battles user has already accepted (to show "already played" badge)
$userAcceptedBattleIds = [];
if ($loggedInUserId > 0) {
    $stmt = $pdo->prepare("
        SELECT id FROM battles WHERE (challenger_id = ? OR opponent_id = ?) AND status != 'open'
        UNION
        SELECT parent_battle_id FROM battles WHERE opponent_id = ? AND parent_battle_id IS NOT NULL
    ");
    $stmt->execute([$loggedInUserId, $loggedInUserId, $loggedInUserId]);
    $userAcceptedBattleIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

$page_title = "Battle Hub - Community Battles";
include __DIR__ . '/includes/header.php';
?>

<style>
    .hub-hero {
        background: linear-gradient(135deg, #0f172a 0%, #312e81 50%, #0f172a 100%);
        padding: 3rem 2rem;
        color: white;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .hub-hero::before {
        content: '';
        position: absolute;
        top: -60%; left: -30%;
        width: 160%; height: 200%;
        background: radial-gradient(circle at 30% 50%, rgba(99,102,241,0.2) 0%, transparent 50%),
                    radial-gradient(circle at 70% 50%, rgba(245,158,11,0.15) 0%, transparent 50%);
        animation: hubGlow 10s ease-in-out infinite alternate;
    }
    @keyframes hubGlow {
        0% { transform: rotate(0deg) scale(1); }
        100% { transform: rotate(10deg) scale(1.05); }
    }
    .hub-hero > * { position: relative; z-index: 2; }

    .hub-battle-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        transition: all 0.3s ease;
    }
    .hub-battle-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.1);
        border-color: #c7d2fe;
    }
    .hub-card-header {
        background: linear-gradient(135deg, #f8fafc, #eef2ff);
        padding: 1.25rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .hub-card-body {
        padding: 1.25rem;
    }
    .hub-stat {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #94a3b8;
    }
    .hub-stat-val {
        font-weight: 800;
        font-size: 1rem;
        color: #1e293b;
    }

    .hub-empty {
        padding: 4rem 2rem;
        text-align: center;
        color: #94a3b8;
    }

    .hub-countdown {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        border: 1px solid #fbbf24;
        border-radius: 8px;
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: #92400e;
        text-align: center;
        margin-bottom: 0.75rem;
    }
    .hub-countdown.expired {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        border-color: #f87171;
        color: #991b1b;
    }

    @media (max-width: 768px) {
        .hub-hero { padding: 2rem 1.25rem; }
        .hub-hero h1 { font-size: 1.5rem; }
    }
</style>

<!-- Hero Section -->
<div class="hub-hero mb-4">
    <div class="d-flex align-items-center justify-content-center gap-3 mb-3">
        <i class="fas fa-globe-americas text-warning" style="font-size: 2.5rem;"></i>
        <div>
            <h1 class="fw-bold mb-0" style="letter-spacing: -0.5px;">Battle Hub</h1>
            <p class="mb-0 text-white-50">Discover open challenges from the community & prove your knowledge</p>
        </div>
    </div>
    <div class="d-flex justify-content-center gap-3 mt-3">
        <div class="px-3 py-2 rounded-pill" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1);">
            <i class="fas fa-fire text-warning me-1"></i>
            <span class="fw-bold"><?php echo count($openBattles); ?></span>
            <span class="text-white-50 small">Open Battles</span>
        </div>
        <?php if ($loggedInUserId > 0): ?>
        <a href="<?php echo SITE_URL; ?>/battle_arena.php" class="btn btn-sm btn-warning fw-bold rounded-pill px-4 shadow-sm">
            <i class="fas fa-plus me-1"></i> Create Your Own
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="container pb-5">

    <?php if (count($openBattles) > 0): ?>
    <div class="row g-4">
        <?php foreach ($openBattles as $battle):
            $isOwner = ($loggedInUserId === (int)$battle['challenger_id']);
            $alreadyPlayed = in_array((int)$battle['id'], $userAcceptedBattleIds);
            $rating = (int)($battle['challenger_rating'] ?? 1000);
            $wins = (int)($battle['challenger_wins'] ?? 0);
            $losses = (int)($battle['challenger_losses'] ?? 0);
            $totalGames = $wins + $losses;
            $winRate = $totalGames > 0 ? round(($wins / $totalGames) * 100) : 0;
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="hub-battle-card h-100 d-flex flex-column">
                <div class="hub-card-header">
                    <a href="<?php echo SITE_URL; ?>/profile_public.php?id=<?php echo htmlspecialchars($battle['challenger_id']); ?>" class="d-flex align-items-center gap-3 text-decoration-none" style="color: inherit;">
                        <img src="<?php echo !empty($battle['challenger_avatar']) ? SITE_URL . '/' . htmlspecialchars($battle['challenger_avatar']) : SITE_URL . '/assets/img/default-avatar.png'; ?>" class="rounded-circle shadow-sm" style="width: 48px; height: 48px; object-fit: cover; border: 2px solid #e2e8f0; transition: transform 0.2s;">
                        <div>
                            <h6 class="mb-0 fw-bold text-dark on-hover-emerald"><?php echo htmlspecialchars($battle['challenger_name']); ?></h6>
                            <div class="d-flex gap-2 mt-1">
                                <span class="badge bg-warning bg-opacity-10 text-warning" style="font-size: 0.7rem;"><i class="fas fa-fire me-1"></i><?php echo number_format($rating); ?> ELO</span>
                                <span class="badge bg-success bg-opacity-10 text-success" style="font-size: 0.7rem;"><?php echo $winRate; ?>% WR</span>
                            </div>
                        </div>
                    </a>
                </div>
                <style> .on-hover-emerald { transition: color 0.2s; } a:hover .on-hover-emerald { color: #10b981 !important; text-decoration: underline; } a:hover img { transform: scale(1.05); } </style>
                <div class="hub-card-body flex-grow-1 d-flex flex-column">
                    <h6 class="fw-bold text-primary mb-3"><i class="fas fa-book-open me-1"></i> <?php echo htmlspecialchars($battle['quiz_title']); ?></h6>
                    
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <?php if ($battle['match_type'] === 'ranked'): ?>
                            <span class="badge bg-danger"><i class="fas fa-fire me-1"></i> Ranked</span>
                        <?php else: ?>
                            <span class="badge bg-success"><i class="fas fa-heart me-1"></i> Friendly</span>
                        <?php endif; ?>
                        <?php if ((int)$battle['xp_wager'] > 0): ?>
                            <span class="badge bg-warning text-dark"><i class="fas fa-coins me-1"></i> <?php echo (int)$battle['xp_wager']; ?> XP</span>
                        <?php endif; ?>
                        <?php if (!empty($battle['custom_timer_seconds'])): ?>
                            <span class="badge bg-info text-dark"><i class="fas fa-stopwatch me-1"></i> <?php echo floor($battle['custom_timer_seconds'] / 60); ?>m</span>
                        <?php endif; ?>
                        <?php
                        $bMaxP = (int)($battle['max_participants'] ?? 1);
                        $bAccepted = (int)($battle['accepted_count'] ?? 0);
                        ?>
                        <span class="badge <?php echo $bAccepted >= $bMaxP ? 'bg-danger' : 'bg-primary'; ?>">
                            <i class="fas fa-users me-1"></i> <?php echo $bAccepted; ?>/<?php echo $bMaxP; ?>
                        </span>
                    </div>

                    <div class="d-flex justify-content-between mb-3 px-1">
                        <div class="text-center">
                            <div class="hub-stat">Battles</div>
                            <div class="hub-stat-val"><?php echo $totalGames; ?></div>
                        </div>
                        <div class="text-center">
                            <div class="hub-stat">Wins</div>
                            <div class="hub-stat-val text-success"><?php echo $wins; ?></div>
                        </div>
                        <div class="text-center">
                            <div class="hub-stat">Losses</div>
                            <div class="hub-stat-val text-danger"><?php echo $losses; ?></div>
                        </div>
                    </div>

                    <div class="text-muted small mb-2"><i class="far fa-clock me-1"></i> Created <?php echo date('M j, g:i A', strtotime($battle['created_at'])); ?></div>

                    <?php
                    $hasStartTime = !empty($battle['start_time']);
                    $hasExpiry = !empty($battle['expires_at']);
                    $startEpoch = $hasStartTime ? strtotime($battle['start_time']) : 0;
                    $expiryEpoch = $hasExpiry ? strtotime($battle['expires_at']) : 0;
                    $now = time();
                    $notStartedYet = $hasStartTime && $startEpoch > $now;
                    ?>
                    <?php if ($notStartedYet): ?>
                    <div class="hub-countdown">
                        <i class="fas fa-calendar-check me-1"></i> Starts: <?php echo date('M j, g:i A', $startEpoch); ?>
                        <div class="mt-1" style="font-size: 0.7rem; color: #b45309;">Battle opens for acceptance at this time</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($hasExpiry): ?>
                    <div class="hub-countdown" data-expires="<?php echo $expiryEpoch; ?>">
                        <i class="fas fa-hourglass-half me-1"></i> <span class="hub-timer">Expires: <?php echo date('M j, g:i A', $expiryEpoch); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="mt-auto">
                        <?php if (!$loggedInUserId): ?>
                            <a href="<?php echo SITE_URL; ?>/login.php?redirect=<?php echo urlencode('battle_invite.php?code=' . $battle['invite_code']); ?>" class="btn btn-primary w-100 fw-bold rounded-pill shadow-sm">
                                <i class="fas fa-sign-in-alt me-1"></i> Login to Accept
                            </a>
                        <?php elseif ($isOwner): ?>
                            <button class="btn btn-outline-primary w-100 fw-bold rounded-pill" onclick="copyHubLink('<?php echo SITE_URL . '/battle_invite.php?code=' . $battle['invite_code']; ?>', this)">
                                <i class="fas fa-share-alt me-1"></i> Share Link
                            </button>
                        <?php elseif ($alreadyPlayed): ?>
                            <button class="btn btn-outline-secondary w-100 fw-bold rounded-pill" disabled>
                                <i class="fas fa-check-circle me-1"></i> Already Accepted
                            </button>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/battle_invite.php?code=<?php echo htmlspecialchars($battle['invite_code']); ?>" class="btn w-100 fw-bold rounded-pill shadow-sm" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border: none;">
                                <i class="fas fa-khanda me-1"></i> View & Accept Challenge
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="hub-empty">
        <i class="fas fa-satellite-dish mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
        <h4 class="fw-bold text-dark mb-2">No Open Battles Right Now</h4>
        <p class="text-muted mb-4">Be the first to create a public battle and challenge the community!</p>
        <?php if ($loggedInUserId > 0): ?>
            <a href="<?php echo SITE_URL; ?>/battle_arena.php" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                <i class="fas fa-plus me-1"></i> Create a Battle
            </a>
        <?php else: ?>
            <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                <i class="fas fa-sign-in-alt me-1"></i> Login to Get Started
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<script>
function copyHubLink(link, btn) {
    navigator.clipboard.writeText(link).then(function() {
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check me-1"></i> Copied!';
        btn.style.background = '#10b981';
        btn.style.color = 'white';
        btn.style.borderColor = '#10b981';
        setTimeout(function() { btn.innerHTML = original; btn.style.background = ''; btn.style.color = ''; btn.style.borderColor = ''; }, 2000);
    });
}

// Live countdown timers
function updateHubTimers() {
    document.querySelectorAll('[data-expires]').forEach(function(el) {
        var exp = parseInt(el.dataset.expires) * 1000;
        var now = Date.now();
        var diff = exp - now;
        var span = el.querySelector('.hub-timer');
        if (diff <= 0) {
            span.textContent = 'Expired';
            el.classList.add('expired');
        } else {
            var h = Math.floor(diff / 3600000);
            var m = Math.floor((diff % 3600000) / 60000);
            var s = Math.floor((diff % 60000) / 1000);
            var parts = [];
            if (h > 0) parts.push(h + 'h');
            parts.push(m + 'm');
            parts.push(s + 's');
            span.textContent = 'Closes in ' + parts.join(' ');
        }
    });
}
setInterval(updateHubTimers, 1000);
updateHubTimers();
</script>

<?php $hide_main_footer = true;
include __DIR__ . '/includes/footer.php'; ?>
