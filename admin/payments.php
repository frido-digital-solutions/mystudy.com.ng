<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Payment.php';

require_admin();

if (!admin_can('payments')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}

$paymentModel = new Payment($pdo);
$page_title = "Manage Payments";

// Handle Actions (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['ref'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('error', 'Invalid CSRF token.');
    } else {
        $ref = $_POST['ref'];
        $action = $_POST['action'];
        if ($action === 'approve') {
            if ($paymentModel->updateStatusByRef($ref, 'successful')) {
                // Auto-generate access code for this user/quiz
                $pRow = $paymentModel->getPaymentByRef($ref);
                if ($pRow) {
                    require_once __DIR__ . '/../models/AccessCode.php';
                    $acModel = new AccessCode($pdo);
                    $newCode = $acModel->generateForUser($pRow['quiz_id'], $pRow['user_id']);

                    // Send notification + email
                    require_once __DIR__ . '/../models/Quiz.php';
                    require_once __DIR__ . '/../models/Notification.php';
                    require_once __DIR__ . '/../models/User.php';
                    $qModel = new Quiz($pdo);
                    $quizData = $qModel->getById($pRow['quiz_id']);
                    $uModel = new User($pdo);
                    $uModel->getById($pRow['user_id']);
                    $quizTitle = $quizData['title'] ?? 'Special Quiz';
                    $quizLink = SITE_URL . '/quiz_special.php?id=' . $pRow['quiz_id'];

                    // In-app notification
                    $nModel = new Notification($pdo);
                    $nTitle = '🎟️ Payment Approved: ' . $quizTitle;
                    $nMsg  = "Great news! Your payment receipt has been approved.\n\n";
                    $nMsg .= "Quiz: {$quizTitle}\n";
                    $nMsg .= "Access Code: {$newCode}\n\n";
                    $nMsg .= "Your access code has been automatically applied. Click below to go to the quiz.";
                    $nModel->create($pRow['user_id'], 'payment_approved', $nTitle, $nMsg, $quizLink);

                    // Email notification via Brevo API
                    if (!empty($uModel->email)) {
                        try {
                            sendProductionEmail($uModel->email, $uModel->name ?? 'Student', 'access_code', [
                                'student_name' => $uModel->name ?? 'Student',
                                'quiz_title' => $quizTitle,
                                'access_code' => $newCode,
                                'quiz_url' => $quizLink
                            ]);
                        } catch (Exception $e) {
                            error_log('Payment approval email failed: ' . $e->getMessage());
                        }
                    }
                }
                set_flash_message('success', 'Payment approved successfully. Access Code auto-generated and user notified.');
            } else {
                set_flash_message('error', 'Failed to approve payment.');
            }
        } elseif ($action === 'reject') {
            if ($paymentModel->updateStatusByRef($ref, 'failed')) {
                set_flash_message('success', 'Payment rejected.');
            } else {
                set_flash_message('error', 'Failed to reject payment.');
            }
        }
    }
    redirect(SITE_URL . '/admin/payments.php');
}

