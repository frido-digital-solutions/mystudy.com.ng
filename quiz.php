<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Quiz.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = $_GET['mode'] ?? 'normal';

$quizModel = new Quiz($pdo);

if ($mode === 'multi') {
  if (empty($_SESSION['custom_quiz']) || time() > $_SESSION['custom_quiz']['expires']) {
    unset($_SESSION['custom_quiz']);
    set_flash_message('error', 'Custom quiz session expired or not found.');
    header('Location: ' . SITE_URL . '/quiz_multi.php');
    exit;
  }
  $customData = $_SESSION['custom_quiz'];
  $quiz = [
    'id' => $customData['quiz_id'],
    'title' => 'Custom Multi-Subject Quiz',
    'subject' => is_array($customData['subjects']) ? implode(', ', $customData['subjects']) : $customData['subjects'],
    'level' => 'All Levels',
    'duration_seconds' => $customData['duration'],
    'mode' => 'multi'
  ];
  $questions = $customData['questions'];
  $id = $quiz['id'];
}
else {
  $quiz = $id ? $quizModel->getById($id) : null;
  if (!$quiz) {
    set_flash_message('error', 'Quiz not found.');
    header('Location: ' . SITE_URL . '/quizzes.php');
    exit;
  }

  // Student-type restriction gate
  $quizType = $quiz['type'] ?? 'both';
  $userType = $_SESSION['user_type'] ?? '';
  if ($quizType !== 'both' && $quizType !== $userType) {
    set_flash_message('error', 'This quiz is restricted to ' . ucfirst($quizType) . ' students only.');
    header('Location: ' . SITE_URL . '/quizzes.php');
    exit;
  }

  // Level restriction gate
  $reqLevel = (int)($quiz['required_level'] ?? 0);
  if ($reqLevel > 0) {
    require_once __DIR__ . '/models/Gamification.php';
    $gamification = new Gamification($pdo);
    $userGamData = $gamification->getUserStats((int)($_SESSION['user_id'] ?? 0));
    $userLevel = (int)$userGamData['level'];
    if ($userLevel < $reqLevel) {
      set_flash_message('error', 'You must be Level ' . $reqLevel . ' or higher to access this quiz.');
      header('Location: ' . SITE_URL . '/quizzes.php');
      exit;
    }
  }

  // Payment gate: if quiz requires payment, check if user has paid or used access code
  if (!empty($quiz['is_paid'])) {
    require_once __DIR__ . '/models/Payment.php';
    require_once __DIR__ . '/models/AccessCode.php';
    $payCheck = new Payment($pdo);
    $codeCheck = new AccessCode($pdo);
    $userHasAccess = $payCheck->hasPaid((int)$_SESSION['user_id'], $id)
      || $codeCheck->hasUserUsedACodeForQuiz((int)$_SESSION['user_id'], $id);
    if (!$userHasAccess) {
      header('Location: ' . SITE_URL . '/quiz_special.php?id=' . $id);
      exit;
    }
  }

  // Prevent taking challenge/paid quiz more than once (anti-cheat)
  $isSpecialQuiz = !empty($quiz['is_paid']) || ($quiz['mode'] ?? 'normal') === 'challenge';
  if ($isSpecialQuiz) {
    require_once __DIR__ . '/models/QuizAttempt.php';
    $attemptModel = new QuizAttempt($pdo);
    if ($attemptModel->hasAttempted($id, (int)$_SESSION['user_id'])) {
      set_flash_message('info', 'You have already completed this quiz. Please check the leaderboard for your ranking.');
      header('Location: ' . SITE_URL . '/quiz_special.php?id=' . $id);
      exit;
    }
  }

  $questions = $quizModel->getQuestions($id);
}

// CSRF token
// Use global CSRF helper to avoid session structure conflicts
$csrf_token = csrf_token();

$displayLevelText = $quiz['level'] ?? '';
if ($displayLevelText === 'Secondary') {
    $et = array_key_exists('exam_types', $quiz) ? $quiz['exam_types'] : 'WAEC,NECO,JAMB';
    if ($et === null || $et === '') $et = 'WAEC,NECO,JAMB';
    $displayLevelText = str_replace(',', ', ', $et);
}

// Timer: store server start time per quiz to prevent refresh exploits
$duration = (int)($quiz['duration_seconds'] ?? 900);
$isChallenge = ($quiz['mode'] ?? 'normal') === 'challenge';
$isPaidQuiz = !empty($quiz['is_paid']);

// Per-user deterministic question + option shuffling for challenge/paid quizzes (anti-cheat)
if ($isChallenge || $isPaidQuiz) {
  $seed = crc32($id . '_' . (int)$_SESSION['user_id']);
  mt_srand($seed);
  // Fisher-Yates shuffle for questions
  for ($i = count($questions) - 1; $i > 0; $i--) {
    $j = mt_rand(0, $i);
    [$questions[$i], $questions[$j]] = [$questions[$j], $questions[$i]];
  }
  // Shuffle options within each question
  foreach ($questions as &$q) {
    if (!empty($q['options']) && count($q['options']) > 1) {
      $opts = $q['options'];
      for ($i = count($opts) - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$opts[$i], $opts[$j]] = [$opts[$j], $opts[$i]];
      }
      $q['options'] = array_values($opts);
    }
  }
  unset($q);
  $questions = array_values($questions);
  mt_srand(); // reset to random
}
$eventStartTs = $isChallenge && !empty($quiz['event_start']) ? strtotime($quiz['event_start']) : null;
$eventEndTs = $isChallenge && !empty($quiz['event_end']) ? strtotime($quiz['event_end']) : null;
$startKey = 'quiz_start_' . $id;
// If not started yet, page will show a pre-start modal and begin timer via AJAX
$hasStarted = !empty($_SESSION[$startKey]);
$elapsed = $hasStarted ? max(0, time() - (int)$_SESSION[$startKey]) : 0;
$remaining = max(0, $duration - $elapsed);
// Cap by event end if challenge and session exists
if ($isChallenge && $eventEndTs) {
  $now = time();
  $untilEnd = max(0, $eventEndTs - $now);
  if ($untilEnd < $remaining) {
    $remaining = $untilEnd;
  }
}

$page_title = htmlspecialchars($quiz['title']);

// Prepare modals and scripts to be injected at the end of the page (footer outputs $page_scripts)
ob_start();
?>
<style>
/* Fallback styles — ONLY applied when Bootstrap CSS is NOT loaded.
   The body gets class 'no-bs' via the script below when Bootstrap is absent. */
