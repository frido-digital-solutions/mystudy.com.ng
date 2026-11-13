<?php
/**
 * Legacy download link. Kept so bookmarks and older posts keep working; the actual
 * hand-off (and the ownership check for paid files) lives in material_download.php.
 * Usage: download.php?id=123
 */
require_once __DIR__ . '/config/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid material ID.');
}

header('Location: ' . SITE_URL . '/material_download.php?id=' . $id);
exit;
