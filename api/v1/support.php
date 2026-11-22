<?php
/**
 * Support tickets for the app.
 *
 * A ticket is the same row the website's Contact page already writes -
 * contact_messages - so the admin panel keeps working with no second inbox to
 * watch, and an admin reply sent from there still emails the student and still
 * raises the notification the app shows. Two things were added to that table on
 * first use, both guarded so an existing install is never broken: user_id (so a
 * student sees only their own tickets, not everyone who ever used the same
 * email) and resolved_at (so the student can close their own ticket).
 *
 * A follow-up message from the student goes into contact_replies, and the
 * ticket is marked unread so the admin panel flags it as waiting for a reply.
 */

require_once __DIR__ . '/_bootstrap.php';

$user = api_auth();
$userId = (int)$user['id'];
$email = strtolower(trim((string)($user['email'] ?? '')));
$name = trim((string)($user['name'] ?? ''));

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$in = $isPost ? api_input() : [];
$action = $isPost ? api_field($in, 'action') : trim((string)($_GET['action'] ?? 'list'));

/** Kept here so the phone and the website offer the same list of reasons. */
function support_categories(): array
{
    return [
        'Payment or refund problem',
        'Quiz or test problem',
        'Material download problem',
        'Account or password problem',
        'Points, badges or leaderboard',
        'Battle or challenge problem',
        'Certificate problem',
        'App or website not working',
        'Report a student or a post',
        'Suggestion or feedback',
        'Something else',
    ];
}

function support_tables(PDO $pdo): void
{
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            subject VARCHAR(500) DEFAULT "",
            message TEXT NOT NULL,
            admin_reply TEXT,
            replied_at DATETIME DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Only the columns this feature needs, and only when they are missing.
        foreach (['user_id' => 'ALTER TABLE contact_messages ADD COLUMN user_id INT DEFAULT NULL, ADD KEY user_id (user_id)',
                  'resolved_at' => 'ALTER TABLE contact_messages ADD COLUMN resolved_at DATETIME DEFAULT NULL'] as $column => $sql) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = ?");
            $stmt->execute([$column]);
            if ((int)$stmt->fetchColumn() === 0) {
                $pdo->exec($sql);
            }
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS contact_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            body TEXT NOT NULL,
            from_user TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY message_id (message_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    } catch (Throwable $e) {
        error_log('api/v1 support tables: ' . $e->getMessage());
        api_err('storage_unavailable', 'Support is not ready on the server yet. Please try again shortly.', 503);
    }
}

support_tables($pdo);

/** The one place that decides which rows are this student's tickets. */
function support_scope(PDO $pdo): string
{
    $has = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'contact_messages\' AND COLUMN_NAME = \'user_id\'');
    $has->execute();
    return (int)$has->fetchColumn() > 0 ? 'user_id = :uid OR email = :email' : 'email = :email';
}

function support_shape(PDO $pdo, array $rows, int $withThread = 0): array
{
    $out = [];
    foreach ($rows as $r) {
        $ticket = [
            'id' => (int)$r['id'],
            'category' => (string)($r['subject'] ?? ''),
            'message' => (string)$r['message'],
            'created_at' => (string)$r['created_at'],
            'reply' => (string)($r['admin_reply'] ?? ''),
            'replied_at' => (string)($r['replied_at'] ?? ''),
            'resolved_at' => (string)($r['resolved_at'] ?? ''),
            'status' => !empty($r['resolved_at']) ? 'resolved' : (!empty($r['admin_reply']) ? 'answered' : 'open'),
        ];
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM contact_replies WHERE message_id = ?');
        $stmt->execute([(int)$r['id']]);
        $ticket['follow_ups'] = (int)$stmt->fetchColumn();

        if ($withThread) {
            $thread = $pdo->prepare('SELECT id, body, from_user, created_at FROM contact_replies
                                       WHERE message_id = ? ORDER BY created_at ASC, id ASC');
            $thread->execute([(int)$r['id']]);
            $posts = array_map(static fn ($x) => [
                'id' => (int)$x['id'],
                'body' => (string)$x['body'],
                'from_user' => (int)$x['from_user'] === 1,
                'created_at' => (string)$x['created_at'],
            ], $thread->fetchAll(PDO::FETCH_ASSOC));

            // The admin panel answers into contact_messages.admin_reply and knows
            // nothing about this table, so the answer is folded into the thread
            // here instead of asking the admin to post it twice.
            $answered = false;
            foreach ($posts as $p) {
                if (!$p['from_user']) $answered = true;
            }
            $adminReply = trim((string)($r['admin_reply'] ?? ''));
            if (!$answered && $adminReply !== '') {
                $posts[] = [
                    'id' => 0,
                    'body' => $adminReply,
                    'from_user' => false,
                    'created_at' => (string)($r['replied_at'] ?? $r['created_at']),
                ];
                usort($posts, static fn ($a, $b) => strcmp($a['created_at'], $b['created_at']));
            }
            $ticket['replies'] = $posts;
        }
        $out[] = $ticket;
    }
    return $out;
}

/** Bind only what the chosen scope actually contains: a missing parameter is
 *  a hard error when native prepares are on, which they are here. */
function support_bind(PDO $pdo, int $userId, string $email, array $extra = []): array
{
    $scope = support_scope($pdo);
    $bind = $extra + [':email' => $email];
    if (strpos($scope, ':uid') !== false) {
        $bind = [':uid' => $userId] + $bind;
    }
    return $bind;
}

$contact = [
    'email' => site_contact_email(),
    'phone' => site_contact_phone(),
    'whatsapp' => str_replace([' ', '-', '(', ')'], '', (string)site_contact_phone()),
    'hours' => trim((string)site_setting('business_hours', 'Mon-Fri from 8am to 5pm')),
    'site_name' => SITE_NAME,
];

// ─────────────────────────────────────────────────────────── open a ticket ────
if ($isPost && $action === 'open') {
    $category = trim(api_field($in, 'category'));
    $body = trim(api_field($in, 'message'));
    if (!in_array($category, support_categories(), true)) {
        api_err('bad_category', 'Choose one of the listed reasons.', 422);
    }
    if (mb_strlen($body) < 10) {
        api_err('too_short', 'Say a little more so support can help - at least 10 characters.', 422);
    }
    if (mb_strlen($body) > 4000) {
        api_err('too_long', 'Keep your message under 4000 characters.', 422);
    }
    $limit = rate_limit_check('api_support:' . $userId, 8, 3600);
    if (!empty($limit['limited'])) {
        api_err('too_many', 'You have sent several tickets just now. Please try again in ' . (int)$limit['retry_seconds'] . ' seconds.', 429,
            ['retry_seconds' => (int)$limit['retry_seconds']]);
    }

    $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, subject, message, user_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $email, $category, $body, $userId]);
    api_out(['ok' => true, 'id' => (int)$pdo->lastInsertId(),
             'message' => 'Ticket opened. Support will answer here and send you a notification.']);
}