body.no-bs .modal { display: none; position: fixed; inset: 0; width: 100%; height: 100%; background: rgba(0,0,0,.45); align-items: center; justify-content: center; padding: 1rem; z-index: 1050; }
body.no-bs .modal.show { display: flex !important; opacity: 1 !important; }
body.no-bs .modal .modal-dialog { width: 100%; max-width: 520px; margin: 0 auto; }
body.no-bs .modal .modal-content { border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 10px 30px rgba(0,0,0,.15); overflow: hidden; background: #fff; }
body.no-bs .modal .modal-header { border-bottom: 1px solid #f0f0f0; }
body.no-bs .modal .modal-footer { border-top: 1px solid #f0f0f0; }
body.no-bs .btn { display: inline-block; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #ccc; background:#f8f9fa; color:#222; cursor:pointer; }
body.no-bs .btn-primary { background:#10b981; border-color:#10b981; color:#fff; }
body.no-bs .btn-outline-secondary { background:transparent; border-color:#6c757d; color:#6c757d; }
body.no-bs .btn-danger { background:#dc3545; border-color:#dc3545; color:#fff; }
body.no-bs .btn:disabled { opacity: .6; cursor:not-allowed; }
body.no-bs .badge { display:inline-block; padding:.35em .65em; border-radius:10rem; background:#eee; color:#333; }
body.no-bs .card { border:1px solid #e5e7eb; border-radius:10px; box-shadow: 0 2px 8px rgba(0,0,0,.04); }
body.no-bs .card-header { border-bottom:1px solid #eee; padding: .75rem 1rem; background:#fff; }
body.no-bs .card-body { padding: 1rem; }
body.no-bs .card-footer { padding: .75rem 1rem; }

/* ========================================= */
/* NORMAL QUIZ PROFESSIONAL GREEN THEME */
/* ========================================= */
body {
    background-color: #f8fafc;
}

/* Main Question Card */
.quiz-question { 
  background: #fff; 
  border: none; 
  border-radius: 16px; 
  padding: 0; 
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
  overflow: visible;
  margin-bottom: 1.25rem;
  margin-top: 1.5rem;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.quiz-question:hover {
  transform: translateY(-4px);
  box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08);
}

/* Question Stem - Distinct Container */
.quiz-question .question-stem { 
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border-radius: 16px 16px 0 0;
  padding: 1.25rem 1.5rem 1rem;
  margin-bottom: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #fff;
  line-height: 1.5;
  position: relative;
}

.quiz-question .question-stem::before {
  content: '\f059';
  font-family: 'Font Awesome 5 Free';
  font-weight: 900;
  position: absolute;
  top: -15px;
  left: 30px;
  background: #fff;
  color: #10b981;
  width: 45px;
  height: 45px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
  z-index: 2;
}

/* Options Container */
.quiz-question .options {
  padding: 1rem 1.5rem 1.5rem;
  background: #fff;
  border-radius: 0 0 16px 16px;
}

/* Individual Option Styling */
.quiz-question .form-check { 
  background: #f8fafc;
  border: 2px solid transparent;
  border-radius: 12px;
  padding: 0.85rem 1.25rem;
  margin-bottom: 0.6rem;
  transition: all 0.3s ease;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
}

.quiz-question .form-check:hover { 
  background: #f1f5f9;
  border-color: #cbd5e1;
  transform: translateX(5px);
}

.quiz-question .form-check.selected {
  background: #f0fdf4;
  border-color: #10b981;
  box-shadow: 0 8px 20px rgba(16, 185, 129, 0.15);
}

.quiz-question .form-check-input { 
  cursor: pointer;
  width: 24px;
  height: 24px;
  border: 2px solid #cbd5e1;
  margin-top: 0;
  margin-right: 1rem;
  transition: all 0.2s ease;
}

.quiz-question .form-check-input:checked {
  background-color: #10b981;
  border-color: #10b981;
}

.quiz-question .form-check-label { 
  cursor: pointer;
  font-size: 0.95rem;
  font-weight: 500;
  color: #334155;
  margin-left: 0;
  flex-grow: 1;
}

/* Question Navigator Styling */
#questionNav .nav-item {
  width: 32px;
  height: 32px;
  min-width: 32px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.85rem;
  background: #f8fafc;
  color: #64748b;
  border: 2px solid #e2e8f0;
  transition: all 0.2s ease;
  cursor: pointer;
  box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}

#questionNav .nav-item.active {
  transform: scale(1.1);
  box-shadow: 0 4px 15px rgba(16,185,129,0.3);
  border-color: #10b981;
  color: #10b981;
  z-index: 2;
}

#questionNav .nav-item:hover { border-color: #10b981; color: #10b981; }
#questionNav .nav-item.btn-success { color: #fff; background-color: #10b981; border-color: #10b981; }
#questionNav .nav-item.btn-success.active { box-shadow: 0 6px 20px rgba(16,185,129,0.4); transform: scale(1.15); border: 2px solid #059669; }

/* Keep navigator buttons compact and inline */
#questionNav { display: flex; flex-wrap: wrap; gap: 0.25rem; }
#questionNav .nav-item { flex: 0 0 auto; }

/* Mobile: compact grid navigator */
@media (max-width: 768px) {
  #questionNav .nav-item { 
    min-width: 38px; 
    height: 38px; 
    border-radius: 10px; 
    font-size: 0.85rem;
    padding: 0;
  }
  #questionNav { gap: 0.35rem; }
}

/* ========================================= */
/* PREMIUM QUIZ TOP BAR                      */
/* ========================================= */
.quiz-top-bar {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  padding: 1.25rem 0;
  margin-bottom: 0;
  position: relative;
  overflow: hidden;
}

.quiz-top-bar::before {
  content: '';
  position: absolute;
  top: -60%;
  right: -20%;
  width: 400px;
  height: 400px;
  background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 70%);
  pointer-events: none;
}

.quiz-top-inner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  position: relative;
  z-index: 1;
}

.quiz-top-title {
  font-size: 1.5rem;
  font-weight: 900;
  color: #fff;
  margin: 0 0 0.5rem 0;
  letter-spacing: -0.5px;
}

.quiz-top-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  color: #94a3b8;
  font-size: 0.9rem;
  font-weight: 500;
}

.quiz-top-meta i { color: #10b981; }
.quiz-meta-sep { color: #475569; margin: 0 0.25rem; }

.quiz-top-timer {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
  font-size: 2rem;
  font-weight: 900;
  padding: 0.75rem 2rem;
  border-radius: 16px;
  min-width: 140px;
  text-align: center;
  box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
  letter-spacing: 2px;
  font-variant-numeric: tabular-nums;
}

.quiz-top-timer.timer-yellow, .challenge-timer-display.timer-yellow {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
  box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4) !important;
}

.quiz-top-timer.timer-red, .challenge-timer-display.timer-red {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
  box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4) !important;
  animation: timerPulse 1s ease-in-out infinite !important;
}

@keyframes timerPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.03); }
}

/* Question stem enhancements */
.question-number-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  opacity: 0.7;
  margin-bottom: 0.75rem;
}

.question-text {
  font-size: 1.2rem;
  font-weight: 700;
  line-height: 1.7;
}

/* ========================================= */
/* PREMIUM QUIZ CONTROLS                     */
/* ========================================= */
.quiz-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 1rem;
  padding: 1rem 0;
  border-top: 1px solid #e2e8f0;
}

.quiz-controls-nav {
  display: flex;
  gap: 0.75rem;
}

.btn-quiz-nav {
  background: #fff;
  border: 2px solid #e2e8f0;
  border-radius: 50px;
  padding: 0.75rem 1.75rem;
  font-weight: 700;
  font-size: 0.95rem;
  color: #475569;
  transition: all 0.2s ease;
}

.btn-quiz-nav:hover {
  border-color: #10b981;
  color: #10b981;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(16,185,129,0.15);
}

.btn-quiz-nav:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  transform: none !important;
  box-shadow: none !important;
}

.btn-quiz-end {
  background: #dc2626 !important;
  background-color: #dc2626 !important;
  border: none !important;
  color: #fff !important;
  border-radius: 50px;
  padding: 0.75rem 2rem;
  font-weight: 700;
  font-size: 1rem;
  color: #fff;
  box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
  transition: all 0.3s ease;
}

.btn-quiz-end:hover {
  transform: translateY(-3px) scale(1.02);
  box-shadow: 0 10px 30px rgba(239, 68, 68, 0.4);
  background: #b91c1c !important;
  background-color: #b91c1c !important;
  color: #fff !important;
}

/* ========================================= */
/* PREMIUM QUIZ SIDEBAR                      */
/* ========================================= */
.quiz-sidebar-card {
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.05);
  overflow: hidden;
  border: none;
  position: sticky;
  top: 1rem;
}

