<?php
// battle_invite.php - Public landing page for open battle invite links
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Battle.php';

$inviteCode = isset($_GET['code']) ? trim($_GET['code']) : '';

if (empty($inviteCode)) {
    header("Location: index.php");
    exit();
}

$battleModel = new Battle($pdo);
$battle = $battleModel->getOpenChallenge($inviteCode);

if (!$battle) {
    $page_title = "Battle Invite";
    include __DIR__ . '/includes/header.php';
    echo '<div class="container py-5 text-center">
        <i class="fas fa-unlink text-muted" style="font-size:5rem;"></i>
        <h2 class="fw-bold mt-4 text-dark">Invite Not Found</h2>
        <p class="text-muted fs-5">This battle invite link is invalid or has expired.</p>
        <a href="' . SITE_URL . '/index.php" class="btn btn-primary rounded-pill px-5 fw-bold mt-3">Go Home</a>
    </div>';
    include __DIR__ . '/includes/footer.php';
    exit();
}

$maxParticipants = (int)($battle['max_participants'] ?? 1);
$acceptedCount = (int)($battle['accepted_count'] ?? 0);
$isFull = $acceptedCount >= $maxParticipants;
$isOwner = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$battle['challenger_id'];
$isLoggedIn = isset($_SESSION['user_id']);
$startTimeLocked = !empty($battle['start_time']) && strtotime($battle['start_time']) > time();

$alreadyAccepted = false;
if ($isLoggedIn && !$isOwner) {
    $stmt = $pdo->prepare("SELECT id FROM battles WHERE parent_battle_id = ? AND opponent_id = ?");
    $stmt->execute([$battle['id'], $_SESSION['user_id']]);
    $alreadyAccepted = (bool)$stmt->fetchColumn();
}

$challengerAvatar = !empty($battle['challenger_avatar'])
    ? SITE_URL . '/' . htmlspecialchars($battle['challenger_avatar'])
    : SITE_URL . '/assets/img/default-avatar.png';

$userXP = 0;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userXP = (int)$stmt->fetchColumn();
}

// Fetch challenger battle stats
$challengerStats = $battleModel->getUserStats((int)$battle['challenger_id']);
$cTotalBattles = ($challengerStats['wins'] ?? 0) + ($challengerStats['losses'] ?? 0) + ($challengerStats['draws'] ?? 0);
$cWinRate = $cTotalBattles > 0 ? round(($challengerStats['wins'] / $cTotalBattles) * 100) : 0;

$page_title = "Battle Invite from " . htmlspecialchars($battle['challenger_name']);
include __DIR__ . '/includes/header.php';
?>

