<?php
/**
 * Admin Header - Bypasses Maintenance Check
 * This header is used for pages that need to work even during maintenance
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define page title if not set
if (!isset($page_title)) {
    $page_title = 'Admin Panel';
}

// Include the regular header but without maintenance check
// We need to copy the header content here without the maintenance check
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin | <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/admin.css" rel="stylesheet">
    
    <style>
        :root {
            --admin-primary: #10b981;
            --admin-secondary: #6366f1;
            --admin-success: #059669;
            --admin-danger: #dc2626;
            --admin-warning: #d97706;
            --admin-dark: #1e293b;
            --admin-light: #f8fafc;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            font-size: 14px;
        }
        
        .page-header {
            padding: 2rem 0 1rem 0;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--admin-dark);
            margin: 0;
        }
        
        .page-header p {
            color: #64748b;
            margin: 0.25rem 0 0 0;
            font-size: 0.875rem;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: linear-gradient(135deg, var(--admin-dark), #334155);
            padding: 1rem;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-brand {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .sidebar-brand:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .section {
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 1rem 0 0.5rem 0;
            padding-left: 1.25rem;
        }
        
        .nav-link {
            color: #cbd5e1;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
            transition: all 0.2s;
        }
        
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .nav-link.active {
            background: var(--admin-primary);
            color: white;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php
    // Auto-evaluate ended challenges globally (if not already done)
    if (!defined('CHALLENGES_EVALUATED')) {
        try {
            if (isset($pdo)) {
                require_once __DIR__ . '/../../models/Quiz.php';
                $quizTemp = new Quiz($pdo);
                $quizTemp->processEndedChallenges($pdo);
            }
        } catch (Exception $e) {}
        define('CHALLENGES_EVALUATED', true);
    }

    // Track user activity
    if (isset($_SESSION['user_id']) && isset($pdo)) {
        try {
            require_once __DIR__ . '/../../models/User.php';
            $actUser = new User($pdo);
            $actUser->id = (int)$_SESSION['user_id'];
            $actUser->updateLastActive();
        } catch (Exception $e) {}
    }
    ?>
