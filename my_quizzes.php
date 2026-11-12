<?php
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/models/QuizAttempt.php';

$page_title = 'My Quizzes';
$userId = (int)$_SESSION['user_id'];

$attemptModel = new QuizAttempt($pdo);
$quizModel = new Quiz($pdo);

// Get user's quiz history grouped by distinct quiz_id, displaying their highest score
$sql = "SELECT q.id as quiz_id, q.title, q.subject, q.level, q.mode, q.total_phases, q.current_phase,
               COUNT(DISTINCT qa.phase) as phases_completed,
               MAX(qa.score) as total_score,
               MAX(qa.total) as total_questions,
               MAX(qa.completed_at) as last_attempt,
               (SELECT id FROM quiz_attempts WHERE quiz_id = q.id AND user_id = ? ORDER BY score DESC, id DESC LIMIT 1) as best_attempt_id
        FROM quiz_attempts qa
        JOIN quizzes q ON q.id = qa.quiz_id
        WHERE qa.user_id = ?
        GROUP BY q.id, q.title, q.subject, q.level, q.mode, q.total_phases, q.current_phase
        ORDER BY last_attempt DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$userId, $userId]);
$userQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's rankings for each challenge
$rankings = [];
foreach ($userQuizzes as $uq) {
    if ($uq['mode'] === 'challenge') {
        $quizId = (int)$uq['quiz_id'];
        $leaderboard = $attemptModel->topScores($quizId, null, 1000); // Get all to find user's rank
        $userRank = 0;
        foreach ($leaderboard as $index => $entry) {
            if ((int)$entry['user_id'] === $userId) {
                $userRank = $index + 1;
                break;
            }
        }
        $rankings[$quizId] = [
            'rank' => $userRank,
            'total_participants' => count($leaderboard)
        ];
    }
}

ob_start();
?>
<style>
body.my-quizzes-page {
  background: linear-gradient(135deg, #10b98115 0%, #05966915 50%, #34d39915 100%);
  min-height: 100vh;
}

.page-header {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  padding: 2.5rem 0;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
  color: #fff;
  text-align: center;
}

.page-header h1 {
  font-size: 2.5rem;
  font-weight: 900;
  margin-bottom: 0.5rem;
}

.quiz-history-card {
  background: #fff;
  border-radius: 20px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 10px 40px rgba(0,0,0,0.08);
  transition: all 0.3s ease;
  border: 2px solid transparent;
}

.quiz-history-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 50px rgba(0,0,0,0.12);
  border-color: #10b981;
}

.quiz-history-card.challenge {
  background-image: linear-gradient(#fff, #fff), linear-gradient(135deg, #10b981 0%, #34d399 100%);
  background-origin: border-box;
  background-clip: padding-box, border-box;
}

.quiz-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  margin-bottom: 1rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.quiz-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 0.5rem;
}

.quiz-meta {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  align-items: center;
  font-size: 0.9rem;
  color: #6b7280;
}

.quiz-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 50px;
  font-weight: 600;
  font-size: 0.85rem;
}

.badge-challenge {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
}

.badge-normal {
  background: #e5e7eb;
  color: #4b5563;
}

.quiz-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
  margin: 1rem 0;
}

.stat-box {
  background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
  padding: 1rem;
  border-radius: 15px;
  text-align: center;
  border: 2px solid #e5e7eb;
}

.stat-label {
  font-size: 0.85rem;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 0.5rem;
}

.stat-value {
  font-size: 1.75rem;
  font-weight: 900;
  color: #1f2937;
}

.stat-value.score {
  color: #10b981;
}

.stat-value.rank {
  color: #059669;
}

.phase-progress {
  background: #e5e7eb;
  height: 10px;
  border-radius: 50px;
  overflow: hidden;
  margin: 1rem 0;
}

