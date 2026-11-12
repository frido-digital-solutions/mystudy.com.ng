<?php
require_once __DIR__ . '/config/config.php';
$page_title = 'Forgot Password - ' . SITE_NAME;

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Your session expired. Please submit the form again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');

        if (empty($email)) {
            $errors['email'] = 'Please enter your email address';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        } else {
            require_once __DIR__ . '/controllers/AuthController.php';
            $auth = new AuthController($pdo);
            $result = $auth->sendPasswordResetEmail($email);

            if ($result['success']) {
                $success = $result['message'];
            } else {
                $errors = $result['errors'] ?? ['general' => 'Something went wrong. Please try again.'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #fef7ed 0%, #fef3e3 50%, #fef9f3 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .auth-container {
            width: 100%;
            max-width: 450px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.5);
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .auth-header {
            text-align: center;
            padding: 2.5rem 2rem 1.5rem;
        }
        
        .auth-logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }
        
        .auth-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .auth-subtitle {
            color: #64748b;
            font-size: 0.95rem;
        }
        
        .auth-body {
            padding: 1rem 2.5rem 2.5rem;
        }
        
        .form-floating > .form-control {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding-left: 1rem;
        }
        
        .form-floating > .form-control:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }
        
        .form-floating > label {
            padding-left: 1rem;
            color: #94a3b8;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            padding: 0.8rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }
        
        .back-link {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .back-link a {
            color: #10b981;
            text-decoration: none;
            font-weight: 600;
        }
        
        .email-error {
            display: block !important;
            margin-top: 0.5rem;
            color: #ef4444;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="auth-container position-relative">
        <a href="<?php echo SITE_URL; ?>/" class="position-absolute top-0 start-0 m-3 text-muted text-decoration-none" style="font-size: 0.9rem; z-index: 10;">
            <i class="fas fa-arrow-left me-1"></i> Back to Home
        </a>
        <div class="auth-header">
            <div class="auth-logo">
                <i class="fas fa-key"></i>
            </div>
            <h2 class="auth-title">Forgot Password</h2>
            <p class="auth-subtitle">Enter your email to reset your password</p>
        </div>
        
        <div class="auth-body">
            <?php if ($success): ?>
                <div class="alert alert-success" style="border-radius: 12px; font-size: 0.9rem;">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <div class="form-floating mb-3">
                    <input type="email" 
                           class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                           id="email" 
                           name="email" 
                           placeholder="name@example.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                           required
                           autofocus>
                    <label for="email">Email Address</label>
                    <?php if (isset($errors['email'])): ?>
                        <div class="email-error"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger" style="border-radius: 12px; font-size: 0.9rem;"><?php echo $errors['general']; ?></div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary">Send Reset Link</button>
            </form>
            <?php endif; ?>
            
            <div class="back-link">
                Remember your password? <a href="<?php echo SITE_URL; ?>/login.php">Sign In</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