// ──────────────────────────────────────────────────────── reply and close ─────
if ($isPost && ($action === 'reply' || $action === 'resolve')) {
    $id = (int)api_field($in, 'id');
    $stmt = $pdo->prepare('SELECT id, admin_reply, resolved_at FROM contact_messages WHERE id = :id AND (' . support_scope($pdo) . ') LIMIT 1');
    $stmt->execute(support_bind($pdo, $userId, $email, [':id' => $id]));
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) api_err('not_found', 'That ticket is not yours, or it is no longer here.', 404);

    if ($action === 'resolve') {
        if (!empty($ticket['resolved_at'])) api_out(['ok' => true, 'already' => true]);
        $pdo->prepare('UPDATE contact_messages SET resolved_at = NOW() WHERE id = ?')->execute([$id]);
        api_out(['ok' => true, 'resolved' => true]);
    }

    if (!empty($ticket['resolved_at'])) {
        api_err('resolved', 'This ticket is closed. Open a new one if you still need help.', 409);
    }
    $body = trim(api_field($in, 'message'));
    if ($body === '' || mb_strlen($body) > 4000) {
        api_err('bad_message', 'Write your message, keep it under 4000 characters.', 422);
    }
    $pdo->prepare('INSERT INTO contact_replies (message_id, user_id, body, from_user) VALUES (?, ?, ?, 1)')
        ->execute([$id, $userId, $body]);
    // Back to unread, which is what makes the admin panel show it as waiting.
    $pdo->prepare('UPDATE contact_messages SET is_read = 0 WHERE id = ?')->execute([$id]);
    api_out(['ok' => true, 'sent' => true]);
}

// ───────────────────────────────────────────────────────────── the list ───────
$rows = $pdo->prepare('SELECT id, subject, message, admin_reply, replied_at, resolved_at, created_at
                         FROM contact_messages WHERE (' . support_scope($pdo) . ')
                        ORDER BY created_at DESC LIMIT 40');
$rows->execute(support_bind($pdo, $userId, $email));
$tickets = support_shape($pdo, $rows->fetchAll(PDO::FETCH_ASSOC));

$openId = (int)($isPost ? api_field($in, 'id') : ($_GET['id'] ?? 0));
$one = null;
if ($openId > 0) {
    foreach ($tickets as $t) {
        if ($t['id'] === $openId) $one = $t;
    }
    if ($one === null) api_err('not_found', 'That ticket is not yours, or it is no longer here.', 404);
    // Re-read the raw row for the thread: the ownership check above already
    // passed, and support_shape needs the columns the shaped ticket renamed.
    $raw = $pdo->prepare('SELECT id, subject, message, admin_reply, replied_at, resolved_at, created_at
                            FROM contact_messages WHERE id = ?');
    $raw->execute([$openId]);
    $row = $raw->fetch(PDO::FETCH_ASSOC);
    $one['replies'] = $row ? support_shape($pdo, [$row], 1)[0]['replies'] : [];
}

api_out([
    'ok' => true,
    'categories' => support_categories(),
    'contact' => $contact,
    'you' => ['name' => $name, 'email' => $email],
    'tickets' => $tickets,
    'ticket' => $one,
]);
