<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/includes/email-config.php';
require_login();

$user = new User($pdo);
$user->getById($_SESSION['user_id']);

$successMsg = '';
$errorMsg = '';
$showSentUI = false;
$tokenVerified = false;

// 1. Fetch the last time verification was sent from user_meta
$stmt = $pdo->prepare("SELECT meta_value FROM user_meta WHERE user_id = ? AND meta_key = 'verification_sent_at'");
$stmt->execute([$user->id]);
$lastSentMeta = $stmt->fetchColumn();
$lastSentAt = $lastSentMeta ? (int)$lastSentMeta : 0;

$now = time();
$cooldownSeconds = 3600; // 1 hour
$timeRemaining = max(0, $cooldownSeconds - ($now - $lastSentAt));

// Handle Token Verification (user clicked link in email)
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
    if ($user->verifyEmail($token)) {
        // Update current object state in memory so UI reflects immediately
        $user->email_verified = 1;
        $tokenVerified = true;
        // Clear the cooldown tracking on success
        $pdo->prepare("DELETE FROM user_meta WHERE user_id = ? AND meta_key = 'verification_sent_at'")->execute([$user->id]);
        $timeRemaining = 0;
        $successMsg = "Your email address has been successfully verified! Your account is now fully secured.";
    } else {
        $errorMsg = "Invalid or expired verification link.";
    }
}
// Handle Sending Verification Link (user clicked button)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_verification'])) {
    if ($user->email_verified) {
        $errorMsg = "Your email is already verified.";
    } elseif ($timeRemaining > 0) {
        $errorMsg = "Please wait before requesting another verification link.";
        $showSentUI = true;
    } else {
        $token = $user->generateVerificationToken($user->email);
        if ($token) {
            $verifyUrl = SITE_URL . "/verify-email.php?token=" . urlencode($token);
            try {
                sendProductionEmail($user->email, $user->name, 'email_verification', [
                    'name' => $user->name,
                    'verify_url' => $verifyUrl
                ]);
                
                // Track when it was sent
                $now = time();
                if ($lastSentMeta !== false) {
                    $pdo->prepare("UPDATE user_meta SET meta_value = ? WHERE user_id = ? AND meta_key = 'verification_sent_at'")->execute([$now, $user->id]);
                } else {
                    $pdo->prepare("INSERT INTO user_meta (user_id, meta_key, meta_value) VALUES (?, 'verification_sent_at', ?)")->execute([$user->id, $now]);
                }
                
                $timeRemaining = $cooldownSeconds;
                $showSentUI = true;
                $successMsg = "A verification link has been sent to <strong>" . htmlspecialchars($user->email) . "</strong>. Please check your inbox and click the link to verify.";
            } catch (Exception $e) {
                error_log("Failed to send verification email: " . $e->getMessage());
                $errorMsg = "Failed to send verification email. Please try again later.";
            }
        } else {
            $errorMsg = "Failed to generate verification token. Please contact support.";
        }
    }
}

// If user forces a page refresh, but they are in cooldown without posting
if (!$tokenVerified && !$errorMsg && !$successMsg && !$user->email_verified && $timeRemaining > 0) {
    $showSentUI = true;
}

