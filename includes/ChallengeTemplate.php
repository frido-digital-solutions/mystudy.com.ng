<?php
/**
 * Challenge Email Template
 * Used for sending challenge notifications
 */

class ChallengeTemplate {
    
    /**
     * Challenge notification email template
     */
    public static function generate($data) {
        $subject = '🎯 Challenge Received - ' . ($data['site_name'] ?? 'MyStudy');
        
        $html = self::baseLayout("
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h2 style='color: #dc2626; margin-bottom: 20px;'>
                    <i class='fas fa-trophy' style='margin-right: 10px;'></i>
                    Challenge Received!
                </h2>
                
                <div style='background: white; padding: 20px; border-radius: 6px; margin-bottom: 20px;'>
                    <h3 style='color: #333; margin-bottom: 15px;'>Challenge Details:</h3>
                    
                    <div style='background: #fef3c7; padding: 15px; border-left: 4px solid #f59e0b; margin-bottom: 15px;'>
                        <p style='margin: 0; color: #333;'><strong>From:</strong> " . htmlspecialchars($data['challenger_name']) . "</p>
                        <p style='margin: 10px 0 0 0; color: #333;'><strong>Quiz Topic:</strong> " . htmlspecialchars($data['quiz_topic']) . "</p>
                        <p style='margin: 10px 0 0 0; color: #333;'><strong>Challenge Type:</strong> " . htmlspecialchars($data['challenge_type']) . "</p>
                        <p style='margin: 10px 0 0 0; color: #333;'><strong>Stake Amount:</strong> " . htmlspecialchars($data['stake_amount']) . "</p>
                        <p style='margin: 10px 0 0 0; color: #333;'><strong>Challenge Message:</strong> " . nl2br(htmlspecialchars($data['challenge_message'])) . "</p>
                    </div>
                </div>
                
                <div style='background: white; padding: 20px; border-radius: 6px;'>
                    <h3 style='color: #10b981; margin-bottom: 15px;'>How to Respond:</h3>
                    <div style='background: #ecfdf5; padding: 15px; border-left: 4px solid #10b981;'>
                        <p style='margin: 0; color: #333;'>
                            You have received a challenge from another user. You can accept or decline this challenge from your dashboard.
                        </p>
                        <div style='text-align: center; margin-top: 20px;'>
                            <a href='" . (defined('SITE_DOMAIN') ? SITE_DOMAIN : SITE_URL) . "/dashboard.php' 
                               style='background: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>
                                <i class='fas fa-tachometer-alt' style='margin-right: 8px;'></i>
                                Go to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
                
                <div style='text-align: center; margin-top: 30px;'>
                    <p style='color: #6b7280; font-size: 14px;'>
                        This is an automated notification about a challenge request.<br>
                        If you did not expect this challenge, please contact support.
                    </p>
                </div>
            </div>
        ", $data['site_name'] ?? 'MyStudy');
        
        $text = "
CHALLENGE RECEIVED
==================

You have received a challenge from another user!

Challenge Details:
- From: " . $data['challenger_name'] . "
- Quiz Topic: " . $data['quiz_topic'] . "
- Challenge Type: " . $data['challenge_type'] . "
- Stake Amount: " . $data['stake_amount'] . "
- Message: " . $data['challenge_message'] . "

How to Respond:
Visit your dashboard to accept or decline this challenge: " . SITE_URL . "/dashboard.php

This is an automated notification from " . ($data['site_name'] ?? 'MyStudy') . ".
        ";
        
        return compact('html', 'text', 'subject');
    }
    
    /**
     * Base layout for email template
     */
    private static function baseLayout($content, $siteName = 'MyStudy') {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$siteName}</title>
        </head>
        <body style='margin: 0; padding: 0; background: #f3f4f6; font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 40px auto; background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden;'>
                <div style='background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 28px; font-weight: bold;'>
                        <i class='fas fa-trophy' style='margin-right: 10px;'></i>
                        {$siteName}
                    </h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>
                        Challenge Notification
                    </p>
                </div>
                {$content}
                <div style='background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef;'>
                    <p style='margin: 0; color: #6b7280; font-size: 12px;'>
                        &copy; " . date('Y') . " {$siteName}. All rights reserved.<br>
                        This is an automated message, please do not reply to this email.
                    </p>
                    <div style='margin-top: 20px;'>
                        <a href='" . SITE_URL . "' style='color: #dc2626; text-decoration: none; font-weight: bold;'>
                            Visit Website
                        </a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}

?>
