<?php
// quiz_battle_play.php - The Standalone Premium Battle Interface
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/models/Battle.php';
require_once __DIR__ . '/models/User.php';

require_login();

$battleId = (int)($_SESSION['active_battle_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if ($battleId <= 0) {
    redirect_with_notice('profile.php', 'No Active Battle Found', 'error');
}

$battleModel = new Battle($pdo);
$battle = $battleModel->getBattle($battleId);

if (!$battle) {
    redirect_with_notice('profile.php', 'Battle Not Found', 'error');
}

// Verify User is a participant and hasn't already played
$isChallenger = ((int)$battle['challenger_id'] === $userId);
$isOpponent = ((int)$battle['opponent_id'] === $userId);

if (!$isChallenger && !$isOpponent) {
    header("Location: index.php");
    exit();
}

if ($isChallenger && $battle['challenger_score'] !== null) {
    redirect_with_notice('profile.php', 'You have already completed your turn.', 'error');
}

if ($isOpponent && $battle['opponent_score'] !== null) {
    redirect_with_notice('profile.php', 'You have already completed your turn.', 'error');
}

// Keeps the form token alive during long battles so the timed auto-submit is never rejected.
if (isset($_GET['csrf_refresh'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['csrf_token' => csrf_token()]);
    exit();
}

// Determine avatars and names for the VS header
$myAvatar = $isChallenger ? $battle['challenger_avatar'] : $battle['opponent_avatar'];
$myName = $isChallenger ? $battle['challenger_name'] : $battle['opponent_name'];
$oppAvatar = $isChallenger ? $battle['opponent_avatar'] : $battle['challenger_avatar'];
$oppName = $isChallenger ? $battle['opponent_name'] : $battle['challenger_name'];

// Fix empty avatars safely
$myAvatar = !empty($myAvatar) ? SITE_URL . '/' . htmlspecialchars($myAvatar) : SITE_URL . '/assets/img/default-avatar.png';
$oppAvatar = !empty($oppAvatar) ? SITE_URL . '/' . htmlspecialchars($oppAvatar) : SITE_URL . '/assets/img/default-avatar.png';

$quizId = (int)$battle['quiz_id'];
$quizModel = new Quiz($pdo);
$quiz = $quizModel->getById($quizId);

if (!$quiz) {
    die("Error: Quiz data missing.");
}

$questions = $quizModel->getQuestions($quizId);

// Deterministic Shuffle: Both players in the battle must see the exact same randomized order
$seed = crc32('battle_engine_' . $battleId);
mt_srand($seed);

// Fisher-Yates shuffle for questions
for ($i = count($questions) - 1; $i > 0; $i--) {
    $j = mt_rand(0, $i);
    [$questions[$i], $questions[$j]] = [$questions[$j], $questions[$i]];
}

// Shuffle options within each question (consistent for both players)
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
mt_srand(); // Reset PRNG

// Active Start Time Enforcement
if (!empty($battle['start_time'])) {
    $startTimeEpoch = strtotime($battle['start_time']);
    if (time() < $startTimeEpoch) {
        redirect_with_notice('battle_arena.php', "This battle is strictly scheduled to begin on " . date('M j, Y g:i A', $startTimeEpoch) . ". Please wait!", 'error');
    }
}

// Battle Timer setup
$duration = (int)($quiz['duration_seconds'] ?? 900);
if (!empty($battle['custom_timer_seconds'])) {
    $duration = (int)$battle['custom_timer_seconds'];
}

$startKey = 'battle_start_' . $battleId . '_user_' . $userId;

$now = time();
if (empty($_SESSION[$startKey])) {
    $_SESSION[$startKey] = $now;
    $remaining = $duration;
} else {
    $elapsed = max(0, $now - (int)$_SESSION[$startKey]);
    $remaining = max(0, $duration - $elapsed);
}

$page_title = "Arena: " . htmlspecialchars($quiz['title']);
$csrf_token = csrf_token();

// We DO NOT include the standard header or topbar. This is an immersive view.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?> - MyStudy Arena</title>
    <!-- Use Bootstrap and FA from CDNs -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --arena-bg: #0b0f19;
            --arena-glow: #e11d48;
            --arena-accent: #3b82f6;
            --card-bg: #1e293b;
        }

        body {
            background-color: var(--arena-bg);
            background-image: radial-gradient(circle at 50% 0%, #1e1b4b 0%, #0b0f19 60%);
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6, .outfit-font {
            font-family: 'Outfit', sans-serif;
        }

        /* Battle Header */
        .battle-header {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding: 1.5rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .vs-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2rem;
        }

        .avatar-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            text-align: center;
            width: 120px;
        }

        .avatar-img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #334155;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }

        .avatar-box.me .avatar-img {
            border-color: var(--arena-accent);
            box-shadow: 0 0 25px rgba(59, 130, 246, 0.4);
        }

        .avatar-box.opponent .avatar-img {
            border-color: var(--arena-glow);
            box-shadow: 0 0 25px rgba(225, 29, 72, 0.4);
        }

        .avatar-name {
            font-size: 0.85rem;
            font-weight: 700;
            color: #cbd5e1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }

        .vs-badge {
            font-family: 'Outfit', sans-serif;
            font-size: 2rem;
            font-weight: 900;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0px 4px 10px rgba(245, 158, 11, 0.2);
            font-style: italic;
            padding: 0 1rem;
        }

        .timer-box {
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            text-align: center;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.8);
        }

        .timer-text {
            font-family: 'Outfit', sans-serif;
            font-size: 2.2rem;
            font-weight: 900;
            color: #f8fafc;
            letter-spacing: 2px;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .timer-text.danger {
            color: #ef4444;
            animation: pulse-danger 1s infinite alternate;
        }

        @keyframes pulse-danger {
            from { text-shadow: 0 0 10px rgba(239, 68, 68, 0.5); transform: scale(1); }
            to { text-shadow: 0 0 25px rgba(239, 68, 68, 1); transform: scale(1.05); }
        }

        .quiz-title-badge {
            display: inline-block;
            margin: 0 auto 1.5rem auto;
            position: relative;
            background: #1e293b;
            color: #94a3b8;
            padding: 0.25rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid rgba(255,255,255,0.1);
            text-transform: uppercase;
            letter-spacing: 1px;
            white-space: nowrap;
            max-width: 90vw;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Question Container */
        .arena-container {
            max-width: 800px;
            margin: 0 auto;
            flex-grow: 1;
            padding: 2rem 1rem;
            width: 100%;
        }

        .question-card {
            background: var(--card-bg);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            overflow: hidden;
            margin-bottom: 2rem;
            position: relative;
        }

        .question-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--arena-accent) 0%, var(--arena-glow) 100%);
        }

        .question-stem {
            padding: 3rem 2.5rem 2rem;
            font-size: 1.25rem;
            font-weight: 600;
            line-height: 1.6;
            color: #f8fafc;
            position: relative;
        }

        .question-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255,255,255,0.1);
            color: #94a3b8;
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            letter-spacing: 1px;
        }

        .options-container {
            padding: 0 2.5rem 3rem;
        }

        /* Options */
        .battle-option {
            background: rgba(15, 23, 42, 0.6);
            border: 2px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
        }

        .battle-option:hover {
            border-color: rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.05);
            transform: translateX(5px);
        }

        .battle-option.selected {
            background: rgba(59, 130, 246, 0.1);
            border-color: var(--arena-accent);
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.15);
        }

        .battle-option-input {
            width: 24px;
            height: 24px;
            border: 2px solid #64748b;
            background: transparent;
            margin-right: 1.25rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .battle-option-input:checked {
            background-color: var(--arena-accent);
            border-color: var(--arena-accent);
        }

        .battle-option-label {
            color: #e2e8f0;
            font-size: 1.1rem;
            font-weight: 500;
            margin: 0;
            cursor: pointer;
            flex-grow: 1;
        }

        /* Controls */
        .battle-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
            border-top: 1px solid rgba(255,255,255,0.05);
            padding: 1rem 2rem;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 999;
        }

        .nav-btn {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.1);
            color: #e2e8f0;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
            transition: all 0.2s;
        }

        .nav-btn:hover:not(:disabled) {
            background: #334155;
            color: #fff;
        }

        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .submit-btn {
            background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
            border: none;
            color: #fff;
            padding: 0.75rem 2.5rem;
            border-radius: 50px;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            box-shadow: 0 4px 15px rgba(225, 29, 72, 0.4);
            transition: all 0.2s;
        }

        .submit-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(225, 29, 72, 0.6);
        }

        /* Nav Pills (Mobile/Responsive Map) */
        .question-map {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding: 0.5rem 0;
            scrollbar-width: none;
            justify-content: center;
        }
        .question-map::-webkit-scrollbar { display: none; }

        .map-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #334155;
            transition: all 0.3s;
            cursor: pointer;
        }

        .map-dot.answered {
            background: var(--arena-accent);
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
        }

        .map-dot.active {
            transform: scale(1.5);
            background: #fff;
        }

        @media (max-width: 768px) {
            .vs-container { gap: 1rem; }
            .avatar-img { width: 50px; height: 50px; }
            .vs-badge { font-size: 1.5rem; padding: 0 0.5rem; }
            .timer-text { font-size: 1.8rem; }
            .timer-box { padding: 0.5rem 1rem; }
            
            .question-stem { padding: 3rem 1.5rem 1.5rem; font-size: 1.1rem; }
            .options-container { padding: 0 1.5rem 2.5rem; }
            
            .battle-controls { padding: 1rem; flex-direction: column; gap: 1rem; }
            .battle-controls > div { width: 100%; display: flex; justify-content: space-between; }
            .question-map { width: 100%; justify-content: center; margin-bottom: 0.5rem; }
            
            .arena-container { padding-bottom: 120px; } /* Space for controls */
        }

        .battle-subjective-input::placeholder {
            color: rgba(255,255,255,0.5) !important;
        }
        .battle-subjective-input:focus {
            border-color: var(--arena-accent) !important;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.15), 0 4px 16px rgba(59,130,246,0.1) !important;
            outline: none;
        }
    </style>
