<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('rewards')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}

$page_title = 'Reward Fulfillment';

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS reward_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    user_id INT NOT NULL,
    rank_position INT NOT NULL,
    full_name VARCHAR(255) NOT NULL DEFAULT '',
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

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_claim') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        set_flash_message('error', 'Invalid token.');
        redirect(SITE_URL . '/admin/rewards.php');
    }
    
    $claimId = (int)$_POST['claim_id'];
    $newStatus = trim($_POST['status']);
    $adminNotes = trim($_POST['admin_notes'] ?? '');
    
    if (in_array($newStatus, ['pending', 'processing', 'sent', 'rejected'])) {
        $stmt = $pdo->prepare("SELECT c.*, q.title as quiz_title FROM reward_claims c JOIN quizzes q ON c.quiz_id = q.id WHERE c.id = ?");
        $stmt->execute([$claimId]);
        $claim = $stmt->fetch();
        
        if ($claim) {
            $upd = $pdo->prepare("UPDATE reward_claims SET status = ?, admin_notes = ? WHERE id = ?");
            if ($upd->execute([$newStatus, $adminNotes, $claimId])) {
                set_flash_message('success', 'Claim status updated to ' . ucfirst($newStatus) . '.');
                
                require_once __DIR__ . '/../models/Notification.php';
                $notif = new Notification($pdo);

                // Notify user if sent
                if ($newStatus === 'sent' && $claim['status'] !== 'sent') {
                    $notif->create(
                        (int)$claim['user_id'],
                        'reward_sent',
                        'Reward Sent: ' . $claim['quiz_title'],
                        "Congratulations! Your reward for ranking #" . $claim['rank_position'] . " in '" . $claim['quiz_title'] . "' has been completely processed and sent.\n\nAdmin Note: " . $adminNotes,
                        SITE_URL . '/quiz_special.php?id=' . $claim['quiz_id']
                    );
                }

                // A claim that cannot be paid as submitted goes BACK to the
                // student with the reason, not into a dead end: the reason is the
                // one thing that lets them fix the account details and try again.
                if ($newStatus === 'rejected') {
                    $reason = $adminNotes !== '' ? $adminNotes : 'The bank details on the claim could not be used as they were.';
                    $notif->create(
                        (int)$claim['user_id'],
                        'reward_returned',
                        'Action needed: ' . $claim['quiz_title'],
                        "Your reward claim for ranking #" . $claim['rank_position'] . " in '" . $claim['quiz_title'] . "' has been sent back by an admin.\n\nReason: " . $reason . "\n\nOpen the claim, correct your bank details and send it again.",
                        SITE_URL . '/claim_reward.php?quiz_id=' . $claim['quiz_id']
                    );
                }
            } else {
                set_flash_message('error', 'Failed to update claim.');
            }
        }
    }
    redirect(SITE_URL . '/admin/rewards.php');
}

$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$whereClause = "";
$params = [];
if ($statusFilter && in_array($statusFilter, ['pending', 'processing', 'sent', 'rejected'])) {
    $whereClause = "WHERE c.status = ?";
    $params[] = $statusFilter;
}