<style>
    .invite-page { max-width: 640px; margin: 0 auto; }
    .invite-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
        border-radius: 24px;
        padding: 3rem 2rem;
        text-align: center;
        color: white;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.1);
        box-shadow: 0 20px 50px rgba(0,0,0,0.4);
    }
    .invite-hero::before {
        content: '';
        position: absolute;
        top: -50%; left: -50%;
        width: 200%; height: 200%;
        background: radial-gradient(circle at 40% 40%, rgba(99,102,241,0.2) 0%, transparent 50%);
    }
    .invite-hero > * { position: relative; z-index: 2; }
    .challenger-avatar {
        width: 100px; height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid rgba(255,255,255,0.2);
        box-shadow: 0 8px 25px rgba(0,0,0,0.4);
    }
    .detail-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.5rem;
    }
    .detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { font-weight: 700; color: #64748b; font-size: 0.9rem; }
    .detail-value { font-weight: 800; color: #0f172a; font-size: 1rem; }
    .share-box {
        background: linear-gradient(135deg, #eff6ff, #e0e7ff);
        border: 2px dashed #6366f1;
        border-radius: 12px;
        padding: 1.25rem;
        text-align: center;
    }
    .share-link {
        font-family: monospace;
        font-size: 0.85rem;
        color: #4338ca;
        word-break: break-all;
        background: rgba(255,255,255,0.7);
        padding: 0.5rem 1rem;
        border-radius: 8px;
        display: block;
        margin: 0.75rem 0;
    }
    @media (max-width: 576px) {
        .invite-hero { padding: 2rem 1.25rem; border-radius: 18px; }
        .challenger-avatar { width: 80px; height: 80px; }
    }
</style>

<div class="container py-5">
    <div class="invite-page">

        <div class="invite-hero mb-4">
            <img src="<?php echo $challengerAvatar; ?>" class="challenger-avatar mb-3" alt="Challenger">
            <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($battle['challenger_name']); ?></h2>
            <p class="text-white-50 mb-3">challenges you to a battle!</p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <span class="badge bg-dark bg-opacity-50 px-3 py-2"><i class="fas fa-fire text-warning me-1"></i> ELO <?php echo number_format($challengerStats['rating'] ?? 1000); ?></span>
                <span class="badge bg-dark bg-opacity-50 px-3 py-2"><i class="fas fa-trophy text-success me-1"></i> <?php echo $cWinRate; ?>% Win</span>
                <span class="badge bg-dark bg-opacity-50 px-3 py-2"><i class="fas fa-gamepad me-1"></i> <?php echo $cTotalBattles; ?> Battles</span>
            </div>
        </div>

        <div class="detail-card mb-4">
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-book-open text-primary me-2"></i> Quiz</span>
                <span class="detail-value"><?php echo htmlspecialchars($battle['quiz_title']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-trophy text-warning me-2"></i> Match Type</span>
                <span class="detail-value"><?php echo $battle['match_type'] === 'ranked' ? '<span class="text-danger">⚡ Ranked</span>' : '🤝 Friendly'; ?></span>
            </div>
            <?php if ((int)$battle['xp_wager'] > 0): ?>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-coins text-warning me-2"></i> XP Wager</span>
                <span class="detail-value text-warning"><?php echo (int)$battle['xp_wager']; ?> XP</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($battle['custom_timer_seconds'])): ?>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-stopwatch text-info me-2"></i> Time Limit</span>
                <span class="detail-value"><?php echo floor($battle['custom_timer_seconds'] / 60); ?> minutes</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($battle['start_time'])): ?>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-calendar-alt text-success me-2"></i> Scheduled</span>
                <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($battle['start_time'])); ?></span>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <span class="detail-label"><i class="fas fa-users text-primary me-2"></i> Slots</span>
                <span class="detail-value">
                    <span class="<?php echo $isFull ? 'text-danger' : 'text-success'; ?>"><?php echo $acceptedCount; ?> / <?php echo $maxParticipants; ?></span>
                    <span class="text-muted" style="font-size: 0.8rem;">accepted</span>
                </span>
            </div>
        </div>

        <?php if ($isFull): ?>
            <div class="alert alert-info text-center border-0 shadow-sm rounded-3 p-4">
                <i class="fas fa-user-check fs-1 mb-2 d-block"></i>
                <h5 class="fw-bold">All Slots Filled</h5>
                <p class="mb-0 text-muted">This battle has reached its maximum of <strong><?php echo $maxParticipants; ?></strong> participant<?php echo $maxParticipants > 1 ? 's' : ''; ?>.</p>
            </div>

        <?php elseif ($alreadyAccepted): ?>
            <div class="alert alert-success text-center border-0 shadow-sm rounded-3 p-4 bg-success bg-opacity-10 border border-success">
                <i class="fas fa-check-double fs-1 mb-2 d-block text-success"></i>
                <h5 class="fw-bold text-success">Already Accepted</h5>
                <p class="mb-0 text-muted">You have already accepted this battle. Check your Battle Arena to play your turn!</p>
                <a href="<?php echo SITE_URL; ?>/battle_arena.php" class="btn btn-success rounded-pill px-4 fw-bold mt-3 shadow-sm">Go to Arena</a>
            </div>

        <?php elseif ($isOwner): ?>
            <div class="share-box">
                <h5 class="fw-bold text-dark mb-2"><i class="fas fa-share-alt text-primary me-2"></i>Share This Invite</h5>
                <p class="text-muted small mb-1">Send this link to anyone you want to challenge:</p>
                <p class="text-muted small mb-2"><strong><?php echo $acceptedCount; ?> / <?php echo $maxParticipants; ?></strong> slots filled</p>
                <div class="share-link"><?php echo SITE_URL . '/battle_invite.php?code=' . htmlspecialchars($inviteCode); ?></div>
                <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="navigator.clipboard.writeText('<?php echo SITE_URL . '/battle_invite.php?code=' . $inviteCode; ?>'); this.innerHTML='<i class=\'fas fa-check me-1\'></i> Copied!'; this.style.background='#10b981'; setTimeout(()=>{this.innerHTML='<i class=\'fas fa-copy me-1\'></i> Copy Link'; this.style.background='';}, 2000);">
                    <i class="fas fa-copy me-1"></i> Copy Link
                </button>
            </div>

        <?php elseif (!$isLoggedIn): ?>
            <div class="text-center">
                <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow w-100" style="background: linear-gradient(135deg, #3b82f6, #2563eb); border: none;">
                    <i class="fas fa-sign-in-alt me-2"></i>Login to Accept Challenge
                </a>
                <p class="text-muted small mt-3">You need to be logged in to accept this battle invite.</p>
            </div>

        <?php elseif ($startTimeLocked): ?>
            <div class="text-center">
                <button class="btn btn-secondary btn-lg rounded-pill w-100 fw-bold shadow" disabled style="padding: 1rem;">
                    <i class="fas fa-lock me-2"></i>Starts <?php echo date('M j, g:i A', strtotime($battle['start_time'])); ?>
                </button>
                <p class="text-muted small mt-3"><i class="fas fa-clock me-1"></i> This battle is scheduled. You can accept once it goes live.</p>
            </div>

        <?php else: ?>
            <!-- Accept or Propose Changes -->
            <div class="d-grid gap-3">
                <form method="POST" action="<?php echo SITE_URL; ?>/process_open_challenge.php">
                    <input type="hidden" name="battle_id" value="<?php echo $battle['id']; ?>">
                    <input type="hidden" name="invite_code" value="<?php echo htmlspecialchars($inviteCode); ?>">
                    <input type="hidden" name="action" value="accept">
                    <button type="submit" class="btn btn-success btn-lg rounded-pill w-100 fw-bold shadow" style="background: linear-gradient(135deg, #10b981, #059669); border: none; padding: 1rem;">
                        <i class="fas fa-bolt me-2"></i>Accept Challenge
                    </button>
                </form>

                <button type="button" class="btn btn-outline-primary btn-lg rounded-pill w-100 fw-bold" data-bs-toggle="collapse" data-bs-target="#proposeChanges">
                    <i class="fas fa-handshake me-2"></i>Propose Different Terms
                </button>

                <div class="collapse" id="proposeChanges">
                    <div class="detail-card mt-2">
                        <form method="POST" action="<?php echo SITE_URL; ?>/process_open_challenge.php">
                            <input type="hidden" name="battle_id" value="<?php echo $battle['id']; ?>">
                            <input type="hidden" name="invite_code" value="<?php echo htmlspecialchars($inviteCode); ?>">
                            <input type="hidden" name="action" value="negotiate">
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Proposed Timer</label>
                                <select class="form-select form-select-sm shadow-sm" name="custom_timer_seconds">
                                    <option value="0">Default</option>
                                    <option value="300">5 Min</option>
                                    <option value="600">10 Min</option>
                                    <option value="900">15 Min</option>
                                    <option value="1800">30 Min</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Proposed XP Wager</label>
                                <input type="number" name="xp_wager" class="form-control form-control-sm shadow-sm" min="0" max="<?php echo site_battle_wager_max(); ?>" step="10" value="<?php echo (int)$battle['xp_wager']; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm">
                                <i class="fas fa-paper-plane me-2"></i>Send Proposal
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ((int)$battle['xp_wager'] > 0): ?>
                <div class="alert alert-warning border-0 shadow-sm rounded-3 mt-2 mb-0">
                    <small><i class="fas fa-info-circle me-1"></i> Accepting will deduct <strong><?php echo (int)$battle['xp_wager']; ?> XP</strong> from your balance into escrow. Your current XP: <strong class="text-primary"><?php echo number_format($userXP); ?></strong></small>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php if (isset($_GET['error']) && !empty($_GET['error'])): ?>
<!-- Error Modal (e.g. Insufficient XP) -->
<div class="modal fade" id="inviteErrorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-body p-5 text-center bg-white">
        <div class="mb-4"><i class="fas fa-exclamation-triangle text-danger" style="font-size: 4rem;"></i></div>
        <h3 class="fw-bold mb-3 text-dark">Action Failed</h3>
        <p class="text-muted mb-4 fs-5"><?php echo htmlspecialchars($_GET['error']); ?></p>
        <?php if ($isLoggedIn): ?>
        <p class="text-muted small">Your current XP: <strong class="text-primary"><?php echo number_format($userXP); ?> XP</strong></p>
        <?php endif; ?>
        <button type="button" class="btn btn-danger rounded-pill px-5 py-2 fw-bold shadow-sm" data-bs-dismiss="modal">Got It</button>
      </div>
    </div>
  </div>
</div>
<script>document.addEventListener("DOMContentLoaded",function(){new bootstrap.Modal(document.getElementById('inviteErrorModal')).show();});</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
