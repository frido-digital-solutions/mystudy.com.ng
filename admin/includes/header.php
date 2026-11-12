<?php
// Start output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?> Admin</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/img/favicon.png">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Admin CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg admin-navbar">
        <div class="container-fluid">
            <button class="btn btn-link text-dark d-lg-none me-2 p-0" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle sidebar">
                <i class="fas fa-bars" style="font-size:1.2rem;"></i>
            </button>
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>/admin/">
                <i class="fas fa-graduation-cap"></i>
                <span><?php echo SITE_NAME; ?></span>
            </a>
            <div class="ms-auto d-flex align-items-center gap-3">
                <a href="<?php echo SITE_URL; ?>/" class="btn btn-sm btn-outline-secondary d-none d-md-inline-flex align-items-center gap-1" target="_blank">
                    <i class="fas fa-external-link-alt"></i> View Site
                </a>
                <div class="dropdown">
                    <a class="admin-user-pill dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?php echo SITE_URL; ?>/assets/img/default-avatar.png" alt="Admin">
                        <span class="d-none d-md-inline">Admin</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 12px; min-width: 200px;">
                        <li><a class="dropdown-item py-2" href="<?php echo SITE_URL; ?>/profile.php"><i class="fas fa-user me-2 text-muted"></i>Profile</a></li>
                        <li><a class="dropdown-item py-2" href="<?php echo SITE_URL; ?>/settings.php"><i class="fas fa-cog me-2 text-muted"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="<?php echo SITE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar backdrop script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var sidebarBtn = document.querySelector('[data-bs-target="#sidebar"]');
        
        if (sidebar && sidebarBtn) {
            // Watch for Bootstrap collapse events
            sidebar.addEventListener('show.bs.collapse', function () {
                document.body.classList.add('sidebar-open');
            });
            
            sidebar.addEventListener('hide.bs.collapse', function () {
                document.body.classList.remove('sidebar-open');
            });

            // Close sidebar when clicking the backdrop
            document.body.addEventListener('click', function(e) {
                if (window.innerWidth < 992 && document.body.classList.contains('sidebar-open')) {
                    var isClickInsideSidebar = sidebar.contains(e.target);
                    var isClickOnButton = sidebarBtn.contains(e.target);
                    
                    if (!isClickInsideSidebar && !isClickOnButton) {
                        var bsCollapse = bootstrap.Collapse.getInstance(sidebar);
                        if (bsCollapse) {
                            bsCollapse.hide();
                        }
                    }
                }
            });
        }
    });
    </script>
