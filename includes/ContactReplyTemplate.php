<?php
/**
 * Contact Reply Email Template
 * Used for replying to contact messages
 */

class ContactReplyTemplate {
    
    /**
     * Contact reply email template
     */
    public static function generate($data) {
        $subject = 'Re: Your Contact Message - ' . ($data['site_name'] ?? 'MyStudy Base');
        
        $html = self::baseLayout("
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h2 style='color: #10b981; margin-bottom: 20px;'>
                    <i class='fas fa-reply' style='margin-right: 10px;'></i>
                    Reply to Your Contact Message
                </h2>
                
                <div style='background: white; padding: 20px; border-radius: 6px; margin-bottom: 20px;'>
                    <h3 style='color: #333; margin-bottom: 15px;'>Your Original Message:</h3>
                    <div style='background: #f1f5f9; padding: 15px; border-left: 4px solid #3b82f6; margin-bottom: 20px;'>
                        <p style='margin: 0; color: #333; font-style: italic;'>
                            " . nl2br(htmlspecialchars($data['original_message'])) . "
                        </p>
                    </div>
                </div>
                
                <div style='background: white; padding: 20px; border-radius: 6px;'>
                    <h3 style='color: #10b981; margin-bottom: 15px;'>Admin Reply:</h3>
                    <div style='background: #ecfdf5; padding: 15px; border-left: 4px solid #10b981;'>
                        <p style='margin: 0; color: #333;'>
                            " . nl2br(htmlspecialchars($data['admin_reply'])) . "
                        </p>
                    </div>
                </div>
                
                <div style='text-align: center; margin-top: 30px;'>
                    <p style='color: #6b7280; font-size: 14px;'>
                        This is an automated reply to your contact message.<br>
                        If you have additional questions, feel free to contact us again.
                    </p>
                    <div style='margin-top: 20px;'>
                        <a href='" . (defined('SITE_DOMAIN') ? SITE_DOMAIN : SITE_URL) . "/contact.php' 
                           style='background: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>
                            <i class='fas fa-envelope' style='margin-right: 8px;'></i>
                            Contact Us Again
                        </a>
                    </div>
                </div>
            </div>
        ", $data['site_name'] ?? 'MyStudy Base');
        
        $text = "
REPLY TO YOUR CONTACT MESSAGE
========================

Your Original Message:
" . $data['original_message'] . "

Admin Reply:
" . $data['admin_reply'] . "

This is an automated reply to your contact message from " . ($data['site_name'] ?? 'MyStudy Base') . ".
If you have additional questions, please visit our website at " . SITE_URL . "
        ";
        
        return compact('html', 'text', 'subject');
    }
    
    /**
     * Base layout for email template
     */
    private static function baseLayout($content, $siteName = 'MyStudy Base') {
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
                <div style='background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center;'>
                    <h1 style='margin: 0; font-size: 28px; font-weight: bold;'>
                        <i class='fas fa-shield-alt' style='margin-right: 10px;'></i>
                        {$siteName}
                    </h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;'>
                        Professional Learning Platform
                    </p>
                </div>
                {$content}
                <div style='background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef;'>
                    <p style='margin: 0; color: #6b7280; font-size: 12px;'>
                        &copy; " . date('Y') . " {$siteName}. All rights reserved.<br>
                        This is an automated message, please do not reply to this email.
                    </p>
                    <div style='margin-top: 20px;'>
                        <a href='" . SITE_URL . "' style='color: #10b981; text-decoration: none; font-weight: bold;'>
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
