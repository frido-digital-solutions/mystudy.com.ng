<?php
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/models/QuizAttempt.php';

$attemptId = (int)($_GET['attempt_id'] ?? 0);
if ($attemptId <= 0) {
    set_flash_message('error', 'Invalid certificate link.');
    redirect(SITE_URL . '/my_quizzes.php');
}

$attemptModel = new QuizAttempt($pdo);
$userId = (int)$_SESSION['user_id'];

// Get the attempt
$attempt = $attemptModel->getAttempt($attemptId, $userId);
if (!$attempt) {
    set_flash_message('error', 'Certificate not found or securely locked.');
    redirect(SITE_URL . '/my_quizzes.php');
}

$quizId = (int)$attempt['quiz_id'];
$quizModel = new Quiz($pdo);
$quiz = $quizModel->getById($quizId);

$isChallenge = ($quiz['mode'] ?? 'normal') === 'challenge';
$isWinner = false;
$userRank = 0;
$rewardText = '';

// Determine Rank (especially for Challenges)
$leaderboard = $attemptModel->topScores($quizId, null, 1000); // Get all to find rank
foreach ($leaderboard as $index => $entry) {
    if ((int)$entry['user_id'] === $userId) {
        $userRank = $index + 1;
        break;
    }
}

// Check if Top 3 Challenge Winner
if ($isChallenge && $userRank > 0 && $userRank <= 3) {
    $isWinner = true;
    
    // Parse reward details if present in quiz description or custom fields (Fallback to generic text if none)
    // For now, we will assign a tier-based text
    if ($userRank === 1) {
        $rewardText = "1st Place Champion";
    } elseif ($userRank === 2) {
        $rewardText = "2nd Place Runner Up";
    } elseif ($userRank === 3) {
        $rewardText = "3rd Place Finalist";
    }
}

// Calculate Percentage
$score = (int)$attempt['score'];
$total = (int)$attempt['total'];
$percentage = $total > 0 ? round(($score / $total) * 100) : 0;

// quizzes has no pass_mark column, so this reuses the 50% pass mark quiz_result.php applies.
$passMark = 50;
if ($percentage < $passMark) {
    $page_title = 'Certificate Not Available';
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="container py-5" style="max-width: 640px;">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
            <div class="mb-3 text-muted" style="font-size:2.5rem;"><i class="fas fa-lock"></i></div>
            <h4 class="fw-bold mb-2">Certificate Not Available</h4>
            <p class="text-muted mb-1">You scored <strong><?php echo $score; ?>/<?php echo $total; ?> (<?php echo $percentage; ?>%)</strong> on "<?php echo htmlspecialchars($quiz['title'] ?? 'Assessment'); ?>".</p>
            <p class="text-muted mb-4">Certificates are issued only at the <?php echo $passMark; ?>% pass mark or above. Retake the quiz to earn yours.</p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <a href="<?php echo SITE_URL; ?>/quiz_result.php?attempt=<?php echo $attemptId; ?>" class="btn btn-outline-secondary rounded-pill">View Result</a>
                <a href="<?php echo SITE_URL; ?>/my_quizzes.php" class="btn btn-primary rounded-pill">My Quizzes</a>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit();
}

$participantName = htmlspecialchars($_SESSION['user_name'] ?? 'Student');
$quizTitle = htmlspecialchars($quiz['title'] ?? 'Assessment');
$dateCompleted = date('j F Y', strtotime($attempt['completed_at']));

$page_title = "Certificate - " . $quizTitle;