.quiz-sidebar-header {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
  font-weight: 700;
  font-size: 1rem;
  padding: 1.25rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.quiz-sidebar-body {
  padding: 1rem;
}

.quiz-sidebar-legend {
  border-top: 1px solid #f1f5f9;
  padding-top: 0.75rem;
  display: flex;
  gap: 1rem;
  font-size: 0.8rem;
  color: #64748b;
  font-weight: 500;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.legend-dot {
  width: 14px;
  height: 14px;
  border-radius: 4px;
}

.legend-dot.answered { background: #10b981; }
.legend-dot.unanswered { background: #e2e8f0; }

/* ========================================= */
/* RESPONSIVE QUIZ LAYOUT                    */
/* ========================================= */
@media (max-width: 768px) {
  /* Ultra-compact sticky header for mobile to maximize screen real estate */
  .quiz-top-bar {
    position: sticky;
    top: 0;
    z-index: 1040; /* Reduced below modal z-index (1050/1055) to unblock start modal */
    padding: 0.6rem 0 !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08); /* slight shadow for distinction */
  }
  .quiz-top-inner { 
    flex-direction: row !important; /* Force side-by-side */
    justify-content: space-between !important;
    align-items: center !important;
    gap: 0.5rem; 
    text-align: left !important;
  }
  .quiz-top-info {
    flex: 1;
    min-width: 0; /* allows truncation */
  }
  .quiz-top-title { 
    display: none !important; /* Hide title completely to save extreme vertical space */
  }
  .quiz-top-meta { 
    justify-content: flex-start !important; 
    flex-wrap: nowrap !important;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    white-space: nowrap;
    padding-bottom: 2px;
    font-size: 0.8rem !important;
    gap: 0.4rem !important;
  }
  /* Hide scrollbar for cleaner look */
  .quiz-top-meta::-webkit-scrollbar { display: none; }
  
  .quiz-top-timer { 
    font-size: 1.15rem !important; 
    padding: 0.35rem 0.65rem !important; 
    min-width: auto !important; 
    border-radius: 8px !important;
    box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2) !important;
    letter-spacing: 0px !important;
    flex-shrink: 0;
  }

  /* Mobile: keep navigation always visible (Battle-style) */
  body { padding-bottom: 120px; }
  .quiz-controls {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    margin-top: 0;
    padding: 0.6rem 0.75rem;
    border-top: 1px solid #e2e8f0;
    background: rgba(248, 250, 252, 0.95);
    backdrop-filter: blur(10px);
    box-shadow: 0 -10px 30px rgba(0,0,0,0.06);
    flex-direction: column;
    gap: 0.5rem;
  }
  .quiz-controls-nav { width: 100%; gap: 0.5rem; }
  .quiz-controls-nav .btn-quiz-nav { flex: 1; text-align: center; }
  .btn-quiz-end { width: 100%; }

  /* Mobile: enforce compact question navigator grid */
  #questionNav { flex-direction: row !important; gap: 0.35rem; }
  #questionNav .nav-item { width: 34px !important; min-width: 34px !important; height: 34px !important; padding: 0 !important; font-size: 0.8rem !important; border-radius: 8px !important; }

  /* Mobile: make the quiz content fit natively on a small screen */
  .quiz-question {
    /* Scroll target should land below compact sticky header */
    scroll-margin-top: 65px;
    max-height: calc(100vh - 65px - 110px); 
    display: flex;
    flex-direction: column;
    overflow: hidden;
    margin-bottom: 0.5rem !important;
    margin-top: 0.5rem !important;
    border-radius: 16px !important;
  }
  .quiz-question .question-stem {
    padding: 1rem 1.15rem 0.75rem !important;
    position: sticky;
    top: 0;
    z-index: 2;
  }
  .quiz-question .question-stem::before {
    display: none !important; /* hide huge overlapping icon to preserve space */
  }
  .question-number-label {
    margin-bottom: 0.25rem !important;
    font-size: 0.75rem !important;
  }
  .question-text {
    font-size: 1.05rem !important; /* tighter text */
    line-height: 1.4 !important;
  }
  
  .quiz-question .options {
    padding: 0.5rem 1.15rem 1rem !important;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    flex: 1 1 auto;
  }
  .quiz-question .form-check { 
    padding: 0.75rem 0.85rem !important; 
    margin-bottom: 0.5rem !important;
    border-radius: 12px !important;
  }
  .quiz-question .form-check-label { font-size: 0.95rem !important; }
  .quiz-question .form-check-input {
    width: 20px !important;
    height: 20px !important;
    margin-right: 0.75rem !important;
  }

  /* Mobile: slightly smaller controls for more screen space */
  .btn-quiz-nav { padding: 0.5rem 0.8rem !important; font-size: 0.9rem !important; }
  .btn-quiz-end { padding: 0.5rem 1rem !important; font-size: 0.95rem !important; }
}


<?php if ($isChallenge): ?>
/* ========================================= */
/* CHALLENGE QUIZ PREMIUM DESIGN */
/* ========================================= */

/* Challenge Background */
body.challenge-quiz-page {
  background: linear-gradient(135deg, #667eea15 0%, #764ba215 50%, #f093fb15 100%);
  min-height: 100vh;
}

/* Challenge Header Banner */
.challenge-quiz-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
  padding: 1.25rem 0;
  margin-bottom: 1.5rem;
  box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
  position: relative;
  overflow: hidden;
}

.challenge-quiz-header::before {
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

.challenge-quiz-title {
  color: #fff;
  font-size: 2rem;
  font-weight: 800;
  text-shadow: 0 2px 10px rgba(0,0,0,0.2);
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  position: relative;
  z-index: 1;
}

.challenge-badge {
  background: rgba(255,255,255,0.25);
  padding: 0.5rem 1rem;
  border-radius: 25px;
  font-size: 0.85rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,0.3);
}

.challenge-subtitle {
  color: rgba(255,255,255,0.95);
  font-size: 1.1rem;
  font-weight: 500;
  position: relative;
  z-index: 1;
}

/* Premium Challenge Timer */
.challenge-timer-display {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
  padding: 1rem 2rem;
  border-radius: 50px;
  font-size: 1.75rem;
  font-weight: 900;
  box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
  border: 3px solid rgba(255,255,255,0.3);
  min-width: 180px;
  text-align: center;
}

@keyframes pulse-urgent {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
}

/* Leaderboard Button */
.btn-leaderboard {
  background: rgba(255,255,255,0.15);
  backdrop-filter: blur(10px);
  color: #fff;
  padding: 0.875rem 1.75rem;
  border-radius: 50px;
  border: 2px solid rgba(255,255,255,0.3);
  font-weight: 700;
  font-size: 1rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.btn-leaderboard:hover {
  background: rgba(255,255,255,0.25);
  border-color: rgba(255,255,255,0.5);
  color: #fff;
  transform: translateY(-3px) scale(1.05);
  box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

.btn-leaderboard i {
  font-size: 1.1rem;
}

/* Premium Question Cards for Challenges */
.challenge-quiz-page .quiz-question {
  background: #fff;
  border: none;
  border-radius: 16px;
  padding: 0;
  box-shadow: 0 10px 40px rgba(0,0,0,0.05);
  position: relative;
  overflow: visible;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  margin-bottom: 1.25rem;
  margin-top: 1.5rem;
}

.challenge-quiz-page .quiz-question::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
}

.challenge-quiz-page .quiz-question:hover {
  transform: translateY(-4px);
  box-shadow: 0 15px 50px rgba(0,0,0,0.1);
}

.challenge-quiz-page .question-stem {
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  border: none;
  border-radius: 0;
  padding: 1.25rem 1.5rem 1rem;
  font-size: 1.05rem;
  font-weight: 700;
  color: #fff;
  margin-bottom: 0;
  position: relative;
  line-height: 1.5;
}

.challenge-quiz-page .question-stem::before {
  content: '\f059';
  font-family: 'Font Awesome 5 Free';
  font-weight: 900;
  position: absolute;
  top: -15px;
  left: 30px;
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  width: 45px;
  height: 45px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: #fff;
  box-shadow: 0 4px 15px rgba(245, 87, 108, 0.4);
}

.challenge-quiz-page .options {
  padding: 1rem 1.5rem 1.5rem;
  background: #fff;
  border-radius: 0 0 16px 16px;
}

.challenge-quiz-page .form-check {
  background: #f8fafc;
  border: 2px solid transparent;
  border-radius: 12px;
  padding: 0.85rem 1.25rem;
  margin-bottom: 0.6rem;
  transition: all 0.3s ease;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
}

.challenge-quiz-page .form-check:hover {
  border-color: #c7d2fe;
  background: #f1f5f9;
  transform: translateX(5px);
}

.challenge-quiz-page .form-check.selected {
  background: #eef2ff;
  border-color: #6366f1;
  box-shadow: 0 8px 20px rgba(99, 102, 241, 0.15);
}

.challenge-quiz-page .form-check-input {
  cursor: pointer;
  width: 24px;
  height: 24px;
  border: 2px solid #cbd5e1;
  margin-top: 0;
  margin-right: 1rem;
  transition: all 0.2s ease;
}

.challenge-quiz-page .form-check-input:checked {
  background-color: #6366f1;
  border-color: #6366f1;
}

.challenge-quiz-page .form-check-label {
  font-size: 0.95rem;
  font-weight: 500;
  color: #334155;
  margin-left: 0;
  flex-grow: 1;
  cursor: pointer;
}

/* Premium Navigation Buttons */
.challenge-quiz-page #questionNav .nav-item {
  background: #fff;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  padding: 0;
  min-width: 32px;
  width: 32px;
  height: 32px;
  font-weight: 700;
  font-size: 0.85rem;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.challenge-quiz-page #questionNav .nav-item:hover {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  border-color: #667eea;
  transform: scale(1.1);
}

.challenge-quiz-page #questionNav .nav-item.btn-success {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border-color: #10b981;
  color: #fff;
}

/* Premium Control Buttons */
.challenge-quiz-page .btn-group .btn {
  border-radius: 50px !important;
  padding: 0.75rem 2rem;
  font-weight: 700;
  font-size: 1rem;
  transition: all 0.3s ease;
  border: 2px solid;
}

.challenge-quiz-page .btn-group .btn:first-child {
  border-radius: 50px !important;
  border-top-right-radius: 50px !important;
  border-bottom-right-radius: 50px !important;
}

.challenge-quiz-page .btn-group .btn:last-child {
  border-radius: 50px !important;
  border-top-left-radius: 50px !important;
  border-bottom-left-radius: 50px !important;
}

.challenge-quiz-page .btn-group .btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.challenge-quiz-page #btnEnd {
  background: #dc2626 !important;
  background-color: #dc2626 !important;
  border: none !important;
  color: #fff !important;
  border-radius: 50px;
  padding: 0.75rem 2.5rem;
  font-weight: 700;
  font-size: 1.1rem;
  color: #fff;
  box-shadow: 0 6px 20px rgba(245, 87, 108, 0.4);
  transition: all 0.3s ease;
}

