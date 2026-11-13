<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('materials')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}
$page_title = 'Manage Study Materials';
require_once __DIR__ . '/../models/Material.php';
require_once __DIR__ . '/../models/MaterialPayment.php';
require_once __DIR__ . '/../includes/materials_paid.php';
$materialModel = new Material($pdo);

// Price changes go through here so the file can move into the locked store in the
// same breath — a paid document must never stay reachable at its old public URL.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['admin_action'] ?? '') === 'set_price') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Your session expired. Please try again.');
        redirect(SITE_URL . '/admin/materials.php?status=' . ($_POST['return_status'] ?? 'approved'));
    }
    $mid = (int)($_POST['material_id'] ?? 0);
    $raw = trim((string)($_POST['price'] ?? ''));
    $row = $mid > 0 ? $materialModel->getById($mid) : null;

    if (!$row) {
        set_flash_message('error', 'Material not found.');
    } else {
        $wantPaid = $raw !== '';
        $newPrice = 0.0;
        if ($wantPaid) {
            $newPrice = (float)str_replace([',', ' '], '', $raw);
            if ($newPrice < 50 || $newPrice > 5000000) {
                set_flash_message('error', 'Enter a price between ₦50 and ₦5,000,000, or leave it empty to make the material free.');
                $wantPaid = false;
                $newPrice = 0.0;
            }
        }

        if ($wantPaid && !material_is_paid($row)) {
            // Moving into the guarded folder before the flag flips, so there is never
            // a moment where a paid file sits at a public path marked as sold.
            $from = material_resolve_file($row);
            $to = null;
            if ($from !== null) {
                $dir = material_secure_base();
                $target = $dir . '/' . bin2hex(random_bytes(16)) . '.' . strtolower(pathinfo($from, PATHINFO_EXTENSION));
                if (@rename($from, $target)) {
                    $to = MATERIAL_SECURE_DIR . '/' . basename($target);
                }
            }
            if ($to === null) {
                set_flash_message('error', 'The file could not be moved into the locked store, so the price was not changed.');
                $wantPaid = false;
                $newPrice = 0.0;
            } else {
                $materialModel->updateFilePath($mid, $to);
            }
        }

        if ($wantPaid || (!$wantPaid && material_is_paid($row))) {
            $materialModel->setPricing($mid, $wantPaid, $wantPaid ? $newPrice : null);
            log_admin_activity($pdo, 'Material pricing', ($wantPaid ? 'Set ₦' . number_format($newPrice, 0) . ' for #' : 'Made free #') . $mid);
            set_flash_message('success', $wantPaid
                ? 'Locked behind Paystack at ₦' . number_format($newPrice, 0) . '. Buyers keep it for life.'
                : 'This material is free again. Anyone signed in can download it.');
        }
    }
    redirect(SITE_URL . '/admin/materials.php?status=' . rawurlencode((string)($_POST['return_status'] ?? 'approved')) . '&page=' . max(1, (int)($_POST['return_page'] ?? 1)));
}

$paidStats = (new MaterialPayment($pdo))->totals();
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
      <style>
        .mat-price-input{width:96px;flex:0 0 auto}
        .mat-naira-sm{color:var(--text-muted,#64748b);font-weight:700}
        .mat-price-form{margin:0}
        .mat-sales-strip{display:flex;flex-wrap:wrap;gap:.5rem 1.25rem;font-size:.85rem;color:#475569}
        .mat-sales-strip strong{color:#0f172a}
      </style>
      <div class="alert alert-light border py-2 mat-sales-strip mb-3">
        <span>Paid materials: <strong><?php echo number_format($paidStats['sales']); ?></strong> sale<?php echo $paidStats['sales'] === 1 ? '' : 's'; ?></span>
        <span>Revenue: <strong>&#8358;<?php echo number_format($paidStats['revenue'], 0); ?></strong></span>
        <span>Unfinished checkouts: <strong><?php echo number_format($paidStats['open']); ?></strong></span>
        <span class="text-muted">Set a price to lock a file behind Paystack; clear it to make the material free again.</span>
      </div>

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
                  <th>Price</th>
                  <th>Uploaded</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($materials)): ?>
                  <tr><td colspan="8" class="text-center text-muted py-4">No materials found.</td></tr>
                <?php else: foreach ($materials as $m): ?>
                  <tr>
                    <td><input type="checkbox" class="row-check" value="<?php echo (int)$m['id']; ?>" onchange="updateCount()"></td>
                    <td data-label="Title">
                      <div class="fw-semibold"><?php echo htmlspecialchars($m['title']); ?></div>
                      <div class="small text-muted text-truncate" style="max-width:420px;">ID: <?php echo (int)$m['id']; ?> �� <?php echo htmlspecialchars(mb_strimwidth($m['description'] ?? '', 0, 90, '...')); ?></div>
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
                    <td data-label="Price">
                      <form method="post" class="mat-price-form">
                        <input type="hidden" name="admin_action" value="set_price">
                        <input type="hidden" name="material_id" value="<?php echo (int)$m['id']; ?>">
                        <input type="hidden" name="return_status" value="<?php echo htmlspecialchars($status); ?>">
                        <input type="hidden" name="return_page" value="<?php echo (int)$page; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <div class="d-flex align-items-center gap-1">
                          <span class="mat-naira-sm">&#8358;</span>
                          <input type="number" name="price" class="form-control form-control-sm mat-price-input" min="50" max="5000000" step="50"
                                 placeholder="free" value="<?php echo material_is_paid($m) ? htmlspecialchars((string)(float)$m['price']) : ''; ?>">
                          <button type="submit" class="btn btn-sm btn-outline-primary" title="Save price (empty = free)"><i class="fas fa-check"></i></button>
                        </div>
                        <div class="small text-muted mt-1">
                          <?php if (material_is_paid($m)): ?>
                            <i class="fas fa-lock me-1"></i>Paystack only
                          <?php else: ?>
                            Free
                          <?php endif; ?>
                        </div>
                      </form>
                    </td>
                    <td data-label="Uploaded"><?php echo htmlspecialchars(isset($m['created_at']) ? date('M j, Y', strtotime($m['created_at'])) : ''); ?></td>
                    <td data-label="Actions" class="text-end">
                      <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#previewModal" data-file="<?php echo htmlspecialchars(SITE_URL . '/material_download.php?id=' . (int)$m['id'] . '&inline=1'); ?>" data-mime="<?php echo htmlspecialchars($m['mime_type'] ?? ''); ?>">Preview</button>
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
