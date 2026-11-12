<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('quizzes')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}
require_once __DIR__ . '/../models/Quiz.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = $_GET['csrf_token'] ?? '';
if ($id <= 0 || !validate_csrf_token($token)) {
    set_flash_message('error', 'Invalid request.');
    redirect(SITE_URL . '/admin/quizzes.php');
}

$admin = new QuizAdmin($pdo);
try {
    $ok = $admin->deleteQuiz($id);
    if ($ok) {
        set_flash_message('success', 'Quiz deleted.');
    } else {
        set_flash_message('error', 'Could not delete quiz.');
    }
} catch (Throwable $e) {
    log_error('Quiz delete failed', ['error'=>$e->getMessage(), 'id'=>$id]);
    set_flash_message('error', 'Server error deleting quiz.');
}
redirect(SITE_URL . '/admin/quizzes.php');

