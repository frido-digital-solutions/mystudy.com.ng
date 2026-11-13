<?php
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/models/QuizAttempt.php';

$page_title = 'Leaderboard';
$quizModel = new Quiz($pdo);
$attemptModel = new QuizAttempt($pdo);

// Get all quizzes
$sql = "SELECT id, title, mode, is_paid FROM quizzes ORDER BY created_at DESC";
$stmt = $pdo->query($sql);
$allQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$specialQuizzes = [];
$normalQuizzes = [];
foreach ($allQuizzes as $q) {
    if (!empty($q['is_paid']) || $q['mode'] === 'challenge') {
        $specialQuizzes[] = $q;
    } else {
        $normalQuizzes[] = $q;
    }
}

$selectedQuizId = (int)($_GET['quiz_id'] ?? 0);

$selectedQuiz = null;
$isPremiumQuiz = false;
$activeTab = 'special';

if ($selectedQuizId > 0) {
    $selectedQuiz = $quizModel->getById($selectedQuizId);
    if ($selectedQuiz) {
        $isPremiumQuiz = (!empty($selectedQuiz['is_paid']) || $selectedQuiz['mode'] === 'challenge');
        $activeTab = $isPremiumQuiz ? 'special' : 'normal';
    }
} else {
    // Default fallback if no valid quiz_id provided
    if (!empty($specialQuizzes)) {
        $selectedQuizId = (int)$specialQuizzes[0]['id'];
        $selectedQuiz = $quizModel->getById($selectedQuizId);
        $isPremiumQuiz = true;
        $activeTab = 'special';
    } elseif (!empty($normalQuizzes)) {
        $selectedQuizId = (int)$normalQuizzes[0]['id'];
        $selectedQuiz = $quizModel->getById($selectedQuizId);
        $isPremiumQuiz = false;
        $activeTab = 'normal';
    }
}

// Get leaderboard data (top 50)
$leaderboard = $selectedQuizId > 0 ? $attemptModel->topScores($selectedQuizId, null, 50) : [];
$totalParticipants = count($leaderboard);

// Current user rank
$currentUserId = (int)$_SESSION['user_id'];
$myRank = null;
$myEntry = null;
foreach ($leaderboard as $i => $entry) {
    if ((int)($entry['user_id'] ?? 0) === $currentUserId) {
        $myRank = $i + 1;
        $myEntry = $entry;
        break;
    }
}

ob_start();
?>
<style>
/* ========================================= */
/* LEADERBOARD — CLEAN PREMIUM DESIGN        */
/* ========================================= */

/* Hero with integrated selector */
.lb-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    padding: 2.5rem 0 2rem;
    position: relative;
    overflow: hidden;
}

.lb-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(251, 191, 36, 0.1) 0%, transparent 70%);
    pointer-events: none;
}

.lb-hero-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    z-index: 1;
    margin-bottom: 1.5rem;
}

.lb-hero-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.lb-hero-icon {
    font-size: 2.5rem;
    color: #fbbf24;
    animation: trophyGlow 2s ease-in-out infinite;
}

@keyframes trophyGlow {
    0%, 100% { filter: drop-shadow(0 0 6px rgba(251, 191, 36, 0.3)); }
    50% { filter: drop-shadow(0 0 18px rgba(251, 191, 36, 0.6)); }
}

.lb-hero-title {
    font-size: 1.75rem;
    font-weight: 900;
    color: #fff;
    margin: 0;
    letter-spacing: -0.5px;
}

.lb-hero-sub {
    font-size: 0.9rem;
    color: #94a3b8;
    margin: 0;
}

/* Compact Dropdown Selector */
.lb-select-wrap {
    position: relative;
    z-index: 1;
}

.lb-select {
    appearance: none;
    -webkit-appearance: none;
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 12px;
    padding: 0.65rem 2.5rem 0.65rem 1rem;
    color: #fff;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    min-width: 220px;
    transition: all 0.2s ease;
}

.lb-select:hover, .lb-select:focus {
    border-color: #fbbf24;
    background: rgba(255,255,255,0.15);
    outline: none;
}

.lb-select option {
    background: #1e293b;
    color: #fff;
}

