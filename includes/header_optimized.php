<?php
// Performance-optimized header
if (!defined('SITE_NAME')) exit;

// Performance headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Cache control for static assets
$cache_time = 2592000; // 30 days
header("Cache-Control: public, max-age=$cache_time");

// Enable compression if available
if (!ob_get_level() && function_exists('ob_gzhandler')) {
    ob_start("ob_gzhandler");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? SITE_NAME); ?></title>
    
    <!-- Preconnect to external domains -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Google Fonts (optimized) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Optimized CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/design-system.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/favicon.ico">
    
    <!-- Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($meta_description ?? 'Premium learning platform for Nigerian students'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords ?? 'education, learning, WAEC, JAMB, Nigeria'); ?>">
    <meta name="author" content="<?php echo SITE_NAME; ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title ?? SITE_NAME); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_description ?? 'Premium learning platform for Nigerian students'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/assets/images/og-image.jpg">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title ?? SITE_NAME); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($meta_description ?? 'Premium learning platform for Nigerian students'); ?>">
    
    <!-- jQuery (optimized) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    
    <!-- Font Awesome (optimized) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <?php if (isset($extra_head)): ?>
        <?php echo $extra_head; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Skip to main content for accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>
