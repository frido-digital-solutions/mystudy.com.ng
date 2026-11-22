<?php
/**
 * POST api/v1/login.php — email + password, returns a bearer token.
 *
 * Credentials are checked by AuthController::authenticate(), the same method the
 * website's own login uses, so a suspended or unknown account is answered
 * identically on both. The app then presents that token to every other endpoint.
 */
require_once __DIR__ . '/_bootstrap.php';

api_require_post();

$in = api_input();
$email = api_field($in, 'email');
$password = (string)($in['password'] ?? '');
$device = mb_substr(api_field($in, 'device', 'android'), 0, 60);

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$limit = rate_limit_check('api_login:' . sha1(strtolower($ip) . '|' . strtolower($email)), 10, 300);
if (!empty($limit['limited'])) {
    api_err('too_many_attempts', 'Too many attempts. Try again in ' . (int)$limit['retry_seconds'] . ' seconds.', 429,
        ['retry_seconds' => (int)$limit['retry_seconds']]);
}

if ($email === '' || $password === '') {
    api_err('missing_fields', 'Enter both your email and password.', 422,
        ['fields' => ['email' => $email === '', 'password' => $password === '']]);
}

$auth = new AuthController($pdo);
$result = $auth->authenticate($email, $password);

if (empty($result['success'])) {
    $general = $result['errors']['general'] ?? ($result['errors']['email'] ?? 'Invalid email or password');
    api_err('bad_credentials', $general, 401, ['fields' => (object)($result['errors'] ?? [])]);
}

$userId = (int)$result['user']->id;

// Re-read the row rather than trust the in-memory model: the payload needs
// is_super_admin, and one query here keeps the response shape identical to
// whatever GET /me returns.
$row = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$row->execute([$userId]);
$user = $row->fetch(PDO::FETCH_ASSOC);

$issued = api_issue_token($pdo, $userId, $device);

api_out([
    'ok' => true,
    'token' => $issued['token'],
    'token_expires_in_days' => $issued['expires_in_days'],
    'user' => api_user_payload($user, $pdo),
    'first_login' => !empty($result['first_login']),
]);
