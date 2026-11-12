<?php
/**
 * Premium, clean HTML email templates (inline-ish styles for broad compatibility).
 */
class EmailTemplates {
    private static function baseLayout(string $title, string $preheader, string $bodyHtml, string $ctaLabel = '', string $ctaUrl = ''): string {
        $site = defined('SITE_NAME') ? SITE_NAME : 'MyStudy Base';
        $siteUrl = defined('SITE_URL') ? SITE_URL : '';
        $brand = '#10b981';
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safePre = htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8');

        // Fetch site logo from database
        $logoHtml = '';
        try {
            global $pdo;
            if (isset($pdo)) {
                $logoStmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'site_logo'");
                $logoStmt->execute();
                $logoPath = $logoStmt->fetchColumn();
                if ($logoPath) {
                    // Always use the production domain for email images
                    // Email clients (Gmail, Yahoo, etc.) cannot reach localhost
                    $prodDomain = defined('SITE_DOMAIN') ? ('https://' . SITE_DOMAIN) : $siteUrl;
                    $logoUrl = $prodDomain . '/' . ltrim($logoPath, '/');
                    $logoHtml = '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '" style="max-height:40px;max-width:180px;vertical-align:middle;margin-right:10px;" />';
                }
            }
        } catch (Throwable $e) {
            // Silent — fall back to text-only header
        }

        $cta = '';
        if ($ctaLabel && $ctaUrl) {
            $cta = '
            <div style="padding: 16px 28px 0;">
              <a href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:' . $brand . ';color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:999px;font-weight:800;">
                ' . htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') . '
              </a>
            </div>';
        }

