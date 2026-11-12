<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('posts')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}
require_once __DIR__ . '/../models/Post.php';

$admin = new PostAdmin($pdo);
$universities = $admin->listUniversities();
$errors = [];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$post = $id ? $admin->getById($id) : null;
$images = $id ? $admin->getImages($id) : [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        // Fall through so the repopulate block below keeps the submitted values
        // and the form re-renders with a fresh token.
        $errors[] = 'Your form session expired. Nothing was saved. Please review and submit again.';
    }

    $payload = [
        'title' => trim($_POST['title'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'excerpt' => trim($_POST['excerpt'] ?? ''),
        'content' => $_POST['content'] ?? '',
        'author_id' => (int)($_SESSION['user_id'] ?? 0),
        'level' => ($_POST['level'] ?? 'Secondary') === 'University' ? 'University' : 'Secondary',
        'university_id' => (int)($_POST['university_id'] ?? 0),
        'is_university_general' => !empty($_POST['is_university_general']) ? 1 : 0,
    ];

    // Validate required
    if ($payload['title'] === '') { $errors[] = 'Title is required.'; }
    if ($payload['slug'] === '') { $errors[] = 'Slug is required.'; }

    // Validate tagging
    if ($payload['level'] === 'University') {
        if ($payload['is_university_general']) {
            $payload['university_id'] = null;
        } else {
            if (empty($payload['university_id']) || $payload['university_id'] <= 0) {
                $errors[] = 'Select a university or mark as University General.';
            }
        }
    } else {
        $payload['is_university_general'] = 0;
        $payload['university_id'] = null;
    }

    if (empty($errors)) {
        try {
            if ($id) {
                $admin->update($id, $payload);
                set_flash_message('success', 'Post updated.');
            } else {
                $newId = $admin->create($payload);
                set_flash_message('success', 'Post created.');
                $id = $newId;
            }

            // Handle attached images
            $postIdForImages = $id;

            // Delete selected existing images
            $removeIds = $_POST['remove_images'] ?? [];
            if (!empty($removeIds) && is_array($removeIds)) {
                $admin->deleteImagesByIds($removeIds, $postIdForImages);
            }

            // Upload new images
            if (!empty($_FILES['images']['name'][0])) {
                $uploadDir = ROOT_PATH . '/assets/uploads/posts/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $total = count($_FILES['images']['name']);
                for ($i = 0; $i < $total; $i++) {
                    if (empty($_FILES['images']['name'][$i])) {
                        continue;
                    }
                    if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    $tmpPath = $_FILES['images']['tmp_name'][$i];
                    $origName = basename($_FILES['images']['name'][$i]);
                    $mimeType = $_FILES['images']['type'][$i] ?? '';

                    // Basic image-only guard
                    if (strpos($mimeType, 'image/') !== 0) {
                        continue;
                    }

                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $newName = uniqid('post_', true) . ($ext ? ('.' . $ext) : '');
                    $destPath = $uploadDir . $newName;

                    if (move_uploaded_file($tmpPath, $destPath)) {
                        $relativePath = 'assets/uploads/posts/' . $newName;
                        $admin->addImage($postIdForImages, $relativePath, null, 0);
                    }
                }
            }

            redirect(SITE_URL . '/admin/posts.php');
        } catch (Throwable $e) {
            log_error('Post save failed', ['error'=>$e->getMessage()]);
            $errors[] = 'Could not save post. Ensure slug is unique.';
        }
    }

    if (!empty($errors)) {
        // Repopulate so the admin does not lose the submitted post body.
        $post = array_merge((array)$post, $payload);
    }
}

$page_title = $id ? 'Edit Post' : 'New Post';
include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
  <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h4 mb-0"><?php echo htmlspecialchars($page_title); ?></h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo SITE_URL; ?>/admin/posts.php">Back</a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-flash"><?php echo htmlspecialchars(implode(' ', $errors)); ?></div>
  <?php endif; ?>

  <form method="post" action="" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

    <div class="card mb-3 shadow-sm border-0" style="border-radius: 0.75rem;">
      <div class="card-header bg-white"><strong>Post Details</strong></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Title</label>
            <input type="text" class="form-control" name="title" required value="<?php echo htmlspecialchars($post['title'] ?? ''); ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label d-flex justify-content-between align-items-center">
              <span>Slug</span>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="btnGenerateSlug">Generate</button>
            </label>
            <input type="text" class="form-control" name="slug" id="slugInput" required value="<?php echo htmlspecialchars($post['slug'] ?? ''); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Level</label>
            <select class="form-select" name="level" id="levelSelect">
              <option value="Secondary" <?php echo (($post['level'] ?? 'Secondary')==='Secondary')?'selected':''; ?>>Secondary</option>
              <option value="University" <?php echo (($post['level'] ?? '')==='University')?'selected':''; ?>>University</option>
            </select>
          </div>
          <div class="col-md-3" id="uniGeneralWrap">
            <label class="form-label d-block">University Scope</label>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="is_university_general" name="is_university_general" <?php echo !empty($post['is_university_general'])?'checked':''; ?>>
              <label class="form-check-label" for="is_university_general">University General</label>
            </div>
          </div>
          <div class="col-md-6" id="uniSelectWrap">
            <label class="form-label">University</label>
            <select class="form-select" name="university_id" id="universitySelect">
              <option value="">Select university</option>
              <?php foreach ($universities as $u): ?>
                <option value="<?php echo (int)$u['id']; ?>" <?php echo ((int)($post['university_id'] ?? 0)===(int)$u['id'])?'selected':''; ?>><?php echo htmlspecialchars($u['name'] . ' — ' . $u['acronym']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Excerpt</label>
            <textarea class="form-control" rows="2" name="excerpt"><?php echo htmlspecialchars($post['excerpt'] ?? ''); ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Content</label>
            <div id="quillEditor" style="height: 400px;"></div>
            <textarea class="form-control d-none" rows="12" name="content" id="contentInput"><?php echo htmlspecialchars($post['content'] ?? ''); ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3 shadow-sm border-0" style="border-radius: 0.75rem;">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>Build Method</strong>
        <div class="btn-group btn-group-sm" role="group" aria-label="Build Method">
          <input type="radio" class="btn-check" name="build_method" id="bmManual" autocomplete="off" checked>
          <label class="btn btn-outline-secondary" for="bmManual">Manual</label>
          <input type="radio" class="btn-check" name="build_method" id="bmCode" autocomplete="off">
          <label class="btn btn-outline-secondary" for="bmCode">Code</label>
        </div>
      </div>
      <div class="card-body" id="codeBuilder" style="display:none;">
        <div class="mb-2 d-flex justify-content-between align-items-center">
          <label class="form-label mb-0">Code Import (JSON)</label>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnShowSample">Show Sample</button>
            <button type="button" class="btn btn-sm btn-primary" id="btnLoadFromCode">Load Data</button>
          </div>
        </div>
        <textarea class="form-control font-monospace" id="codeInput" rows="8" placeholder='{"title":"...","slug":"...","excerpt":"...","content":"<p>HTML</p>","level":"Secondary","university_acronym":"UNILAG","is_university_general":false}'></textarea>
        <div id="codeSample" class="mt-2" style="display:none;">
          <pre class="bg-light p-2 border rounded small mb-0"><code id="sampleJson"></code></pre>
        </div>
        <div id="codeConsole" class="mt-2 small" style="display:none;"></div>
      </div>
    </div>

    <div class="card mb-3 shadow-sm border-0" style="border-radius: 0.75rem;">
      <div class="card-header bg-white">
        <strong>Images</strong>
      </div>
      <div class="card-body">
        <?php if (!empty($images)): ?>
          <div class="mb-3">
            <label class="form-label d-block">Attached Images</label>
            <div class="d-flex flex-wrap gap-3">
              <?php foreach ($images as $img): ?>
                <div class="border rounded p-2" style="width: 140px;">
                  <div class="ratio ratio-4x3 mb-1" style="background:#f8fafc; border-radius:0.5rem; overflow:hidden;">
                    <img src="<?php echo SITE_URL . '/' . htmlspecialchars($img['file_path']); ?>" alt="Post image" class="img-fluid" style="object-fit: cover;">
                  </div>
                  <div class="form-check small">
                    <input class="form-check-input" type="checkbox" name="remove_images[]" value="<?php echo (int)$img['id']; ?>" id="rmImg<?php echo (int)$img['id']; ?>">
                    <label class="form-check-label" for="rmImg<?php echo (int)$img['id']; ?>">Remove</label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <label class="form-label">Upload Images</label>
        <input type="file" class="form-control" name="images[]" multiple accept="image/*">
        <small class="text-muted d-block mt-1">You can select multiple images. JPG, PNG, and GIF are recommended.</small>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
      <button type="submit" class="btn btn-primary">Save</button>
      <a class="btn btn-outline-secondary" href="<?php echo SITE_URL; ?>/admin/posts.php">Cancel</a>
    </div>
  </form>
    </main>
  </div>
</div>

<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<script>
(function(){
  var levelSelect = document.getElementById('levelSelect');
  var uniGeneral = document.getElementById('is_university_general');
  var uniSelect = document.getElementById('universitySelect');
  var uniGeneralWrap = document.getElementById('uniGeneralWrap');
  var uniSelectWrap = document.getElementById('uniSelectWrap');
  var titleInput = document.querySelector('input[name="title"]');
  var slugInput = document.getElementById('slugInput');
  var btnGenerateSlug = document.getElementById('btnGenerateSlug');
  var contentInput = document.getElementById('contentInput');
  var bmManual = document.getElementById('bmManual');
  var bmCode = document.getElementById('bmCode');
  var codeBuilder = document.getElementById('codeBuilder');
  var codeInput = document.getElementById('codeInput');
  var codeConsole = document.getElementById('codeConsole');
  var codeSampleWrap = document.getElementById('codeSample');
  var sampleJsonEl = document.getElementById('sampleJson');
  var btnShowSample = document.getElementById('btnShowSample');
  var btnLoadFromCode = document.getElementById('btnLoadFromCode');
  var quill;
  var csrfTokenInput = document.querySelector('input[name="csrf_token"]');

  function updateUniUI(){
    var isUni = levelSelect && levelSelect.value === 'University';
    uniGeneralWrap.style.display = isUni ? '' : 'none';
    uniSelectWrap.style.display = (isUni && !uniGeneral.checked) ? '' : 'none';
    if (uniSelect) uniSelect.disabled = (!isUni || uniGeneral.checked);
  }

  levelSelect && levelSelect.addEventListener('change', updateUniUI);
  uniGeneral && uniGeneral.addEventListener('change', updateUniUI);
  updateUniUI();

  function slugify(text){
    return (text || '')
      .toString()
      .trim()
      .toLowerCase()
      .replace(/['"]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .substring(0, 120);
  }
  if (btnGenerateSlug){
    btnGenerateSlug.addEventListener('click', function(){
      if (titleInput && slugInput){ slugInput.value = slugify(titleInput.value); }
    });
  }
  if (titleInput && slugInput){
    titleInput.addEventListener('input', function(){ if (!slugInput.value) { slugInput.value = slugify(titleInput.value); } });
  }

  function setConsole(msg, isError){
    if (!codeConsole) return;
    codeConsole.style.display = 'block';
    codeConsole.style.whiteSpace = 'pre-wrap';
    codeConsole.style.border = '1px solid ' + (isError ? '#f5c2c7' : '#cfe2ff');
    codeConsole.style.background = isError ? '#f8d7da' : '#e7f1ff';
    codeConsole.style.color = isError ? '#842029' : '#084298';
    codeConsole.style.padding = '8px';
    codeConsole.style.borderRadius = '6px';
    codeConsole.textContent = msg;
  }

  function clearConsole(){
    if (!codeConsole) return;
    codeConsole.style.display = 'none';
    codeConsole.textContent = '';
  }

  function updateBuildMethod(){
    if (!bmManual || !bmCode || !codeBuilder) return;
    var useCode = bmCode.checked;
    codeBuilder.style.display = useCode ? '' : 'none';
  }

  if (bmManual && bmCode){
    bmManual.addEventListener('change', updateBuildMethod);
    bmCode.addEventListener('change', updateBuildMethod);
    updateBuildMethod();
  }

  var universitiesAcronymMap = <?php
    $map = [];
    foreach ($universities as $u) {
        $map[$u['acronym']] = (int)$u['id'];
    }
    echo json_encode($map);
  ?>;

  function loadFromObject(obj){
    if (!obj || typeof obj !== 'object') {
      throw new Error('Code must be a JSON object.');
    }
    if (!obj.title) {
      throw new Error('Missing required field: title');
    }

    if (titleInput) titleInput.value = obj.title || '';
    if (slugInput) slugInput.value = obj.slug || '';
    var excerptEl = document.querySelector('textarea[name="excerpt"]');
    if (excerptEl) excerptEl.value = obj.excerpt || '';

    var level = (obj.level === 'University') ? 'University' : 'Secondary';
    if (levelSelect) {
      levelSelect.value = level;
      updateUniUI();
    }

    var uniGeneralEl = document.getElementById('is_university_general');
    if (typeof obj.is_university_general !== 'undefined' && uniGeneralEl) {
      uniGeneralEl.checked = !!obj.is_university_general;
    }

    if (obj.university_acronym && level === 'University') {
      var uniSelect = document.getElementById('universitySelect');
      var uid = universitiesAcronymMap[obj.university_acronym] || null;
      if (uniSelect && uid) {
        uniSelect.value = String(uid);
      }
    }

    if (quill && obj.content) {
      quill.clipboard.dangerouslyPasteHTML(obj.content);
      if (contentInput) {
        contentInput.value = obj.content;
      }
    } else if (contentInput && obj.content) {
      contentInput.value = obj.content;
    }
  }

  function getSample(){
    return JSON.stringify({
      title: 'How to Stay Focused While Studying',
      slug: 'stay-focused-while-studying',
      excerpt: 'Practical tips to keep your mind sharp and focused before exams.',
      content: '<h2>Stay Focused</h2><p>Remove distractions, take breaks, and plan your sessions.</p>',
      level: 'Secondary',
      university_acronym: null,
      is_university_general: false
    }, null, 2);
  }

  if (btnShowSample && sampleJsonEl && codeSampleWrap){
    btnShowSample.addEventListener('click', function(){
      if (codeSampleWrap.style.display === 'none' || !codeSampleWrap.style.display) {
        sampleJsonEl.textContent = getSample();
        codeSampleWrap.style.display = '';
      } else {
        codeSampleWrap.style.display = 'none';
      }
    });
  }

  if (btnLoadFromCode && codeInput){
    btnLoadFromCode.addEventListener('click', function(){
      clearConsole();
      var txt = codeInput.value.trim();
      if (!txt){
        setConsole('Please paste JSON code to import.', true);
        return;
      }
      try {
        var obj = JSON.parse(txt);
        loadFromObject(obj);
        setConsole('Loaded successfully. Review and click Save to persist.', false);
      } catch (e) {
        setConsole('Parse/validation error: ' + e.message, true);
      }
    });
  }

  // Initialize Quill (free, no API key)
  if (window.Quill) {
    quill = new Quill('#quillEditor', {
      theme: 'snow',
      modules: {
        toolbar: [
          [{ header: [1, 2, 3, false] }],
          ['bold', 'italic', 'underline'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['link', 'image'],
          ['clean']
        ]
      }
    });
    if (contentInput && contentInput.value) {
      quill.clipboard.dangerouslyPasteHTML(contentInput.value);
    }
    var toolbar = quill.getModule('toolbar');
    if (toolbar) {
      toolbar.addHandler('image', function() {
        var fileInput = document.createElement('input');
        fileInput.setAttribute('type', 'file');
        fileInput.setAttribute('accept', 'image/*');
        fileInput.onchange = function() {
          var file = fileInput.files[0];
          if (!file) return;
          var formData = new FormData();
          formData.append('image', file);
          if (csrfTokenInput && csrfTokenInput.value) {
            formData.append('csrf_token', csrfTokenInput.value);
          }
          fetch('<?php echo SITE_URL; ?>/ajax/post_image_upload.php', {
            method: 'POST',
            body: formData
          })
          .then(function(res){ return res.json(); })
          .then(function(json){
            if (json && json.success && json.url) {
              var range = quill.getSelection(true);
              quill.insertEmbed(range ? range.index : 0, 'image', json.url, 'user');
            } else if (json && json.error) {
              alert(json.error);
            }
          })
          .catch(function(){
            alert('Could not upload image. Please try again.');
          });
        };
        fileInput.click();
      });
    }

    var form = document.querySelector('form');
    if (form) {
      form.addEventListener('submit', function(){
        if (contentInput && quill) { contentInput.value = quill.root.innerHTML; }
      });
    }
  }

})();
</script>

