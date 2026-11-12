<?php
require_once __DIR__ . '/../includes/EmailService.php';
require_once __DIR__ . '/../includes/EmailTemplates.php';
require_once __DIR__ . '/../includes/email-config.php';

class AuthController {
    private $userModel;
    
    public function __construct($db) {
        $this->userModel = new User($db);
    }
    
    /**
     * Register a new user
     */
    public function register($data) {
        // Validate input
        $errors = [];
        
        // Check if email is valid
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        } elseif ($this->userModel->emailExists($data['email'])) {
            $errors['email'] = 'Email is already taken';
        }
        
        // Validate name
        if (empty($data['name'])) {
            $errors['name'] = 'Please enter your name';
        }
        
        // Validate password
        if (empty($data['password'])) {
            $errors['password'] = 'Please enter a password';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long';
        }
        
        // Validate user type
        if (empty($data['user_type']) || !in_array($data['user_type'], ['secondary', 'university'])) {
            $errors['user_type'] = 'Please select a valid user type';
        }
        
        // For university students, validate additional fields
        if ($data['user_type'] === 'university') {
            if (empty($data['university'])) {
                $errors['university'] = 'Please select your university';
            }
            if (empty($data['department'])) {
                $errors['department'] = 'Please enter your department';
            }
            if (empty($data['level'])) {
                $errors['level'] = 'Please select your level';
            }
        }
        
