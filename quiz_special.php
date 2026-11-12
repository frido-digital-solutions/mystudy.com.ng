<?php
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/models/Payment.php';

require_once __DIR__ . '/models/AccessCode.php';
require_once __DIR__ . '/models/QuizAttempt.php';

$quizModel = new Quiz($pdo);
$paymentModel = new Payment($pdo);
$accessCodeModel = new AccessCode($pdo);
$attemptModel = new QuizAttempt($pdo);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = (int)$_SESSION['user_id'];

$quiz = $quizModel->getById($id);

// Fetch attached study materials for this quiz
$quizStudyMaterials = [];
if ($id > 0) {
    $matStmt = $pdo->prepare("SELECT m.id, m.title, m.original_name, m.file_path, m.file_size FROM quiz_materials qm JOIN materials m ON m.id = qm.material_id WHERE qm.quiz_id = ? AND m.status = 'approved' ORDER BY m.title ASC");
    $matStmt->execute([$id]);
    $quizStudyMaterials = $matStmt->fetchAll(PDO::FETCH_ASSOC);
}

if (!$quiz) {
    set_flash_message('error', 'This quiz is not available.');
    redirect(SITE_URL . '/quizzes.php');
}

// Student-type restriction check
$typeRestricted = false;
$quizType = $quiz['type'] ?? 'both';
$userType = $_SESSION['user_type'] ?? '';
if ($quizType !== 'both' && $quizType !== $userType) {
    $typeRestricted = true;
}

// Level restriction gate
$reqLevel = (int)($quiz['required_level'] ?? 0);
if ($reqLevel > 0) {
    require_once __DIR__ . '/models/Gamification.php';
    $gamification = new Gamification($pdo);
    $userGamData = $gamification->getUserStats($userId);
    $userLevel = (int)$userGamData['level'];
    if ($userLevel < $reqLevel) {
        set_flash_message('error', 'You must be Level ' . $reqLevel . ' or higher to access this quiz.');
        redirect(SITE_URL . '/quizzes.php');
    }
}

// Allow both paid quizzes and challenge-mode quizzes (even if free)
$isChallenge = ($quiz['mode'] ?? 'normal') === 'challenge';
if (!$isChallenge && empty($quiz['is_paid'])) {
    set_flash_message('error', 'This quiz is not available.');
    redirect(SITE_URL . '/quizzes.php');
}

// Challenge timing

$isLive = false;
$eventStartTs = null;
$eventEndTs = null;
$now = time();

if ($isChallenge) {
    if (!empty($quiz['event_start']) && !empty($quiz['event_end'])) {
        $eventStartTs = strtotime($quiz['event_start']);
        $eventEndTs = strtotime($quiz['event_end']);
        if ($now >= $eventStartTs && $now < $eventEndTs) {
            $isLive = true;
        }
    }
}

// Check access
$hasAccess = false;
$hasPendingPayment = false;
$userCodeStr = '';
$hasJoined = false;

// Check if user has joined the challenge
if ($isChallenge) {
    $joinCheck = $pdo->prepare("SELECT id FROM challenge_participants WHERE quiz_id = ? AND user_id = ?");
    $joinCheck->execute([$id, $userId]);
    $hasJoined = (bool)$joinCheck->fetch();
}

// Track participant count (from all sources)
$stmtCount = $pdo->prepare("
    SELECT COUNT(DISTINCT u) FROM (
        SELECT used_by_user_id as u FROM quiz_access_codes WHERE quiz_id = ? AND used_by_user_id IS NOT NULL
        UNION
        SELECT user_id as u FROM quiz_attempts WHERE quiz_id = ?
        UNION
        SELECT user_id as u FROM challenge_participants WHERE quiz_id = ?
    ) as participants
");
$stmtCount->execute([$id, $id, $id]);
$participantCount = (int)$stmtCount->fetchColumn();

$maxParticipants = !empty($quiz['max_participants']) ? (int)$quiz['max_participants'] : null;
$isFull = $maxParticipants && $participantCount >= $maxParticipants;

$hasAttempted = $attemptModel->hasAttempted($id, $userId);

$entryPrice = !empty($quiz['entry_fee']) ? (float)$quiz['entry_fee'] : 0;

// Handle Join Challenge action (for free challenges)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_challenge']) && $isChallenge && !$hasJoined && !$isFull) {
    try {
        $joinStmt = $pdo->prepare("INSERT IGNORE INTO challenge_participants (quiz_id, user_id) VALUES (?, ?)");
        $joinStmt->execute([$id, $userId]);
        $hasJoined = true;
        $participantCount++;
        $isFull = $maxParticipants && $participantCount >= $maxParticipants;
        
        // Send notification
        require_once __DIR__ . '/models/Notification.php';
        $notif = new Notification($pdo);
        $notif->create($userId, 'challenge_joined', '🎯 Joined: ' . $quiz['title'], 
            "You've joined the challenge \"" . $quiz['title'] . "\". We'll notify you when it's about to go live!", 
            SITE_URL . '/quiz_special.php?id=' . $id);
    } catch (Exception $e) {
        // silently ignore duplicates
    }
}

if ($entryPrice <= 0) {
    // Free challenge: access granted if user has joined, already attempted, or if challenge is unlimited
    if ($isChallenge) {
        $hasAccess = $hasJoined || $hasAttempted || !$maxParticipants;
    } else {
        if ($isFull && !$hasAttempted) {
            $hasAccess = false;
        } else {
            $hasAccess = true;
        }
    }
} else {
    $hasAccess = $paymentModel->hasPaid($userId, $id);
    if (!$hasAccess && $accessCodeModel->hasUserUsedACodeForQuiz($userId, $id)) {
        $hasAccess = true;
    }
    if ($hasAccess) {
        $userCodeStr = $accessCodeModel->getCodeForUser($userId, $id) ?? '';
        // Also auto-register as joined for paid challenges
        if ($isChallenge && !$hasJoined) {
            try {
                $pdo->prepare("INSERT IGNORE INTO challenge_participants (quiz_id, user_id) VALUES (?, ?)")->execute([$id, $userId]);
                $hasJoined = true;
            } catch (Exception $e) {}
        }
    }
}

