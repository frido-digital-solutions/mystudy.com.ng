<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('quizzes')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}
require_once __DIR__ . '/../models/Gamification.php';
require_once __DIR__ . '/../models/Quiz.php';

$admin = new QuizAdmin($pdo);
$errors = [];
$editingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quiz = $editingId ? $admin->getFull($editingId) : null;

/**
 * The one picture control a question or an option gets.
 *
 * It is deliberately not a plain file input inside the form. A quiz can carry
 * a hundred and twenty of those, and PHP discards every upload past the
 * twentieth in a single submit; and a picture the admin uploaded but never
 * saved would sit in the folder forever. So the button posts the file away on
 * its own, and this field only ever holds the path that came back.
 */
function quiz_image_field(string $name, $path, bool $compact = false): string
{
    $path = trim((string)$path);
    $url = $path !== '' ? SITE_URL . '/' . $path : '';
    $uid = preg_replace('/[^a-zA-Z0-9]/', '_', $name);
    ob_start(); ?>
    <div class="qimg-field<?php echo $compact ? ' qimg-compact' : ''; ?> <?php echo $compact ? 'mt-1' : 'mb-2'; ?>" data-qimg>
      <input type="hidden" name="<?php echo htmlspecialchars($name); ?>" value="<?php echo htmlspecialchars($path); ?>">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <button type="button" class="btn btn-sm btn-outline-primary btnQuizImage">
          <i class="fas fa-image me-1"></i><?php echo $path !== '' ? ($compact ? 'Change' : 'Change picture') : ($compact ? 'Picture' : 'Add picture'); ?>
        </button>
        <button type="button" class="btn btn-sm btn-outline-danger btnQuizImageRemove"<?php echo $path === '' ? ' style="display:none;"' : ''; ?>>Remove</button>
        <span class="small text-muted btnQuizImageStatus" style="display:none;">Uploading&hellip;</span>
      </div>
      <img class="qimg-thumb rounded border mt-2<?php echo $path === '' ? ' d-none' : ''; ?>"<?php echo $path !== '' ? ' src="' . htmlspecialchars($url) . '"' : ''; ?> alt="Attached picture">
    </div>
    <?php return ob_get_clean();
}

