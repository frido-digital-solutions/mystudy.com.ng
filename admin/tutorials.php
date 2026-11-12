<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('tutorials')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}
$page_title = 'Manage Tutorials';
require_once __DIR__ . '/../models/Tutorial.php';

$tutorialModel = new Tutorial($pdo);
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$postedItem = null;

// Handle create/update (single mode)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Keep the submitted values so a token or save failure does not wipe the form.
    $postedItem = [
        'id' => (int)($_POST['id'] ?? 0),
        'title' => trim($_POST['title'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'youtube_url' => trim($_POST['youtube_url'] ?? ''),
        'level' => (($_POST['level'] ?? 'University') === 'Secondary') ? 'Secondary' : 'University',
        'courses' => array_map(fn($c) => ['name' => $c], array_values(array_filter(array_map('trim', explode(',', trim($_POST['courses'] ?? '')))))),
    ];
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Your form session expired. Nothing was saved. Please review and submit again.';
    } else {
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'youtube_url' => trim($_POST['youtube_url'] ?? ''),
            'level' => (($_POST['level'] ?? 'University') === 'Secondary') ? 'Secondary' : 'University',
        ];
        $coursesRaw = trim($_POST['courses'] ?? '');
        $courses = array_values(array_filter(array_map('trim', explode(',', $coursesRaw))));
        try {
            if (!empty($_POST['id'])) {
                $tid = (int)$_POST['id'];
                $tutorialModel->update($tid, $data, $courses);
                set_flash_message('success', 'Tutorial updated successfully.');
                header('Location: ' . SITE_URL . '/admin/tutorials.php');
                exit;
            } else {
                $tid = $tutorialModel->create($data, $courses);
                set_flash_message('success', 'Tutorial created successfully.');
                header('Location: ' . SITE_URL . '/admin/tutorials.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = 'Save failed: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Handle delete
if ($action === 'delete' && $id > 0) {
    if (!isset($_GET['csrf_token']) || !validate_csrf_token($_GET['csrf_token'])) {
        set_flash_message('error', 'Invalid token.');
    } else {
        $tutorialModel->delete($id);
        set_flash_message('success', 'Tutorial deleted.');
    }
    header('Location: ' . SITE_URL . '/admin/tutorials.php');
    exit;
}

// Load data for view
// Pagination for listing
$tutPage = max(1, (int)($_GET['page'] ?? 1));
$tutPerPage = 20;
$tutOffset = ($tutPage - 1) * $tutPerPage;
$totalTutorials = (int)$pdo->query("SELECT COUNT(*) FROM tutorials")->fetchColumn();
$totalTutPages = max(1, (int)ceil($totalTutorials / $tutPerPage));
$tutorials = $tutorialModel->list($tutPerPage, $tutOffset);
$editItem = ($action === 'edit' && $id > 0) ? $tutorialModel->getById($id) : null;

include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      
      <!-- Page Header -->
      <div class="page-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <h1><i class="fab fa-youtube me-2" style="color: var(--admin-primary);"></i> Tutorials</h1>
            <p class="text-muted mb-0">Manage video tutorials and learning content</p>
          </div>
          <div class="d-flex gap-2">
            <a class="btn btn-primary" href="<?php echo SITE_URL; ?>/admin/tutorials.php?action=add">
              <i class="fas fa-plus me-1"></i> Single Add
            </a>
            <a class="btn btn-success" href="<?php echo SITE_URL; ?>/admin/tutorials.php?action=bulk">
              <i class="fas fa-layer-group me-1"></i> Bulk Add
            </a>
          </div>
        </div>
      </div>

      <?php echo display_flash_message(); ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
  <?php endif; ?>

  <?php // ==================== SINGLE ADD/EDIT MODE ==================== ?>
  <?php if ($action === 'add' || $action === 'edit'): ?>
    <?php 
      $t = $postedItem ?? ($editItem ?: [
        'id' => '', 'title' => '', 'slug' => '', 'description' => '',
        'youtube_url' => '', 'level' => 'University', 'courses' => []
      ]);
      $coursesStr = '';
      if (!empty($t['courses'])) { $coursesStr = implode(', ', array_map(fn($c)=>$c['name'], $t['courses'])); }
    ?>
    <div class="card mb-4">
      <div class="card-header bg-white"><strong><?php echo $action==='edit'?'Edit':'Add'; ?> Tutorial</strong></div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)$t['id']); ?>">
          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label">YouTube URL</label>
              <div class="input-group">
                <input type="url" class="form-control" name="youtube_url" id="ytUrlInput" placeholder="https://www.youtube.com/watch?v=..." value="<?php echo htmlspecialchars($t['youtube_url']); ?>" required>
                <button class="btn btn-outline-secondary" type="button" id="getInfoBtn"><i class="fas fa-magic me-1"></i> Get Info</button>
              </div>
              <div class="form-text">Paste a YouTube link and click Get Info to auto-fill title, description, and thumbnail.</div>
              <div class="mt-2 d-flex align-items-center gap-3" id="thumbPreviewWrap" style="display: none;">
                <img id="thumbPreview" src="#" alt="thumbnail" style="width: 120px; height: 68px; object-fit: cover; border-radius: 4px; border:1px solid #e5e7eb;">
                <div class="small text-muted" id="videoMeta"></div>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Title</label>
              <input type="text" class="form-control" name="title" id="singleTitle" value="<?php echo htmlspecialchars($t['title']); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Slug (optional)</label>
              <input type="text" class="form-control" name="slug" value="<?php echo htmlspecialchars($t['slug']); ?>">
            </div>
            <div class="col-md-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" id="singleDesc" rows="4"><?php echo htmlspecialchars($t['description']); ?></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label">Level</label>
              <select name="level" class="form-select">
                <option value="University" <?php echo ($t['level']==='University')?'selected':''; ?>>University</option>
                <option value="Secondary" <?php echo ($t['level']==='Secondary')?'selected':''; ?>>Secondary</option>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label">Courses (comma-separated)</label>
              <input type="text" class="form-control" name="courses" value="<?php echo htmlspecialchars($coursesStr); ?>" placeholder="e.g., Calculus, Linear Algebra, Physics">
            </div>
          </div>
          <div class="mt-3">
            <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i> Save Tutorial</button>
            <a class="btn btn-outline-secondary" href="<?php echo SITE_URL; ?>/admin/tutorials.php">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php // ==================== BULK ADD MODE ==================== ?>
  <?php if ($action === 'bulk'): ?>
    <div class="card mb-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong><i class="fas fa-layer-group me-1"></i> Bulk Add Tutorials</strong>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo SITE_URL; ?>/admin/tutorials.php"><i class="fas fa-arrow-left me-1"></i> Back</a>
      </div>
      <div class="card-body">
        <!-- Step 1: Paste URLs -->
        <div id="bulkStep1">
          <label class="form-label fw-bold">Paste YouTube URLs (one per line)</label>
          <textarea class="form-control font-monospace" id="bulkUrlsInput" rows="8" placeholder="https://www.youtube.com/watch?v=abc123
https://youtu.be/def456
https://www.youtube.com/watch?v=ghi789"></textarea>
          <div class="form-text">Paste up to 20 YouTube URLs, one per line. Click "Fetch All Info" to load video details for each.</div>
          <div class="mt-3 d-flex gap-2 align-items-center">
            <button class="btn btn-primary" id="bulkFetchBtn" type="button"><i class="fas fa-download me-1"></i> Fetch All Info</button>
            <div id="bulkFetchProgress" class="d-none">
              <span class="spinner-border spinner-border-sm me-1"></span>
              <span id="bulkFetchStatus">Fetching 0 / 0...</span>
            </div>
          </div>
        </div>

        <!-- Step 2: Review & Edit -->
        <div id="bulkStep2" class="d-none mt-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><i class="fas fa-list me-1"></i> Fetched Videos</h6>
            <div class="d-flex gap-2 align-items-center">
              <div class="d-flex gap-2">
                <select class="form-select form-select-sm" id="bulkLevelAll" style="width: auto;">
                  <option value="University">All: University</option>
                  <option value="Secondary">All: Secondary</option>
                </select>
                <input type="text" class="form-control form-control-sm" id="bulkCoursesAll" placeholder="Courses for all (comma-separated)" style="width: 250px;">
              </div>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered align-middle" id="bulkPreviewTable">
              <thead class="table-light">
                <tr>
                  <th style="width: 30px;"><input type="checkbox" id="bulkSelectAll" checked></th>
                  <th style="width: 80px;">Thumb</th>
                  <th>Title</th>
                  <th style="width: 200px;">Description</th>
                  <th style="width: 110px;">Level</th>
                  <th style="width: 150px;">Courses</th>
                  <th style="width: 70px;">Status</th>
                </tr>
              </thead>
              <tbody id="bulkPreviewBody"></tbody>
            </table>
          </div>

          <div class="mt-3 d-flex gap-2 align-items-center">
            <button class="btn btn-success btn-lg" id="bulkSaveBtn" type="button"><i class="fas fa-save me-1"></i> Save All Selected</button>
            <button class="btn btn-outline-secondary" id="bulkBackBtn" type="button"><i class="fas fa-arrow-left me-1"></i> Back to URLs</button>
            <div id="bulkSaveProgress" class="d-none">
              <span class="spinner-border spinner-border-sm me-1"></span>
              <span id="bulkSaveStatus">Saving...</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php // ==================== TUTORIALS LIST ==================== ?>
  <div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <strong>All Tutorials</strong>
      <div class="d-flex gap-2 align-items-center">
        <button class="btn btn-sm btn-danger d-none" id="tutBulkDeleteBtn" onclick="tutBulkAction('delete','tutorial')"><i class="fas fa-trash me-1"></i> Delete Selected (<span id="tutSelectedCount">0</span>)</button>
        <span class="badge bg-secondary"><?php echo count($tutorials); ?></span>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:30px;"><input type="checkbox" id="tutSelectAll" onchange="tutToggleAll(this)"></th>
              <th>Title</th>
              <th>Level</th>
              <th>Courses</th>
              <th>Created</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($tutorials)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No tutorials found.</td></tr>
            <?php else: foreach ($tutorials as $row): $tid=(int)$row['id']; $crs=$tutorialModel->getCoursesForTutorial($tid); ?>
              <tr>
                <td><input type="checkbox" class="tut-row-check" value="<?php echo $tid; ?>" onchange="tutUpdateCount()"></td>
                <td data-label="Title">
                  <div class="d-flex align-items-center gap-2">
                    <img src="<?php echo htmlspecialchars($row['thumbnail_url'] ?: ('https://img.youtube.com/vi/' . $row['youtube_id'] . '/default.jpg')); ?>" alt="thumb" style="width:56px;height:40px;object-fit:cover;border-radius:4px;">
                    <div>
                      <div class="fw-semibold"><?php echo htmlspecialchars($row['title']); ?></div>
                      <div class="text-muted small">YouTube ID: <?php echo htmlspecialchars($row['youtube_id']); ?></div>
                    </div>
                  </div>
                </td>
                <td data-label="Level"><span class="badge bg-info text-dark"><?php echo htmlspecialchars($row['level']); ?></span></td>
                <td data-label="Courses" class="text-muted">
                  <?php echo htmlspecialchars(implode(', ', array_map(fn($c)=>$c['name'], $crs))); ?>
                </td>
                <td data-label="Created"><?php echo htmlspecialchars(date('M j, Y', strtotime($row['created_at']))); ?></td>
                <td data-label="Actions" class="text-end">
                  <a class="btn btn-sm btn-outline-secondary" href="https://www.youtube.com/watch?v=<?php echo urlencode($row['youtube_id']); ?>" target="_blank">Open</a>
                  <a class="btn btn-sm btn-outline-primary" href="<?php echo SITE_URL; ?>/admin/tutorials.php?action=edit&id=<?php echo $tid; ?>">Edit</a>
                  <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('Delete this tutorial?'))tutBulkAction('delete','tutorial',[<?= $tid ?>])">Delete</button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if ($totalTutPages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
      <small class="text-muted">Page <?= $tutPage ?> of <?= $totalTutPages ?> (<?= $totalTutorials ?> total)</small>
      <nav><ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $tutPage <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $tutPage - 1 ?>">Previous</a></li>
        <?php for ($tp = max(1, $tutPage - 3); $tp <= min($totalTutPages, $tutPage + 3); $tp++): ?>
          <li class="page-item <?= $tp === $tutPage ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $tp ?>"><?= $tp ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $tutPage >= $totalTutPages ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $tutPage + 1 ?>">Next</a></li>
      </ul></nav>
    </div>
    <?php endif; ?>
  </div>
</main>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
(function(){
  var SITE_URL = <?php echo json_encode(SITE_URL); ?>;
  var CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;

  // ============================================================
  // SINGLE MODE: Get Info Button
  // ============================================================
  var btn = document.getElementById('getInfoBtn');
  if (btn) {
    var urlInput     = document.getElementById('ytUrlInput');
    var titleInput   = document.getElementById('singleTitle');
    var descInput    = document.getElementById('singleDesc');
    var thumbWrap    = document.getElementById('thumbPreviewWrap');
    var thumbImg     = document.getElementById('thumbPreview');
    var meta         = document.getElementById('videoMeta');

    function setPreview(th, text) {
      if (!th) { thumbWrap.style.display = 'none'; return; }
      thumbImg.src = th;
      thumbWrap.style.display = 'flex';
      meta.textContent = text || '';
    }

    btn.addEventListener('click', function(){
      var url = (urlInput.value || '').trim();
      if (!url) { alert('Please paste a YouTube link first.'); return; }
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Fetching...';

      fetch(SITE_URL + '/admin/ajax/youtube_info.php?url=' + encodeURIComponent(url))
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (!data.ok) { alert(data.error || 'Failed to fetch video info'); return; }
          if (data.title && titleInput) titleInput.value = data.title;
          if (descInput && data.description) descInput.value = data.description;
          if (data.thumbnail_url) setPreview(data.thumbnail_url, data.duration_text ? ('Duration: ' + data.duration_text) : '');
        })
        .catch(function(e) { alert('Network error while fetching video info: ' + e.message); })
        .finally(function() {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-magic me-1"></i> Get Info';
        });
    });
  }

  // ============================================================
  // BULK MODE
  // ============================================================
  var bulkFetchBtn  = document.getElementById('bulkFetchBtn');
  if (!bulkFetchBtn) return; // Not on bulk page

  var bulkUrlsInput   = document.getElementById('bulkUrlsInput');
  var bulkStep1       = document.getElementById('bulkStep1');
  var bulkStep2       = document.getElementById('bulkStep2');
  var bulkFetchProg   = document.getElementById('bulkFetchProgress');
  var bulkFetchStatus = document.getElementById('bulkFetchStatus');
  var bulkPreviewBody = document.getElementById('bulkPreviewBody');
  var bulkSaveBtn     = document.getElementById('bulkSaveBtn');
  var bulkSaveProg    = document.getElementById('bulkSaveProgress');
  var bulkSaveStatus  = document.getElementById('bulkSaveStatus');
  var bulkBackBtn     = document.getElementById('bulkBackBtn');
  var bulkSelectAll   = document.getElementById('bulkSelectAll');
  var bulkLevelAll    = document.getElementById('bulkLevelAll');
  var bulkCoursesAll  = document.getElementById('bulkCoursesAll');

  var bulkVideoData = []; // Stores fetched video info

  // Back button
  bulkBackBtn.addEventListener('click', function(){
    bulkStep2.classList.add('d-none');
    bulkStep1.classList.remove('d-none');
  });

  // Select all checkbox
  bulkSelectAll.addEventListener('change', function(){
    var cbs = document.querySelectorAll('.bulk-row-check');
    cbs.forEach(function(cb) { cb.checked = bulkSelectAll.checked; });
  });

  // Apply level to all
  bulkLevelAll.addEventListener('change', function(){
    var selects = document.querySelectorAll('.bulk-level-select');
    selects.forEach(function(s) { s.value = bulkLevelAll.value; });
    bulkVideoData.forEach(function(v) { v.level = bulkLevelAll.value; });
  });

  // Apply courses to all
  bulkCoursesAll.addEventListener('input', function(){
    var inputs = document.querySelectorAll('.bulk-courses-input');
    inputs.forEach(function(inp) { inp.value = bulkCoursesAll.value; });
    bulkVideoData.forEach(function(v) { v.courses = bulkCoursesAll.value; });
  });

  // ---- FETCH ALL ----
  bulkFetchBtn.addEventListener('click', function(){
    var rawText = bulkUrlsInput.value.trim();
    if (!rawText) { alert('Please paste at least one YouTube URL.'); return; }

    var lines = rawText.split('\n').map(function(l) { return l.trim(); }).filter(function(l) { return l !== ''; });
    if (lines.length === 0) { alert('No valid URLs found.'); return; }
    if (lines.length > 20) {
      alert('Maximum 20 URLs at a time. Only the first 20 will be processed.');
      lines = lines.slice(0, 20);
    }

    bulkFetchBtn.disabled = true;
    bulkFetchProg.classList.remove('d-none');
    bulkFetchStatus.textContent = 'Fetching ' + lines.length + ' URLs...';

    fetch(SITE_URL + '/admin/ajax/youtube_info.php?urls=' + encodeURIComponent(JSON.stringify(lines)))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.ok) { alert(data.error || 'Failed to fetch'); return; }

        bulkVideoData = [];
        bulkPreviewBody.innerHTML = '';

        var results = data.results || [];
        results.forEach(function(v, idx) {
          var ok = v.ok && v.youtube_id;
          bulkVideoData.push({
            url:           v.url || lines[idx] || '',
            youtube_id:    v.youtube_id || '',
            title:         v.title || '',
            description:   v.description || '',
            thumbnail_url: v.thumbnail_url || '',
            level:         'University',
            courses:       '',
            ok:            !!ok,
            error:         v.error || '',
          });

          var tr = document.createElement('tr');
          if (!ok) tr.classList.add('table-danger');
          tr.innerHTML =
            '<td><input type="checkbox" class="bulk-row-check" data-idx="' + idx + '" ' + (ok ? 'checked' : 'disabled') + '></td>' +
            '<td>' + (v.thumbnail_url ? '<img src="' + escHtml(v.thumbnail_url) + '" style="width:70px;height:40px;object-fit:cover;border-radius:4px;">' : '<span class="text-muted">—</span>') + '</td>' +
            '<td><input type="text" class="form-control form-control-sm bulk-title-input" data-idx="' + idx + '" value="' + escAttr(v.title || '') + '" ' + (!ok ? 'disabled' : '') + '></td>' +
            '<td><textarea class="form-control form-control-sm bulk-desc-input" data-idx="' + idx + '" rows="2" ' + (!ok ? 'disabled' : '') + '>' + escHtml(v.description || '') + '</textarea></td>' +
            '<td><select class="form-select form-select-sm bulk-level-select" data-idx="' + idx + '" ' + (!ok ? 'disabled' : '') + '><option value="University">University</option><option value="Secondary">Secondary</option></select></td>' +
            '<td><input type="text" class="form-control form-control-sm bulk-courses-input" data-idx="' + idx + '" placeholder="Courses..." ' + (!ok ? 'disabled' : '') + '></td>' +
            '<td>' + (ok ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger" title="' + escAttr(v.error || 'Failed') + '">Fail</span>') + '</td>';
          bulkPreviewBody.appendChild(tr);
        });

        // Wire up inline edit events
        document.querySelectorAll('.bulk-title-input').forEach(function(inp) {
          inp.addEventListener('input', function() { bulkVideoData[+this.dataset.idx].title = this.value; });
        });
        document.querySelectorAll('.bulk-desc-input').forEach(function(inp) {
          inp.addEventListener('input', function() { bulkVideoData[+this.dataset.idx].description = this.value; });
        });
        document.querySelectorAll('.bulk-level-select').forEach(function(sel) {
          sel.addEventListener('change', function() { bulkVideoData[+this.dataset.idx].level = this.value; });
        });
        document.querySelectorAll('.bulk-courses-input').forEach(function(inp) {
          inp.addEventListener('input', function() { bulkVideoData[+this.dataset.idx].courses = this.value; });
        });

        bulkStep1.classList.add('d-none');
        bulkStep2.classList.remove('d-none');
        bulkFetchStatus.textContent = results.length + ' fetched (' + results.filter(function(v) { return v.ok; }).length + ' OK)';
      })
      .catch(function(e) {
        alert('Network error: ' + e.message);
      })
      .finally(function() {
        bulkFetchBtn.disabled = false;
        bulkFetchProg.classList.add('d-none');
      });
  });

  // ---- SAVE ALL ----
  bulkSaveBtn.addEventListener('click', function(){
    // Collect selected tutorials
    var selected = [];
    document.querySelectorAll('.bulk-row-check:checked').forEach(function(cb) {
      var idx = +cb.dataset.idx;
      var v = bulkVideoData[idx];
      if (v && v.ok) {
        selected.push({
          title:         v.title,
          youtube_url:   v.url,
          description:   v.description,
          thumbnail_url: v.thumbnail_url,
          level:         v.level,
          courses:       v.courses,
        });
      }
    });

    if (selected.length === 0) { alert('No tutorials selected. Check at least one.'); return; }

    bulkSaveBtn.disabled = true;
    bulkSaveProg.classList.remove('d-none');
    bulkSaveStatus.textContent = 'Saving ' + selected.length + ' tutorials...';

    fetch(SITE_URL + '/admin/ajax/tutorials_bulk.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ csrf_token: CSRF_TOKEN, tutorials: selected })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.ok) { alert(data.error || 'Save failed'); return; }

      var msg = data.saved + ' of ' + data.total + ' tutorials saved successfully!';
      // Check for individual failures
      var fails = (data.results || []).filter(function(r) { return !r.ok; });
      if (fails.length > 0) {
        msg += '\n\nFailed:\n' + fails.map(function(f) { return '• ' + (f.title || 'Unknown') + ': ' + (f.error || 'Unknown error'); }).join('\n');
      }
      alert(msg);

      // Reload page to see new tutorials
      window.location.href = SITE_URL + '/admin/tutorials.php';
    })
    .catch(function(e) {
      alert('Network error: ' + e.message);
    })
    .finally(function() {
      bulkSaveBtn.disabled = false;
      bulkSaveProg.classList.add('d-none');
    });
  });

  // Helpers
  function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }
  function escAttr(s) {
    return (s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
})();

