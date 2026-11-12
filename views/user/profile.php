<?php
// Include configuration and initialize database connection
require_once __DIR__ . '/../../config/config.php';

// Require login to access this page
require_login();

// Initialize variables
$user = new User($pdo);
$user->getById($_SESSION['user_id']);

// Fetch Battle Data
require_once __DIR__ . '/../../models/Battle.php';
$battleModel = new Battle($pdo);
$pendingChallenges = $battleModel->getPendingChallenges($_SESSION['user_id']);
$recentBattles = $battleModel->getUserBattles($_SESSION['user_id'], 5);

$errors = [];
$success = false;

// Fetch universities dynamically from the database
$universitiesList = [];
try {
    $uniStmt = $pdo->query('SELECT name FROM universities ORDER BY name ASC');
    $universitiesList = $uniStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    // Table might not exist yet if Post model was never loaded
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $errors['general'] = 'Your form session expired. Please check your details and submit again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $university = isset($_POST['university']) ? sanitize($_POST['university']) : null;
    $department = isset($_POST['department']) ? sanitize($_POST['department']) : null;
    $level = isset($_POST['level']) ? sanitize($_POST['level']) : null;
    
    // Privacy Settings (checkboxes are only set in POST if checked)
    $privacy_show_school = isset($_POST['privacy_show_school']) ? 1 : 0;
    $privacy_show_stats = isset($_POST['privacy_show_stats']) ? 1 : 0;
    $privacy_show_discussions = isset($_POST['privacy_show_discussions']) ? 1 : 0;
    $privacy_show_badges = isset($_POST['privacy_show_badges']) ? 1 : 0;
    $privacy_show_battles = isset($_POST['privacy_show_battles']) ? 1 : 0;
    $privacy_allow_messages = isset($_POST['privacy_allow_messages']) ? 1 : 0;
    $show_level_on_users_page = isset($_POST['show_level_on_users_page']) ? 1 : 0;
    
    // Validate input
    if (empty($name)) {
        $errors['name'] = 'Name is required';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    } elseif ($email !== $user->email && $user->emailExists($email)) {
        $errors['email'] = 'Email is already taken';
    }
    
    // If changing password — only validate if user intentionally filled password fields
    // Skip password validation entirely when just uploading a profile picture
    $isPictureOnlyUpload = isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK 
                           && empty($current_password) && empty($new_password) && empty($confirm_password);
    
    if (!$isPictureOnlyUpload && (!empty($current_password) || !empty($new_password) || !empty($confirm_password))) {
        if (empty($current_password)) {
            $errors['current_password'] = 'Current password is required to change your password';
        } elseif (!password_verify($current_password, $user->password_hash)) {
            $errors['current_password'] = 'Current password is incorrect';
        }
        
        if (strlen($new_password) < 8) {
            $errors['new_password'] = 'New password must be at least 8 characters long';
        } elseif ($new_password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
    }
    
    // Handle profile picture upload
    $profile_picture = $user->profile_picture;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = ROOT_PATH . '/assets/uploads/profile_pictures/';
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        $file = $_FILES['profile_picture'];
        $file_type = mime_content_type($file['tmp_name']);
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $file_extension;
        $target_path = $upload_dir . $new_filename;
        
        // Create upload directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            $errors['profile_picture'] = 'Only JPG, PNG, and GIF files are allowed';
        }
        // Validate file size
        elseif ($file['size'] > $max_size) {
            $errors['profile_picture'] = 'File size must be less than 2MB';
        }
        // Move uploaded file
        elseif (move_uploaded_file($file['tmp_name'], $target_path)) {
            // Delete old profile picture if it exists and is not the default
            if (!empty($user->profile_picture) && strpos($user->profile_picture, 'default-avatar') === false) {
                $old_file = ROOT_PATH . '/' . $user->profile_picture;
                if (file_exists($old_file)) {
                    @unlink($old_file);
                }
            }
            $profile_picture = 'assets/uploads/profile_pictures/' . $new_filename;
        } else {
            $errors['profile_picture'] = 'Error uploading file. Please try again.';
        }
    }
    
    // If no errors, update user
    if (empty($errors)) {
        $user->name = $name;
        $user->email = $email;
        $user->profile_picture = $profile_picture;
        
        // For university students
        if ($user->user_type === 'university') {
            $user->university = $university;
            $user->department = $department;
            $user->level = $level;
        }
        
        // Update Privacy Settings
        $user->privacy_show_school = $privacy_show_school;
        $user->privacy_show_stats = $privacy_show_stats;
        $user->privacy_show_discussions = $privacy_show_discussions;
        $user->privacy_show_badges = $privacy_show_badges;
        $user->privacy_show_battles = $privacy_show_battles;
        $user->privacy_allow_messages = $privacy_allow_messages;
        $user->show_level_on_users_page = $show_level_on_users_page;
        
        try {
            if ($user->update()) {
                // update() never writes password_hash, so a new password must go
                // through the model's own hasher or it is silently discarded.
                $passwordSaved = empty($new_password) || $user->updatePassword($new_password);
                if (!$passwordSaved) {
                    error_log("Password update failed for user ID: " . $user->id);
                    $errors['general'] = 'Your profile details were saved, but the password could not be changed. Please try again.';
                } else {
                    // Update session data
                    $_SESSION['user_name'] = $user->name;
                    $_SESSION['user_email'] = $user->email;
                    $_SESSION['user_picture'] = $user->profile_picture;
                    
                    $success = true;
                    set_flash_message('success', 'Profile updated successfully!');
                    header('Location: ' . SITE_URL . '/profile.php');
                    exit;
                }
            } else {
                error_log("Profile update failed for user ID: " . $user->id . " - PDO errorInfo: " . json_encode($pdo->errorInfo()));
                $errors['general'] = 'Error updating profile. Please try again.';
            }
        } catch (Exception $e) {
            error_log("Profile update exception for user ID: " . $user->id . " - " . $e->getMessage());
            $errors['general'] = 'Error updating profile: ' . $e->getMessage();
        }
    }
}