.challenge-quiz-page #btnEnd:hover {
  background: #b91c1c !important;
  background-color: #b91c1c !important;
  transform: translateY(-3px) scale(1.05);
  box-shadow: 0 10px 30px rgba(245, 87, 108, 0.5);
  color: #fff !important;
}

/* Premium Sidebar */
.challenge-quiz-page .challenge-sidebar {
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.08);
  overflow: hidden;
  border: 2px solid transparent;
  background-image: linear-gradient(#fff, #fff), linear-gradient(135deg, #667eea 0%, #f093fb 100%);
  background-origin: border-box;
  background-clip: padding-box, border-box;
}

.challenge-quiz-page .challenge-sidebar .card-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  font-weight: 700;
  font-size: 1.1rem;
  padding: 1.25rem;
  border: none;
}

/* Premium Modals for Challenges */
.challenge-quiz-page .modal-content {
  border-radius: 25px;
  border: none;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.challenge-quiz-page .modal-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  border: none;
  padding: 1.5rem 2rem;
}

.challenge-quiz-page .modal-title {
  font-weight: 800;
  font-size: 1.5rem;
}

.challenge-quiz-page .modal-body {
  padding: 2rem;
  font-size: 1.05rem;
}

.challenge-quiz-page .modal-footer {
  padding: 1.5rem 2rem;
  border: none;
  background: #f8f9fa;
}

/* Mobile Responsiveness for Challenge */
@media (max-width: 768px) {
  .challenge-quiz-header {
    padding: 0.6rem 0 !important;
    margin-bottom: 0.5rem !important;
    position: sticky;
    top: 0;
    z-index: 1040; /* Reduced to ensure modals show properly */
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
  }
  
  .challenge-quiz-header .container > .d-flex {
    flex-direction: row !important; /* Force inline side-by-side */
    justify-content: space-between !important;
    align-items: center !important;
    gap: 0.5rem !important;
  }
  
  .challenge-quiz-header .container > .d-flex > div:first-child {
    flex: 1;
    min-width: 0; 
  }
  
  .challenge-quiz-title {
    display: none !important; /* Hide giant title to maximize vertical space */
  }
  
  .challenge-subtitle {
    font-size: 0.8rem !important;
    text-align: left !important;
    margin: 0 !important;
    white-space: nowrap !important;
    overflow-x: auto !important;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding-bottom: 2px;
  }
  .challenge-subtitle::-webkit-scrollbar { display: none; }
  
  .challenge-timer-display {
    font-size: 1.15rem !important;
    padding: 0.35rem 0.65rem !important;
    min-width: auto !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 10px rgba(245, 87, 108, 0.3) !important;
    letter-spacing: 0px !important;
    flex-shrink: 0;
  }
  
  .btn-leaderboard {
    display: none !important; /* Hide leaderboard button during quiz on mobile to save space */
  }
  
  .challenge-quiz-page .quiz-question {
    padding: 0 !important; /* Base layout handles inner padding */
    margin-bottom: 0.5rem !important;
    margin-top: 0.5rem !important;
    border-radius: 16px !important;
  }
  
  .challenge-quiz-page .question-stem {
    font-size: 1.05rem !important;
    padding: 1rem 1.15rem 0.75rem !important;
  }
  
  .challenge-quiz-page .question-stem::before {
    display: none !important; /* Hide large overlapping icon */
  }
  
  .challenge-quiz-page .options {
    padding: 0.5rem 1.15rem 1rem !important;
  }
  
  .challenge-quiz-page .form-check {
    padding: 0.75rem 0.85rem !important;
    margin-bottom: 0.5rem !important;
    border-radius: 12px !important;
  }
  
  .challenge-quiz-page .form-check-label {
    font-size: 0.95rem !important;
  }
  
  .challenge-quiz-page #questionNav .nav-item {
    min-width: 34px !important;
    height: 34px !important;
    font-size: 0.8rem !important;
    padding: 0 !important;
    border-radius: 8px !important;
  }
  
  .challenge-quiz-page .btn-group .btn,
  .challenge-quiz-page .btn-quiz-nav {
    padding: 0.5rem 0.8rem !important;
    font-size: 0.9rem !important;
  }
  
  .challenge-quiz-page #btnEnd {
    padding: 0.5rem 1rem !important;
    font-size: 0.95rem !important;
  }
}

@media (max-width: 576px) {
  /* Inherits optimal compact sizes from 768px naturally */
}
<?php
endif; ?>
/* ========================================= */
/* SPECIAL PAID QUIZ — DARK/GOLD THEME       */
/* ========================================= */
body.special-quiz-page {
    background: linear-gradient(135deg, #0c0f1a 0%, #141827 50%, #0c0f1a 100%);
    min-height: 100vh;
}

body.special-quiz-page .quiz-top-bar {
    background: linear-gradient(135deg, #1a1f35 0%, #0f172a 100%);
    border-bottom: 2px solid rgba(251, 191, 36, 0.2);
}

body.special-quiz-page .quiz-top-bar::before {
    background: radial-gradient(circle, rgba(251, 191, 36, 0.08) 0%, transparent 70%);
}

body.special-quiz-page .quiz-top-bar h1,
body.special-quiz-page .quiz-top-bar .quiz-title-text {
    color: #fbbf24;
}

body.special-quiz-page .quiz-timer {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.15) 0%, rgba(245, 158, 11, 0.1) 100%);
    border: 1px solid rgba(251, 191, 36, 0.3);
}

body.special-quiz-page .quiz-timer .timer-display {
    color: #fbbf24;
}

body.special-quiz-page .quiz-question {
    background: linear-gradient(135deg, #1e2340 0%, #1a1f35 100%);
    border: 1px solid rgba(251, 191, 36, 0.15);
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}

body.special-quiz-page .quiz-question .question-stem {
    background: linear-gradient(135deg, #252b48 0%, #1e2340 100%);
    color: #e2e8f0;
    border-color: rgba(251, 191, 36, 0.2);
}

body.special-quiz-page .quiz-question .question-icon {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #0f172a;
}

body.special-quiz-page .quiz-question .options {
    background: rgba(15, 23, 42, 0.5);
}

body.special-quiz-page .quiz-question .form-check {
    background: #1a1f35;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    transition: all 0.2s ease;
}

body.special-quiz-page .quiz-question .form-check:hover {
    border-color: rgba(251, 191, 36, 0.3);
    background: #252b48;
}

body.special-quiz-page .quiz-question .form-check-label {
    color: #cbd5e1;
}

body.special-quiz-page .quiz-question .form-check-input:checked ~ .form-check-label {
    color: #fbbf24;
    font-weight: 700;
}

body.special-quiz-page .quiz-question .form-check-input:checked {
    background-color: #fbbf24;
    border-color: #fbbf24;
}

body.special-quiz-page .quiz-question .form-check:has(.form-check-input:checked) {
    border-color: #fbbf24;
    background: rgba(251, 191, 36, 0.08);
    box-shadow: 0 0 15px rgba(251, 191, 36, 0.1);
}

body.special-quiz-page #questionNav .nav-item {
    background: #1a1f35;
    border-color: rgba(255,255,255,0.1);
    color: #94a3b8;
}

body.special-quiz-page #questionNav .nav-item:hover {
    border-color: #fbbf24;
    color: #fbbf24;
}

