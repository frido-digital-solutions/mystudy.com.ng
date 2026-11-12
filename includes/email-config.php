<?php
/**
 * Email Configuration for Production
 * This file ensures all email settings are properly configured for production
 */

// BREVO_API_KEY, BREVO_FROM_EMAIL, BREVO_FROM_NAME, BREVO_REPLY_TO_EMAIL and
// BREVO_REPLY_TO_NAME are defined in config/secrets.local.php (never committed).
// EmailService::fromConfig() returns null when they are absent, so a missing
// configuration skips email and logs it instead of fataling.

// Email Settings
if (!defined('EMAIL_ENABLED')) define('EMAIL_ENABLED', true);
if (!defined('EMAIL_LOG_ENABLED')) define('EMAIL_LOG_ENABLED', true);
if (!defined('EMAIL_DEBUG_MODE')) define('EMAIL_DEBUG_MODE', false); // Set to false in production

/**
 * Get production-ready email URLs
 */
function getEmailUrl($type, $token = null) {
    $base = SITE_URL;
    
    switch ($type) {
        case 'verify':
            return $base . '/verify-email.php?token=' . urlencode($token);
        case 'reset':
            return $base . '/reset-password.php?token=' . urlencode($token);
        case 'claim':
            return $base . '/rewards/claim/' . urlencode($token);
        case 'appeal':
            return $base . '/appeal/' . urlencode($token);
        case 'support':
            return $base . '/support/tickets/new';
        case 'guidelines':
            return $base . '/community-guidelines';
        case 'challenge_results':
            return $base . '/challenge/results';
        case 'quiz':
            return $base . '/quiz';
        default:
            return $base;
    }
}

/**
 * Generate secure tokens
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Log email activity (for debugging in production)
 */
function logEmailActivity($type, $email, $status, $details = '') {
    if (!EMAIL_LOG_ENABLED) return;
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $type,
        'email' => $email,
        'status' => $status,
        'details' => $details
    ];
    
    $logFile = __DIR__ . '/../logs/email_activity.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Production Email Service Factory
 */
function getProductionEmailService() {
    $emailService = EmailService::fromConfig();
    
    if (!$emailService) {
        error_log('Email service not configured properly');
        return null;
    }
    
    return $emailService;
}

/**
 * Send production email with error handling
 */
