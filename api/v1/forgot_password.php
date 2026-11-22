<?php
/**
 * POST api/v1/forgot_password.php — send a reset link through the website's own
 * mailer. The reply is deliberately identical whether or not the address exists.
 */
require_once __DIR__ . '/_bootstrap.php';

api_require_post();

$email = api_field(api_input(), 'email');
if ($email === '') {
    api_err('missing_fields', 'Enter your email address.', 422, ['fields' => (object)['email' => 'Required']]);
}

$auth = new AuthController($pdo);
$result = $auth->sendPasswordResetEmail($email);

if (empty($result['success'])) {
    api_err('request_failed', $result['errors']['general'] ?? 'Could not send the reset link. Please try again.', 429);
}

// No link is ever returned: a device that can name someone else's email address
// must not be handed the reset token for it. The mailer is the only carrier.
api_out([
    'ok' => true,
    'message' => $result['message'] ?? 'If that email is registered, a reset link is on its way.',
]);