body.special-quiz-page #questionNav .nav-item.btn-success {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    border-color: #fbbf24;
    color: #0f172a;
    box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
}

body.special-quiz-page .quiz-sidebar-card {
    background: #1a1f35;
    border: 1px solid rgba(251, 191, 36, 0.15);
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

body.special-quiz-page .quiz-sidebar-header {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.15) 0%, rgba(245, 158, 11, 0.08) 100%);
    color: #fbbf24;
}

body.special-quiz-page .quiz-nav-label {
    color: #94a3b8;
}

body.special-quiz-page .btn-quiz-submit {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    color: #0f172a;
    font-weight: 800;
    box-shadow: 0 6px 20px rgba(251, 191, 36, 0.3);
}

body.special-quiz-page .btn-quiz-submit:hover {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(251, 191, 36, 0.4);
    color: #0f172a;
}

/* Special quiz label in top bar */
.special-quiz-label {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(251, 191, 36, 0.15);
    border: 1px solid rgba(251, 191, 36, 0.3);
    color: #fbbf24;
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ========================================= */
/* SUBJECTIVE TEXT INPUT — PREMIUM STYLES    */
/* ========================================= */
.subjective-answer-wrap {
    padding: 0.5rem 0;
}
.subjective-input:focus {
    border-color: #6366f1 !important;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12), 0 4px 16px rgba(99, 102, 241, 0.08) !important;
    background: #fff !important;
    outline: none;
}
.subjective-input::placeholder {
    color: #94a3b8;
    font-weight: 400;
}
/* Dark theme variant for Special & Challenge quizzes */
body.special-quiz-page .subjective-input,
body.challenge-quiz-page .subjective-input {
    background: linear-gradient(135deg, #1e2340 0%, #1a1f35 100%) !important;
    border-color: rgba(255,255,255,0.12) !important;
    color: #e2e8f0 !important;
}
body.special-quiz-page .subjective-input:focus {
    border-color: #fbbf24 !important;
    box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.12), 0 4px 16px rgba(251, 191, 36, 0.08) !important;
}
body.challenge-quiz-page .subjective-input:focus {
    border-color: #10b981 !important;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12), 0 4px 16px rgba(16, 185, 129, 0.08) !important;
}
@media (max-width: 576px) {
    .subjective-input {
        font-size: 0.95rem !important;
        padding: 0.75rem 1rem !important;
        border-radius: 12px !important;
    }
}
</style>
<?php
?>
<!-- Pre-start Modal -->
<div class="modal fade" id="startModal" tabindex="-1" aria-hidden="true" style="display:none;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <?php if ($isPaidQuiz): ?>
        <h5 class="modal-title text-warning" style="font-weight:800;"><i class="fas fa-star text-warning me-2"></i>Special Quiz Entry</h5>
        <?php
else: ?>
        <h5 class="modal-title">Ready to start?</h5>
        <?php
endif; ?>
        <button type="button" class="btn-close" id="btnCancelStart" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($isPaidQuiz): ?>
        <div class="alert alert-warning mb-3" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.4); color: #b45309; border-radius: 8px;">
            <i class="fas fa-exclamation-triangle me-2"></i><strong>One-Time Entry Only!</strong><br>
            You can only take this special quiz once. Ensure you have a stable internet connection and are fully prepared before continuing.
        </div>
        <?php
endif; ?>
        <div class="mb-2"><strong>Subject:</strong> <?php echo htmlspecialchars($quiz['subject'] ?? ''); ?></div>
        <div class="mb-2"><strong>Level:</strong> <?php echo htmlspecialchars($displayLevelText); ?></div>
        <div class="mb-2"><strong>Duration:</strong> <?php echo ceil($duration / 60); ?> mins</div>
        <div class="mb-2"><strong>Questions:</strong> <?php echo count($questions); ?></div>
        <div class="text-muted">Click "Ready to Start" to begin the timer.</div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" id="btnCancelStartFooter">Cancel</button>
        <button type="button" class="btn btn-primary" id="btnReady">
            <span class="btn-text">Ready to Start</span>
            <span class="btn-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
        </button>
      </div>
    </div>
  </div>
 </div>

<!-- End Confirmation Modal -->
<div class="modal fade" id="endModal" tabindex="-1" aria-hidden="true" style="display:none;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title">Submit Quiz?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="unansweredSummary" class="mb-2"></div>
        <div class="text-muted">You can cancel to continue or confirm to submit.</div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmSubmit">Submit</button>
      </div>
    </div>
</div>


