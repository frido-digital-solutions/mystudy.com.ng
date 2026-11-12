<?php
// settings.php - User account settings
require_once __DIR__ . '/config/config.php';
require_login();

$user = new User($pdo);
$user->getById($_SESSION['user_id']);

$page_title = 'Settings';
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2 class="fw-bold mb-4"><i class="fas fa-cog me-2"></i>Settings</h2>

            <!-- Account Section -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="fas fa-user me-2 text-primary"></i>Account</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <strong>Full Name</strong>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($user->name); ?></p>
                        </div>
                        <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-sm btn-outline-primary rounded-pill">Edit</a>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <strong>Email Address</strong>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($user->email); ?></p>
                        </div>
                        <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-sm btn-outline-primary rounded-pill">Edit</a>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Account Type</strong>
                            <p class="text-muted mb-0"><?php echo ($user->user_type === 'university') ? 'University Student' : 'Secondary Student'; ?></p>
                        </div>
                        <span class="badge bg-<?php echo $user->status === 'active' ? 'success' : 'danger'; ?>"><?php echo ucfirst($user->status); ?></span>
                    </div>
                </div>
            </div>

            <!-- Security Section -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="fas fa-shield-alt me-2 text-warning"></i>Security</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Password</strong>
                            <p class="text-muted mb-0">Change your account password</p>
                        </div>
                        <a href="<?php echo SITE_URL; ?>/profile.php#password" class="btn btn-sm btn-outline-warning rounded-pill">
                            <i class="fas fa-key me-1"></i>Change
                        </a>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="card border-danger">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Deactivate Account</strong>
                            <p class="text-muted mb-0">Permanently deactivate your account. This cannot be undone.</p>
                        </div>
                        <a href="<?php echo SITE_URL; ?>/deactivate-account.php" class="btn btn-sm btn-outline-danger rounded-pill"
                           onclick="return confirm('Are you sure you want to deactivate your account?');">
                            <i class="fas fa-user-slash me-1"></i>Deactivate
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
