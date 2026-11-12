<?php
/**
 * Admin Maintenance Control - Special bypass for maintenance mode
 * This page allows admins to disable maintenance mode even when it's active
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/maintenance-functions.php';

// Verify admin access
if (!is_logged_in() || !has_role('admin')) {
    header('Location: ' . SITE_URL . '/admin/login.php?secure=FridoAdminAccess');
    exit;
}

if (!admin_can('maintenance')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    header('Location: ' . SITE_URL . '/admin/dashboard.php');
    exit;
}

$page_title = 'Maintenance Control';

// Handle maintenance toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid form token. Please try again.');
        header('Location: ' . SITE_URL . '/admin/maintenance-control.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'disable_maintenance') {
        if (disable_maintenance_mode()) {
            log_admin_activity($pdo, 'Disabled Maintenance', 'Disabled site maintenance mode');
            set_flash_message('success', 'Maintenance mode disabled successfully!');
            header('Location: ' . SITE_URL . '/admin/dashboard.php');
            exit;
        } else {
            $error = 'Failed to disable maintenance mode';
        }
    }
    
    if ($action === 'enable_maintenance') {
        $message = trim($_POST['maintenance_message'] ?? '');
        if (enable_maintenance_mode($message)) {
            log_admin_activity($pdo, 'Enabled Maintenance', 'Enabled site maintenance mode');
            set_flash_message('success', 'Maintenance mode enabled! All users are now blocked.');
            header('Location: ' . SITE_URL . '/admin/maintenance-control.php');
            exit;
        } else {
            $error = 'Failed to enable maintenance mode';
        }
    }
}

// Get current maintenance status
$maintenance_active = is_maintenance_mode();
$maintenance_message = '';

try {
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'maintenance_message'");
    $stmt->execute();
    $maintenance_message = $stmt->fetchColumn() ?: '';
} catch (Exception $e) {
    $maintenance_message = '';
}

// Use special admin header that bypasses maintenance check
include __DIR__ . '/includes/header-no-maintenance.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      
      <!-- Page Header -->
      <div class="page-header">
        <div>
          <h1><i class="fas fa-tools me-2" style="color: var(--admin-primary);"></i> Maintenance Control</h1>
          <p class="text-muted mb-0">Control site-wide maintenance mode</p>
        </div>
      </div>

      <?php echo display_flash_message(); ?>

      <?php if ($maintenance_active): ?>
        <!-- Maintenance Active Warning -->
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
          <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
            <div>
              <strong>⚠️ MAINTENANCE MODE IS ACTIVE</strong><br>
              <span class="small">All users are currently blocked from accessing the site. Only you can disable it.</span>
            </div>
          </div>
        </div>

        <!-- Disable Maintenance -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="fas fa-power-off me-2"></i>Disable Maintenance Mode</h5>
          </div>
          <div class="card-body">
            <p class="text-muted mb-4">Disabling maintenance mode will immediately restore full access to all users.</p>
            
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
              <input type="hidden" name="action" value="disable_maintenance">
              <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Are you sure you want to disable maintenance mode?')">
                <i class="fas fa-check-circle me-2"></i> Disable Maintenance Mode
              </button>
            </form>
          </div>
        </div>

        <!-- Current Status -->
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">Current Status</h5>
          </div>
          <div class="card-body">
            <table class="table table-borderless">
              <tr>
                <td width="150"><strong>Status:</strong></td>
                <td><span class="badge bg-danger fs-6">MAINTENANCE ACTIVE</span></td>
              </tr>
              <tr>
                <td><strong>Message:</strong></td>
                <td><?php echo htmlspecialchars($maintenance_message) ?: 'Default maintenance message'; ?></td>
              </tr>
              <tr>
                <td><strong>Started:</strong></td>
                <td><?php echo date('Y-m-d H:i:s'); ?></td>
              </tr>
              <tr>
                <td><strong>Impact:</strong></td>
                <td><span class="text-danger">ALL users blocked (including admins)</span></td>
              </tr>
            </table>
          </div>
        </div>

      <?php else: ?>
        <!-- Maintenance Inactive -->
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
          <div class="d-flex align-items-center">
            <i class="fas fa-check-circle me-3 fs-4"></i>
            <div>
              <strong>✅ Site is Operating Normally</strong><br>
              <span class="small">Maintenance mode is currently disabled. All users have full access.</span>
            </div>
          </div>
        </div>

        <!-- Enable Maintenance -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Enable Maintenance Mode</h5>
          </div>
          <div class="card-body">
            <div class="alert alert-warning mb-4">
              <strong>⚠️ WARNING:</strong> Enabling maintenance mode will immediately block ALL access to the site, including:
              <ul class="mb-0 mt-2">
                <li>All logged-in users (will be logged out)</li>
                <li>Admin access (except this control page)</li>
                <li>Direct links to any page</li>
                <li>API endpoints and services</li>
              </ul>
            </div>
            
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
              <input type="hidden" name="action" value="enable_maintenance">
              
              <div class="mb-3">
                <label for="maintenance_message" class="form-label">Custom Maintenance Message (optional)</label>
                <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="3" placeholder="Enter a custom message to show users during maintenance..."><?php echo htmlspecialchars($maintenance_message); ?></textarea>
                <div class="form-text">This message will be displayed on the maintenance page. Leave blank for default message.</div>
              </div>
              
              <button type="submit" class="btn btn-warning btn-lg" onclick="return confirm('⚠️ Are you absolutely sure? This will block ALL access to the site immediately!')">
                <i class="fas fa-tools me-2"></i> Enable Maintenance Mode
              </button>
            </form>
          </div>
        </div>

        <!-- Quick Test -->
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">Quick Test</h5>
          </div>
          <div class="card-body">
            <p class="text-muted mb-3">Test maintenance mode by visiting any page while it's enabled:</p>
            <div class="d-flex gap-2">
              <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-outline-primary" target="_blank">
                <i class="fas fa-home me-1"></i> Test Homepage
              </a>
              <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="btn btn-outline-secondary" target="_blank">
                <i class="fas fa-tachometer-alt me-1"></i> Test Admin Dashboard
              </a>
              <a href="<?php echo SITE_URL; ?>/maintenance.php" class="btn btn-outline-info" target="_blank">
                <i class="fas fa-eye me-1"></i> View Maintenance Page
              </a>
            </div>
          </div>
        </div>

      <?php endif; ?>

    </main>
  </div>
</div>

<?php include __DIR__ . '/includes/footer-no-maintenance.php'; ?>