// Fetch all approved materials for the multi-select
$allMaterials = $pdo->query("SELECT id, title, original_name FROM materials WHERE status = 'approved' ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch already-attached material IDs for this quiz
$attachedMaterialIds = [];
if ($editingId) {
    $matStmt = $pdo->prepare("SELECT material_id FROM quiz_materials WHERE quiz_id = ?");
    $matStmt->execute([$editingId]);
    $attachedMaterialIds = $matStmt->fetchAll(PDO::FETCH_COLUMN);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        // Fall through: the repopulate block below keeps every submitted field
        // and question, and the form re-renders with a fresh token.
        $errors[] = 'Your form session expired. Nothing was saved. Please review and submit again.';
    }

    // Collect quiz fields
    $payload = [
        'title' => trim($_POST['title'] ?? ''),
        'subject' => trim($_POST['subject'] ?? ''),
        'level' => '', // auto-derived from type below
        'description' => trim($_POST['description'] ?? ''),
        'type' => trim($_POST['type'] ?? ''),
        'is_prize' => 0,
        'mode' => trim($_POST['mode'] ?? 'normal'),
        'event_start' => trim($_POST['event_start'] ?? ''),
        'event_end' => trim($_POST['event_end'] ?? ''),
        'prize_type' => trim($_POST['prize_type'] ?? ''),
        'prize_amount' => trim($_POST['prize_amount'] ?? ''),
        'prize_text' => trim($_POST['prize_text'] ?? ''),
        'duration_seconds' => (int)($_POST['duration_seconds'] ?? 900),
        'slug' => trim($_POST['slug'] ?? '') ?: null,
        'max_participants' => trim($_POST['max_participants'] ?? '') !== '' ? (int)$_POST['max_participants'] : null,
        'is_paid' => !empty($_POST['is_paid']) ? 1 : 0,
        'entry_fee' => trim($_POST['entry_fee'] ?? '') !== '' ? $_POST['entry_fee'] : null,
        'required_level' => (int)($_POST['required_level'] ?? 0),
        'exam_types' => (in_array(trim($_POST['type'] ?? ''), ['secondary','both'])) ? (isset($_POST['exam_types']) && is_array($_POST['exam_types']) ? implode(',', $_POST['exam_types']) : '') : null,
    ];

    if ($payload['title'] === '') { $errors[] = 'Title is required.'; }
    // Auto-derive level from type
    if (!in_array($payload['type'], ['secondary','university','both'], true)) { $errors[] = 'Target Students must be secondary, university, or both.'; }
    $payload['level'] = ($payload['type'] === 'university') ? 'University' : (($payload['type'] === 'both') ? 'General' : 'Secondary');
    if (!in_array($payload['mode'], ['normal','challenge'], true)) { $errors[] = 'Mode must be normal or challenge.'; }

    // Challenge-specific validation
    if ($payload['mode'] === 'challenge') {
        // Mark as prize/challenge for backward compatibility
        $payload['is_prize'] = 1;
        $es = $payload['event_start'] ? strtotime($payload['event_start']) : false;
        $ee = $payload['event_end'] ? strtotime($payload['event_end']) : false;
        if (!$es) { $errors[] = 'Event start is required for Challenge quiz.'; }
        if (!$ee) { $errors[] = 'Event end is required for Challenge quiz.'; }
        if ($es && $ee && $ee <= $es) { $errors[] = 'Event end must be after event start.'; }
        if ($payload['prize_type'] && !in_array($payload['prize_type'], ['money','airtime','data','text'], true)) {
            $errors[] = 'Prize type must be money, airtime, data, or text.';
        }
        if (in_array($payload['prize_type'], ['money','airtime','data'], true)) {
            if ($payload['prize_amount'] === '' || !is_numeric($payload['prize_amount']) || (float)$payload['prize_amount'] <= 0) {
                $errors[] = 'Enter a valid prize amount/value.';
            }
        }
        // Normalize datetimes to MySQL format
        if ($es) { $payload['event_start'] = date('Y-m-d H:i:s', $es); }
        if ($ee) { $payload['event_end'] = date('Y-m-d H:i:s', $ee); }
        if (!in_array($payload['prize_type'], ['money','airtime','data'], true)) { $payload['prize_amount'] = null; }
        if ($payload['prize_type'] !== 'text') { /* keep prize_text as provided or empty */ }
    } else {
        // Normal mode clears challenge fields
        $payload['event_start'] = null;
        $payload['event_end'] = null;
        $payload['prize_type'] = null;
        $payload['prize_amount'] = null;
        $payload['prize_text'] = null;
    }

    // Questions structure
    $questions = $_POST['questions'] ?? [];
    $normalized = [];
    // A picture only reaches the database as a path this page's own uploader
    // handed back. Anything else - someone typing ../config/secrets.local.php
    // into the field, for instance - is dropped, because quiz images are
    // published to every student who opens the quiz.
    $keptImage = static function ($path): string {
        $path = trim((string)$path);
        return preg_match('#^assets/uploads/quiz_images/[a-f0-9]{16}\.(?:jpg|jpeg|png|gif|webp)$#', $path) ? $path : '';
    };
    foreach ($questions as $q) {
        $qText = trim($q['question'] ?? '');
        if ($qText === '') { continue; }
        $qExpl = trim($q['explanation'] ?? '');
        $qType = ($q['question_type'] ?? 'objective') === 'subjective' ? 'subjective' : 'objective';
        $correctAns = trim($q['correct_answer'] ?? '');
        $opts = $q['options'] ?? [];
        $normOpts = [];
        if ($qType === 'objective') {
            foreach ($opts as $opt) {
                $t = trim($opt['text'] ?? '');
                $optImage = $keptImage($opt['image_path'] ?? '');
                // A picture can be the whole option, so an empty text box is not
                // an empty option any more.
                if ($t === '' && $optImage === '') { continue; }
                $normOpts[] = [
                    'text' => $t,
                    'image_path' => $optImage,
                    'is_correct' => !empty($opt['is_correct']) ? 1 : 0,
                ];
            }
        }
        $normalized[] = [
            'question' => $qText,
            'explanation' => $qExpl,
            'question_type' => $qType,
            'correct_answer' => $qType === 'subjective' ? $correctAns : '',
            'image_path' => $keptImage($q['image_path'] ?? ''),
            'options' => $normOpts,
        ];
    }

    if (empty($errors)) {
        try {
            // Pictures already on file for this quiz, taken before the rows are
            // rewritten. Whatever is not referenced again afterwards is a file
            // the admin just replaced or deleted, and it goes.
            $beforeImages = [];
            if ($editingId) {
                $imgStmt = $pdo->prepare("SELECT q.image_path FROM quiz_questions q WHERE q.quiz_id = ?
                                          UNION ALL
                                          SELECT o.image_path FROM quiz_options o
                                          JOIN quiz_questions q ON q.id = o.question_id WHERE q.quiz_id = ?");
                $imgStmt->execute([$editingId, $editingId]);
                $beforeImages = array_filter(array_map('strval', $imgStmt->fetchAll(PDO::FETCH_COLUMN)));
            }

            if ($editingId) {
                $admin->updateQuiz($editingId, $payload);
                $admin->saveQuestions($editingId, $normalized);
                $quizIdForMaterials = $editingId;
                set_flash_message('success', 'Quiz updated successfully.');
            } else {
                $newId = $admin->createQuiz($payload);
                $admin->saveQuestions($newId, $normalized);
                $quizIdForMaterials = $newId;
                set_flash_message('success', 'Quiz created successfully.');
                $editingId = $newId;
            }

            if ($beforeImages) {
                // Anything no row anywhere points at now is a picture this save
                // replaced or deleted. Checked across the whole library rather
                // than just this quiz, because two questions are allowed to share
                // one picture and the other one is not ours to throw away.
                $mineStmt = $pdo->prepare("SELECT image_path FROM quiz_questions WHERE quiz_id = ? AND image_path IS NOT NULL AND image_path <> ''
                                           UNION
                                           SELECT o.image_path FROM quiz_options o
                                           JOIN quiz_questions q ON q.id = o.question_id
                                           WHERE q.quiz_id = ? AND o.image_path IS NOT NULL AND o.image_path <> ''");
                $mineStmt->execute([$editingId, $editingId]);
                $mineImages = array_filter(array_map('strval', $mineStmt->fetchAll(PDO::FETCH_COLUMN)));

                $stillUsed = [];
                $usedStmt = $pdo->query("SELECT image_path FROM quiz_questions WHERE image_path IS NOT NULL AND image_path <> ''
                                        UNION
                                        SELECT image_path FROM quiz_options WHERE image_path IS NOT NULL AND image_path <> ''");
                if ($usedStmt) {
                    foreach (array_map('strval', $usedStmt->fetchAll(PDO::FETCH_COLUMN)) as $keep) {
                        if ($keep !== '') { $stillUsed[$keep] = true; }
                    }
                }

                // The library-wide read has to agree with what this very quiz just
                // saved. If it does not, the read is broken rather than empty, and
                // guessing would delete pictures students are looking at.
                $libraryReadOk = true;
                foreach ($mineImages as $mine) {
                    if (!isset($stillUsed[$mine])) { $libraryReadOk = false; break; }
                }

                if ($libraryReadOk) {
                    foreach ($beforeImages as $gone) {
                        if (isset($stillUsed[$gone]) || strpos($gone, 'assets/uploads/quiz_images/') !== 0) { continue; }
                        $file = ROOT_PATH . '/' . $gone;
                        if (is_file($file)) { @unlink($file); }
                    }
                }
            }

            // Save attached materials (sync: delete old, insert new)
            $selectedMaterials = $_POST['quiz_materials'] ?? [];
            $pdo->prepare("DELETE FROM quiz_materials WHERE quiz_id = ?")->execute([$quizIdForMaterials]);
            if (!empty($selectedMaterials)) {
                $insStmt = $pdo->prepare("INSERT INTO quiz_materials (quiz_id, material_id) VALUES (?, ?)");
                foreach ($selectedMaterials as $matId) {
                    $matId = (int)$matId;
                    if ($matId > 0) {
                        $insStmt->execute([$quizIdForMaterials, $matId]);
                    }
                }
            }

            // Handle manually uploaded files
            if (!empty($_FILES['quiz_upload_materials']['name'][0])) {
                $uploadDir = ROOT_PATH . '/assets/uploads/materials/';
                if (!file_exists($uploadDir)) { mkdir($uploadDir, 0777, true); }
                $insMatStmt = $pdo->prepare("INSERT INTO materials (title, file_path, file_size, mime_type, original_name, uploader_id, status, visibility, level) VALUES (?, ?, ?, ?, ?, ?, 'approved', 'public', 'Secondary')");
                $linkStmt = $pdo->prepare("INSERT INTO quiz_materials (quiz_id, material_id) VALUES (?, ?)");
                
                foreach ($_FILES['quiz_upload_materials']['name'] as $idx => $origName) {
                    if ($_FILES['quiz_upload_materials']['error'][$idx] !== UPLOAD_ERR_OK) continue;
                    $origName = basename($origName);
                    $tmpPath = $_FILES['quiz_upload_materials']['tmp_name'][$idx];
                    $fileSize = $_FILES['quiz_upload_materials']['size'][$idx];
                    $mimeType = $_FILES['quiz_upload_materials']['type'][$idx];
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $newName = uniqid() . '.' . $ext;
                    $destPath = $uploadDir . $newName;
                    
                    if (move_uploaded_file($tmpPath, $destPath)) {
                        $title = pathinfo($origName, PATHINFO_FILENAME);
                        $relPath = 'assets/uploads/materials/' . $newName;
                        $insMatStmt->execute([$title, $relPath, $fileSize, $mimeType, $origName, $_SESSION['user_id']]);
                        $newMatId = $pdo->lastInsertId();
                        $linkStmt->execute([$quizIdForMaterials, $newMatId]);
                    }
                }
            }

            redirect(SITE_URL . '/admin/quizzes.php');
        } catch (Throwable $e) {
            log_error('Quiz save failed', ['error'=>$e->getMessage()]);
            $errors[] = 'Could not save quiz. Please try again.';
        }
    }

    if (!empty($errors)) {
        // Repopulate form with submitted data so admin doesn't lose progress
        $quiz = array_merge((array)$quiz, $payload);
        $quiz['questions'] = $normalized;
    }
}

$page_title = $editingId ? 'Edit Quiz' : 'New Quiz';
include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
  <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h4 mb-0"><?php echo htmlspecialchars($page_title); ?></h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo SITE_URL; ?>/admin/quizzes.php">Back</a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" id="quizErrors" style="border-radius: var(--radius-sm);">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <strong>Please fix the following:</strong><br>
      <?php echo htmlspecialchars(implode(' | ', $errors)); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <script>document.getElementById('quizErrors').scrollIntoView({behavior:'smooth'});</script>
  <?php endif; ?>

  <style>
    .qimg-thumb { max-width: 100%; max-height: 170px; object-fit: contain; background: #f8fafc; }
    .qimg-compact .qimg-thumb { max-height: 84px; }
    .qimg-compact .btn-quiz-image-label { font-size: .78rem; }
  </style>
  <form method="post" action="" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

    <div class="card mb-3">
      <div class="card-header bg-white"><strong>Quiz Details</strong></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Title</label>
            <input type="text" class="form-control" name="title" required value="<?php echo htmlspecialchars($quiz['title'] ?? ''); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Subject</label>
            <input type="text" class="form-control" name="subject" value="<?php echo htmlspecialchars($quiz['subject'] ?? ''); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Target Students</label>
            <select class="form-select" name="type" required>
              <option value="secondary" <?php echo (($quiz['type'] ?? '')==='secondary')?'selected':''; ?>>Secondary Only</option>
              <option value="university" <?php echo (($quiz['type'] ?? '')==='university')?'selected':''; ?>>University Only</option>
              <option value="both" <?php echo (($quiz['type'] ?? '')==='both')?'selected':''; ?>>General (Secondary & University)</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Duration (seconds)</label>
            <input type="number" class="form-control" min="60" step="30" name="duration_seconds" value="<?php echo (int)($quiz['duration_seconds'] ?? 900); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Mode</label>
            <?php $mode = $quiz['mode'] ?? 'normal'; ?>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="mode" id="modeNormal" value="normal" <?php echo ($mode==='normal')?'checked':''; ?>>
              <label class="btn btn-outline-secondary" for="modeNormal">Normal</label>
              <input type="radio" class="btn-check" name="mode" id="modeChallenge" value="challenge" <?php echo ($mode==='challenge')?'checked':''; ?>>
              <label class="btn btn-outline-secondary" for="modeChallenge">Challenge</label>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Slug</label>
            <input type="text" class="form-control" name="slug" value="<?php echo htmlspecialchars($quiz['slug'] ?? ''); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Required Level <small class="text-muted fw-normal">(Optional Lock)</small></label>
            <select class="form-select" name="required_level">
              <option value="0" <?php echo empty($quiz['required_level']) ? 'selected' : ''; ?>>None (Open to All)</option>
              <?php for($i=1; $i<=30; $i++): ?>
                <option value="<?php echo $i; ?>" <?php echo ((int)($quiz['required_level']??0)===$i)?'selected':''; ?>>Level <?php echo $i; ?>+</option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-12" id="examTypesWrap" style="display: <?php echo in_array($quiz['type'] ?? '', ['secondary', 'both']) || !isset($quiz['type']) ? '' : 'none'; ?>;">
            <label class="form-label">Applicable Exams (Secondary Only)</label>
            <?php
            $et = array_key_exists('exam_types', (array)$quiz) ? $quiz['exam_types'] : 'WAEC,NECO,JAMB';
            if ($et === null) $et = 'WAEC,NECO,JAMB';
            $etArr = $et === '' ? [] : explode(',', $et);
            ?>
            <div class="d-flex gap-4 mt-1">
              <div class="form-check custom-checkbox">
                <input class="form-check-input" type="checkbox" name="exam_types[]" value="WAEC" id="etWaec" <?php echo in_array('WAEC', $etArr)?'checked':''; ?>>
                <label class="form-check-label fw-bold" for="etWaec" style="color:#059669;">WAEC</label>
              </div>
              <div class="form-check custom-checkbox">
                <input class="form-check-input" type="checkbox" name="exam_types[]" value="NECO" id="etNeco" <?php echo in_array('NECO', $etArr)?'checked':''; ?>>
                <label class="form-check-label fw-bold" for="etNeco" style="color:#3b82f6;">NECO</label>
              </div>
              <div class="form-check custom-checkbox">
                <input class="form-check-input" type="checkbox" name="exam_types[]" value="JAMB" id="etJamb" <?php echo in_array('JAMB', $etArr)?'checked':''; ?>>
                <label class="form-check-label fw-bold" for="etJamb" style="color:#8b5cf6;">JAMB</label>
              </div>
            </div>
            <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Select the specific secondary school exams this assessment targets. Display defaults to all if none selected.</small>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea class="form-control" rows="3" name="description"><?php echo htmlspecialchars($quiz['description'] ?? ''); ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3" id="challengeFields" style="display: <?php echo (($quiz['mode'] ?? 'normal')==='challenge')?'':'none'; ?>;">
      <div class="card-header bg-white"><strong>Challenge Settings</strong></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Event Start (local)</label>
            <input type="datetime-local" class="form-control" name="event_start" value="<?php echo isset($quiz['event_start']) && $quiz['event_start'] ? date('Y-m-d\TH:i', strtotime($quiz['event_start'])) : ''; ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Event End (local)</label>
            <input type="datetime-local" class="form-control" name="event_end" value="<?php echo isset($quiz['event_end']) && $quiz['event_end'] ? date('Y-m-d\TH:i', strtotime($quiz['event_end'])) : ''; ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Prize Type</label>
            <?php $pt = $quiz['prize_type'] ?? ''; ?>
            <select class="form-select" name="prize_type" id="prizeType">
              <option value="">None</option>
              <option value="money" <?php echo ($pt==='money')?'selected':''; ?>>Money (NGN)</option>
              <option value="airtime" <?php echo ($pt==='airtime')?'selected':''; ?>>Airtime (NGN)</option>
              <option value="data" <?php echo ($pt==='data')?'selected':''; ?>>Data Bundle</option>
              <option value="text" <?php echo ($pt==='text')?'selected':''; ?>>Text</option>
            </select>
          </div>
          <div class="col-md-3" id="moneyWrap" style="display: <?php echo (in_array($quiz['prize_type'] ?? '', ['money','airtime','data']))?'':'none'; ?>;">
            <label class="form-label" id="prizeAmountLabel">Prize Amount / Value</label>
            <input type="number" class="form-control" step="0.01" min="0" name="prize_amount" value="<?php echo htmlspecialchars($quiz['prize_amount'] ?? ''); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Max Participants</label>
            <input type="number" class="form-control" name="max_participants" min="1" placeholder="Unlimited" value="<?php echo htmlspecialchars($quiz['max_participants'] ?? ''); ?>">
          </div>
          <div class="col-12" id="textWrap" style="display: <?php echo (($quiz['prize_type'] ?? '')==='text')?'':'none'; ?>;">
            <label class="form-label">Prize Text</label>
            <input type="text" class="form-control" name="prize_text" placeholder="e.g., Top 3 get Free Data" value="<?php echo htmlspecialchars($quiz['prize_text'] ?? ''); ?>">
          </div>
          
          <!-- Paid Quiz Section -->
          <div class="col-12 mt-3">
            <div class="p-3 rounded" style="background: rgba(16,185,129,0.05); border: 1px solid rgba(16,185,129,0.15);">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="isPaid" name="is_paid" value="1" <?php echo !empty($quiz['is_paid']) ? 'checked' : ''; ?>>
                <label class="form-check-label fw-bold" for="isPaid"><i class="fas fa-lock me-1"></i> Requires Entry Fee to Join</label>
              </div>
              <div id="entryFeeWrap" style="display: <?php echo !empty($quiz['is_paid']) ? '' : 'none'; ?>;">
                <div class="row g-2 mt-1">
                  <div class="col-md-6">
                    <label class="form-label">Entry Fee (₦)</label>
                    <input type="number" class="form-control" name="entry_fee" step="0.01" min="0" placeholder="e.g. 500" value="<?php echo htmlspecialchars($quiz['entry_fee'] ?? ''); ?>">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Study Materials Attachment -->
          <div class="col-12 mt-3">
            <div class="p-3 rounded" style="background: rgba(59,130,246,0.05); border: 1px solid rgba(59,130,246,0.15);">
              <label class="form-label fw-bold"><i class="fas fa-book-reader me-1 text-primary"></i> Attach Study Materials <span class="text-muted fw-normal">(optional)</span></label>
              <p class="text-muted small mb-2">Select materials that users should study before this quiz. They'll be shown to users while the event is upcoming.</p>
              <?php if (!empty($allMaterials)): ?>
              <select class="form-select" name="quiz_materials[]" id="quizMaterials" multiple size="5" style="min-height: 120px;">
                <?php foreach ($allMaterials as $mat): ?>
                  <option value="<?php echo (int)$mat['id']; ?>" <?php echo in_array($mat['id'], $attachedMaterialIds) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($mat['title']); ?>
                    <?php if (!empty($mat['original_name'])): ?>
                      (<?php echo htmlspecialchars($mat['original_name']); ?>)
                    <?php endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted mt-1 d-block"><i class="fas fa-info-circle me-1"></i> Hold <kbd>Ctrl</kbd> (or <kbd>Cmd</kbd>) to select/deselect multiple materials.</small>
              <?php else: ?>
              <div class="text-muted small mb-2"><i class="fas fa-info-circle me-1"></i> No approved materials in the library yet.</div>
              <?php endif; ?>

              <hr class="my-3" style="border-color: rgba(59,130,246,0.2);">
              <label class="form-label fw-semibold"><i class="fas fa-upload me-1 text-primary"></i> Or Upload New Files</label>
              <input type="file" class="form-control" name="quiz_upload_materials[]" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.jpg,.jpeg,.png">
              <small class="text-muted d-block mt-1"><i class="fas fa-info-circle me-1"></i> Upload files directly. They'll be saved as materials and linked to this quiz automatically.</small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="card mb-3">
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
        <textarea class="form-control font-monospace" id="codeInput" rows="10" placeholder='{"title":"...", "type":"secondary", "duration_seconds":900, "questions":[{"question":"...","explanation":"...","options":[{"text":"A","is_correct":true}]}]}'></textarea>
        <div id="codeSample" class="mt-2" style="display:none;">
          <pre class="bg-light p-2 border rounded small"><code id="sampleJson"></code></pre>
        </div>
        <div id="codeConsole" class="mt-2 small" style="display:none;"></div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>Questions</strong>
        <button type="button" class="btn btn-sm btn-primary" id="btnAddQuestion"><i class="fas fa-plus me-1"></i> Add Question</button>
      </div>
      <div class="card-body">
        <div id="questionsContainer" class="d-flex flex-column gap-3">
          <?php 
          $qs = $quiz['questions'] ?? [];
          if (!empty($qs)):
            foreach ($qs as $idx=>$q): ?>
              <div class="question-item border rounded p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <strong>Question <?php echo (int)($idx+1); ?></strong>
                  <button type="button" class="btn btn-sm btn-outline-danger btnRemoveQuestion">Remove</button>
                </div>
                <?php $qType = $q['question_type'] ?? 'objective'; ?>
                <div class="mb-2">
                  <label class="form-label">Type</label>
                  <div class="btn-group btn-group-sm w-100" role="group">
                    <input type="radio" class="btn-check" name="questions[<?php echo $idx; ?>][question_type]" id="qt<?php echo $idx; ?>_obj" value="objective" <?php echo $qType !== 'subjective' ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-secondary" for="qt<?php echo $idx; ?>_obj">Objective (MCQ)</label>
                    <input type="radio" class="btn-check" name="questions[<?php echo $idx; ?>][question_type]" id="qt<?php echo $idx; ?>_sub" value="subjective" <?php echo $qType === 'subjective' ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-secondary" for="qt<?php echo $idx; ?>_sub">Subjective</label>
                  </div>
                </div>
                <div class="mb-2">
                  <label class="form-label">Text</label>
                  <textarea class="form-control" name="questions[<?php echo $idx; ?>][question]" rows="2" required><?php echo htmlspecialchars($q['question']); ?></textarea>
                </div>
                <?php echo quiz_image_field('questions[' . (int)$idx . '][image_path]', $q['image_path'] ?? ''); ?>
                <div class="mb-2">
                  <label class="form-label">Explanation (optional)</label>
                  <textarea class="form-control" name="questions[<?php echo $idx; ?>][explanation]" rows="2"><?php echo htmlspecialchars($q['explanation'] ?? ''); ?></textarea>
                </div>
                <div class="subjective-fields" style="display: <?php echo $qType === 'subjective' ? '' : 'none'; ?>;">
                  <label class="form-label">Correct Answer <small class="text-muted">(case-insensitive)</small></label>
                  <input type="text" class="form-control" name="questions[<?php echo $idx; ?>][correct_answer]" value="<?php echo htmlspecialchars($q['correct_answer'] ?? ''); ?>" placeholder="e.g. Mitochondria">
                </div>
                <div class="options-list" style="display: <?php echo $qType === 'subjective' ? 'none' : ''; ?>;">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold">Options</span>
                    <button type="button" class="btn btn-sm btn-outline-primary btnAddOption">Add Option</button>
                  </div>
                  <?php $opts = $q['options'] ?? []; $o=0; foreach ($opts as $opt): ?>
                    <div class="row g-2 align-items-center option-item mb-2">
                      <div class="col-md-8">
                        <input type="text" class="form-control" name="questions[<?php echo $idx; ?>][options][<?php echo $o; ?>][text]" value="<?php echo htmlspecialchars($opt['option_text'] ?? $opt['text'] ?? ''); ?>" placeholder="Option text">
                      </div>
                      <div class="col-md-3">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" name="questions[<?php echo $idx; ?>][options][<?php echo $o; ?>][is_correct]" <?php echo !empty($opt['is_correct'])?'checked':''; ?>>
                          <label class="form-check-label">Correct</label>
                        </div>
                      </div>
                      <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger btnRemoveOption">×</button>
                      </div>
                      <div class="col-12 ps-md-4">
                        <?php echo quiz_image_field('questions[' . (int)$idx . '][options][' . (int)$o . '][image_path]', $opt['image_path'] ?? '', true); ?>
                      </div>
                    </div>
                  <?php $o++; endforeach; ?>
                </div>
              </div>
            <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
      <button type="submit" class="btn btn-primary">Save</button>
      <a class="btn btn-outline-secondary" href="<?php echo SITE_URL; ?>/admin/quizzes.php">Cancel</a>
    </div>
  </form>
</main>

<script>
(function(){
  var qContainer = document.getElementById('questionsContainer');
  var addQBtn = document.getElementById('btnAddQuestion');
  var bmManual = document.getElementById('bmManual');
  var bmCode = document.getElementById('bmCode');
  var codeBuilder = document.getElementById('codeBuilder');
  var codeInput = document.getElementById('codeInput');
  var codeConsole = document.getElementById('codeConsole');
  var codeSampleWrap = document.getElementById('codeSample');
  var sampleJsonEl = document.getElementById('sampleJson');
  var btnShowSample = document.getElementById('btnShowSample');
  var btnLoadFromCode = document.getElementById('btnLoadFromCode');

  // Target Students -> Exam Types toggle
  function updateExamTypes(){
    var typeSelector = document.querySelector('select[name="type"]');
    var etWrap = document.getElementById('examTypesWrap');
    if (typeSelector && etWrap) {
        var val = typeSelector.value;
        etWrap.style.display = (val === 'secondary' || val === 'both') ? '' : 'none';
    }
  }
  var typeSelTrigger = document.querySelector('select[name="type"]');
  typeSelTrigger && typeSelTrigger.addEventListener('change', updateExamTypes);
  updateExamTypes();

  // Mode toggle handlers
  function updateMode(){
    var isCh = document.getElementById('modeChallenge') && document.getElementById('modeChallenge').checked;
    var ch = document.getElementById('challengeFields');
    if (ch) { ch.style.display = isCh ? '' : 'none'; }
  }
  var modeNormal = document.getElementById('modeNormal');
  var modeChallenge = document.getElementById('modeChallenge');
  modeNormal && modeNormal.addEventListener('change', updateMode);
  modeChallenge && modeChallenge.addEventListener('change', updateMode);
  updateMode();

  // Prize type toggle
  function updatePrize(){
    var t = document.getElementById('prizeType');
    var mw = document.getElementById('moneyWrap');
    var tw = document.getElementById('textWrap');
    var al = document.getElementById('prizeAmountLabel');
    if (!t) return;
    var v = t.value;
    if (mw) mw.style.display = (v === 'money' || v === 'airtime' || v === 'data') ? '' : 'none';
    if (tw) tw.style.display = (v === 'text') ? '' : 'none';
    if (al) al.textContent = (v === 'data') ? 'Data Size (GB)' : 'Prize Amount (₦)';
  }
  var prizeTypeEl = document.getElementById('prizeType');
  prizeTypeEl && prizeTypeEl.addEventListener('change', updatePrize);
  updatePrize();

  // Paid quiz toggle
  var isPaidEl = document.getElementById('isPaid');
  var entryFeeWrap = document.getElementById('entryFeeWrap');
  function updatePaid(){
    if (isPaidEl && entryFeeWrap) { entryFeeWrap.style.display = isPaidEl.checked ? '' : 'none'; }
  }
  isPaidEl && isPaidEl.addEventListener('change', updatePaid);
  updatePaid();
  function qimgMarkup(name, path, compact){
    path = path || '';
    var shown = path !== '';
    return '<div class="qimg-field' + (compact ? ' qimg-compact mt-1' : ' mb-2') + '" data-qimg>' +
      '<input type="hidden" name="' + name + '" value="' + path + '">' +
      '<div class="d-flex align-items-center gap-2 flex-wrap">' +
        '<button type="button" class="btn btn-sm btn-outline-primary btnQuizImage"><i class="fas fa-image me-1"></i>' + (shown ? 'Change picture' : 'Add picture') + '</button>' +
        '<button type="button" class="btn btn-sm btn-outline-danger btnQuizImageRemove"' + (shown ? '' : ' style="display:none;"') + '>Remove</button>' +
        '<span class="small text-muted btnQuizImageStatus" style="display:none;">Uploading&hellip;</span>' +
      '</div>' +
      '<img class="qimg-thumb rounded border mt-2' + (shown ? '' : ' d-none') + '"' + (shown ? ' src="' + imageBase + path + '"' : '') + ' alt="Attached picture">' +
    '</div>';
  }

  // One hidden file box for the whole form. Every "Add picture" button borrows
  // it, so a hundred-row quiz never posts a hundred file inputs - and each
  // picture is stored the moment it is picked, where the admin can see it.
  var imageEndpoint = <?php echo json_encode(SITE_URL . '/admin/ajax/quiz_image.php'); ?>;
  var imageBase = <?php echo json_encode(SITE_URL . '/'); ?>;
  var csrfField = document.querySelector('input[name="csrf_token"]');
  var picker = document.createElement('input');
  picker.type = 'file';
  picker.accept = 'image/jpeg,image/png,image/gif,image/webp';
  picker.style.display = 'none';
  document.body.appendChild(picker);
  var picking = null;

  function qimgSet(field, path, label){
    var thumb = field.querySelector('.qimg-thumb');
    var hideBtn = field.querySelector('.btnQuizImageRemove');
    var addBtn = field.querySelector('.btnQuizImage');
    field.querySelector('input[type="hidden"]').value = path || '';
    if (path) {
      thumb.setAttribute('src', imageBase + path);
      thumb.classList.remove('d-none');
      hideBtn.style.display = '';
    } else {
      thumb.removeAttribute('src');
      thumb.classList.add('d-none');
      hideBtn.style.display = 'none';
    }
    addBtn.innerHTML = '<i class="fas fa-image me-1"></i>' + (path ? 'Change picture' : 'Add picture');
    if (label) { qimgStatus(field, label, true); }
  }

  function qimgStatus(field, msg, isError){
    var el = field.querySelector('.btnQuizImageStatus');
    el.textContent = msg;
    el.style.display = msg ? '' : 'none';
    el.className = 'small btnQuizImageStatus ' + (isError ? 'text-danger' : 'text-muted');
  }

  function sendQuizImage(field, file){
    qimgStatus(field, 'Uploading…', false);
    var body = new FormData();
    body.append('image', file, file.name);
    if (csrfField) { body.append('csrf_token', csrfField.value); }
    fetch(imageEndpoint, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data && data.ok) {
          qimgSet(field, data.path);
        } else {
          qimgStatus(field, (data && data.error) || 'Upload failed. Please try again.', true);
        }
      })
      .catch(function(){ qimgStatus(field, 'Upload failed. Check your connection and try again.', true); });
  }

  picker.addEventListener('change', function(){
    var file = picker.files && picker.files[0];
    var field = picking;
    picking = null;
    if (!field || !file) return;
    if (file.size > 3 * 1024 * 1024) { qimgStatus(field, 'That picture is over 3MB. Pick a smaller one.', true); return; }
    sendQuizImage(field, file);
  });

  function newOptionRow(qIdx, optIdx){
    var wrap = document.createElement('div');
    wrap.className = 'row g-2 align-items-center option-item mb-2';
    wrap.innerHTML = '\
      <div class="col-md-8">\
        <input type="text" class="form-control" name="questions['+qIdx+'][options]['+optIdx+'][text]" placeholder="Option text">\
      </div>\
      <div class="col-md-3">\
        <div class="form-check">\
          <input class="form-check-input" type="checkbox" name="questions['+qIdx+'][options]['+optIdx+'][is_correct]">\
          <label class="form-check-label">Correct</label>\
        </div>\
      </div>\
      <div class="col-md-1 text-end">\
        <button type="button" class="btn btn-sm btn-outline-danger btnRemoveOption">×</button>\
      </div>\
      <div class="col-12 ps-md-4">' + qimgMarkup('questions['+qIdx+'][options]['+optIdx+'][image_path]', '', true) + '</div>';
    return wrap;
  }

  function newQuestionItem(idx){
    var div = document.createElement('div');
    div.className = 'question-item border rounded p-3';
    div.innerHTML = '\
      <div class="d-flex justify-content-between align-items-start mb-2">\
        <strong>Question '+(idx+1)+'</strong>\
        <button type="button" class="btn btn-sm btn-outline-danger btnRemoveQuestion">Remove</button>\
      </div>\
      <div class="mb-2">\
        <label class="form-label">Type</label>\
        <div class="btn-group btn-group-sm w-100" role="group">\
          <input type="radio" class="btn-check qtTypeToggle" name="questions['+idx+'][question_type]" id="qt'+idx+'_obj" value="objective" checked>\
          <label class="btn btn-outline-secondary" for="qt'+idx+'_obj">Objective (MCQ)</label>\
          <input type="radio" class="btn-check qtTypeToggle" name="questions['+idx+'][question_type]" id="qt'+idx+'_sub" value="subjective">\
          <label class="btn btn-outline-secondary" for="qt'+idx+'_sub">Subjective</label>\
        </div>\
      </div>\
      <div class="mb-2">\
        <label class="form-label">Text</label>\
        <textarea class="form-control" name="questions['+idx+'][question]" rows="2" required></textarea>\
      </div>\
      ' + qimgMarkup('questions['+idx+'][image_path]', '') + '\
      <div class="mb-2">\
        <label class="form-label">Explanation (optional)</label>\
        <textarea class="form-control" name="questions['+idx+'][explanation]" rows="2"></textarea>\
      </div>\
      <div class="subjective-fields" style="display:none;">\
        <label class="form-label">Correct Answer <small class="text-muted">(case-insensitive)</small></label>\
        <input type="text" class="form-control" name="questions['+idx+'][correct_answer]" placeholder="e.g. Mitochondria">\
      </div>\
      <div class="options-list">\
        <div class="d-flex justify-content-between align-items-center mb-2">\
          <span class="fw-semibold">Options</span>\
          <button type="button" class="btn btn-sm btn-outline-primary btnAddOption">Add Option</button>\
        </div>\
      </div>';
    return div;
  }

  function reindexQuestions(){
    var items = qContainer.querySelectorAll('.question-item');
    items.forEach(function(item, idx){
      item.querySelector('strong').textContent = 'Question ' + (idx+1);
      // Reindex names
      item.querySelectorAll('[name]').forEach(function(input){
        input.name = input.name.replace(/questions\[[0-9]+\]/, 'questions['+idx+']');
      });
    });
  }

  function setConsole(msg, isError){
    codeConsole.style.display = 'block';
    codeConsole.style.whiteSpace = 'pre-wrap';
    codeConsole.style.border = '1px solid ' + (isError ? '#f5c2c7' : '#cfe2ff');
    codeConsole.style.background = isError ? '#f8d7da' : '#e7f1ff';
    codeConsole.style.color = isError ? '#842029' : '#084298';
    codeConsole.style.padding = '8px';
    codeConsole.style.borderRadius = '6px';
    codeConsole.textContent = msg;
  }

  function clearConsole(){ codeConsole.style.display = 'none'; codeConsole.textContent=''; }

  function loadFromObject(obj){
    // Validate minimal fields
    var required = ['title','type','duration_seconds'];
    var missing = required.filter(function(k){ return !(k in obj); });
    if (missing.length){ throw new Error('Missing required fields: ' + missing.join(', ')); }
    if (['secondary','university','both'].indexOf(obj.type) === -1){ throw new Error('type must be secondary, university, or both'); }

    // Map quiz fields
    document.querySelector('input[name="title"]').value = obj.title || '';
    document.querySelector('input[name="subject"]').value = obj.subject || '';
    document.querySelector('select[name="type"]').value = obj.type || '';
    document.querySelector('input[name="duration_seconds"]').value = parseInt(obj.duration_seconds || 900, 10);
    document.querySelector('input[name="slug"]').value = obj.slug || '';
    document.querySelector('textarea[name="description"]').value = obj.description || '';

    // Questions
    qContainer.innerHTML = '';
    var qs = Array.isArray(obj.questions) ? obj.questions : [];
    qs.forEach(function(q, idx){
      var item = newQuestionItem(idx);
      qContainer.appendChild(item);
      item.querySelector('textarea[name="questions['+idx+'][question]"]').value = q.question || '';
      item.querySelector('textarea[name="questions['+idx+'][explanation]"]').value = q.explanation || '';
      
      // Handle question type
      var isSubjective = (q.question_type === 'subjective');
      var objRadio = item.querySelector('#qt'+idx+'_obj');
      var subRadio = item.querySelector('#qt'+idx+'_sub');
      if (isSubjective && subRadio) {
        subRadio.checked = true;
        objRadio.checked = false;
        var sf = item.querySelector('.subjective-fields');
        var ol = item.querySelector('.options-list');
        if (sf) sf.style.display = '';
        if (ol) ol.style.display = 'none';
        var caInput = item.querySelector('input[name="questions['+idx+'][correct_answer]"]');
        if (caInput) caInput.value = q.correct_answer || '';
      } else {
        var list = item.querySelector('.options-list');
        var opts = Array.isArray(q.options) ? q.options : [];
        opts.forEach(function(opt, oi){
          var row = newOptionRow(idx, oi);
          row.querySelector('input[type="text"]').value = opt.text || '';
          row.querySelector('input[type="checkbox"]').checked = !!opt.is_correct;
          list.appendChild(row);
        });
      }
    });
    if (!qs.length){ // ensure at least one blank question exists
      qContainer.appendChild(newQuestionItem(0));
    }
  }

  function getSample(){
    return JSON.stringify({
      title: 'Sample Biology Quiz',
      subject: 'Biology',
      type: 'secondary',
      duration_seconds: 900,
      is_prize: false,
      slug: 'biology-sample',
      description: 'Basic biology practice questions.',
      questions: [
        { question: 'What is the powerhouse of the cell?', explanation: 'Mitochondria generate ATP.', question_type: 'objective', options: [
          { text: 'Nucleus', is_correct: false },
          { text: 'Mitochondria', is_correct: true },
          { text: 'Ribosome', is_correct: false },
          { text: 'Golgi apparatus', is_correct: false }
        ]},
        { question: 'What gas do plants release during photosynthesis?', explanation: 'Plants release oxygen.', question_type: 'subjective', correct_answer: 'Oxygen' },
        { question: 'DNA stands for?', explanation: '', question_type: 'objective', options: [
          { text: 'Deoxyribonucleic Acid', is_correct: true },
          { text: 'Deoxyribose Acid', is_correct: false }
        ]}
      ]
    }, null, 2);
  }

  // Build method toggle
  function updateBuildMethod(){
    var code = bmCode.checked;
    codeBuilder.style.display = code ? '' : 'none';
  }
  bmManual && bmManual.addEventListener('change', updateBuildMethod);
  bmCode && bmCode.addEventListener('change', updateBuildMethod);
  updateBuildMethod();

  // Show sample
  btnShowSample && btnShowSample.addEventListener('click', function(){
    if (codeSampleWrap.style.display === 'none'){
      sampleJsonEl.textContent = getSample();
      codeSampleWrap.style.display = '';
    } else {
      codeSampleWrap.style.display = 'none';
    }
  });

  // Load data
  btnLoadFromCode && btnLoadFromCode.addEventListener('click', function(){
    clearConsole();
    var txt = codeInput.value.trim();
    if (!txt){ setConsole('Please paste JSON code to import.', true); return; }
    try {
      var obj = JSON.parse(txt);
      loadFromObject(obj);
      setConsole('Loaded successfully. Review and click Save to persist.', false);
    } catch (e) {
      setConsole('Parse/validation error: ' + e.message, true);
    }
  });
  addQBtn && addQBtn.addEventListener('click', function(){
    var idx = qContainer.querySelectorAll('.question-item').length;
    var item = newQuestionItem(idx);
    qContainer.appendChild(item);
  });

  qContainer && qContainer.addEventListener('click', function(e){
    if (e.target.classList.contains('btnAddOption')){
      var qi = e.target.closest('.question-item');
      var qIdxMatch = (qi.querySelector('[name^="questions["]').name.match(/questions\[([0-9]+)\]/) || [])[1];
      var optIdx = qi.querySelectorAll('.option-item').length;
      qi.querySelector('.options-list').appendChild(newOptionRow(qIdxMatch, optIdx));
    }
    if (e.target.classList.contains('btnRemoveQuestion')){
      var qi = e.target.closest('.question-item');
      qi.parentNode.removeChild(qi);
      reindexQuestions();
    }
    if (e.target.classList.contains('btnRemoveOption')){
      var oi = e.target.closest('.option-item');
      var list = oi.parentNode;
      oi.parentNode.removeChild(oi);
      // The slots are named by position, so a gap would make two rows share a
      // name and the later one silently overwrite the first on save.
      list.querySelectorAll('.option-item').forEach(function(row, oi2){
        row.querySelectorAll('[name]').forEach(function(input){
          input.name = input.name.replace(/options\[[0-9]+\]/, 'options['+oi2+']');
        });
      });
    }
    var imgBtn = e.target.closest && e.target.closest('.btnQuizImage');
    if (imgBtn) {
      picking = imgBtn.closest('.qimg-field');
      qimgStatus(picking, '', false);
      picker.value = '';
      picker.click();
    }
    var rmImgBtn = e.target.closest && e.target.closest('.btnQuizImageRemove');
    if (rmImgBtn) {
      // The file on the server stays until the save actually lands: this row
      // may still be pointing at it in the database until then.
      qimgSet(rmImgBtn.closest('.qimg-field'), '');
    }
  });

  // Question Type toggle handler (objective/subjective)
  qContainer && qContainer.addEventListener('change', function(e){
    if (e.target.classList.contains('qtTypeToggle') || e.target.classList.contains('btn-check')) {
      var qi = e.target.closest('.question-item');
      if (!qi) return;
      var checked = qi.querySelector('input[value="subjective"]:checked');
      var sf = qi.querySelector('.subjective-fields');
      var ol = qi.querySelector('.options-list');
      if (checked) {
        if (sf) sf.style.display = '';
        if (ol) ol.style.display = 'none';
      } else {
        if (sf) sf.style.display = 'none';
        if (ol) ol.style.display = '';
      }
    }
  });
})();
</script>
</main>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