.phase-progress-bar {
  background: linear-gradient(90deg, #10b981 0%, #059669 100%);
  height: 100%;
  border-radius: 50px;
  transition: width 0.3s ease;
}

.phase-info {
  font-size: 0.9rem;
  color: #6b7280;
  margin-top: 0.5rem;
}

.action-buttons {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.btn-view-details {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
  padding: 0.75rem 1.5rem;
  border-radius: 50px;
  text-decoration: none;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.3s ease;
  border: none;
}

.btn-view-details:hover {
  background: linear-gradient(135deg, #059669 0%, #10b981 100%);
  transform: scale(1.05);
  color: #fff;
}

.btn-leaderboard {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  padding: 0.75rem 1.5rem;
  border-radius: 50px;
  text-decoration: none;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.3s ease;
  border: 2px solid #10b981;
}

.btn-leaderboard:hover {
  background: #10b981;
  color: #fff;
  transform: scale(1.05);
}

.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.08);
}

.empty-state i {
  font-size: 4rem;
  color: #d1d5db;
  margin-bottom: 1rem;
}

.empty-state h3 {
  color: #6b7280;
  margin-bottom: 1rem;
}

.empty-state .btn {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
  padding: 0.875rem 2rem;
  border-radius: 50px;
  text-decoration: none;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 1rem;
}

@media (max-width: 768px) {
  .page-header h1 {
    font-size: 1.75rem;
  }
  
  .quiz-header {
    flex-direction: column;
  }
  
  .quiz-stats {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
<?php
$page_scripts = ($page_scripts ?? '') . ob_get_clean();

include __DIR__ . '/includes/header.php';
?>
<script>
  document.body.classList.add('my-quizzes-page');
</script>

<div class="page-header">
  <div class="container">
    <h1><i class="fas fa-history me-2"></i>My Quizzes</h1>
    <p>Track your progress and rankings across all challenges</p>
  </div>
</div>

<div class="container py-4">
  <?php if (empty($userQuizzes)): ?>
    <div class="empty-state">
      <i class="fas fa-clipboard-question"></i>
      <h3>No Quiz History Yet</h3>
      <p>Start taking quizzes to see your progress here!</p>
      <a href="<?php echo SITE_URL; ?>/quizzes.php" class="btn">
        <i class="fas fa-rocket me-2"></i>
        Browse Quizzes
      </a>
    </div>
  <?php else: ?>
    <?php foreach ($userQuizzes as $quiz): 
      $quizId = (int)$quiz['quiz_id'];
      $isChallenge = $quiz['mode'] === 'challenge';
      $totalPhases = (int)$quiz['total_phases'];
      $currentPhase = (int)$quiz['current_phase'];
      $phasesCompleted = (int)$quiz['phases_completed'];
      $phaseProgress = $totalPhases > 1 ? ($phasesCompleted / $totalPhases) * 100 : 100;
      $totalScore = (int)$quiz['total_score'];
      $totalQuestions = (int)$quiz['total_questions'];
      $percentage = $totalQuestions > 0 ? round(($totalScore / $totalQuestions) * 100, 1) : 0;
    ?>
    <div class="quiz-history-card <?php echo $isChallenge ? 'challenge' : ''; ?>">
      <div class="quiz-header">
        <div>
          <h3 class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></h3>
          <div class="quiz-meta">
            <span class="quiz-badge <?php echo $isChallenge ? 'badge-challenge' : 'badge-normal'; ?>">
              <?php echo $isChallenge ? '<i class="fas fa-trophy me-1"></i> Challenge' : 'Normal Quiz'; ?>
            </span>
            <?php if (!empty($quiz['subject'])): ?>
              <span><i class="fas fa-book me-1"></i><?php echo htmlspecialchars($quiz['subject']); ?></span>
            <?php endif; ?>
            <?php if (!empty($quiz['level'])): ?>
              <span><i class="fas fa-layer-group me-1"></i><?php echo htmlspecialchars($quiz['level']); ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="text-muted small">
          Last attempt: <?php echo date('M j, Y', strtotime($quiz['last_attempt'])); ?>
        </div>
      </div>

      <div class="quiz-stats">
        <div class="stat-box">
          <div class="stat-label">Your Score</div>
          <div class="stat-value score"><?php echo $totalScore; ?>/<?php echo $totalQuestions; ?></div>
          <small class="text-muted"><?php echo $percentage; ?>%</small>
        </div>
        
        <?php if ($isChallenge && isset($rankings[$quizId])): ?>
          <div class="stat-box">
            <div class="stat-label">Your Rank</div>
            <div class="stat-value rank">#<?php echo $rankings[$quizId]['rank']; ?></div>
            <small class="text-muted">of <?php echo $rankings[$quizId]['total_participants']; ?></small>
          </div>
        <?php endif; ?>
        
        <?php if ($totalPhases > 1): ?>
          <div class="stat-box">
            <div class="stat-label">Phases</div>
            <div class="stat-value"><?php echo $phasesCompleted; ?>/<?php echo $totalPhases; ?></div>
            <small class="text-muted">Completed</small>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($totalPhases > 1): ?>
      <div class="phase-progress">
        <div class="phase-progress-bar" style="width: <?php echo $phaseProgress; ?>%"></div>
      </div>
      <div class="phase-info">
        <?php if ($phasesCompleted < $totalPhases): ?>
          <i class="fas fa-info-circle me-1"></i>
          Phase <?php echo $currentPhase; ?> is currently active. Complete it to improve your ranking!
        <?php else: ?>
          <i class="fas fa-check-circle me-1 text-success"></i>
          All phases completed! Check final results.
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="action-buttons mt-3">
        <!-- Always show leaderboard and results for ALL quiz types -->
        <a href="<?php echo SITE_URL; ?>/leaderboard.php?quiz_id=<?php echo $quizId; ?>" class="btn-leaderboard">
          <i class="fas fa-trophy"></i>
          View Leaderboard
        </a>
        <a href="<?php echo SITE_URL; ?>/quiz_result.php?attempt=<?php echo htmlspecialchars($quiz['best_attempt_id'] ?? ''); ?>" class="btn-view-details">
          <i class="fas fa-chart-line"></i>
          View Full Results
        </a>
        <a href="<?php echo SITE_URL; ?>/certificate.php?attempt_id=<?php echo htmlspecialchars($quiz['best_attempt_id'] ?? ''); ?>" target="_blank" class="btn-leaderboard">
          <i class="fas fa-certificate text-warning"></i>
          Download Result
        </a>
        
        <!-- Only allow "Take Again" for normal quizzes (not challenge mode) -->
        <?php if (!$isChallenge): ?>
          <a href="<?php echo SITE_URL; ?>/quiz.php?id=<?php echo $quizId; ?>" class="btn-view-details" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
            <i class="fas fa-redo"></i>
            Take Again
          </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
