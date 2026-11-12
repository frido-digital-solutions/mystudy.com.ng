<?php
/**
 * TEMPLATE ONLY — contains no real secrets.
 *
 * Copy this file to "secrets.local.php" in the same folder and fill in the
 * real values for the server you are on. secrets.local.php is gitignored,
 * so it never reaches GitHub and is never overwritten by a deployment.
 *
 *   cPanel → File Manager → config/ → copy secrets.sample.php → secrets.local.php
 */

// --- Database (cPanel → MySQL Databases on THIS hosting account) ---
define('DB_HOST', 'localhost');
define('DB_USER', 'your_cpanel_db_user');
define('DB_PASS', 'your_database_password');
define('DB_NAME', 'your_cpanel_db_name');

// --- Paystack (dashboard.paystack.com → Settings → API Keys & Webhooks) ---
define('PAYSTACK_PUBLIC_KEY', 'pk_live_xxxxxxxxxxxxxxxxxxxxxxxx');
define('PAYSTACK_SECRET_KEY', 'sk_live_xxxxxxxxxxxxxxxxxxxxxxxx');

// --- Brevo transactional email (app.brevo.com → SMTP & API → API Keys) ---
define('BREVO_API_KEY', 'xkeysib-xxxxxxxxxxxxxxxxxxxxxxxx');

// The sender domain must be verified inside Brevo or every email is rejected.
define('BREVO_FROM_EMAIL', 'no-reply@yourdomain.ng');
define('BREVO_FROM_NAME', 'MyStudyBase');
define('BREVO_REPLY_TO_EMAIL', 'support@yourdomain.ng');
define('BREVO_REPLY_TO_NAME', 'MyStudyBase Support');

// --- Public site identity ---
define('SITE_DOMAIN', 'yourdomain.ng');
// Fallback only. The address actually used is the "Admin Email" field on the
// admin Settings page; this applies if that field is left blank.
define('ADMIN_EMAIL_DEFAULT', 'admin@yourdomain.ng');