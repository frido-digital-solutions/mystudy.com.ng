<?php
/**
 * POST api/v1/register.php — create an account, then hand back a token so the
 * app lands the student straight inside instead of bouncing them to a login form.
 *
 * Validation is AuthController::register(), the same method the website calls,
 * including the welcome email and the admin's "registrations open" switch.
 */
require_once __DIR__ . '/_bootstrap.php';

api_require_post();

if (site_setting('allow_registration', '1') !== '1') {
    api_err('registrations_closed', 'New sign-ups are paused right now. Please try again later.', 403);
}

$in = api_input();
$data = [
    'name' => api_field($in, 'name'),
    'email' => api_field($in, 'email'),
    'password' => (string)($in['password'] ?? ''),
    'user_type' => api_field($in, 'user_type'),
    'university' => api_field($in, 'university'),
    'department' => api_field($in, 'department'),
    'level' => api_field($in, 'level'),
];

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$limit = rate_limit_check('api_register:' . sha1(strtolower($ip)), 6, 3600);
if (!empty($limit['limited'])) {
    api_err('too_many_attempts', 'Too many accounts created from this device. Please try again later.', 429);
}

$auth = new AuthController($pdo);
$result = $auth->register($data);

if (empty($result['success'])) {
    api_err('validation_failed', $result['errors']['general'] ?? 'Please correct the highlighted fields.', 422,
        ['fields' => (object)($result['errors'] ?? [])]);
}

// Registration does not log the web user in, so look the new row up by email.
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$data['email']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    api_err('registration_failed', 'Your account was created but could not be opened. Please log in.', 500);
}

$issued = api_issue_token($pdo, (int)$user['id'], mb_substr(api_field($in, 'device', 'android'), 0, 60));

api_out([
    'ok' => true,
    'token' => $issued['token'],
    'token_expires_in_days' => $issued['expires_in_days'],
    'user' => api_user_payload($user, $pdo),
], 201);
