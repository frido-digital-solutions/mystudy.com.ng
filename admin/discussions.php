<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('discussions')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}
require_once __DIR__ . '/../models/Discussion.php';

$discussionModel = new Discussion($pdo);

// Handle Report Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid form token. Please try again.');
        header('Location: ' . SITE_URL . '/admin/discussions.php?tab=' . ($_POST['tab'] ?? 'reports'));
        exit;
    }

    $action = $_POST['action'] ?? '';
    $reportId = (int)($_POST['report_id'] ?? 0);
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM discussion_reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($report) {
            require_once __DIR__ . '/../models/Notification.php';
            $notif = new Notification($pdo);
            $reporterId = (int)$report['user_id'];
            $targetType = $report['target_type'];
            $ownerId = $discussionModel->getAuthorIdByTarget($targetType, (int)$report['target_id']);
            
            if ($action === 'dismiss') {
                $pdo->prepare("UPDATE discussion_reports SET status = 'dismissed' WHERE id = ?")->execute([$reportId]);
                $notif->create($reporterId, 'report_dismissed', 'ℹ️ Report Dismissed', "Your report on a $targetType was reviewed and found to not violate guidelines.");
                if ($ownerId) {
                    $notif->create($ownerId, 'content_cleared', '✅ Content Cleared', "A report against your $targetType was reviewed and no violations were found.");
                }
                set_flash_message('success', 'Report dismissed.');
            } elseif ($action === 'delete') {
                if ($targetType === 'discussion') {
                    $discussionModel->deleteDiscussion((int)$report['target_id'], 0, true);
                } else {
                    $discussionModel->softDeleteComment((int)$report['target_id'], 0, true);
                }
                $pdo->prepare("UPDATE discussion_reports SET status = 'resolved' WHERE id = ?")->execute([$reportId]);
                $notif->create($reporterId, 'report_resolved', '🛡️ Report Resolved', "Thank you! The reported $targetType has been removed.");
                if ($ownerId) {
                    $pdo->prepare("UPDATE users SET report_strikes = report_strikes + 1 WHERE id = ?")->execute([$ownerId]);
                    $uStmt = $pdo->prepare("SELECT report_strikes FROM users WHERE id = ?");
                    $uStmt->execute([$ownerId]);
                    $strikes = (int)$uStmt->fetchColumn();
                    if ($strikes >= 3) {
                        $pdo->prepare("UPDATE users SET is_frozen = 1 WHERE id = ?")->execute([$ownerId]);
                        $notif->create($ownerId, 'account_frozen', '❄️ Account Frozen', "Your account has been frozen after 3 content violations.");
                    } else {
                        $notif->create($ownerId, 'content_deleted', '⚠️ Content Deleted', "Your $targetType was removed by a moderator. Strike $strikes/3.");
                    }
                }
                set_flash_message('success', 'Content deleted, user penalized.');
            }
        }
    } catch (Exception $e) {
        set_flash_message('error', 'Action failed: ' . $e->getMessage());
    }
    
    header('Location: ' . SITE_URL . '/admin/discussions.php?tab=' . ($_POST['tab'] ?? 'reports'));
    exit;
}

// Active tab
$tab = $_GET['tab'] ?? 'all';
if (!in_array($tab, ['all', 'reports'])) $tab = 'all';

// Fetch all discussions for "All" tab
$allDiscussions = [];
$totalDiscussions = 0;
if ($tab === 'all') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    $allDiscussions = $discussionModel->getDiscussions($perPage, $offset, 'newest');
    $totalDiscussions = (int)$pdo->query("SELECT COUNT(*) FROM discussions")->fetchColumn();
}

