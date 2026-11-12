<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid notification id']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
    $ok = $stmt->execute([':id' => $id, ':uid' => (int)$_SESSION['user_id']]);
    
    if ($ok) {
        unset($_SESSION['_cached_admin_notice']);
        unset($_SESSION['_last_admin_notice_check']);
    }
    
    echo json_encode(['success' => $ok]);
} catch (Throwable $e) {
    log_error('mark_admin_broadcast_read failed', ['error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

