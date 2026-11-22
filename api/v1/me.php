<?php
/**
 * GET api/v1/me.php — the signed-in student, as the app needs to see them.
 *
 * Same row the website session carries, so a profile edited on the phone shows
 * on the web immediately and vice versa.
 */
require_once __DIR__ . '/_bootstrap.php';

$user = api_auth();

$unread = 0;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([(int)$user['id']]);
    $unread = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    // A missing count must never fail the call that decides whether the session is valid.
    error_log('api me unread count: ' . $e->getMessage());
}

api_out([
    'ok' => true,
    'user' => api_user_payload($user, $pdo),
    'unread_notifications' => $unread,
    'can_access_admin' => (int)($user['is_super_admin'] ?? 0) === 1 || has_role('admin'),
]);
