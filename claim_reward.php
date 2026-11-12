<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/models/QuizAttempt.php';
require_once __DIR__ . '/models/Notification.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$quizId = (int)($_GET['quiz_id'] ?? 0);

if ($quizId <= 0) {
    set_flash_message('error', 'Invalid quiz.');
    redirect(SITE_URL . '/quizzes.php');
}

$quizModel = new Quiz($pdo);
$quiz = $quizModel->getById($quizId);

if (!$quiz || $quiz['mode'] !== 'challenge') {
    set_flash_message('error', 'Challenge not found.');
    redirect(SITE_URL . '/quizzes.php');
}

// Rewards unlock only once the challenge window has closed, so a claim can never lock in a
// provisional rank while other players are still submitting.
if (empty($quiz['event_end']) || strtotime($quiz['event_end']) > time()) {
    set_flash_message('info', 'This challenge is still ongoing. Rewards can be claimed after it ends.');
    redirect(SITE_URL . '/challenge_results.php?quiz_id=' . $quizId);
}

// Check if user participated
$attemptModel = new QuizAttempt($pdo);
if (!$attemptModel->hasAttempted($quizId, $userId)) {
    set_flash_message('error', 'You did not participate in this challenge.');
    redirect(SITE_URL . '/quizzes.php');
}

// Get leaderboard and find rank
$leaderboard = $attemptModel->topScores($quizId, null, 1000);
$userRank = 0;
$userScore = 0;
$userTotal = 0;
$totalParticipants = count($leaderboard);
foreach ($leaderboard as $idx => $entry) {
    if ((int)$entry['user_id'] === $userId) {
        $userRank = $idx + 1;
        $userScore = (int)$entry['score'];
        $userTotal = (int)$entry['total'];
        break;
    }
}

// Only top 3 can claim rewards (rank 0 means the user is not on the leaderboard at all)
$hasPrize = !empty($quiz['prize_amount']) || !empty($quiz['prize_text']);
if ($userRank < 1 || $userRank > 3 || !$hasPrize) {
    set_flash_message('info', 'Only top 3 finishers with prizes can claim rewards.');
    redirect(SITE_URL . '/challenge_results.php?quiz_id=' . $quizId);
}

// Calculate exactly what the user deserves based on Tiered Rank Slice (50% / 30% / 20%)
$calculatedAmount = 0;
$detailedAmountDisplay = '';
$prizeDisplay = '';

if (!empty($quiz['prize_amount']) && $quiz['prize_amount'] > 0) {
    $basePool = (float)$quiz['prize_amount'];
    // Same exact split the winner notification used, so the three shares always total the pool.
    $calculatedAmount = Quiz::prizeShare($basePool, $userRank);
    
    $detailedAmountDisplay = format_prize($calculatedAmount, $quiz['prize_type'] ?? 'money');
    $prizeDisplay = "Total Pool: " . format_prize($basePool, $quiz['prize_type'] ?? 'money') . " <br><span class='badge bg-success mt-2'>Your Locked Cut: " . $detailedAmountDisplay . "</span>";
} elseif (!empty($quiz['prize_text'])) {
    $prizeDisplay = htmlspecialchars($quiz['prize_text']);
}

$percentage = $userTotal > 0 ? round(($userScore / $userTotal) * 100) : 0;

