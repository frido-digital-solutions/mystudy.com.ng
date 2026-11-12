<?php
require_once __DIR__ . '/config/config.php';
$page_title = 'Reset Password - ' . SITE_NAME;

$token = $_GET['token'] ?? '';
$errors = [];
$success = '';

if (empty($token)) {
    set_flash_message('error', 'Invalid reset link. Please request a new password reset.');
    header('Location: ' . SITE_URL . '/forgot-password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $errors['password'] = 'Please enter a new password';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long';
    }
    
    if (empty($confirm_password)) {
        $errors['confirm_password'] = 'Please confirm your password';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    if (empty($errors)) {
        require_once __DIR__ . '/controllers/AuthController.php';
        $auth = new AuthController($pdo);
        $result = $auth->resetPassword($token, $password);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $errors = $result['errors'] ?? ['general' => 'Something went wrong. Please try again.'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    
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
        
        .form-floating > .form-control.is-invalid {
            border-color: #ef4444;
        }
        
        .form-floating > label {
            padding-left: 1rem;
            color: #94a3b8;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            border-radius: 2px;
            background: #e2e8f0;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        
        .password-strength-bar.weak {
            width: 33%;
            background: #ef4444;
        }
        
        .password-strength-bar.medium {
            width: 66%;
            background: #f59e0b;
        }
        
        .password-strength-bar.strong {
            width: 100%;
            background: #10b981;
        }
        
        .password-requirements {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.5rem;
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: 1rem;
        }
        
        .password-requirements li {
            margin-bottom: 0.25rem;
        }
        
        .password-requirements li.met {
            color: #10b981;
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
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .password-error {
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
                <i class="fas fa-lock"></i>
            </div>
            <h2 class="auth-title">Reset Password</h2>
            <p class="auth-subtitle">Create your new password</p>
        </div>
        
        <div class="auth-body">
            <?php if ($success): ?>
                <div class="alert alert-success" style="border-radius: 12px; font-size: 0.9rem;">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-primary">Sign In</a>
                </div>
            <?php else: ?>
                <form action="" method="POST">
                    <div class="form-floating mb-3">
                        <input type="password" 
                               class="form-control password-field <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                               id="password" 
                               name="password" 
                               placeholder="New Password"
                               required>
                        <label for="password">New Password</label>
                    </div>
                    
                    <div class="password-strength" id="passwordStrength" style="display: none;">
                        <div class="password-strength-bar" id="passwordStrengthBar"></div>
                    </div>
                    
                    <div class="password-requirements" id="passwordRequirements" style="display: none;">
                        <ul>
                            <li id="req-length">At least 8 characters</li>
                            <li id="req-match">Passwords match</li>
                        </ul>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-floating flex-grow-1">
                            <input type="password" 
                                   class="form-control password-field <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Confirm Password"
                                   required>
                            <label for="confirm_password">Confirm Password</label>
                        </div>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['confirm_password']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($errors['general'])): ?>
                        <div class="alert alert-danger" style="border-radius: 12px; font-size: 0.9rem;"><?php echo $errors['general']; ?></div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </form>
            <?php endif; ?>
            
            <div class="back-link">
                <a href="<?php echo SITE_URL; ?>/login.php">Back to Sign In</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript for Password Validation -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const submitBtn = document.querySelector('button[type="submit"]');
            const passwordStrength = document.getElementById('passwordStrength');
            const passwordStrengthBar = document.getElementById('passwordStrengthBar');
            const passwordRequirements = document.getElementById('passwordRequirements');
            const reqLength = document.getElementById('req-length');
            const reqMatch = document.getElementById('req-match');
            
            function checkPasswordStrength(password) {
                let strength = 0;
                
                // Length check
                if (password.length >= 8) strength++;
                if (password.length >= 12) strength++;
                
                // Character variety checks
                if (/[a-z]/.test(password)) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^a-zA-Z0-9]/.test(password)) strength++;
                
                return strength;
            }
            
            function updatePasswordStrength(password) {
                if (password.length === 0) {
                    passwordStrength.style.display = 'none';
                    passwordRequirements.style.display = 'none';
                    return;
                }
                
                passwordStrength.style.display = 'block';
                passwordRequirements.style.display = 'block';
                
                const strength = checkPasswordStrength(password);
                
                // Update strength bar
                passwordStrengthBar.className = 'password-strength-bar';
                if (strength <= 2) {
                    passwordStrengthBar.classList.add('weak');
                } else if (strength <= 4) {
                    passwordStrengthBar.classList.add('medium');
                } else {
                    passwordStrengthBar.classList.add('strong');
                }
                
                // Update requirements
                if (password.length >= 8) {
                    reqLength.classList.add('met');
                } else {
                    reqLength.classList.remove('met');
                }
                
                // Check if passwords match
                if (confirmPassword.value && password === confirmPassword.value) {
                    reqMatch.classList.add('met');
                } else {
                    reqMatch.classList.remove('met');
                }
            }
            
            function validatePasswords() {
                const passwordValue = password.value;
                const confirmValue = confirmPassword.value;
                
                // Remove existing error messages
                document.querySelectorAll('.password-error').forEach(el => el.remove());
                
                let isValid = true;
                
                // Validate password length
                if (passwordValue.length > 0 && passwordValue.length < 8) {
                    showError(password, 'Password must be at least 8 characters long');
                    isValid = false;
                }
                
                // Validate password matching
                if (confirmValue.length > 0 && passwordValue !== confirmValue) {
                    showError(confirmPassword, 'Passwords do not match');
                    isValid = false;
                }
                
                // Update submit button state
                if (submitBtn) {
                    submitBtn.disabled = !isValid || passwordValue.length === 0 || confirmValue.length === 0;
                }
                
                return isValid;
            }
            
            function showError(field, message) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'password-error';
                errorDiv.textContent = message;
                field.parentNode.appendChild(errorDiv);
                field.classList.add('is-invalid');
            }
            
            function clearErrors(field) {
                field.classList.remove('is-invalid');
                field.parentNode.querySelectorAll('.password-error').forEach(el => el.remove());
            }
            
            // Add event listeners
            password.addEventListener('input', function() {
                clearErrors(password);
                clearErrors(confirmPassword);
                updatePasswordStrength(this.value);
                validatePasswords();
            });
            
            confirmPassword.addEventListener('input', function() {
                clearErrors(confirmPassword);
                updatePasswordStrength(password.value);
                validatePasswords();
            });
            
            password.addEventListener('focus', function() {
                if (this.value.length > 0) {
                    updatePasswordStrength(this.value);
                }
            });
            
            // Initial validation
            validatePasswords();
        });
    </script>
</body>
</html>
