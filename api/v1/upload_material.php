<?php
/**
 * Material submission for the app - the same door as upload-material.php.
 *
 * GET hands the form what it needs: the university list, the rules, whether
 * this account may put a price on a file (admins only, exactly like the web),
 * and the student's own past submissions with their review status.
 *
 * POST takes the same multipart shape the website form sends (file[] plus the
 * detail fields), applies the same limits - 5MB a file, 30MB a batch, the same
 * extensions - and files every upload as 'pending' for an admin to review.
 * Nothing here approves anything, and a student posting is_paid is ignored.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Material.php';
require_once __DIR__ . '/../../includes/materials_paid.php';

$user = api_auth();
$userId = (int)$user['id'];
$canSell = has_role('admin');
$materialModel = new Material($pdo);

$allowedExt = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
$maxPerFile = 5 * 1024 * 1024;
$maxTotal = 30 * 1024 * 1024;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $mine = [];
    try {
        $stmt = $pdo->prepare("SELECT id, title, status, level, secondary_board, is_paid, price, file_size, original_name, created_at, reviewed_at
                                 FROM materials WHERE uploader_id = ?
                                ORDER BY created_at DESC LIMIT 30");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $bytes = (int)($r['file_size'] ?? 0);
            $mine[] = [
                'id' => (int)$r['id'],
                'title' => (string)$r['title'],
                'status' => (string)$r['status'],
                'level' => $r['level'],
                'board' => $r['secondary_board'],
                'is_paid' => (int)$r['is_paid'] === 1,
                'price' => (float)($r['price'] ?? 0),
                'size_label' => $bytes > 0 ? ($bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int)round($bytes / 1024)) . ' KB') : '',
                'format' => strtoupper(pathinfo((string)($r['original_name'] ?? ''), PATHINFO_EXTENSION)),
                'created_at' => $r['created_at'],
                'reviewed_at' => $r['reviewed_at'],
            ];
        }
    } catch (Throwable $e) {
        error_log('api upload_material mine: ' . $e->getMessage());
    }

    api_out([
        'ok' => true,
        'can_sell' => $canSell,
        'universities' => array_map(fn($u) => ['id' => (int)$u['id'], 'name' => (string)$u['name'], 'acronym' => (string)$u['acronym']], $materialModel->listUniversities()),
        'boards' => ['General', 'WAEC', 'NECO', 'JAMB', 'GCE'],
        'limits' => [
            'per_file_bytes' => $maxPerFile,
            'total_bytes' => $maxTotal,
            'extensions' => $allowedExt,
        ],
        'mine' => $mine,
    ]);
}

api_require_post();

$title = api_field($_POST, 'title');
$description = api_field($_POST, 'description');
$level = api_field($_POST, 'level', 'Secondary') === 'University' ? 'University' : 'Secondary';
$secondaryBoard = in_array(api_field($_POST, 'secondary_board', 'General'), ['General', 'WAEC', 'NECO', 'JAMB', 'GCE'], true)
    ? api_field($_POST, 'secondary_board', 'General') : 'General';
$universityScope = api_field($_POST, 'university_scope', 'specific') === 'general' ? 'general' : 'specific';
$isUniversityGeneral = ($level === 'University' && $universityScope === 'general') ? 1 : 0;
$universityId = api_field($_POST, 'university_id') !== '' ? (int)$_POST['university_id'] : null;

// Only the platform owners can put a price on learning - a student sending
// is_paid=1 is ignored, exactly as the website form ignores it.
$isPaid = $canSell && !empty($_POST['is_paid']);
$price = 0.0;
if ($isPaid) {
    $price = (float)str_replace([',', ' ', "\xc2\xa0"], '', (string)($_POST['price'] ?? ''));
    if ($price < 50 || $price > 5000000) {
        api_err('bad_price', 'Enter a price between ₦50 and ₦5,000,000.');
    }
}

if (!isset($_FILES['file'])) {
    api_err('no_file', 'Please upload at least one file.');
}
$f = $_FILES['file'];
$files = [];
if (is_array($f['name'] ?? null)) {
    foreach ($f['name'] as $i => $name) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $files[] = ['name' => $name, 'tmp_name' => $f['tmp_name'][$i] ?? '', 'error' => $f['error'][$i] ?? UPLOAD_ERR_OK, 'size' => (int)($f['size'][$i] ?? 0)];
    }
} else {
    $files[] = $f;
}

if (!$files) {
    api_err('no_file', 'Please upload at least one file.');
}
$isBulk = count($files) > 1;
if ($isPaid && $isBulk) {
    api_err('paid_bulk', 'A paid material is one file at a time — the price belongs to a single document.');
}
if (!$isBulk && $title === '') {
    api_err('no_title', 'Title is required.');
}
if ($level === 'University' && !$isUniversityGeneral && empty($universityId)) {
    api_err('no_university', 'Please select a university (or choose University General).');
}
if ($universityId !== null) {
    $known = false;
    foreach ($materialModel->listUniversities() as $u) {
        if ((int)$u['id'] === $universityId) { $known = true; break; }
    }
    if (!$known) api_err('bad_university', 'That university is not on the list.');
}

$totalSize = 0;
foreach ($files as $file) {
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        api_err('upload_failed', 'One or more files failed to upload. Please try again.');
    }
    $size = (int)($file['size'] ?? 0);
    $totalSize += max(0, $size);
    if ($size <= 0 || $size > $maxPerFile) {
        api_err('file_too_big', 'Each file must be 5MB or less.');
    }
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, $allowedExt, true)) {
        api_err('bad_type', 'Invalid file type detected. Allowed: pdf, doc/docx, ppt/pptx, xls/xlsx, jpg/png.');
    }
}
if ($totalSize > $maxTotal) {
    api_err('batch_too_big', 'Total upload must be 30MB or less.');
}

// Paid files go where no URL can reach them; the download gateway is the only door.
$targetDir = $isPaid ? material_secure_base() : ROOT_PATH . '/assets/uploads/materials/';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$created = [];
foreach ($files as $file) {
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $targetDir . $safeName;

    // The API receives the file through PHP's own upload machinery, so the
    // same is_uploaded_file + move_uploaded_file pair the website uses applies.
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        api_err('save_failed', 'Failed to save one of the uploaded files.');
    }

    $relativePath = material_store_dir($isPaid) . '/' . $safeName;
    $mime = mime_content_type($dest) ?: null;
    $originalName = trim((string)$file['name']);

    $finalTitle = $isBulk ? preg_replace('/[\-_]+/', ' ', preg_replace('/\.[^.]+$/', '', $originalName)) : $title;
    $finalTitle = trim((string)$finalTitle);
    if ($finalTitle === '') $finalTitle = 'Study Material';

    try {
        $id = $materialModel->create([
            'title' => $finalTitle,
            'description' => $isBulk ? '' : $description,
            'category_id' => null,
            'file_path' => $relativePath,
            'file_size' => filesize($dest),
            'mime_type' => $mime,
            'uploader_id' => $userId,
            'visibility' => 'public',
            'status' => 'pending',
            'level' => $level,
            'secondary_board' => $secondaryBoard,
            'university_id' => $isUniversityGeneral ? null : $universityId,
            'is_university_general' => $level === 'University' ? $isUniversityGeneral : 0,
            'original_name' => $originalName,
            'is_paid' => $isPaid ? 1 : 0,
            'price' => $isPaid ? $price : null,
        ]);
        $created[] = ['id' => $id, 'title' => $finalTitle];
    } catch (Throwable $e) {
        error_log('api upload_material create: ' . $e->getMessage());
        @unlink($dest);
        api_err('save_failed', 'The upload could not be recorded. Please try again.');
    }
}

api_out([
    'ok' => true,
    'created' => count($created),
    'items' => $created,
    'message' => count($created) > 1
        ? count($created) . ' uploads received! Awaiting admin approval.'
        : 'Upload received! Awaiting admin approval.',
]);