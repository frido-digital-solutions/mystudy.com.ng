<?php
/**
 * Brevo Transactional Email sender (API v3)
 * - Reads API key from environment or local secrets include.
 * - Designed to fail safely (returns false, logs error) without breaking requests.
 */
class EmailService {
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;
    private ?string $replyToEmail;
    private ?string $replyToName;

    public function __construct(
        string $apiKey,
        string $fromEmail,
        string $fromName,
        ?string $replyToEmail = null,
        ?string $replyToName = null
    ) {
        $this->apiKey = $apiKey;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->replyToEmail = $replyToEmail;
        $this->replyToName = $replyToName;
    }

    public static function fromConfig(): ?self {
        $apiKey = getenv('BREVO_API_KEY') ?: (defined('BREVO_API_KEY') ? BREVO_API_KEY : '');
        $fromEmail = defined('BREVO_FROM_EMAIL') ? BREVO_FROM_EMAIL : (defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '');
        $fromName  = defined('BREVO_FROM_NAME') ? BREVO_FROM_NAME : (defined('SITE_NAME') ? SITE_NAME : 'MyStudy Base');
        $replyToEmail = defined('BREVO_REPLY_TO_EMAIL') ? BREVO_REPLY_TO_EMAIL : null;
        $replyToName  = defined('BREVO_REPLY_TO_NAME') ? BREVO_REPLY_TO_NAME : null;

        if (!$apiKey || !$fromEmail || !$fromName) return null;
        return new self($apiKey, $fromEmail, $fromName, $replyToEmail, $replyToName);
    }

    /**
     * Send an email via Brevo transactional API.
     *
     * @param array $attachments each item: ['content' => base64, 'name' => ..., 'filename' => ...]
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlContent, string $textContent = '', array $attachments = []): bool {
        $payload = [
            'sender' => [
                'name' => $this->fromName,
                'email' => $this->fromEmail,
            ],
            'to' => [[
                'email' => $toEmail,
                'name' => $toName ?: $toEmail,
            ]],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
        ];

        if ($textContent !== '') {
            $payload['textContent'] = $textContent;
        }
        if ($this->replyToEmail) {
            $payload['replyTo'] = [
                'email' => $this->replyToEmail,
                'name' => $this->replyToName ?: $this->replyToEmail,
            ];
        }
        if ($attachments) {
            $clean = [];
            foreach ($attachments as $a) {
                if (empty($a['content']) || empty($a['name'])) continue;
                $clean[] = [
                    'content' => (string)$a['content'],
                    'name' => basename((string)$a['name']),
                    'filename' => basename((string)($a['filename'] ?? $a['name'])),
                ];
            }
            if ($clean) $payload['attachments'] = $clean;
        }

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $this->apiKey,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        // A 4 MB attachment is ~5.4 MB of base64; 12s is not enough to post that.
        curl_setopt($ch, CURLOPT_TIMEOUT, $attachments ? 60 : 12);
        
        // Disable SSL verification for local XAMPP environments
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            error_log('Brevo email error (curl errno=' . $errno . ')');
            return false;
        }
        if ($http < 200 || $http >= 300) {
            error_log('Brevo email error (http=' . $http . '): ' . substr((string)$resp, 0, 600));
            return false;
        }
        return true;
    }
}

