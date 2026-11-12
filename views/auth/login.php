<?php
// View only. Login logic is handled in `login.php`.
// Expects `$registration_success`, `$email`, `$errors` from the controller.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    
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
        
        .input-group-text {
            border-radius: 0 12px 12px 0;
            border: 2px solid #e2e8f0;
            border-left: none;
            background: white;
            cursor: pointer;
            color: #94a3b8;
        }
        
        .password-field {
            border-radius: 12px 0 0 12px !important;
            border-right: none !important;
        }
        
        .forgot-password {
            text-align: right;
            margin-bottom: 1.5rem;
        }
        
        .forgot-password a {
            color: #64748b;
            font-size: 0.85rem;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .forgot-password a:hover {
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
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 2rem 0;
            color: #94a3b8;
            font-size: 0.85rem;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .divider::before { margin-right: 1em; }
        .divider::after { margin-left: 1em; }
        
        .social-login {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .btn-social {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.6rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .btn-google {
            background: white;
            border: 2px solid #e2e8f0;
            color: #1e293b;
        }
        
        .btn-google:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        
        .btn-facebook {
            background: #1877f2;
            color: white;
            border: none;
        }
        
        .btn-facebook:hover {
            background: #166fe5;
        }
        
        .register-link {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .register-link a {
            color: #10b981;
            font-weight: 600;
            text-decoration: none;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }

        .form-check-input:checked {
            background-color: #10b981;
            border-color: #10b981;
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
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h2 class="auth-title">Welcome Back</h2>
            <p class="auth-subtitle">Sign in to continue your learning journey</p>
        </div>
        
        <div class="auth-body">
            <!-- Success Toast Container -->
            <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 11">
                <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-check-circle me-2"></i> Registration successful!
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-progress" style="height: 3px; background: rgba(255,255,255,0.3); width: 100%;">
                        <div class="toast-progress-bar" style="height: 100%; background: #fff; width: 100%; transition: width 5s linear;"></div>
                    </div>
                </div>
            </div>
            
            <?php echo display_flash_message(); ?>
            
            <form action="" method="POST" id="loginForm">
                <div class="form-floating mb-3">
                    <input type="email" 
                           class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                           id="email" 
                           name="email" 
                           placeholder="name@example.com"
                           value="<?php echo htmlspecialchars($email); ?>" 
                           required
                           autofocus>
                    <label for="email">Email Address</label>
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <div class="input-group">
                        <div class="form-floating flex-grow-1">
                            <input type="password" 
                                   class="form-control password-field <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Password"
                                   required>
                            <label for="password">Password</label>
                        </div>
                        <span class="input-group-text toggle-password">
                            <i class="fas fa-eye"></i>
                        </span>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                        <label class="form-check-label text-muted" for="remember_me" style="font-size: 0.9rem;">Remember me</label>
                    </div>
                    <div class="forgot-password mb-0">
                        <a href="<?php echo SITE_URL; ?>/forgot-password.php">Forgot Password?</a>
                    </div>
                </div>
                
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger" style="border-radius: 12px; font-size: 0.9rem;"><?php echo $errors['general']; ?></div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>
            

            
            <div class="register-link">
                Don't have an account? <a href="<?php echo SITE_URL; ?>/register.php">Create Account</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
        // Show toast if registration was successful
        <?php if (isset($registration_success) && $registration_success): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var toastEl = document.getElementById('successToast');
            var toast = new bootstrap.Toast(toastEl, { delay: 5000 });
            toast.show();
            
            // Animate progress bar
            setTimeout(function() {
                document.querySelector('.toast-progress-bar').style.width = '0%';
            }, 100);
        });
        <?php endif; ?>
    </script>
</body>
</html>
