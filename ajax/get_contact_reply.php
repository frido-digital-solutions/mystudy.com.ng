<?php
require_once __DIR__ . '/../config/config.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$messageId = (int)$_GET['id'];
$userId = (int)$_SESSION['user_id'];

try {
    // We need to fetch the email of the logged in user first
    $stmtUser = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();
    if (!$user) {
        throw new Exception("User not found");
    }

    $stmt = $pdo->prepare("SELECT id, subject, message, created_at, admin_reply, replied_at FROM contact_messages WHERE id = ? AND email = ?");
    $stmt->execute([$messageId, $user['email']]);
    $reply = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reply) {
        echo json_encode(['success' => true, 'data' => $reply]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Message not found or unauthorized']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