        // If there are validation errors, return them
        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors
            ];
        }
        
        // Create user
        $this->userModel->name = $data['name'];
        $this->userModel->email = $data['email'];
        $this->userModel->password_hash = $data['password']; // Will be hashed in the model
        $this->userModel->user_type = $data['user_type'];
        
        // For university students, set additional fields
        if ($data['user_type'] === 'university') {
            $this->userModel->university = $data['university'];
            $this->userModel->department = $data['department'];
            $this->userModel->level = $data['level'];
        }
        
        // Handle profile picture upload if provided
        if (!empty($_FILES['profile_picture']['name'])) {
            $uploadResult = $this->handleFileUpload('profile_picture');
            if ($uploadResult['success']) {
                $this->userModel->profile_picture = $uploadResult['file_path'];
            } else {
                return [
                    'success' => false,
                    'errors' => ['profile_picture' => $uploadResult['error']]
                ];
            }
        }
        
        // Create user in database
        if ($this->userModel->create()) {
            // Send welcome email (non-blocking — failure won't prevent registration)
            try {
                sendProductionEmail($data['email'], $data['name'], 'welcome', [
                    'student_name' => $data['name']
                ]);
            } catch (Exception $e) {
                error_log('Welcome email failed: ' . $e->getMessage());
            }
            
            // Simplified flow: no email verification required
            return [
                'success' => true,
                'message' => 'Registration successful! You can now log in.'
            ];
        } else {
            return [
                'success' => false,
                'errors' => ['general' => 'Registration failed. Please try again.']
            ];
        }
    }
    
    /**
     * Login user
     */
    public function login($email, $password) {
        // Validate input
        $errors = [];
        
        if (empty($email)) {
            $errors['email'] = 'Please enter your email';
        }
        
        if (empty($password)) {
            $errors['password'] = 'Please enter your password';
        }
        
        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors
            ];
        }
        
        // Check if user exists
        if (!$this->userModel->getByEmail($email)) {
            return [
                'success' => false,
                'errors' => ['general' => 'Invalid email or password']
            ];
        }
        
        // Verify password
        if (!password_verify($password, $this->userModel->password_hash)) {
            return [
                'success' => false,
                'errors' => ['general' => 'Invalid email or password']
            ];
        }
        
        // Simplified: skip email verification check
        
        // Check if account is active
        if ($this->userModel->status !== 'active') {
            $status = ucfirst($this->userModel->status);
            return [
                'success' => false,
                'errors' => ['general' => 'Your account is ' . $status . '. Please contact support for assistance.']
            ];
        }
        
        // Check if this is their very first login before updating
        if (empty($this->userModel->last_login)) {
            $_SESSION['is_first_login'] = true;
        }

        // Update last login
        $this->userModel->updateLastLogin();
        
        // Set session
        $this->setUserSession($this->userModel);
        
        return [
            'success' => true,
            'redirect' => $this->getDashboardUrl($this->userModel->user_type)
        ];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        // Unset all session variables
        $_SESSION = array();
        
        // Delete the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
        
        // Redirect to homepage
        $dest = defined('SITE_URL') ? SITE_URL . '/' : '/';
        redirect($dest);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($email) {
        // The reply must read the same whether or not the address exists, so a
        // blocked request still has to look like an accepted one.
        $generic = [
            'success' => true,
            'message' => 'If your email exists in our system, you will receive a password reset link.'
        ];

        // Generous on purpose: on shared hosting every visitor can appear to come
        // from the same proxy address, and a tight network cap would then throttle
        // the whole site. The per-address limit below is the real protection.
        $ipLimit = rate_limit_check('pw_reset_ip:' . sha1(get_client_ip()), 200, 3600);
        if ($ipLimit['limited']) {
            return [
                'success' => false,
                'errors' => ['general' => 'Too many reset requests from this network. Please try again in ' . max(1, (int)ceil($ipLimit['retry_seconds'] / 60)) . ' minutes.']
            ];
        }

        // Counted per address rather than per network: this is what actually caps
        // the daily email quota, and it cannot be dodged by changing headers.
        $emailLimit = rate_limit_check('pw_reset_email:' . sha1(mb_strtolower($email)), 3, 3600);
        if ($emailLimit['limited']) {
            return $generic;
        }

        if ($this->userModel->getByEmail($email)) {
            $token = $this->userModel->generatePasswordResetToken($email);
            
            if ($token) {
                // Use production email configuration
                // Since getByEmail doesn't return user data properly, get it from the model properties
                $studentName = $this->userModel->name ?: $this->userModel->email ?: $email;
                
                $sent = sendProductionEmail($email, $studentName, 'password', [
                    'token' => $token,
                    'student_name' => $studentName
                ]);
                
                if ($sent) {
                    return [
                        'success' => true,
                        'message' => 'Password reset link has been sent to your email.'
                    ];
                }
                
                // Fallback for development
                $reset_link = getEmailUrl('reset', $token);
                return [
                    'success' => true,
                    'message' => 'Password reset link has been sent to your email.',
                    'reset_link' => $reset_link // Only for development, remove in production
                ];
            }
        }
        
        // Always return success to prevent email enumeration
        return $generic;
    }
    
    /**
     * Reset password with token
     */
    public function resetPassword($token, $new_password) {
        // Validate password
        if (strlen($new_password) < 8) {
            return [
                'success' => false,
                'errors' => ['password' => 'Password must be at least 8 characters long']
            ];
        }
        
        if ($this->userModel->resetPassword($token, $new_password)) {
            return [
                'success' => true,
                'message' => 'Your password has been reset. You can now log in with your new password.'
            ];
        } else {
            return [
                'success' => false,
                'errors' => ['general' => 'Invalid or expired token. Please try again.']
            ];
        }
    }
    
    /**
     * Verify email with token
     */
    public function verifyEmail($token) {
        if ($this->userModel->verifyEmail($token)) {
            return [
                'success' => true,
                'message' => 'Your email has been verified. You can now log in.'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Invalid or expired verification token.'
            ];
        }
    }
    
    /**
     * Resend verification email
     */
    public function resendVerificationEmail($email) {
        if ($this->userModel->getByEmail($email)) {
            if ($this->userModel->email_verified) {
                return [
                    'success' => false,
                    'message' => 'This email is already verified.'
                ];
            }
            
            // Generate a new verification token
            $this->userModel->verification_token = generate_token();
            
            // Update user with new token
            $query = "UPDATE users SET verification_token = :token WHERE id = :id";
            $stmt = $this->userModel->conn->prepare($query);
            $stmt->bindParam(':token', $this->userModel->verification_token);
            $stmt->bindParam(':id', $this->userModel->id);
            
            if ($stmt->execute()) {
                // Send verification email
                $this->sendVerificationEmail($email, $this->userModel->verification_token);
                
                return [
                    'success' => true,
                    'message' => 'Verification email has been resent. Please check your inbox.'
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'Failed to resend verification email. Please try again.'
        ];
    }
    
    /**
     * Set user session after successful login
     */
    private function setUserSession($user) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_name'] = $user->name;
        $_SESSION['user_type'] = $user->user_type;
        $_SESSION['user_picture'] = $user->profile_picture;
        
        // Load super admin flag
        if (isset($user->id)) {
            try {
                global $pdo;
                $stmt = $pdo->prepare("SELECT is_super_admin FROM users WHERE id = ?");
                $stmt->execute([$user->id]);
                $_SESSION['is_super_admin'] = (int)$stmt->fetchColumn() === 1;
            } catch (Exception $e) {
                $_SESSION['is_super_admin'] = false;
            }
        }
        $_SESSION['privileges_checked_at'] = time();
        
        $_SESSION['last_activity'] = time();
        
        // Set session expiration (24 hours)
        $_SESSION['expire_time'] = time() + (24 * 60 * 60);
        
        // Force write session data (important for shared hosting)
        session_write_close();
        
        // Restart session for continued use
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Get dashboard URL based on user type
     */
    private function getDashboardUrl($user_type) {
        switch ($user_type) {
            case 'admin':
                return '/admin/dashboard.php';
            case 'university':
                return '/university/dashboard.php';
            case 'secondary':
            default:
                return '/dashboard.php';
        }
    }
    
    /**
     * Handle file uploads
     */
    private function handleFileUpload($field_name) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        $file = $_FILES[$field_name];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_name = uniqid() . '.' . $file_extension;
        $upload_dir = ROOT_PATH . '/assets/uploads/profile_pictures/';
        $file_path = 'assets/uploads/profile_pictures/' . $file_name;
        
        // Create upload directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Check for errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'error' => 'Error uploading file. Please try again.'
            ];
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            return [
                'success' => false,
                'error' => 'Only JPG, PNG, and GIF files are allowed.'
            ];
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            return [
                'success' => false,
                'error' => 'File size must be less than 2MB.'
            ];
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $file_name)) {
            return [
                'success' => true,
                'file_path' => $file_path
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Error uploading file. Please try again.'
        ];
    }
    
    /**
     * Send verification email
     */
    private function sendVerificationEmail($email, $token) {
        // Use production email configuration
        $studentName = $this->userModel->name ?? 'Student';
        
        $sent = sendProductionEmail($email, $studentName, 'verify', [
            'token' => $token,
            'student_name' => $studentName
        ]);
        
        // Always log for troubleshooting (safe)
        if ($sent) {
            logEmailActivity('verify', $email, 'sent', 'Verification email sent successfully');
        } else {
            logEmailActivity('verify', $email, 'failed', 'Verification email failed');
        }
        
        return true; // fail-safe: don't block auth flows
    }
}
?>
