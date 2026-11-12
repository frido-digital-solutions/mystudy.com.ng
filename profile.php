<?php
// Include configuration and initialize database connection
require_once 'config/config.php';

// Require login to access this page
require_login();

// Include the profile view
require_once 'views/user/profile.php';
?>
