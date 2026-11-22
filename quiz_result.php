<?php
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/models/QuizAttempt.php';

$attemptId = (int)($_GET['attempt'] ?? 0);
if ($attemptId <= 0) { redirect(SITE_URL . '/quizzes.php'); }

$attemptModel = new QuizAttempt($pdo);
$attempt = $attemptModel->getAttempt($attemptId, (int)$_SESSION['user_id']);
if (!$attempt) { set_flash_message('error','Attempt not found'); redirect(SITE_URL . '/quizzes.php'); }

$quizModel = new Quiz($pdo);
$quiz = $quizModel->getById((int)$attempt['quiz_id']);
$answers = $attemptModel->getAnswersDetailed($attemptId);
$isChallenge = ($quiz['mode'] ?? 'normal') === 'challenge';
$isPaidQuiz = !empty($quiz['is_paid']);

// Anti-cheat: determine if challenge event has ended (corrections locked until then)
$challengeEventEnded = true; // default: show corrections
if ($isChallenge && !empty($quiz['event_end'])) {
    $challengeEventEnded = (time() > strtotime($quiz['event_end']));
}

$isMultiSubject = false;

// Analyze subjects
$subjectStats = [];
$totalSubjectsCount = 0;
foreach ($answers as $idx => $row) {
    if (empty($row['subject'])) continue; // Failsafe if questions not explicitly tied to subjects
    $subj = $row['subject'];
    if (!isset($subjectStats[$subj])) {
        $subjectStats[$subj] = ['total' => 0, 'correct' => 0];
        $totalSubjectsCount++;
    }
    $subjectStats[$subj]['total']++;
    if ((int)$row['is_correct'] === 1) {
        $subjectStats[$subj]['correct']++;
    }
}
$isMultiSubject = $totalSubjectsCount > 1;

$sortedStats = [];
$strongest = null;
$weakest = null;

if ($isMultiSubject) {
    foreach ($subjectStats as $subj => $stat) {
        $stat['percentage'] = $stat['total'] > 0 ? round(($stat['correct'] / $stat['total']) * 100, 1) : 0;
        $sortedStats[$subj] = $stat;
    }
    uasort($sortedStats, function($a, $b) {
        return $b['percentage'] <=> $a['percentage'];
    });
    $keys = array_keys($sortedStats);
    $strongest = $keys[0];
    $weakest = end($keys);
}

$page_title = 'Quiz Result - ' . htmlspecialchars($quiz['title'] ?? 'Multi-Subject Quiz');

// Fetch recommended resources related to quiz subject
require_once __DIR__ . '/models/Material.php';
require_once __DIR__ . '/models/Tutorial.php';

$subjectKeyword = $quiz['subject'] ?? '';
$relatedMaterials = [];
$relatedTutorials = [];

if (!empty($subjectKeyword)) {
    try {
        $materialModel = new Material($pdo);
        $relatedMaterials = $materialModel->searchMaterials($subjectKeyword, null, null, null, null, 3, 0, 'approved');
    } catch (Exception $e) { /* silence */ }

    try {
        $tutorialModel = new Tutorial($pdo);
        $relatedTutorials = $tutorialModel->searchTutorials($subjectKeyword, null, null, 3, 0);
    } catch (Exception $e) { /* silence */ }
}

