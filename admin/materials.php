<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('materials')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}
$page_title = 'Manage Study Materials';
require_once __DIR__ . '/../models/Material.php';
$materialModel = new Material($pdo);
$status = isset($_GET['status']) && in_array($_GET['status'], ['pending','approved','rejected'], true) ? $_GET['status'] : 'pending';
$counts = $materialModel->moderationCounts();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$totalMaterials = (int)($counts[$status] ?? 0);
$totalPages = max(1, (int)ceil($totalMaterials / $perPage));
$materials = $materialModel->listByStatus($status, $perPage, $offset);

include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h4 mb-0">Study Materials Moderation</h1>
        <div>
          <a class="btn btn-outline-secondary btn-sm me-2" href="<?php echo SITE_URL; ?>/materials.php" target="_blank"><i class="fas fa-external-link-alt me-1"></i> View Site</a>
          <a class="btn btn-primary btn-sm" href="<?php echo SITE_URL; ?>/upload-material.php" target="_blank"><i class="fas fa-plus me-1"></i> Add Material</a>
        </div>
      </div>
      <?php echo display_flash_message(); ?>

      <ul class="nav nav-pills mb-3">
        <li class="nav-item me-2"><a class="nav-link <?php echo $status==='pending'?'active':''; ?>" href="?status=pending">Pending <span class="badge bg-light text-dark ms-1"><?php echo (int)($counts['pending'] ?? 0); ?></span></a></li>
        <li class="nav-item me-2"><a class="nav-link <?php echo $status==='approved'?'active':''; ?>" href="?status=approved">Approved <span class="badge bg-light text-dark ms-1"><?php echo (int)($counts['approved'] ?? 0); ?></span></a></li>
        <li class="nav-item me-2"><a class="nav-link <?php echo $status==='rejected'?'active':''; ?>" href="?status=rejected">Rejected <span class="badge bg-light text-dark ms-1"><?php echo (int)($counts['rejected'] ?? 0); ?></span></a></li>
      </ul>

      <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
          <strong><?php echo ucfirst($status); ?> Materials</strong>
          <div class="d-flex gap-2">
            <?php if ($status === 'pending'): ?>
              <button class="btn btn-sm btn-success d-none bulk-btn" onclick="bulkAction('approve','material')"><i class="fas fa-check me-1"></i> Approve <span class="sel-count"></span></button>
              <button class="btn btn-sm btn-outline-warning d-none bulk-btn" onclick="bulkAction('reject','material')"><i class="fas fa-times me-1"></i> Reject <span class="sel-count"></span></button>
            <?php elseif ($status === 'approved'): ?>
              <button class="btn btn-sm btn-outline-warning d-none bulk-btn" onclick="bulkAction('reject','material')"><i class="fas fa-times me-1"></i> Reject <span class="sel-count"></span></button>
            <?php else: ?>
              <button class="btn btn-sm btn-success d-none bulk-btn" onclick="bulkAction('approve','material')"><i class="fas fa-check me-1"></i> Approve <span class="sel-count"></span></button>
            <?php endif; ?>
            <button class="btn btn-sm btn-danger d-none bulk-btn" onclick="bulkAction('delete','material')"><i class="fas fa-trash me-1"></i> Delete <span class="sel-count"></span></button>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:30px;"><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
                  <th style="min-width:240px;">Title</th>
                  <th>Category</th>
                  <th>Level</th>
                  <th>Visibility</th>
                  <th>Uploaded</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($materials)): ?>
                  <tr><td colspan="7" class="text-center text-muted py-4">No materials found.</td></tr>
                <?php else: foreach ($materials as $m): ?>
                  <tr>
                    <td><input type="checkbox" class="row-check" value="<?php echo (int)$m['id']; ?>" onchange="updateCount()"></td>
                    <td data-label="Title">
                      <div class="fw-semibold"><?php echo htmlspecialchars($m['title']); ?></div>
                      <div class="small text-muted text-truncate" style="max-width:420px;">ID: <?php echo (int)$m['id']; ?> ¡¤ <?php echo htmlspecialchars(mb_strimwidth($m['description'] ?? '', 0, 90, '...')); ?></div>
                    </td>
                    <td data-label="Category" class="text-muted"><?php echo htmlspecialchars($m['category_name'] ?? ''); ?></td>
                    <td data-label="Level">
                      <?php if (($m['level'] ?? 'Secondary') === 'University'): ?>
                        <span class="badge bg-primary">Uni</span>
                        <?php echo !empty($m['is_university_general']) ? '<span class="badge bg-info text-dark">Gen</span>' : '<span class="badge bg-secondary">Spec</span>'; ?>
                      <?php else: ?>
                        <span class="badge bg-success">Sec</span>
                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($m['secondary_board'] ?? 'General'); ?></span>
                      <?php endif; ?>
                    </td>
                    <td data-label="Visibility"><span class="badge <?php echo ($m['visibility'] ?? 'public') === 'public' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo htmlspecialchars($m['visibility'] ?? 'public'); ?></span></td>
                    <td data-label="Uploaded"><?php echo htmlspecialchars(isset($m['created_at']) ? date('M j, Y', strtotime($m['created_at'])) : ''); ?></td>
                    <td data-label="Actions" class="text-end">
                      <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#previewModal" data-file="<?php echo SITE_URL . '/' . ltrim(($m['file_path'] ?? ''), '/'); ?>" data-mime="<?php echo htmlspecialchars($m['mime_type'] ?? ''); ?>">Preview</button>
                      <?php if ($status === 'pending'): ?>
                        <button class="btn btn-sm btn-success" onclick="bulkAction('approve','material',[<?= (int)$m['id'] ?>])">Approve</button>
                        <button class="btn btn-sm btn-outline-warning" onclick="bulkAction('reject','material',[<?= (int)$m['id'] ?>])">Reject</button>
                      <?php elseif ($status === 'approved'): ?>
                        <button class="btn btn-sm btn-outline-warning" onclick="bulkAction('reject','material',[<?= (int)$m['id'] ?>])">Reject</button>
                      <?php else: ?>
                        <button class="btn btn-sm btn-success" onclick="bulkAction('approve','material',[<?= (int)$m['id'] ?>])">Approve</button>
                      <?php endif; ?>
                      <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('Delete permanently?'))bulkAction('delete','material',[<?= (int)$m['id'] ?>])">Delete</button>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
          <small class="text-muted">Page <?= $page ?> of <?= $totalPages ?> (<?= $totalMaterials ?> total)</small>
          <nav><ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?status=<?= $status ?>&page=<?= $page - 1 ?>">Previous</a></li>
            <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
              <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="?status=<?= $status ?>&page=<?= $p ?>"><?= $p ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="?status=<?= $status ?>&page=<?= $page + 1 ?>">Next</a></li>
          </ul></nav>
        </div>
        <?php endif; ?>
      </div>

      <!-- Preview Modal -->
      <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Preview</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="previewBody" style="min-height:60vh;"><div class="text-muted">Loading...</div></div>
          </div>
        </div>
      </div>
      <script>
      (function(){
        var modal = document.getElementById('previewModal');
        if (!modal) return;
        modal.addEventListener('show.bs.modal', function (e) {
          var btn = e.relatedTarget; if (!btn) return;
          var f = btn.getAttribute('data-file') || '', m = (btn.getAttribute('data-mime')||'').toLowerCase(), b = document.getElementById('previewBody'), h = '';
          if (m.indexOf('pdf') !== -1) h = '<embed src="'+f+'" type="application/pdf" style="width:100%;height:70vh;" />';
          else if (m.indexOf('image/') === 0) h = '<img src="'+f+'" class="img-fluid" />';
          else if (/\.docx?$|\.pptx?$|\.xlsx?$/i.test(f)) h = '<iframe src="https://view.officeapps.live.com/op/embed.aspx?src='+encodeURIComponent(f)+'" style="width:100%;height:70vh;" frameborder="0"></iframe>';
          else h = '<div class="alert alert-info">No inline preview available.</div>';
          b.innerHTML = h;
        });
      })();
      </script>
    </main>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