<script>
(function(){
  var total = <?php echo (int)count($questions); ?>;
  var remaining = <?php echo (int)$remaining; ?>; // seconds
  var duration = <?php echo (int)$duration; ?>;
  var hasStarted = <?php echo $hasStarted ? 'true' : 'false'; ?>;
  var isChallenge = <?php echo $isChallenge ? 'true' : 'false'; ?>;
  var eventStartTs = <?php echo $eventStartTs ? (int)$eventStartTs : 'null'; ?>;
  var eventEndTs = <?php echo $eventEndTs ? (int)$eventEndTs : 'null'; ?>;
  var timerEl = document.getElementById('timer');
  var cur = 0;
  var qEls = Array.prototype.slice.call(document.querySelectorAll('.quiz-question'));
  var navBtns = Array.prototype.slice.call(document.querySelectorAll('#questionNav .nav-item'));
  var formatter = new Intl.NumberFormat('en-US', {minimumIntegerDigits: 2});
  var hasBootstrap = (typeof window.bootstrap !== 'undefined');
  // If Bootstrap is NOT loaded, add 'no-bs' class so fallback CSS activates
  if (!hasBootstrap) { document.body.classList.add('no-bs'); }
  var startModalEl = document.getElementById('startModal');
  var endModalEl = document.getElementById('endModal');
  var startModal = hasBootstrap ? new bootstrap.Modal(startModalEl, {backdrop: 'static', keyboard: false}) : null;
  var endModal = hasBootstrap ? new bootstrap.Modal(endModalEl) : null;
  function showModal(el, modal){ if (hasBootstrap && modal) { modal.show(); } else { el.classList.add('show'); el.style.display = 'flex'; el.setAttribute('aria-hidden','false'); document.body.style.overflow = 'hidden'; } }
  function hideModal(el, modal){ if (hasBootstrap && modal) { modal.hide(); } else { el.classList.remove('show'); el.style.display = 'none'; el.setAttribute('aria-hidden','true'); document.body.style.overflow = ''; } }
  // Fallback close handlers
  // Only allow closing of endModal by clicking outside, startModal must use close button
  endModalEl.addEventListener('click', function(e){ if (!hasBootstrap && e.target === endModalEl) { hideModal(endModalEl, null); } });
  
  // Close button handlers
  [startModalEl, endModalEl].forEach(function(el){
    if (!el) return;
    var closeBtns = el.querySelectorAll('[data-bs-dismiss="modal"], .btn-close');
    if (!hasBootstrap && closeBtns && closeBtns.length) {
      closeBtns.forEach(function(btn){ btn.addEventListener('click', function(){ hideModal(el, null); }); });
    }
  });

  function fmt(s){ var m=Math.floor(s/60), ss=s%60; return formatter.format(m)+':' + formatter.format(ss); }
  
  function showQuestion(index) {
    qEls.forEach(function(el, i) {
      el.style.display = (i === index) ? '' : 'none';
    });
    cur = index;
    
    // Save current slide to localStorage so refresh jumps back
    try {
        var str = localStorage.getItem(storageKey);
        var data = str ? JSON.parse(str) : {};
        data['_current_slide'] = cur;
        localStorage.setItem(storageKey, JSON.stringify(data));
    } catch(e) {}

    // Auto-switch tabs if the user navigated across boundaries via prev/next/nav
    var activeQ = qEls[index];
    if (activeQ && currentTab !== 'all') {
        var qSubject = activeQ.getAttribute('data-subject');
        if (qSubject && qSubject !== currentTab) {
            // Need to switch the visual active tab, or we force flip into it
            switchTab(qSubject, false); // false = don't reset to question 0
        }
    }

    // Update Prev/Next button states
    document.getElementById('btnPrev').disabled = (index === 0);
    document.getElementById('btnNext').disabled = (index === total - 1);

    // Update nav highlights
    navBtns.forEach(function(btn, i) {
      if (i === index) {
        btn.classList.add('active');
        // ensure it's scrolled into view in sidebar
        btn.scrollIntoView({behavior: 'smooth', block: 'nearest'});
      } else {
        btn.classList.remove('active');
      }
    });

    // Scroll top
    if (activeQ) {
        if (window.innerWidth <= 768) {
            activeQ.scrollIntoView({behavior: 'smooth', block: 'start'});
        } else {
            window.scrollTo({top: 0, behavior: 'smooth'});
        }
    }
  }

  // Tab Filtering Logic
  var currentTab = 'all';
  function switchTab(subject, resetIndex) {
      currentTab = subject;
      
      // visual update on tab buttons
      var tabBtns = document.querySelectorAll('.subject-tab-btn');
      tabBtns.forEach(function(tb) {
          if (tb.getAttribute('data-subject') === subject) {
              tb.classList.add('active');
              tb.style.borderColor = '#10b981';
              tb.style.background = '#f0fdf4';
              tb.style.color = '#10b981';
          } else {
              tb.classList.remove('active');
              tb.style.borderColor = '#e2e8f0';
              tb.style.background = '#fff';
              tb.style.color = '#64748b';
          }
      });

      // hide/show navigator items
      var firstVisibleIndex = -1;
      navBtns.forEach(function(btn, i) {
          var btnSubject = btn.getAttribute('data-subject');
          if (subject === 'all' || btnSubject === subject) {
              btn.style.display = 'flex';
              if (firstVisibleIndex === -1) firstVisibleIndex = i;
          } else {
              btn.style.display = 'none';
          }
      });

      // Show the first question of this new tab if allowed
      if (resetIndex && firstVisibleIndex !== -1) {
          showQuestion(firstVisibleIndex);
      }
  }

  // Bind tab click events
  var tabBtns = document.querySelectorAll('.subject-tab-btn');
  tabBtns.forEach(function(tb) {
      tb.addEventListener('click', function(e) {
          e.preventDefault();
          var s = this.getAttribute('data-subject');
          if (s) switchTab(s, true);
      });
  });

  function updateTimerWarning(){
    if (!timerEl) return;
    timerEl.classList.remove('warning', 'timer-yellow', 'timer-red');
    if (remaining <= 180) { // 3 minutes
        timerEl.classList.add('timer-red');
    } else if (remaining <= 600) { // 10 minutes
        timerEl.classList.add('timer-yellow');
    }
  }
  function updateAnsweredStates(){
    navBtns.forEach(function(btn, idx){
      var q = qEls[idx];
      var answered = q.querySelector('.answer-input:checked') || (q.querySelector('input[type="text"].answer-input') && q.querySelector('input[type="text"].answer-input').value.trim() !== '');
      btn.classList.toggle('btn-success', !!answered);
      btn.classList.toggle('btn-outline-secondary', !answered);
    });
    updateButtonLabel();
  }
  function unansweredCount(){ var n = 0; qEls.forEach(function(q){ var answered = q.querySelector('.answer-input:checked') || (q.querySelector('input[type="text"].answer-input') && q.querySelector('input[type="text"].answer-input').value.trim() !== ''); if (!answered) n++; }); return n; }
  function allAnswered(){ return unansweredCount() === 0; }
  function updateButtonLabel(){
    var b = document.getElementById('btnEnd');
    if (!b) return;
    b.innerHTML = allAnswered() ? '<i class="fas fa-check-circle me-2"></i> Submit' : '<i class="fas fa-flag-checkered me-2"></i> End Exam';
  }
  var initialRemaining = remaining;
  var timerStartMs = null;
  function startTimer(sec){ 
      remaining = sec; 
      initialRemaining = sec;
      timerStartMs = Date.now();
      tick(); 
  }
  function tick(){
    if (timerStartMs) {
      var elapsedSecs = Math.floor((Date.now() - timerStartMs) / 1000);
      remaining = initialRemaining - elapsedSecs;
    }

    if (timerEl) { timerEl.textContent = fmt(Math.max(0, remaining)); }
    updateTimerWarning();
    
    if (remaining <= 0){ 
      if (isChallenge && eventEndTs) {
          // If a sweetalert exists, show it, but just submitting is fine too. Let's just submit so no delay exists.
      }
      window.isSubmitting = true;
      document.getElementById('quizForm').submit(); 
      return; 
    }
    setTimeout(tick, 1000);
  }



  document.getElementById('btnPrev').addEventListener('click', function(){ showQuestion(cur-1); });
  document.getElementById('btnNext').addEventListener('click', function(){ showQuestion(cur+1); });
  document.getElementById('btnEnd').addEventListener('click', function(){
    if (allAnswered()) { 
        window.isSubmitting = true;
        document.getElementById('quizForm').submit(); 
        return; 
    }
    var ua = unansweredCount();
    var text = ua + ' question(s) are still unanswered. Submit anyway?';
    document.getElementById('unansweredSummary').textContent = text;
    showModal(endModalEl, endModal);
  });
  document.getElementById('confirmSubmit').addEventListener('click', function(){ 
      window.isSubmitting = true;
      document.getElementById('quizForm').submit(); 
  });
  document.getElementById('toggleNav').addEventListener('click', function(){ var c = document.getElementById('navContainer'); var hidden = c.style.display === 'none'; c.style.display = hidden ? '' : 'none'; this.textContent = hidden ? 'Hide' : 'Show'; });
  navBtns.forEach(function(btn){ btn.addEventListener('click', function(){ showQuestion(parseInt(btn.getAttribute('data-target'))); }); });
  showQuestion(0);
  // Initialize timer display immediately
  if (timerEl) { timerEl.textContent = fmt(remaining); }
  
  var storageKey = 'mystudy_quiz_' + <?php echo (int)$id; ?> + '_user_' + <?php echo (int)$_SESSION['user_id']; ?>;
  function saveAnswersLocal() {
    var str = localStorage.getItem(storageKey);
    var data = str ? JSON.parse(str) : {};
    
    // Maintain the slide index
    var curSlide = data['_current_slide'] || 0;
    data = {'_current_slide': curSlide}; 
    
    document.querySelectorAll('.answer-input:checked, input[type="text"].answer-input').forEach(function(el){
        if (el.type === 'radio' && el.checked) { data[el.name] = el.value; }
        else if (el.type === 'text' && el.value.trim() !== '') { data[el.name] = el.value; }
    });
    localStorage.setItem(storageKey, JSON.stringify(data));
  }
  
  function restoreAnswersLocal() {
    try {
        var str = localStorage.getItem(storageKey);
        if (str) {
            var data = JSON.parse(str);
            for (var name in data) {
                if(name === '_current_slide') continue;
                var el = document.querySelector('input[name="'+name+'"][value="'+data[name]+'"]');
                if (el) { el.checked = true; var r = el.closest('.form-check'); if(r) r.classList.add('selected'); }
                else {
                    var txt = document.querySelector('input[type="text"][name="'+name+'"]');
                    if (txt) txt.value = data[name];
                }
            }
            if (data['_current_slide'] !== undefined) {
                cur = parseInt(data['_current_slide']);
            }
        }
    } catch(e) {}
  }

  // Restore existing selection on load and jump to slide
  restoreAnswersLocal();
  updateAnsweredStates(); updateButtonLabel();

  // Clear storage on form submit
  document.getElementById('quizForm').addEventListener('submit', function() {
      localStorage.removeItem(storageKey);
      window.isSubmitting = true; // prevent beforeunload warning
  });
  
  // Professional Exit Warning (beforeunload for browser actions)
  window.addEventListener('beforeunload', function(e) {
      if (hasStarted && !window.isSubmitting) {
          e.preventDefault();
          e.returnValue = 'You have an active quiz session. If you leave, your quiz will be automatically submitted with your current answers.';
      }
  });

  // Prevent Navigation/Back Button via popstate
  history.pushState(null, null, location.href);
  window.addEventListener('popstate', function(event) {
      if (hasStarted && !window.isSubmitting) {
          // Push state again to trap the user
          history.pushState(null, null, location.href);
          if (confirm("⚠️ WARNING: Are you sure you want to exit? Your quiz will be AUTO-SUBMITTED with current answers and you cannot return!")) {
              window.isSubmitting = true;
              localStorage.removeItem(storageKey);
              document.getElementById('quizForm').submit();
          }
      }
  });

  // Intercept internal links (like logo or nav) to show the professional modal instead of instant submit
  var exitWarningModalEl = document.getElementById('exitWarningModal');
  var exitWarningModal = hasBootstrap ? new bootstrap.Modal(exitWarningModalEl) : null;
  var pendingExitUrl = null;

  document.querySelectorAll('a').forEach(function(link) {
      link.addEventListener('click', function(e) {
          var href = this.getAttribute('href');
          // Ignore anchor links or javascript links
          if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
          
          if (hasStarted && !window.isSubmitting) {
              e.preventDefault();
              pendingExitUrl = href;
              showModal(exitWarningModalEl, exitWarningModal);
          }
      });
  });

  document.getElementById('btnForceExit').addEventListener('click', function() {
      if (pendingExitUrl) {
          window.isSubmitting = true; // disable beforeunload
          document.getElementById('quizForm').submit(); // Submits the quiz
      }
  });

  document.getElementById('quizForm').addEventListener('change', function(e){
    if (e.target.classList.contains('answer-input')) {
      // Highlight selected option row
      var qq = e.target.closest('.quiz-question');
      if (qq) {
        var rows = qq.querySelectorAll('.form-check');
        rows.forEach(function(r){ r.classList.remove('selected'); });
        var selected = qq.querySelector('.answer-input:checked');
        if (selected) { var r = selected.closest('.form-check'); if (r) r.classList.add('selected'); }
      }
      updateAnsweredStates();
      saveAnswersLocal(); // Auto-save on change
    }
  });
  document.getElementById('quizForm').addEventListener('input', function(e){ 
      if (e.target.classList.contains('answer-input')) { 
          updateAnsweredStates(); 
          saveAnswersLocal(); // Auto-save on input
      } 
  });
  if (!hasStarted){
    showModal(startModalEl, startModal);
  } else {
    showQuestion(cur); // Render the matched slide or slide 0
    startTimer(remaining);
  }

  // Cancel start button handlers - redirect to quizzes page
  var btnCancelStart = document.getElementById('btnCancelStart');
  var btnCancelStartFooter = document.getElementById('btnCancelStartFooter');
  if (btnCancelStart) {
    btnCancelStart.addEventListener('click', function(){
      window.location.href = '<?php echo SITE_URL; ?>/quizzes.php';
    });
  }
  if (btnCancelStartFooter) {
    btnCancelStartFooter.addEventListener('click', function(){
      window.location.href = '<?php echo SITE_URL; ?>/quizzes.php';
    });
  }

  var btnReadyEl = document.getElementById('btnReady');
  if (btnReadyEl) {
    btnReadyEl.addEventListener('click', function(){
      // Set loading state
      btnReadyEl.disabled = true;
      btnReadyEl.querySelector('.btn-text').textContent = 'Starting...';
      btnReadyEl.querySelector('.btn-spinner').classList.remove('d-none');
      
      fetch('<?php echo SITE_URL; ?>/quiz_start.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'quiz_id=<?php echo (int)$id; ?>&mode=<?php echo htmlspecialchars($mode ?? "normal"); ?>&csrf_token=<?php echo htmlspecialchars($csrf_token); ?>'
      }).then(function(r){ return r.json(); }).then(function(data){
        if (data && data.ok){ 
            hasStarted = true;
            hideModal(startModalEl, startModal); 
            startTimer(data.remaining); 
        }
        else {
            btnReadyEl.disabled = false;
            btnReadyEl.querySelector('.btn-text').textContent = 'Ready to Start';
            btnReadyEl.querySelector('.btn-spinner').classList.add('d-none');
            
            if (data && data.code === 'not_started') { alert('Challenge has not started yet. Starts in ' + Math.ceil((data.start_in||0)/60) + ' min.'); }
            else if (data && data.code === 'ended') { alert('This challenge has ended.'); window.location.href = '<?php echo SITE_URL; ?>/quizzes.php'; }
            else { alert('Could not start quiz. Please refresh.'); }
        }
      }).catch(function(){ 
          btnReadyEl.disabled = false;
          btnReadyEl.querySelector('.btn-text').textContent = 'Ready to Start';
          btnReadyEl.querySelector('.btn-spinner').classList.add('d-none');
          alert('Network error. Please try again.'); 
      });
    });
  }
})();
</script>
<?php
$page_scripts = ($page_scripts ?? '') . ob_get_clean();