// Fetch all payments (simple query for admin)
$stmt = $pdo->query("
    SELECT p.*, u.name as user_name, u.email as user_email, q.title as quiz_title
    FROM quiz_payments p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN quizzes q ON p.quiz_id = q.id
    ORDER BY p.created_at DESC
");
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2"><i class="fas fa-money-bill-wave text-success me-2"></i> Payments & Receipts</h1>
            </div>

            <?php echo display_flash_message(); ?>

            <div class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>User</th>
                                <th>Quiz</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No payments found.</td></tr>
                            <?php else: ?>
                            <?php foreach ($payments as $p): ?>
                            <tr>
                                <td data-label="Date"><?php echo date('M d, Y H:i', strtotime($p['created_at'])); ?></td>
                                <td data-label="Reference"><span class="badge bg-secondary"><?php echo htmlspecialchars($p['payment_ref']); ?></span></td>
                                <td data-label="User">
                                    <strong><?php echo htmlspecialchars($p['user_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($p['user_email']); ?></small>
                                </td>
                                <td data-label="Quiz"><?php echo htmlspecialchars($p['quiz_title']); ?></td>
                                <td data-label="Amount">₦<?php echo number_format($p['amount'], 0); ?></td>
                                <td data-label="Status">
                                    <?php
                                    $bClass = 'bg-warning text-dark';
                                    $icon = '<i class="fas fa-clock fs-xs me-1"></i>';
                                    if ($p['status'] === 'successful') { $bClass = 'bg-success'; $icon = '<i class="fas fa-check-circle fs-xs me-1"></i>'; }
                                    if ($p['status'] === 'failed') { $bClass = 'bg-danger'; $icon = '<i class="fas fa-times-circle fs-xs me-1"></i>'; }
                                    ?>
                                    <span class="badge rounded-pill shadow-sm <?php echo $bClass; ?>" style="font-weight:600; padding:0.4em 0.8em; letter-spacing:0.5px;">
                                        <?php echo $icon . ' ' . ucfirst($p['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Action">
                                    <?php if (!empty($p['receipt_path'])): ?>
                                        <button class="btn btn-sm btn-outline-primary shadow-sm" onclick="viewReceipt('<?php echo SITE_URL . $p['receipt_path']; ?>', '<?php echo htmlspecialchars($p['payment_ref']); ?>')">
                                            <i class="fas fa-file-invoice"></i> View Receipt
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($p['status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline ms-1 mt-1 mt-md-0">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                        <input type="hidden" name="ref" value="<?php echo htmlspecialchars($p['payment_ref']); ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-sm btn-success shadow-sm" onclick="return confirm('Approve this payment?');"><i class="fas fa-check me-1"></i>Approve</button>
                                    </form>
                                    <form method="POST" class="d-inline ms-1 mt-1 mt-md-0">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                        <input type="hidden" name="ref" value="<?php echo htmlspecialchars($p['payment_ref']); ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-sm btn-danger shadow-sm" onclick="return confirm('Reject this payment?');"><i class="fas fa-times me-1"></i>Reject</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(5px);">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow:hidden;">
      <div class="modal-header border-0" style="background:#0f172a; color:#fff; padding:1.25rem 1.5rem;">
        <h5 class="modal-title fw-bold"><i class="fas fa-file-invoice" style="color:#60a5fa;"></i> Receipt <span id="receiptRef" style="color:#fbbf24; font-family:monospace; margin-left:0.5rem; font-size:1.1rem;"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" style="background:#e2e8f0; display:flex; align-items:center; justify-content:center; height: 50vh; min-height: 250px; max-height: 600px;">
        <div id="receiptContainer" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; overflow:auto; padding:0.5rem;">
            <!-- content injected by JS -->
        </div>
      </div>
      <div class="modal-footer border-0 bg-white">
        <button type="button" class="btn btn-secondary px-4 shadow-sm" style="border-radius: 8px;" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
var receiptModal = null;

function viewReceipt(url, ref) {
    if (!receiptModal) {
        var modalEl = document.getElementById('receiptModal');
        if (typeof bootstrap !== 'undefined') {
            receiptModal = new bootstrap.Modal(modalEl);
        } else {
            console.error("Bootstrap is not loaded yet.");
            alert("Please wait a moment for the page to fully load before viewing receipts.");
            return;
        }
    }

    document.getElementById('receiptRef').textContent = ref;
    var container = document.getElementById('receiptContainer');
    
    // Check if PDF or Image
    var ext = url.split('.').pop().toLowerCase();
    container.innerHTML = '<div class="spinner-border text-primary my-5" role="status"></div>';
    
    setTimeout(function() {
        if (ext === 'pdf') {
            container.innerHTML = '<iframe src="' + url + '" style="width:100%; height:100%; border:none;"></iframe>';
        } else {
            container.innerHTML = '<img src="' + url + '" style="max-width:100%; max-height:100%; object-fit:contain;">';
        }
    }, 300); // slight delay for smooth transition
    
    receiptModal.show();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