var SITE_URL = <?= json_encode(SITE_URL) ?>, CSRF_TOKEN = <?= json_encode(csrf_token()) ?>, CURRENT_STATUS = <?= json_encode($status) ?>;
function toggleAll(m) { document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = m.checked; }); updateCount(); }
function updateCount() {
    var c = document.querySelectorAll('.row-check:checked').length;
    document.querySelectorAll('.sel-count').forEach(function(el) { el.textContent = '(' + c + ')'; });
    document.querySelectorAll('.bulk-btn').forEach(function(b) { b.classList.toggle('d-none', c === 0); });
}
function bulkAction(action, type, ids) {
    ids = ids || []; if (!ids.length) document.querySelectorAll('.row-check:checked').forEach(function(cb) { ids.push(+cb.value); });
    if (!ids.length) { alert('No items selected.'); return; }
    if (!arguments[2] && !confirm(action.charAt(0).toUpperCase() + action.slice(1) + ' ' + ids.length + ' item(s)?')) return;
    fetch(SITE_URL + '/admin/ajax/bulk_action.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: CSRF_TOKEN, action: action, type: type, ids: ids }) })
    .then(function(r) { return r.json(); }).then(function(d) { if (d.ok) window.location.href = SITE_URL + '/admin/materials.php?status=' + CURRENT_STATUS; else alert(d.error || 'Error'); })
    .catch(function(e) { alert('Network error: ' + e.message); });
}
</script>