if (!$hasAccess && !$hasAttempted) {
    $hasPendingPayment = ($paymentModel->getPendingPayment($userId, $id) !== null);
}

// User details for payment
require_once __DIR__ . '/models/User.php';
$userModel = new User($pdo);
$userModel->getById($userId);
$userEmail = $userModel->email;
$userName = $userModel->name ?: 'Student';

$codeError = '';

// Handle access code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['access_code'])) {
    $submitted = trim($_POST['access_code']);
    
    // First, check universal access code
    if (!empty($quiz['access_code']) && strtoupper($submitted) === strtoupper(trim($quiz['access_code']))) {
        $paymentModel->recordPayment($userId, $id, 0, 'CODE-' . strtoupper($submitted) . '-' . time(), 'successful');
        $hasAccess = true;
    } else {
        // Now check unique 1-time voucher codes
        $voucher = $accessCodeModel->verifyCode($submitted, $id);
        if ($voucher) {
            if ((int)$voucher['is_used'] === 1) {
                if ((int)$voucher['used_by_user_id'] === $userId) {
                    $hasAccess = true; // They used it already, let them in
                } else {
                    $codeError = 'This access code has already been used by another user.';
                }
            } else {
                // Not used, redeem it
                if ($accessCodeModel->useCode((int)$voucher['id'], $userId)) {
                    $paymentModel->recordPayment($userId, $id, 0, 'VOUCHER-' . strtoupper($submitted) . '-' . time(), 'successful');
                    $hasAccess = true;
                } else {
                    $codeError = 'Failed to redeem the code. Please try again.';
                }
            }
        } else {
            $codeError = 'Invalid access code. Please try again.';
        }
    }
}

// Redirect to quiz if access granted via GET start
if ($hasAccess && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['start'])) {
    if ($hasAttempted) {
        redirect(SITE_URL . '/quiz_special.php?id=' . $id);
    }
    redirect(SITE_URL . '/quiz.php?id=' . $id);
}

// If user already took this quiz, fetch their score and rank
$attemptScore = 0;
$attemptTotal = 0;
$userRank = 0;
$totalParticipants = 0;
if ($hasAttempted) {
    $stmtAtt = $pdo->prepare('SELECT score, total FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? ORDER BY completed_at DESC LIMIT 1');
    $stmtAtt->execute([$id, $userId]);
    $userAttemptData = $stmtAtt->fetch(PDO::FETCH_ASSOC);
    if ($userAttemptData) {
        $attemptScore = (int)$userAttemptData['score'];
        $attemptTotal = (int)$userAttemptData['total'];
    }
    if ($isChallenge) {
        $leaderboard = $attemptModel->topScores($id, null, 1000);
        $totalParticipants = count($leaderboard);
        foreach ($leaderboard as $idx => $entry) {
            if ((int)$entry['user_id'] === $userId) {
                $userRank = $idx + 1;
                break;
            }
        }
    }
}

$entryFee = !empty($quiz['entry_fee']) ? (float)$quiz['entry_fee'] : 0;
$entryFeeKobo = (int)round($entryFee * 100);

// Bind this checkout reference to the user, quiz and exact amount server-side.
$paystackRef = 'MSB-' . $userId . '-' . $id . '-' . bin2hex(random_bytes(5));
if (!isset($_SESSION['paystack_pending']) || !is_array($_SESSION['paystack_pending'])) {
    $_SESSION['paystack_pending'] = [];
}
foreach ($_SESSION['paystack_pending'] as $oldRef => $old) {
    if (($old['created'] ?? 0) < time() - 7200) unset($_SESSION['paystack_pending'][$oldRef]);
}
$_SESSION['paystack_pending'][$paystackRef] = [
    'quiz_id' => $id,
    'user_id' => $userId,
    'amount'  => $entryFee,
    'created' => time(),
];
$hasPrize = !empty($quiz['prize_amount']) || !empty($quiz['prize_text']);
$prizeDisplay = '';
$prizeType = $quiz['prize_type'] ?? 'none';
if (!empty($quiz['prize_amount']) && $quiz['prize_amount'] > 0) {
    $prizeDisplay = format_prize($quiz['prize_amount'], $prizeType);
} elseif (!empty($quiz['prize_text'])) {
    $prizeDisplay = htmlspecialchars($quiz['prize_text']);
}

$eventStart = !empty($quiz['event_start']) ? strtotime($quiz['event_start']) : null;
$eventEnd = !empty($quiz['event_end']) ? strtotime($quiz['event_end']) : null;
$now = time();
$isUpcoming = $eventStart && $now < $eventStart;
$isLive = $eventStart && $eventEnd && $now >= $eventStart && $now <= $eventEnd;
$isEnded = $eventEnd && $now > $eventEnd;

$questionCount = $quizModel->getQuestionCount($id);
$duration = (int)($quiz['duration_seconds'] ?? 900);
$durationFormatted = $duration >= 3600
    ? floor($duration / 3600) . 'h ' . (($duration % 3600) / 60) . 'm'
    : floor($duration / 60) . ' mins';

$page_title = htmlspecialchars($quiz['title']) . ' — Special Quiz';

ob_start();
?>
<style>
/* ========================================= */
/* SPECIAL QUIZ LANDING — PREMIUM CLEAN      */
/* ========================================= */

/* Hero */
.sq-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0f172a 100%);
    padding: 3rem 0 0;
    position: relative;
    overflow: hidden;
}

.sq-hero::before {
    content: '';
    position: absolute;
    top: -40%;
    right: -15%;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(251, 191, 36, 0.12) 0%, transparent 70%);
    pointer-events: none;
}

.sq-hero-z { position: relative; z-index: 1; }

