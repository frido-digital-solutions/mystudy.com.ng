<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/AccessCode.php';
require_once __DIR__ . '/../models/Quiz.php';

require_admin();

if (!admin_can('access_codes')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}

$accessCodeModel = new AccessCode($pdo);
$quizModel = new Quiz($pdo);

$page_title = "Manage Access Codes";

// Handle Generate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('error', 'Invalid CSRF token.');
    } else {
        $quizId = (int)$_POST['quiz_id'];
        $userEmail = trim($_POST['user_email']);
        
        $stmtU = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
        $stmtU->execute([':e' => $userEmail]);
        $u = $stmtU->fetch();

        if ($quizId > 0 && $u) {
            require_once __DIR__ . '/../models/Payment.php';
            $paymentModel = new Payment($pdo);
            
            // Check if already paid/generated
            if ($paymentModel->hasPaid($u['id'], $quizId)) {
                 set_flash_message('error', 'User already has access to this quiz.');
            } else {
                 $ref = 'ADMIN-GEN-' . time() . '-' . rand(100,999);
                 $paymentModel->recordPayment($u['id'], $quizId, 0, $ref, 'successful');
                 
                 $code = $accessCodeModel->generateForUser($quizId, $u['id']);
                 set_flash_message('success', "Successfully generated access code <strong>$code</strong> for $userEmail.");
            }
        } else {
            set_flash_message('error', 'Invalid quiz or user email not found.');
        }
    }
    redirect(SITE_URL . '/admin/access_codes.php');
}

// Fetch all paid quizzes for the dropdown
$stmtQ = $pdo->query("SELECT id, title FROM quizzes WHERE is_paid = 1 ORDER BY created_at DESC");
$paidQuizzes = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

// Fetch access codes
$quizFilter = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$query = "
    SELECT a.*, q.title as quiz_title, u.email as used_by_email
    FROM quiz_access_codes a
    JOIN quizzes q ON a.quiz_id = q.id
    LEFT JOIN users u ON a.used_by_user_id = u.id
";
$params = [];
if ($quizFilter > 0) {
    $query .= " WHERE a.quiz_id = :q";
    $params[':q'] = $quizFilter;
}
$query .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$codes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] == 1) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="access_codes_export_'.time().'.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Quiz', 'Code', 'Status', 'Used By', 'Generated On']);
    foreach ($codes as $c) {
        $status = $c['is_used'] ? 'Used' : 'Unused';
        $usedBy = $c['used_by_email'] ?? 'N/A';
        fputcsv($output, [$c['quiz_title'], $c['code'], $status, $usedBy, $c['created_at']]);
    }
    fclose($output);
    exit;
}

include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2"><i class="fas fa-key text-primary me-2"></i> Access Codes</h1>
                
                <div>
                    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#generateModal" style="border-radius: 8px;">
                        <i class="fas fa-user-plus me-1"></i> Grant User Access
                    </button>
                    <?php if (!empty($codes)): ?>
                        <a href="?export=1<?php echo $quizFilter ? '&quiz_id='.$quizFilter : ''; ?>" class="btn btn-outline-success shadow-sm ms-2">
                            <i class="fas fa-file-csv me-1"></i> Export CSV
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php echo display_flash_message(); ?>

            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-body bg-light rounded-top">
                    <form method="GET" class="row gx-2 gy-2 align-items-center">
                        <div class="col-auto">
                            <label class="visually-hidden" for="quizFilter">Filter by Quiz</label>
                            <select name="quiz_id" id="quizFilter" class="form-select">
                                <option value="0">All Paid Quizzes</option>
                                <?php foreach($paidQuizzes as $q): ?>
                                    <option value="<?php echo $q['id']; ?>" <?php echo $quizFilter == $q['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($q['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-secondary">Filter</button>
                            <?php if ($quizFilter > 0): ?>
                            <a href="access_codes.php" class="btn btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Generated</th>
                                <th>Quiz</th>
                                <th>Access Code</th>
                                <th>Status</th>
                                <th>Used By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($codes)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No access codes found.</td></tr>
                            <?php else: ?>
                            <?php foreach ($codes as $c): ?>
                            <tr>
                                <td data-label="Generated"><?php echo date('M d, Y H:i', strtotime($c['created_at'])); ?></td>
                                <td data-label="Quiz"><?php echo htmlspecialchars($c['quiz_title']); ?></td>
                                <td data-label="Access Code"><span class="font-monospace fw-bold px-2 py-1 bg-dark text-white rounded" style="letter-spacing:1px;"><?php echo htmlspecialchars($c['code']); ?></span></td>
                                <td data-label="Status">
                                    <?php if ($c['is_used']): ?>
                                        <span class="badge bg-danger">Redeemed</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Used By">
                                    <?php if ($c['is_used']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($c['used_by_email']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
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

<!-- Generate Modal -->
<div class="modal fade" id="generateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow:hidden;">
      <div class="modal-header text-white border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b);">
        <h5 class="modal-title"><i class="fas fa-shield-check me-2" style="color:#fbbf24;"></i> Grant Code Access</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="action" value="generate">
          
          <div class="modal-body p-4 bg-light">
            <div class="mb-4">
                <label class="form-label fw-bold text-dark">Select Quiz</label>
                <select name="quiz_id" class="form-select form-select-lg shadow-sm" required style="border-radius: 8px; border: 1px solid #cbd5e1;">
                    <option value="">-- Choose Quiz --</option>
                    <?php foreach($paidQuizzes as $q): ?>
                        <option value="<?php echo $q['id']; ?>"><?php echo htmlspecialchars($q['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold text-dark">User Email</label>
                <div class="input-group input-group-lg shadow-sm" style="border-radius: 8px; overflow: hidden;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="user_email" class="form-control border-start-0" placeholder="student@example.com" required>
                </div>
                <div class="form-text text-muted mt-2"><i class="fas fa-info-circle"></i> This auto-generates a code and records a successful payment for them.</div>
            </div>
          </div>
          <div class="modal-footer bg-white border-top">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
            <button type="submit" class="btn btn-primary shadow-sm" style="border-radius: 8px;"><i class="fas fa-check-circle me-1"></i> Grant Access</button>
          </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