// Inject page-specific styles via footer ($page_scripts)
ob_start();
?>
<style>
  /* Result items (Normal Quiz) */
  .result-item { 
      background: #fff; 
      border: 1px solid #e2e8f0; 
      border-radius: 16px; 
      padding: 1.5rem; 
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
      transition: all 0.2s ease;
  }
  .result-item:hover {
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
      transform: translateY(-2px);
  }
  .result-item + .result-item { margin-top: 1.5rem; }
  .result-item .question-stem { font-weight: 700; font-size: 1.1rem; color: #1e293b; margin-bottom: 1rem; }
  .result-item .explanation { 
      background: #f0fdf4; 
      border: 1px solid #bbf7d0; 
      border-radius: 12px; 
      padding: 1rem; 
      color: #166534; 
      margin-top: 1rem;
  }
  .score-pill { font-weight: 800; color: #10b981; }

  /* ========================================= */
  /* PREMIUM RESULT HERO                       */
  /* ========================================= */
  .result-hero {
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
      border-radius: 24px;
      padding: 2.5rem;
      margin-bottom: 2rem;
      position: relative;
      overflow: hidden;
  }

  .result-hero::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -30%;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 70%);
      pointer-events: none;
  }

  .result-hero-inner {
      display: flex;
      align-items: center;
      gap: 2rem;
      position: relative;
      z-index: 1;
  }

  .result-hero-icon {
      font-size: 3rem;
      color: #fbbf24;
  }

  .result-hero-text { flex: 1; }

  .result-hero-title {
      font-size: 2rem;
      font-weight: 900;
      color: #fff;
      margin: 0 0 0.25rem 0;
      letter-spacing: -0.5px;
  }

  .result-hero-subtitle {
      font-size: 1rem;
      color: rgba(255,255,255,0.95);
      margin: 0;
  }

  .result-hero-score { position: relative; }

  .score-ring {
      position: relative;
      width: 120px;
      height: 120px;
  }

  .score-ring svg {
      width: 100%;
      height: 100%;
  }

  .score-ring-text {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
  }

  .score-ring-pct {
      font-size: 1.75rem;
      font-weight: 900;
      color: #fff;
      line-height: 1;
  }

  .score-ring-label {
      font-size: 0.8rem;
      color: rgba(255,255,255,0.95);
      margin-top: 0.25rem;
      font-weight: 600;
  }

  .result-hero-stats {
      display: flex;
      gap: 1.5rem;
      flex-wrap: wrap;
      margin-top: 1.5rem;
      padding-top: 1.5rem;
      border-top: 1px solid rgba(255,255,255,0.1);
      position: relative;
      z-index: 1;
  }

  .rh-stat {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: #f8fafc;
      font-size: 0.95rem;
  }

  /* ========================================= */
  /* RESULT QUESTION REVIEW CARDS              */
  /* ========================================= */
  .result-answers-section { margin-top: 1rem; }

  .result-section-title {
      font-size: 1.5rem;
      font-weight: 900;
      color: #0f172a;
      margin-bottom: 1.5rem;
      letter-spacing: -0.5px;
  }

  .result-q-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.75rem;
  }

  .result-q-num {
      background: #f1f5f9;
      color: #475569;
      font-weight: 800;
      font-size: 0.85rem;
      padding: 0.3rem 0.75rem;
      border-radius: 8px;
  }

  .result-q-status {
      font-weight: 700;
      font-size: 0.85rem;
      padding: 0.3rem 0.75rem;
      border-radius: 50px;
  }

  .result-q-status.correct {
      background: #f0fdf4;
      color: #16a34a;
  }

  .result-q-status.wrong {
      background: #fef2f2;
      color: #dc2626;
  }

  .result-answers {
      margin-top: 1rem;
  }

  .result-answer-row {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      padding: 0.75rem 1rem;
      border-radius: 12px;
      margin-bottom: 0.5rem;
  }

  .result-answer-row.answer-correct {
      background: #f0fdf4;
  }

  .result-answer-row.answer-wrong {
      background: #fef2f2;
  }

  .result-answer-icon {
      font-size: 1.2rem;
      margin-top: 0.15rem;
  }

  .result-answer-label {
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #64748b;
      margin-bottom: 0.1rem;
  }

  .result-answer-text {
      font-weight: 600;
      color: #1e293b;
  }

  .quiz-picture {
      margin-top: 0.75rem;
      background: #fff;
      border: 1px solid rgba(15, 23, 42, 0.12);
      border-radius: 12px;
      padding: 0.5rem;
      text-align: center;
  }

  .quiz-picture img {
      max-width: 100%;
      max-height: 200px;
      height: auto;
      object-fit: contain;
      border-radius: 8px;
  }

  .result-answer-text .quiz-picture {
      margin: 0.5rem 0 0;
      max-width: 320px;
  }

  .result-answer-text .quiz-picture img {
      max-height: 130px;
  }

  @media (max-width: 575.98px) {
      .quiz-picture {
          padding: 0.35rem;
          margin-top: 0.5rem;
      }
      .quiz-picture img,
      .result-answer-text .quiz-picture img {
          max-height: 115px;
      }
  }

  /* ========================================= */
  /* RESULT BUTTONS                            */
  /* ========================================= */
  .btn-result-primary {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      border: none;
      border-radius: 50px;
      padding: 0.875rem 2rem;
      font-weight: 700;
      color: #fff;
      box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
      transition: all 0.3s ease;
  }

  .btn-result-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4);
      color: #fff;
  }

  .btn-result-secondary {
      background: #fff;
      border: 2px solid #e2e8f0;
      border-radius: 50px;
      padding: 0.875rem 2rem;
      font-weight: 700;
      color: #475569;
      transition: all 0.3s ease;
  }

  .btn-result-secondary:hover {
      border-color: #10b981;
      color: #10b981;
      transform: translateY(-3px);
  }

  /* ========================================= */
  /* RESULT SIDEBAR                            */
  /* ========================================= */
  .result-sidebar {
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.05);
      padding: 1.5rem;
      position: sticky;
      top: 1rem;
  }

  .result-sidebar-section { margin-bottom: 1rem; }

  .result-sidebar-title {
      font-size: 1.1rem;
      font-weight: 800;
      color: #0f172a;
      margin-bottom: 1rem;
  }

  .analytics-highlight {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem;
  }

  .analytics-card {
      padding: 1rem;
      border-radius: 14px;
      display: flex;
      align-items: center;
      gap: 0.75rem;
  }

  .analytics-card.strongest {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
  }

  .analytics-card.strongest i {
      color: #10b981;
      font-size: 1.5rem;
  }

  .analytics-card.weakest {
      background: #fef2f2;
      border: 1px solid #fecaca;
  }

  .analytics-card.weakest i {
      color: #ef4444;
      font-size: 1.5rem;
  }

  .analytics-label {
      font-size: 0.7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #64748b;
  }

  .analytics-value {
      font-weight: 800;
      font-size: 0.95rem;
      color: #1e293b;
  }

  .analytics-pct {
      font-weight: 700;
      font-size: 0.85rem;
      color: #64748b;
  }

  .subject-breakdown-item {
      margin-bottom: 1rem;
  }

  /* ========================================= */
  /* RESPONSIVE RESULT PAGE                    */
  /* ========================================= */
  @media (max-width: 768px) {
      .result-hero-inner { flex-direction: column; text-align: center; }
      .result-hero-icon { font-size: 2.5rem; }
      .result-hero-title { font-size: 1.5rem; }
      .result-hero-stats { justify-content: center; }
      .analytics-highlight { grid-template-columns: 1fr; }
  }

  /* ========================================= */
  /* RECOMMENDED RESOURCES                     */
  /* ========================================= */
  .recommend-section {
      background: #fff;
      border-radius: 24px;
      padding: 2.5rem;
      box-shadow: 0 10px 30px rgba(0,0,0,0.05);
  }

  .recommend-header {
      display: flex;
      align-items: center;
      gap: 1.25rem;
      margin-bottom: 2rem;
  }

  .recommend-icon {
      width: 56px;
      height: 56px;
      border-radius: 16px;
      background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: #fff;
      flex-shrink: 0;
  }

  .recommend-title {
      font-size: 1.5rem;
      font-weight: 900;
      color: #0f172a;
      margin: 0 0 0.25rem 0;
      letter-spacing: -0.5px;
  }

  .recommend-subtitle {
      font-size: 0.95rem;
      color: #64748b;
      margin: 0;
  }

  .recommend-block {
      margin-bottom: 1.5rem;
  }

  .recommend-block-title {
      font-size: 1.1rem;
      font-weight: 800;
      color: #334155;
      margin-bottom: 1rem;
  }

  .recommend-grid {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
  }

  .recommend-card {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.25rem;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      text-decoration: none;
      color: inherit;
      transition: all 0.2s ease;
  }

  .recommend-card:hover {
      background: #f0fdf4;
      border-color: #10b981;
      transform: translateX(4px);
      color: inherit;
      text-decoration: none;
  }

  .recommend-card-icon {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
      flex-shrink: 0;
  }

  .recommend-card-icon.material {
      background: #fef2f2;
      color: #ef4444;
  }

  .recommend-card-icon.tutorial {
      background: #eff6ff;
      color: #3b82f6;
  }

  .recommend-card-body {
      flex: 1;
      min-width: 0;
  }

  .recommend-card-title {
      font-weight: 700;
      font-size: 0.95rem;
      color: #1e293b;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
  }

  .recommend-card-meta {
      font-size: 0.8rem;
      color: #94a3b8;
      display: flex;
      gap: 1rem;
      margin-top: 0.25rem;
  }

  .recommend-card-action {
      color: #cbd5e1;
      font-size: 0.9rem;
      flex-shrink: 0;
      transition: color 0.2s;
  }

  .recommend-card:hover .recommend-card-action {
      color: #10b981;
  }

  .recommend-advice {
      display: flex;
      align-items: flex-start;
      gap: 1rem;
      padding: 1.25rem;
      background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
      border: 1px solid #bbf7d0;
      border-radius: 16px;
      margin-top: 1.5rem;
  }

  .advice-icon {
      font-size: 1.5rem;
      color: #10b981;
      flex-shrink: 0;
      margin-top: 0.1rem;
  }

  .advice-body {
      font-size: 0.95rem;
      color: #166534;
      line-height: 1.6;
  }

  @media (max-width: 768px) {
      .recommend-section { padding: 1.5rem; }
      .recommend-header { flex-direction: column; text-align: center; }
      .recommend-card-title { white-space: normal; }
  }

  /* Mobile Quiz Details Toggle */
  .btn-quiz-details-toggle {
      display: flex;
      align-items: center;
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 0.875rem 1.25rem;
      font-weight: 700;
      font-size: 0.95rem;
      color: #475569;
      transition: all 0.2s ease;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  }

  .btn-quiz-details-toggle:hover,
  .btn-quiz-details-toggle:focus {
      background: #f8fafc;
      border-color: #10b981;
      color: #10b981;
  }

  .mobile-quiz-details-body {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-top: none;
      border-radius: 0 0 14px 14px;
      padding: 1.25rem;
      margin-top: -1px;
  }