.lb-select-arrow {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #fbbf24;
    font-size: 0.8rem;
    pointer-events: none;
}

/* Custom Toggle Segmented Control */
.lb-toggle-container {
    background: rgba(255,255,255,0.05); /* very subtle dark bg */
    border: 1px solid rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    border-radius: 50px;
    padding: 0.35rem;
    display: inline-flex;
    position: relative;
    margin-bottom: 1.25rem;
    width: 100%;
    max-width: 400px;
}

/* The sliding active pill */
.lb-toggle-slider {
    position: absolute;
    top: 5px;
    bottom: 5px;
    width: calc((100% - 0.7rem) / 2);
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    border-radius: 50px;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1;
    box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
}

body:not(.special-leaderboard-page) .lb-toggle-slider {
    background: linear-gradient(135deg, #10b981, #059669);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.lb-toggle-btn {
    flex: 1;
    background: transparent;
    border: none;
    color: #94a3b8;
    padding: 0.6rem 0;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    position: relative;
    z-index: 2;
    transition: color 0.3s ease;
    text-align: center;
}

.lb-toggle-btn.active {
    color: #fff;
}

/* Quiz Meta Bar — below hero */
.lb-meta {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    position: relative;
    z-index: 1;
    padding: 0.875rem 0;
}

.lb-meta-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.lb-meta-tags {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.lb-meta-tag {
    background: rgba(255,255,255,0.2);
    color: #fff;
    padding: 0.3rem 0.85rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.lb-meta-tag.prize {
    background: rgba(251, 191, 36, 0.35);
    border: 1px solid rgba(251, 191, 36, 0.5);
}

.lb-meta-count {
    color: rgba(255,255,255,0.9);
    font-weight: 700;
    font-size: 0.85rem;
}

/* ========================================= */
/* MY RANK STRIP                             */
/* ========================================= */
.lb-my-rank {
    background: #fff;
    border: 2px solid #10b981;
    border-radius: 16px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.15);
}

.lb-my-rank-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #1e293b;
    font-weight: 700;
}

.lb-my-rank-badge {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    font-size: 1.1rem;
}

.lb-my-rank-score {
    color: #10b981;
    font-weight: 800;
    font-size: 1.1rem;
}

/* ========================================= */
/* PODIUM — TOP 3                           */
/* ========================================= */
.lb-podium {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 0.75rem;
    margin-bottom: 2rem;
    padding: 1rem 0;
}

.pod {
    background: #fff;
    border-radius: 20px;
    padding: 1.5rem 1rem 1.25rem;
    text-align: center;
    position: relative;
    box-shadow: 0 8px 25px rgba(0,0,0,0.06);
    transition: all 0.3s ease;
    flex: 1;
    min-width: 0;
    max-width: 180px;
}

.pod:hover { transform: translateY(-5px); box-shadow: 0 12px 35px rgba(0,0,0,0.1); }

/* Order: silver | gold | bronze */
.pod.p1 { order: 2; border: 2px solid #fbbf24; background: linear-gradient(180deg, #fef3c7 0%, #fefce8 100%); }
.pod.p2 { order: 1; border: 2px solid #94a3b8; background: linear-gradient(180deg, #e2e8f0 0%, #f1f5f9 100%); }
.pod.p3 { order: 3; border: 2px solid #cd7f32; background: linear-gradient(180deg, #fed7aa 0%, #fff7ed 100%); }

.pod-crown {
    position: absolute;
    top: -16px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 1.5rem;
}

.pod.p1 .pod-crown { color: #fbbf24; }
.pod.p2 .pod-crown { color: #94a3b8; }
.pod.p3 .pod-crown { color: #cd7f32; }

.pod-num {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    font-size: 0.9rem;
    color: #fff;
    margin: 0 auto 0.5rem;
}

.pod.p1 .pod-num { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.pod.p2 .pod-num { background: linear-gradient(135deg, #94a3b8, #64748b); }
.pod.p3 .pod-num { background: linear-gradient(135deg, #cd7f32, #b8722d); }

.pod-av {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    margin: 0 auto 0.5rem;
    overflow: hidden;
}

.pod.p1 .pod-av { border: 3px solid #fbbf24; }
.pod.p2 .pod-av { border: 3px solid #94a3b8; }
.pod.p3 .pod-av { border: 3px solid #cd7f32; }

.pod-av img { width: 100%; height: 100%; object-fit: cover; }

.pod-av-init {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1.1rem;
    color: #fff;
}

.pod.p1 .pod-av-init { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.pod.p2 .pod-av-init { background: linear-gradient(135deg, #94a3b8, #64748b); }
.pod.p3 .pod-av-init { background: linear-gradient(135deg, #cd7f32, #b8722d); }

.pod-name {
    font-weight: 800;
    font-size: 0.85rem;
    color: #1e293b;
    margin-bottom: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    text-decoration: none;
}
.pod-name:hover { text-decoration: underline; color: #10b981; }

.pod-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; margin-top: 0.4rem; margin-bottom: 0.1rem; }
.pod-score { font-weight: 700; font-size: 0.8rem; color: #10b981; }
.pod-time { font-size: 0.75rem; color: #94a3b8; }

/* ========================================= */
/* RANK LIST (4+)                           */
/* ========================================= */
.lb-table {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    overflow: hidden;
}

.lb-table-head {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.25rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #94a3b8;
}

.lb-table-head .th-rank { width: 50px; }
.lb-table-head .th-user { flex: 1; }
.lb-table-head .th-score { width: 80px; text-align: center; }
.lb-table-head .th-time { width: 70px; text-align: center; }

.lb-r {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.2s ease;
}

.lb-r:last-child { border-bottom: none; }

.lb-r:hover {
    background: #fafbfc;
}

.lb-r.me {
    background: #f0fdf4;
    border-left: 3px solid #10b981;
}

.lb-r-rank {
    width: 50px;
    font-weight: 800;
    font-size: 0.95rem;
    color: #64748b;
    flex-shrink: 0;
}

.lb-r-user {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.lb-r-av {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid #e2e8f0;
}

.lb-r-av img { width: 100%; height: 100%; object-fit: cover; }

.lb-r-av-init {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    font-weight: 700;
    font-size: 0.75rem;
}

.lb-r-name {
    font-weight: 700;
    font-size: 0.9rem;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-decoration: none;
}
.lb-r-name:hover { text-decoration: underline; color: #10b981; }

.lb-r-score {
    width: 80px;
    text-align: center;
    font-weight: 800;
    font-size: 0.9rem;
    color: #10b981;
    flex-shrink: 0;
}

.lb-r-time {
    width: 70px;
    text-align: center;
    font-weight: 600;
    font-size: 0.85rem;
    color: #64748b;
    flex-shrink: 0;
}

/* Empty State */
.lb-empty {
    text-align: center;
    padding: 4rem 2rem;
}

.lb-empty-icon { font-size: 3.5rem; color: #cbd5e1; margin-bottom: 1rem; }
.lb-empty h4 { font-weight: 800; color: #1e293b; margin-bottom: 0.5rem; }
.lb-empty p { color: #64748b; max-width: 360px; margin: 0 auto; }

.lb-cta {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1.5rem;
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    padding: 0.7rem 1.5rem;
    border-radius: 50px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
}

.lb-cta:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4); color: #fff; text-decoration: none; }

/* Responsive */
@media (max-width: 768px) {
    .lb-hero { padding: 1.5rem 0; }
    .lb-hero-top { flex-direction: column; align-items: stretch; gap: 1rem; margin-bottom: 1.5rem !important; text-align: center; }
    .lb-hero-left { margin-bottom: 0 !important; justify-content: center; }
    .lb-hero-title { font-size: 1.5rem; }
    .lb-controls-wrap { margin-left: 0 !important; }
    .lb-select-wrap { width: 100%; }
    .lb-select { width: 100%; min-width: 100%; margin-bottom: 0; }
    .lb-toggle-container { max-width: 100%; margin-bottom: 1rem; }
    /* Top 3: champion on its own row, runners-up side by side, instead of three
       squeezed columns. The desktop order trick is reset so 1/2/3 keep their rows. */
    .lb-podium { flex-wrap: wrap; align-items: stretch; gap: 0.75rem; padding: 1.25rem 0 0.25rem; }
    .pod { flex: 1 1 auto; max-width: none; min-width: 0; padding: 1.15rem 0.75rem; }
    .pod.p1 { order: 1; flex: 1 1 100%; }
    .pod.p2 { order: 2; flex: 1 1 calc(50% - 0.4rem); }
    .pod.p3 { order: 3; flex: 1 1 calc(50% - 0.4rem); }
    .pod-av { width: 46px; height: 46px; }
    .pod.p1 .pod-av { width: 62px; height: 62px; }
    .pod.p1 .pod-name { font-size: 1rem; }
    .pod-name { font-size: 0.8rem; }
    .pod-crown { font-size: 1.15rem; top: -12px; }
    .lb-meta-inner { flex-direction: column; align-items: flex-start; }
    .lb-my-rank { flex-direction: column; gap: 0.75rem; text-align: center; }
}
/* ========================================= */
/* SPECIAL LEADERBOARD — MEDIUM GOLD VARIANT */
/* ========================================= */
body.special-leaderboard-page {
    background: linear-gradient(135deg, #334155 0%, #1e293b 100%); /* Medium slate background */
    color: #f8fafc;
}

/* Hero Section Overrides */
.special-leaderboard-page .lb-hero { background: linear-gradient(135deg, rgba(51, 65, 85, 0.9) 0%, rgba(30, 41, 59, 0.9) 100%); border-bottom: 1px solid rgba(251, 191, 36, 0.15); }
.special-leaderboard-page .lb-hero-title { color: #f8fafc; }
.special-leaderboard-page .lb-hero-sub { color: #fbbf24; opacity: 0.9; }
.special-leaderboard-page .lb-hero-icon { color: #fbbf24; animation: none; filter: drop-shadow(0 2px 8px rgba(251, 191, 36, 0.4)); }
.special-leaderboard-page .lb-select { background: rgba(15, 23, 42, 0.6); border-color: rgba(251, 191, 36, 0.3); color: #f8fafc; backdrop-filter: blur(8px); }
.special-leaderboard-page .lb-select:hover, .special-leaderboard-page .lb-select:focus { border-color: #fbbf24; background: rgba(15, 23, 42, 0.8); }
.special-leaderboard-page .lb-select option { background: #1e293b; color: #f8fafc; }
.special-leaderboard-page .lb-toggle-container { background: rgba(15, 23, 42, 0.4); border-color: rgba(251, 191, 36, 0.2); }
.special-leaderboard-page .lb-toggle-btn { color: #cbd5e1; }
.special-leaderboard-page .lb-toggle-btn.active { color: #fff; }

/* Meta Bar Overrides */
.special-leaderboard-page .lb-meta { background: rgba(30, 41, 59, 0.75); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid rgba(251, 191, 36, 0.15); }
.special-leaderboard-page .lb-meta-tag { background: rgba(15, 23, 42, 0.5); color: #f8fafc; border: 1px solid rgba(255,255,255,0.05); }
.special-leaderboard-page .lb-meta-tag i { color: #fbbf24; }
.special-leaderboard-page .lb-meta-tag.prize { background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); color: #fbbf24; }
.special-leaderboard-page .lb-meta-count { color: #fcd34d; font-weight: 600; }

/* Table Overrides */
.special-leaderboard-page .lb-table { background: rgba(30, 41, 59, 0.6); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 40px rgba(0,0,0,0.25); }
.special-leaderboard-page .lb-table-head { background: rgba(15, 23, 42, 0.4); border-bottom: 1px solid rgba(255,255,255,0.05); color: #cbd5e1; }
.special-leaderboard-page .lb-r { border-bottom: 1px solid rgba(255,255,255,0.03); }
.special-leaderboard-page .lb-r:hover { background: rgba(255,255,255,0.05); }
.special-leaderboard-page .lb-r.me { background: rgba(245, 158, 11, 0.1); border-left: 3px solid #fbbf24; }
.special-leaderboard-page .lb-r-name { color: #f8fafc; }
.special-leaderboard-page .lb-r-score { color: #fbbf24; font-weight: 800; }
.special-leaderboard-page .lb-r-time { color: #94a3b8; }
.special-leaderboard-page .lb-r-rank { color: #cbd5e1; }

/* Podiums Overrides */
.special-leaderboard-page .pod { background: linear-gradient(135deg, rgba(51, 65, 85, 0.8) 0%, rgba(30, 41, 59, 0.8) 100%); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.special-leaderboard-page .pod-name { color: #f8fafc; }
.special-leaderboard-page .pod-name:hover { color: #fbbf24; }
.special-leaderboard-page .pod-score { color: #fbbf24; font-weight: 800; }

/* My Rank Overrides */
.special-leaderboard-page .lb-my-rank { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); border-left: 4px solid #fbbf24; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
.special-leaderboard-page .lb-my-rank-left { color: #f8fafc; }
.special-leaderboard-page .lb-my-rank-badge { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
.special-leaderboard-page .lb-my-rank-score { color: #fbbf24; font-weight: 800; }
</style>
<?php
$page_scripts = ($page_scripts ?? '') . ob_get_clean();
include __DIR__ . '/includes/header.php';
?>

<?php if (!empty($isPremiumQuiz)): ?>
<script>document.body.classList.add('special-leaderboard-page');</script>
<?php endif; ?>

<!-- HERO with integrated dropdown -->
<div class="lb-hero">
    <div class="container">
        <div class="lb-hero-top">
            <div class="lb-hero-left" style="margin-bottom: <?php echo (!empty($allQuizzes)) ? '0' : '1.5rem'; ?>;">
                <div class="lb-hero-icon"><i class="fas fa-trophy"></i></div>
                <div>
                    <h1 class="lb-hero-title">Leaderboard</h1>
                    <p class="lb-hero-sub">Compete with the best, climb to the top</p>
                </div>
            </div>
            <?php if (!empty($allQuizzes)): ?>
            <div style="display:flex; flex-direction:column; align-items:center; width: 100%; max-width: 400px; margin: 0 auto;" class="lb-controls-wrap">
                <div class="lb-toggle-container">
                    <div class="lb-toggle-slider" id="lbToggleSlider"></div>
                    <button type="button" class="lb-toggle-btn <?php echo $activeTab === 'special' ? 'active' : ''; ?>" data-tab="special" onclick="lbSwitchTab('special', 0)">Special Quizzes</button>
                    <button type="button" class="lb-toggle-btn <?php echo $activeTab === 'normal' ? 'active' : ''; ?>" data-tab="normal" onclick="lbSwitchTab('normal', 1)">Normal Quizzes</button>
                </div>
                <div class="lb-select-wrap" style="width: 100%;">
                    <select class="lb-select" id="lbSelect" onchange="window.location='?quiz_id='+this.value">
                        <!-- Options injected by JS based on tab -->
                    </select>
                    <span class="lb-select-arrow"><i class="fas fa-chevron-down"></i></span>
                </div>
            </div>
            
            <script>
            const quizesData = {
                special: <?php echo json_encode($specialQuizzes); ?>,
                normal: <?php echo json_encode($normalQuizzes); ?>
            };
            const currentSelected = <?php echo $selectedQuizId; ?>;
            
            function lbSwitchTab(tabName, index) {
                // Animate slider
                const slider = document.getElementById('lbToggleSlider');
                slider.style.transform = `translateX(${index * 100}%)`;
                
                // Update buttons active class
                document.querySelectorAll('.lb-toggle-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelector(`.lb-toggle-btn[data-tab="${tabName}"]`).classList.add('active');
                
                // Update select options
                const selectEl = document.getElementById('lbSelect');
                selectEl.innerHTML = '';
                
                const list = quizesData[tabName] || [];
                if (list.length === 0) {
                    selectEl.innerHTML = '<option value="">No ' + tabName + ' quizzes available</option>';
                    return;
                }
                
                let foundCurrent = false;
                list.forEach(q => {
                    const opt = document.createElement('option');
                    opt.value = q.id;
                    opt.textContent = q.title;
                    if (parseInt(q.id) === currentSelected) {
                        opt.selected = true;
                        foundCurrent = true;
                    }
                    selectEl.appendChild(opt);
                });
                
                // Only force redirect if the user clicked the toggle MANUALLY to switch tabs, AND the currently loaded quiz isn't in this new tab.
                if (!foundCurrent && tabName !== '<?php echo $activeTab; ?>') {
                     window.location = '?quiz_id=' + list[0].id; // selects the newest
                }
            }
            
            // Initialize on load
            document.addEventListener("DOMContentLoaded", () => {
                const initTab = '<?php echo $activeTab; ?>';
                const initIndex = initTab === 'special' ? 0 : 1;
                
                // Move slider immediately without animation
                const slider = document.getElementById('lbToggleSlider');
                slider.style.transition = 'none';
                slider.style.transform = `translateX(${initIndex * 100}%)`;
                
                // Restore animation after a tiny delay
                setTimeout(() => { slider.style.transition = 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)'; }, 50);
                
                lbSwitchTab(initTab, initIndex);
            });
            </script>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($selectedQuiz): ?>
<!-- Quiz Meta Bar -->
<div class="lb-meta">
    <div class="container">
        <div class="lb-meta-inner">
            <div class="lb-meta-tags">
                <?php if (!empty($selectedQuiz['subject'])): ?>
                    <span class="lb-meta-tag"><i class="fas fa-book"></i> <?php echo htmlspecialchars($selectedQuiz['subject']); ?></span>
                <?php endif; ?>
                <?php if (!empty($selectedQuiz['level'])): ?>
                    <span class="lb-meta-tag"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($selectedQuiz['level']); ?></span>
                <?php endif; ?>
                <?php if (!empty($selectedQuiz['prize_amount'])): ?>
                    <span class="lb-meta-tag prize"><i class="fas fa-trophy"></i> Prize: <?php echo format_prize($selectedQuiz['prize_amount'], $selectedQuiz['prize_type'] ?? 'money'); ?></span>
                <?php elseif (!empty($selectedQuiz['prize_text'])): ?>
                    <span class="lb-meta-tag prize"><i class="fas fa-gift"></i> <?php echo htmlspecialchars($selectedQuiz['prize_text']); ?></span>
                <?php endif; ?>
            </div>
            <div class="lb-meta-count">
                <i class="fas fa-users me-1"></i> <?php echo $totalParticipants; ?> participant<?php echo $totalParticipants !== 1 ? 's' : ''; ?>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">

    <?php if (empty($leaderboard)): ?>
    <div class="lb-table">
        <div class="lb-empty">
            <div class="lb-empty-icon"><i class="fas fa-users-slash"></i></div>
            <h4>No Participants Yet</h4>
            <p>Be the first to take this challenge and claim the top spot!</p>
            <a href="<?php echo SITE_URL; ?>/quiz.php?id=<?php echo $selectedQuizId; ?>" class="lb-cta">
                <i class="fas fa-play"></i> Take This Challenge
            </a>
        </div>
    </div>
    <?php else: ?>

    <!-- My Rank Strip -->
    <?php if ($myRank && $myEntry): ?>
    <div class="lb-my-rank">
        <div class="lb-my-rank-left">
            <div class="lb-my-rank-badge">#<?php echo $myRank; ?></div>
            <span>Your Position</span>
        </div>
        <div class="lb-my-rank-score">
            <?php echo (int)$myEntry['score']; ?>/<?php echo (int)$myEntry['total']; ?>
            <span style="color:#94a3b8; font-weight:600; font-size:0.85rem; margin-left:0.5rem;">
                <i class="fas fa-clock"></i> <?php echo gmdate('i:s', (int)$myEntry['duration_used']); ?>
            </span>
        </div>
    </div>
    <?php endif; ?>

    <!-- PODIUM — Top 3 -->
    <?php $top3 = array_slice($leaderboard, 0, min(3, count($leaderboard))); ?>
    <?php if (count($top3) >= 1): ?>
    <div class="lb-podium">
        <?php
        $podClasses = ['p1', 'p2', 'p3'];
        $crownIcons = ['fa-crown', 'fa-medal', 'fa-award'];
        foreach ($top3 as $idx => $e):
            $initials = strtoupper(substr($e['name'], 0, 2));
            $pic = !empty($e['profile_picture']) ? $e['profile_picture'] : '';
            $hasPic = !empty($pic) && file_exists(__DIR__ . '/' . $pic);
            $time = gmdate('i:s', (int)$e['duration_used']);
            $pct = (int)$e['total'] > 0 ? round(((int)$e['score'] / (int)$e['total']) * 100) : 0;
        ?>
        <div class="pod <?php echo $podClasses[$idx]; ?>">
            <div class="pod-crown"><i class="fas <?php echo $crownIcons[$idx]; ?>"></i></div>
            <div class="pod-num"><?php echo $idx + 1; ?></div>
            <a href="<?php echo SITE_URL; ?>/profile_public.php?id=<?php echo htmlspecialchars($e['user_id']); ?>" style="text-decoration:none; color:inherit; display:block;">
                <div class="pod-av">
                    <?php if ($hasPic): ?>
                        <img src="<?php echo SITE_URL . '/' . htmlspecialchars($pic); ?>" alt="Avatar">
                    <?php else: ?>
                        <div class="pod-av-init"><?php echo htmlspecialchars($initials); ?></div>
                    <?php endif; ?>
                </div>
                <div class="pod-name" title="<?php echo htmlspecialchars($e['name']); ?>"><?php echo htmlspecialchars($e['name']); ?></div>
            </a>
            <div class="pod-label">Score</div>
            <div class="pod-score"><?php echo (int)$e['score']; ?>/<?php echo (int)$e['total']; ?> (<?php echo $pct; ?>%)</div>
            <div class="pod-label">Time</div>
            <div class="pod-time"><i class="fas fa-clock me-1"></i><?php echo $time; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Full Rankings Table -->
    <?php $rest = array_slice($leaderboard, 3); ?>
    <?php if (!empty($rest)): ?>
    <div class="lb-table">
        <div class="lb-table-head">
            <span class="th-rank">Rank</span>
            <span class="th-user">Player</span>
            <span class="th-score">Score</span>
            <span class="th-time">Time</span>
        </div>
        <?php foreach ($rest as $idx => $e):
            $rank = $idx + 4;
            $initials = strtoupper(substr($e['name'], 0, 2));
            $pic = !empty($e['profile_picture']) ? $e['profile_picture'] : '';
            $hasPic = !empty($pic) && file_exists(__DIR__ . '/' . $pic);
            $time = gmdate('i:s', (int)$e['duration_used']);
            $isMe = ((int)($e['user_id'] ?? 0) === $currentUserId);
        ?>
        <div class="lb-r <?php echo $isMe ? 'me' : ''; ?>">
            <div class="lb-r-rank">#<?php echo $idx + 4; ?></div>
            <a href="<?php echo SITE_URL; ?>/profile_public.php?id=<?php echo htmlspecialchars($e['user_id']); ?>" class="lb-r-user" style="text-decoration:none; color:inherit;">
                <div class="lb-r-av">
                    <?php if ($hasPic): ?>
                        <img src="<?php echo SITE_URL . '/' . htmlspecialchars($pic); ?>" alt="Avatar">
                    <?php else: ?>
                        <div class="lb-r-av-init" <?php echo !empty($isPremiumQuiz) ? 'style="background:rgba(245,158,11,0.2); color:#fbbf24;"' : ''; ?>><?php echo htmlspecialchars($initials); ?></div>
                    <?php endif; ?>
                </div>
                <div class="lb-r-name" title="<?php echo htmlspecialchars($e['name']); ?>">
                    <?php echo htmlspecialchars($e['name']); ?> <?php if ($isMe): ?><span class="badge bg-success ms-1" style="font-size:0.65rem;">You</span><?php endif; ?>
                </div>
            </a>
            <div class="lb-r-score"><?php echo (int)$e['score']; ?>/<?php echo (int)$e['total']; ?></div>
            <div class="lb-r-time"><?php echo $time; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php else: ?>
<!-- No challenges -->
<div class="container py-5">
    <div class="lb-table">
        <div class="lb-empty">
            <div class="lb-empty-icon"><i class="fas fa-list-check"></i></div>
            <h4>No Challenges Available</h4>
            <p>There are no challenge quizzes yet. Check back soon!</p>
            <a href="<?php echo SITE_URL; ?>/quizzes.php" class="lb-cta">
                <i class="fas fa-arrow-left"></i> Browse Quizzes
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
