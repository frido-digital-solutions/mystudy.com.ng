<?php
/**
 * POST api/v1/logout.php — revoke the presenting token.
 *
 * The token is deleted rather than just forgotten on the device: a lost or
 * sold phone must not keep a live credential against the same database the
 * website uses.
 */
require_once __DIR__ . '/_bootstrap.php';

api_require_post();

api_auth();

$stmt = $pdo->prepare('UPDATE api_tokens SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL');
$stmt->execute([hash('sha256', api_bearer_token())]);

api_out(['ok' => true]);
