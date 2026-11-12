<?php
// View only. Registration logic is handled in `register.php`.
// Expects `$errors`, `$name`, `$email`, `$user_type`, `$university`, `$department`, `$level` from the controller.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    
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
            padding: 2rem 1rem;
        }
        
        .auth-container {
            width: 100%;
            max-width: 750px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
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
            border-bottom: 1px solid #f1f5f9;
        }
        
        .auth-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1rem;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }
        
        .auth-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .auth-subtitle {
            color: #64748b;
            font-size: 0.95rem;
        }
        
        .auth-body {
            padding: 2rem;
        }
        
        /* Custom Tabs */
        .nav-tabs {
            border: none;
            justify-content: center;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        
        .nav-link {
            border: none;
            background: #f1f5f9;
            color: #64748b;
            border-radius: 50px !important;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .nav-link.active {
            background: #10b981 !important;
            color: white !important;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            transform: translateY(-2px);
        }
        
        .nav-link i {
            margin-right: 0.5rem;
        }
        
        /* Form Styling */
        .form-floating > .form-control,
        .form-floating > .form-select {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding-left: 1rem;
            height: calc(3.5rem + 2px);
        }
        
        .form-floating > .form-control:focus,
        .form-floating > .form-select:focus {
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
        
        /* Profile Upload */
        .profile-upload-area {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .profile-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e2e8f0;
            margin-bottom: 1rem;
            display: none;
        }
        
        .upload-btn-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .btn-upload {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            color: #64748b;
            padding: 0.6rem 1.5rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-upload:hover {
            border-color: #10b981;
            color: #10b981;
            background: #f0fdf4;
        }
        
        .upload-btn-wrapper input[type=file] {
            font-size: 100px;
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            padding: 1rem;
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
        
        .login-link {
            text-align: center;
            margin-top: 2rem;
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .login-link a {
            color: #10b981;
            font-weight: 600;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .was-validated .form-control:invalid {
            border-color: #ef4444;
            background-image: none;
        }
        
        .was-validated .form-control:valid {
            border-color: #10b981;
            background-image: none;
        }

        /* Mobile Responsiveness */
        @media (max-width: 576px) {
            body {
                padding: 1rem 0.5rem;
            }
            
            .auth-container {
                border-radius: 16px;
            }
            
            .auth-header {
                padding: 2rem 1.5rem 1rem;
            }
            
            .auth-body {
                padding: 1.5rem;
            }
            
            .nav-tabs {
                gap: 0.5rem;
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 0.5rem;
            }
            
            .nav-link {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
                white-space: nowrap;
            }
            
            .auth-title {
                font-size: 1.25rem;
            }
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
                <i class="fas fa-user-plus"></i>
            </div>
            <h2 class="auth-title">Create Account</h2>
            <p class="auth-subtitle">Join thousands of students learning smarter</p>
        </div>
        
        <div class="auth-body">
            <?php echo display_flash_message(); ?>
            
            <ul class="nav nav-tabs" id="userTypeTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo ($user_type !== 'university') ? 'active' : ''; ?>" 
                            id="secondary-tab" 
                            data-bs-toggle="tab" 
                            data-bs-target="#secondary" 
                            type="button" 
                            role="tab">
                        <i class="fas fa-school"></i> Secondary
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo ($user_type === 'university') ? 'active' : ''; ?>" 
                            id="university-tab" 
                            data-bs-toggle="tab" 
                            data-bs-target="#university" 
                            type="button" 
                            role="tab">
                        <i class="fas fa-university"></i> University
                    </button>
                </li>
            </ul>
            
            <form action="<?php echo SITE_URL; ?>/register.php" method="POST" enctype="multipart/form-data" id="registrationForm" novalidate>
                <div class="tab-content" id="userTypeTabsContent">
                    <!-- Secondary Student Content -->
                    <div class="tab-pane fade <?php echo ($user_type !== 'university') ? 'show active' : ''; ?>" id="secondary" role="tabpanel">
                        <input type="hidden" name="user_type" value="secondary">
                        
                        <div class="profile-upload-area">
                            <img id="profilePicturePreview" src="#" alt="Preview" class="profile-preview">
                            <div class="upload-btn-wrapper">
                                <button type="button" class="btn-upload">
                                    <i class="fas fa-camera me-2"></i> Upload Photo
                                </button>
                                <input type="file" name="profile_picture" id="profilePicture" accept="image/*">
                            </div>
                            <div id="fileName" class="small text-muted mt-2"></div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="name" name="name" placeholder="Full Name" value="<?php echo htmlspecialchars($name); ?>" required>
                                    <label for="name">Full Name</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" required>
                                    <label for="email">Email Address</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <div class="form-floating flex-grow-1">
                                        <input type="password" class="form-control password-field" id="password" name="password" placeholder="Password" required minlength="8">
                                        <label for="password">Password</label>
                                    </div>
                                    <span class="input-group-text toggle-password"><i class="fas fa-eye"></i></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <div class="form-floating flex-grow-1">
                                        <input type="password" class="form-control password-field" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                                        <label for="confirm_password">Confirm Password</label>
                                    </div>
                                    <span class="input-group-text toggle-password"><i class="fas fa-eye"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- University Student Content -->
                    <div class="tab-pane fade <?php echo ($user_type === 'university') ? 'show active' : ''; ?>" id="university" role="tabpanel">
                        <input type="hidden" name="user_type" value="university">
                        
                        <div class="profile-upload-area">
                            <img id="uniProfilePicturePreview" src="#" alt="Preview" class="profile-preview">
                            <div class="upload-btn-wrapper">
                                <button type="button" class="btn-upload">
                                    <i class="fas fa-camera me-2"></i> Upload Photo
                                </button>
                                <input type="file" name="uni_profile_picture" id="uniProfilePicture" accept="image/*">
                            </div>
                            <div id="uniFileName" class="small text-muted mt-2"></div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="uni_name" name="name" placeholder="Full Name" value="<?php echo htmlspecialchars($name); ?>" required>
                                    <label for="uni_name">Full Name</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="uni_email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" required>
                                    <label for="uni_email">Email Address</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="uni_select" name="university" required>
                                        <option value="" disabled selected>Select University</option>
                                        <?php if (!empty($universitiesList)): ?>
                                            <?php foreach ($universitiesList as $uni): ?>
                                                <option value="<?php echo htmlspecialchars($uni); ?>"><?php echo htmlspecialchars($uni); ?></option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="Adekunle Ajasin University">Adekunle Ajasin University</option>
                                            <option value="Afe Babalola University">Afe Babalola University</option>
                                            <option value="Ahmadu Bello University">Ahmadu Bello University</option>
                                            <option value="Ambrose Alli University">Ambrose Alli University</option>
                                            <option value="Babcock University">Babcock University</option>
                                            <option value="Bayero University Kano">Bayero University Kano</option>
                                            <option value="Bells University of Technology">Bells University of Technology</option>
                                            <option value="Benson Idahosa University">Benson Idahosa University</option>
                                            <option value="Bowen University">Bowen University</option>
                                            <option value="Covenant University">Covenant University</option>
                                            <option value="Delta State University">Delta State University</option>
                                            <option value="Ekiti State University">Ekiti State University</option>
                                            <option value="Enugu State University of Science and Technology">Enugu State University of Science and Technology</option>
                                            <option value="Federal University of Agriculture, Abeokuta">Federal University of Agriculture, Abeokuta</option>
                                            <option value="Federal University of Petroleum Resources">Federal University of Petroleum Resources</option>
                                            <option value="Federal University of Technology, Akure">Federal University of Technology, Akure</option>
                                            <option value="Federal University of Technology, Minna">Federal University of Technology, Minna</option>
                                            <option value="Federal University of Technology, Owerri">Federal University of Technology, Owerri</option>
                                            <option value="Igbinedion University">Igbinedion University</option>
                                            <option value="Kaduna State University">Kaduna State University</option>
                                            <option value="Kwara State University">Kwara State University</option>
                                            <option value="Ladoke Akintola University of Technology">Ladoke Akintola University of Technology</option>
                                            <option value="Lagos State University">Lagos State University</option>
                                            <option value="Lead City University">Lead City University</option>
                                            <option value="Madonna University">Madonna University</option>
                                            <option value="Nnamdi Azikiwe University">Nnamdi Azikiwe University</option>
                                            <option value="Nasarawa State University">Nasarawa State University</option>
                                            <option value="Obafemi Awolowo University">Obafemi Awolowo University</option>
                                            <option value="Olabisi Onabanjo University">Olabisi Onabanjo University</option>
                                            <option value="Osun State University">Osun State University</option>
                                            <option value="Rivers State University">Rivers State University</option>
                                            <option value="University of Abuja">University of Abuja</option>
                                            <option value="University of Benin">University of Benin</option>
                                            <option value="University of Calabar">University of Calabar</option>
                                            <option value="University of Ibadan">University of Ibadan</option>
                                            <option value="University of Ilorin">University of Ilorin</option>
                                            <option value="University of Jos">University of Jos</option>
                                            <option value="University of Lagos">University of Lagos</option>
                                            <option value="University of Maiduguri">University of Maiduguri</option>
                                            <option value="University of Nigeria">University of Nigeria</option>
                                            <option value="University of Port Harcourt">University of Port Harcourt</option>
                                            <option value="University of Uyo">University of Uyo</option>
                                            <option value="Usmanu Danfodiyo University">Usmanu Danfodiyo University</option>
                                        <?php endif; ?>
                                        <option value="Other">Other</option>
                                    </select>
                                    <label for="uni_select">University</label>
                                </div>
                                <div id="otherUniversityContainer" class="mt-2" style="display:none;">
                                    <input type="text" class="form-control" id="otherUniversity" name="other_university" placeholder="Enter University Name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="department" name="department" placeholder="Department" value="<?php echo htmlspecialchars($department); ?>" required>
                                    <label for="department">Department</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="level" name="level" required>
                                        <option value="" disabled selected>Select Level</option>
                                        <option value="100 Level">100 Level</option>
                                        <option value="200 Level">200 Level</option>
                                        <option value="300 Level">300 Level</option>
                                        <option value="400 Level">400 Level</option>
                                        <option value="500 Level">500 Level</option>
                                        <option value="Postgraduate">Postgraduate</option>
                                    </select>
                                    <label for="level">Level</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <div class="form-floating flex-grow-1">
                                        <input type="password" class="form-control password-field" id="uni_password" name="password" placeholder="Password" required minlength="8">
                                        <label for="uni_password">Password</label>
                                    </div>
                                    <span class="input-group-text toggle-password"><i class="fas fa-eye"></i></span>
                                </div>
                            </div>
                            <div class="col-md-12"> <!-- Full width confirm password for balance -->
                                <div class="input-group">
                                    <div class="form-floating flex-grow-1">
                                        <input type="password" class="form-control password-field" id="uni_confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                                        <label for="uni_confirm_password">Confirm Password</label>
                                    </div>
                                    <span class="input-group-text toggle-password"><i class="fas fa-eye"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mt-4" style="border-radius: 12px;">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error) echo "<li>$error</li>"; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($registrations_open ?? true): ?>
                <button type="submit" class="btn btn-primary mt-4">Create Account</button>
                <?php else: ?>
                <button type="button" class="btn btn-secondary mt-4" disabled>Create Account</button>
                <?php endif; ?>
                
                <div class="login-link">
                    Already have an account? <a href="<?php echo SITE_URL; ?>/login.php">Log In</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Tab switching logic to enable/disable inputs
        function setActivePane(paneId) {
            const panes = ['secondary', 'university'];
            panes.forEach(id => {
                const pane = document.getElementById(id);
                const isActive = (id === paneId);
                pane.querySelectorAll('input, select').forEach(el => {
                    if (isActive) {
                        el.disabled = false;
                    } else {
                        el.disabled = true;
                    }
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = document.querySelector('.tab-pane.show.active');
            setActivePane(activeTab ? activeTab.id : 'secondary');

            // Listen for tab changes
            const tabEls = document.querySelectorAll('button[data-bs-toggle="tab"]');
            tabEls.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(event) {
                    setActivePane(event.target.getAttribute('data-bs-target').substring(1));
                });
            });
        });

        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', function() {
                const input = this.closest('.input-group').querySelector('input');
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

        // Profile picture preview
        function setupPreview(inputId, previewId, nameId) {
            document.getElementById(inputId).addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = document.getElementById(previewId);
                        preview.src = e.target.result;
                        preview.style.display = 'inline-block';
                    }
                    reader.readAsDataURL(file);
                    document.getElementById(nameId).textContent = file.name;
                }
            });
        }

        setupPreview('profilePicture', 'profilePicturePreview', 'fileName');
        setupPreview('uniProfilePicture', 'uniProfilePicturePreview', 'uniFileName');

        // Other university toggle
        document.getElementById('uni_select').addEventListener('change', function() {
            const otherContainer = document.getElementById('otherUniversityContainer');
            const otherInput = document.getElementById('otherUniversity');
            
            if (this.value === 'Other') {
                otherContainer.style.display = 'block';
                otherInput.required = true;
                this.name = ''; // Remove name so it doesn't submit
                otherInput.name = 'university';
            } else {
                otherContainer.style.display = 'none';
                otherInput.required = false;
                otherInput.name = 'other_university';
                this.name = 'university';
            }
        });

        // Form validation
        const form = document.getElementById('registrationForm');
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    </script>
</body>
</html>
