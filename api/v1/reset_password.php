<?php
/**
 * POST api/v1/reset_password.php — trade the emailed token for a new password.
 *
 * Checked by AuthController::resetPassword(), so token expiry and the minimum
 * length rule are the same rules the website enforces.
 */
require_once __DIR__ . '/_bootstrap.php';

api_require_post();

$in = api_input();
$token = api_field($in, 'token');
$password = (string)($in['password'] ?? '');

if ($token === '' || $password === '') {
    api_err('missing_fields', 'Both the reset link and the new password are needed.', 422,
        ['fields' => (object)['token' => $token === '', 'password' => $password === '']]);
}

$auth = new AuthController($pdo);
$result = $auth->resetPassword($token, $password);

if (empty($result['success'])) {
    api_err('reset_failed', $result['errors']['general'] ?? ($result['errors']['password'] ?? 'That reset link is no longer valid.'), 400,
        ['fields' => (object)($result['errors'] ?? [])]);
}

api_out(['ok' => true, 'message' => $result['message'] ?? 'Password reset. You can sign in now.']);
