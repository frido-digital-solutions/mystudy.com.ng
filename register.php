<?php
// register.php - Controller for registration
error_reporting(E_ALL);
ini_set('display_errors', 1);

$base_path = __DIR__;
require_once $base_path . '/config/config.php'; // loads database.php and functions.php, sets $pdo and SITE_URL

// View model defaults
$name = '';
$email = '';
$user_type = '';
$university = '';
$department = '';
$level = '';
$errors = [];

// Fetch universities dynamically from the database
$universitiesList = [];
try {
    $uniStmt = $pdo->query('SELECT name FROM universities ORDER BY name ASC');
    $universitiesList = $uniStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    // Table might not exist yet if Post model was never loaded, fallback empty
}

// Detect AJAX
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
}

// The "Allow New User Registration" switch on the admin Settings page.
$registrations_open = site_setting('allow_registration', '1') === '1';
$registration_closed_message = 'New user registrations are currently closed. Please check back later.';

if (!$registrations_open) {
    $errors['general'] = $registration_closed_message;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_ajax) {
        http_response_code(403);
        echo json_encode(['success' => false, 'errors' => $errors, 'message' => $registration_closed_message]);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gather inputs
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = sanitize($_POST['user_type'] ?? '');

    $university = sanitize($_POST['university'] ?? '');
    $department = sanitize($_POST['department'] ?? '');
    $level = sanitize($_POST['level'] ?? '');

    // Validate
    if ($name === '' || mb_strlen($name) < 2) {
        $errors['name'] = 'Please enter your full name (min 2 chars)';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    if (!in_array($user_type, ['secondary', 'university'], true)) {
        $errors['user_type'] = 'Please select a valid user type';
    }
    if ($user_type === 'university') {
        if ($university === '') $errors['university'] = 'University is required';
        if ($department === '') $errors['department'] = 'Department is required';
        if ($level === '') $errors['level'] = 'Level is required';
    }

    // Check email uniqueness
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email is already registered';
        }
    }

    // Handle success or return errors
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, user_type, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$name, $email, $password_hash, $user_type]);
            $user_id = (int)$pdo->lastInsertId();

            if ($user_type === 'university') {
                $meta_stmt = $pdo->prepare('INSERT INTO user_meta (user_id, meta_key, meta_value) VALUES (?, ?, ?)');
                $meta_stmt->execute([$user_id, 'university', $university]);
                $meta_stmt->execute([$user_id, 'department', $department]);
                $meta_stmt->execute([$user_id, 'level', $level]);
            }

            // Optional profile picture
            $file_input_name = ($user_type === 'university') ? 'uni_profile_picture' : 'profile_picture';
            
            if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
                $upload_dir = $base_path . '/assets/uploads/profiles/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_extension = pathinfo($_FILES[$file_input_name]['name'], PATHINFO_EXTENSION);
                $filename = 'user_' . $user_id . '_' . time() . '.' . strtolower($file_extension);
                $target_file = $upload_dir . $filename;
                $check = @getimagesize($_FILES[$file_input_name]['tmp_name']);
                if ($check !== false && move_uploaded_file($_FILES[$file_input_name]['tmp_name'], $target_file)) {
                    $rel = '/assets/uploads/profiles/' . $filename;
                    $up = $pdo->prepare('UPDATE users SET profile_picture = ? WHERE id = ?');
                    $up->execute([$rel, $user_id]);
                }
            }

            $pdo->commit();

            // Send welcome notification
            try {
                require_once $base_path . '/models/Notification.php';
                $notifModel = new Notification($pdo);
                $notifModel->create(
                    $user_id,
                    'welcome',
                    '👋 Welcome to ' . SITE_NAME . '!',
                    "Hi {$name}! Your account is set up and ready. Start exploring quizzes, study materials, and video tutorials.",
                    SITE_URL . '/dashboard.php'
                );
            } catch (Throwable $e) {
                // Non-critical, don't fail registration
            }

            // Send welcome email (non-blocking — failure won't prevent registration)
            try {
                sendProductionEmail($email, $name, 'welcome', [
                    'student_name' => $name
                ]);
            } catch (Throwable $e) {
                error_log('Welcome email failed for ' . $email . ': ' . $e->getMessage());
            }

            if ($is_ajax) {
                echo json_encode([
                    'success' => true,
                    'redirect' => SITE_URL . '/login.php?registered=1',
                    'message' => 'Registration successful! Redirecting to login...'
                ]);
                exit;
            }

            // set_flash_message('success', 'Registration successful! Please log in.');
            header('Location: ' . SITE_URL . '/login.php?registered=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Registration error: ' . $e->getMessage());
            $errors['general'] = 'An error occurred during registration. Please try again.';
        }
    }

    if ($is_ajax) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'errors' => $errors,
            'message' => 'Please correct the errors below.'
        ]);
        exit;
    }
}

// Render view
// Render view
require_once $base_path . '/views/auth/register.php';
