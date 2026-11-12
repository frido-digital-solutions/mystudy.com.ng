<?php
require_once __DIR__ . '/config/config.php';
require_login();

// Resolve the code from the database for this user, never from the query string.
$quizId = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

if ($quizId <= 0) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
}

require_once __DIR__ . '/models/AccessCode.php';
$accessCodeModel = new AccessCode($pdo);
$code = $accessCodeModel->getCodeForUser((int)$_SESSION['user_id'], $quizId);

if (empty($code)) {
    set_flash_message('success', 'Your access is active. Open the quiz to continue.');
    header("Location: " . SITE_URL . "/quiz_special.php?id=" . $quizId);
    exit;
}

// Fetch quiz details for the display
require_once __DIR__ . '/models/Quiz.php';
$quizModel = new Quiz($pdo);
$quiz = $quizModel->getById($quizId);

$quizTitle = $quiz ? $quiz['title'] : 'Special Quiz';
$quizLink = SITE_URL . '/quiz_special.php?id=' . $quizId;

$page_title = 'Payment Successful';
include __DIR__ . '/includes/header.php';
?>

<style>
    body {
        background-color: #f8fafc;
    }
    .success-container {
        max-width: 600px;
        margin: 4rem auto;
        animation: slideUpFade 0.6s ease forwards;
    }
    
    .success-card {
        background: #ffffff;
        border-radius: 24px;
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);
        padding: 3rem 2rem;
        text-align: center;
        border: 1px solid #f1f5f9;
        position: relative;
        overflow: hidden;
    }

    /* Top decorative gradient bar */
    .success-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 8px;
        background: linear-gradient(90deg, #10b981, #3b82f6);
    }

    .icon-wrapper {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, #10b981, #059669);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        box-shadow: 0 15px 35px rgba(16, 185, 129, 0.3);
        animation: scaleInBounce 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }

    .icon-wrapper i {
        font-size: 2.5rem;
        color: white;
    }

    .code-display-box {
        background: linear-gradient(145deg, #f8fafc, #f1f5f9);
        border: 2px dashed #cbd5e1;
        border-radius: 16px;
        padding: 1.5rem;
        margin: 2rem 0;
        position: relative;
        transition: all 0.3s;
    }

    .code-display-box:hover {
        border-color: #3b82f6;
        box-shadow: 0 10px 25px rgba(59, 130, 246, 0.1);
    }

    .the-code {
        font-family: 'Courier New', Courier, monospace;
        font-size: 2.5rem;
        font-weight: 900;
        letter-spacing: 4px;
        color: #0f172a;
        margin: 0.5rem 0;
        display: inline-block;
    }

    .btn-quiz {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        padding: 0.85rem 2rem;
        border-radius: 12px;
        font-weight: 700;
        font-size: 1.1rem;
        border: none;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }

    .btn-quiz:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        color: white;
    }

    .btn-dash {
        background: white;
        color: #64748b;
        padding: 0.85rem 2rem;
        border-radius: 12px;
        font-weight: 600;
        font-size: 1rem;
        border: 2px solid #e2e8f0;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }

    .btn-dash:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #0f172a;
    }

    @keyframes slideUpFade {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes scaleInBounce {
        0% { transform: scale(0); opacity: 0; }
        50% { transform: scale(1.1); opacity: 1; }
        100% { transform: scale(1); opacity: 1; }
    }
</style>

<div class="container">
    <div class="success-container">
        <div class="success-card">
            
            <div class="icon-wrapper">
                <i class="fas fa-check"></i>
            </div>

            <h2 class="fw-bold mb-2">Payment Successful!</h2>
            <p class="text-muted mb-0" style="font-size: 1.1rem;">You successfully purchased access to <strong><?php echo htmlspecialchars($quizTitle); ?></strong>.</p>

            <div class="code-display-box">
                <span style="font-size: 0.8rem; text-transform: uppercase; font-weight: 800; color: #64748b; letter-spacing: 1px;">Your Access Code</span><br>
                <div class="the-code" id="accessCodeText"><?php echo htmlspecialchars($code); ?></div>
                <div class="mt-2">
                    <span class="badge bg-success" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;"><i class="fas fa-magic me-1"></i> Auto-Applied</span>
                </div>
                <!-- Optional: small tooltip note -->
                <p class="small text-muted mb-0 mt-3"><i class="fas fa-info-circle me-1"></i> This code has been safely linked to your account. You don't need to write it down!</p>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-center gap-3 mt-4">
                <a href="<?php echo $quizLink; ?>" class="btn-quiz">
                    <i class="fas fa-play"></i> Return to Quiz
                </a>
                <a href="<?php echo SITE_URL; ?>/dashboard.php" class="btn-dash">
                    <i class="fas fa-home"></i> Go to Dashboard
                </a>
            </div>

        </div>
    </div>
</div>

<!-- Extra Confetti Animation Script for Premium Feel -->
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var duration = 3 * 1000;
        var end = Date.now() + duration;

        (function frame() {
            confetti({
                particleCount: 5,
                angle: 60,
                spread: 55,
                origin: { x: 0 },
                colors: ['#10b981', '#3b82f6', '#fbbf24']
            });
            confetti({
                particleCount: 5,
                angle: 120,
                spread: 55,
                origin: { x: 1 },
                colors: ['#10b981', '#3b82f6', '#fbbf24']
            });

            if (Date.now() < end) {
                requestAnimationFrame(frame);
            }
        }());
    });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
