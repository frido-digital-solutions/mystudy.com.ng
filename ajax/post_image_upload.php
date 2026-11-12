<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated.', 'auth' => false]);
    exit;
}

if (!has_role('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You do not have permission to do that.']);
    exit;
}

try {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        echo json_encode(['success' => false, 'error' => 'Invalid form token.']);
        exit;
    }

    if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
        echo json_encode(['success' => false, 'error' => 'No image uploaded.']);
        exit;
    }

    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Upload error.']);
        exit;
    }

    $mime = mime_content_type($file['tmp_name']);
    if (strpos($mime, 'image/') !== 0) {
        echo json_encode(['success' => false, 'error' => 'Only image uploads are allowed.']);
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
        echo json_encode(['success' => false, 'error' => 'Unsupported image type.']);
        exit;
    }

    $uploadDir = ROOT_PATH . '/assets/uploads/posts/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newName = uniqid('post_inline_', true) . '.' . $ext;
    $dest = $uploadDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save image.']);
        exit;
    }

    $relativePath = 'assets/uploads/posts/' . $newName;
    $url = SITE_URL . '/' . $relativePath;

    echo json_encode(['success' => true, 'url' => $url]);
} catch (Throwable $e) {
    log_error('Post image upload failed', ['error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => 'Unexpected error.']);
}

