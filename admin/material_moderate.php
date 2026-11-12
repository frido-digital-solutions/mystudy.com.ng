<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('materials')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}
require_once __DIR__ . '/../models/Material.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . SITE_URL . '/admin/materials.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    set_flash_message('error', 'Invalid CSRF token.');
    header('Location: ' . SITE_URL . '/admin/materials.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = $_POST['action'] ?? '';
$status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : '');

if ($id <= 0 || $status === '') {
    set_flash_message('error', 'Invalid request.');
    header('Location: ' . SITE_URL . '/admin/materials.php');
    exit;
}

try {
    $model = new Material($pdo);
    $ok = $model->updateStatus($id, $status, (int)($_SESSION['user_id'] ?? null));
    if ($ok) {
        set_flash_message('success', 'Material status updated to ' . $status . '.');
        log_admin_activity($pdo, ucfirst($status) . ' Material', 'Material ID ' . $id . ' ' . $status);
    } else {
        set_flash_message('error', 'Failed to update status.');
    }
} catch (Throwable $e) {
    set_flash_message('error', 'Server error while updating status.');
}

$back = SITE_URL . '/admin/materials.php?status=' . urlencode($_POST['return_status'] ?? ($status === 'approved' ? 'approved' : ($status === 'rejected' ? 'rejected' : 'pending')));
header('Location: ' . $back);
exit;

