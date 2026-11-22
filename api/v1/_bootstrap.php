<?php
/**
 * Shared bootstrap for the mobile app API (api/v1/*).
 *
 * The app never touches MySQL and never gets a copy of the business rules. It
 * authenticates with a bearer token, and this file then fills $_SESSION exactly
 * as the website's own login does, so every existing model, helper and access
 * rule (material_require_access, quiz scoring, battle logic) runs unchanged for
 * app traffic. Anything the app is allowed to do, the website already does.
 */

// config.php starts a PHP session for every visitor; the API authenticates with
// a bearer token instead and never reads that session from storage.
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// A PHP warning printed as HTML in the middle of a body breaks the app's JSON
// parser, so errors go to the log and every failure leaves as JSON.
ini_set('display_errors', '0');

/** Send JSON and stop. */
function api_out(array $payload, int $code = 200): void {
    http_response_code($code);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // The length lets the phone take the answer and go, which is what makes the
    // lines below run after it has gone rather than before.
    if (!headers_sent()) header('Content-Length: ' . strlen($json));
    echo $json;

    if (function_exists('msb_flush_response_jobs')) {
        while (ob_get_level() > 0) {
            if (!ob_end_flush()) {
                break;
            }
        }
        flush();
        // Notifications were parked here by whoever sent the message, precisely
        // so that a slow push gateway cannot look like a slow app.
        msb_flush_response_jobs();
    }

    exit;
}

/** Send a machine-readable error the app can switch on. */
function api_err(string $code, string $message, int $http = 400, array $extra = []): void {
    api_out(['ok' => false, 'error' => ['code' => $code, 'message' => $message]] + $extra, $http);
}

/** Read a JSON body, falling back to form fields so curl stays usable. */
function api_input(): array {
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

function api_field(array $in, string $key, string $default = ''): string {
    return isset($in[$key]) && is_scalar($in[$key]) ? trim((string)$in[$key]) : $default;
}

/** Reject anything that is not a POST before touching the database. */
function api_require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        api_err('method_not_allowed', 'This endpoint needs a POST request.', 405);
    }
}

/**
 * Fill the session keys the rest of the app reads. Mirrors
 * AuthController::setUserSession() minus the cookie/session-file side effects.
 */
function api_bind_user(array $user): void {
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
    $_SESSION['is_super_admin'] = !empty($user['is_super_admin']);
    $_SESSION['privileges_checked_at'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['expire_time'] = time() + SESSION_LIFETIME;
}

function api_table_ready(PDO $pdo): bool {
    static $ready = false;
    if ($ready) {
        return true;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            device_label VARCHAR(60) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME DEFAULT NULL,
            UNIQUE KEY token_hash (token_hash),
            KEY user_id (user_id),
            CONSTRAINT api_tokens_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ready = true;
    } catch (Throwable $e) {
        error_log('api_tokens unavailable: ' . $e->getMessage());
        return false;
    }
    return true;
}

/** Issue a token. Only its SHA-256 is stored, so a leaked table is useless. */
function api_issue_token(PDO $pdo, int $userId, string $deviceLabel): array {
    $token = bin2hex(random_bytes(32));
    $days = 90;

    $stmt = $pdo->prepare('INSERT INTO api_tokens (user_id, token_hash, device_label, expires_at) VALUES (:uid, :h, :dev, DATE_ADD(NOW(), INTERVAL :d DAY))');
    $stmt->execute([':uid' => $userId, ':h' => hash('sha256', $token), ':dev' => mb_substr($deviceLabel, 0, 60), ':d' => $days]);

    // Keep at most 5 live tokens per account. Revoke the 6th-newest and older.
    $stale = $pdo->prepare('SELECT id FROM api_tokens WHERE user_id = ? AND revoked_at IS NULL ORDER BY id DESC LIMIT 100 OFFSET 5');
    $stale->execute([$userId]);
    $ids = $stale->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $revoke = $pdo->prepare('UPDATE api_tokens SET revoked_at = NOW() WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $revoke->execute($ids);
    }

    return ['token' => $token, 'expires_in_days' => $days];
}

/** Resolve a bearer token to a user row, or return null. */
function api_resolve_token(PDO $pdo, string $token): ?array {
    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT u.*, t.id AS token_id
           FROM api_tokens t
           JOIN users u ON u.id = t.user_id
          WHERE t.token_hash = :h AND t.revoked_at IS NULL AND t.expires_at > NOW()
          LIMIT 1"
    );
    $stmt->execute([':h' => hash('sha256', $token)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['status'] !== 'active') {
        return null;
    }
    $pdo->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?')->execute([(int)$row['token_id']]);
    return $row;
}

/** The token from the Authorization header. */
function api_bearer_token(): string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }
    if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m)) {
        return $m[1];
    }
    return '';
}

