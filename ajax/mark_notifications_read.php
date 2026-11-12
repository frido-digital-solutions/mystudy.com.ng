<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated', 'auth' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $notifId = (int)$_POST['id'];
    $userId = (int)$_SESSION['user_id'];

    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$notifId, $userId])) {
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false]);
exit;