.sq-badges {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.sq-b {
    padding: 0.35rem 0.85rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.sq-b.special { background: rgba(251,191,36,0.2); border: 1px solid rgba(251,191,36,0.4); color: #fbbf24; }
.sq-b.live { background: rgba(16,185,129,0.2); border: 1px solid rgba(16,185,129,0.5); color: #10b981; animation: livePulse 2s infinite; }
.sq-b.ended { background: rgba(148,163,184,0.2); border: 1px solid rgba(148,163,184,0.4); color: #94a3b8; }
.sq-b.upcoming { background: rgba(59,130,246,0.2); border: 1px solid rgba(59,130,246,0.4); color: #3b82f6; }
.sq-b.paid { background: rgba(245,158,11,0.2); border: 1px solid rgba(245,158,11,0.4); color: #f59e0b; }

@keyframes livePulse { 0%,100% { opacity:1; } 50% { opacity:0.6; } }

.sq-title {
    font-size: 2.25rem;
    font-weight: 900;
    color: #fff;
    margin: 0 0 0.5rem;
    letter-spacing: -1px;
}

.sq-meta {
    font-size: 0.9rem;
    color: #94a3b8;
    margin-bottom: 1.5rem;
}

.sq-meta span { margin-right: 1rem; }

/* Stats cards */
.sq-stats {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}

.sq-s {
    background: rgba(255,255,255,0.07);
    backdrop-filter: blur(10px);
    padding: 0.65rem 1rem;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.1);
    text-align: center;
    min-width: 85px;
    flex: 1;
}

.sq-s-l { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 0.15rem; }
.sq-s-v { font-size: 1.15rem; font-weight: 800; color: #fff; }
.sq-s-v.prize { color: #fbbf24; }
.sq-s-v.fee { color: #f59e0b; }

/* ========================================= */
/* ACCESS STRIP (in hero or below)           */
/* ========================================= */
.sq-access-strip {
    background: linear-gradient(135deg, #1a1f35 0%, #252b48 100%);
    border-top: 1px solid rgba(251,191,36,0.15);
    padding: 1.25rem 0;
}

.sq-access-inner {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.sq-access-inner form {
    display: flex;
    gap: 0.5rem;
    flex: 1;
    min-width: 250px;
}

.sq-code-in {
    flex: 1;
    border: 2px solid rgba(251,191,36,0.3);
    background: rgba(255,255,255,0.06);
    border-radius: 12px;
    padding: 0.65rem 1rem;
    color: #fff;
    font-weight: 700;
    font-size: 0.95rem;
    text-transform: uppercase;
    letter-spacing: 2px;
    text-align: center;
    transition: border-color 0.2s;
}

.sq-code-in::placeholder { color: rgba(255,255,255,0.3); letter-spacing: 1px; text-transform: none; }
.sq-code-in:focus { border-color: #fbbf24; outline: none; background: rgba(255,255,255,0.1); }

.sq-code-btn {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #0f172a;
    border: none;
    border-radius: 12px;
    padding: 0.65rem 1.5rem;
    font-weight: 800;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}

.sq-code-btn:hover { transform: scale(1.03); box-shadow: 0 4px 15px rgba(251,191,36,0.3); }

.sq-or {
    color: #64748b;
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
}

.sq-pay-btn {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 0.65rem 1.5rem;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    white-space: nowrap;
}

.sq-pay-btn:hover { transform: scale(1.03); box-shadow: 0 6px 20px rgba(245,158,11,0.3); }

.sq-code-err {
    width: 100%;
    background: rgba(239,68,68,0.15);
    border: 1px solid rgba(239,68,68,0.3);
    color: #fca5a5;
    padding: 0.5rem 1rem;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
}

/* Access Granted Strip */
.sq-granted-strip {
    background: linear-gradient(135deg, #065f46 0%, #064e3b 100%);
    border-top: 2px solid #10b981;
    padding: 1rem 0;
}

.sq-granted-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.sq-granted-text {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #d1fae5;
    font-weight: 700;
}

.sq-granted-text i { font-size: 1.5rem; color: #34d399; }

.sq-start-btn {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    border: none;
    border-radius: 50px;
    padding: 0.75rem 2rem;
    font-weight: 800;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    box-shadow: 0 6px 20px rgba(16,185,129,0.3);
}

.sq-start-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(16,185,129,0.4); color: #fff; }

/* Ended Strip */
.sq-ended-strip {
    background: linear-gradient(135deg, #1e293b/95, #1e293b);
    border-top: 2px solid #475569;
    padding: 1rem 0;
}

.sq-ended-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.sq-ended-text {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #94a3b8;
    font-weight: 700;
}

.sq-lb-btn {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #0f172a;
    border: none;
    border-radius: 50px;
    padding: 0.65rem 1.5rem;
    font-weight: 800;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    text-decoration: none;
}

.sq-lb-btn:hover { transform: translateY(-2px); color: #0f172a; }

/* ========================================= */
/* COUNTDOWN                                 */
/* ========================================= */
.sq-cd-wrap {
    background: rgba(255,255,255,0.05); /* blend into hero */
    border: 1px solid rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    margin-bottom: 2rem;
    max-width: 500px;
}

.sq-cd-title {
    font-size: 0.85rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #cbd5e1;
    margin-bottom: 1rem;
}

.sq-cd-boxes { display: flex; justify-content: center; gap: 0.75rem; }

.sq-cd-box {
    background: linear-gradient(135deg, #0f172a, #1e293b);
    border-radius: 14px;
    padding: 0.85rem 1rem;
    min-width: 70px;
}

.sq-cd-n { font-size: 1.75rem; font-weight: 900; color: #fbbf24; line-height: 1; }
.sq-cd-l { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; margin-top: 0.2rem; }

/* ========================================= */
/* ABOUT — COLLAPSIBLE ON MOBILE             */
/* ========================================= */
.sq-about {
    background: #fff;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    margin-bottom: 1.5rem;
}

/* Study materials section */
.sq-study-materials {
    background: #fff;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    margin-bottom: 1.5rem;
}
.sq-materials-toggle { display: none; }

.sq-about-toggle {
    display: none;
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    padding: 0.85rem 1.25rem;
    font-weight: 700;
    font-size: 0.9rem;
    color: #475569;
    cursor: pointer;
    width: 100%;
    text-align: left;
    margin-bottom: 1rem;
    transition: all 0.2s;
}

.sq-about-toggle i.arrow { transition: transform 0.3s; margin-left: auto; float: right; }
.sq-about-toggle.open i.arrow { transform: rotate(180deg); }
.sq-about-toggle:hover { border-color: #10b981; color: #10b981; }

.sq-about-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sq-about-body { color: #475569; line-height: 1.7; }

.sq-rules {
    list-style: none;
    padding: 0;
    margin: 1rem 0 0;
}

.sq-rules li {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.4rem 0;
    font-size: 0.9rem;
    color: #475569;
}

.sq-rules li i { color: #10b981; margin-top: 0.2rem; flex-shrink: 0; }

@media (max-width: 768px) {
    .sq-hero { padding: 2rem 0 0; }
    .sq-title { font-size: 1.6rem; }
    .sq-stats { gap: 0.5rem; }
    .sq-s { min-width: 0; padding: 0.5rem 0.5rem; }
    .sq-s-v { font-size: 1rem; }
    .sq-cd-boxes { gap: 0.5rem; }
    .sq-cd-box { min-width: 55px; padding: 0.65rem 0.5rem; }
    .sq-cd-n { font-size: 1.3rem; }
    .sq-access-inner { flex-direction: column; align-items: stretch; }
    .sq-access-inner form { min-width: 0; }
    .sq-granted-inner { flex-direction: column; text-align: center; }
    .sq-ended-inner { flex-direction: column; text-align: center; }
    /* Mobile: About collapsible */
    .sq-about-toggle { display: flex; align-items: center; gap: 0.5rem; }
    .sq-about { display: none; }
    .sq-about.open { display: block; }
    /* Mobile: Study Materials collapsible */
    .sq-materials-toggle { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.75rem; }
    .sq-study-materials { display: none; margin-top: 0; }
    .sq-study-materials.open { display: block; }
}
</style>
<?php
$page_scripts = ($page_scripts ?? '') . ob_get_clean();
include __DIR__ . '/includes/header.php';
?>

<!-- HERO SECTION -->
<div class="sq-hero">
    <div class="container sq-hero-z">
        <div class="sq-badges">
            <span class="sq-b special"><i class="fas fa-star"></i> Special Quiz</span>
            <?php if ($isLive): ?>
                <span class="sq-b live"><i class="fas fa-circle" style="font-size:0.45rem;"></i> Live Now</span>
            <?php elseif ($isUpcoming): ?>
                <span class="sq-b upcoming"><i class="fas fa-clock"></i> Upcoming</span>
            <?php elseif ($isEnded): ?>
                <span class="sq-b ended"><i class="fas fa-flag-checkered"></i> Ended</span>
            <?php endif; ?>
            <?php if ($entryFee > 0): ?>
                <span class="sq-b paid"><i class="fas fa-ticket-alt"></i> Paid Entry</span>
            <?php endif; ?>
        </div>

        <h1 class="sq-title"><?php echo htmlspecialchars($quiz['title']); ?></h1>
        <div class="sq-meta">
            <?php if (!empty($quiz['subject'])): ?>
                <span><i class="fas fa-book me-1"></i><?php echo htmlspecialchars($quiz['subject']); ?></span>
            <?php endif; ?>
            <?php if (!empty($quiz['level'])): ?>
                <span><i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($quiz['level']); ?></span>
            <?php endif; ?>
        </div>

        <div class="sq-stats">
            <div class="sq-s">
                <div class="sq-s-l">Questions</div>
                <div class="sq-s-v"><?php echo $questionCount; ?></div>
            </div>
            <div class="sq-s">
                <div class="sq-s-l">Duration</div>
                <div class="sq-s-v"><?php echo $durationFormatted; ?></div>
            </div>
            <?php if ($hasPrize): ?>
            <div class="sq-s">
                <div class="sq-s-l">Prize</div>
                <div class="sq-s-v prize"><?php echo htmlspecialchars($prizeDisplay); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($entryFee > 0): ?>
            <div class="sq-s">
                <div class="sq-s-l">Entry Fee</div>
                <div class="sq-s-v fee">₦<?php echo number_format($entryFee, 0); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($maxParticipants): ?>
            <div class="sq-s">
                <div class="sq-s-l">Joined</div>
                <div class="sq-s-v <?php echo $isFull ? 'text-danger' : ''; ?>"><?php echo $participantCount; ?> <span style="font-size:0.8rem; color:#94a3b8;">/ <?php echo $maxParticipants; ?></span></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Hero Countdown Component -->
        <?php if (($isUpcoming && $eventStart) || ($isLive && $eventEnd)): ?>
        <?php 
            $cdTitle = $isUpcoming ? 'Starts In' : 'Ends In';
            $cdTargetTs = $isUpcoming ? $eventStart : $eventEnd;
        ?>
        <div class="sq-cd-wrap" <?php echo $isLive ? 'style="border: 1px solid rgba(16,185,129,0.4); background: rgba(16,185,129,0.05);"' : ''; ?>>
            <div class="sq-cd-title" <?php echo $isLive ? 'style="color: #6ee7b7;"' : ''; ?>>
                <i class="fas <?php echo $isUpcoming ? 'fa-hourglass-start' : 'fa-stopwatch'; ?> me-2"></i><?php echo $cdTitle; ?>
            </div>
            <div class="sq-cd-boxes">
                <div class="sq-cd-box" <?php echo $isLive ? 'style="background: rgba(6,78,59,0.6);"' : ''; ?>><div class="sq-cd-n" id="cd-d" <?php echo $isLive ? 'style="color:#6ee7b7;"' : ''; ?>>--</div><div class="sq-cd-l">Days</div></div>
                <div class="sq-cd-box" <?php echo $isLive ? 'style="background: rgba(6,78,59,0.6);"' : ''; ?>><div class="sq-cd-n" id="cd-h" <?php echo $isLive ? 'style="color:#6ee7b7;"' : ''; ?>>--</div><div class="sq-cd-l">Hours</div></div>
                <div class="sq-cd-box" <?php echo $isLive ? 'style="background: rgba(6,78,59,0.6);"' : ''; ?>><div class="sq-cd-n" id="cd-m" <?php echo $isLive ? 'style="color:#6ee7b7;"' : ''; ?>>--</div><div class="sq-cd-l">Mins</div></div>
                <div class="sq-cd-box" <?php echo $isLive ? 'style="background: rgba(6,78,59,0.6);"' : ''; ?>><div class="sq-cd-n" id="cd-s" <?php echo $isLive ? 'style="color:#6ee7b7;"' : ''; ?>>--</div><div class="sq-cd-l">Secs</div></div>
            </div>
        </div>
        <script>
        (function(){
            var t=<?php echo (int)$cdTargetTs;?>*1000;
            function tick(){
                var d=Math.max(0,t-Date.now());
                document.getElementById('cd-d').textContent=String(Math.floor(d/864e5)).padStart(2,'0');
                document.getElementById('cd-h').textContent=String(Math.floor(d%864e5/36e5)).padStart(2,'0');
                document.getElementById('cd-m').textContent=String(Math.floor(d%36e5/6e4)).padStart(2,'0');
                document.getElementById('cd-s').textContent=String(Math.floor(d%6e4/1e3)).padStart(2,'0');
                if(d>0)setTimeout(tick,1000);else location.reload();
            }
            tick();
        })();
        </script>
        <?php endif; ?>

    </div>

    <!-- ACCESS / PAYMENT STRIP — Inside hero bottom -->
    <?php if ($hasAttempted): ?>
        <!-- ========== COMPLETED / LOCKED STATE ========== -->
        <div style="background: linear-gradient(135deg, #064e3b, #065f46); border-top: 2px solid #10b981; padding: 2.5rem 0;">
            <div class="container">
                <div style="background: rgba(255,255,255,0.06); backdrop-filter: blur(10px); border: 1px solid rgba(16,185,129,0.2); border-radius: 24px; padding: 2.5rem; text-align: center;">
                    <!-- Check Icon -->
                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; box-shadow: 0 10px 30px rgba(16,185,129,0.3);">
                        <i class="fas fa-check" style="font-size: 2rem; color: #fff;"></i>
                    </div>
                    <h3 style="color: #fff; font-weight: 900; font-size: 1.5rem; margin-bottom: 0.5rem;">You've Completed This Quiz</h3>
                    <p style="color: #a7f3d0; font-size: 0.95rem; margin-bottom: 2rem;">You've already submitted your attempt. Each participant gets only one shot!</p>

                    <!-- Score & Rank Cards -->
                    <div class="d-flex justify-content-center gap-3 flex-wrap mb-4">
                        <div style="background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 1.25rem 2rem; min-width: 140px;">
                            <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #6ee7b7; margin-bottom: 0.4rem;">Your Score</div>
                            <div style="font-size: 2rem; font-weight: 900; color: #fff;"><?php echo $attemptScore; ?><span style="font-size: 1rem; color: #94a3b8;">/<?php echo $attemptTotal; ?></span></div>
                            <div style="font-size: 0.8rem; color: #a7f3d0;"><?php echo $attemptTotal > 0 ? round(($attemptScore / $attemptTotal) * 100) : 0; ?>%</div>
                        </div>
                        <?php if ($isChallenge && $userRank > 0): ?>
                        <div style="background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 1.25rem 2rem; min-width: 140px;">
                            <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #fbbf24; margin-bottom: 0.4rem;">Current Rank</div>
                            <div style="font-size: 2rem; font-weight: 900; color: #fbbf24;">#<?php echo $userRank; ?></div>
                            <div style="font-size: 0.8rem; color: #94a3b8;">of <?php echo $totalParticipants; ?> participant<?php echo $totalParticipants !== 1 ? 's' : ''; ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($isChallenge && $eventEnd && $now < $eventEnd): ?>
                    <div style="background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.2); border-radius: 14px; padding: 1rem 1.5rem; margin-bottom: 1.5rem; display: inline-block;">
                        <i class="fas fa-clock" style="color: #fbbf24; margin-right: 0.5rem;"></i>
                        <span style="color: #fde68a; font-weight: 700; font-size: 0.9rem;">The event is still live! Your final ranking will be revealed once it ends.</span>
                    </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-center gap-3 flex-wrap mt-3">
                        <?php if ($isChallenge): ?>
                            <a href="<?php echo SITE_URL; ?>/leaderboard.php?quiz_id=<?php echo $id; ?>" class="sq-start-btn" style="text-decoration:none;">
                                <i class="fas fa-trophy"></i> View Leaderboard
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo SITE_URL; ?>/quizzes.php" style="text-decoration:none; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; border-radius: 50px; padding: 0.75rem 2rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s;">
                            <i class="fas fa-th-large"></i> Browse Other Quizzes
                        </a>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($hasAccess): ?>
        <?php if ($isEnded): ?>
            <div class="sq-ended-strip">
                <div class="container">
                    <div class="sq-ended-inner">
                        <div class="sq-ended-text"><i class="fas fa-flag-checkered"></i> This quiz has ended. Check the results!</div>
                        <a href="<?php echo SITE_URL; ?>/leaderboard.php?quiz_id=<?php echo $id; ?>" class="sq-lb-btn">
                            <i class="fas fa-trophy"></i> View Leaderboard
                        </a>
                    </div>
                </div>
            </div>
        <?php elseif ($isUpcoming): ?>
            <div class="sq-granted-strip" style="background: linear-gradient(135deg, #1e293b, #252b48); border-color: #3b82f6;">
                <div class="container">
                    <div class="sq-granted-inner">
                        <div class="sq-granted-text" style="color:#93c5fd;">
                            <i class="fas fa-shield-check" style="color:#60a5fa;"></i>
                            <div>
                                <strong>Access Granted!</strong>
                                <?php if($userCodeStr): ?>
                                    <br><small style="color:#bfdbfe;">Code <strong><?php echo htmlspecialchars($userCodeStr); ?></strong> was automatically applied.</small>
                                <?php elseif($entryPrice > 0): ?>
                                    <br><small style="color:#bfdbfe;">Your payment was verified successfully.</small>
                                <?php else: ?>
                                    <br><small style="color:#bfdbfe;">You're all set for this challenge.</small>
                                <?php endif; ?>
                                <br><small style="font-weight:400;">Quiz starts when the countdown reaches zero.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="sq-granted-strip">
                <div class="container">
                    <div class="sq-granted-inner">
                        <div class="sq-granted-text">
                            <i class="fas fa-shield-check"></i>
                            <div>
                                <strong>Access Granted!</strong>
                                <?php if($userCodeStr): ?>
                                    <br><small style="color:#bfdbfe;">Code <strong style="letter-spacing:1.5px;"><?php echo htmlspecialchars($userCodeStr); ?></strong> was auto-applied.</small>
                                <?php elseif($entryPrice > 0): ?>
                                    <br><small style="color:#bfdbfe;">Your payment was verified successfully.</small>
                                <?php else: ?>
                                    <br><small style="color:#bfdbfe;">You're on the list.</small>
                                <?php endif; ?>
                                <br><small style="font-weight:400;">You're all set to take this quiz.</small>
                            </div>
                        </div>
                        <?php if (!$isChallenge || $isLive): ?>
                            <a href="<?php echo SITE_URL; ?>/quiz.php?id=<?php echo $id; ?>" class="sq-start-btn" data-event-end="<?php echo $eventEndTs ? $eventEndTs : ''; ?>" data-duration="<?php echo $duration; ?>">
                                <i class="fas fa-play"></i> Start Quiz Now
                            </a>
                        <?php else: ?>
                            <div style="background: rgba(255,255,255,0.1); padding: 0.75rem 1.5rem; border-radius: 50px; color: #fff; font-weight: 700;">
                                <i class="fas fa-hourglass-half"></i> Waiting for Challenge to Start...
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif ($hasJoined && $isUpcoming): ?>
        <div class="sq-granted-strip" style="background: linear-gradient(135deg, #1e293b, #252b48); border-color: #3b82f6;">
            <div class="container">
                <div class="sq-granted-inner">
                    <div class="sq-granted-text" style="color:#93c5fd;">
                        <i class="fas fa-calendar-check" style="color:#60a5fa;"></i>
                        <div>
                            <strong>You're In!</strong>
                            <br><small style="font-weight:400; color:#bfdbfe;">You've successfully joined the waitlist for this challenge. We'll notify you when it starts!</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($isEnded): ?>
        <div class="sq-ended-strip">
            <div class="container">
                <div class="sq-ended-inner">
                    <div class="sq-ended-text"><i class="fas fa-lock"></i> Registration is closed for this quiz.</div>
                    <a href="<?php echo SITE_URL; ?>/leaderboard.php?quiz_id=<?php echo $id; ?>" class="sq-lb-btn">
                        <i class="fas fa-trophy"></i> View Leaderboard
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Access / Join Strip -->
        <div class="sq-access-strip">
            <div class="container">
                <?php if (!empty($codeError)): ?>
                    <div class="sq-code-err mb-3"><i class="fas fa-exclamation-circle me-1"></i> <?php echo htmlspecialchars($codeError); ?></div>
                <?php endif; ?>
                <div class="sq-access">
            <?php if ($hasPendingPayment): ?>
                <!-- PENDING PAYMENT STATE -->
                <div class="alert mb-0" style="background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3); color: #fbbf24; border-radius: 14px; padding: 1.25rem;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="spinner-border spinner-border-sm" role="status" style="width: 1.5rem; height: 1.5rem;"></div>
                        <div>
                            <h5 class="mb-1 text-white fw-bold">Receipt Under Review</h5>
                            <p class="mb-0" style="font-size: 0.95rem;">Your payment receipt has been uploaded and is waiting for admin approval. We will notify you once your access code is ready.</p>
                        </div>
                    </div>
                </div>

            <?php elseif ($isFull): ?>
                <div class="alert mb-0 w-100 text-center" style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; border-radius: 14px; padding: 1.25rem;">
                    <h5 class="mb-1 fw-bold"><i class="fas fa-ban me-2"></i> Challenge is Full</h5>
                    <p class="mb-0" style="font-size: 0.95rem;">The maximum number of participants (<?php echo $maxParticipants; ?>) has been reached.</p>
                </div>

            <?php else: ?>
                <!-- ACCESS CODE + PAY / JOIN -->
                <div class="sq-access-inner">
                    <?php if ($entryPrice <= 0 && $isChallenge): ?>
                        <div class="w-100 text-center">
                            <form method="POST">
                                <input type="hidden" name="join_challenge" value="1">
                                <button type="submit" class="sq-start-btn" style="padding: 1rem 3rem; font-size: 1.1rem;">
                                    <i class="fas fa-sign-in-alt"></i> Join Challenge Waitlist
                                </button>
                            </form>
                            <p class="mt-2 mb-0" style="color: #94a3b8; font-size: 0.9rem;">Join now to reserve your spot before it fills up!</p>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <input type="text" name="access_code" class="sq-code-in" placeholder="Enter access code" maxlength="20" required>
                            <button type="submit" class="sq-code-btn"><i class="fas fa-unlock me-1"></i> Join</button>
                        </form>
                        <?php if ($entryPrice > 0): ?>
                            <span class="sq-or">or</span>
                            <div class="d-flex gap-2">
                                <button class="sq-pay-btn" onclick="payWithPaystack()" type="button">
                                    <i class="fas fa-credit-card"></i> Pay ₦<?php echo number_format($entryPrice, 0); ?>
                                </button>
                                <button class="sq-pay-btn" style="background: linear-gradient(135deg, #10b981, #059669);" onclick="openManualPaymentModal()" type="button">
                                    <i class="fas fa-university"></i> Bank Transfer
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="container py-4">

    <!-- About Toggle (mobile only) -->
    <button class="sq-about-toggle" onclick="this.classList.toggle('open');document.getElementById('aboutPanel').classList.toggle('open');">
        <i class="fas fa-info-circle me-1 text-primary"></i> About This Quiz
        <i class="fas fa-chevron-down arrow"></i>
    </button>

    <!-- About Card -->
    <div class="sq-about" id="aboutPanel">
        <h3 class="sq-about-title"><i class="fas fa-info-circle text-primary"></i> About This Quiz</h3>
        <div class="sq-about-body">
            <?php echo nl2br(htmlspecialchars($quiz['description'] ?? 'No description available.')); ?>
        </div>
        <ul class="sq-rules">
            <li>
                <div class="fw-bold mb-1"><i class="fas fa-coins text-warning me-2"></i> Cost to Join</div>
                <div class="text-muted small ps-4">
                    <?php if ($quiz['is_paid']): ?>
                        ₦<?php echo number_format($entryFee, 0); ?> Entry Fee
                    <?php else: ?>
                        Free to join
                    <?php endif; ?>
                </div>
            </li>
            <li>
                <div class="fw-bold mb-1"><i class="fas fa-clock text-primary me-2"></i> Time Allowed</div>
                <div class="text-muted small ps-4">
                    <?php echo $durationFormatted; ?> to complete <?php echo $questionCount; ?> questions.<br>
                    Timer starts exactly when you click "Start Quiz".
                </div>
            </li>
            <?php if ($hasPrize): ?>
            <li>
                <div class="fw-bold mb-1"><i class="fas fa-trophy text-success me-2"></i> Prize Pool & Breakdown</div>
                <div class="text-muted small ps-4">
                    <div class="mb-1">Total Pool: <strong><?php echo $prizeDisplay; ?></strong></div>
                    <?php if (!empty($quiz['prize_amount']) && $quiz['prize_amount'] > 0): ?>
                        <div class="d-flex align-items-center mb-1">
                            <i class="fas fa-medal me-2" style="color: #fbbf24;"></i> 1st Place (50%): <strong><?php echo format_prize($quiz['prize_amount'] * 0.50, $prizeType); ?></strong>
                        </div>
                        <div class="d-flex align-items-center mb-1">
                            <i class="fas fa-medal me-2" style="color: #94a3b8;"></i> 2nd Place (30%): <strong><?php echo format_prize($quiz['prize_amount'] * 0.30, $prizeType); ?></strong>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-medal me-2" style="color: #b45309;"></i> 3rd Place (20%): <strong><?php echo format_prize($quiz['prize_amount'] * 0.20, $prizeType); ?></strong>
                        </div>
                    <?php else: ?>
                        <div>Top scorers win the prize.</div>
                    <?php endif; ?>
                </div>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <?php if (!empty($quizStudyMaterials)): ?>
    <!-- Study Materials Mobile Toggle -->
    <button class="sq-about-toggle sq-materials-toggle" onclick="this.classList.toggle('open');document.getElementById('materialsPanel').classList.toggle('open');" style="border-left-color: #3b82f6;">
        <i class="fas fa-book-reader me-1" style="color: #3b82f6;"></i> Study Materials (<?php echo count($quizStudyMaterials); ?>)
        <i class="fas fa-chevron-down arrow"></i>
    </button>

    <!-- Study Materials Section -->
    <div class="sq-study-materials open" id="materialsPanel" style="border-left: 4px solid #3b82f6;">
        <h3 class="sq-about-title"><i class="fas fa-book-reader" style="color: #3b82f6;"></i> Study Materials</h3>
        <p class="text-muted mb-3" style="font-size: 0.85rem;">Download and study these materials — the quiz questions will be based on them.</p>
        <div class="list-group" style="gap: 0.6rem;">
            <?php foreach ($quizStudyMaterials as $qm):
                $ext = strtoupper(pathinfo($qm['file_path'], PATHINFO_EXTENSION));
                $sizeKB = $qm['file_size'] ? round($qm['file_size'] / 1024) : null;
                $sizeDisplay = $sizeKB ? ($sizeKB > 1024 ? round($sizeKB / 1024, 1) . ' MB' : $sizeKB . ' KB') : '';
                $iconClass = 'fas fa-file'; $iconColor = '#64748b';
                if (in_array($ext, ['PDF'])) { $iconClass = 'fas fa-file-pdf'; $iconColor = '#ef4444'; }
                if (in_array($ext, ['DOC','DOCX'])) { $iconClass = 'fas fa-file-word'; $iconColor = '#3b82f6'; }
                if (in_array($ext, ['PPT','PPTX'])) { $iconClass = 'fas fa-file-powerpoint'; $iconColor = '#f59e0b'; }
                if (in_array($ext, ['XLS','XLSX'])) { $iconClass = 'fas fa-file-excel'; $iconColor = '#10b981'; }
            ?>
            <div class="list-group-item border-0 d-flex align-items-center justify-content-between" style="background: #f8fafc; border-radius: 12px; padding: 0.85rem 1rem;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: <?php echo $iconColor; ?>15; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="<?php echo $iconClass; ?>" style="font-size: 1.1rem; color: <?php echo $iconColor; ?>;"></i>
                    </div>
                    <div>
                        <div class="fw-semibold" style="font-size: 0.88rem; color: #1e293b;"><?php echo htmlspecialchars($qm['title']); ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;"><?php echo $ext; ?><?php if ($sizeDisplay): ?> · <?php echo $sizeDisplay; ?><?php endif; ?></div>
                    </div>
                </div>
                <a href="<?php echo SITE_URL; ?>/download.php?id=<?php echo (int)$qm['id']; ?>" class="btn btn-sm px-3" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; border-radius: 8px; font-weight: 600; font-size: 0.78rem;">
                    <i class="fas fa-download me-1"></i> Download
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Manual Payment Modal -->
<div class="modal fade" id="manualPaymentModal" tabindex="-1" aria-labelledby="manualPaymentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.1);">
      <div class="modal-header" style="background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none;">
        <h5 class="modal-title fw-bold" id="manualPaymentModalLabel"><i class="fas fa-university me-2"></i> Bank Transfer</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <p class="text-muted mb-4">Please make a transfer of <strong>₦<?php echo number_format($entryFee, 0); ?></strong> to the account below and upload your receipt.</p>
        
        <div class="p-3 mb-4 rounded-3" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
            <div style="font-size: 0.85rem; color: #64748b; text-transform: uppercase; font-weight: 700;">Bank Name</div>
            <div style="font-size: 1.1rem; color: #0f172a; font-weight: 800; margin-bottom: 0.5rem;">Access Bank</div>
            <div style="font-size: 0.85rem; color: #64748b; text-transform: uppercase; font-weight: 700;">Account Number</div>
            <div style="font-size: 1.3rem; color: #0f172a; font-weight: 900; letter-spacing: 2px; margin-bottom: 0.5rem;">0123456789</div>
            <div style="font-size: 0.85rem; color: #64748b; text-transform: uppercase; font-weight: 700;">Account Name</div>
            <div style="font-size: 1.1rem; color: #0f172a; font-weight: 800;">MyStudy Educational Ltd</div>
        </div>

        <form id="receiptUploadForm" onsubmit="submitReceipt(event)">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="quiz_id" value="<?php echo $id; ?>">
            <input type="hidden" name="amount" value="<?php echo $entryFee; ?>">
            
            <div class="mb-3 text-start">
                <label for="receipt" class="form-label fw-bold text-dark">Upload Payment Receipt</label>
                <input class="form-control form-control-lg" type="file" id="receipt" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required>
                <div class="form-text">Max size: 1MB. Accepted formats: JPG, PNG, PDF.</div>
            </div>
            
            <button type="submit" class="btn btn-success w-100 py-2 fw-bold" id="btnUploadReceipt" style="border-radius: 12px;">
                <i class="fas fa-cloud-upload-alt me-2"></i> Submit Receipt for Verification
            </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Receipt Upload Success Modal -->
<div class="modal fade" id="receiptSuccessModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="border-radius: 24px; border: none; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.15); text-align: center;">
      <div class="modal-body p-4">
        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; box-shadow: 0 8px 25px rgba(16,185,129,0.3);">
          <i class="fas fa-check" style="font-size: 2rem; color: #fff;"></i>
        </div>
        <h5 class="fw-bold mb-2" style="color: #0f172a;">Receipt Submitted!</h5>
        <p class="text-muted mb-3" style="font-size: 0.9rem;">Your payment receipt has been uploaded successfully. An admin will review it and grant your access code shortly.</p>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 0.75rem; color: #15803d; font-weight: 600; font-size: 0.85rem;">
          <i class="fas fa-sync-alt fa-spin me-1"></i> Refreshing in <span id="receiptCountdown">3</span>s...
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
function payWithPaystack() {
    var paystackDone = false;
    var handler = PaystackPop.setup({
        key: <?php echo json_encode(PAYSTACK_PUBLIC_KEY); ?>,
        email: <?php echo json_encode($userEmail); ?>,
        amount: <?php echo (int)$entryFeeKobo; ?>,
        currency: 'NGN',
        ref: <?php echo json_encode($paystackRef); ?>,
        metadata: {
            quiz_id: <?php echo (int)$id; ?>,
            user_id: <?php echo (int)$userId; ?>,
            custom_fields: [
                { display_name: 'Quiz', variable_name: 'quiz', value: <?php echo json_encode($quiz['title']); ?> },
                { display_name: 'Student', variable_name: 'student', value: <?php echo json_encode($userName); ?> }
            ]
        },
        callback: function (response) {
            paystackDone = true;
            window.location.href = <?php echo json_encode(SITE_URL . '/verify_redirect.php?quiz_id=' . $id); ?> + '&reference=' + encodeURIComponent(response.reference);
        },
        onClose: function () {
            if (!paystackDone) {
                alert('Payment was not completed. You can try again.');
            }
        }
    });
    handler.openIframe();
}

var manualModal = null;
function openManualPaymentModal() {
    if (!manualModal) {
        manualModal = new bootstrap.Modal(document.getElementById('manualPaymentModal'));
    }
    manualModal.show();
}

function submitReceipt(e) {
    e.preventDefault();
    var form = document.getElementById('receiptUploadForm');
    var btn = document.getElementById('btnUploadReceipt');
    var formData = new FormData(form);
    
    // Check file size (client side)
    var fileInput = document.getElementById('receipt');
    if (fileInput.files.length > 0) {
        var fileSize = fileInput.files[0].size;
        if (fileSize > 1048576) { // 1MB limit
            alert("File is too large. Maximum size is 1MB.");
            return;
        }
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Uploading...';
    
    fetch('<?php echo SITE_URL; ?>/upload_receipt.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cloud-upload-alt me-2"></i> Submit Receipt for Verification';
        
        if (data.success) {
            // Hide manual payment modal
            manualModal.hide();
            form.reset();
            // Show success modal
            var successModal = new bootstrap.Modal(document.getElementById('receiptSuccessModal'));
            successModal.show();
            // Countdown and auto-reload
            var count = 3;
            var countEl = document.getElementById('receiptCountdown');
            var timer = setInterval(function() {
                count--;
                if (countEl) countEl.textContent = count;
                if (count <= 0) {
                    clearInterval(timer);
                    window.location.reload();
                }
            }, 1000);
        } else {
            alert(data.error || "An error occurred during upload.");
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cloud-upload-alt me-2"></i> Submit Receipt for Verification';
        alert("Network error. Please try again.");
    });
}

// Pre-quiz Warning Modal for ending-soon challenges
document.querySelectorAll('.sq-start-btn').forEach(function(btn) {
    if(!btn.hasAttribute('data-event-end')) return;
    btn.addEventListener('click', function(e) {
        var eventEnd = btn.getAttribute('data-event-end');
        var duration = parseInt(btn.getAttribute('data-duration'), 10);
        if(!eventEnd) return;
        var now = Math.floor(Date.now() / 1000);
        var timeLeft = parseInt(eventEnd, 10) - now;
        
        if (timeLeft > 0 && timeLeft < duration) {
            e.preventDefault();
            var minsLeft = Math.ceil(timeLeft / 60);
            var durMins = Math.ceil(duration / 60);
            Swal.fire({
                title: 'Event Ends Soon!',
                html: 'This challenge ends in roughly <b>' + minsLeft + ' minutes</b>.<br>You will not have the full ' + durMins + ' minutes to complete it. Your quiz will automatically submit when the event ends.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Start Anyway',
                confirmButtonColor: '#10b981',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = btn.href;
                }
            });
        }
    });
});
</script>

<?php if ($typeRestricted): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({
        title: '<i class="fas fa-ban" style="color:#ef4444;"></i> Access Restricted',
        html: '<div style="text-align:center;"><p style="font-size:1.1rem;margin-bottom:0.5rem;">This quiz is for <strong><?php echo ucfirst(htmlspecialchars($quizType)); ?></strong> students only.</p><p style="color:#64748b;">Your account is registered as a <strong><?php echo ucfirst(htmlspecialchars($userType)); ?></strong> student, so you cannot participate in this challenge.</p></div>',
        icon: null,
        showConfirmButton: true,
        confirmButtonText: '<i class="fas fa-arrow-left me-2"></i> Go Back to Quizzes',
        confirmButtonColor: '#10b981',
        allowOutsideClick: false,
        allowEscapeKey: false,
        customClass: {popup: 'rounded-4'}
    }).then(function() {
        window.location.href = '<?php echo SITE_URL; ?>/quizzes.php';
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