        return '<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>' . $safeTitle . '</title>
</head>
<body style="margin:0;background:#f1f5f9;font-family:Inter,Segoe UI,Arial,sans-serif;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . $safePre . '</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,0.08);">
          <tr>
            <td style="padding:18px 22px;background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;">
              <div style="display:flex;align-items:center;">
                ' . $logoHtml . '
                <div>
                  <div style="font-weight:900;letter-spacing:-0.3px;font-size:18px;">' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '</div>
                  <div style="color:#cbd5e1;font-weight:700;font-size:12px;margin-top:4px;">' . $safeTitle . '</div>
                </div>
              </div>
            </td>
          </tr>
          <tr>
            <td style="padding:22px 28px 8px;color:#0f172a;">
              ' . $bodyHtml . '
            </td>
          </tr>
          <tr><td>' . $cta . '</td></tr>
          <tr>
            <td style="padding:18px 28px 22px;color:#64748b;font-size:12.5px;line-height:1.55;">
              <div style="border-top:1px solid #e2e8f0;padding-top:14px;">
                If you didn’t request this, you can ignore this email.<br>
                <span style="color:#94a3b8;">— ' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . ' Team</span>
              </div>
            </td>
          </tr>
        </table>
        <div style="max-width:640px;color:#94a3b8;font-size:11px;line-height:1.6;padding:10px 6px 0;text-align:center;">
          You’re receiving this because you have an account on ' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '.
        </div>
      </td>
    </tr>
  </table>
</body>
</html>';
    }

    public static function verifyEmail(string $studentName, string $verifyUrl): array {
        $body = '
          <h2 style="margin:0 0 10px;font-size:20px;line-height:1.25;">Verify your email</h2>
          <p style="margin:0 0 10px;color:#334155;font-size:14.5px;line-height:1.7;">
            Hi <strong>' . htmlspecialchars($studentName ?: 'Student', ENT_QUOTES, 'UTF-8') . '</strong>, welcome to MyStudy.
            Please confirm your email address to secure your account.
          </p>
          <p style="margin:0;color:#64748b;font-size:13.5px;line-height:1.7;">
            This link may expire. If the button doesn’t work, copy and paste this URL:<br>
            <span style="word-break:break-all;color:#0f172a;">' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '</span>
          </p>';
        return [
            'subject' => 'Verify your email - ' . (defined('SITE_NAME') ? SITE_NAME : 'MyStudy Base'),
            'html' => self::baseLayout('Email Verification', 'Confirm your email address to finish setup.', $body, 'Verify Email', $verifyUrl),
            'text' => "Hi {$studentName},\n\nVerify your email: {$verifyUrl}\n\n— MyStudy Team",
        ];
    }

    public static function passwordReset(string $studentName, string $resetUrl): array {
        $body = '
          <h2 style="margin:0 0 10px;font-size:20px;line-height:1.25;">Reset your password</h2>
          <p style="margin:0 0 10px;color:#334155;font-size:14.5px;line-height:1.7;">
            Hi <strong>' . htmlspecialchars($studentName ?: 'Student', ENT_QUOTES, 'UTF-8') . '</strong>, we received a request to reset your password.
          </p>
          <p style="margin:0;color:#64748b;font-size:13.5px;line-height:1.7;">
            If you didn’t request this, ignore this email. Otherwise, use the button below to set a new password.
            <br><br>
            Or copy and paste this URL:<br>
            <span style="word-break:break-all;color:#0f172a;">' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '</span>
          </p>';
        return [
            'subject' => 'Reset your password - ' . (defined('SITE_NAME') ? SITE_NAME : 'MyStudy Base'),
            'html' => self::baseLayout('Password Reset', 'Use this link to reset your password.', $body, 'Reset Password', $resetUrl),
            'text' => "Hi {$studentName},\n\nReset password: {$resetUrl}\n\n— MyStudy Team",
        ];
    }

    public static function challengeResult(string $studentName, string $quizTitle, int $rank, int $totalParticipants, string $scoreLine, string $ctaUrl, bool $isWinner, string $prizeLine = ''): array {
        $headline = $isWinner ? 'You placed in the Top 3!' : 'Your challenge results are ready';
        $body = '
          <h2 style="margin:0 0 10px;font-size:20px;line-height:1.25;">' . htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') . '</h2>
          <p style="margin:0 0 12px;color:#334155;font-size:14.5px;line-height:1.7;">
            Hi <strong>' . htmlspecialchars($studentName ?: 'Student', ENT_QUOTES, 'UTF-8') . '</strong>, the challenge <strong>' . htmlspecialchars($quizTitle, ENT_QUOTES, 'UTF-8') . '</strong> has ended.
          </p>
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:12px 14px;margin:0 0 10px;">
            <div style="font-weight:900;color:#0f172a;">Rank: #' . (int)$rank . ' <span style="color:#94a3b8;font-weight:700;">/ ' . (int)$totalParticipants . '</span></div>
            <div style="color:#334155;margin-top:4px;font-weight:700;">' . htmlspecialchars($scoreLine, ENT_QUOTES, 'UTF-8') . '</div>
            ' . ($prizeLine ? '<div style="color:#0f172a;margin-top:6px;font-weight:800;">' . htmlspecialchars($prizeLine, ENT_QUOTES, 'UTF-8') . '</div>' : '') . '
          </div>
          <p style="margin:0;color:#64748b;font-size:13.5px;line-height:1.7;">
            ' . ($isWinner ? 'Claim your reward using the button below.' : 'View the leaderboard and detailed results using the button below.') . '
          </p>';
        return [
            'subject' => ($isWinner ? '🏆 You’re a winner! ' : 'Challenge Results: ') . $quizTitle,
            'html' => self::baseLayout('Challenge Results', 'Your results are ready.', $body, $isWinner ? 'Claim Reward' : 'View Results', $ctaUrl),
            'text' => "Hi {$studentName},\n\n{$quizTitle} ended.\nRank: #{$rank}/{$totalParticipants}\n{$scoreLine}\n\n{$ctaUrl}\n\n— MyStudy Team",
        ];
    }

    public static function paymentAccessCode(string $studentName, string $quizTitle, string $accessCode, string $quizUrl): array {
        $body = '
          <h2 style="margin:0 0 10px;font-size:20px;line-height:1.25;">Your access is ready</h2>
          <p style="margin:0 0 12px;color:#334155;font-size:14.5px;line-height:1.7;">
            Hi <strong>' . htmlspecialchars($studentName ?: 'Student', ENT_QUOTES, 'UTF-8') . '</strong>, your payment for <strong>' . htmlspecialchars($quizTitle, ENT_QUOTES, 'UTF-8') . '</strong> was successful.
          </p>
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:12px 14px;margin:0 0 10px;">
            <div style="color:#64748b;font-weight:800;font-size:12px;text-transform:uppercase;letter-spacing:0.8px;">Access Code</div>
            <div style="font-weight:900;font-size:18px;color:#0f172a;margin-top:6px;">' . htmlspecialchars($accessCode, ENT_QUOTES, 'UTF-8') . '</div>
          </div>
          <p style="margin:0;color:#64748b;font-size:13.5px;line-height:1.7;">
            You can now open the quiz page and get started.
          </p>';
        return [
            'subject' => 'Access Code for ' . $quizTitle . ' - ' . (defined('SITE_NAME') ? SITE_NAME : 'MyStudy Base'),
            'html' => self::baseLayout('Access Code', 'Your access code is ready.', $body, 'Open Quiz', $quizUrl),
            'text' => "Hi {$studentName},\n\nAccess Code: {$accessCode}\nQuiz: {$quizUrl}\n\n— MyStudy Team",
        ];
    }

    public static function rewardClaimApproved(string $studentName, string $challengeTitle, string $reward, string $claimUrl): array {
        $body = '
          <h2 style="margin:0 0 10px;font-size:20px;line-height:1.25;">🎉 Reward Claim Approved!</h2>
          <p style="margin:0 0 12px;color:#334155;font-size:14.5px;line-height:1.7;">
            Hi <strong>' . htmlspecialchars($studentName ?: 'Student', ENT_QUOTES, 'UTF-8') . '</strong>, congratulations! Your reward claim for <strong>' . htmlspecialchars($challengeTitle, ENT_QUOTES, 'UTF-8') . '</strong> has been approved.
          </p>
          <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:14px;padding:12px 14px;margin:0 0 10px;">
            <div style="color:#16a34a;font-weight:800;font-size:12px;text-transform:uppercase;letter-spacing:0.8px;">Approved Reward</div>
            <div style="font-weight:900;font-size:18px;color:#0f172a;margin-top:6px;">' . htmlspecialchars($reward, ENT_QUOTES, 'UTF-8') . '</div>
          </div>
          <p style="margin:0;color:#64748b;font-size:13.5px;line-height:1.7;">
            Please use the button below to claim your reward. Follow the instructions to complete the redemption process.
          </p>';
        return [
            'subject' => '✅ Reward Claim Approved - ' . $challengeTitle,
            'html' => self::baseLayout('Reward Claim Approved', 'Your reward has been approved and is ready to claim.', $body, 'Claim Your Reward', $claimUrl),
            'text' => "Hi {$studentName},\n\nYour reward claim for {$challengeTitle} has been approved!\nReward: {$reward}\nClaim here: {$claimUrl}\n\n— MyStudy Team",
        ];
    }

    public static function rewardClaimRejected(string $studentName, string $challengeTitle, string $reason, string $supportUrl): array {
        $body = '
          <h2 style="margin:0 0 10px;font-size:20px;line-height:1.25;">Reward Claim Update</h2>
          <p style="margin:0 0 12px;color:#334155;font-size:14.5px;line-height:1.7;">
            Hi <strong>' . htmlspecialchars($studentName ?: 'Student', ENT_QUOTES, 'UTF-8') . '</strong>, we\'ve reviewed your reward claim for <strong>' . htmlspecialchars($challengeTitle, ENT_QUOTES, 'UTF-8') . '</strong>.
          </p>
          <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:14px;padding:12px 14px;margin:0 0 10px;">
            <div style="color:#dc2626;font-weight:800;font-size:12px;text-transform:uppercase;letter-spacing:0.8px;">Claim Status</div>
            <div style="font-weight:900;font-size:18px;color:#0f172a;margin-top:6px;">Not Approved</div>
            <div style="color:#7f1d1d;margin-top:6px;font-size:13px;line-height:1.6;">' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</div>
          </div>
          <p style="margin:0;color:#64748b;font-size:13.5px;line-height:1.7;">
            If you believe this is an error or have questions, please contact our support team for assistance.
          </p>';
        return [
            'subject' => '📋 Reward Claim Update - ' . $challengeTitle,
            'html' => self::baseLayout('Reward Claim Update', 'Your reward claim has been reviewed.', $body, 'Contact Support', $supportUrl),
            'text' => "Hi {$studentName},\n\nYour reward claim for {$challengeTitle} was not approved.\nReason: {$reason}\nSupport: {$supportUrl}\n\n— MyStudy Team",
        ];
    }

    public static function rewardClaimPaid(string $studentName, string $challengeTitle, string $reward, string $amount, string $paymentDetails): array {
        $body = '
          <h2 style="margin:0 0 10px;font-size:20px;line-height:1.25;">💰 Reward Paid!</h2>
          <p style="margin:0 0 12px;color:#334155;font-size:14.5px;line-height:1.7;">
            Hi <strong>' . htmlspecialchars($studentName ?: 'Student', ENT_QUOTES, 'UTF-8') . '</strong>, great news! Your reward for <strong>' . htmlspecialchars($challengeTitle, ENT_QUOTES, 'UTF-8') . '</strong> has been processed and paid.
          </p>
          <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:14px;padding:12px 14px;margin:0 0 10px;">
            <div style="color:#0369a1;font-weight:800;font-size:12px;text-transform:uppercase;letter-spacing:0.8px;">Payment Details</div>
            <div style="font-weight:900;font-size:18px;color:#0f172a;margin-top:6px;">' . htmlspecialchars($reward, ENT_QUOTES, 'UTF-8') . '</div>
            <div style="color:#0c4a6e;margin-top:4px;font-weight:700;">Amount: ' . htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') . '</div>
            <div style="color:#64748b;margin-top:6px;font-size:12px;line-height:1.5;">' . htmlspecialchars($paymentDetails, ENT_QUOTES, 'UTF-8') . '</div>
          </div>
          <p style="margin:0;color:#64748b;font-size:13.5px;line-height:1.7;">
            Thank you for your participation! Keep an eye out for more challenges and opportunities to earn rewards.
          </p>';
        return [
            'subject' => '💰 Reward Paid - ' . $challengeTitle,
            'html' => self::baseLayout('Reward Paid', 'Your reward has been processed and paid.', $body, 'View Challenges', ''),
            'text' => "Hi {$studentName},\n\nYour reward for {$challengeTitle} has been paid!\nReward: {$reward}\nAmount: {$amount}\nDetails: {$paymentDetails}\n\n— MyStudy Team",
        ];
    }

    public static function moderationContentRemoved(string $studentName, string $contentType, string $contentTitle, string $reason, string $appealUrl): array {
        $body = '
          <h2 style="margin:0 0 10px;font-size:20px;line-height:1.25;">Content Removed</h2>
          <p style="margin:0 0 12px;color:#334155;font-size:14.5px;line-height:1.7;">
            Hi <strong>' . htmlspecialchars($studentName ?: 'Student', ENT_QUOTES, 'UTF-8') . '</strong>, we wanted to inform you that your <strong>' . htmlspecialchars($contentType, ENT_QUOTES, 'UTF-8') . '</strong> titled <strong>"' . htmlspecialchars($contentTitle, ENT_QUOTES, 'UTF-8') . '"</strong> has been removed.
          </p>
          <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:14px;padding:12px 14px;margin:0 0 10px;">
            <div style="color:#dc2626;font-weight:800;font-size:12px;text-transform:uppercase;letter-spacing:0.8px;">Reason for Removal</div>
            <div style="color:#7f1d1d;margin-top:6px;font-size:13px;line-height:1.6;">' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</div>
          </div>
          <p style="margin:0;color:#64748b;font-size:13.5px;line-height:1.7;">
            This removal helps maintain a safe and respectful learning environment for all users. Please review our community guidelines to ensure future contributions comply with our standards.
          </p>';
        return [
            'subject' => '🚨 Content Removed - ' . (defined('SITE_NAME') ? SITE_NAME : 'MyStudy Base'),
            'html' => self::baseLayout('Content Removed', 'Your content has been removed by moderators.', $body, 'Review Guidelines', $appealUrl),
            'text' => "Hi {$studentName},\n\nYour {$contentType} \"{$contentTitle}\" has been removed.\nReason: {$reason}\nGuidelines: {$appealUrl}\n\n— MyStudy Team",
        ];
    }

    public static function moderationSuspension(string $studentName, string $reason, int $durationDays, string $appealUrl, string $endDate): array {
        $body = '
          <h2 style="margin:0 0 10px;font-size:20px;line-height:1.25;">Account Suspended</h2>
          <p style="margin:0 0 12px;color:#334155;font-size:14.5px;line-height:1.7;">
            Hi <strong>' . htmlspecialchars($studentName ?: 'Student', ENT_QUOTES, 'UTF-8') . '</strong>, your account has been temporarily suspended due to violations of our community guidelines.
          </p>
          <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:14px;padding:12px 14px;margin:0 0 10px;">
            <div style="color:#dc2626;font-weight:800;font-size:12px;text-transform:uppercase;letter-spacing:0.8px;">Suspension Details</div>
            <div style="font-weight:900;font-size:18px;color:#0f172a;margin-top:6px;">' . (int)$durationDays . ' Days</div>
            <div style="color:#7f1d1d;margin-top:4px;font-weight:700;">Ends: ' . htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') . '</div>
            <div style="color:#7f1d1d;margin-top:6px;font-size:13px;line-height:1.6;">' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</div>
          </div>
          <p style="margin:0;color:#64748b;font-size:13.5px;line-height:1.7;">
            During this suspension, you won\'t be able to access certain features. Please review our community guidelines to avoid future violations.
          </p>';
        return [
            'subject' => '⚠️ Account Suspended - ' . (defined('SITE_NAME') ? SITE_NAME : 'MyStudy Base'),
            'html' => self::baseLayout('Account Suspended', 'Your account has been temporarily suspended.', $body, 'Appeal Decision', $appealUrl),
            'text' => "Hi {$studentName},\n\nYour account is suspended for {$durationDays} days.\nEnds: {$endDate}\nReason: {$reason}\nAppeal: {$appealUrl}\n\n— MyStudy Team",
        ];
    }

    public static function moderationBan(string $studentName, string $reason, string $appealUrl): array {
        $body = '
          <h2 style="margin:0 0 10px;font-size:20px;line-height:1.25;">Account Permanently Banned</h2>
          <p style="margin:0 0 12px;color:#334155;font-size:14.5px;line-height:1.7;">
            Hi <strong>' . htmlspecialchars($studentName ?: 'Student', ENT_QUOTES, 'UTF-8') . '</strong>, after careful review, your account has been permanently banned due to serious violations of our community guidelines.
          </p>
          <div style="background:#7f1d1d;border:1px solid #991b1b;border-radius:14px;padding:12px 14px;margin:0 0 10px;">
            <div style="color:#fca5a5;font-weight:800;font-size:12px;text-transform:uppercase;letter-spacing:0.8px;">Ban Reason</div>
            <div style="color:#ffffff;margin-top:6px;font-size:13px;line-height:1.6;">' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</div>
          </div>
          <p style="margin:0;color:#64748b;font-size:13.5px;line-height:1.7;">
            This decision is final and your account access has been permanently revoked. If you believe this is an error, you may submit an appeal using the button below.
          </p>';
        return [
            'subject' => '🚫 Account Banned - ' . (defined('SITE_NAME') ? SITE_NAME : 'MyStudy Base'),
            'html' => self::baseLayout('Account Banned', 'Your account has been permanently banned.', $body, 'Submit Appeal', $appealUrl),
            'text' => "Hi {$studentName},\n\nYour account has been permanently banned.\nReason: {$reason}\nAppeal: {$appealUrl}\n\n— MyStudy Team",
        ];
    }
    public static function welcomeEmail(string $studentName, string $siteUrl): array {
        $site = defined('SITE_NAME') ? SITE_NAME : 'MyStudy Base';
        $safeName = htmlspecialchars($studentName ?: 'Student', ENT_QUOTES, 'UTF-8');
        $body = '
          <h2 style="margin:0 0 10px;font-size:22px;line-height:1.25;">Welcome to ' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '! 🎉</h2>
          <p style="margin:0 0 16px;color:#334155;font-size:14.5px;line-height:1.7;">
            Hi <strong>' . $safeName . '</strong>, we\'re thrilled to have you join our community! Your account is all set up and ready to go. Here\'s a quick tour of everything you can do:
          </p>

          <!-- Feature 1: Practice Quizzes -->
          <div style="background:#eff6ff;border:1px solid #dbeafe;border-radius:12px;padding:14px 16px;margin:0 0 10px;display:flex;gap:12px;">
            <div style="min-width:40px;height:40px;border-radius:10px;background:rgba(59,130,246,0.15);color:#3b82f6;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">📝</div>
            <div>
              <div style="font-weight:800;color:#0f172a;font-size:14px;margin-bottom:2px;">Practice Quizzes</div>
              <div style="color:#475569;font-size:13px;line-height:1.5;">Sharpen your skills with our free, untimed General Quizzes — perfect for studying at your own pace.
                <a href="' . htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') . '/quizzes.php" style="color:#3b82f6;font-weight:700;text-decoration:none;">Browse Quizzes →</a>
              </div>
            </div>
          </div>

          <!-- Feature 2: Challenges & Rewards -->
          <div style="background:#fffbeb;border:1px solid #fef3c7;border-radius:12px;padding:14px 16px;margin:0 0 10px;display:flex;gap:12px;">
            <div style="min-width:40px;height:40px;border-radius:10px;background:rgba(245,158,11,0.15);color:#f59e0b;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">🏆</div>
            <div>
              <div style="font-weight:800;color:#0f172a;font-size:14px;margin-bottom:2px;">Special Challenges & Rewards</div>
              <div style="color:#475569;font-size:13px;line-height:1.5;">Compete in timed challenges! Top scorers earn real prizes — <strong>Cash (₦)</strong>, <strong>Data (GB)</strong>, and <strong>Airtime</strong>.
                <a href="' . htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') . '/quizzes.php" style="color:#f59e0b;font-weight:700;text-decoration:none;">View Challenges →</a>
              </div>
            </div>
          </div>

          <!-- Feature 3: Study Materials -->
          <div style="background:#f0fdf4;border:1px solid #dcfce7;border-radius:12px;padding:14px 16px;margin:0 0 10px;display:flex;gap:12px;">
            <div style="min-width:40px;height:40px;border-radius:10px;background:rgba(16,185,129,0.15);color:#10b981;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">📚</div>
            <div>
              <div style="font-weight:800;color:#0f172a;font-size:14px;margin-bottom:2px;">Study Materials</div>
              <div style="color:#475569;font-size:13px;line-height:1.5;">Browse curated notes and past questions, or upload your own to help the community!
                <a href="' . htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') . '/materials.php" style="color:#10b981;font-weight:700;text-decoration:none;">Browse Materials →</a>
              </div>
            </div>
          </div>

          <!-- Feature 4: Battle Arena -->
          <div style="background:#eff6ff;border:1px solid #dbeafe;border-radius:12px;padding:14px 16px;margin:0 0 10px;display:flex;gap:12px;">
            <div style="min-width:40px;height:40px;border-radius:10px;background:rgba(99,102,241,0.15);color:#6366f1;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">⚔️</div>
            <div>
              <div style="font-weight:800;color:#0f172a;font-size:14px;margin-bottom:2px;">Battle Arena</div>
              <div style="color:#475569;font-size:13px;line-height:1.5;">Challenge friends to head-to-head quiz battles and prove your skills!
                <a href="' . htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') . '/battle_hub.php" style="color:#6366f1;font-weight:700;text-decoration:none;">Enter Arena →</a>
              </div>
            </div>
          </div>

          <!-- Feature 5: Community Forum -->
          <div style="background:#fdf2f8;border:1px solid #fce7f3;border-radius:12px;padding:14px 16px;margin:0 0 10px;display:flex;gap:12px;">
            <div style="min-width:40px;height:40px;border-radius:10px;background:rgba(236,72,153,0.15);color:#ec4899;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">💬</div>
            <div>
              <div style="font-weight:800;color:#0f172a;font-size:14px;margin-bottom:2px;">Community Hub</div>
              <div style="color:#475569;font-size:13px;line-height:1.5;">Ask questions, share answers, and learn with other students.
                <a href="' . htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') . '/discussions.php" style="color:#ec4899;font-weight:700;text-decoration:none;">Join Discussions →</a>
              </div>
            </div>
          </div>

          <!-- Feature 6: Messaging -->
          <div style="background:#fff7ed;border:1px solid #ffedd5;border-radius:12px;padding:14px 16px;margin:0 0 10px;display:flex;gap:12px;">
            <div style="min-width:40px;height:40px;border-radius:10px;background:rgba(245,158,11,0.15);color:#f59e0b;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">✉️</div>
            <div>
              <div style="font-weight:800;color:#0f172a;font-size:14px;margin-bottom:2px;">Private Messaging</div>
              <div style="color:#475569;font-size:13px;line-height:1.5;">Chat privately with other users and stay connected while you study.
                <a href="' . htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') . '/messages.php" style="color:#f59e0b;font-weight:700;text-decoration:none;">Open Messages →</a>
              </div>
            </div>
          </div>

          <p style="margin:14px 0 0;color:#64748b;font-size:13.5px;line-height:1.7;">
            We\'re excited to have you on board. Click the button below to head to your dashboard and start exploring!
          </p>';
        return [
            'subject' => 'Welcome to ' . $site . '! 🎉',
            'html' => self::baseLayout('Welcome to ' . $site, 'Your account is ready — here\'s everything you can do.', $body, 'Go to Dashboard', $siteUrl . '/dashboard.php'),
            'text' => "Welcome to {$site}, {$studentName}!\n\nYour account is ready. Here's what you can do:\n\n• Practice Quizzes — Free, untimed quizzes\n• Challenges & Rewards — Win Cash, Data, Airtime\n• Study Materials — Notes & past questions\n• Battle Arena — 1v1 quiz challenges\n• Community Hub — Ask & answer questions\n• Private Messaging — Chat with other students\n\nVisit your dashboard: {$siteUrl}/dashboard.php\n\n— {$site} Team",
        ];
    }

    /**
     * Battle notification email — handles all battle event types.
     * @param string $recipientName Name of the email recipient
     * @param string $subject Email subject line (same as notification title)
     * @param string $message Email body message (same as notification message)
     * @param string $ctaUrl Link for the CTA button
     * @param string $eventType One of: challenge, accepted, declined, negotiate, turn, result
     */
    public static function battleNotification(string $recipientName, string $subject, string $message, string $ctaUrl, string $eventType = 'challenge'): array {
        $site = defined('SITE_NAME') ? SITE_NAME : 'MyStudy Base';
        $safeName = htmlspecialchars($recipientName ?: 'Student', ENT_QUOTES, 'UTF-8');
        $safeMsg = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        // Event-specific styling
        $colors = [
            'challenge' => ['bg' => '#eff6ff', 'border' => '#dbeafe', 'icon' => '⚔️', 'accent' => '#3b82f6'],
            'accepted'  => ['bg' => '#f0fdf4', 'border' => '#dcfce7', 'icon' => '✅', 'accent' => '#10b981'],
            'declined'  => ['bg' => '#fef2f2', 'border' => '#fecaca', 'icon' => '❌', 'accent' => '#ef4444'],
            'negotiate' => ['bg' => '#fffbeb', 'border' => '#fef3c7', 'icon' => '🤝', 'accent' => '#f59e0b'],
            'turn'      => ['bg' => '#f5f3ff', 'border' => '#ddd6fe', 'icon' => '⚔️', 'accent' => '#8b5cf6'],
            'result'    => ['bg' => '#fefce8', 'border' => '#fef08a', 'icon' => '🏆', 'accent' => '#eab308'],
        ];
        $c = $colors[$eventType] ?? $colors['challenge'];

        // CTA button label
        $ctaLabels = [
            'challenge' => 'View Challenge',
            'accepted'  => 'Enter Arena',
            'declined'  => 'Back to Arena',
            'negotiate' => 'Review Counter-Offer',
            'turn'      => 'Take Your Turn',
            'result'    => 'View Results',
        ];
        $ctaLabel = $ctaLabels[$eventType] ?? 'View Battle';

        $body = '
          <p style="margin:0 0 16px;color:#334155;font-size:14.5px;line-height:1.7;">
            Hi <strong>' . $safeName . '</strong>,
          </p>

          <div style="background:' . $c['bg'] . ';border:1px solid ' . $c['border'] . ';border-radius:14px;padding:18px 20px;margin:0 0 16px;">
            <div style="display:flex;gap:12px;align-items:flex-start;">
              <div style="min-width:44px;height:44px;border-radius:12px;background:rgba(0,0,0,0.06);display:flex;align-items:center;justify-content:center;font-size:1.5rem;">' . $c['icon'] . '</div>
              <div style="color:#1e293b;font-size:14px;line-height:1.7;">
                ' . $safeMsg . '
              </div>
            </div>
          </div>

          <p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">
            Head over to the Battle Arena to take action!
          </p>';

        return [
            'subject' => $subject,
            'html' => self::baseLayout($subject, strip_tags($message), $body, $ctaLabel, $ctaUrl),
            'text' => "Hi {$recipientName},\n\n{$message}\n\nTake action: {$ctaUrl}\n\n— {$site} Team",
        ];
    }

    /**
     * Email Verification notification.
     * @param string $recipientName Name of the email recipient
     * @param string $verifyUrl The unique verification link
     */
    public static function emailVerification(string $recipientName, string $verifyUrl): array {
        $site = defined('SITE_NAME') ? SITE_NAME : 'MyStudy Base';
        $safeName = htmlspecialchars($recipientName ?: 'Student', ENT_QUOTES, 'UTF-8');

        $body = '
          <p style="margin:0 0 16px;color:#334155;font-size:14.5px;line-height:1.7;">
            Hi <strong>' . $safeName . '</strong>,
          </p>

          <div style="background:#eff6ff;border:1px solid #dbeafe;border-radius:14px;padding:18px 20px;margin:0 0 16px;">
            <div style="display:flex;gap:12px;align-items:flex-start;">
              <div style="min-width:44px;height:44px;border-radius:12px;background:rgba(59,130,246,0.1);display:flex;align-items:center;justify-content:center;font-size:1.5rem;">🔒</div>
              <div style="color:#1e293b;font-size:14px;line-height:1.7;">
                To secure your account and access all features of ' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . ', please verify your email address by clicking the button below.
              </div>
            </div>
          </div>

          <p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">
            If the button doesn\'t work, you can copy and paste this link into your browser: <br>
            <a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#3b82f6;text-decoration:underline;">' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '</a>
          </p>';

        return [
            'subject' => 'Verify your email address - ' . $site,
            'html' => self::baseLayout('Verify your email address', 'Secure your account by verifying your email.', $body, 'Verify Email Address', $verifyUrl),
            'text' => "Hi {$recipientName},\n\nPlease verify your email address to secure your account.\n\nClick here to verify: {$verifyUrl}\n\n— {$site} Team",
        ];
    }
}

