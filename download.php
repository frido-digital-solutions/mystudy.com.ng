<?php
/**
 * Download material with tracking.
 * Usage: download.php?id=123
 * Increments download_count then redirects to the file.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Material.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid material ID.');
}

$materialModel = new Material($pdo);
$material = $materialModel->getById($id);

if (!$material || $material['status'] !== 'approved') {
    http_response_code(404);
    exit('Material not found.');
}

// Increment download count
$materialModel->incrementDownloads($id);

// Build file URL and redirect
$fileUrl = SITE_URL . '/assets/uploads/materials/' . basename($material['file_path']);
header('Location: ' . $fileUrl);
exit;
