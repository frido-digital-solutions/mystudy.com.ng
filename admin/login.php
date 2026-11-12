<?php
// admin/login.php - Dedicated controller and view for Admin Login
$base_path = dirname(__DIR__);
require_once $base_path . '/config/config.php'; 
require_once $base_path . '/controllers/AuthController.php';

// If already logged in, redirect
if (function_exists('is_logged_in') && is_logged_in()) {
    if (function_exists('has_role') && has_role('admin')) {
        header('Location: ' . SITE_URL . '/admin/dashboard.php');
        exit;
    }
    // If logged in but not admin, they shouldn't be here
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

// Secure Admin Login Entry
// Must have ?secure=FridoAdminAccess to view the page
if (!isset($_GET['secure']) || $_GET['secure'] !== 'FridoAdminAccess') {
    header("HTTP/1.0 404 Not Found");
    echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>";
    exit;
}

$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors = ['general' => 'Your login form token expired. Please submit the form again.'];
    } else {
        $auth = new AuthController($pdo);
        $result = $auth->login($email, $password);

        if (!empty($result['success'])) {
            // Enforce admin check immediately after login attempt
            // We need to re-fetch the user role because auth controller just sets sessions
            if (has_role('admin')) {
                header('Location: ' . SITE_URL . '/admin/dashboard.php');
                exit;
            } else {
                // Log them out if they tried to log into admin portal without admin rights
                session_destroy();
                session_start();
                $errors = ['general' => 'Access Denied. You do not have administrator privileges.'];
            }
        } else {
            $errors = $result['errors'] ?? ['general' => 'Invalid email or password'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal Login - <?php echo SITE_NAME; ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .admin-login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .admin-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 2.5rem 2rem;
            text-align: center;
            color: #fff;
        }
        .admin-logo {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.2);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            border: 2px solid rgba(255,255,255,0.4);
        }
        .admin-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0; letter-spacing: 0.5px; }
        .admin-subtitle { color: rgba(255,255,255,0.8); font-size: 0.9rem; margin-top: 5px; }
        
        .admin-body { padding: 2rem; }
        
        .form-floating > .form-control { border-radius: 8px; border: 2px solid #e2e8f0; }
        .form-floating > .form-control:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        .form-floating > label { color: #64748b; }
        
        .btn-admin {
            background: #0f172a;
            color: #fff;
            border: none;
            padding: 0.8rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            width: 100%;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.3);
        }
        .btn-admin:hover {
            background: #1e293b;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(15, 23, 42, 0.4);
        }
        .input-group-text {
            border-radius: 0 8px 8px 0;
            border: 2px solid #e2e8f0;
            border-left: none;
            background: white;
            cursor: pointer;
            color: #94a3b8;
        }
        .password-field {
            border-radius: 8px 0 0 8px !important;
            border-right: none !important;
        }
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
            display: block;
            color: #64748b;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .back-link:hover { color: #10b981; }
    </style>
</head>
<body>
    <div class="admin-login-card">
        <div class="admin-header">
            <div class="admin-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2 class="admin-title"><?php echo SITE_NAME; ?></h2>
            <div class="admin-subtitle">Secure Administration Portal</div>
        </div>
        
        <div class="admin-body">
            <?php 
            // Check if maintenance mode is active and show warning
            try {
                require_once __DIR__ . '/../includes/maintenance-functions.php';
                if (is_maintenance_mode()) {
                    echo '<div class="alert alert-warning mb-3" style="border-radius: 8px; font-size: 0.9rem; border-left: 4px solid #f59e0b;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>⚠️ Maintenance Mode Active</strong><br>
                        <span class="small">Site is currently under maintenance. Login to disable it.</span>
                    </div>';
                }
            } catch (Throwable $e) {
                error_log('Admin login maintenance check failed: ' . $e->getMessage());
                echo '<div class="alert alert-danger mb-3" style="border-radius: 8px; font-size: 0.9rem; border-left: 4px solid #dc2626;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Maintenance status could not be checked</strong><br>
                    <span class="small">If the site is under maintenance, log in and turn it off from Maintenance Control.</span>
                </div>';
            }
            
            echo display_flash_message(); 
            ?>
            
            <form action="?secure=FridoAdminAccess" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger" style="border-radius: 8px; font-size: 0.9rem;">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>

                <div class="form-floating mb-3">
                    <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" placeholder="name@example.com" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
                    <label for="email"><i class="fas fa-user-tie me-2"></i>Admin Email</label>
                    <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?>
                </div>
                
                <div class="mb-4">
                    <div class="input-group">
                        <div class="form-floating flex-grow-1">
                            <input type="password" class="form-control password-field <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="Password" required>
                            <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                        </div>
                        <span class="input-group-text toggle-password">
                            <i class="fas fa-eye"></i>
                        </span>
                        <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?php echo $errors['password']; ?></div><?php endif; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-admin">Secure Login</button>
            </form>
            
            <a href="<?php echo SITE_URL; ?>/" class="back-link">
                <i class="fas fa-arrow-left me-1"></i> Return to Main Site
            </a>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('fa-eye-slash', 'fa-eye');
                }
            });
        });
    </script>
</body>
</html>