function sendProductionEmail($toEmail, $toName, $templateType, $templateData = []) {
    if (!EMAIL_ENABLED) {
        logEmailActivity($templateType, $toEmail, 'skipped', 'Email sending disabled');
        return false;
    }

    $emailService = getProductionEmailService();
    
    if (!$emailService) {
        logEmailActivity($templateType, $toEmail, 'failed', 'Email service not available');
        return false;
    }
    
    try {
        // Get template based on type
        $emailTemplate = getEmailTemplate($templateType, $templateData);
        
        if (!$emailTemplate) {
            logEmailActivity($templateType, $toEmail, 'failed', 'Invalid template type');
            return false;
        }
        
        $sent = $emailService->send(
            $toEmail,
            $toName,
            $emailTemplate['subject'],
            $emailTemplate['html'],
            $emailTemplate['text']
        );
        
        if ($sent) {
            logEmailActivity($templateType, $toEmail, 'sent', 'Email sent successfully');
            return true;
        } else {
            logEmailActivity($templateType, $toEmail, 'failed', 'Email service returned false');
            return false;
        }
        
    } catch (Exception $e) {
        logEmailActivity($templateType, $toEmail, 'failed', 'Exception: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get email template with production data
 */
function getEmailTemplate($type, $data) {
    $studentName = $data['student_name'] ?? 'Student';
    
    switch ($type) {
        case 'verify':
            $token = $data['token'] ?? generateSecureToken();
            $verifyUrl = getEmailUrl('verify', $token);
            return EmailTemplates::verifyEmail($studentName, $verifyUrl);
            
        case 'password':
            $token = $data['token'] ?? generateSecureToken();
            $resetUrl = getEmailUrl('reset', $token);
            return EmailTemplates::passwordReset($studentName, $resetUrl);
            
        case 'challenge_winner':
            return EmailTemplates::challengeResult(
                $studentName,
                $data['challenge_title'] ?? 'Challenge',
                $data['rank'] ?? 1,
                $data['total_participants'] ?? 100,
                $data['score_line'] ?? 'Score: 95/100',
                $data['results_url'] ?? getEmailUrl('challenge_results'),
                true,
                $data['prize_line'] ?? '🏆 Prize'
            );
            
        case 'access_code':
            return EmailTemplates::paymentAccessCode(
                $studentName,
                $data['quiz_title'] ?? 'Premium Content',
                $data['access_code'] ?? generateSecureToken(8),
                $data['quiz_url'] ?? getEmailUrl('quiz')
            );
            
        case 'reward_approved':
            return EmailTemplates::rewardClaimApproved(
                $studentName,
                $data['challenge_title'] ?? 'Challenge',
                $data['reward'] ?? 'Reward',
                $data['claim_url'] ?? getEmailUrl('claim', generateSecureToken(8))
            );
            
        case 'reward_rejected':
            return EmailTemplates::rewardClaimRejected(
                $studentName,
                $data['challenge_title'] ?? 'Challenge',
                $data['reason'] ?? 'Requirements not met',
                $data['support_url'] ?? getEmailUrl('support')
            );
            
        case 'reward_paid':
            return EmailTemplates::rewardClaimPaid(
                $studentName,
                $data['challenge_title'] ?? 'Challenge',
                $data['reward'] ?? 'Reward',
                $data['amount'] ?? '$0.00',
                $data['payment_details'] ?? 'Payment processed'
            );
            
        case 'content_removed':
            return EmailTemplates::moderationContentRemoved(
                $studentName,
                $data['content_type'] ?? 'content',
                $data['content_title'] ?? 'Removed Content',
                $data['reason'] ?? 'Guideline violation',
                $data['appeal_url'] ?? getEmailUrl('guidelines')
            );
            
        case 'suspension':
            return EmailTemplates::moderationSuspension(
                $studentName,
                $data['reason'] ?? 'Guideline violations',
                $data['duration_days'] ?? 7,
                $data['appeal_url'] ?? getEmailUrl('appeal', generateSecureToken(4)),
                $data['end_date'] ?? date('F j, Y \a\t g:i A UTC', strtotime('+7 days'))
            );
            
        case 'ban':
            return EmailTemplates::moderationBan(
                $studentName,
                $data['reason'] ?? 'Severe violations',
                $data['appeal_url'] ?? getEmailUrl('appeal', generateSecureToken(4))
            );
            
        case 'email_verification':
            return EmailTemplates::emailVerification(
                $data['name'] ?? 'User',
                $data['verify_url'] ?? '#'
            );
        case 'welcome':
            $siteUrl = defined('SITE_URL') ? SITE_URL : '';
            return EmailTemplates::welcomeEmail($studentName, $siteUrl);
            
        default:
            return null;
    }
}

/**
 * Send a battle-related email notification.
 * Looks up the user's email/name from DB and sends via Brevo.
 * Non-blocking — failures are logged but never thrown.
 *
 * @param PDO    $pdo       Database connection
 * @param int    $userId    Recipient user ID
 * @param string $subject   Email subject (e.g. the notification title)
 * @param string $message   Email body text (e.g. the notification message)
 * @param string $ctaUrl    CTA button link
 * @param string $eventType One of: challenge, accepted, declined, negotiate, turn, result
 */
function sendBattleEmail($pdo, $userId, $subject, $message, $ctaUrl, $eventType = 'challenge') {
    try {
        $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || empty($user['email'])) return;

        require_once __DIR__ . '/EmailService.php';
        require_once __DIR__ . '/EmailTemplates.php';

        $tpl = EmailTemplates::battleNotification(
            $user['name'] ?? 'Student',
            $subject,
            $message,
            $ctaUrl,
            $eventType
        );

        $emailService = getProductionEmailService();
        if ($emailService) {
            $emailService->send(
                $user['email'],
                $user['name'] ?? 'Student',
                $tpl['subject'],
                $tpl['html'],
                $tpl['text']
            );
        }
    } catch (Throwable $e) {
        error_log('Battle email failed: ' . $e->getMessage());
    }
}

?>