// Create claims table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS reward_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    user_id INT NOT NULL,
    rank_position INT NOT NULL,
    amount DECIMAL(10,2) NULL,
    full_name VARCHAR(255) NOT NULL,
    bank_name VARCHAR(255) NOT NULL DEFAULT '',
    account_number VARCHAR(50) NOT NULL DEFAULT '',
    account_name VARCHAR(255) NOT NULL DEFAULT '',
    phone VARCHAR(30) NULL,
    status ENUM('pending','processing','sent','paid','rejected') DEFAULT 'pending',
    admin_notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_claim (quiz_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
try { $pdo->exec("ALTER TABLE reward_claims ADD COLUMN amount DECIMAL(10,2) NULL AFTER rank_position"); } catch (Throwable $e) {}

// Check if already submitted
$claimStmt = $pdo->prepare("SELECT * FROM reward_claims WHERE quiz_id = ? AND user_id = ? LIMIT 1");
$claimStmt->execute([$quizId, $userId]);
$existingClaim = $claimStmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
$submitSuccess = false;
$submitError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingClaim) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $submitError = 'Invalid CSRF token.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $bankName = trim($_POST['bank_name'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');
        $accountName = trim($_POST['account_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        $isValid = false;
        if ($quiz['prize_type'] === 'money') {
            if (empty($fullName) || empty($bankName) || empty($accountNumber) || empty($accountName)) {
                $submitError = 'Please fill in all required fields for bank transfer.';
            } else {
                $isValid = true;
            }
        } elseif (in_array($quiz['prize_type'], ['airtime', 'data'])) {
            // For airtime/data: bank_name holds Network, phone holds Phone Number
            if (empty($bankName) || empty($phone)) {
                $submitError = 'Please select your network provider and provide your phone number.';
            } else {
                // Ensure unused variables don't hold null
                $fullName = 'N/A';
                $accountName = '';
                $accountNumber = '';
                $isValid = true;
            }
        } else {
            if (empty($fullName)) {
                $submitError = 'Please enter your full name.';
            } else {
                $isValid = true;
            }
        }

        if (!$submitError && $isValid) {
            $ins = $pdo->prepare("INSERT INTO reward_claims (quiz_id, user_id, rank_position, amount, full_name, bank_name, account_number, account_name, phone) VALUES (?,?,?,?,?,?,?,?,?)");
            $ins->execute([$quizId, $userId, $userRank, $calculatedAmount > 0 ? $calculatedAmount : null, $fullName, $bankName, $accountNumber, $accountName, $phone]);
            $submitSuccess = true;

            // Re-fetch the claim
            $claimStmt->execute([$quizId, $userId]);
            $existingClaim = $claimStmt->fetch(PDO::FETCH_ASSOC);

            // Notify user
            $nModel = new Notification($pdo);
            $nModel->create($userId, 'reward_claim', '✅ Reward Claim Submitted', 
                "Your reward claim details for \"{$quiz['title']}\" have been submitted. We'll process it shortly and notify you once complete.", 
                SITE_URL . '/claim_reward.php?quiz_id=' . $quizId);
        }
    }
}

$rankEmoji = ['', '🥇', '🥈', '🥉'];
$rankLabel = ['', '1st Place', '2nd Place', '3rd Place'];
$page_title = 'Claim Reward — ' . htmlspecialchars($quiz['title']);

include __DIR__ . '/includes/header.php';
?>