</head>
<body>

    <header class="battle-header border-bottom text-center">
        <div class="quiz-title-badge"><?php echo htmlspecialchars($quiz['title']); ?></div>
        
        <div class="container vs-container">
            <div class="avatar-box me">
                <img src="<?php echo $myAvatar; ?>" alt="Me" class="avatar-img">
                <div class="avatar-name">You</div>
            </div>
            
            <div class="timer-box">
                <div class="timer-text" id="arenaTimer">00:00</div>
            </div>

            <div class="avatar-box opponent">
                <img src="<?php echo $oppAvatar; ?>" alt="Opponent" class="avatar-img">
                <div class="avatar-name"><?php echo htmlspecialchars($oppName); ?></div>
            </div>
        </div>
    </header>

    <main class="arena-container">
        <form id="battleForm" action="<?php echo SITE_URL; ?>/quiz_battle_submit.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="quiz_id" value="<?php echo $quizId; ?>">

            <?php foreach ($questions as $index => $q): ?>
            <div class="question-card battle-question-slide" id="question_slide_<?php echo $index; ?>" style="display: <?php echo $index === 0 ? 'block' : 'none'; ?>;">
                <div class="question-badge">Q <?php echo $index + 1; ?> / <?php echo count($questions); ?></div>
                <div class="question-stem">
                    <?php echo nl2br(safe_quiz_html($q['question'] ?? '')); ?>
                </div>
                
                <div class="options-container">
                    <?php 
                    $isSubjective = ($q['question_type'] ?? 'objective') === 'subjective';
                    if ($isSubjective): 
                    ?>
                        <div style="padding: 0.5rem 0;">
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge" style="background: rgba(99,102,241,0.15); color: #a5b4fc; font-size: 0.75rem; border-radius: 8px; padding: 0.35rem 0.75rem;"><i class="fas fa-pen-fancy me-1"></i> Type Your Answer</span>
                            </div>
                            <input type="text" class="form-control answer-input battle-subjective-input" name="q[<?php echo $q['id']; ?>]" placeholder="Enter your answer here..." autocomplete="off" style="width:100%; display:block; background: rgba(255,255,255,0.06); border: 2px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 0.85rem 1.25rem; font-size: 1rem; font-weight: 500; color: #f1f5f9; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <small class="d-block mt-1" style="color: rgba(255,255,255,0.4); font-size: 0.72rem;"><i class="fas fa-info-circle me-1"></i>Case does not matter</small>
                        </div>
                    <?php 
                    elseif (!empty($q['options']) && is_array($q['options'])): 
                        foreach ($q['options'] as $opt): 
                            $optValue = safe_quiz_html(is_array($opt) ? ($opt['option_text'] ?? '') : strval($opt));
                            $optId = is_array($opt) ? ($opt['id'] ?? $optValue) : $optValue;
                    ?>
                        <label class="battle-option">
                            <input class="form-check-input battle-option-input pt-0 mt-0" type="radio" name="q[<?php echo $q['id']; ?>]" value="<?php echo htmlspecialchars($optId); ?>">
                            <span class="battle-option-label"><?php echo $optValue; ?></span>
                        </label>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                        <input type="text" class="form-control bg-dark text-light border-secondary p-3" name="q[<?php echo $q['id']; ?>]" placeholder="Type your answer here..." style="border-radius: 12px;">
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </form>
    </main>

    <footer class="battle-controls border-top shadow-lg">
        <div class="question-map w-100 mb-md-0 d-md-flex d-none pb-0">
            <?php foreach ($questions as $index => $q): ?>
                <div class="map-dot <?php echo $index === 0 ? 'active' : ''; ?>" data-idx="<?php echo $index; ?>" id="dot_<?php echo $index; ?>"></div>
            <?php endforeach; ?>
        </div>

        <div class="d-flex justify-content-between w-100">
            <button type="button" class="nav-btn" id="btnPrev" disabled><i class="fas fa-chevron-left me-2"></i>Prev</button>
            <button type="button" class="submit-btn ms-auto d-none" id="btnSubmitArena">Strike! <i class="fas fa-bolt ms-2"></i></button>
            <button type="button" class="nav-btn ms-auto" id="btnNext">Next<i class="fas fa-chevron-right ms-2"></i></button>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const totalQs = <?php echo count($questions); ?>;
            let current = 0;
            const slides = document.querySelectorAll('.battle-question-slide');
            const dots = document.querySelectorAll('.map-dot');
            const options = document.querySelectorAll('.battle-option');

            const STORAGE_KEY = 'mystudy_battle_<?php echo $battleId; ?>_user_<?php echo $userId; ?>';
            const csrfInput = document.querySelector('#battleForm input[name="csrf_token"]');

            // The submit endpoint no longer exempts auto-submit, so the token is kept fresh here.
            function refreshCsrf() {
                return fetch('<?php echo SITE_URL; ?>/quiz_battle_play.php?csrf_refresh=1', { credentials: 'same-origin', cache: 'no-store' })
                    .then(r => r.json())
                    .then(d => { if (d && d.csrf_token && csrfInput) { csrfInput.value = d.csrf_token; } })
                    .catch(() => {});
            }
            setInterval(refreshCsrf, 600000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshCsrf(); });
            
            // Timer Logic
            let initialRemaining = <?php echo $remaining; ?>;
            const timerEl = document.getElementById('arenaTimer');
            const endTime = Date.now() + (initialRemaining * 1000);
            let hasSubmitted = false;
            
            function updateTimer() {
                if (hasSubmitted) return;
                let remaining = Math.max(0, Math.floor((endTime - Date.now()) / 1000));
                
                if (remaining <= 0) {
                    hasSubmitted = true;
                    sessionStorage.removeItem(STORAGE_KEY);
                    // Auto-submit with a flag
                    let form = document.getElementById('battleForm');
                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'auto_submit';
                    input.value = '1';
                    form.appendChild(input);
                    refreshCsrf().then(() => form.submit());
                    return;
                }
                
                const m = Math.floor(remaining / 60);
                const s = remaining % 60;
                timerEl.textContent = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
                
                if (remaining <= 60) {
                    timerEl.classList.add('danger');
                }
            }
            // Use setInterval for more robust tracking
            setInterval(updateTimer, 500);
            updateTimer();

            // State Preservation
            function saveState() {
                const formData = new FormData(document.getElementById('battleForm'));
                const data = {};
                for (let [key, value] of formData.entries()) {
                    if (key !== 'csrf_token' && key !== 'quiz_id' && value.trim() !== '') {
                        data[key] = value;
                    }
                }
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
                    current: current,
                    answers: data
                }));
            }
            
            function loadState() {
                const saved = sessionStorage.getItem(STORAGE_KEY);
                if (saved) {
                    try {
                        const state = JSON.parse(saved);
                        if (state.current !== undefined && state.current >= 0 && state.current < totalQs) {
                            current = state.current;
                        }
                        if (state.answers) {
                            for (let key in state.answers) {
                                const val = state.answers[key];
                                // Attempt radio
                                const radio = document.querySelector(`input[type="radio"][name="${key}"][value="${val}"]`);
                                if (radio) {
                                    radio.checked = true;
                                    const parent = radio.closest('.battle-option');
                                    if (parent) parent.classList.add('selected');
                                }
                                // Attempt text
                                const text = document.querySelector(`input[type="text"][name="${key}"]`);
                                if (text) {
                                    text.value = val;
                                }
                            }
                        }
                    } catch(e) {}
                }
            }

            // Options Selection Visually
            options.forEach(opt => {
                opt.addEventListener('click', function(e) {
                    const parentCard = this.closest('.options-container');
                    const allOpts = parentCard.querySelectorAll('.battle-option');
                    allOpts.forEach(o => o.classList.remove('selected'));
                    
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio && e.target !== radio) {
                        radio.checked = true;
                    }
                    this.classList.add('selected');
                    updateDots();
                    checkNavState();
                    saveState();
                });
            });

            const updateDots = () => {
                slides.forEach((slide, idx) => {
                    const dot = document.getElementById('dot_' + idx);
                    if(!dot) return;
                    dot.classList.remove('active');
                    if (idx === current) dot.classList.add('active');
                    
                    const isChecked = slide.querySelector('input[type="radio"]:checked') || 
                                      (slide.querySelector('input[type="text"]') && slide.querySelector('input[type="text"]').value.trim() !== '');
                    
                    if (isChecked) {
                        dot.classList.add('answered');
                    } else {
                        dot.classList.remove('answered');
                    }
                });
            };

            const goToSlide = (idx) => {
                if (idx < 0 || idx >= totalQs) return;
                slides.forEach((s, i) => {
                    s.style.display = (i === idx) ? 'block' : 'none';
                });
                current = idx;
                
                checkNavState();
                updateDots();
                window.scrollTo({top: 0, behavior: 'smooth'});
                saveState();
            };

            const btnPrev = document.getElementById('btnPrev');
            const btnNext = document.getElementById('btnNext');
            const btnSubmit = document.getElementById('btnSubmitArena');

            const checkNavState = () => {
                btnPrev.disabled = (current === 0);
                
                if (current === totalQs - 1) {
                    btnNext.classList.add('d-none');
                    btnSubmit.classList.remove('d-none');
                } else {
                    btnNext.classList.remove('d-none');
                    btnSubmit.classList.add('d-none');
                }
            };

            btnPrev.addEventListener('click', () => goToSlide(current - 1));
            btnNext.addEventListener('click', () => goToSlide(current + 1));
            
            btnSubmit.addEventListener('click', () => {
                let answered = 0;
                slides.forEach(s => {
                    if (s.querySelector('input[type="radio"]:checked') || 
                       (s.querySelector('input[type="text"]') && s.querySelector('input[type="text"]').value.trim() !== '')) {
                        answered++;
                    }
                });

                if (answered < totalQs) {
                    if(!confirm(`You have only answered ${answered} out of ${totalQs} questions. Are you sure you want to surrender your turn?`)) {
                        return;
                    }
                }
                
                hasSubmitted = true;
                sessionStorage.removeItem(STORAGE_KEY);
                document.getElementById('battleForm').submit();
            });

            // Text input tracking
            const textInputs = document.querySelectorAll('input[type="text"]');
            textInputs.forEach(i => {
                i.addEventListener('input', () => {
                    updateDots();
                    saveState();
                });
            });
            
            // Map dots
            dots.forEach(d => {
                d.addEventListener('click', () => {
                    goToSlide(parseInt(d.getAttribute('data-idx')));
                });
            });

            // Prevent Back Button / Accidental Refresh
            history.pushState(null, null, location.href);
            window.addEventListener('popstate', function(event) {
                if (!hasSubmitted) {
                    // Push state again to trap the user
                    history.pushState(null, null, location.href);
                    if (confirm("⚠️ WARNING: Are you sure you want to exit? Your battle turn will be AUTO-SUBMITTED and you cannot return!")) {
                        hasSubmitted = true;
                        sessionStorage.removeItem(STORAGE_KEY);
                        let form = document.getElementById('battleForm');
                        let input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'auto_submit';
                        input.value = '1';
                        form.appendChild(input);
                        refreshCsrf().then(() => form.submit());
                    }
                }
            });

            window.addEventListener('beforeunload', function(e) {
                if (!hasSubmitted) {
                    e.preventDefault();
                    e.returnValue = ''; // Trigger browser's native leave warning
                }
            });

            // Initialize State
            loadState();
            slides.forEach((s, i) => s.style.display = (i === current) ? 'block' : 'none');
            updateDots();
            checkNavState();
        });
    </script>
</body>
</html>
