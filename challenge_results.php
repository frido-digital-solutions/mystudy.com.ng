<?php
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/models/QuizAttempt.php';

$quizId = (int)($_GET['quiz_id'] ?? 0);
if ($quizId <= 0) {
    set_flash_message('error', 'Invalid quiz ID');
    redirect(SITE_URL . '/quizzes.php');
}

$quizModel = new Quiz($pdo);
$attemptModel = new QuizAttempt($pdo);
$userId = (int)$_SESSION['user_id'];

$quiz = $quizModel->getById($quizId);
if (!$quiz || $quiz['mode'] !== 'challenge') {
    set_flash_message('error', 'Challenge not found');
    redirect(SITE_URL . '/quizzes.php');
}

$totalPhases = (int)$quiz['total_phases'];
$currentPhase = (int)$quiz['current_phase'];

// Get user's phase breakdown
$userPhases = $attemptModel->getUserPhaseBreakdown($quizId, $userId);

// Get final leaderboard
$leaderboard = $attemptModel->topScores($quizId, null, 100);

// Find user's rank
$userRank = 0;
$userTotalScore = 0;
$userTotalQuestions = 0;
foreach ($leaderboard as $index => $entry) {
    if ((int)$entry['user_id'] === $userId) {
        $userRank = $index + 1;
        $userTotalScore = (int)$entry['score'];
        $userTotalQuestions = (int)$entry['total'];
        break;
    }
}

$userPercentage = $userTotalQuestions > 0 ? round(($userTotalScore / $userTotalQuestions) * 100, 1) : 0;
$isWinner = $userRank === 1;
$isTopThree = $userRank <= 3;

$page_title = 'Challenge Results - ' . htmlspecialchars($quiz['title']);

ob_start();
?>
<style>
body.challenge-results-page {
  background: linear-gradient(135deg, #10b98110 0%, #05966910 50%, #34d39910 100%);
  min-height: 100vh;
}

.results-header {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  padding: 3rem 0;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
  position: relative;
  overflow: hidden;
}

.results-header::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
  animation: rotate 20s linear infinite;
  pointer-events: none;
}

