<?php
/**
 * A short link the app can open inside itself.
 *
 * The phone has a bearer token, but the reader and the file streamer are website
 * pages that only understand a browser session. This endpoint is the bridge: it
 * checks the account may really read this file, then hands back two links that
 * carry a ten-minute ticket instead of the account's real token.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Material.php';
require_once __DIR__ . '/../../includes/materials_paid.php';
require_once __DIR__ . '/../../includes/app_ticket.php';

api_require_post();
$user = api_auth();
$userId = (int)$user['id'];

$id = (int)api_field(api_input(), 'id', (string)($_GET['id'] ?? 0));
if ($id <= 0) {
    api_err('bad_request', 'Name the material you want to read.');
}

$materialModel = new Material($pdo);
$material = $materialModel->getById($id);
if (!$material || ($material['status'] ?? '') !== 'approved') {
    api_err('not_found', 'That material is not available.', 404);
}

// A payment that landed while the checkout tab was closing is applied here, so
// the student who has just paid is not told the file is still locked.
if (material_is_paid($material)) {
    material_verify_open_payments($pdo, $userId, $material);
}
if (!material_can_access($pdo, $userId, $material)) {
    api_err('locked', 'This material is paid, and this account has not bought it yet.', 403, [
        'price' => (float)($material['price'] ?? 0),
        'material_id' => $id,
    ]);
}

$token = app_ticket_issue($userId, 'read:' . $id);
$tail = 'id=' . $id . '&t=' . rawurlencode($token);

api_out([
    'ok' => true,
    'ticket' => $token,
    'expires_in' => APP_TICKET_TTL,
    'reader_url' => api_url('reader.php?app=1&' . $tail),
    'file_url' => api_url('material_download.php?inline=1&' . $tail),
    'download_url' => api_url('material_download.php?' . $tail),
    'title' => (string)$material['title'],
    'file_name' => material_download_name($material),
    'size_bytes' => (int)($material['file_size'] ?? 0),
    'is_pdf' => strpos(strtolower((string)($material['mime_type'] ?? '')), 'application/pdf') === 0
        || strtolower(pathinfo((string)$material['file_path'], PATHINFO_EXTENSION)) === 'pdf',
]);