include __DIR__ . '/includes/header.php';
?>
<?php if ($isChallenge): ?>
<script>
  document.body.classList.add('challenge-quiz-page');
</script>
<?php
endif; ?>
<?php if ($isPaidQuiz): ?>
<script>
  document.body.classList.add('special-quiz-page');
</script>
<?php
endif; ?>

<?php if ($isChallenge): ?>
<!-- Challenge Quiz Premium Header -->
<div class="challenge-quiz-header">
  <div class="container">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
      <div>
        <div class="challenge-quiz-title">
          <i class="fas fa-trophy"></i>
          <?php echo htmlspecialchars($quiz['title']); ?>
          <span class="challenge-badge">
            <i class="fas fa-bolt me-1"></i> LIVE CHALLENGE
          </span>

        </div>
        <div class="challenge-subtitle">
          <i class="fas fa-book me-2"></i><?php echo htmlspecialchars($quiz['subject'] ?? ''); ?>
          <span class="mx-2">·</span>
          <i class="fas fa-layer-group me-2"></i><?php echo htmlspecialchars($displayLevelText); ?>
          <?php if (!empty($quiz['prize_amount'])): ?>
            <span class="mx-2">·</span>
            <i class="fas fa-medal me-2"></i>Prize: <?php echo format_prize($quiz['prize_amount'], $quiz['prize_type'] ?? 'money'); ?>
          <?php
  elseif (!empty($quiz['prize_text'])): ?>
            <span class="mx-2">·</span>
            <i class="fas fa-gift me-2"></i><?php echo htmlspecialchars($quiz['prize_text']); ?>
          <?php
  endif; ?>
        </div>
      </div>
      <div class="d-flex gap-3 align-items-center">
        <div class="challenge-timer-display" id="timer">--:--</div>
        <a href="<?php echo SITE_URL; ?>/leaderboard.php?quiz_id=<?php echo (int)$quiz['id']; ?>" 
           class="btn btn-leaderboard" 
           title="View Leaderboard">
          <i class="fas fa-trophy me-2"></i>
          <span class="d-none d-md-inline">Leaderboard</span>
        </a>
      </div>
    </div>
  </div>
</div>
<?php
endif; ?>

<?php if (!$isChallenge): ?>
<!-- ======================================
     PREMIUM NORMAL QUIZ HEADER BAR
     ====================================== -->
<div class="quiz-top-bar">
    <div class="container">
        <div class="quiz-top-inner">
            <div class="quiz-top-info">
                <h1 class="quiz-top-title">
                    <?php echo htmlspecialchars($quiz['title']); ?>
                    <?php if ($isPaidQuiz): ?> <span class="special-quiz-label"><i class="fas fa-star"></i> SPECIAL</span><?php
  endif; ?>

                </h1>
                <div class="quiz-top-meta">
                    <span><i class="fas fa-book-open me-1"></i> <?php echo htmlspecialchars($quiz['subject'] ?? 'General'); ?></span>
                    <span class="quiz-meta-sep">·</span>
                    <span><i class="fas fa-signal me-1"></i> <?php echo htmlspecialchars($displayLevelText); ?></span>
                    <span class="quiz-meta-sep">·</span>
                    <span><i class="fas fa-clock me-1"></i> <?php echo ceil($duration / 60); ?> mins</span>
                    <span class="quiz-meta-sep">·</span>
                    <span><i class="fas fa-list-ol me-1"></i> <?php echo count($questions); ?> Qs</span>
                </div>
            </div>
            <div class="quiz-top-timer" id="timer">--:--</div>
        </div>
    </div>
