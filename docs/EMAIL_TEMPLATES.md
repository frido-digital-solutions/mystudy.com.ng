# Email Templates Documentation

This document describes all available email templates in the MyStudy platform and how to use them.

## Available Email Templates

### 1. Email Verification
**Method**: `EmailTemplates::verifyEmail($studentName, $verifyUrl)`
**Purpose**: Sent when a new user needs to verify their email address.
**Parameters**:
- `$studentName` (string): The student's name
- `$verifyUrl` (string): Verification URL with token

### 2. Password Reset
**Method**: `EmailTemplates::passwordReset($studentName, $resetUrl)`
**Purpose**: Sent when a user requests to reset their password.
**Parameters**:
- `$studentName` (string): The student's name
- `$resetUrl` (string): Password reset URL with token

### 3. Challenge Results
**Method**: `EmailTemplates::challengeResult($studentName, $quizTitle, $rank, $totalParticipants, $scoreLine, $ctaUrl, $isWinner, $prizeLine)`
**Purpose**: Sent when a challenge ends and results are available.
**Parameters**:
- `$studentName` (string): The student's name
- `$quizTitle` (string): Challenge title
- `$rank` (int): Student's rank
- `$totalParticipants` (int): Total participants
- `$scoreLine` (string): Score information
- `$ctaUrl` (string): Call-to-action URL
- `$isWinner` (bool): Whether student is in top 3
- `$prizeLine` (string): Prize information (if winner)

### 4. Payment Access Code
**Method**: `EmailTemplates::paymentAccessCode($studentName, $quizTitle, $accessCode, $quizUrl)`
**Purpose**: Sent after successful payment for premium content.
**Parameters**:
- `$studentName` (string): The student's name
- `$quizTitle` (string): Quiz/challenge title
- `$accessCode` (string): Access code for the content
- `$quizUrl` (string): URL to access the content

### 5. Reward Claim Approved
**Method**: `EmailTemplates::rewardClaimApproved($studentName, $challengeTitle, $reward, $claimUrl)`
**Purpose**: Sent when a reward claim is approved and ready to claim.
**Parameters**:
- `$studentName` (string): The student's name
- `$challengeTitle` (string): Challenge title
- `$reward` (string): Reward description
- `$claimUrl` (string): URL to claim the reward

### 6. Reward Claim Rejected
**Method**: `EmailTemplates::rewardClaimRejected($studentName, $challengeTitle, $reason, $supportUrl)`
**Purpose**: Sent when a reward claim is not approved.
**Parameters**:
- `$studentName` (string): The student's name
- `$challengeTitle` (string): Challenge title
- `$reason` (string): Reason for rejection
- `$supportUrl` (string): Support URL for appeals

### 7. Reward Claim Paid
**Method**: `EmailTemplates::rewardClaimPaid($studentName, $challengeTitle, $reward, $amount, $paymentDetails)`
**Purpose**: Sent when a reward has been successfully paid to the user.
**Parameters**:
- `$studentName` (string): The student's name
- `$challengeTitle` (string): Challenge title
- `$reward` (string): Reward description
- `$amount` (string): Payment amount
- `$paymentDetails` (string): Payment transaction details

### 8. Content Removed (Moderation)
**Method**: `EmailTemplates::moderationContentRemoved($studentName, $contentType, $contentTitle, $reason, $appealUrl)`
**Purpose**: Sent when user content is removed by moderators.
**Parameters**:
- `$studentName` (string): The student's name
- `$contentType` (string): Type of content (e.g., "quiz comment", "forum post")
- `$contentTitle` (string): Title of the removed content
- `$reason` (string): Reason for removal
- `$appealUrl` (string): URL to appeal or view guidelines

### 9. Account Suspension
**Method**: `EmailTemplates::moderationSuspension($studentName, $reason, $durationDays, $appealUrl, $endDate)`
**Purpose**: Sent when a user account is temporarily suspended.
**Parameters**:
- `$studentName` (string): The student's name
- `$reason` (string): Reason for suspension
- `$durationDays` (int): Number of days suspended
- `$appealUrl` (string): URL to appeal the decision
- `$endDate` (string): Date when suspension ends

### 10. Account Ban
**Method**: `EmailTemplates::moderationBan($studentName, $reason, $appealUrl)`
**Purpose**: Sent when a user account is permanently banned.
**Parameters**:
- `$studentName` (string): The student's name
- `$reason` (string): Reason for ban
- `$appealUrl` (string): URL to appeal the decision

## Usage Example

```php
// Include required files
require_once __DIR__ . '/includes/EmailService.php';
require_once __DIR__ . '/includes/EmailTemplates.php';

// Get email service instance
$emailService = EmailService::fromConfig();

if ($emailService) {
    // Get email template
    $emailTemplate = EmailTemplates::passwordReset('John Doe', 'https://mystudy.com/reset?token=abc123');
    
    // Send email
    $success = $emailService->send(
        'user@example.com',
        'John Doe',
        $emailTemplate['subject'],
        $emailTemplate['html'],
        $emailTemplate['text']
    );
    
    if ($success) {
        echo "Email sent successfully!";
    } else {
        echo "Failed to send email.";
    }
}
```

## Testing

Use the test file at `/test-emails.php` to preview all email templates locally before deployment.

1. Navigate to `http://localhost/mystudy.com/test-emails.php`
2. Browse through all available templates
3. Use the test email sending form to verify your email configuration

## Configuration

Email templates require the following constants to be defined:

- `SITE_NAME`: Your site name (defaults to 'MyStudy')
- `SITE_URL`: Your site base URL
- `BREVO_API_KEY`: Brevo API key for email sending
- `BREVO_FROM_EMAIL`: From email address
- `BREVO_FROM_NAME`: From name (defaults to SITE_NAME)

## Return Format

All template methods return an array with the following keys:

- `subject`: Email subject line
- `html`: HTML version of the email
- `text`: Plain text version of the email

## Design Principles

- Mobile-responsive design
- Inline CSS for maximum email client compatibility
- Accessible and semantic HTML
- Consistent branding with site colors
- Clear call-to-action buttons
- Professional and clean layout