// Fetch uploaded site logo for watermark
$siteLogo = '';
try {
    $logoStmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'site_logo'");
    $logoStmt->execute();
    $siteLogo = $logoStmt->fetchColumn() ?: '';
} catch (Exception $e) {
    $siteLogo = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;700;900&family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- PDF Generation Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        :root {
            --brand-primary: #10b981;
            --brand-dark: #059669;
            --brand-light: #34d399;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem;
            color: var(--text-main);
        }

        /* Certificate Container Frame - Enforce Strict A4 Dimensions */
        .cert-scale-wrapper {
            /* This wrapper visual shrinks it for small screens while preserving logical dimensions */
            width: 100%;
            display: flex;
            justify-content: center;
            overflow-x: auto;
            padding-bottom: 6rem;
        }

        .cert-wrapper {
            position: relative;
            /* Explicit A4 Landscape sizing at 96 DPI */
            width: 1122px; 
            height: 793px;
            min-width: 1122px;
            background: <?php echo $isWinner ? 'linear-gradient(135deg, #064e3b 0%, #0f766e 100%)' : '#ffffff'; ?>;
            border-radius: 4px; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transform-origin: top center;
        }

        /* Responsive scaling for viewing only */
        @media (max-width: 1200px) { .cert-wrapper { transform: scale(0.85); margin-bottom: -100px; } }
        @media (max-width: 900px) { .cert-wrapper { transform: scale(0.65); margin-bottom: -250px; } }
        @media (max-width: 600px) { .cert-wrapper { transform: scale(0.40); margin-bottom: -450px; } }
        @media (max-width: 400px) { .cert-wrapper { transform: scale(0.30); margin-bottom: -550px; } }

        /* -------------------------------------
           WINNER DESIGN (Top 3 Challenge)
           ------------------------------------- */
        .cert-winner {
            color: #ffffff;
            position: relative;
            height: 100%;
            background: #064e3b; /* Deep solid green for perfect canvas retention */
            padding: 1.5rem;
        }

        .winner-inner-border {
            border: 3px solid #fbbf24;
            height: 100%;
            position: relative;
            padding: 8px;
        }

        .winner-inner-border-2 {
            border: 1px solid rgba(251, 191, 36, 0.4);
            height: 100%;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        /* Gold Corner Ornaments */
        .winner-corner-ornament {
            position: absolute; width: 50px; height: 50px; border: 4px solid #fbbf24;
        }
        .winner-corner-tl { top: -3px; left: -3px; border-right: none; border-bottom: none; }
        .winner-corner-tr { top: -3px; right: -3px; border-left: none; border-bottom: none; }
        .winner-corner-bl { bottom: -3px; left: -3px; border-right: none; border-top: none; }
        .winner-corner-br { bottom: -3px; right: -3px; border-left: none; border-top: none; }

        /* Bright Gold Watermark */
        .winner-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 550px;
            color: #fbbf24;
            text-align: center;
            opacity: 0.05;
            z-index: 0;
            pointer-events: none;
        }

        .cert-winner-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .winner-header { text-align: center; }
        
        .winner-header-logo {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #fbbf24; 
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .winner-title {
            font-family: 'Trajan Pro', 'Cinzel', 'Times New Roman', serif;
            font-size: 3.5rem;
            color: #fbbf24;
            text-transform: uppercase;
            letter-spacing: 6px;
            margin-bottom: 0.25rem;
            line-height: 1.1;
        }

        .winner-subtitle {
            font-size: 1.1rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 5px;
            text-transform: uppercase;
        }

        .winner-central-card {
            background: #ffffff;
            border: 4px solid #fbbf24;
            border-radius: 4px;
            padding: 1.5rem 3rem;
            margin: 1rem 6rem;
            text-align: center;
            position: relative;
            z-index: 3;
            color: var(--text-main);
            box-shadow: inset 0 0 0 4px #064e3b;
        }

        .winner-central-text {
            font-size: 1.5rem;
            font-weight: 800;
            color: #064e3b;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .winner-details {
            font-size: 1.2rem;
            color: #334155;
            line-height: 1.4;
            margin-bottom: 1.5rem;
        }

        .winner-name-highlight {
            font-family: 'Great Vibes', 'Brush Script MT', cursive;
            font-size: 3.5rem;
            color: #064e3b;
            display: inline-block;
            line-height: 1;
            padding: 0 2.5rem 0.5rem 2.5rem;
            border-bottom: 2px solid #e2e8f0;
            margin: 1rem 0;
        }

        .winner-stats-row {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
        }

        .winner-stat-item {
            text-align: center;
            background: #064e3b;
            border: 2px solid #fbbf24;
            border-radius: 50px;
            padding: 0.5rem 1rem;
            min-width: 100px;
        }

        .winner-stat-value {
            font-size: 1.1rem;
            font-weight: 800;
            color: #fbbf24;
            margin-bottom: 0.2rem;
        }

        .winner-stat-label {
            font-size: 0.6rem;
            color: #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }

        .trophy-container {
            position: absolute;
            bottom: -25px; right: -25px;
            font-size: 5.5rem;
            color: #fbbf24;
            background: #ffffff;
            border-radius: 50%;
            padding: 0.75rem;
            border: 4px solid #064e3b;
        }

        .badge-container {
            position: absolute;
            top: -25px; left: -25px;
            width: 75px; height: 75px;
            background: #fbbf24;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; color: #064e3b; font-weight: 900;
            border: 4px solid #ffffff;
            box-shadow: 0 0 0 4px #fbbf24;
        }

        .winner-footer {
            display: flex; justify-content: space-between; align-items: flex-end;
            padding: 0 2rem;
            margin-top: auto; 
            padding-top: 1rem;
        }

        .winner-signature-block { text-align: center; }

        .winner-signature-line {
            width: 220px;
            height: 2px;
            background: rgba(255,255,255,0.4);
            margin-bottom: 0.5rem;
        }

        .winner-signature-title {
            font-size: 0.95rem;
            color: #fbbf24;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .winner-seal-container {
            width: 80px; height: 80px;
            background: rgba(251, 191, 36, 0.1);
            border: 2px dashed #fbbf24;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fbbf24; font-size: 2rem;
        }

        /* -------------------------------------
           PREMIUM STANDARD DESIGN (Normal Quiz)
           ------------------------------------- */
        .cert-standard {
            height: 100%;
            background: #ffffff;
            position: relative;
            padding: 1.5rem;
        }
        
        /* Elegant multi-layered border for professional look */
        .standard-inner-border {
            border: 2px solid var(--brand-primary);
            height: 100%;
            position: relative;
            padding: 8px;
        }

        .standard-inner-border-2 {
            border: 1px solid rgba(16, 185, 129, 0.3);
            height: 100%;
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: #f8fafc; /* Unified subtle landing page aesthetic */
            position: relative;
            z-index: 1;
        }

        /* Center Watermark Logo */
        .cert-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            /* PLACEHOLDER LOGO (Using FontAwesome Icon temporarily for perfect html2canvas compatibility) */
            font-size: 450px;
            color: var(--brand-primary);
            text-align: center;
            opacity: 0.08; /* Increased slightly for better visibility while staying non-intrusive */
            z-index: 0;
            pointer-events: none;
        }

        /* Ensure content stays above the watermark */
        .standard-header, .standard-body, .standard-bottom-row {
            position: relative;
            z-index: 2;
        }

        /* Subtle Corner Ornaments */
        .corner-ornament {
            position: absolute; width: 40px; height: 40px; border: 3px solid var(--brand-primary);
        }
        .corner-tl { top: -2px; left: -2px; border-right: none; border-bottom: none; }
        .corner-tr { top: -2px; right: -2px; border-left: none; border-bottom: none; }
        .corner-bl { bottom: -2px; left: -2px; border-right: none; border-top: none; }
        .corner-br { bottom: -2px; right: -2px; border-left: none; border-top: none; }

        .standard-header { text-align: center; }

        .standard-logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--brand-dark);
            display: flex; justify-content: center; align-items: center; gap: 0.75rem;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .standard-title {
            font-family: 'Trajan Pro', 'Cinzel', 'Times New Roman', serif;
            font-size: 3.8rem;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 4px;
            margin-bottom: 0.5rem;
            line-height: 1.1;
        }

        .standard-subtitle {
            font-size: 1.1rem;
            color: var(--brand-primary);
            font-weight: 700;
            letter-spacing: 8px;
            text-transform: uppercase;
        }

        .standard-body { text-align: center; margin: 1rem 0; flex-grow: 1; }

        .standard-presented-to {
            font-size: 1.2rem;
            color: var(--text-muted);
            font-style: italic;
            margin-bottom: 1rem;
        }

        .standard-name {
            font-family: 'Great Vibes', 'Brush Script MT', cursive;
            font-size: 4rem;
            color: var(--brand-dark);
            margin-bottom: 1.5rem;
            line-height: 1;
            padding: 0 3rem 0.5rem 3rem;
            border-bottom: 2px solid #e2e8f0;
            display: inline-block;
        }

        .standard-description {
            font-size: 1.2rem;
            line-height: 1.5;
            color: #334155;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .quiz-title-clamp {
            display: block;
            font-size: 1.4rem;
            color: #0f172a;
            font-weight: 800;
            margin: 0.5rem 0;
            /* Prevent excessively long titles from breaking the height */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        /* Powerful Analytics/Cohort Circles */
        .analytics-box {
            display: flex;
            justify-content: center;
            gap: 2.5rem;
            margin-top: 1.5rem;
        }

        .analytics-circle-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .analytics-circle {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            /* Completely removed inset box shadows. html2canvas interprets inset shadows as a white translucent overlay, which causes the color to wash out. */
            border: 3px solid #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); 
        }
        
        /* Unique solid colors for html2canvas compatibility */
        .circle-score {
            background: #2563eb;
            color: #ffffff;
        }
        
        .circle-rank {
            background: #6d28d9;
            color: #ffffff;
        }
        
        .circle-percentile {
            background: #059669;
            color: #ffffff;
        }

        .analytics-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            font-weight: 800;
        }

        /* Certificate Footer Row (Analytics + Signature side-by-side) */
        .standard-bottom-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: auto;
            border-top: 1px solid #e2e8f0;
            padding-top: 1.5rem;
        }

        .standard-footer {
            display: flex; 
            justify-content: flex-end; 
            align-items: flex-end;
            gap: 2rem;
        }

        .signature-block { text-align: center; }

        .signature-line {
            width: 220px;
            height: 1px;
            background: #64748b;
            margin-bottom: 0.5rem;
        }

        .signature-title {
            font-size: 0.95rem;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .seal-container {
            width: 100px; height: 100px;
            background: rgba(16, 185, 129, 0.05);
            border: 2px dashed var(--brand-primary);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--brand-dark); font-size: 2.5rem;
            transform: rotate(-15deg);
            opacity: 0.8;
        }

        /* -------------------------------------
           TOOL BAR AND OVERLAYS
           ------------------------------------- */
        .action-toolbar {
            position: fixed;
            bottom: 2rem; left: 50%; transform: translateX(-50%);
            background: #ffffff;
            padding: 1rem 1.5rem;
            border-radius: 50px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            display: flex; gap: 1rem; z-index: 100;
        }

        .btn-action {
            border: none; background: #f1f5f9; color: var(--text-main);
            padding: 0.8rem 1.5rem; border-radius: 50px;
            font-family: inherit; font-weight: 700; font-size: 1rem;
            cursor: pointer; display: flex; align-items: center; gap: 0.5rem;
            transition: all 0.2s;
        }
        .btn-action:hover { background: #e2e8f0; }
        .btn-action.primary { background: var(--brand-primary); color: #fff; }
        .btn-action.primary:hover { background: var(--brand-dark); }

        #exportOverlay {
            position: fixed; inset: 0; background: rgba(255,255,255,0.95);
            z-index: 9999; display: none; flex-direction: column;
            justify-content: center; align-items: center;
            font-size: 1.5rem; font-weight: bold; color: var(--brand-primary);
        }
        .spinner {
            width: 50px; height: 50px; border: 5px solid #e2e8f0;
            border-top-color: var(--brand-primary); border-radius: 50%;
            animation: spin 1s linear infinite; margin-bottom: 1rem;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        /* -------------------------------------
           NATIVE PRINT MEDIA QUERY (Crucial for flawless PDF)
           ------------------------------------- */
        @media print {
            @page {
                size: A4 landscape;
                margin: 0; /* Eradicate browser headers/footers */
            }
            body { 
                background: white !important; 
                margin: 0 !important; 
                padding: 0 !important;
                display: block; /* Remove flex centering */
            }
            .cert-scale-wrapper {
                padding: 0 !important;
                display: block;
            }
            .cert-wrapper { 
                box-shadow: none !important; 
                transform: scale(1) !important;
                margin: 0 !important;
                border: none !important;
                border-radius: 0 !important;
                position: absolute;
                top: 0; left: 0;
            }
            .action-toolbar, #exportOverlay { display: none !important; }
        }
    </style>
</head>
<body>

    <!-- Loading Overlay -->
    <div id="exportOverlay">
        <div class="spinner"></div>
        Generating High Quality Record...
    </div>

    <div class="cert-scale-wrapper">
        <div class="cert-wrapper" id="certificateNode">
            <?php 
                $totalParticipants = count($leaderboard);
                if ($isWinner): 
            ?>
                <!-- TOP 3 CHALLENGE WINNER DESIGN -->
                <div class="cert-winner">
                    <div class="winner-inner-border">
                        <div class="winner-corner-ornament winner-corner-tl"></div>
                        <div class="winner-corner-ornament winner-corner-tr"></div>
                        <div class="winner-corner-ornament winner-corner-bl"></div>
                        <div class="winner-corner-ornament winner-corner-br"></div>
                        
                        <div class="winner-inner-border-2">
                            <?php if ($siteLogo): ?>
                                <img src="<?php echo SITE_URL . '/' . htmlspecialchars($siteLogo); ?>" class="winner-watermark" style="font-size:unset; max-width:85%; width:650px; height:auto; opacity:0.15;">
                            <?php else: ?>
                                <i class="fas fa-graduation-cap winner-watermark"></i>
                            <?php endif; ?>
                            
                            <div class="cert-winner-content">
                                <div class="winner-header">
                                    <div class="winner-header-logo">
                                        <i class="fas fa-graduation-cap"></i> <?php echo SITE_NAME; ?> ACADEMY
                                    </div>
                                    <h1 class="winner-title">Excellence Award</h1>
                                    <h2 class="winner-subtitle">Official Challenge Result</h2>
                                </div>

                                <div class="winner-central-card">
                                    <div class="badge-container">
                                        <?php 
                                            echo $userRank;
                                            if ($userRank == 1) echo '<sup style="font-size:1.2rem;">st</sup>';
                                            elseif ($userRank == 2) echo '<sup style="font-size:1.2rem;">nd</sup>';
                                            elseif ($userRank == 3) echo '<sup style="font-size:1.2rem;">rd</sup>';
                                        ?>
                                    </div>
                                    <div class="winner-central-text">Outstanding Achievement</div>
                                    <div class="winner-details">
                                        This verifies that<br>
                                        <span class="winner-name-highlight"><?php echo $participantName; ?></span><br>
                                        has successfully conquered the challenge:<br>
                                        <strong style="font-size:1.5rem; color:#0f172a; margin-top: 0.5rem; display:block;">"<?php echo $quizTitle; ?>"</strong><br>
                                    </div>
                                    
                                    <div class="winner-stats-row">
                                        <div class="winner-stat-item">
                                            <div class="winner-stat-value"><?php echo $percentage; ?>%</div>
                                            <div class="winner-stat-label">Final Score</div>
                                        </div>
                                        <div class="winner-stat-item">
                                            <div class="winner-stat-value"><?php echo $rewardText; ?></div>
                                            <div class="winner-stat-label">Prize Won</div>
                                        </div>
                                        <div class="winner-stat-item">
                                            <div class="winner-stat-value"><?php echo $totalParticipants; ?></div>
                                            <div class="winner-stat-label">Participants</div>
                                        </div>
                                    </div>

                                    <div class="trophy-container">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                </div>

                                <div class="winner-footer">
                                    <div class="winner-signature-block">
                                        <div class="winner-signature-title" style="margin-bottom: 0.5rem; font-size: 1.1rem; color: #ffffff; text-transform:none; letter-spacing:0;"><?php echo date('F jS, Y', strtotime($attempt['completed_at'])); ?></div>
                                        <div class="winner-signature-line"></div>
                                        <div class="winner-signature-title">Date</div>
                                    </div>
                                    
                                    <div class="winner-seal-container">
                                        <i class="fas fa-award"></i>
                                    </div>
                                    
                                    <div class="winner-signature-block">
                                        <div class="winner-signature-title" style="margin-bottom: 0.5rem; font-size: 1.6rem; font-family: 'Brush Script MT', cursive; text-transform:none; color: #fbbf24; letter-spacing: 0;">Verified</div>
                                        <div class="winner-signature-line"></div>
                                        <div class="winner-signature-title">Platform Administrator</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- PREMIUM STANDARD QUIZ RESULT DESIGN -->
                <?php
                    // Calculate cohort statistics
                    $totalParticipants = count($leaderboard);
                    // Prevent division by zero and handle pure solitary attempts
                    if ($totalParticipants <= 1) {
                        $percentileText = "1%";
                        $rankDisplay = "1 / 1";
                    } else {
                        $outperformed = $totalParticipants - $userRank;
                        $percentile = round(($userRank / $totalParticipants) * 100);
                        // Ensure it says at least top 1% instead of 0%
                        if ($percentile < 1) $percentile = 1;
                        $percentileText = $percentile . "%";
                        $rankDisplay = $userRank . " / " . $totalParticipants;
                    }
                ?>
                <div class="cert-standard">
                    <div class="standard-inner-border">
                        <div class="corner-ornament corner-tl"></div>
                        <div class="corner-ornament corner-tr"></div>
                        <div class="corner-ornament corner-bl"></div>
                        <div class="corner-ornament corner-br"></div>
                        
                        <div class="standard-inner-border-2">
                            <?php if ($siteLogo): ?>
                                <img src="<?php echo SITE_URL . '/' . htmlspecialchars($siteLogo); ?>" class="cert-watermark" style="font-size:unset; max-width:85%; width:650px; height:auto; opacity:0.12;">
                            <?php else: ?>
                                <i class="fas fa-graduation-cap cert-watermark"></i>
                            <?php endif; ?>
                            <div class="standard-header">
                                <div class="standard-logo">
                                    <i class="fas fa-graduation-cap"></i> <?php echo SITE_NAME; ?> ACADEMY
                                </div>
                                <h1 class="standard-title">Certificate of Achievement</h1>
                                <div class="standard-subtitle">Official Result Record</div>
                            </div>

                            <div class="standard-body">
                                <div class="standard-presented-to">This document serves to certify that</div>
                                <div class="standard-name"><?php echo $participantName; ?></div>
                                <div class="standard-description">
                                    has successfully participated in and completed the structured assessment for:
                                    <span class="quiz-title-clamp" title="<?php echo $quizTitle; ?>">"<?php echo $quizTitle; ?>"</span>
                                    achieving the following verified performance results:
                                </div>

                                <div class="standard-bottom-row">
                                    <div class="analytics-box" style="margin-top: 0;">
                                        <div class="analytics-circle-wrapper">
                                            <div class="analytics-circle circle-score"><?php echo $percentage; ?>%</div>
                                            <div class="analytics-label">Final Score</div>
                                        </div>
                                        
                                        <div class="analytics-circle-wrapper">
                                            <div class="analytics-circle circle-rank"><?php echo $rankDisplay; ?></div>
                                            <div class="analytics-label">Position</div>
                                        </div>
                                        
                                        <div class="analytics-circle-wrapper">
                                            <div class="analytics-circle circle-percentile">Top <?php echo $percentileText; ?></div>
                                            <div class="analytics-label">Top Tier</div>
                                        </div>
                                    </div>

                                    <div class="standard-footer">
                                        <div class="signature-block">
                                            <div class="signature-title" style="margin-bottom: 0.5rem; font-size: 1.2rem; font-weight: bold; color: #0f172a; text-transform:none; letter-spacing:0;"><?php echo date('F jS, Y', strtotime($attempt['completed_at'])); ?></div>
                                            <div class="signature-line"></div>
                                            <div class="signature-title">Date</div>
                                        </div>
                                        
                                        <div class="seal-container">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        
                                        <div class="signature-block">
                                            <div class="signature-title" style="margin-bottom: 0.5rem; font-size: 1.6rem; font-family: 'Brush Script MT', cursive; text-transform:none; color: var(--brand-dark); letter-spacing: 0;">Verified</div>
                                            <div class="signature-line"></div>
                                            <div class="signature-title">Platform Administrator</div>
                                        </div>
                                    </div>
                                </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Floating Action Toolbar -->
    <div class="action-toolbar">
        <a href="my_quizzes.php" class="btn-action" style="text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <button class="btn-action" onclick="downloadPDF()">
            <i class="fas fa-file-pdf"></i> Download PDF
        </button>
        <button class="btn-action primary" onclick="downloadImage()">
            <i class="fas fa-image"></i> Download Image
        </button>
    </div>

    <script>
        const node = document.getElementById('certificateNode');
        const overlay = document.getElementById('exportOverlay');
        const filename = 'certificate_<?php echo $attemptId; ?>';

        function prepareCapture() {
            overlay.style.display = 'flex';
            window.scrollTo(0, 0); 
            const originalTransform = node.style.transform;
            node.style.transform = 'none';
            node.style.margin = '0';
            return originalTransform;
        }

        function finalizeCapture(originalTransform) {
            node.style.transform = '';
            node.style.margin = '';
            overlay.style.display = 'none';
        }

        // IMAGE EXPORT: Using html2canvas (Ideal for PNGs)
        function downloadImage() {
            const orig = prepareCapture();

            html2canvas(node, {
                scale: 2, 
                useCORS: true,
                backgroundColor: null,
                width: 1122,
                height: 793,
                windowWidth: 1122,
                windowHeight: 793
            }).then(canvas => {
                finalizeCapture(orig);
                const link = document.createElement('a');
                link.download = filename + '.png';
                link.href = canvas.toDataURL('image/png', 1.0);
                link.click();
            }).catch(err => {
                finalizeCapture(orig);
                alert('An error occurred capturing the image.');
                console.error(err);
            });
        }

        // PDF EXPORT: Using Flawless Native Browser Print Formatting
        function downloadPDF() {
            // Since we added flawless @page { size: A4 landscape; margin: 0; } CSS,
            // Native window.print() is ACTUALLY the most reliable, crisp way to generate the PDF 
            // without blurry canvas artifacts or JavaScript failure.
            // We just need to prompt the user to "Save as PDF" in the dialog.
            
            alert("For the highest quality PDF, please ensure 'Destination' is set to 'Save as PDF' and 'Layout' is 'Landscape'.");
            window.print();
        }
    </script>
</body>
</html>