/** Require a valid token, bind the user, and return the row. */
function api_auth(): array {
    global $pdo;

    if (!api_table_ready($pdo)) {
        api_err('storage_unavailable', 'The app sign-in store is not ready yet. Please retry shortly.', 503);
    }

    $token = api_bearer_token();
    if ($token === '') {
        api_err('unauthenticated', 'Sign in to use this part of the app.', 401);
    }

    $user = api_resolve_token($pdo, $token);
    if (!$user) {
        api_err('token_invalid', 'Your session ended. Please sign in again.', 401);
    }

    api_bind_user($user);

    // The website marks a visitor active inside includes/header.php, a file no
    // phone ever walks through. Without this the app was the one visitor that
    // never made anyone "Online", so anyone who only used the phone looked
    // offline to everyone else forever. The date test in the WHERE clause is the
    // throttle: a chat screen polling every few seconds still costs at most one
    // write a minute, and one minute is far inside the five-minute online window.
    try {
        $pdo->prepare('UPDATE users SET last_active = NOW() WHERE id = ? AND (last_active IS NULL OR last_active < DATE_SUB(NOW(), INTERVAL 60 SECOND))')
            ->execute([(int)$user['id']]);
    } catch (Throwable $e) {
        error_log('api presence: ' . $e->getMessage());
    }

    return $user;
}

/**
 * Turn a path stored in the database into something a phone can load.
 * Uploads live under the site root and the app fetches them over plain HTTPS.
 */
function api_url(?string $path): ?string {
    if ($path === null || trim($path) === '') {
        return null;
    }
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
        return $path;
    }
    return SITE_URL . '/' . ltrim(str_replace('\\', '/', $path), '/');
}

/** Public-safe user shape. Never send password_hash to a device. */
function api_user_payload(array $user, ?PDO $pdo = null): array {
    $out = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'user_type' => $user['user_type'],
        'picture' => api_url($user['profile_picture'] ?? null),
        'points' => (int)($user['points'] ?? 0),
        'is_super_admin' => (int)($user['is_super_admin'] ?? 0) === 1,
        'created_at' => $user['created_at'] ?? null,
        'university' => null,
        'department' => null,
        'level' => null,
    ];

    // Where the website keeps a university student's course details. Reading them
    // here means a student who signed up on the web sees the same school in the app.
    if ($pdo !== null && ($user['user_type'] ?? '') === 'university') {
        try {
            $stmt = $pdo->prepare('SELECT meta_key, meta_value FROM user_meta WHERE user_id = ?');
            $stmt->execute([(int)$user['id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (array_key_exists($row['meta_key'], $out)) {
                    $out[$row['meta_key']] = $row['meta_value'];
                }
            }
        } catch (Throwable $e) {
            error_log('api user meta: ' . $e->getMessage());
        }
    }

    return $out;
}

/**
 * Shared-hosting note: Apache may strip Authorization. The app can also send the
 * token as a header named X-App-Token, which no module reserves.
 */
if (empty($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['HTTP_X_APP_TOKEN'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_SERVER['HTTP_X_APP_TOKEN'];
}