$page_title = 'Verify Email';
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0" style="border-radius: 16px; overflow: hidden;">
                <div class="card-body text-center p-5">
                    
                    <?php if ($tokenVerified): // UI STATE 1: Successfully Verified via Token Click ?>
                        <div class="mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-check fs-1"></i>
                            </div>
                            <h3 class="fw-bold mb-3">Email Verified!</h3>
                            <p class="text-muted fs-5 mb-4"><?php echo $successMsg; ?></p>
                            <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-primary rounded-pill px-5 py-2 fw-bold">
                                Back to Profile
                            </a>
                        </div>
                        
                    <?php elseif ($errorMsg && !$showSentUI): // UI STATE 2: General Error ?>
                        <div class="mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-times fs-1"></i>
                            </div>
                            <h3 class="fw-bold mb-3">Verification Failed</h3>
                            <p class="text-muted fs-5 mb-4"><?php echo $errorMsg; ?></p>
                            <?php if ($timeRemaining > 0): ?>
                                <a href="<?php echo SITE_URL; ?>/verify-email.php" class="btn btn-primary rounded-pill px-5 py-2 fw-bold">
                                    Check Status
                                </a>
                            <?php else: ?>
                                <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-outline-secondary rounded-pill px-5 py-2 fw-bold">
                                    Back to Profile
                                </a>
                            <?php endif; ?>
                        </div>
                        
                    <?php elseif ($user->email_verified): // UI STATE 3: Already Verified ?>
                        <div class="mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-shield-check fs-1"></i>
                            </div>
                            <h3 class="fw-bold mb-3">Already Verified</h3>
                            <p class="text-muted fs-5 mb-4">Your email address is already verified and your account is secure.</p>
                            <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-primary rounded-pill px-5 py-2 fw-bold">
                                Back to Profile
                            </a>
                        </div>
                        
                    <?php elseif ($showSentUI): // UI STATE 4: Email Sent / In Cooldown ?>
                        <div class="mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-paper-plane fs-1"></i>
                            </div>
                            <h3 class="fw-bold mb-3">Verification Link Sent</h3>
                            <p class="text-muted fs-5 mb-4">
                                <?php echo $successMsg ? $successMsg : "A verification link was recently sent to <strong>" . htmlspecialchars($user->email) . "</strong>. Please check your inbox (and spam folder) and click the link to verify."; ?>
                            </p>
                            
                            <?php if ($errorMsg): ?>
                                <p class="text-danger small mb-3"><?php echo $errorMsg; ?></p>
                            <?php endif; ?>

                            <form method="POST" action="" id="resendForm">
                                <input type="hidden" name="send_verification" value="1">
                                <button type="submit" id="resendBtn" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold w-100 shadow-sm mb-3" disabled>
                                    Resend in <span id="countdownDisplay">...</span> <i class="fas fa-redo ms-2"></i>
                                </button>
                            </form>
                            <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-link text-muted text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i> Back to Profile
                            </a>
                        </div>
                        
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                let timeRemaining = <?php echo $timeRemaining; ?>;
                                const display = document.getElementById('countdownDisplay');
                                const btn = document.getElementById('resendBtn');
                                
                                function updateCountdown() {
                                    if (timeRemaining <= 0) {
                                        display.innerText = "Now";
                                        btn.disabled = false;
                                        btn.innerHTML = 'Resend Verification Link <i class="fas fa-paper-plane ms-2"></i>';
                                        return;
                                    }
                                    
                                    const m = Math.floor(timeRemaining / 60);
                                    const s = timeRemaining % 60;
                                    display.innerText = m + ":" + (s < 10 ? "0" + s : s);
                                    timeRemaining--;
                                    setTimeout(updateCountdown, 1000);
                                }
                                
                                updateCountdown();
                            });
                        </script>
                        
                    <?php else: // UI STATE 5: Clean slate, ready to send ?>
                        <div class="mb-4">
                            <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle mb-3" style="width: 80px; height: 80px;">
                                <i class="fas fa-envelope-open-text fs-1"></i>
                            </div>
                            <h3 class="fw-bold mb-3">Verify Your Email</h3>
                            <p class="text-muted fs-5 mb-4">
                                Click the button below to send a secure verification link to:<br>
                                <strong class="text-dark"><?php echo htmlspecialchars($user->email); ?></strong>
                            </p>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="send_verification" value="1">
                                <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold w-100 shadow-sm mb-3">
                                    Send Verification Link <i class="fas fa-paper-plane ms-2"></i>
                                </button>
                            </form>
                            <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-link text-muted text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i> Back to Profile
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