<style>
.cr-hero {
    background: linear-gradient(135deg, #0f172a, #1e293b);
    padding: 3rem 0 6rem; /* increased bottom padding to prevent overlap with floating card */
    color: #fff;
}
.cr-card {
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    overflow: hidden;
    margin-top: -4rem;
    position: relative;
    z-index: 2;
}
.cr-rank-badge {
    width: 100px; height: 100px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 2.5rem;
    margin: 0 auto 1rem;
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}
.cr-rank-1 { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
.cr-rank-2 { background: linear-gradient(135deg, #94a3b8, #64748b); }
.cr-rank-3 { background: linear-gradient(135deg, #d97706, #b45309); }
.cr-stat {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem;
    text-align: center;
}
.cr-stat-label { font-size: 0.7rem; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; color: #64748b; }
.cr-stat-value { font-size: 1.3rem; font-weight: 900; color: #0f172a; }
.cr-form-group label { font-weight: 700; color: #1e293b; font-size: 0.9rem; }
.cr-form-group input {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.7rem 1rem;
    font-size: 0.95rem;
    transition: border-color 0.2s;
}
.cr-form-group input:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.1); }
.cr-submit-btn {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff; border: none; border-radius: 14px;
    padding: 0.9rem 2rem; font-weight: 800; font-size: 1rem;
    cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;
    box-shadow: 0 6px 20px rgba(16,185,129,0.3);
    transition: all 0.2s;
}
.cr-submit-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16,185,129,0.4); }
.cr-submitted {
    background: #f0fdf4; border: 2px solid #bbf7d0; border-radius: 18px;
    padding: 2rem; text-align: center;
}
.cr-status-badge {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.4rem 1rem; border-radius: 50px; font-weight: 700; font-size: 0.8rem;
}
.cr-status-pending { background: rgba(245,158,11,0.15); color: #d97706; }
.cr-status-processing { background: rgba(59,130,246,0.15); color: #3b82f6; }
.cr-status-sent { background: rgba(16,185,129,0.15); color: #059669; }
.cr-status-paid { background: rgba(16,185,129,0.15); color: #059669; }

/* Mobile Responsiveness */
@media (max-width: 576px) {
    .cr-hero { padding: 2rem 0 5rem; }
    .cr-rank-badge { width: 80px; height: 80px; font-size: 2rem; margin-bottom: 0.5rem; }
    h1.fw-bold { font-size: 1.5rem; }
    .cr-hero p { font-size: 0.95rem; }
    .cr-card { padding: 1.5rem !important; margin-top: -3rem; border-radius: 16px; }
    .cr-stat { padding: 0.75rem; }
    .cr-stat-value { font-size: 1.1rem; }
    .d-flex.gap-3.mb-4 { flex-direction: column; gap: 0.75rem !important; }
    .cr-submitted { padding: 1.5rem; }
}
</style>

<!-- Hero -->
<div class="cr-hero">
    <div class="container text-center">
        <div class="cr-rank-badge cr-rank-<?php echo $userRank; ?>">
            <?php echo $rankEmoji[$userRank] ?? '🏆'; ?>
        </div>
        <h1 class="fw-bold mb-2"><?php echo $rankLabel[$userRank] ?? 'Winner'; ?>!</h1>
        <p class="mb-0" style="color:#94a3b8; font-size: 1.1rem;"><?php echo htmlspecialchars($quiz['title']); ?></p>
    </div>
</div>

<div class="container" style="max-width: 640px;">
    <div class="cr-card p-4 mb-5">
        
        <!-- Stats -->
        <div class="d-flex gap-3 mb-4">
            <div class="cr-stat flex-fill">
                <div class="cr-stat-label">Your Rank</div>
                <div class="cr-stat-value">#<?php echo $userRank; ?></div>
            </div>
            <div class="cr-stat flex-fill">
                <div class="cr-stat-label">Score</div>
                <div class="cr-stat-value"><?php echo $userScore; ?>/<?php echo $userTotal; ?></div>
            </div>
            <div class="cr-stat flex-fill">
                <div class="cr-stat-label">Accuracy</div>
                <div class="cr-stat-value"><?php echo $percentage; ?>%</div>
            </div>
            <?php if ($prizeDisplay): ?>
            <div class="cr-stat flex-fill">
                <div class="cr-stat-label">Prize (<?php echo $quiz['prize_type'] === 'money' ? 'Cash' : ucfirst(htmlspecialchars($quiz['prize_type'] ?? 'Reward')); ?>)</div>
                <div class="cr-stat-value" style="color:#059669;"><?php echo $prizeDisplay; ?></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($existingClaim): ?>
            <!-- Reward Sent / Paid Success State -->
            <?php if (in_array($existingClaim['status'], ['sent', 'paid'])): ?>
            <div class="cr-submitted shadow-sm" style="background: linear-gradient(to bottom, #f0fdf4, #fff); border-color: #34d399;">
                <div style="width:80px;height:80px;background:linear-gradient(135deg,#10b981,#059669);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem; box-shadow: 0 4px 15px rgba(16,185,129,0.4);">
                    <i class="fas fa-gift" style="font-size:2rem;color:#fff;"></i>
                </div>
                <h3 class="fw-bold mb-2 text-success">Reward Sent!</h3>
                <p class="text-muted mb-4" style="font-size:1.05rem;">Congratulations! Your reward has been successfully processed and sent to your provided details. Keep learning, keep challenging yourself, and aim for the top in the next quiz!</p>
                
                <div class="d-flex justify-content-center gap-2 mb-4">
                    <span class="cr-status-badge cr-status-paid" style="font-size:0.9rem; padding: 0.5rem 1.25rem;">
                        <i class="fas fa-check-circle"></i> Successfully Sent
                    </span>
                </div>
            <?php else: ?>
            <!-- Already Submitted (Processing/Pending) -->
            <div class="cr-submitted">
                <div style="width:60px;height:60px;background:linear-gradient(135deg,#10b981,#059669);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                    <i class="fas fa-check" style="font-size:1.5rem;color:#fff;"></i>
                </div>
                <h4 class="fw-bold mb-2">Claim Submitted!</h4>
                <p class="text-muted mb-3">Your reward claim details have been received. We'll process your payment shortly.</p>
                
                <div class="d-flex justify-content-center gap-2 mb-3">
                    <?php 
                    $statusClass = 'cr-status-' . $existingClaim['status'];
                    $statusIcon = $existingClaim['status'] === 'processing' ? 'fa-spinner fa-spin' : 'fa-clock';
                    ?>
                    <span class="cr-status-badge <?php echo $statusClass; ?>">
                        <i class="fas <?php echo $statusIcon; ?>"></i>
                        <?php echo ucfirst($existingClaim['status']); ?>
                    </span>
                </div>
            <?php endif; ?>

                <div style="background:#f8fafc;border-radius:12px;padding:1rem;font-size:0.85rem;color:#475569;text-align:left;">
                    <?php if (!empty($existingClaim['amount'])): ?>
                        <div class="mb-2 p-2 bg-success text-white rounded shadow-sm text-center">
                            <strong>Locked Amount:</strong> <?php echo in_array($quiz['prize_type'] ?? '', ['money', 'airtime', 'data'], true) ? format_prize((float)$existingClaim['amount'], $quiz['prize_type']) : number_format((float)$existingClaim['amount']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($quiz['prize_type'] === 'money'): ?>
                        <strong>Bank:</strong> <?php echo htmlspecialchars($existingClaim['bank_name']); ?><br>
                        <strong>Account:</strong> <?php echo htmlspecialchars($existingClaim['account_number']); ?><br>
                        <strong>Name:</strong> <?php echo htmlspecialchars($existingClaim['account_name']); ?>
                    <?php elseif (in_array($quiz['prize_type'], ['airtime', 'data'])): ?>
                        <strong>Network:</strong> <?php echo htmlspecialchars($existingClaim['bank_name']); ?><br>
                        <strong>Phone Number:</strong> <?php echo htmlspecialchars($existingClaim['phone']); ?>
                    <?php else: ?>
                        <strong>Prize:</strong> <?php echo htmlspecialchars($quiz['prize_text']); ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($existingClaim['admin_notes'])): ?>
                <div class="mt-3 p-3" style="background:#fef3c7;border:1px solid #fde68a;border-radius:12px;font-size:0.85rem;color:#92400e;">
                    <i class="fas fa-comment-alt me-1"></i> <strong>Admin Note:</strong> <?php echo htmlspecialchars($existingClaim['admin_notes']); ?>
                </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Claim Form -->
            <h4 class="fw-bold mb-1">Claim Your Reward</h4>
            <?php if (in_array($quiz['prize_type'] ?? '', ['airtime', 'data'])): ?>
                <p class="text-muted mb-4" style="font-size:0.9rem;">Please select your network provider and enter your phone number so we can send your <?php echo htmlspecialchars($quiz['prize_type']); ?>.</p>
            <?php elseif ($quiz['prize_type'] === 'money'): ?>
                <p class="text-muted mb-4" style="font-size:0.9rem;">Please provide your bank details below so we can send your prize.</p>
            <?php else: ?>
                <p class="text-muted mb-4" style="font-size:0.9rem;">Please provide your details below so we can process your reward.</p>
            <?php endif; ?>

            <?php if ($submitError): ?>
                <div class="alert alert-danger" style="border-radius:12px;"><i class="fas fa-exclamation-circle me-1"></i> <?php echo htmlspecialchars($submitError); ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                
                <?php if ($calculatedAmount > 0): ?>
                <div class="cr-form-group mb-3 p-3 bg-light rounded border border-success">
                    <label class="form-label text-success fw-bold"><i class="fas fa-lock me-1"></i> Locked Prize Share</label>
                    <input type="text" class="form-control text-success fw-bold" style="font-size: 1.25rem; background: #fff;" value="<?php echo $detailedAmountDisplay; ?>" disabled readonly>
                    <small class="text-muted mt-1 d-block"><i class="fas fa-info-circle"></i> This cut is guaranteed centrally by the system relative to the total prize pool and your match rank.</small>
                </div>
                <?php endif; ?>
                
                <?php if ($quiz['prize_type'] === 'money'): ?>
                    <div class="cr-form-group mb-3">
                        <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required placeholder="Your full legal name">
                    </div>
                    
                    <div class="cr-form-group mb-3">
                        <label for="bank_name" class="form-label">Bank Name <span class="text-danger">*</span></label>
                        <select class="form-control" id="bank_name" name="bank_name" required>
                            <option value="">Select your bank...</option>
                            <option value="Access Bank">Access Bank</option>
                            <option value="Citibank">Citibank</option>
                            <option value="Ecobank">Ecobank</option>
                            <option value="Fidelity Bank">Fidelity Bank</option>
                            <option value="First Bank of Nigeria">First Bank of Nigeria</option>
                            <option value="FCMB">First City Monument Bank</option>
                            <option value="Globus Bank">Globus Bank</option>
                            <option value="GTBank">Guaranty Trust Bank</option>
                            <option value="Heritage Bank">Heritage Bank</option>
                            <option value="Keystone Bank">Keystone Bank</option>
                            <option value="Kuda Bank">Kuda Microfinance Bank</option>
                            <option value="Moniepoint">Moniepoint</option>
                            <option value="OPay">OPay</option>
                            <option value="PalmPay">PalmPay</option>
                            <option value="Polaris Bank">Polaris Bank</option>
                            <option value="Providus Bank">Providus Bank</option>
                            <option value="Stanbic IBTC">Stanbic IBTC Bank</option>
                            <option value="Standard Chartered">Standard Chartered</option>
                            <option value="Sterling Bank">Sterling Bank</option>
                            <option value="SunTrust Bank">SunTrust Bank</option>
                            <option value="Titan Trust Bank">Titan Trust Bank</option>
                            <option value="Union Bank">Union Bank</option>
                            <option value="UBA">United Bank for Africa (UBA)</option>
                            <option value="Unity Bank">Unity Bank</option>
                            <option value="Wema Bank">Wema Bank</option>
                            <option value="Zenith Bank">Zenith Bank</option>
                        </select>
                    </div>
                    
                    <div class="cr-form-group mb-3">
                        <label for="account_number" class="form-label">Account Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="account_number" name="account_number" required placeholder="10-digit account number" maxlength="20">
                    </div>
                    
                    <div class="cr-form-group mb-3">
                        <label for="account_name" class="form-label">Account Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="account_name" name="account_name" required placeholder="Name on your bank account">
                    </div>
                    
                    <div class="cr-form-group mb-4">
                        <label for="phone" class="form-label">Phone Number <span class="text-muted">(optional)</span></label>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="For follow-up contact">
                    </div>
                <?php elseif (in_array($quiz['prize_type'] ?? '', ['airtime', 'data'])): ?>
                    <div class="cr-form-group mb-3">
                        <label for="bank_name" class="form-label">Network Provider <span class="text-danger">*</span></label>
                        <select class="form-control" id="bank_name" name="bank_name" required>
                            <option value="">Select Network...</option>
                            <option value="MTN">MTN</option>
                            <option value="Airtel">Airtel</option>
                            <option value="Glo">Glo</option>
                            <option value="9mobile">9mobile</option>
                        </select>
                    </div>
                    
                    <div class="cr-form-group mb-4">
                        <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="phone" name="phone" required placeholder="e.g. 08012345678" maxlength="15">
                    </div>
                <?php else: ?>
                    <!-- Fallback: prize_type is empty or text — show Full Name + Phone -->
                    <div class="cr-form-group mb-3">
                        <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required placeholder="Your full legal name">
                    </div>
                    <div class="cr-form-group mb-4">
                        <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="phone" name="phone" required placeholder="e.g. 08012345678" maxlength="15">
                    </div>
                <?php endif; ?>
                
                <div class="text-center">
                    <button type="submit" class="cr-submit-btn">
                        <i class="fas fa-paper-plane"></i> Submit Claim
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="<?php echo SITE_URL; ?>/challenge_results.php?quiz_id=<?php echo $quizId; ?>" class="text-decoration-none" style="color:#64748b;font-weight:600;font-size:0.9rem;">
                <i class="fas fa-trophy me-1"></i> View Full Leaderboard
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