</div>
<?php
endif; ?>

<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-8">
            
            <?php if ($mode === 'multi'): ?>
            <!-- Multi-Subject Navigation Tabs -->
            <div class="card border-0 mb-4" style="border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.03);">
                <div class="card-body p-2 d-flex flex-nowrap" style="overflow-x: auto; gap: 0.5rem;" id="subjectTabsContainer">
                    <button class="btn subject-tab-btn active px-4 py-3 fw-bold flex-shrink-0" data-subject="all" style="border-radius: 12px; transition: all 0.3s ease; border: 2px solid transparent; background: #f0fdf4; color: #10b981;">
                        <i class="fas fa-layer-group me-2"></i> Mixed Exam
                    </button>
                    <?php
  $uniqueLabels = [];
  foreach ($questions as $q) {
    if (!empty($q['origin_quiz_title']) && !in_array($q['origin_quiz_title'], $uniqueLabels)) {
      $uniqueLabels[] = $q['origin_quiz_title'];
    }
  }
  foreach ($uniqueLabels as $ulabel):
?>
                    <button class="btn subject-tab-btn px-4 py-3 fw-bold flex-shrink-0" data-subject="<?php echo htmlspecialchars($ulabel); ?>" style="border-radius: 12px; transition: all 0.3s ease; border: 2px solid #e2e8f0; background: #fff; color: #64748b;">
                        <?php echo htmlspecialchars($ulabel); ?>
                    </button>
                    <?php
  endforeach; ?>
                </div>
            </div>
            <style>
                .subject-tab-btn:hover:not(.active) { border-color: #cbd5e1; background: #f8fafc; color: #334155; }
                .subject-tab-btn.active { border-color: #10b981 !important; background: #f0fdf4 !important; color: #10b981 !important; box-shadow: 0 4px 15px rgba(16,185,129,0.15); }
            </style>
            <?php
endif; ?>

            <form id="quizForm" method="post" action="<?php echo SITE_URL; ?>/quiz_submit.php">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="quiz_id" value="<?php echo (int)$id; ?>">
                        <input type="hidden" name="mode" value="<?php echo htmlspecialchars($quiz['mode'] ?? 'normal'); ?>">

                        <?php foreach ($questions as $index => $q):
  $safeLabel = htmlspecialchars($q['origin_quiz_title'] ?? 'all');
?>
                            <div class="quiz-question mb-4" data-index="<?php echo (int)$index; ?>" data-subject="<?php echo $safeLabel; ?>" id="question-<?php echo (int)$index; ?>" style="display:none;">
                                <div class="question-stem">
                                    <?php if (!empty($q['origin_quiz_title'])): ?>
                                        <span class="badge bg-white text-dark mb-2 d-inline-block shadow-sm" style="font-size:0.8rem;"><i class="fas fa-book me-1 text-success"></i> <?php echo htmlspecialchars($q['origin_quiz_title']); ?></span><br>
                                    <?php
  endif; ?>
                                    <span class="question-number-label">Question <?php echo($index + 1); ?> of <?php echo count($questions); ?></span>
                                    <div class="question-text"><?php echo nl2br(safe_quiz_html($q['question'])); ?></div>
                                </div>
                                <div class="options">
                                <?php if (($q['question_type'] ?? 'objective') === 'subjective'): ?>
                                    <div class="subjective-answer-wrap">
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size: 0.75rem; border-radius: 8px; padding: 0.35rem 0.75rem;"><i class="fas fa-pen-fancy me-1"></i> Type Your Answer</span>
                                        </div>
                                        <input type="text" class="form-control answer-input subjective-input" name="q[<?php echo (int)$q['id']; ?>]" placeholder="Enter your answer here..." autocomplete="off" style="border: 2px solid #e2e8f0; border-radius: 14px; padding: 0.85rem 1.25rem; font-size: 1rem; font-weight: 500; background: linear-gradient(135deg, #fefefe 0%, #f8fafc 100%); transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                                        <small class="text-muted d-block mt-1" style="font-size: 0.72rem;"><i class="fas fa-info-circle me-1"></i>Case does not matter</small>
                                    </div>
                                <?php
  elseif (!empty($q['options'])): ?>
                                    <?php foreach ($q['options'] as $optIdx => $opt): ?>
                                        <div class="form-check">
                                            <input class="form-check-input answer-input" type="radio" name="q[<?php echo (int)$q['id']; ?>]" id="q<?php echo (int)$q['id']; ?>o<?php echo (int)$opt['id']; ?>" value="<?php echo (int)$opt['id']; ?>">
                                            <label class="form-check-label" for="q<?php echo (int)$q['id']; ?>o<?php echo (int)$opt['id']; ?>">
                                                <?php
      $label = chr(65 + $optIdx) . '. ' . safe_quiz_html($opt['option_text']);
      echo $label;
?>
                                            </label>
                                        </div>
                                    <?php
    endforeach; ?>
                                <?php
  else: ?>
                                    <input type="text" class="form-control answer-input" name="q[<?php echo (int)$q['id']; ?>]" placeholder="Type your answer">
                                <?php
  endif; ?>
                                </div>
                            </div>
                        <?php
endforeach; ?>

                        <!-- Premium Navigation Controls -->
                        <div class="quiz-controls">
                            <div class="quiz-controls-nav">
                                <button class="btn btn-quiz-nav" type="button" id="btnPrev"><i class="fas fa-chevron-left me-2"></i> Previous</button>
                                <button class="btn btn-quiz-nav" type="button" id="btnNext">Next <i class="fas fa-chevron-right ms-2"></i></button>
                            </div>
                            <button class="btn btn-quiz-end" type="button" id="btnEnd"><i class="fas fa-flag-checkered me-2"></i> End Exam</button>
                        </div>
                    </form>
        </div>
        <div class="col-lg-4">
            <div class="quiz-sidebar-card <?php echo $isChallenge ? 'challenge-sidebar' : ''; ?>">
                <div class="quiz-sidebar-header <?php echo $isChallenge ? '' : ''; ?>">
                  <strong><i class="fas fa-compass me-2"></i>Question Navigator</strong>
                  <button class="btn btn-sm btn-outline-light" id="toggleNav">Hide</button>
                </div>
                <div class="quiz-sidebar-body">
                    <div id="navContainer" class="mb-3">
                        <div class="d-flex flex-wrap gap-2" id="questionNav">
                            <?php foreach ($questions as $index => $q):
  $safeLabel = htmlspecialchars($q['origin_quiz_title'] ?? 'all');
?>
                                <button type="button" class="btn btn-sm btn-outline-secondary nav-item" data-index="<?php echo (int)$index; ?>" data-subject="<?php echo $safeLabel; ?>" data-target="<?php echo (int)$index; ?>"><?php echo (int)($index + 1); ?></button>
                            <?php
endforeach; ?>
                        </div>
                    </div>
                    <div class="quiz-sidebar-legend">
                        <div class="legend-item"><span class="legend-dot answered"></span> Answered</div>
                        <div class="legend-item"><span class="legend-dot unanswered"></span> Not answered</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Exit Warning Modal -->
<div class="modal fade" id="exitWarningModal" tabindex="-1" aria-labelledby="exitWarningLabel" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px; border: none; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
      <div class="modal-header border-0 bg-danger text-white p-4">
        <h5 class="modal-title fw-bold" id="exitWarningLabel" style="font-size: 1.25rem;"><i class="fas fa-exclamation-triangle me-2"></i>Warning: Action Restricted</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <div style="width: 70px; height: 70px; background: rgba(220,38,38,0.1); color: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.5rem;">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h4 class="fw-bold mb-3" style="color: #1e293b;">Are you sure you want to leave?</h4>
        <p class="text-muted mb-4" style="font-size: 1.05rem; line-height: 1.6;">
            You are currently taking an active quiz. If you choose to leave this page, your current answers will be <strong>automatically submitted</strong> and your attempt will end immediately.
        </p>
        <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
            <button type="button" class="btn btn-outline-secondary px-4 py-2 fw-bold" style="border-radius: 12px;" data-bs-dismiss="modal">Return to Quiz</button>
            <button type="button" class="btn btn-danger px-4 py-2 fw-bold" style="border-radius: 12px;" id="btnForceExit">Submit & Leave</button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