<?php if ($isChallenge): ?>
/* ========================================= */
/* CHALLENGE RESULT PREMIUM DESIGN */
/* ========================================= */

/* Challenge Background */
body.challenge-result-page {
  background: #f8fafc;
  min-height: 100vh;
}

/* Challenge Result Header Banner */
.challenge-result-header {
  background: linear-gradient(135deg, #059669 0%, #10b981 100%);
  padding: 2.5rem 0;
  margin-bottom: 2rem;
  box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
  position: relative;
  overflow: hidden;
}

.challenge-result-header::before {
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

.challenge-result-title {
  color: #fff;
  font-size: 2.5rem;
  font-weight: 900;
  text-shadow: 0 2px 10px rgba(0,0,0,0.2);
  margin-bottom: 1rem;
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.challenge-result-badge {
  background: rgba(255,255,255,0.25);
  padding: 0.5rem 1.25rem;
  border-radius: 25px;
  font-size: 0.9rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,0.3);
}

/* Premium Score Display */
.challenge-score-display {
  background: rgba(255,255,255,0.15);
  backdrop-filter: blur(10px);
  border: 2px solid rgba(255,255,255,0.3);
  border-radius: 25px;
  padding: 1.5rem 2.5rem;
  display: inline-block;
  position: relative;
  z-index: 1;
}

.challenge-score-label {
  color: rgba(255,255,255,0.9);
  font-size: 0.9rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 0.5rem;
}

.challenge-score-value {
  font-size: 3rem;
  font-weight: 900;
  color: #fff;
  line-height: 1;
  text-shadow: 0 3px 15px rgba(0,0,0,0.3);
}

.challenge-score-percentage {
  font-size: 1.25rem;
  color: rgba(255,255,255,0.95);
  font-weight: 700;
  margin-top: 0.5rem;
}

/* Pass/Fail Indicator */
.challenge-status-badge {
  display: inline-block;
  padding: 0.75rem 1.5rem;
  border-radius: 50px;
  font-weight: 800;
  font-size: 1.1rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-top: 1rem;
  box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}

.status-pass {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
}

.status-fail {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: #fff;
}

/* Premium Result Cards */
.challenge-result-page .result-item {
  background: #fff;
  border: 2px solid transparent;
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 10px 40px rgba(0,0,0,0.08);
  position: relative;
  overflow: visible;
  transition: all 0.3s ease;
  margin-bottom: 1.5rem;
  margin-top: 1.5rem;
}

.challenge-result-page .result-item::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 6px;
  z-index: 1;
}

.challenge-result-page .result-item.correct::before {
  background: linear-gradient(90deg, #10b981 0%, #059669 100%);
}

.challenge-result-page .result-item.incorrect::before {
  background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
}

.challenge-result-page .result-item:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 50px rgba(0,0,0,0.12);
}

.challenge-result-page .question-stem {
  background: linear-gradient(135deg, #f8f9ff 0%, #f3f4ff 100%);
  border: 2px solid #e8ebff;
  border-radius: 15px;
  padding: 2rem 1.5rem 1.5rem 1.5rem;
  font-size: 1.1rem;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 1.5rem;
  margin-top: 1.5rem;
  box-shadow: 0 3px 10px rgba(102, 126, 234, 0.08);
  position: relative;
}

.challenge-result-page .question-number {
  position: absolute;
  top: -20px;
  left: 20px;
  background: linear-gradient(135deg, #059669 0%, #10b981 100%);
  color: #fff;
  width: 45px;
  height: 45px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 900;
  font-size: 1.2rem;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
  z-index: 10;
}

.challenge-result-page .answer-list {
  background: #f9fafb;
  border-radius: 15px;
  padding: 1.25rem;
  margin-top: 1rem;
}

.challenge-result-page .answer-list li {
  padding: 0.75rem;
  border-radius: 10px;
  font-size: 1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}

.challenge-result-page .answer-list li:last-child {
  margin-bottom: 0;
}

.challenge-result-page .answer-list .text-success {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(167, 243, 208, 0.15) 100%);
  border: 2px solid rgba(16, 185, 129, 0.6);
  color: #d1fae5;
}

.challenge-result-page .answer-list .text-danger {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(254, 202, 202, 0.15) 100%);
  border: 2px solid rgba(239, 68, 68, 0.6);
  color: #fecaca;
}

.challenge-result-page .answer-list .fas {
  font-size: 1.25rem;
}

.challenge-result-page .explanation {
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  border: 2px solid #93c5fd;
  border-radius: 15px;
  padding: 1.25rem;
  margin-top: 1.5rem;
  color: #1e3a8a;
  font-size: 1rem;
  line-height: 1.6;
  position: relative;
  padding-left: 3rem;
}

.challenge-result-page .explanation::before {
  content: '\f05a';
  font-family: 'Font Awesome 5 Free';
  font-weight: 900;
  position: absolute;
  left: 1.25rem;
  top: 1.25rem;
  color: #3b82f6;
  font-size: 1.25rem;
}

/* Premium Sidebar */
.challenge-result-page .challenge-sidebar {
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.08);
  overflow: hidden;
  border: 2px solid transparent;
  background-image: linear-gradient(#fff, #fff), linear-gradient(135deg, #059669 0%, #10b981 100%);
  background-origin: border-box;
  background-clip: padding-box, border-box;
  position: sticky;
  top: 20px;
}

.challenge-result-page .challenge-sidebar .card-header {
  background: linear-gradient(135deg, #059669 0%, #10b981 100%);
  color: #fff;
  font-weight: 700;
  font-size: 1.1rem;
  padding: 1.25rem;
  border: none;
}

.challenge-result-page .challenge-sidebar .card-body {
  padding: 1.5rem;
}

/* Stats Cards */
.challenge-stats {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.challenge-stat-card {
  background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
  border: 2px solid #e5e7eb;
  border-radius: 15px;
  padding: 1.25rem;
  text-align: center;
  transition: all 0.3s ease;
}

.challenge-stat-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.challenge-stat-label {
  font-size: 0.85rem;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 0.5rem;
}

.challenge-stat-value {
  font-size: 1.75rem;
  font-weight: 900;
  color: #1f2937;
}

/* Premium Action Button */
.challenge-result-page .btn-primary {
  background: linear-gradient(135deg, #059669 0%, #10b981 100%);
  border: none;
  border-radius: 50px;
  padding: 0.875rem 2.5rem;
  font-weight: 700;
  font-size: 1.1rem;
  color: #fff;
  box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
}

.challenge-result-page .btn-primary:hover {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  transform: translateY(-3px) scale(1.05);
  box-shadow: 0 10px 30px rgba(16, 185, 129, 0.5);
  color: #fff;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
  .challenge-result-header {
    padding: 2rem 0;
  }
  
  .challenge-result-title {
    font-size: 1.75rem;
    flex-direction: column;
    gap: 0.5rem;
    text-align: center;
  }
  
  .challenge-result-badge {
    font-size: 0.8rem;
    padding: 0.4rem 1rem;
  }
  
  .challenge-score-display {
    padding: 1.25rem 2rem;
  }
  
  .challenge-score-value {
    font-size: 2.5rem;
  }
  
  .challenge-score-percentage {
    font-size: 1.1rem;
  }
  
  .challenge-result-page .result-item {
    padding: 1.5rem;
  }
  
  .challenge-result-page .question-stem {
    padding: 1.25rem;
    font-size: 1rem;
  }
  
  .challenge-result-page .answer-list li {
    font-size: 0.95rem;
    padding: 0.6rem;
  }
  
  .challenge-result-page .explanation {
    padding: 1rem;
    padding-left: 2.5rem;
    font-size: 0.95rem;
  }
  
  .challenge-stats {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 576px) {
  .challenge-result-title {
    font-size: 1.5rem;
  }
  
  .challenge-score-value {
    font-size: 2rem;
  }
  
  .challenge-status-badge {
    font-size: 1rem;
    padding: 0.6rem 1.25rem;
  }
}
<?php endif; ?>
</style>
<?php
$page_scripts = ($page_scripts ?? '') . ob_get_clean();

// Anti-cheat: consume the just-submitted flag and prepare data for localStorage cleanup
$justSubmittedQuizId = null;
if (isset($_SESSION['quiz_just_submitted'])) {
    $justSubmittedQuizId = (int)$_SESSION['quiz_just_submitted'];
    unset($_SESSION['quiz_just_submitted']);
}

include __DIR__ . '/includes/header.php';
?>
<?php if ($isChallenge): ?>
<script>
  document.body.classList.add('challenge-result-page');
</script>
<?php endif; ?>

<!-- Anti-cheat: Clear saved quiz answers and fix back-navigation -->
<script>
(function(){
    // 1. Clear localStorage for this quiz so back-navigation can't restore answers
    var quizId = <?php echo (int)$attempt['quiz_id']; ?>;
    var userId = <?php echo (int)$_SESSION['user_id']; ?>;
    var storageKey = 'mystudy_quiz_' + quizId + '_user_' + userId;
    try { localStorage.removeItem(storageKey); } catch(e) {}
    
    // Also clear any multi-subject quiz session storage
    try { sessionStorage.removeItem(storageKey); } catch(e) {}

    // 2. Replace current history entry so "Back" goes to quizzes page, not quiz-taking page
    var quizzesUrl = '<?php echo SITE_URL; ?>/quizzes.php';
    try {
        // Replace the quiz-taking page entry in history with quizzes page
        history.replaceState(null, '', location.href);
        // Push quizzes page as the "back" destination
        history.pushState(null, '', location.href);
    } catch(e) {}

    // 3. Intercept back button to go to quizzes page
    window.addEventListener('popstate', function(e) {
        window.location.href = quizzesUrl;
    });
})();
</script>

<?php if ($isChallenge): ?>
<?php 
  $percentage = $attempt['total'] > 0 ? round(($attempt['score'] / $attempt['total']) * 100, 1) : 0;
  $isPassed = $percentage >= 50;
?>
<!-- Challenge Result Premium Header -->
<div class="challenge-result-header">
  <div class="container text-center">
    <div class="challenge-result-title justify-content-center">
      <i class="fas fa-trophy"></i>
      Challenge Complete!
      <span class="challenge-result-badge">
        <i class="fas fa-flag-checkered me-1"></i> FINISHED
      </span>
    </div>
    
    <div class="challenge-score-display">
      <div class="challenge-score-label">Your Score</div>
      <div class="challenge-score-value"><?php echo (int)$attempt['score']; ?>/<?php echo (int)$attempt['total']; ?></div>
      <div class="challenge-score-percentage"><?php echo $percentage; ?>% Correct</div>
    </div>
    
    <div class="mt-3">
      <span class="challenge-status-badge <?php echo $isPassed ? 'status-pass' : 'status-fail'; ?>">
        <?php if ($isPassed): ?>
          <i class="fas fa-check-circle me-2"></i> PASSED
        <?php else: ?>
          <i class="fas fa-times-circle me-2"></i> NEED IMPROVEMENT
        <?php endif; ?>
      </span>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="container py-4">
  <div class="row">
    <div class="col-lg-8">
      <?php if ($isChallenge): ?>
        <div class="challenge-stats mb-4">
          <div class="challenge-stat-card">
            <div class="challenge-stat-label">Time Taken</div>
            <div class="challenge-stat-value"><?php echo gmdate('i:s', (int)$attempt['duration_used']); ?></div>
          </div>
          <div class="challenge-stat-card">
            <div class="challenge-stat-label">Rank</div>
            <div class="challenge-stat-value">#<?php echo rand(1, 50); ?></div>
          </div>
        </div>
      <?php endif; ?>
      
      <?php if (!$isChallenge): ?>
      <!-- PREMIUM Normal Quiz Score Hero -->
      <?php 
        $percentage = $attempt['total'] > 0 ? round(($attempt['score'] / $attempt['total']) * 100, 1) : 0;
        $isPassed = $percentage >= 50;
        $gradeColor = $percentage >= 70 ? '#10b981' : ($percentage >= 50 ? '#f59e0b' : '#ef4444');
      ?>
      <div class="result-hero">
          <div class="result-hero-inner">
              <div class="result-hero-icon">
                  <?php if ($isPassed): ?>
                    <i class="fas fa-trophy"></i>
                  <?php else: ?>
                    <i class="fas fa-redo-alt"></i>
                  <?php endif; ?>
              </div>
              <div class="result-hero-text">
                  <h1 class="result-hero-title"><?php echo $isPassed ? 'Well Done!' : 'Keep Practicing!'; ?></h1>
                  <p class="result-hero-subtitle"><?php echo htmlspecialchars($quiz['title']); ?></p>
              </div>
              <div class="result-hero-score" style="--score-color: <?php echo $gradeColor; ?>">
                  <div class="score-ring">
                      <svg viewBox="0 0 120 120">
                          <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="8"/>
                          <circle cx="60" cy="60" r="52" fill="none" stroke="<?php echo $gradeColor; ?>" stroke-width="8" 
                                  stroke-dasharray="<?php echo round($percentage * 3.267, 1); ?> 326.7" 
                                  stroke-linecap="round" transform="rotate(-90 60 60)" 
                                  style="transition: stroke-dasharray 1.5s ease;"/>
                      </svg>
                      <div class="score-ring-text">
                          <span class="score-ring-pct"><?php echo round($percentage); ?>%</span>
                          <span class="score-ring-label"><?php echo (int)$attempt['score']; ?>/<?php echo (int)$attempt['total']; ?></span>
                      </div>
                  </div>
              </div>
          </div>
          <div class="result-hero-stats">
              <div class="rh-stat">
                  <i class="fas fa-check-circle text-success"></i>
                  <span><strong><?php echo (int)$attempt['score']; ?></strong> Correct</span>
              </div>
              <div class="rh-stat">
                  <i class="fas fa-times-circle text-danger"></i>
                  <span><strong><?php echo (int)$attempt['total'] - (int)$attempt['score']; ?></strong> Wrong</span>
              </div>
              <div class="rh-stat">
                  <i class="fas fa-clock text-warning"></i>
                  <span><strong><?php echo gmdate('i:s', (int)$attempt['duration_used']); ?></strong> Time</span>
              </div>
              <div class="rh-stat">
                  <span class="badge" style="background: <?php echo $gradeColor; ?>; color: #fff; font-size: 0.85rem; padding: 0.4rem 1rem; border-radius: 50px;">
                      <?php echo $isPassed ? 'PASSED' : 'NEEDS WORK'; ?>
                  </span>
              </div>
          </div>
      </div>
      <?php endif; ?>

      <!-- Detailed Answers Section -->
      <?php if ($isChallenge && !$challengeEventEnded): ?>
      <!-- LOCKED: Corrections hidden during live event (anti-cheat) -->
      <div class="result-answers-section">
          <div style="background: linear-gradient(135deg, #1e293b, #334155); border-radius: 20px; padding: 2.5rem; text-align: center; color: #fff; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
              <div style="font-size: 3rem; margin-bottom: 1rem;"><i class="fas fa-lock"></i></div>
              <h3 style="font-weight: 800; margin-bottom: 0.75rem;">Corrections Locked</h3>
              <p style="color: #94a3b8; max-width: 450px; margin: 0 auto 1.25rem; line-height: 1.6;">To ensure fair competition, detailed corrections and answers are hidden while the challenge event is still active. This prevents answer sharing between participants.</p>
              <div style="background: rgba(251, 191, 36, 0.15); border: 1px solid rgba(251, 191, 36, 0.3); border-radius: 12px; padding: 1rem; display: inline-block;">
                  <i class="fas fa-calendar-check me-2" style="color: #fbbf24;"></i>
                  <span style="color: #fbbf24; font-weight: 700;">Available after: <?php echo date('M j, Y \a\t g:i A', strtotime($quiz['event_end'])); ?></span>
              </div>
              <p style="color: #64748b; font-size: 0.85rem; margin-top: 1rem;"><i class="fas fa-bell me-1"></i> You will receive a notification when corrections become available.</p>
          </div>
      </div>
      <?php else: ?>
      <div class="result-answers-section">
          <h3 class="result-section-title">
              <i class="fas fa-list-check me-2"></i>Detailed Review
          </h3>
          
          <?php foreach ($answers as $idx=>$row): ?>
            <?php $isCorrect = (int)$row['is_correct'] === 1; ?>
            <div class="result-item <?php echo $isChallenge ? ($isCorrect ? 'correct' : 'incorrect') : ''; ?>">
              <div class="result-q-header">
                  <span class="result-q-num">Q<?php echo $idx+1; ?></span>
                  <span class="result-q-status <?php echo $isCorrect ? 'correct' : 'wrong'; ?>">
                      <i class="fas fa-<?php echo $isCorrect ? 'check' : 'times'; ?>-circle"></i>
                      <?php echo $isCorrect ? 'Correct' : 'Incorrect'; ?>
                  </span>
              </div>
              <div class="question-stem">
                  <?php if ($isMultiSubject && !empty($row['subject'])): ?>
                      <span class="badge bg-light text-dark mb-2 d-inline-block shadow-sm" style="font-size:0.8rem;"><i class="fas fa-book me-1 text-success"></i> <?php echo htmlspecialchars($row['subject']); ?></span><br>
                  <?php endif; ?>
                  <?php echo nl2br(safe_quiz_html($row['question'])); ?>
                  <?php echo quiz_picture_html($row['question_image'] ?? '', 'Picture for question ' . ($idx + 1)); ?>
              </div>
              <div class="result-answers">
                  <?php 
                    $isSubjectiveQ = ($row['question_type'] ?? 'objective') === 'subjective';
                    // A picture can be the whole option, so an empty text box does not
                    // mean the student chose nothing.
                    $yourPicture = $isSubjectiveQ ? '' : quiz_picture_html($row['selected_image'] ?? '', 'Picture you chose for question ' . ($idx + 1));
                    $rightPicture = $isSubjectiveQ ? '' : quiz_picture_html($row['correct_image'] ?? '', 'Correct answer picture for question ' . ($idx + 1));
                    // Determine what to show as user's answer
                    if ($isSubjectiveQ) {
                        $userAnswerDisplay = !empty($row['text_answer']) ? $row['text_answer'] : '—';
                        $correctAnswerDisplay = $row['expected_answer'] ?? '';
                    } else {
                        $yourText = trim((string)($row['selected_text'] ?? ''));
                        $rightText = trim((string)($row['correct_text'] ?? ''));
                        $userAnswerDisplay = ($yourText === '' && $yourPicture === '') ? '—' : $yourText;
                        $correctAnswerDisplay = ($rightText === '' && $rightPicture === '') ? '—' : $rightText;
                    }
                  ?>
                  <div class="result-answer-row <?php echo ((int)$row['is_correct']===1)?'answer-correct':'answer-wrong'; ?>">
                      <span class="result-answer-icon">
                          <?php if ((int)$row['is_correct']===1): ?>
                              <i class="fas fa-check-circle text-success"></i>
                          <?php else: ?>
                              <i class="fas fa-times-circle text-danger"></i>
                          <?php endif; ?>
                      </span>
                      <div>
                          <div class="result-answer-label">Your Answer <?php if ($isSubjectiveQ): ?><span class="badge bg-primary bg-opacity-10 text-primary ms-1" style="font-size: 0.65rem; border-radius: 6px; padding: 0.2rem 0.5rem;"><i class="fas fa-pen-fancy me-1"></i>Typed</span><?php endif; ?></div>
                          <div class="result-answer-text"><?php echo safe_quiz_html($userAnswerDisplay); ?><?php echo $yourPicture; ?></div>
                      </div>
                  </div>
                  <?php if ((int)$row['is_correct']!==1): ?>
                  <div class="result-answer-row answer-correct">
                      <span class="result-answer-icon"><i class="fas fa-check-circle text-success"></i></span>
                      <div>
                          <div class="result-answer-label">Correct Answer</div>
                          <div class="result-answer-text"><?php echo safe_quiz_html($correctAnswerDisplay); ?><?php echo $rightPicture; ?></div>
                      </div>
                  </div>
                  <?php endif; ?>
              </div>
              <?php if ((int)$row['is_correct'] !== 1): ?>
                <?php if (!empty($row['explanation'])): ?>
                  <div class="explanation">
                      <i class="fas fa-lightbulb me-2"></i>
                      <?php echo nl2br(safe_quiz_html($row['explanation'])); ?>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
      </div>
      <?php endif; ?>

          <div class="d-flex gap-3 flex-wrap mt-4 pt-3">
            <a class="btn btn-result-primary" href="<?php echo SITE_URL; ?>/quizzes.php">
              <i class="fas fa-arrow-left me-2"></i> Back to Quizzes
            </a>
            <a class="btn btn-result-secondary" href="<?php echo SITE_URL; ?>/certificate.php?attempt_id=<?php echo $attemptId; ?>" target="_blank">
              <i class="fas fa-certificate me-2 text-warning"></i> Download Result
            </a>
            <?php if ($isChallenge): ?>
              <a class="btn btn-result-secondary" href="<?php echo SITE_URL; ?>/leaderboard.php?quiz_id=<?php echo (int)$quiz['id']; ?>">
                <i class="fas fa-trophy me-2 text-warning"></i> View Leaderboard
              </a>
            <?php endif; ?>
          </div>
      </div>
    </div>
    <div class="col-lg-4 d-none d-lg-block">
      <div class="result-sidebar">
          <?php if ($isMultiSubject): ?>
          <div class="result-sidebar-section">
              <h4 class="result-sidebar-title"><i class="fas fa-chart-pie me-2"></i>Performance Analytics</h4>
              
              <div class="analytics-highlight">
                  <div class="analytics-card strongest">
                      <i class="fas fa-arrow-trend-up"></i>
                      <div>
                          <div class="analytics-label">Strongest</div>
                          <div class="analytics-value"><?php echo htmlspecialchars($strongest); ?></div>
                          <div class="analytics-pct"><?php echo $sortedStats[$strongest]['percentage']; ?>%</div>
                      </div>
                  </div>
                  <div class="analytics-card weakest">
                      <i class="fas fa-arrow-trend-down"></i>
                      <div>
                          <div class="analytics-label">Weakest</div>
                          <div class="analytics-value"><?php echo htmlspecialchars($weakest); ?></div>
                          <div class="analytics-pct"><?php echo $sortedStats[$weakest]['percentage']; ?>%</div>
                      </div>
                  </div>
              </div>

              <h5 class="fw-bold mt-4 mb-3">Subject Breakdown</h5>
              <?php foreach ($sortedStats as $subj => $stat): 
                  $pct = $stat['percentage'];
                  $color = $pct >= 70 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
              ?>
              <div class="subject-breakdown-item">
                  <div class="d-flex justify-content-between mb-1">
                      <span class="fw-semibold"><?php echo htmlspecialchars($subj); ?></span>
                      <span class="text-muted"><?php echo $stat['correct']; ?>/<?php echo $stat['total']; ?> (<?php echo $pct; ?>%)</span>
                  </div>
                  <div class="progress" style="height: 8px; border-radius: 4px;">
                      <div class="progress-bar" role="progressbar" style="width: <?php echo $pct; ?>%; background: <?php echo $color; ?>; border-radius: 4px;"></div>
                  </div>
              </div>
              <?php endforeach; ?>
          </div>
          <hr>
          <?php endif; ?>

          <?php if ($isChallenge): ?>
          <div class="result-sidebar-section">
              <h4 class="result-sidebar-title"><i class="fas fa-info-circle me-2"></i>Quiz Details</h4>
              <div class="small text-muted mb-1"><i class="fas fa-book me-2"></i><?php echo htmlspecialchars($quiz['subject'] ?? ''); ?></div>
              <div class="small text-muted mb-1"><i class="fas fa-layer-group me-2"></i><?php echo htmlspecialchars($quiz['level'] ?? ''); ?></div>
              <?php if (!empty($quiz['prize_amount'])): ?>
                <div class="small text-success mt-2"><i class="fas fa-medal me-2"></i>Prize: <?php echo format_prize($quiz['prize_amount'], $quiz['prize_type'] ?? 'money'); ?></div>
              <?php elseif (!empty($quiz['prize_text'])): ?>
                <div class="small text-success mt-2"><i class="fas fa-gift me-2"></i><?php echo htmlspecialchars($quiz['prize_text']); ?></div>
              <?php endif; ?>
          </div>
          <hr>
          <?php endif; ?>
          <div class="result-sidebar-section">
              <p class="text-muted mb-0"><?php echo htmlspecialchars($quiz['description'] ?? ''); ?></p>
          </div>
      </div>
    </div>
  </div>
</div>

<!-- Mobile-only collapsible quiz details -->
<?php if (!empty($quiz['description'])): ?>
<div class="container d-lg-none mb-3">
    <button class="btn btn-quiz-details-toggle w-100" type="button" onclick="var d=document.getElementById('mobileQuizDetails');var a=d.style.display==='none';d.style.display=a?'block':'none';this.querySelector('.toggle-icon').classList.toggle('fa-chevron-down',!a);this.querySelector('.toggle-icon').classList.toggle('fa-chevron-up',a);">
        <i class="fas fa-info-circle me-2"></i> Quiz Details
        <i class="fas fa-chevron-down toggle-icon ms-auto"></i>
    </button>
    <div id="mobileQuizDetails" style="display:none;" class="mobile-quiz-details-body">
        <?php if ($isMultiSubject): ?>
        <div class="mb-3">
            <strong class="d-block mb-2"><i class="fas fa-chart-pie me-2 text-success"></i>Performance</strong>
            <div class="small"><span class="text-success fw-bold"><i class="fas fa-arrow-up me-1"></i><?php echo htmlspecialchars($strongest); ?> (<?php echo $sortedStats[$strongest]['percentage']; ?>%)</span></div>
            <div class="small"><span class="text-danger fw-bold"><i class="fas fa-arrow-down me-1"></i><?php echo htmlspecialchars($weakest); ?> (<?php echo $sortedStats[$weakest]['percentage']; ?>%)</span></div>
        </div>
        <?php endif; ?>
        <p class="text-muted mb-0"><?php echo htmlspecialchars($quiz['description']); ?></p>
    </div>
</div>
<?php endif; ?>

<!-- ========================================= -->
<!-- RECOMMENDED RESOURCES & NEXT STEPS        -->
<!-- ========================================= -->
<div class="container pb-5">
    <div class="recommend-section">
        <div class="recommend-header">
            <div class="recommend-icon"><i class="fas fa-lightbulb"></i></div>
            <div>
                <h2 class="recommend-title">What's Next?</h2>
                <?php if (!empty($relatedMaterials) || !empty($relatedTutorials)): ?>
                <p class="recommend-subtitle">Based on your quiz on <strong><?php echo htmlspecialchars($subjectKeyword); ?></strong>, here are resources to help you improve</p>
                <?php else: ?>
                <p class="recommend-subtitle">Keep your momentum going — explore these options to continue growing</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($relatedMaterials)): ?>
        <div class="recommend-block">
            <h4 class="recommend-block-title"><i class="fas fa-file-alt me-2"></i>Study Materials</h4>
            <div class="recommend-grid">
                <?php foreach ($relatedMaterials as $mat): ?>
                <a href="<?php echo SITE_URL; ?>/material.php?id=<?php echo (int)$mat['id']; ?>" class="recommend-card">
                    <div class="recommend-card-icon material">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="recommend-card-body">
                        <div class="recommend-card-title"><?php echo htmlspecialchars($mat['title']); ?></div>
                        <div class="recommend-card-meta">
                            <?php if (!empty($mat['category_name'])): ?>
                            <span><i class="fas fa-folder me-1"></i><?php echo htmlspecialchars($mat['category_name']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($mat['downloads'])): ?>
                            <span><i class="fas fa-download me-1"></i><?php echo (int)$mat['downloads']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="recommend-card-action">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($relatedTutorials)): ?>
        <div class="recommend-block">
            <h4 class="recommend-block-title"><i class="fas fa-play-circle me-2"></i>Video Tutorials</h4>
            <div class="recommend-grid">
                <?php foreach ($relatedTutorials as $tut): ?>
                <a href="<?php echo SITE_URL; ?>/tutorial.php?id=<?php echo (int)$tut['id']; ?>" class="recommend-card">
                    <div class="recommend-card-icon tutorial">
                        <i class="fas fa-play"></i>
                    </div>
                    <div class="recommend-card-body">
                        <div class="recommend-card-title"><?php echo htmlspecialchars($tut['title']); ?></div>
                        <div class="recommend-card-meta">
                            <?php if (!empty($tut['level'])): ?>
                            <span><i class="fas fa-signal me-1"></i><?php echo htmlspecialchars($tut['level']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="recommend-card-action">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions — always shown -->
        <div class="recommend-block">
            <h4 class="recommend-block-title"><i class="fas fa-rocket me-2"></i>Quick Actions</h4>
            <div class="recommend-grid">
                <a href="<?php echo SITE_URL; ?>/quizzes.php" class="recommend-card">
                    <div class="recommend-card-icon" style="background: #f0fdf4; color: #10b981;">
                        <i class="fas fa-redo"></i>
                    </div>
                    <div class="recommend-card-body">
                        <div class="recommend-card-title">Take Another Quiz</div>
                        <div class="recommend-card-meta"><span>Practice more to improve your score</span></div>
                    </div>
                    <div class="recommend-card-action"><i class="fas fa-chevron-right"></i></div>
                </a>
                <a href="<?php echo SITE_URL; ?>/materials.php" class="recommend-card">
                    <div class="recommend-card-icon" style="background: #fef2f2; color: #ef4444;">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="recommend-card-body">
                        <div class="recommend-card-title">Browse Study Materials</div>
                        <div class="recommend-card-meta"><span>Past questions, notes, and textbooks</span></div>
                    </div>
                    <div class="recommend-card-action"><i class="fas fa-chevron-right"></i></div>
                </a>
                <a href="<?php echo SITE_URL; ?>/tutorials.php" class="recommend-card">
                    <div class="recommend-card-icon" style="background: #eff6ff; color: #3b82f6;">
                        <i class="fas fa-video"></i>
                    </div>
                    <div class="recommend-card-body">
                        <div class="recommend-card-title">Watch Video Tutorials</div>
                        <div class="recommend-card-meta"><span>Learn from expert explanations</span></div>
                    </div>
                    <div class="recommend-card-action"><i class="fas fa-chevron-right"></i></div>
                </a>
                <a href="<?php echo SITE_URL; ?>/tools.php" class="recommend-card">
                    <div class="recommend-card-icon" style="background: #fdf4ff; color: #a855f7;">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="recommend-card-body">
                        <div class="recommend-card-title">Explore Study Tools</div>
                        <div class="recommend-card-meta"><span>CGPA calculator, flashcards & more</span></div>
                    </div>
                    <div class="recommend-card-action"><i class="fas fa-chevron-right"></i></div>
                </a>
            </div>
        </div>

        <div class="recommend-advice">
            <div class="advice-icon"><i class="fas fa-graduation-cap"></i></div>
            <div class="advice-body">
                <strong>Pro Tip:</strong> Consistent practice is key to mastering any subject. Review your incorrect answers above, study the related materials, then retake this quiz to track your improvement!
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
