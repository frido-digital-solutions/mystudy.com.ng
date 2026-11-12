<?php
// login.php - Controller for login
$base_path = __DIR__;
require_once $base_path . '/config/config.php'; // loads database.php and functions.php, sets $pdo and SITE_URL

// If already logged in, redirect
if (function_exists('is_logged_in') && is_logged_in()) {
    if (function_exists('has_role') && has_role('admin')) {
        header('Location: ' . SITE_URL . '/admin/');
        exit;
    }
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

// Flags and view vars
$registration_success = isset($_GET['registered']) && $_GET['registered'] === '1';
$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);

    $auth = new AuthController($pdo);
    $result = $auth->login($email, $password);

    if (!empty($result['success'])) {
        $target = !empty($result['redirect']) ? $result['redirect'] : '/dashboard.php';
        header('Location: ' . SITE_URL . $target);
        exit;
    } else {
        $errors = $result['errors'] ?? ['general' => 'Invalid email or password'];
    }
}

require_once $base_path . '/views/auth/login.php';
