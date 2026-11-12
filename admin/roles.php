<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('users')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}
$page_title = 'Roles & Permissions';
include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h4 mb-0">Roles & Permissions</h1>
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="card h-100">
            <div class="card-header bg-white"><strong>Roles</strong></div>
            <div class="card-body">
              <ul>
                <li><strong>admin</strong>: Full access</li>
                <li><strong>university</strong>: University student</li>
                <li><strong>secondary</strong>: Secondary student</li>
              </ul>
              <p class="text-muted small mb-0">Roles are fixed and are not edited on this page. To make a user an admin, or to revoke admin access, use <a href="<?php echo SITE_URL; ?>/admin/admins.php">Manage Admins</a> (super admins only).</p>
            </div>
          </div>
        </div>
        <div class="col-md-8">
          <div class="card h-100">
            <div class="card-header bg-white"><strong>Permissions Matrix</strong></div>
            <div class="card-body">
              <p class="text-muted mb-0">This page is read-only information and saves nothing. The permissions that actually control access to materials, quizzes, posts and the admin panel are granted per admin from <a href="<?php echo SITE_URL; ?>/admin/admins.php">Manage Admins</a> (super admins only).</p>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

