<?php
/**
 * The only way a material file leaves the server. Free files behave exactly as
 * before; paid files are streamed from assets/secure/ after ownership is checked,
 * so no URL exists that hands one out.
 * Usage: material_download.php?id=12[&inline=1]
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Material.php';
require_once __DIR__ . '/includes/materials_paid.php';
require_once __DIR__ . '/includes/app_ticket.php';

$id = (int)($_GET['id'] ?? 0);
$inline = (($_GET['inline'] ?? '') === '1');

$materialModel = new Material($pdo);
$material = $id > 0 ? $materialModel->getById($id) : null;

// Moderators must be able to preview a submission before approving it.
$isModerator = has_role('admin');

if (!$material || ($isModerator ? false : $material['status'] !== 'approved')) {
    http_response_code(404);
    exit('Material not found.');
}

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
// The mobile app holds a bearer token, not a cookie, so api/v1 hands it a link
// with a ten-minute ticket scoped to this one file. The ticket is the login.
$ticket = trim((string)($_GET['t'] ?? ''));
if ($ticket !== '') {
    $ticketUser = app_ticket_resolve($pdo, $ticket, 'read:' . $id);
    if ($ticketUser) {
        $userId = (int)$ticketUser['id'];
    }
}
material_require_access($pdo, $material, $userId > 0 ? $userId : null);

$path = material_resolve_file($material);
if ($path === null) {
    http_response_code(404);
    exit('This file is no longer available.');
}

if (!$inline) {
    $materialModel->incrementDownloads($id);
}

material_stream($path, material_download_name($material), $material['mime_type'] ?? null, $inline);