// ============================================================
// TUTORIAL LIST: Multi-select & Bulk Delete
// ============================================================
function tutToggleAll(master) {
    document.querySelectorAll('.tut-row-check').forEach(function(cb) { cb.checked = master.checked; });
    tutUpdateCount();
}
function tutUpdateCount() {
    var checked = document.querySelectorAll('.tut-row-check:checked');
    var btn = document.getElementById('tutBulkDeleteBtn');
    var cnt = document.getElementById('tutSelectedCount');
    if (btn && cnt) { cnt.textContent = checked.length; btn.classList.toggle('d-none', checked.length === 0); }
}
function tutBulkAction(action, type, specificIds) {
    var ids = specificIds || [];
    if (!specificIds) { document.querySelectorAll('.tut-row-check:checked').forEach(function(cb) { ids.push(+cb.value); }); }
    if (ids.length === 0) { alert('No items selected.'); return; }
    if (!specificIds && !confirm('Delete ' + ids.length + ' tutorial(s)?')) return;
    fetch(SITE_URL + '/admin/ajax/bulk_action.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: CSRF_TOKEN, action: action, type: type, ids: ids })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) { location.href = SITE_URL + '/admin/tutorials.php'; }
        else { alert('Error: ' + (data.error || 'Unknown')); }
    })
    .catch(function(e) { alert('Network error: ' + e.message); });
}
</script>
