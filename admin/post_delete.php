<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('posts')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}
require_once __DIR__ . '/../models/Post.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = $_GET['csrf_token'] ?? '';
if ($id <= 0 || !validate_csrf_token($token)) {
    set_flash_message('error', 'Invalid request.');
    redirect(SITE_URL . '/admin/posts.php');
}

$admin = new PostAdmin($pdo);
try {
    $ok = $admin->delete($id);
    if ($ok) {
        set_flash_message('success', 'Post deleted.');
    } else {
        set_flash_message('error', 'Could not delete post.');
    }
} catch (Throwable $e) {
    log_error('Post delete failed', ['error'=>$e->getMessage(), 'id'=>$id]);
    set_flash_message('error', 'Server error deleting post.');
}
redirect(SITE_URL . '/admin/posts.php');

