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

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode(['success' => false, 'error' => 'Empty query.']);
    exit;
}

try {
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE name LIKE :q1 OR email LIKE :q2 ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([':q1' => $like, ':q2' => $like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['success' => true, 'results' => $rows]);
} catch (Throwable $e) {
    log_error('admin_user_search failed', ['error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => 'Server error.']);
}