// Set page title
$page_title = 'My Profile';

// Include header
include __DIR__ . '/../../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="position-relative d-inline-block mb-3">
                        <img src="<?php echo !empty($user->profile_picture) 
                            ? (SITE_URL . '/' . ltrim(htmlspecialchars($user->profile_picture), '/')) 
                            : (SITE_URL . '/assets/img/default-avatar.png'); ?>" 
                             alt="Profile Picture" 
                             class="rounded-circle" 
                             style="width: 150px; height: 150px; object-fit: cover; border: 5px solid #f8f9fa;">
                        <label for="profile_picture" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2" style="cursor: pointer;">
                            <i class="fas fa-camera"></i>
                        </label>
                    </div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($user->name); ?></h4>
                    <p class="text-muted mb-2"><?php echo ucfirst($user->user_type); ?> Student</p>
                    <?php if ($user->user_type === 'university'): ?>
                        <p class="mb-1"><?php echo htmlspecialchars($user->university ?? 'Not specified'); ?></p>
                        <p class="text-muted mb-3">
                            <?php 
                            $info = [];
                            if (!empty($user->department)) $info[] = htmlspecialchars($user->department);
                            if (!empty($user->level)) $info[] = htmlspecialchars($user->level);
                            echo implode(' - ', $info);
                            ?>
                        </p>
                    <?php endif; ?>
                    <p class="small text-muted mb-0">Member since <?php echo date('F Y', strtotime($user->created_at)); ?></p>
                </div>
            </div>
            
            <!-- Account Status -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Account Status</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-bold text-secondary">Email Security:</span>
                        <div>
                            <?php if ($user->email_verified): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2 rounded-pill shadow-sm">
                                    <i class="fas fa-shield-check me-1"></i> Verified & Secured
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-3 py-2 rounded-pill shadow-sm mb-2 d-inline-block">
                                    <i class="fas fa-exclamation-triangle me-1"></i> Action Required
                                </span>
                                <a href="<?php echo SITE_URL; ?>/verify-email.php" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold shadow-sm d-block mt-2" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border: none;">
                                    Verify Now <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Account Status:</span>
                        <span class="badge bg-<?php echo $user->status === 'active' ? 'success' : 'danger'; ?>">
                            <?php echo ucfirst($user->status); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            
            <!-- Battle Arena Link Card -->
            <div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); border-radius: 16px; overflow: hidden;">
                <div class="card-body d-flex justify-content-between align-items-center p-4">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-khanda text-warning" style="font-size: 2rem;"></i>
                        <div>
                            <h5 class="text-white fw-bold mb-1">Battle Arena</h5>
                            <p class="text-white-50 mb-0 small">Manage challenges, track battles & create invites</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (count($pendingChallenges) > 0): ?>
                            <span class="badge bg-danger rounded-pill shadow-sm px-3 py-2" style="font-size: 0.85rem;"><?php echo count($pendingChallenges); ?> Pending</span>
                        <?php endif; ?>
                        <a href="<?php echo SITE_URL; ?>/battle_arena.php" class="btn btn-warning fw-bold rounded-pill px-4 shadow-sm">
                            Go to Arena <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Profile Edit Form -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Edit Profile</h5>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i> Your profile has been updated successfully!
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($errors['general'])): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $errors['general']; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" id="profileForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="d-none" onchange="clearPasswordsAndSubmit()">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" 
                                       class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                       id="name" 
                                       name="name" 
                                       value="<?php echo htmlspecialchars($user->name); ?>" 
                                       required>
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" 
                                       class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($user->email); ?>" 
                                       required>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($user->user_type === 'university'): ?>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="university" class="form-label">University</label>
                                    <select class="form-select" id="university" name="university">
                                        <option value="">Select University</option>
                                        <?php if (!empty($universitiesList)): ?>
                                            <?php foreach ($universitiesList as $uni): ?>
                                                <option value="<?php echo htmlspecialchars($uni); ?>" <?php echo (isset($user->university) && $user->university === $uni) ? 'selected' : ''; ?>><?php echo htmlspecialchars($uni); ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <option value="Other" <?php echo (isset($user->university) && $user->university === 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="department" class="form-label">Department</label>
                                    <input type="text" 
                                           class="form-control <?php echo isset($errors['department']) ? 'is-invalid' : ''; ?>" 
                                           id="department" 
                                           name="department" 
                                           value="<?php echo htmlspecialchars($user->department ?? ''); ?>">
                                    <?php if (isset($errors['department'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['department']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="level" class="form-label">Level</label>
                                    <select class="form-select <?php echo isset($errors['level']) ? 'is-invalid' : ''; ?>" 
                                            id="level" 
                                            name="level"
                                            data-toggle-init="1">
                                        <option value="" <?php echo empty($user->level) ? 'selected' : ''; ?>>Select Level</option>
                                        <option value="100 Level" <?php echo $user->level === '100 Level' ? 'selected' : ''; ?>>100 Level</option>
                                        <option value="200 Level" <?php echo $user->level === '200 Level' ? 'selected' : ''; ?>>200 Level</option>
                                        <option value="300 Level" <?php echo $user->level === '300 Level' ? 'selected' : ''; ?>>300 Level</option>
                                        <option value="400 Level" <?php echo $user->level === '400 Level' ? 'selected' : ''; ?>>400 Level</option>
                                        <option value="500 Level" <?php echo $user->level === '500 Level' ? 'selected' : ''; ?>>500 Level</option>
                                        <option value="600 Level" <?php echo $user->level === '600 Level' ? 'selected' : ''; ?>>600 Level</option>
                                        <option value="Postgraduate" <?php echo $user->level === 'Postgraduate' ? 'selected' : ''; ?>>Postgraduate</option>
                                    </select>
                                    <?php if (isset($errors['level'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['level']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <hr class="my-4">
                        <h5 class="mb-3"><i class="fas fa-user-shield text-primary me-2"></i>Privacy Settings</h5>
                        <p class="text-muted small mb-4">Control what information is visible to the community on your public profile.</p>
                        
                        <div class="mb-3 form-check form-switch ps-5">
                            <input class="form-check-input" style="margin-left: -2.5em; width: 2.5em; height: 1.25em; cursor: pointer;" type="checkbox" role="switch" id="privacy_show_school" name="privacy_show_school" <?php echo $user->privacy_show_school ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-medium ms-2" for="privacy_show_school" style="cursor: pointer; padding-top: 0.15em;">Show my University / Secondary School Details</label>
                            <div class="text-muted small ms-2">If disabled, the community will not see where you study or your current academic level.</div>
                        </div>
                        
                        <div class="mb-3 form-check form-switch ps-5">
                            <input class="form-check-input" style="margin-left: -2.5em; width: 2.5em; height: 1.25em; cursor: pointer;" type="checkbox" role="switch" id="privacy_show_stats" name="privacy_show_stats" <?php echo $user->privacy_show_stats ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-medium ms-2" for="privacy_show_stats" style="cursor: pointer; padding-top: 0.15em;">Show my Total XP and Gamification Rank</label>
                            <div class="text-muted small ms-2">If disabled, your accumulated XP and Rank (e.g., Scholar) will be hidden from your profile.</div>
                        </div>
                        
                        <div class="mb-3 form-check form-switch ps-5">
                            <input class="form-check-input" style="margin-left: -2.5em; width: 2.5em; height: 1.25em; cursor: pointer;" type="checkbox" role="switch" id="privacy_show_discussions" name="privacy_show_discussions" <?php echo $user->privacy_show_discussions ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-medium ms-2" for="privacy_show_discussions" style="cursor: pointer; padding-top: 0.15em;">Show my Discussion Topics</label>
                            <div class="text-muted small ms-2">If disabled, the questions and topics you've posted in the community will not be listed on your profile.</div>
                        </div>
                        
                        <div class="mb-3 form-check form-switch ps-5">
                            <input class="form-check-input" style="margin-left: -2.5em; width: 2.5em; height: 1.25em; cursor: pointer;" type="checkbox" role="switch" id="privacy_show_badges" name="privacy_show_badges" <?php echo $user->privacy_show_badges ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-medium ms-2" for="privacy_show_badges" style="cursor: pointer; padding-top: 0.15em;">Show my Earned Certificates/Badges</label>
                            <div class="text-muted small ms-2">If disabled, the custom badges and quiz certificates you've earned will be hidden from the community.</div>
                        </div>

                        <div class="mb-4 form-check form-switch ps-5">
                            <input class="form-check-input" style="margin-left: -2.5em; width: 2.5em; height: 1.25em; cursor: pointer;" type="checkbox" role="switch" id="privacy_show_battles" name="privacy_show_battles" <?php echo (isset($user->privacy_show_battles) ? $user->privacy_show_battles : 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-medium ms-2" for="privacy_show_battles" style="cursor: pointer; padding-top: 0.15em;">Show my Recent Battles & Match History</label>
                            <div class="text-muted small ms-2">If disabled, your Battle Arena match history (Wins/Draws/Defeats) will be hidden from your public profile.</div>
                        </div>

                        <div class="mb-4 form-check form-switch ps-5">
                            <input class="form-check-input" style="margin-left: -2.5em; width: 2.5em; height: 1.25em; cursor: pointer;" type="checkbox" role="switch" id="privacy_allow_messages" name="privacy_allow_messages" <?php echo (isset($user->privacy_allow_messages) ? $user->privacy_allow_messages : 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-medium ms-2" for="privacy_allow_messages" style="cursor: pointer; padding-top: 0.15em;">Allow Direct Messages</label>
                            <div class="text-muted small ms-2">If disabled, other users will not be able to start new conversations with you.</div>
                        </div>

                        <div class="mb-4 form-check form-switch ps-5">
                            <input class="form-check-input" style="margin-left: -2.5em; width: 2.5em; height: 1.25em; cursor: pointer;" type="checkbox" role="switch" id="show_level_on_users_page" name="show_level_on_users_page" <?php echo (isset($user->show_level_on_users_page) ? $user->show_level_on_users_page : 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-medium ms-2" for="show_level_on_users_page" style="cursor: pointer; padding-top: 0.15em;">Show Level on Find Friends</label>
                            <div class="text-muted small ms-2">If disabled, your Gamification Level (e.g. Lvl 4 Guardian) will be hidden on the public 'Find Friends' directory page.</div>
                        </div>
                        
                        <hr class="my-4">
                        <h5 class="mb-3"><i class="fas fa-desktop text-primary me-2"></i>Display Preferences</h5>
                        
                        <div class="mb-4 form-check form-switch ps-5">
                            <input class="form-check-input" style="margin-left: -2.5em; width: 2.5em; height: 1.25em; cursor: pointer;" type="checkbox" role="switch" id="pref_quick_actions" onchange="toggleQuickAccessSetting(this)">
                            <label class="form-check-label fw-medium ms-2" for="pref_quick_actions" style="cursor: pointer; padding-top: 0.15em;">Show Quick Actions Floating Button</label>
                            <div class="text-muted small ms-2">Displays the floating quick action button on the bottom right of the screen for fast navigation.</div>
                        </div>

                        <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            var userId = '<?php echo $_SESSION['user_id'] ?? 0; ?>';
                            var quickAccessPref = localStorage.getItem('hideQuickAccess_user_' + userId);
                            var toggleInput = document.getElementById('pref_quick_actions');
                            if (toggleInput) {
                                // Checked if NOT hidden (default behavior)
                                toggleInput.checked = (quickAccessPref !== '1');
                            }
                        });

                        function toggleQuickAccessSetting(checkbox) {
                            var userId = '<?php echo $_SESSION['user_id'] ?? 0; ?>';
                            var isChecked = checkbox.checked;
                            if (isChecked) {
                                localStorage.removeItem('hideQuickAccess_user_' + userId);
                                // Try to show the button immediately if it exists on the page
                                var fab = document.getElementById('quickAccessFab');
                                if (fab) fab.style.display = 'flex';
                            } else {
                                localStorage.setItem('hideQuickAccess_user_' + userId, '1');
                                // Try to hide the button immediately if it exists on the page
                                var fab = document.getElementById('quickAccessFab');
                                if (fab) fab.style.display = 'none';
                            }
                            
                            // Optional small visual feedback
                            if (window.Swal && window.Swal.fire) {
                                const Toast = window.Swal.mixin({
                                    toast: true,
                                    position: 'bottom-end',
                                    showConfirmButton: false,
                                    timer: 2000,
                                    timerProgressBar: true
                                });
                                Toast.fire({
                                    icon: 'success',
                                    title: 'Preference saved'
                                });
                            }
                        }
                        </script>
                        
                        <hr class="my-4">
                        <h5 class="mb-3">Change Password</h5>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="current_password" class="form-label">Current Password</label>
                                <div class="input-group">
                                    <input type="password" autocomplete="off"
                                           class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" 
                                           id="current_password" 
                                           name="current_password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (isset($errors['current_password'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['current_password']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <small class="form-text text-muted">Leave blank to keep current password</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" autocomplete="off"
                                           class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" 
                                           id="new_password" 
                                           name="new_password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (isset($errors['new_password'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['new_password']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" autocomplete="off"
                                           class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                           id="confirm_password" 
                                           name="confirm_password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (isset($errors['confirm_password'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                            <a href="<?php echo SITE_URL; ?>/deactivate-account.php" class="text-danger" onclick="return confirm('Are you sure you want to deactivate your account? This action cannot be undone.');">
                                <i class="fas fa-user-slash me-1"></i> Deactivate Account
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom JavaScript for this page -->
<script>
// Clear password fields and submit form (for profile picture upload)
function clearPasswordsAndSubmit() {
    document.getElementById('current_password').value = '';
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_password').value = '';
    document.getElementById('profileForm').submit();
}

// Toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.querySelector(`button[onclick="togglePassword('${inputId}')"] i`);
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Initialize all toggle password buttons
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const input = this.previousElementSibling;
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

// Show file name when a new profile picture is selected
document.getElementById('profile_picture').addEventListener('change', function(e) {
    const fileName = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
    console.log('Selected file:', fileName);
});

// Form validation
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New password and confirm password do not match.');
        return false;
    }
    
    return true;
});
</script>

<?php if (isset($_GET['modal']) && $_GET['modal'] === 'insufficient_xp_accept'): ?>
<!-- Insufficient XP Modal -->
<div class="modal fade" id="insufficientXpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-body p-5 text-center bg-white">
        <div class="mb-4">
            <i class="fas fa-exclamation-triangle text-danger" style="font-size: 4rem;"></i>
        </div>
        <h3 class="fw-bold mb-3 text-dark">Insufficient XP</h3>
        <p class="text-muted mb-4 fs-5">You do not have enough XP to cover the wager for this battle. Your current balance is <strong class="text-primary"><?php echo number_format($user->points); ?> XP</strong>.</p>
        <button type="button" class="btn btn-danger rounded-pill px-5 py-2 fw-bold shadow-sm" data-bs-dismiss="modal">Got It</button>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var myModal = new bootstrap.Modal(document.getElementById('insufficientXpModal'));
    myModal.show();
});
</script>
<?php endif; ?>

<?php
// Include footer
include __DIR__ . '/../../includes/footer.php';
?>
