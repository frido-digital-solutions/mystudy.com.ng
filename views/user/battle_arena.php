<?php
// views/user/battle_arena.php - Premium Standalone Battle Arena View
include __DIR__ . '/../../includes/header.php';
?>

<style>
    .arena-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
        border-radius: 20px;
        padding: 2.5rem 2rem;
        color: white;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.08);
        box-shadow: 0 15px 40px rgba(0,0,0,0.3);
    }
    .arena-hero::before {
        content: '';
        position: absolute;
        top: -50%; left: -50%;
        width: 200%; height: 200%;
        background: radial-gradient(circle at 30% 50%, rgba(99,102,241,0.15) 0%, transparent 50%),
                    radial-gradient(circle at 70% 50%, rgba(245,158,11,0.1) 0%, transparent 50%);
        animation: heroGlow 8s ease-in-out infinite alternate;
    }
    @keyframes heroGlow {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(15deg); }
    }
    .arena-hero > * { position: relative; z-index: 2; }

    .stat-pill {
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.12);
        border-radius: 16px;
        padding: 1.25rem 1.5rem;
        text-align: center;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
    }
    .stat-pill:hover { background: rgba(255,255,255,0.12); transform: translateY(-3px); }
    .stat-pill .stat-num { font-size: 2rem; font-weight: 900; line-height: 1; }
    .stat-pill .stat-lbl { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-top: 0.5rem; }

    .section-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
    }
    .section-header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .section-header h5 { margin: 0; font-weight: 800; font-size: 1.1rem; color: #0f172a; }

    .battle-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #f8fafc;
        transition: background 0.2s;
    }
    .battle-row:last-child { border-bottom: none; }
    .battle-row:hover { background: #f8fafc; }

    .invite-link-box {
        background: linear-gradient(135deg, #eff6ff, #e0e7ff);
        border: 2px dashed #6366f1;
        border-radius: 12px;
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.75rem;
    }
    .invite-link-box .link-text {
        font-family: monospace;
        font-size: 0.85rem;
        color: #4338ca;
        word-break: break-all;
        flex: 1;
    }
    .btn-copy {
        background: #6366f1;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-weight: 700;
        font-size: 0.8rem;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s;
    }
    .btn-copy:hover { background: #4f46e5; transform: scale(1.05); }

    @media (max-width: 768px) {
        .arena-hero { padding: 1.75rem 1.25rem; border-radius: 14px; }
        .stat-pill .stat-num { font-size: 1.5rem; }
        .stat-pill { padding: 1rem; }
        .battle-row { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
        .section-header { flex-wrap: wrap; gap: 0.5rem; }
        .section-header .d-flex.flex-fill { width: 100% !important; margin-right: 0 !important; }
        .section-header .d-flex.flex-shrink-0 { width: 100%; justify-content: flex-end; }
        .arena-tab { font-size: 0.8rem !important; padding: 7px 0 !important; }
    }
    .on-hover-emerald { transition: color 0.2s; } 
    a:hover .on-hover-emerald { color: #10b981 !important; text-decoration: underline; } 
    a:hover img.rounded-circle { transform: scale(1.05); }
</style>

<div class="container py-4">

    <!-- Result of the last action is shown by the global toast in the footer -->

    <!-- Hero Banner -->
    <div class="arena-hero mb-4">
        <div class="d-flex align-items-center gap-3 mb-4">
            <i class="fas fa-khanda text-warning" style="font-size: 2.5rem;"></i>
            <div>
                <h2 class="fw-bold mb-0" style="letter-spacing: -0.5px;">Battle Arena</h2>
                <p class="mb-0 text-white-50">Your command center for all PvP battles</p>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-6 col-md-2">
                <div class="stat-pill">
                    <div class="stat-num text-warning"><i class="fas fa-fire me-1" style="font-size:1.25rem;"></i><?php echo number_format($battleStats['rating']); ?></div>
                    <div class="stat-lbl">ELO Rating</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-pill">
                    <div class="stat-num text-success"><?php echo $winRate; ?>%</div>
                    <div class="stat-lbl">Win Rate</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="stat-pill">
                    <div class="stat-num text-info"><?php echo (int)$battleStats['wins']; ?></div>
                    <div class="stat-lbl">Wins</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="stat-pill">
                    <div class="stat-num text-danger"><?php echo (int)$battleStats['losses']; ?></div>
                    <div class="stat-lbl">Losses</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="stat-pill">
                    <div class="stat-num text-secondary"><?php echo (int)$battleStats['draws']; ?></div>
                    <div class="stat-lbl">Draws</div>
                </div>
            </div>
            <div class="col-12 col-md-2">
                <div class="stat-pill">
                    <div class="stat-num text-primary"><?php echo (int)$battleStats['current_streak']; ?> <i class="fas fa-burn" style="font-size:1rem;"></i></div>
                    <div class="stat-lbl">Streak</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Incoming Challenges -->
    <?php if (count($pendingChallenges) > 0): ?>
    <div class="section-card mb-4">
        <div class="section-header">
            <h5><i class="fas fa-exclamation-circle text-danger me-2"></i>Incoming Challenges</h5>
            <span class="badge bg-danger rounded-pill shadow-sm"><?php echo count($pendingChallenges); ?></span>
        </div>
        <div>
            <?php foreach ($pendingChallenges as $pending): ?>
            <div class="battle-row" id="battle-<?php echo $pending['id']; ?>">
                <a href="<?php echo SITE_URL; ?>/profile_public.php?id=<?php echo htmlspecialchars($pending['challenger_id']); ?>" class="d-flex align-items-center gap-3 text-decoration-none" style="color: inherit;">
                    <img src="<?php echo !empty($pending['challenger_avatar']) ? SITE_URL . '/' . htmlspecialchars($pending['challenger_avatar']) : SITE_URL . '/assets/img/default-avatar.png'; ?>" class="rounded-circle shadow-sm" style="width: 45px; height: 45px; object-fit: cover; transition: transform 0.2s;">
                    <div>
                        <h6 class="mb-0 fw-bold on-hover-emerald"><?php echo htmlspecialchars($pending['challenger_name']); ?></h6>
                        <small class="text-muted"><i class="fas fa-book-open text-primary me-1"></i> <?php echo htmlspecialchars($pending['quiz_title']); ?></small>
                        <div class="mt-1 d-flex gap-1">
                            <?php if ($pending['match_type'] === 'ranked'): ?>
                                <span class="badge bg-danger"><i class="fas fa-fire me-1"></i>Ranked</span>
                            <?php endif; ?>
                            <?php if ($pending['xp_wager'] > 0): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-star text-white me-1"></i><?php echo (int)$pending['xp_wager']; ?> XP</span>
                            <?php endif; ?>
                            <?php if (!empty($pending['custom_timer_seconds'])): ?>
                                <span class="badge bg-info text-dark"><i class="fas fa-stopwatch me-1"></i><?php echo floor($pending['custom_timer_seconds'] / 60); ?>m</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php
                $pendingStartLocked = !empty($pending['start_time']) && strtotime($pending['start_time']) > time();
                ?>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <?php if ($pendingStartLocked): ?>
                        <span class="badge bg-secondary rounded-pill px-3 py-2" style="font-size: 0.75rem;"><i class="fas fa-lock me-1"></i>Starts <?php echo date('M j, g:i A', strtotime($pending['start_time'])); ?></span>
                    <?php else: ?>
                    <form method="POST" action="<?php echo SITE_URL; ?>/process_battle_action.php" class="m-0 p-0">
                        <input type="hidden" name="battle_id" value="<?php echo $pending['id']; ?>">
                        <button type="submit" name="action" value="accept" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm" style="background: linear-gradient(135deg, #3b82f6, #2563eb); border: none;">Accept <i class="fas fa-check ms-1"></i></button>
                    </form>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#declineModal<?php echo $pending['id']; ?>">Review</button>
                </div>
            </div>

            <!-- Decline / Negotiate Modal -->
            <div class="modal fade" id="declineModal<?php echo $pending['id']; ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
                  <div class="modal-header bg-light" style="padding: 1.25rem;">
                    <h6 class="modal-title fw-bold text-dark"><i class="fas fa-hand-paper text-danger me-2"></i> Decline or Negotiate</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body p-4">
                    <form method="POST" action="<?php echo SITE_URL; ?>/process_battle_action.php" class="mb-4 pb-3 border-bottom">
                        <input type="hidden" name="battle_id" value="<?php echo $pending['id']; ?>">
                        <input type="hidden" name="action" value="decline">
                        <label class="form-label fw-bold text-danger">Why are you declining?</label>
                        <select class="form-select mb-3 shadow-sm" name="decline_reason" required>
                            <option value="" disabled selected>-- Select a Reason --</option>
                            <option value="Not comfortable with the scheduled time.">Not comfortable with the scheduled time.</option>
                            <option value="The XP wager is too high.">The XP wager is too high.</option>
                            <option value="I don't play Ranked matches.">I don't play Ranked matches.</option>
                            <option value="Not interested right now.">Not interested right now.</option>
                            <option value="Other">Other</option>
                        </select>
                        <button type="submit" class="btn btn-danger w-100 fw-bold rounded-pill shadow-sm">Decline Challenge</button>
                    </form>
                    <form method="POST" action="<?php echo SITE_URL; ?>/process_battle_action.php">
                        <input type="hidden" name="battle_id" value="<?php echo $pending['id']; ?>">
                        <input type="hidden" name="action" value="negotiate">
                        <label class="form-label fw-bold text-primary"><i class="fas fa-handshake me-1"></i> Propose New Terms</label>
                        <div class="mb-3">
                            <label class="small fw-bold">New Timer</label>
                            <select class="form-select form-select-sm shadow-sm" name="custom_timer_seconds">
                                <option value="0" <?php echo empty($pending['custom_timer_seconds']) ? 'selected' : ''; ?>>Default</option>
                                <option value="300" <?php echo ($pending['custom_timer_seconds'] ?? 0) == 300 ? 'selected' : ''; ?>>5 Min</option>
                                <option value="600" <?php echo ($pending['custom_timer_seconds'] ?? 0) == 600 ? 'selected' : ''; ?>>10 Min</option>
                                <option value="1800" <?php echo ($pending['custom_timer_seconds'] ?? 0) == 1800 ? 'selected' : ''; ?>>30 Min</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold">New XP Wager</label>
                            <input type="number" name="xp_wager" class="form-control form-control-sm shadow-sm" min="0" max="<?php echo site_battle_wager_max(); ?>" step="10" value="<?php echo (int)$pending['xp_wager']; ?>">
                        </div>
                        <button type="submit" class="btn btn-outline-primary w-100 fw-bold rounded-pill">Send Counter-Offer</button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabbed Section: All Battles / Open Invites -->
    <div class="section-card mb-4">
        <div class="section-header">
            <div class="d-flex align-items-center flex-fill" style="background: #f1f5f9; border-radius: 10px; padding: 3px; margin-right: 12px;">
                <button class="arena-tab active flex-fill" data-tab="allBattles" style="padding: 8px 0; border-radius: 8px; border: none; font-size: 0.85rem; font-weight: 700; cursor: pointer; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; transition: all 0.25s; text-align: center;">
                    All Battles
                </button>
                <button class="arena-tab flex-fill" data-tab="openInvites" style="padding: 8px 0; border-radius: 8px; border: none; font-size: 0.85rem; font-weight: 700; cursor: pointer; background: transparent; color: #64748b; transition: all 0.25s; text-align: center;">
                    Open Invites<?php if (count($openChallenges) > 0): ?> <span class="badge bg-danger rounded-pill" style="font-size: 0.6rem;"><?php echo count($openChallenges); ?></span><?php endif; ?>
                </button>
            </div>
            <div class="d-flex gap-2 flex-shrink-0">
                <a href="<?php echo SITE_URL; ?>/battle_hub.php" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-bold" style="font-size: 0.75rem;">
                    <i class="fas fa-globe me-1"></i>Hub
                </a>
                <button class="btn btn-sm fw-bold rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#createBattleModal" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border: none; font-size: 0.75rem; white-space: nowrap;">
                    <i class="fas fa-plus me-1"></i>Create
                </button>
            </div>
        </div>

        <!-- Tab: All Battles -->
        <div id="tab-allBattles" class="arena-tab-panel">
            <?php if (count($recentBattles) > 0): ?>
                <?php foreach ($recentBattles as $rb):
                    $isChallenger = ((int)$rb['challenger_id'] === $userId);
                    $opponentName = $isChallenger ? $rb['opponent_name'] : $rb['challenger_name'];
                    $opponentAvatar = $isChallenger ? $rb['opponent_avatar'] : $rb['challenger_avatar'];
                    $hasPlayed = $isChallenger ? ($rb['challenger_score'] !== null) : ($rb['opponent_score'] !== null);

                    $statusBadge = '<span class="badge bg-secondary rounded-pill">Unknown</span>';
                    if ($rb['status'] === 'pending') $statusBadge = '<span class="badge bg-warning text-dark rounded-pill">Pending</span>';
                    else if ($rb['status'] === 'open') $statusBadge = '<span class="badge rounded-pill" style="background:#6366f1; color:white;">Open Invite</span>';
                    else if ($rb['status'] === 'negotiating') $statusBadge = '<span class="badge bg-warning text-dark rounded-pill"><i class="fas fa-handshake me-1"></i> Negotiating</span>';
                    else if ($rb['status'] === 'declined') {
                        $statusBadge = '<span class="badge bg-secondary rounded-pill">';
                        $statusBadge .= !empty($rb['decline_reason']) ? 'Declined: ' . htmlspecialchars(substr($rb['decline_reason'], 0, 30)) : 'Declined';
                        $statusBadge .= '</span>';
                    }
                    else if ($rb['status'] === 'completed') {
                        if (empty($rb['opponent_id'])) {
                            $statusBadge = '<span class="badge bg-secondary rounded-pill"><i class="fas fa-door-closed me-1"></i> Closed Invite</span>';
                        } else {
                            if ((int)$rb['winner_id'] === $userId) $statusBadge = '<span class="badge bg-success rounded-pill px-3 shadow-sm"><i class="fas fa-trophy text-warning me-1"></i> Victory</span>';
                            else if (empty($rb['winner_id'])) $statusBadge = '<span class="badge bg-info rounded-pill">Draw</span>';
                            else $statusBadge = '<span class="badge bg-danger rounded-pill px-3 shadow-sm"><i class="fas fa-times-circle me-1"></i> Defeat</span>';
                        }
                    } else {
                        if ($hasPlayed) $statusBadge = '<span class="badge bg-info rounded-pill px-3 shadow-sm"><i class="fas fa-hourglass-half me-1"></i> Waiting</span>';
                        else $statusBadge = '<span class="badge bg-primary rounded-pill px-3 shadow-sm">Your Turn</span>';
                    }

                    $xpOutcome = '';
                    if ($rb['status'] === 'completed' && (int)$rb['xp_wager'] > 0 && !empty($rb['opponent_id'])) {
                        $wager = (int)$rb['xp_wager'];
                        if ((int)$rb['winner_id'] === $userId) $xpOutcome = '<span class="text-success fw-bold small"><i class="fas fa-arrow-up me-1"></i>+' . ($wager * 2) . ' XP</span>';
                        else if (empty($rb['winner_id'])) $xpOutcome = '<span class="text-muted fw-bold small"><i class="fas fa-undo me-1"></i>' . $wager . ' XP returned</span>';
                        else $xpOutcome = '<span class="text-danger fw-bold small"><i class="fas fa-arrow-down me-1"></i>-' . $wager . ' XP</span>';
                    }
                ?>
                <div class="battle-row" id="battle-<?php echo $rb['id']; ?>">
                    <div class="d-flex align-items-center gap-3">
                        <?php if (!empty($rb['opponent_id'])): 
                            $opponentId = $isChallenger ? $rb['opponent_id'] : $rb['challenger_id'];
                        ?>
                        <a href="<?php echo SITE_URL; ?>/profile_public.php?id=<?php echo htmlspecialchars($opponentId); ?>" class="d-flex align-items-center gap-3 text-decoration-none" style="color: inherit;">
                            <img src="<?php echo !empty($opponentAvatar) ? SITE_URL . '/' . htmlspecialchars($opponentAvatar) : SITE_URL . '/assets/img/default-avatar.png'; ?>" class="rounded-circle shadow-sm" style="width: 40px; height: 40px; object-fit: cover; transition: transform 0.2s;">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark on-hover-emerald">vs <?php echo htmlspecialchars($opponentName ?? 'Unknown'); ?></h6>
                        <?php else: ?>
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; font-size: 1rem;"><i class="fas fa-link"></i></div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark">Open Invite</h6>
                        <?php endif; ?>
                                <small class="text-muted"><?php echo htmlspecialchars($rb['quiz_title']); ?></small>
                                <?php if ($xpOutcome): ?><div class="mt-1"><?php echo $xpOutcome; ?></div><?php endif; ?>
                            </div>
                        <?php echo (!empty($rb['opponent_id'])) ? '</a>' : '</div>'; ?>
                    </div>
                    <div class="text-end">
                        <div class="mb-1"><?php echo $statusBadge; ?></div>
                        <?php if ($rb['status'] === 'p1_turn' || $rb['status'] === 'p2_turn'): ?>
                            <?php if ($hasPlayed): ?>
                                <a href="<?php echo SITE_URL; ?>/quiz_battle_result.php?id=<?php echo $rb['id']; ?>" class="btn btn-sm btn-outline-info rounded-pill mt-1" style="font-size: 0.75rem;">View Status</a>
                            <?php else: ?>
                                <a href="<?php echo SITE_URL; ?>/quiz_battle.php?id=<?php echo $rb['id']; ?>" class="btn btn-sm btn-primary rounded-pill mt-1 shadow-sm" style="font-size: 0.75rem;"><i class="fas fa-play me-1"></i> Enter Arena</a>
                            <?php endif; ?>
                        <?php elseif ($rb['status'] === 'completed' && !empty($rb['opponent_id'])): ?>
                            <a href="<?php echo SITE_URL; ?>/quiz_battle_result.php?id=<?php echo $rb['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill mt-1" style="font-size: 0.75rem;">View Result</a>
                        <?php elseif ($rb['status'] === 'negotiating' && $isChallenger):
                            $proposed = json_decode($rb['proposed_changes'], true) ?: [];
                        ?>
                            <div class="mt-2 text-start small border bg-light p-2 rounded shadow-sm">
                                <strong class="text-primary"><i class="fas fa-sync-alt me-1"></i> Counter-Offer:</strong><br>
                                <span class="text-muted">Timer:</span> <?php echo !empty($proposed['custom_timer_seconds']) ? floor($proposed['custom_timer_seconds']/60).' mins' : 'Default'; ?><br>
                                <span class="text-muted">Wager:</span> <?php echo isset($proposed['xp_wager']) ? (int)$proposed['xp_wager'].' XP' : '0 XP'; ?><br>
                                <form method="POST" action="<?php echo SITE_URL; ?>/process_battle_action.php" class="mt-2 text-end">
                                    <input type="hidden" name="battle_id" value="<?php echo $rb['id']; ?>">
                                    <button type="submit" name="action" value="accept_negotiation" class="btn btn-success btn-sm rounded-pill px-2 shadow-sm" style="font-size: 0.75rem;"><i class="fas fa-check"></i> Accept</button>
                                    <button type="submit" name="action" value="decline_negotiation" class="btn btn-outline-danger btn-sm rounded-pill px-2 shadow-sm ms-1" style="font-size: 0.75rem;"><i class="fas fa-times"></i> Deny</button>
                                </form>
                            </div>
                        <?php elseif ($rb['status'] === 'open' && !empty($rb['invite_code'])): ?>
                            <button class="btn btn-sm btn-outline-primary rounded-pill mt-1" style="font-size: 0.75rem;" onclick="copyInviteLink('<?php echo SITE_URL . '/battle_invite.php?code=' . $rb['invite_code']; ?>')"><i class="fas fa-copy me-1"></i> Copy Link</button>
                        <?php endif; ?>
                        <div class="text-muted mt-1" style="font-size: 0.7rem;"><i class="far fa-clock me-1"></i><?php echo date('M j, g:i A', strtotime($rb['created_at'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center p-5 text-muted">
                    <i class="fas fa-ghost fs-1 mb-3 opacity-25"></i>
                    <p class="mb-0">No battles yet. Challenge someone or click <strong>"+ Create"</strong> to make an open invite!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Open Invites -->
        <div id="tab-openInvites" class="arena-tab-panel" style="display: none;">
            <?php if (count($openChallenges) > 0): ?>
                <div class="p-3">
                    <?php foreach ($openChallenges as $oc): ?>
                    <div class="invite-link-box">
                        <div>
                            <div class="fw-bold small text-dark"><?php echo htmlspecialchars($oc['quiz_title']); ?></div>
                            <div class="link-text mt-1"><?php echo SITE_URL . '/battle_invite.php?code=' . htmlspecialchars($oc['invite_code']); ?></div>
                            <div class="mt-1 d-flex gap-1 flex-wrap">
                                <?php if ($oc['xp_wager'] > 0): ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-star me-1"></i> <?php echo (int)$oc['xp_wager']; ?> XP</span>
                                <?php endif; ?>
                                <?php if (!empty($oc['is_public'])): ?>
                                    <span class="badge bg-success"><i class="fas fa-globe me-1"></i> On Hub</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="fas fa-lock me-1"></i> Private</span>
                                <?php endif; ?>
                                <?php if (!empty($oc['start_time'])): ?>
                                    <span class="badge bg-primary"><i class="fas fa-calendar me-1"></i> Starts <?php echo date('M j, g:i A', strtotime($oc['start_time'])); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($oc['expires_at'])): ?>
                                    <span class="badge bg-danger"><i class="fas fa-hourglass-end me-1"></i> Ends <?php echo date('M j, g:i A', strtotime($oc['expires_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button class="btn-copy" onclick="copyInviteLink('<?php echo SITE_URL . '/battle_invite.php?code=' . $oc['invite_code']; ?>')">
                            <i class="fas fa-copy me-1"></i> Copy
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center p-5 text-muted">
                    <i class="fas fa-link fs-1 mb-3 opacity-25"></i>
                    <p class="mb-0">No open invites yet. Click <strong>"+ Create"</strong> to make one!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Create Open Battle Modal -->
<div class="modal fade" id="createBattleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header border-0 pb-0" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 1.5rem 1.5rem 1.25rem;">
        <h5 class="modal-title fw-bold text-white"><i class="fas fa-link me-2"></i> Create Open Battle</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="p-4">
        <p class="text-muted small mb-3">Create a battle and share the invite link. Anyone can accept!</p>
        <form method="POST" action="<?php echo SITE_URL; ?>/battle_arena.php">
            <input type="hidden" name="create_open_battle" value="1">
            <div class="mb-3">
                <label class="form-label fw-bold small"><i class="fas fa-book-open text-primary me-1"></i> Quiz Subject</label>
                <select class="form-select form-select-sm shadow-sm" name="quiz_id" required>
                    <option value="" disabled selected>-- Choose --</option>
                    <?php foreach ($availableQuizzes as $q): ?>
                        <option value="<?php echo $q['id']; ?>"><?php echo htmlspecialchars($q['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold small"><i class="fas fa-trophy text-warning me-1"></i> Match Type</label>
                <div class="d-flex gap-2">
                    <label class="flex-fill p-2 rounded border text-center small fw-bold" for="modalFriendly" style="cursor:pointer; background: #f0fdf4; border-color: #bbf7d0 !important;">
                        <input class="form-check-input me-1" type="radio" name="match_type" id="modalFriendly" value="friendly" checked>
                        <i class="fas fa-heart text-success me-1"></i> Friendly
                        <div class="text-muted fw-normal mt-1" style="font-size: 0.7rem;">Just for fun. No rank changes.</div>
                    </label>
                    <label class="flex-fill p-2 rounded border text-center small fw-bold" for="modalRanked" style="cursor:pointer; background: #fef2f2; border-color: #fecaca !important;">
                        <input class="form-check-input me-1" type="radio" name="match_type" id="modalRanked" value="ranked">
                        <i class="fas fa-fire text-danger me-1"></i> Ranked
                        <div class="text-muted fw-normal mt-1" style="font-size: 0.7rem;">Win to gain ELO. Lose &amp; some points are deducted.</div>
                    </label>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label fw-bold small"><i class="fas fa-stopwatch text-primary me-1"></i> Quiz Timer</label>
                    <select class="form-select form-select-sm shadow-sm" name="custom_timer_seconds">
                        <option value="0" selected>Default</option>
                        <option value="300">5 Min</option>
                        <option value="600">10 Min</option>
                        <option value="900">15 Min</option>
                        <option value="1800">30 Min</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label fw-bold small"><i class="fas fa-coins text-warning me-1"></i> XP Wager</label>
                    <input type="number" name="xp_wager" class="form-control form-control-sm shadow-sm" min="0" max="<?php echo site_battle_wager_max(); ?>" step="10" placeholder="0">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold small"><i class="fas fa-calendar-alt text-success me-1"></i> Starts At <span class="fw-normal text-muted">(optional)</span></label>
                <input type="datetime-local" name="start_time" class="form-control form-control-sm shadow-sm">
                <div class="form-text small text-muted" style="font-size: 0.7rem;">When should the battle go live for acceptance? Leave empty for immediately.</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold small"><i class="fas fa-hourglass-half text-danger me-1"></i> Open For <span class="fw-normal text-muted">(optional)</span></label>
                <select class="form-select form-select-sm shadow-sm" name="duration_hours">
                    <option value="0" selected>No Expiry</option>
                    <option value="1">1 Hour</option>
                    <option value="3">3 Hours</option>
                    <option value="6">6 Hours</option>
                    <option value="12">12 Hours</option>
                    <option value="24">24 Hours</option>
                    <option value="48">2 Days</option>
                    <option value="72">3 Days</option>
                    <option value="168">7 Days</option>
                </select>
                <div class="form-text small text-muted" style="font-size: 0.7rem;">How long should this invite stay open? After this the invite expires.</div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label fw-bold small"><i class="fas fa-users text-primary me-1"></i> Max Challengers</label>
                    <select class="form-select form-select-sm shadow-sm" name="max_participants">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-6 d-flex align-items-end">
                    <div class="form-text small text-muted mb-1" style="font-size: 0.7rem;">How many people can accept this battle invite.</div>
                </div>
            </div>
            <div class="mb-3 d-flex align-items-center justify-content-between">
                <label class="small fw-bold mb-0" for="modalListOnHub"><i class="fas fa-globe text-success me-1"></i> List on Battle Hub</label>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="list_on_hub" value="1" id="modalListOnHub" checked style="cursor: pointer;">
                </div>
            </div>
            <button type="submit" class="btn w-100 fw-bold rounded-pill shadow" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border: none;">
                <i class="fas fa-paper-plane me-2"></i>Create & Get Link
            </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if (isset($_GET['modal']) && $_GET['modal'] === 'insufficient_xp_accept'): ?>
<div class="modal fade" id="insufficientXpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-body p-5 text-center bg-white">
        <div class="mb-4"><i class="fas fa-exclamation-triangle text-danger" style="font-size: 4rem;"></i></div>
        <h3 class="fw-bold mb-3 text-dark">Insufficient XP</h3>
        <p class="text-muted mb-4 fs-5">You do not have enough XP to cover this wager. Your current balance is <strong class="text-primary"><?php echo number_format($user->points); ?> XP</strong>.</p>
        <button type="button" class="btn btn-danger rounded-pill px-5 py-2 fw-bold shadow-sm" data-bs-dismiss="modal">Got It</button>
      </div>
    </div>
  </div>
</div>
<script>document.addEventListener("DOMContentLoaded",function(){new bootstrap.Modal(document.getElementById('insufficientXpModal')).show();});</script>
<?php endif; ?>

<script>
function copyInviteLink(link) {
    navigator.clipboard.writeText(link).then(function() {
        var btn = event.target.closest('.btn-copy, .btn');
        if (btn) {
            var original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Copied!';
            btn.style.background = '#10b981';
            setTimeout(function() { btn.innerHTML = original; btn.style.background = ''; }, 2000);
        }
    });
}

// Tab Toggle Logic
document.querySelectorAll('.arena-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.arena-tab').forEach(function(t) {
            t.classList.remove('active');
            t.style.background = 'transparent';
            t.style.color = '#64748b';
        });
        this.classList.add('active');
        this.style.background = 'linear-gradient(135deg, #3b82f6, #2563eb)';
        this.style.color = 'white';

        document.querySelectorAll('.arena-tab-panel').forEach(function(p) { p.style.display = 'none'; });
        document.getElementById('tab-' + this.dataset.tab).style.display = 'block';
    });
});

// Auto-scroll & highlight when arriving from a notification deep-link
(function() {
    var hash = window.location.hash;
    if (!hash || !hash.startsWith('#battle-')) return;
    var el = document.getElementById(hash.substring(1));
    if (!el) return;
    setTimeout(function() {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.style.transition = 'box-shadow 0.3s';
        el.style.boxShadow = '0 0 0 3px rgba(59,130,246,0.5), 0 0 20px rgba(59,130,246,0.2)';
        setTimeout(function() {
            el.style.boxShadow = '0 0 0 3px rgba(59,130,246,0.3)';
            setTimeout(function() {
                el.style.boxShadow = '0 0 0 3px rgba(59,130,246,0.5), 0 0 20px rgba(59,130,246,0.2)';
                setTimeout(function() { el.style.boxShadow = ''; }, 1500);
            }, 500);
        }, 500);
    }, 300);
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
