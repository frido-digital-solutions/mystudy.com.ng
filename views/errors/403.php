<?php
$page_title = '403 Forbidden';
$hide_header = true;
$hide_footer = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
        }
        .error-code {
            font-size: 8rem;
            font-weight: 700;
            color: #d32f2f;
            line-height: 1;
            margin-bottom: 1rem;
        }
        .error-message {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: #333;
        }
        .error-description {
            color: #6c757d;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }
        .btn-home {
            background-color: #2e7d32;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            margin-right: 1rem;
        }
        .btn-login {
            background-color: #1976d2;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-home:hover, .btn-login:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .btn-home:hover {
            background-color: #1b5e20;
        }
        .btn-login:hover {
            background-color: #1565c0;
        }
        .btn-container {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">403</div>
        <h1 class="error-message">Access Forbidden</h1>
        <p class="error-description">
            You don't have permission to access this page. 
            Please check your account and try again or contact the administrator.
        </p>
        <div class="btn-container">
            <a href="<?php echo SITE_URL; ?>" class="btn-home">
                <i class="fas fa-home me-2"></i> Back to Home
            </a>
            <?php if (!is_logged_in()): ?>
            <a href="<?php echo SITE_URL; ?>/login" class="btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Login
            </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
