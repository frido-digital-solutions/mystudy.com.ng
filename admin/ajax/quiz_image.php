<?php
/**
 * Stores one picture for a quiz question or a quiz option.
 *
 * The question editor sends each picture on its own, the moment it is chosen,
 * instead of putting a file box on every row of the form. Two reasons: a quiz
 * with thirty questions would carry a hundred and twenty file inputs in one
 * submit, and PHP quietly throws away everything past the twentieth; and an
 * admin who picks the wrong crop wants to see it on the row at once, not after
 * a save. The form only ever keeps the returned path in a hidden field.
 */
require_once __DIR__ . '/../../config/config.php';
require_admin();
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$out = ['ok' => false, 'error' => 'Upload failed.'];
http_response_code(200);

if (!admin_can('quizzes')) {
    echo json_encode(['ok' => false, 'error' => 'You cannot manage quizzes.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'This endpoint takes a POST.']);
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'error' => 'Your form session expired. Please reload the page and try again.']);
    exit;
}

$file = $_FILES['image'] ?? null;
if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['ok' => false, 'error' => 'No picture was chosen.']);
    exit;
}

$MAX_BYTES = 3 * 1024 * 1024;
$ALLOWED   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

switch ((int)$file['error']) {
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
        $out = ['ok' => false, 'error' => 'That picture is too large. Use one under 3MB.'];
        break;
    case UPLOAD_ERR_PARTIAL:
    case UPLOAD_ERR_NO_TMP_DIR:
    case UPLOAD_ERR_CANT_WRITE:
    case UPLOAD_ERR_EXTENSION:
        $out = ['ok' => false, 'error' => 'The upload was interrupted. Please try again.'];
        break;
    default:
        $out = null;
}
if ($out) {
    echo json_encode($out);
    exit;
}

$ext = strtolower(pathinfo(basename((string)$file['name']), PATHINFO_EXTENSION));
if (!in_array($ext, $ALLOWED, true)) {
    echo json_encode(['ok' => false, 'error' => 'Use a JPG, PNG, GIF or WEBP picture.']);
    exit;
}
if ((int)$file['size'] > $MAX_BYTES || (int)$file['size'] <= 0) {
    echo json_encode(['ok' => false, 'error' => 'That picture is too large. Use one under 3MB.']);
    exit;
}
// getimagesize only answers for real picture bytes, so a script wearing a
// .jpg name never reaches the folder that is readable by every student.
$info = @getimagesize($file['tmp_name']);
if ($info === false) {
    echo json_encode(['ok' => false, 'error' => 'That file is not a picture we can read.']);
    exit;
}

$dir = ROOT_PATH . '/assets/uploads/quiz_images/';
if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
    echo json_encode(['ok' => false, 'error' => 'The server could not make room for the picture.']);
    exit;
}

$filename = bin2hex(random_bytes(8)) . '.' . $ext;
$relative = 'assets/uploads/quiz_images/' . $filename;
if (!move_uploaded_file($file['tmp_name'], ROOT_PATH . '/' . $relative)) {
    echo json_encode(['ok' => false, 'error' => 'The picture could not be saved. Please try again.']);
    exit;
}
@chmod(ROOT_PATH . '/' . $relative, 0644);

echo json_encode([
    'ok'    => true,
    'path'  => $relative,
    'url'   => SITE_URL . '/' . $relative,
    'width' => (int)($info[0] ?? 0),
    'height'=> (int)($info[1] ?? 0),
]);
