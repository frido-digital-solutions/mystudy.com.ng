<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('quizzes')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}
$page_title = 'Manage Quizzes';
require_once __DIR__ . '/../models/Quiz.php';
$quizAdmin = new QuizAdmin($pdo);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$totalQuizzes = (int)$pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$totalPages = max(1, (int)ceil($totalQuizzes / $perPage));
$quizzes = $quizAdmin->listAll($perPage, $offset, null);

include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h4 mb-0">Quizzes</h1>
        <a class="btn btn-primary btn-sm" href="<?php echo SITE_URL; ?>/admin/quiz_edit.php"><i class="fas fa-plus me-1"></i> New Quiz</a>
      </div>
      <?php echo display_flash_message(); ?>
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <strong>All Quizzes <span class="badge bg-secondary ms-1"><?= $totalQuizzes ?></span></strong>
          <button class="btn btn-sm btn-danger d-none" id="bulkDeleteBtn" onclick="bulkAction('delete','quiz')">
            <i class="fas fa-trash me-1"></i> Delete Selected (<span id="selectedCount">0</span>)
          </button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:30px;"><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
                  <th>Title</th>
                  <th>Subject</th>
                  <th>Level</th>
                  <th>Questions</th>
                  <th>Created</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($quizzes)): ?>
                  <tr><td colspan="7" class="text-center text-muted py-4">No quizzes found.</td></tr>
                <?php else: foreach ($quizzes as $q): $qid=(int)$q['id']; ?>
                  <tr>
                    <td><input type="checkbox" class="row-check" value="<?php echo $qid; ?>" onchange="updateCount()"></td>
                    <td data-label="Title"><?php echo htmlspecialchars($q['title']); ?></td>
                    <td data-label="Subject" class="text-muted"><?php echo htmlspecialchars($q['subject'] ?? ''); ?></td>
                    <td data-label="Level"><span class="badge bg-info text-dark"><?php echo htmlspecialchars($q['level'] ?? ''); ?></span></td>
                    <td data-label="Questions"><?php echo (int)$quizAdmin->getQuestionCount($qid); ?></td>
                    <td data-label="Created"><?php echo htmlspecialchars(date('M j, Y', strtotime($q['created_at']))); ?></td>
                    <td data-label="Actions" class="text-end">
                      <a class="btn btn-sm btn-outline-secondary" href="<?php echo SITE_URL; ?>/quiz.php?id=<?php echo $qid; ?>" target="_blank">View</a>
                      <a class="btn btn-sm btn-outline-primary" href="<?php echo SITE_URL; ?>/admin/quiz_edit.php?id=<?php echo $qid; ?>">Edit</a>
                      <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('Delete this quiz?'))bulkAction('delete','quiz',[<?= $qid ?>])">Delete</button>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
          <small class="text-muted">Page <?= $page ?> of <?= $totalPages ?> (<?= $totalQuizzes ?> total)</small>
          <nav><ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a></li>
            <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
              <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $page + 1 ?>">Next</a></li>
          </ul></nav>
        </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
var SITE_URL = <?= json_encode(SITE_URL) ?>;
var CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
function toggleAll(master) { document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = master.checked; }); updateCount(); }
function updateCount() {
    var c = document.querySelectorAll('.row-check:checked').length;
    var btn = document.getElementById('bulkDeleteBtn'), cnt = document.getElementById('selectedCount');
    if (btn && cnt) { cnt.textContent = c; btn.classList.toggle('d-none', c === 0); }
}
function bulkAction(action, type, ids) {
    ids = ids || []; if (!ids.length) document.querySelectorAll('.row-check:checked').forEach(function(cb) { ids.push(+cb.value); });
    if (!ids.length) { alert('No items selected.'); return; }
    if (!arguments[2] && !confirm('Delete ' + ids.length + ' quiz(es)?')) return;
    fetch(SITE_URL + '/admin/ajax/bulk_action.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: CSRF_TOKEN, action: action, type: type, ids: ids }) })
    .then(function(r) { return r.json(); }).then(function(d) { if (d.ok) location.reload(); else alert(d.error || 'Error'); })
    .catch(function(e) { alert('Network error: ' + e.message); });
}
</script>
