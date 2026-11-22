<?php
/**
 * Short-lived tickets that tell a website page who is asking when the visitor is
 * an app user rather than a browser holding a session cookie.
 *
 * The phone signs in with a bearer token that only api/v1 endpoints read, so a
 * page opened inside the app (the PDF reader today, any single-sign page later)
 * cannot tell who is looking at it. Handing that 90-day token over in a URL is
 * not acceptable: it would sit in server logs and access records, and it opens
 * the whole account. So api/v1 mints a random ticket instead - ten minutes long,
 * scoped to exactly one material, useless for anything else.
 *
 * Only the SHA-256 of a ticket is ever written down, and the file is removed the
 * first time it is presented after its expiry, so a leaked cache directory
 * cannot be replayed.
 */

const APP_TICKET_TTL = 600;

function app_ticket_dir(): string
{
    $dir = ROOT_PATH . '/cache/tickets';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/** Return a token whose meaning only the holder of this file's hash can give. */
function app_ticket_issue(int $userId, string $scope, int $ttl = APP_TICKET_TTL): string
{
    $token = bin2hex(random_bytes(16));
    app_ticket_sweep();
    file_put_contents(
        app_ticket_dir() . '/' . hash('sha256', $token),
        json_encode(['user_id' => $userId, 'scope' => $scope, 'expires_at' => time() + $ttl]),
        LOCK_EX
    );
    return $token;
}

/**
 * The account behind a ticket, or null. The scope must match exactly, so a
 * ticket minted for one file cannot be pointed at another.
 */
function app_ticket_resolve(PDO $pdo, string $token, string $scope): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    $file = app_ticket_dir() . '/' . hash('sha256', $token);
    if (!is_file($file) || !is_readable($file)) {
        return null;
    }
    $payload = json_decode((string)file_get_contents($file), true);
    if (!is_array($payload) || ($payload['scope'] ?? '') !== $scope || (int)($payload['expires_at'] ?? 0) < time()) {
        @unlink($file);
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id AND status = "active" LIMIT 1');
    $stmt->execute([':id' => (int)($payload['user_id'] ?? 0)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

/**
 * Expired tickets are worthless, so every so often the folder is swept. A hash
 * of a random token cannot be listed back, but there is no reason to keep them.
 */
function app_ticket_sweep(int $chance = 20): void
{
    if (random_int(1, $chance) !== 1) {
        return;
    }
    foreach ((array)glob(app_ticket_dir() . '/*') as $file) {
        if (!is_file($file)) {
            continue;
        }
        $payload = json_decode((string)file_get_contents($file), true);
        if (!is_array($payload) || (int)($payload['expires_at'] ?? 0) < time()) {
            @unlink($file);
        }
    }
}
