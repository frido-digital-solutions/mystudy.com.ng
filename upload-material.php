<?php
require_once __DIR__ . '/config/config.php';
require_login();

$page_title = 'Upload Material';
require_once __DIR__ . '/models/Material.php';
$materialModel = new Material($pdo);
// Load universities for University scope selection
$universities = $materialModel->listUniversities();

// CSRF token (centralized helper returns string)
$csrf_token = csrf_token();

$errors = [];
$success = false;
$title = '';
$description = '';
$category_id = null; // removed from form; keep null by default
$visibility = 'public'; // default
$level = 'Secondary';
$secondary_board = 'General';
$university_id = '';
$is_university_general = 0;
$university_scope = 'specific'; // 'general' or 'specific' (university only)

// Categories not needed on this form anymore
$categories = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors['csrf'] = 'Invalid form token. Please refresh and try again.';
    }

    // sanitize() passes arrays through, which would fatal in htmlspecialchars below
    $title = is_array($_POST['title'] ?? null) ? '' : sanitize($_POST['title'] ?? '');
    $description = is_array($_POST['description'] ?? null) ? '' : sanitize($_POST['description'] ?? '');
    // category and visibility removed from form; keep defaults
    $level = ($_POST['level'] ?? 'Secondary') === 'University' ? 'University' : 'Secondary';
    $secondary_board = in_array(($_POST['secondary_board'] ?? 'General'), ['General','WAEC','NECO','JAMB','GCE'], true) ? ($_POST['secondary_board'] ?? 'General') : 'General';
    $university_scope = ($_POST['university_scope'] ?? 'specific') === 'general' ? 'general' : 'specific';
    $is_university_general = ($level === 'University' && $university_scope === 'general') ? 1 : 0;
    $university_id = isset($_POST['university_id']) && $_POST['university_id'] !== '' ? (int)$_POST['university_id'] : null;

    // File handling (supports single or multiple files)
    $allowed_ext = ['pdf','doc','docx','ppt','pptx','xls','xlsx','jpg','jpeg','png'];
    $max_per_file = 5 * 1024 * 1024; // 5MB per file
    $max_total = 30 * 1024 * 1024; // 30MB total per upload
    $upload_dir = ROOT_PATH . '/assets/uploads/materials/';

    $files = [];
    if (!isset($_FILES['file'])) {
        $errors['file'] = 'Please upload at least one file';
    } else {
        $f = $_FILES['file'];
        $isMulti = is_array($f['name'] ?? null);
        if ($isMulti) {
            $count = count($f['name']);
            for ($i = 0; $i < $count; $i++) {
                if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                $files[] = [
                    'name' => $f['name'][$i] ?? '',
                    'tmp_name' => $f['tmp_name'][$i] ?? '',
                    'error' => $f['error'][$i] ?? UPLOAD_ERR_OK,
                    'size' => (int)($f['size'][$i] ?? 0),
                ];
            }
        } else {
            $files[] = $f;
        }
    }

    $isBulk = count($files) > 1;
    if (!$isBulk && $title === '') {
        $errors['title'] = 'Title is required';
    }

    // Level validation (applies to single and bulk)
    if ($level === 'University' && !$is_university_general && empty($university_id)) {
        $errors['university_id'] = 'Please select a university (or choose University General).';
    }

    if (empty($errors)) {
        if (empty($files)) {
            $errors['file'] = 'Please upload at least one file';
        } else {
            $totalSize = 0;
            foreach ($files as $file) {
                if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    $errors['file'] = 'One or more files failed to upload. Please try again.';
                    break;
                }
                $size = (int)($file['size'] ?? 0);
                $totalSize += max(0, $size);
                if ($size <= 0 || $size > $max_per_file) {
                    $errors['file'] = 'Each file must be 5MB or less';
                    break;
                }
                $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
                if ($ext === '' || !in_array($ext, $allowed_ext, true)) {
                    $errors['file'] = 'Invalid file type detected';
                    break;
                }
            }
            if (empty($errors) && $totalSize > $max_total) {
                $errors['file'] = 'Total upload must be 30MB or less';
            }
        }
    }

    if (empty($errors)) {
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $created = 0;
        foreach ($files as $file) {
            $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
            $dest = $upload_dir . $safeName;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $errors['file'] = 'Failed to save one of the uploaded files';
                break;
            }

            $relative_path = 'assets/uploads/materials/' . $safeName;
            $mime = mime_content_type($dest) ?: null;
            $original_name = trim((string)$file['name']);

            $finalTitle = $isBulk ? preg_replace('/[\-_]+/', ' ', preg_replace('/\.[^.]+$/', '', $original_name)) : $title;
            $finalTitle = trim((string)$finalTitle);
            if ($finalTitle === '') $finalTitle = 'Study Material';

            $finalDesc = $isBulk ? '' : $description;

            $materialModel->create([
                'title' => $finalTitle,
                'description' => $finalDesc,
                'category_id' => null,
                'file_path' => $relative_path,
                'file_size' => filesize($dest),
                'mime_type' => $mime,
                'uploader_id' => (int)$_SESSION['user_id'],
                'visibility' => 'public',
                'status' => 'pending',
                'level' => $level,
                'secondary_board' => $secondary_board,
                'university_id' => $is_university_general ? null : $university_id,
                'is_university_general' => $level === 'University' ? (int)$is_university_general : 0,
                'original_name' => $original_name,
            ]);
            $created++;
        }

        if (empty($errors)) {
            $success = true;
            set_flash_message('success', $created > 1 ? ($created . ' uploads received! Awaiting admin approval.') : 'Upload received! Awaiting admin approval.');
            
            $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            if ($is_ajax) {
                echo json_encode(['success' => true, 'redirect' => SITE_URL . '/materials.php']);
                exit;
            }
            
            header('Location: ' . SITE_URL . '/materials.php');
            exit;
        }
    }
    
    // If validation failed and it's AJAX, return JSON array of errors
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($is_ajax && !empty($errors)) {
        echo json_encode(['success' => false, 'errors' => array_values($errors)]);
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
    <h3 class="mb-3">Upload Study Material</h3>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="materialForm" data-disable-level-toggle>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <div class="mb-4">
            <label class="form-label d-block">Select file</label>
            <div id="dropArea" class="border border-2 border-dashed rounded-3 p-4 text-center bg-white" style="border-style: dashed; cursor: pointer;">
                <div class="text-muted">
                    <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                    <div>Drag & drop your file here, or click to browse</div>
                    <div class="small">Allowed: pdf, doc/docx, ppt/pptx, xls/xlsx, jpg/png. Max 5MB per file. Max 30MB total.</div>
                </div>
            </div>
            <input type="file" name="file[]" id="fileInput" class="form-control" required multiple>
            <div id="fileInfo" class="mt-2 d-none">
                <div class="small text-muted mb-2" id="fileSummary"></div>
                <div id="fileList" class="d-flex flex-wrap gap-2"></div>
            </div>
        </div>

        <div id="detailsWrap">
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" id="titleInput" class="form-control" value="<?php echo htmlspecialchars($title); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($description); ?></textarea>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Material Type</label>
                    <select name="level" id="levelSelect" class="form-select">
                        <option value="Secondary" <?php echo $level==='Secondary'?'selected':''; ?>>Secondary</option>
                        <option value="University" <?php echo $level==='University'?'selected':''; ?>>University</option>
                    </select>
                    <div class="form-text text-muted">For multiple uploads, this selection applies to all selected files.</div>
                </div>
                <div class="col-md-4" id="secBoardWrap">
                    <label class="form-label">Secondary Scope</label>
                    <select name="secondary_board" id="secondaryBoard" class="form-select">
                        <?php foreach (['General'=>'All','WAEC'=>'WAEC','NECO'=>'NECO','JAMB'=>'JAMB','GCE'=>'GCE'] as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo $secondary_board===$val?'selected':''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4" id="uniScopeWrap" style="display:none;">
                    <label class="form-label">University Scope</label>
                    <select name="university_scope" id="universityScope" class="form-select">
                        <option value="specific" <?php echo $university_scope==='specific'?'selected':''; ?>>Specific University</option>
                        <option value="general" <?php echo $university_scope==='general'?'selected':''; ?>>University General</option>
                    </select>
                </div>
                <div class="col-md-6" id="uniSelectWrap" style="display:none;">
                    <label class="form-label">University</label>
                    <select name="university_id" id="universitySelect" class="form-select">
                        <option value="">Select university</option>
                        <?php foreach ($universities as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>" <?php echo ((int)$university_id===(int)$u['id'])?'selected':''; ?>><?php echo htmlspecialchars($u['name'] . ' — ' . $u['acronym']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Upload Progress UI -->
            <div id="uploadProgressContainer" class="mt-4 mb-3 d-none">
                <div class="d-flex justify-content-between mb-1">
                    <span class="small fw-semibold text-primary" id="uploadStatusText">Uploading files...</span>
                    <span class="small fw-bold text-primary" id="uploadPercentage">0%</span>
                </div>
                <div class="progress" style="height: 10px; border-radius: 5px;">
                    <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>

            <!-- Error Alerts for AJAX -->
            <div id="ajaxErrorContainer" class="alert alert-danger mt-3 d-none">
                <ul class="mb-0" id="ajaxErrorList"></ul>
            </div>

            <div class="mt-4 d-flex align-items-center gap-2">
                <button class="btn btn-primary" type="submit" id="submitBtn"><i class="fas fa-paper-plane me-1"></i> Submit for Review</button>
                <a href="<?php echo SITE_URL; ?>/materials.php" class="btn btn-outline-secondary" id="cancelBtn">Cancel</a>
            </div>
        </div>
    </form>
</div>
<script>
(function(){
  var drop = document.getElementById('dropArea');
  var fileInput = document.getElementById('fileInput');
  var fileInfo = document.getElementById('fileInfo');
  var fileSummary = document.getElementById('fileSummary');
  var fileList = document.getElementById('fileList');
  var details = document.getElementById('detailsWrap');
  var titleInput = document.getElementById('titleInput');
  var titleWrap = titleInput ? titleInput.closest('.mb-3') : null;
  var descWrap = null;
  try { descWrap = document.querySelector('textarea[name=\"description\"]').closest('.mb-3'); } catch(e) {}
  var levelSel = document.getElementById('levelSelect');
  var secBoardWrap = document.getElementById('secBoardWrap');
  var uniScopeWrap = document.getElementById('uniScopeWrap');
  var uniScopeSel = document.getElementById('universityScope');
  var uniSelWrap = document.getElementById('uniSelectWrap');
  var uniSelect = document.getElementById('universitySelect');

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function updateLevelUI(){
    var isUni = levelSel.value === 'University';
    secBoardWrap.style.display = isUni ? 'none' : 'block';
    uniScopeWrap.style.display = isUni ? 'block' : 'none';
    var scope = uniScopeSel ? uniScopeSel.value : 'specific';
    uniSelWrap.style.display = (isUni && scope !== 'general') ? 'block' : 'none';
  }
  if (levelSel){
    levelSel.addEventListener('change', updateLevelUI);
  }
  if (uniScopeSel){
    uniScopeSel.addEventListener('change', updateLevelUI);
  }
  updateLevelUI();

  function handleFiles(files){
    if (!files || !files.length) return;
    fileList.innerHTML = '';
    var total = 0;
    for (var i = 0; i < files.length; i++){
      total += files[i].size || 0;
      var badge = document.createElement('span');
      badge.className = 'badge bg-light text-dark border';
      badge.innerHTML = '<i class=\"far fa-file me-1\"></i> ' + escapeHtml(files[i].name);
      fileList.appendChild(badge);
    }
    var mb = (total / (1024*1024)).toFixed(2);
    fileSummary.textContent = files.length + ' file(s) selected — Total: ' + mb + ' MB (Max 30 MB). Each file max 5 MB.';
    fileInfo.classList.remove('d-none');
    details.classList.remove('d-none');

    var isBulk = files.length > 1;
    if (isBulk) {
      if (titleWrap) titleWrap.classList.add('d-none');
      if (descWrap) descWrap.classList.add('d-none');
      if (titleInput) titleInput.removeAttribute('required');
      if (titleInput) titleInput.value = '';
    } else {
      if (titleWrap) titleWrap.classList.remove('d-none');
      if (descWrap) descWrap.classList.remove('d-none');
      if (titleInput) titleInput.setAttribute('required', 'required');
      var f = files[0];
      var base = f.name.replace(/\.[^.]+$/, '');
      if (titleInput && !titleInput.value) titleInput.value = base.replace(/[\-_]+/g, ' ').trim();
    }
  }

  ['dragenter','dragover'].forEach(function(ev){
    drop.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); drop.classList.add('bg-light'); });
  });
  ;['dragleave','drop'].forEach(function(ev){
    drop.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); drop.classList.remove('bg-light'); });
  });
  drop.addEventListener('drop', function(e){
    if (e.dataTransfer && e.dataTransfer.files) {
      fileInput.files = e.dataTransfer.files;
      handleFiles(e.dataTransfer.files);
    }
  });
  drop.addEventListener('click', function(){ fileInput.click(); });
  fileInput.addEventListener('change', function(){ handleFiles(this.files); });

  // AJAX Form Submission with Progress Bar
  var form = document.getElementById('materialForm');
  var submitBtn = document.getElementById('submitBtn');
  var cancelBtn = document.getElementById('cancelBtn');
  var progressContainer = document.getElementById('uploadProgressContainer');
  var progressBar = document.getElementById('uploadProgressBar');
  var progressText = document.getElementById('uploadPercentage');
  var statusText = document.getElementById('uploadStatusText');
  var ajaxErrorContainer = document.getElementById('ajaxErrorContainer');
  var ajaxErrorList = document.getElementById('ajaxErrorList');

  if (form) {
      form.addEventListener('submit', function(e) {
          e.preventDefault();
          
          if(!fileInput.files.length) {
              alert("Please select at least one file to upload.");
              return;
          }

          // Check limits before uploading locally to save bandwidth
          var totalSize = 0;
          for (var i = 0; i < fileInput.files.length; i++) {
              if (fileInput.files[i].size > 5 * 1024 * 1024) {
                  alert('File "' + fileInput.files[i].name + '" exceeds the 5MB limit.');
                  return;
              }
              totalSize += fileInput.files[i].size;
          }
          if (totalSize > 30 * 1024 * 1024) {
              alert('Total upload size exceeds the 30MB limit.');
              return;
          }

          // Initialize UI
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading...';
          cancelBtn.classList.add('disabled');
          progressContainer.classList.remove('d-none');
          ajaxErrorContainer.classList.add('d-none');
          progressBar.style.width = '0%';
          progressBar.classList.remove('bg-success');
          progressBar.classList.add('bg-primary');
          progressText.innerText = '0%';
          statusText.innerText = 'Uploading files... Do not close this page.';

          var formData = new FormData(form);
          var xhr = new XMLHttpRequest();
          xhr.open('POST', form.action || window.location.href, true);
          xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest'); // Flag as AJAX
          
          // Track upload progress
          xhr.upload.addEventListener('progress', function(e) {
              if (e.lengthComputable) {
                  var percentComplete = Math.floor((e.loaded / e.total) * 100);
                  progressBar.style.width = percentComplete + '%';
                  progressBar.setAttribute('aria-valuenow', percentComplete);
                  progressText.innerText = percentComplete + '%';
                  
                  if (percentComplete === 100) {
                      statusText.innerText = 'Processing on server... Please wait.';
                      progressBar.classList.add('bg-success');
                      progressBar.classList.remove('bg-primary');
                  }
              }
          });

          // Handle server response
          xhr.onload = function() {
              if (xhr.status >= 200 && xhr.status < 400) {
                  try {
                      var res = JSON.parse(xhr.responseText);
                      if (res.success && res.redirect) {
                          statusText.innerText = 'Upload Complete! Redirecting...';
                          window.location.href = res.redirect;
                      } else if (!res.success && res.errors) {
                          // Validation failed on server
                          resetFormBtn();
                          ajaxErrorList.innerHTML = '';
                          res.errors.forEach(function(err) {
                              var li = document.createElement('li');
                              li.textContent = err;
                              ajaxErrorList.appendChild(li);
                          });
                          ajaxErrorContainer.classList.remove('d-none');
                      } else {
                          throw new Error('Invalid response format');
                      }
                  } catch (err) {
                      alert('Server response could not be read. Please try again.');
                      resetFormBtn();
                  }
              } else {
                  alert('Upload failed due to a server or network error. Status: ' + xhr.status);
                  resetFormBtn();
              }
          };
          
          xhr.onerror = function() {
              alert('A network error occurred during upload. Please check your internet connection.');
              resetFormBtn();
          };

          function resetFormBtn() {
              submitBtn.disabled = false;
              submitBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit for Review';
              cancelBtn.classList.remove('disabled');
          }

          xhr.send(formData);
      });
  }
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