@keyframes rotate {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.results-title {
  color: #fff;
  font-size: 2.5rem;
  font-weight: 900;
  text-align: center;
  margin-bottom: 1rem;
  position: relative;
  z-index: 1;
}

.results-subtitle {
  color: rgba(255,255,255,0.95);
  font-size: 1.1rem;
  text-align: center;
  position: relative;
  z-index: 1;
}

.user-result-banner {
  background: #fff;
  border-radius: 25px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 15px 50px rgba(0,0,0,0.1);
  border: 3px solid transparent;
  position: relative;
  overflow: hidden;
}

.user-result-banner.winner {
  border: 3px solid #fbbf24;
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
}

.user-result-banner.top-three {
  border: 3px solid #10b981;
  background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
}

.result-badge {
  position: absolute;
  top: -15px;
  right: 20px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
  padding: 0.5rem 1.5rem;
  border-radius: 50px;
  font-weight: 700;
  font-size: 0.9rem;
  box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}

.result-badge.winner {
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  animation: pulse-gold 2s ease-in-out infinite;
}

@keyframes pulse-gold {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

.user-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-top: 1.5rem;
}

.stat-card {
  background: #fff;
  border: 2px solid #e5e7eb;
  border-radius: 16px;
  padding: 1.5rem 1rem;
  text-align: center;
  transition: border-color 0.2s;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.stat-card:hover {
  border-color: #10b981;
}

.stat-card-icon {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.25rem;
  margin-bottom: 0.75rem;
}

.stat-sub {
  font-size: 0.8rem;
  color: #9ca3af;
  margin-top: 0.25rem;
}

.stat-label {
  font-size: 0.9rem;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 0.5rem;
}

.stat-value {
  font-size: 2.5rem;
  font-weight: 900;
  color: #1f2937;
}

.stat-value.rank {
  color: #10b981;
}

.stat-value.score {
  color: #10b981;
}

.section-title {
  font-size: 1.75rem;
  font-weight: 800;
  color: #1f2937;
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.phase-breakdown-card {
  background: #fff;
  border-radius: 20px;
  padding: 2rem;
  margin-bottom: 2rem;
  box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.phase-item {
  background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
  border: 2px solid #e5e7eb;
  border-radius: 15px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.phase-item:last-child {
  margin-bottom: 0;
}

.phase-header {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.phase-number {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 900;
  font-size: 1.1rem;
  flex-shrink: 0;
}

.phase-info h4 {
  margin: 0;
  font-size: 1.2rem;
  font-weight: 700;
  color: #1f2937;
}

.phase-info p {
  margin: 0;
  font-size: 0.9rem;
  color: #6b7280;
}

.phase-score {
  display: flex;
  gap: 2rem;
  align-items: center;
}

.phase-stat {
  text-align: center;
}

.phase-stat-label {
  font-size: 0.8rem;
  color: #9ca3af;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.phase-stat-value {
  font-size: 1.25rem;
  font-weight: 900;
  color: #10b981;
  margin-top: 0.15rem;
}

.final-leaderboard {
  background: #fff;
  border-radius: 25px;
  overflow: hidden;
  box-shadow: 0 15px 50px rgba(0,0,0,0.1);
  margin-bottom: 2rem;
}

.leaderboard-header {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
  padding: 1.25rem 1.5rem;
  font-size: 1.25rem;
  font-weight: 800;
}

.leaderboard-entry {
  padding: 1.5rem 2rem;
  border-bottom: 2px solid #f3f4f6;
  display: flex;
  align-items: center;
  gap: 1.5rem;
  transition: all 0.3s ease;
}

.leaderboard-entry:hover {
  background: #f0fdf4;
}

.rank-badge-small {
  min-width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  font-weight: 900;
  background: #f3f4f6;
  color: #6b7280;
  flex-shrink: 0;
}

.leaderboard-entry.rank-1 .rank-badge-small {
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  color: #fff;
}

.leaderboard-entry.rank-2 .rank-badge-small {
  background: linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%);
  color: #fff;
}

.leaderboard-entry.rank-3 .rank-badge-small {
  background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
  color: #fff;
}

.user-info-small {
  flex: 1;
}

.user-info-small h4 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: #1f2937;
}

.user-info-small p {
  margin: 0;
  font-size: 0.85rem;
  color: #6b7280;
}

.score-display {
  font-size: 1.25rem;
  font-weight: 900;
  color: #10b981;
  white-space: nowrap;
}

.score-column {
  text-align: right;
  flex-shrink: 0;
}

.score-detail {
  font-size: 0.75rem;
  color: #9ca3af;
  font-weight: 600;
}

.leaderboard-entry.current-user {
  background: #f0fdf4;
  border-left: 4px solid #10b981;
}

.leaderboard-entry.current-user:hover {
  background: #dcfce7;
}

.entry-time {
  font-size: 0.75rem;
  color: #9ca3af;
  margin-left: 0.5rem;
}

.entry-time i {
  font-size: 0.65rem;
}

.entry-time.tied {
  color: #f59e0b;
  font-weight: 700;
}

@media (max-width: 768px) {
  .results-header {
    padding: 2rem 0;
    margin-bottom: 1.5rem;
  }
  
  .results-title {
    font-size: 1.5rem;
  }
  
  .results-subtitle {
    font-size: 0.95rem;
  }
  
  .user-result-banner {
    padding: 1.5rem;
    border-radius: 16px;
    margin-bottom: 1.5rem;
  }
  
  .user-result-banner h2 {
    font-size: 1.25rem;
  }
  
  .result-badge {
    position: relative;
    top: auto;
    right: auto;
    display: inline-block;
    margin-bottom: 1rem;
    font-size: 0.8rem;
    padding: 0.4rem 1rem;
  }
  
  .user-stats {
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
  }
  
  .stat-card {
    padding: 1rem;
    border-radius: 12px;
  }
  
  .stat-value {
    font-size: 1.75rem;
  }
  
  .stat-label {
    font-size: 0.8rem;
  }
  
  .section-title {
    font-size: 1.25rem;
    margin-bottom: 1rem;
  }
  
  .phase-breakdown-card {
    padding: 1.25rem;
    border-radius: 16px;
  }
  
  .phase-item {
    flex-direction: column;
    align-items: stretch;
    padding: 1rem;
    gap: 0.75rem;
  }
  
  .phase-number {
    width: 40px;
    height: 40px;
    font-size: 1rem;
  }
  
  .phase-info h4 {
    font-size: 1rem;
  }
  
  .phase-score {
    gap: 1rem;
    justify-content: space-around;
    width: 100%;
  }
  
  .phase-stat-value {
    font-size: 1.1rem;
  }
  
  .final-leaderboard {
    border-radius: 16px;
  }
  
  .leaderboard-header {
    font-size: 1.1rem;
    padding: 1rem 1.25rem;
  }
  
  .leaderboard-entry {
    padding: 1rem;
    gap: 0.75rem;
  }
  
  .rank-badge-small {
    min-width: 40px;
    height: 40px;
    font-size: 1rem;
    border-radius: 10px;
  }
  
  .user-info-small h4 {
    font-size: 0.95rem;
  }
  
  .user-info-small p {
    font-size: 0.8rem;
  }
  
  .score-display {
    font-size: 1.1rem;
  }
}

@media (max-width: 576px) {
  .results-title {
    font-size: 1.25rem;
  }
  
  .user-stats {
    grid-template-columns: 1fr;
  }
  
  .stat-value {
    font-size: 1.5rem;
  }
  
  .phase-score {
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-start;
  }
  
  .phase-stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-align: left;
  }
  
  .phase-stat-value {
    font-size: 1rem;
  }
  
  .score-display {
    font-size: 1rem;
  }
}
</style>
<?php
$page_scripts = ($page_scripts ?? '') . ob_get_clean();

include __DIR__ . '/includes/header.php';
?>
<script>
  document.body.classList.add('challenge-results-page');
</script>

<div class="results-header">
  <div class="container">
    <div class="results-title">
      <i class="fas fa-trophy me-2"></i>
      Challenge Results
    </div>
    <div class="results-subtitle">
      <?php echo htmlspecialchars($quiz['title']); ?>
    </div>
  </div>
</div>

<div class="container py-4">
  <!-- User Result Banner -->
  <div class="user-result-banner <?php echo $isWinner ? 'winner' : ($isTopThree ? 'top-three' : ''); ?>">
    <?php if ($isWinner): ?>
      <div class="result-badge winner">
        <i class="fas fa-crown me-2"></i>WINNER!
      </div>
    <?php elseif ($isTopThree): ?>
      <div class="result-badge">
        <i class="fas fa-medal me-2"></i>TOP 3
      </div>
    <?php else: ?>
      <div class="result-badge">COMPLETED</div>
    <?php endif; ?>
    
    <h2 class="mb-0">
      <?php if ($isWinner): ?>
        🎉 Congratulations! You Won!
      <?php elseif ($isTopThree): ?>
        🥈 Amazing! You're in the Top 3!
      <?php else: ?>
        Great Job Completing the Challenge!
      <?php endif; ?>
    </h2>
    
    <div class="user-stats">
      <div class="stat-card">
        <div class="stat-card-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
          <i class="fas fa-medal"></i>
        </div>
        <div class="stat-label">Final Rank</div>
        <div class="stat-value rank">#<?php echo $userRank; ?></div>
        <div class="stat-sub">Out of <?php echo count($leaderboard); ?> participants</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-card-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
          <i class="fas fa-bullseye"></i>
        </div>
        <div class="stat-label">Total Score</div>
        <div class="stat-value score"><?php echo $userPercentage; ?>%</div>
        <div class="stat-sub"><?php echo $userTotalScore; ?> correct out of <?php echo $userTotalQuestions; ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-card-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
          <i class="fas fa-layer-group"></i>
        </div>
        <div class="stat-label">Phases</div>
        <div class="stat-value"><?php echo count($userPhases); ?>/<?php echo $totalPhases; ?></div>
        <div class="stat-sub"><?php echo count($userPhases) >= $totalPhases ? 'All completed ✓' : 'In progress'; ?></div>
      </div>
    </div>
  </div>

  <!-- Phase Breakdown -->
  <?php if (count($userPhases) > 1): ?>
  <div class="phase-breakdown-card">
    <h3 class="section-title">
      <i class="fas fa-chart-line"></i>
      Phase-by-Phase Breakdown
    </h3>
    
    <?php foreach ($userPhases as $phase): 
      $phaseNum = (int)$phase['phase'];
      $phaseScore = (int)$phase['score'];
      $phaseTotal = (int)$phase['total'];
      $phasePercentage = $phaseTotal > 0 ? round(($phaseScore / $phaseTotal) * 100, 1) : 0;
      $phaseDuration = gmdate('i:s', (int)$phase['duration_used']);
    ?>
    <div class="phase-item">
      <div class="phase-header">
        <div class="phase-number"><?php echo $phaseNum; ?></div>
        <div class="phase-info">
          <h4>Phase <?php echo $phaseNum; ?></h4>
          <p><?php echo date('M j, Y', strtotime($phase['completed_at'])); ?></p>
        </div>
      </div>
      
      <div class="phase-score">
        <div class="phase-stat">
          <div class="phase-stat-label">Score</div>
          <div class="phase-stat-value"><?php echo $phaseScore; ?>/<?php echo $phaseTotal; ?></div>
        </div>
        <div class="phase-stat">
          <div class="phase-stat-label">Accuracy</div>
          <div class="phase-stat-value"><?php echo $phasePercentage; ?>%</div>
        </div>
        <div class="phase-stat">
          <div class="phase-stat-label">Time</div>
          <div class="phase-stat-value"><?php echo $phaseDuration; ?></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Final Leaderboard -->
  <div class="final-leaderboard">
    <div class="leaderboard-header">
      <i class="fas fa-ranking-star me-2"></i>
      Final Rankings
      <span style="font-size:0.7rem; font-weight:500; opacity:0.85; display:block; margin-top:0.25rem;">Ranked by score &bull; Ties broken by fastest time &bull; Then earliest submission</span>
    </div>
    
    <?php 
    $prevScore = null;
    $prevTime = null;
    foreach (array_slice($leaderboard, 0, 20) as $index => $entry): 
      $rank = $index + 1;
      $rankClass = 'rank-' . min($rank, 4);
      $entryScore = (int)$entry['score'];
      $entryTotal = (int)$entry['total'];
      $entryPct = $entryTotal > 0 ? round(($entryScore / $entryTotal) * 100, 1) : 0;
      $entryTime = (int)$entry['duration_used'];
      $timeFormatted = gmdate('H:i:s', $entryTime);
      $isCurrentUser = ((int)$entry['user_id'] === $userId);
      $isTied = ($prevScore !== null && $entryScore === $prevScore);
      $prevScore = $entryScore;
      $prevTime = $entryTime;
    ?>
    <div class="leaderboard-entry <?php echo $rankClass; ?> <?php echo $isCurrentUser ? 'current-user' : ''; ?>">
      <div class="rank-badge-small">
        <?php if ($rank === 1): ?>
          <i class="fas fa-crown"></i>
        <?php else: ?>
          <?php echo $rank; ?>
        <?php endif; ?>
      </div>
      <div class="user-info-small">
        <h4><?php echo htmlspecialchars($entry['name']); ?><?php echo $isCurrentUser ? ' <span style="color:#10b981; font-size:0.75rem;">(You)</span>' : ''; ?></h4>
        <p>
          <?php echo ucfirst(htmlspecialchars($entry['user_type'])); ?>
          <span class="entry-time<?php echo $isTied ? ' tied' : ''; ?>">
            <i class="fas fa-clock"></i> <?php echo $timeFormatted; ?>
          </span>
        </p>
      </div>
      <div class="score-column">
        <div class="score-display"><?php echo $entryPct; ?>%</div>
        <div class="score-detail"><?php echo $entryScore; ?>/<?php echo $entryTotal; ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="text-center">
    <a href="<?php echo SITE_URL; ?>/my_quizzes.php" class="btn btn-primary btn-lg" style="border-radius: 50px; padding: 1rem 3rem;">
      <i class="fas fa-arrow-left me-2"></i>
      Back to My Quizzes
    </a>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
