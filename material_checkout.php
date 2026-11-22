<?php
/**
 * Opens a checkout for a paid material: mints the reference, writes the open row and
 * hands the browser everything Paystack needs. The price is read from the database,
 * never from the request, so nobody can buy a ₦5,000 document for ₦100.
 */
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Material.php';
require_once __DIR__ . '/includes/materials_paid.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Your session expired. Please refresh the page and try again.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$materialId = (int)($_POST['material_id'] ?? 0);

$materialModel = new Material($pdo);
$material = $materialId > 0 ? $materialModel->getById($materialId) : null;

if (!$material || $material['status'] !== 'approved') {
    http_response_code(404);
    echo json_encode(['error' => 'That material is not available.']);
    exit;
}
if (!material_is_paid($material)) {
    echo json_encode(['error' => 'This material is free — no payment needed.']);
    exit;
}

// Checked before any payment row exists: with no key configured the browser would
// open a Paystack popup that cannot work, and the admin queue would fill with
// pending rows that no student could ever have completed.
if (trim((string)PAYSTACK_SECRET_KEY) === '' || trim((string)PAYSTACK_PUBLIC_KEY) === '') {
    http_response_code(503);
    echo json_encode(['error' => 'Payments are not configured on this site yet. Please check back soon.']);
    exit;
}

$payments = new MaterialPayment($pdo);
if ($payments->owns($userId, $materialId)) {
    echo json_encode([
        'already_owned' => true,
        'redirect' => SITE_URL . '/material.php?id=' . $materialId,
    ]);
    exit;
}

$price = (float)$material['price'];

// Where to send the receipt. The account address is used unless they give a valid one.
$email = trim((string)($_POST['email'] ?? ''));
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'That email address does not look right.']);
    exit;
}
if ($email === '') {
    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $email = trim((string)$stmt->fetchColumn());
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Add an email address so we can send you the file as well.']);
    exit;
}
$email = strtolower($email);

$ref = material_new_ref($userId, $materialId);
if (!$payments->open($userId, $materialId, $email, $price, $ref)) {
    http_response_code(500);
    echo json_encode(['error' => 'We could not start the payment. Please try again.']);
    exit;
}

// Also remembered in the session, so a reload of this browser recovers instantly.
if (!isset($_SESSION['paystack_pending_materials']) || !is_array($_SESSION['paystack_pending_materials'])) {
    $_SESSION['paystack_pending_materials'] = [];
}
foreach ($_SESSION['paystack_pending_materials'] as $oldRef => $ts) {
    if ((int)$ts < time() - 7200) {
        unset($_SESSION['paystack_pending_materials'][$oldRef]);
    }
}
$_SESSION['paystack_pending_materials'][$ref] = time();

echo json_encode([
    'ref' => $ref,
    'email' => $email,
    'amount' => (int)round($price * 100), // Paystack works in kobo
    'key' => PAYSTACK_PUBLIC_KEY,
    'metadata' => [
        'material_id' => $materialId,
        'user_id' => $userId,
        'material_title' => (string)$material['title'],
        'custom_fields' => [[
            'display_name' => 'Material ID',
            'variable_name' => 'material_id',
            'value' => (string)$materialId,
        ]],
    ],
    'redirect' => SITE_URL . '/material_verify.php?material_id=' . $materialId . '&reference=' . urlencode($ref),
]);
exit;
