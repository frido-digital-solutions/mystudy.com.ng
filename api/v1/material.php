<?php
/**
 * One study material, for the app's detail sheet.
 *
 * A row in the list only carries an id and a title, so tapping it needs a second
 * call before the sheet can show a price, a size or how far the student got. This
 * is that call. It says nothing about the file itself beyond what the website
 * already shows: no path, no token. The app gets those from reader_ticket.php once
 * the account has proved it may read the file.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Material.php';
require_once __DIR__ . '/../../models/MaterialPayment.php';
require_once __DIR__ . '/../../includes/materials_paid.php';
require_once __DIR__ . '/../../includes/material_progress.php';

$user = api_auth();
$userId = (int)$user['id'];

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    api_err('bad_request', 'Name the material you want to see.');
}

$materialModel = new Material($pdo);
$material = $materialModel->getById($id);
if (!$material || ($material['status'] ?? '') !== 'approved') {
    api_err('not_found', 'That material is not available.', 404);
}

$payments = new MaterialPayment($pdo);
$mine = $payments->owns($userId, $id);

// A payment that landed while the checkout tab was closing is applied here, so the
// sheet never quotes a price the webhook has already been paid.
if (!$mine && material_is_paid($material)) {
    material_verify_open_payments($pdo, $userId, $material);
    $mine = $payments->owns($userId, $id);
}

$canRead = material_can_access($pdo, $userId, $material);

$uploadsRoot = rtrim(str_replace('\\', '/', ROOT_PATH), '/') . '/';
$relative = str_replace('\\', '/', (string)($material['file_path'] ?? ''));
$absolute = $uploadsRoot . ltrim($relative, '/');
$bytes = (int)($material['file_size'] ?? 0);
if ($bytes <= 0 && is_file($absolute)) {
    $bytes = (int)filesize($absolute);
}

$extension = strtoupper(pathinfo($relative, PATHINFO_EXTENSION));
$read = material_progress_for($pdo, $userId, [$id])[$id] ?? null;

$author = null;
try {
    $stmt = $pdo->prepare('SELECT name FROM users WHERE id = :u LIMIT 1');
    $stmt->execute([':u' => (int)$material['uploader_id']]);
    $author = $stmt->fetchColumn() ?: null;
} catch (Throwable $e) {
    $author = null;
}

$price = isset($material['price']) ? (float)$material['price'] : 0.0;

api_out([
    'ok' => true,
    'id' => $id,
    'title' => (string)$material['title'],
    'description' => trim(strip_tags((string)($material['description'] ?? ''))),
    'category' => $material['category_name'] ?? null,
    'level' => $material['level'] ?? null,
    'board' => $material['secondary_board'] ?? null,
    'author' => $author === null ? null : (string)$author,
    'downloads' => (int)($material['download_count'] ?? 0),
    'created_at' => $material['created_at'] ?? null,
    'file_name' => material_download_name($material),
    'format' => $extension === '' ? 'FILE' : $extension,
    'readable' => $extension === 'PDF',
    'size_bytes' => $bytes,
    'size_label' => $bytes > 0 ? ($bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int)round($bytes / 1024)) . ' KB') : '',
    'is_paid' => material_is_paid($material),
    'price' => $price,
    'price_label' => $price > 0 ? '₦' . number_format($price, 0) : 'Free',
    'owned' => $mine,
    // Exactly the rule reader_ticket.php and material_download.php will apply, so
    // a sheet cannot offer a button the file exit then refuses.
    'can_read' => $canRead,
    'percent_read' => $read === null ? null : (int)$read['percent'],
    'read_page' => $read === null ? 0 : (int)$read['page'],
    'read_pages' => $read === null ? 0 : (int)$read['pages'],
    // Where the website would show this file to a browser, for the sheet's
    // "Open on the website" fallback and for buying it.
    'page_url' => api_url('material.php?id=' . $id),
    'checkout_url' => api_url('material_checkout.php?id=' . $id),
]);