$sql = "SELECT c.*, q.title as quiz_title, q.prize_type, u.name as user_name, u.email as user_email 
        FROM reward_claims c 
        JOIN quizzes q ON c.quiz_id = q.id 
        JOIN users u ON c.user_id = u.id 
        $whereClause 
        ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h4 mb-0">Reward Fulfillment Tracking</h1>
        
        <div class="btn-group">
            <a href="?status=" class="btn btn-sm <?php echo $statusFilter === '' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
            <a href="?status=pending" class="btn btn-sm <?php echo $statusFilter === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">Pending</a>
            <a href="?status=processing" class="btn btn-sm <?php echo $statusFilter === 'processing' ? 'btn-info' : 'btn-outline-info'; ?>">Processing</a>
            <a href="?status=sent" class="btn btn-sm <?php echo $statusFilter === 'sent' ? 'btn-success' : 'btn-outline-success'; ?>">Sent</a>
            <a href="?status=rejected" class="btn btn-sm <?php echo $statusFilter === 'rejected' ? 'btn-danger' : 'btn-outline-danger'; ?>">Sent back</a>
        </div>
      </div>
      
      <?php if (empty($claims)): ?>
        <div class="alert alert-info border-0 shadow-sm" style="background:#e0f2fe; color:#0369a1;">
            <i class="fas fa-info-circle me-2"></i> No reward claims found the current filter.
        </div>
      <?php else: ?>
        <div class="row g-4">
            <?php foreach ($claims as $c): 
                $bgColor = '#fff';
                $badgeClass = 'bg-secondary';
                if ($c['status'] === 'pending') { $badgeClass = 'bg-warning text-dark'; $bgColor = '#fffbeb'; }
                elseif ($c['status'] === 'processing') { $badgeClass = 'bg-info text-dark'; $bgColor = '#eff6ff'; }
                elseif ($c['status'] === 'sent') { $badgeClass = 'bg-success'; $bgColor = '#f0fdf4'; }
                elseif ($c['status'] === 'rejected') { $badgeClass = 'bg-danger'; $bgColor = '#fef2f2'; }
                
                $prizeType = $c['prize_type'] ?? 'money';
                $topColor = ['sent' => '#10b981', 'processing' => '#3b82f6', 'rejected' => '#ef4444'][$c['status']] ?? '#f59e0b';
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0" style="border-radius:12px; border-top: 4px solid <?php echo $topColor; ?> !important; background: <?php echo $bgColor; ?>;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title fw-bold mb-0 text-truncate" title="<?php echo htmlspecialchars($c['quiz_title']); ?>">
                                <?php echo htmlspecialchars($c['quiz_title']); ?>
                            </h5>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $c['status'] === 'rejected' ? 'Sent back' : ucfirst($c['status']); ?></span>
                        </div>
                        
                        <div class="mb-3 text-muted small">
                            <i class="fas fa-trophy text-warning me-1"></i> Rank #<?php echo $c['rank_position']; ?> &nbsp;|&nbsp;
                            <i class="fas fa-calendar-alt me-1"></i> <?php echo date('M j, g:i A', strtotime($c['created_at'])); ?>
                        </div>
                        
                        <div class="p-3 rounded mb-3" style="background: rgba(255,255,255,0.6); border: 1px solid rgba(0,0,0,0.05);">
                            <div class="fw-bold mb-1"><i class="fas fa-user-circle text-secondary me-2"></i> <?php echo htmlspecialchars($c['full_name']); ?></div>
                            <div class="text-muted small mb-2"><a href="mailto:<?php echo htmlspecialchars($c['user_email']); ?>" class="text-decoration-none"><?php echo htmlspecialchars($c['user_email']); ?></a></div>
                            
                            <?php if (!empty($c['amount'])): ?>
                                <div class="mb-2 p-2 bg-success text-white rounded shadow-sm text-center fw-bold" style="font-size: 0.9rem;">
                                    <i class="fas fa-lock me-1"></i> Locked Prize Share: <?php echo format_prize($c['amount'], $prizeType); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($prizeType === 'money'): ?>
                                <div class="small"><strong>Bank:</strong> <?php echo htmlspecialchars($c['bank_name'] ?: 'N/A'); ?></div>
                                <div class="small"><strong>Account No:</strong> <?php echo htmlspecialchars($c['account_number'] ?: 'N/A'); ?></div>
                                <div class="small"><strong>Account Name:</strong> <?php echo htmlspecialchars($c['account_name'] ?: 'N/A'); ?></div>
                            <?php elseif (in_array($prizeType, ['airtime', 'data'])): ?>
                                <div class="small"><strong>Prize Type:</strong> <?php echo ucfirst($prizeType); ?></div>
                                <div class="small"><strong>Network:</strong> <?php echo htmlspecialchars($c['bank_name'] ?: 'N/A'); ?></div>
                                <div class="small"><strong>Phone Number:</strong> <?php echo htmlspecialchars($c['phone'] ?: 'N/A'); ?></div>
                            <?php else: ?>
                                <div class="small"><strong>Prize Type:</strong> <?php echo ucfirst($prizeType); ?></div>
                                <div class="small"><strong>Details:</strong> <?php echo htmlspecialchars($c['bank_name'] ?: 'N/A'); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <input type="hidden" name="action" value="update_claim">
                            <input type="hidden" name="claim_id" value="<?php echo $c['id']; ?>">
                            
                            <div class="mb-2">
                                <label class="form-label small fw-bold">Update Status</label>
                                <select name="status" class="form-select form-select-sm mb-2">
                                    <option value="pending" <?php echo $c['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $c['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="sent" <?php echo $c['status'] === 'sent' ? 'selected' : ''; ?>>Sent (Notifies User)</option>
                                    <option value="rejected" <?php echo $c['status'] === 'rejected' ? 'selected' : ''; ?>>Send back - ask for a fix (Notifies User)</option>
                                </select>
                                <div class="small text-muted">
                                    <i class="fas fa-undo me-1"></i>On <strong>Send back</strong> the note below becomes the reason the
                                    student sees, and their claim page re-opens with the details editable so they can send it again.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Admin Notes</label>
                                <textarea name="admin_notes" class="form-control form-control-sm" rows="2" placeholder="Tracking ID, transaction ref, or the exact detail that is wrong..."><?php echo htmlspecialchars($c['admin_notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-sm btn-dark w-100">Save Update</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

