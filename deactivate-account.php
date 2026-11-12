<?php
require_once __DIR__ . '/config/config.php';
require_login();

$page_title = 'Deactivate Account';
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-user-slash text-danger mb-3" style="font-size: 3rem;"></i>
                    <h3 class="mb-3">Account Deactivation</h3>
                    <p class="text-muted mb-4">
                        This feature is not yet available. If you need to deactivate your account, 
                        please contact our support team.
                    </p>
                    <a href="<?php echo SITE_URL; ?>/contact.php" class="btn btn-outline-primary rounded-pill px-4 me-2">
                        <i class="fas fa-envelope me-2"></i>Contact Support
                    </a>
                    <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-outline-secondary rounded-pill px-4">
                        <i class="fas fa-arrow-left me-2"></i>Back to Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
