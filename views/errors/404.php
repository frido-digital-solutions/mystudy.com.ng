<?php
$page_title = '404 Page Not Found';
$hide_header = true;
$hide_footer = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Page Not Found - <?php echo SITE_NAME; ?></title>
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
            color: #2e7d32;
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
        }
        .btn-home:hover {
            background-color: #1b5e20;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <h1 class="error-message">Page Not Found</h1>
        <p class="error-description">
            Oops! The page you are looking for might have been removed, had its name changed,
            or is temporarily unavailable.
        </p>
        <a href="<?php echo SITE_URL; ?>" class="btn btn-home">
            <i class="fas fa-home me-2"></i> Back to Home
        </a>
    </div>
</body>
</html>
