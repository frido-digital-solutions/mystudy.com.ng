<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('posts')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}
$page_title = 'Manage Posts';
require_once __DIR__ . '/../models/Post.php';
$postModel = new Post($pdo);
$postAdmin = new PostAdmin($pdo);
$universities = $postAdmin->listUniversities();
$level = isset($_GET['level']) ? ($_GET['level'] === 'University' ? 'University' : ($_GET['level'] === 'Secondary' ? 'Secondary' : null)) : null;
$universityId = isset($_GET['university_id']) ? (int)$_GET['university_id'] : 0;

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Count total with same filter
$countWhere = [];
$countParams = [];
if ($level) { $countWhere[] = 'level = ?'; $countParams[] = $level; }
if ($level === 'University' && $universityId > 0) { $countWhere[] = 'university_id = ?'; $countParams[] = $universityId; }
$countSql = 'SELECT COUNT(*) FROM posts' . (empty($countWhere) ? '' : ' WHERE ' . implode(' AND ', $countWhere));
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalPosts = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalPosts / $perPage));

$posts = $postModel->listFiltered($level, $universityId ?: null, $perPage, $offset);
include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h4 mb-0">Posts</h1>
        <a class="btn btn-primary btn-sm" href="<?php echo SITE_URL; ?>/admin/post_edit.php"><i class="fas fa-plus me-1"></i> New Post</a>
      </div>
      <?php echo display_flash_message(); ?>
      <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-md-3">
          <label class="form-label">Level</label>
          <select class="form-select" name="level" id="levelFilter">
            <option value="">All</option>
            <option value="Secondary" <?php echo ($level==='Secondary')?'selected':''; ?>>Secondary</option>
            <option value="University" <?php echo ($level==='University')?'selected':''; ?>>University</option>
          </select>
        </div>
        <div class="col-md-5" id="uniFilterWrap" style="display: <?php echo ($level==='University')?'block':'none'; ?>;">
          <label class="form-label">University</label>
          <select class="form-select" name="university_id" id="universityFilter">
            <option value="">All (including General)</option>
            <?php foreach ($universities as $u): ?>
              <option value="<?php echo (int)$u['id']; ?>" <?php echo ($universityId===(int)$u['id'])?'selected':''; ?>><?php echo htmlspecialchars($u['name'] . ' — ' . $u['acronym']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-secondary w-100">Filter</button>
        </div>
      </form>
      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <strong>Posts <span class="badge bg-secondary ms-1"><?= $totalPosts ?></span></strong>
          <button class="btn btn-sm btn-danger d-none" id="bulkDeleteBtn" onclick="bulkAction('delete','post')">
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
                  <th>Tags</th>
                  <th>Slug</th>
                  <th>Created</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($posts)): ?>
                  <tr><td colspan="6" class="text-center text-muted py-4">No posts found.</td></tr>
                <?php else: foreach ($posts as $p): ?>
                  <tr>
                    <td><input type="checkbox" class="row-check" value="<?php echo (int)$p['id']; ?>" onchange="updateCount()"></td>
                    <td data-label="Title"><?php echo htmlspecialchars($p['title']); ?></td>
                    <td data-label="Tags">
                      <?php if (($p['level'] ?? 'Secondary') === 'University'): ?>
                        <span class="badge bg-primary">University</span>
                        <?php if (!empty($p['is_university_general'])): ?>
                          <span class="badge bg-info text-dark">General</span>
                        <?php else:
                          $acronym = '';
                          foreach ($universities as $u) { if ((int)$u['id'] === (int)($p['university_id'] ?? 0)) { $acronym = $u['acronym']; break; } }
                        ?>
                          <?php if ($acronym): ?><span class="badge bg-secondary"><?php echo htmlspecialchars($acronym); ?></span><?php endif; ?>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="badge bg-success">Secondary</span>
                      <?php endif; ?>
                    </td>
                    <td data-label="Slug" class="text-muted"><?php echo htmlspecialchars($p['slug']); ?></td>
                    <td data-label="Created"><?php echo htmlspecialchars(date('M j, Y', strtotime($p['created_at']))); ?></td>
                    <td data-label="Actions" class="text-end">
                      <a class="btn btn-sm btn-outline-secondary" href="<?php echo SITE_URL; ?>/post.php?slug=<?php echo urlencode($p['slug']); ?>" target="_blank">View</a>
                      <a class="btn btn-sm btn-outline-primary" href="<?php echo SITE_URL; ?>/admin/post_edit.php?id=<?php echo (int)$p['id']; ?>">Edit</a>
                      <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('Delete this post?'))bulkAction('delete','post',[<?= (int)$p['id'] ?>])">Delete</button>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php if ($totalPages > 1):
          // Build base URL preserving filter params
          $paginationParams = array_filter(['level' => $level, 'university_id' => $universityId ?: null]);
        ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
          <small class="text-muted">Page <?= $page ?> of <?= $totalPages ?></small>
          <nav><ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($paginationParams, ['page' => $page - 1])) ?>">Previous</a></li>
            <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
              <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($paginationParams, ['page' => $p])) ?>"><?= $p ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($paginationParams, ['page' => $page + 1])) ?>">Next</a></li>
          </ul></nav>
        </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
var SITE_URL = <?= json_encode(SITE_URL) ?>, CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
document.getElementById('levelFilter').addEventListener('change', function() {
  document.getElementById('uniFilterWrap').style.display = this.value === 'University' ? 'block' : 'none';
});
function toggleAll(m) { document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = m.checked; }); updateCount(); }
function updateCount() {
    var c = document.querySelectorAll('.row-check:checked').length;
    var btn = document.getElementById('bulkDeleteBtn'), cnt = document.getElementById('selectedCount');
    if (btn && cnt) { cnt.textContent = c; btn.classList.toggle('d-none', c === 0); }
}
function bulkAction(action, type, ids) {
    ids = ids || []; if (!ids.length) document.querySelectorAll('.row-check:checked').forEach(function(cb) { ids.push(+cb.value); });
    if (!ids.length) { alert('No items selected.'); return; }
    if (!arguments[2] && !confirm('Delete ' + ids.length + ' post(s)?')) return;
    fetch(SITE_URL + '/admin/ajax/bulk_action.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: CSRF_TOKEN, action: action, type: type, ids: ids }) })
    .then(function(r) { return r.json(); }).then(function(d) { if (d.ok) location.reload(); else alert(d.error || 'Error'); })
    .catch(function(e) { alert('Network error: ' + e.message); });
}
</script>
