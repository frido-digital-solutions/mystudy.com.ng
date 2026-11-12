<?php
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Payment.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$quizId = (int)($_POST['quiz_id'] ?? 0);

if ($quizId <= 0) {
    echo json_encode(['error' => 'Invalid quiz or amount.']);
    exit;
}

require_once __DIR__ . '/models/Quiz.php';
$quizModel = new Quiz($pdo);
$quiz = $quizModel->getById($quizId);

// The fee is whatever the quiz actually costs, never whatever the browser posts.
$expectedAmount = $quiz ? (float)($quiz['entry_fee'] ?? 0) : 0;
if ($expectedAmount <= 0) {
    echo json_encode(['error' => 'This quiz does not have an entry fee to pay.']);
    exit;
}

$amount = (float)($_POST['amount'] ?? 0);
if (abs($amount - $expectedAmount) > 0.01) {
    echo json_encode(['error' => 'The amount does not match this quiz entry fee of ₦' . number_format($expectedAmount, 2) . '.']);
    exit;
}
$amount = $expectedAmount;

$paymentModel = new Payment($pdo);
if ($paymentModel->getPendingPayment($userId, $quizId)) {
    echo json_encode(['error' => 'You already have a pending receipt under review.']);
    exit;
}

if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Please upload a valid receipt file.']);
    exit;
}

$file = $_FILES['receipt'];
$maxSize = 1 * 1024 * 1024; // 1MB max

if ($file['size'] > $maxSize) {
    echo json_encode(['error' => 'File size exceeds 1MB limit.']);
    exit;
}

$allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
if (!in_array($file['type'], $allowedMimeTypes)) {
    echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, and PDF are allowed.']);
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
// Allow safe extensions even if MIME type is spoofed
if (!in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'pdf'])) {
    echo json_encode(['error' => 'Invalid file extension.']);
    exit;
}

$uploadDir = UPLOAD_PATH . '/receipts/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$newFileName = 'rcpt_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destination = $uploadDir . $newFileName;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode(['error' => 'Failed to save the uploaded file.']);
    exit;
}

$relativeUrl = '/assets/uploads/receipts/' . $newFileName;
$txRef = 'MANUAL-' . $userId . '-' . time();

$paymentModel = new Payment($pdo);
$paymentModel->recordPayment($userId, $quizId, $amount, $txRef, 'pending', $relativeUrl);

echo json_encode([
    'success' => true,
    'message' => 'Receipt uploaded successfully. Your payment is pending verification.'
]);