// Fetch pending reports for "Reports" tab
$pendingReports = [];
if ($tab === 'reports') {
    $sql = "
        SELECT r.*, 
               u.name as reporter_name,
               d.title as disc_title, d.content as disc_content,
               c.content as comm_content, c.discussion_id as comm_disc_id
        FROM discussion_reports r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN discussions d ON r.target_type = 'discussion' AND r.target_id = d.id
        LEFT JOIN discussion_comments c ON r.target_type = 'comment' AND r.target_id = c.id
        WHERE r.status = 'pending'
        ORDER BY r.created_at ASC
    ";
    $pendingReports = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

$reportCount = (int)$pdo->query("SELECT COUNT(*) FROM discussion_reports WHERE status = 'pending'")->fetchColumn();

$page_title = 'Discussion Management';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="fw-bold mb-0 text-dark">Discussion Management</h2>
                <p class="text-secondary mb-0">View all forum questions, moderate reports, and manage content.</p>
            </div>
            <a href="<?= SITE_URL ?>/discussions.php" target="_blank" class="btn btn-outline-primary fw-bold">
                <i class="fa-solid fa-arrow-up-right-from-square me-2"></i> View Live Forum
            </a>
        </div>

        <?php echo display_flash_message(); ?>

        <!-- Tabs -->
        <ul class="nav nav-pills mb-3">
            <li class="nav-item me-2">
                <a class="nav-link <?= $tab === 'all' ? 'active' : '' ?>" href="?tab=all">
                    <i class="fas fa-list me-1"></i> All Discussions
                    <span class="badge bg-light text-dark ms-1"><?= $totalDiscussions ?: (int)$pdo->query("SELECT COUNT(*) FROM discussions")->fetchColumn() ?></span>
                </a>
            </li>
            <li class="nav-item me-2">
                <a class="nav-link <?= $tab === 'reports' ? 'active' : '' ?>" href="?tab=reports">
                    <i class="fas fa-flag me-1"></i> Pending Reports
                    <span class="badge <?= $reportCount > 0 ? 'bg-danger' : 'bg-light text-dark' ?> ms-1"><?= $reportCount ?></span>
                </a>
            </li>
        </ul>

        <?php if ($tab === 'all'): ?>
        <!-- ==================== ALL DISCUSSIONS TAB ==================== -->
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>All Forum Discussions</strong>
                <div>
                    <button class="btn btn-sm btn-danger d-none" id="bulkDeleteBtn" onclick="bulkAction('delete','discussion')">
                        <i class="fas fa-trash me-1"></i> Delete Selected (<span id="selectedCount">0</span>)
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:30px;"><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Subject</th>
                                <th>Comments</th>
                                <th>Votes</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allDiscussions)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No discussions found.</td></tr>
                            <?php else: foreach ($allDiscussions as $d): ?>
                                <tr>
                                    <td><input type="checkbox" class="row-check" value="<?= (int)$d['id'] ?>" onchange="updateCount()"></td>
                                    <td data-label="Title">
                                        <div class="fw-semibold text-truncate" style="max-width:300px;"><?= htmlspecialchars($d['title']) ?></div>
                                        <div class="small text-muted text-truncate" style="max-width:300px;"><?= htmlspecialchars(mb_strimwidth(strip_tags($d['content']), 0, 80, '...')) ?></div>
                                    </td>
                                    <td data-label="Author" class="text-muted small"><?= htmlspecialchars($d['author_name'] ?? 'Unknown') ?></td>
                                    <td data-label="Subject"><span class="badge bg-secondary"><?= htmlspecialchars($d['subject'] ?? '—') ?></span></td>
                                    <td data-label="Comments"><?= (int)($d['comment_count'] ?? 0) ?></td>
                                    <td data-label="Votes">
                                        <span class="text-success"><i class="fas fa-arrow-up"></i> <?= (int)($d['upvotes'] ?? 0) ?></span>
                                        <span class="text-danger ms-1"><i class="fas fa-arrow-down"></i> <?= (int)($d['downvotes'] ?? 0) ?></span>
                                    </td>
                                    <td data-label="Created" class="small"><?= date('M j, Y', strtotime($d['created_at'])) ?></td>
                                    <td data-label="Actions" class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= SITE_URL ?>/discussion_view.php?id=<?= (int)$d['id'] ?>" target="_blank">View</a>
                                        <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('Delete this discussion?'))bulkAction('delete','discussion',[<?= (int)$d['id'] ?>])">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($totalDiscussions > $perPage): ?>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <small class="text-muted">Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalDiscussions) ?> of <?= $totalDiscussions ?></small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= ceil($totalDiscussions / $perPage); $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="?tab=all&page=<?= $p ?>"><?= $p ?></a></li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif ($tab === 'reports'): ?>
        <!-- ==================== REPORTS TAB ==================== -->
        <?php if (empty($pendingReports)): ?>
            <div class="card border-0 shadow-sm rounded-4 text-center p-5">
                <div class="card-body">
                    <div class="bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 80px; height: 80px;">
                        <i class="fa-solid fa-shield-check fa-2x"></i>
                    </div>
                    <h3 class="fw-bold text-dark">All Clear!</h3>
                    <p class="text-secondary mb-0">No pending reports right now.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($pendingReports as $report): 
                    $isDiscussion = $report['target_type'] === 'discussion';
                    $contentSnippet = $isDiscussion ? ($report['disc_content'] ?? '[Deleted]') : ($report['comm_content'] ?? '[Deleted]');
                    $titleSnippet = $isDiscussion ? ($report['disc_title'] ?? 'Discussion') : 'Comment on thread #' . ($report['comm_disc_id'] ?? '?');
                    $linkToThread = SITE_URL . "/discussion_view.php?id=" . ($isDiscussion ? $report['target_id'] : $report['comm_disc_id']);
                    if (!$isDiscussion) $linkToThread .= "#comment-" . $report['target_id'];
                ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm rounded-4 h-100">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-start">
                                <span class="badge <?= $isDiscussion ? 'bg-primary' : 'bg-info' ?> rounded-pill text-uppercase px-3 py-2">
                                    <i class="fa-solid <?= $isDiscussion ? 'fa-message' : 'fa-reply' ?> me-1"></i>
                                    <?= ucfirst($report['target_type']) ?>
                                </span>
                                <span class="text-muted small"><i class="fa-regular fa-clock me-1"></i><?= date('M j, g:ia', strtotime($report['created_at'])) ?></span>
                            </div>
                            <div class="card-body">
                                <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($titleSnippet) ?></h5>
                                <div class="bg-light p-3 rounded-3 mb-3 border">
                                    <small class="text-muted d-block mb-1 fw-bold text-uppercase">Reported Content</small>
                                    <p class="mb-0 text-dark small" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">
                                        <?= htmlspecialchars($contentSnippet) ?>
                                    </p>
                                </div>
                                <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-3 mb-3">
                                    <small class="fw-bold d-block text-uppercase mb-1"><i class="fa-solid fa-flag me-1"></i> Reason</small>
                                    <p class="mb-0 small fw-medium">"<?= htmlspecialchars($report['reason']) ?>"</p>
                                    <small class="d-block mt-2 opacity-75">— <?= htmlspecialchars($report['reporter_name']) ?></small>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top-0 pb-4 pt-0 d-flex flex-column gap-2">
                                <a href="<?= $linkToThread ?>" target="_blank" class="btn btn-light w-100 text-dark fw-bold border">
                                    <i class="fa-solid fa-eye me-2"></i> View in Context
                                </a>
                                <div class="d-flex gap-2">
                                    <form method="POST" class="w-50" onsubmit="return confirm('Dismiss this report?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="dismiss">
                                        <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                        <input type="hidden" name="tab" value="reports">
                                        <button type="submit" class="btn btn-outline-secondary w-100 fw-bold">Dismiss</button>
                                    </form>
                                    <form method="POST" class="w-50" onsubmit="return confirm('Delete this content permanently?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                        <input type="hidden" name="tab" value="reports">
                                        <button type="submit" class="btn btn-danger w-100 fw-bold"><i class="fa-solid fa-trash me-2"></i>Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php endif; ?>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
var SITE_URL = <?= json_encode(SITE_URL) ?>;
var CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;

function toggleAll(master) {
    document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = master.checked; });
    updateCount();
}

function updateCount() {
    var checked = document.querySelectorAll('.row-check:checked');
    var btn = document.getElementById('bulkDeleteBtn');
    var cnt = document.getElementById('selectedCount');
    if (btn && cnt) {
        cnt.textContent = checked.length;
        btn.classList.toggle('d-none', checked.length === 0);
    }
}

function bulkAction(action, type, specificIds) {
    var ids = specificIds || [];
    if (!specificIds) {
        document.querySelectorAll('.row-check:checked').forEach(function(cb) { ids.push(+cb.value); });
    }
    if (ids.length === 0) { alert('No items selected.'); return; }
    if (!specificIds && !confirm('Are you sure you want to ' + action + ' ' + ids.length + ' item(s)?')) return;

    fetch(SITE_URL + '/admin/ajax/bulk_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: CSRF_TOKEN, action: action, type: type, ids: ids })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            alert(data.success + ' of ' + data.total + ' items processed.');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown'));
        }
    })
    .catch(function(e) { alert('Network error: ' + e.message); });
}
</script>
