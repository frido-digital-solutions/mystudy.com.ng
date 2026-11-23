<?php
/**
 * Phone push for the Expo app.
 *
 * One question decides everything here: is there a message waiting for somebody
 * who is not looking at the screen? The answer is sent to Expo's push service,
 * which hands it to the phone. Nothing in this file knows what an FCM key or an
 * APNs certificate is - Expo owns that, and it is the reason the same code works
 * inside Expo Go today and inside a real APK later without being rewritten.
 *
 * The rules this file keeps:
 *   · One row per device token, so a phone that signs in twice is not notified
 *     twice, and a token that Expo says is dead is deleted the moment we meet it.
 *   · The sender never learns anything about the recipient's devices - a token is
 *     only ever sent to, never returned.
 *   · A push must never delay a message. The send is fire-and-forget with a short
 *     timeout, and every failure is swallowed: the list already refreshes itself.
 *   · Nothing about the message body is logged.
 */

class Push
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureTable();
    }

    /**
     * The table ships with the code rather than as SQL someone has to remember to
     * run, which is how the chat tables were built too.
     */
    public function ensureTable()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `device_push_tokens` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `token` VARCHAR(255) NOT NULL,
                `platform` VARCHAR(16) DEFAULT NULL,
                `rich` TINYINT(1) DEFAULT 0,
                `last_seen_at` DATETIME DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_token` (`token`),
                INDEX `idx_push_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    /**
     * A phone announces itself once per install. `rich` is how the device says
     * whether it can carry the WhatsApp-shaped notification - reply and mark-read
     * buttons under the message - which is only true in a built APK, never in
     * Expo Go. Storing it per device means one phone with an old build cannot
     * downgrade the notifications of the whole account.
     */
    public function register(int $userId, string $token, string $platform = 'android', bool $rich = false): bool
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 250) return false;

        $stmt = $this->pdo->prepare("
            INSERT INTO device_push_tokens (user_id, token, platform, rich, last_seen_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), platform = VALUES(platform),
                                    rich = VALUES(rich), last_seen_at = NOW()
        ");
        if (!$stmt->execute([$userId, $token, $platform, $rich ? 1 : 0])) return false;

        // A phone that has not announced itself in two months is a phone that was
        // uninstalled, sold, or replaced. Those rows go here rather than in a
        // cron nobody remembers to set up, because every one of them is a second
        // of somebody's message spent calling Expo for a device that is gone.
        $this->pdo->prepare('DELETE FROM device_push_tokens WHERE user_id = ? AND last_seen_at < DATE_SUB(NOW(), INTERVAL 60 DAY)')
            ->execute([$userId]);
        return true;
    }

    public function unregister(string $token): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM device_push_tokens WHERE token = ?');
        $stmt->execute([trim($token)]);
    }

    /**
     * The one call the rest of the app makes. It runs inside Message::sendMessage,
     * so a note sent from the website, from the app, or from a reply button in a
     * notification all notify in exactly the same way.
     */
    /**
     * Whom a message in this thread should tell, or 0 when nobody should be
     * told: the sender themself, a thread that is not there, and anyone who has
     * blocked the sender. The block rule lives in one place so push cannot say
     * something the chat itself refuses to.
     */
    public function recipientFor(int $conversationId, int $senderId): int
    {
        $pair = $this->pdo->prepare('SELECT user_one_id, user_two_id FROM conversations WHERE id = ?');
        $pair->execute([$conversationId]);
        $row = $pair->fetch(PDO::FETCH_ASSOC);
        if (!$row) return 0;

        $recipient = (int)$senderId === (int)$row['user_one_id'] ? (int)$row['user_two_id'] : (int)$row['user_one_id'];
        if ($recipient <= 0 || $recipient === (int)$senderId) return 0;
        return $this->blocked($recipient, $senderId) ? 0 : $recipient;
    }

    /**
     * One notice from the notification list, sent to the phones as well.
     *
     * The notification list is not a second-class surface: anything the
     * website writes there - a badge, a challenge result, an answer to a post,
     * a message from the support team - is something the person is waiting to
     * hear about, and the phone is where they are when they are not looking at
     * the website. So the notice is sent to every device the account has, with
     * enough of itself in the payload (which notice it is, what it is about,
     * where it opens) that the phone can do exactly what a tap on the row in
     * the list would have done.
     *
     * @param int $userId
     * @param int $notificationId the row in the notifications table
     * @param string $type
     * @param string $title
     * @param string $message
     * @param string|null $link the website link the notice carries
     */
    public function notifyNotification(int $userId, int $notificationId, string $type, string $title, string $message, ?string $link = null): void
    {
        if ($userId <= 0) return;

        $tokens = $this->tokensFor($userId);
        if (!$tokens) return; // Nobody to tell; the row is in the list all the same.

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$message)));
        if (mb_strlen($text) > 240) $text = mb_substr($text, 0, 239) . '…';
        if ($text === '') $text = 'Open MyStudy Base to read more.';

        $messages = [];
        foreach ($tokens as $t) {
            $messages[] = [
                'to' => $t['token'],
                'title' => (string)$title,
                'body' => $text,
                'sound' => 'default',
                'priority' => 'default',
                'data' => [
                    'notification' => '1',
                    'notification_id' => $notificationId,
                    'type' => (string)$type,
                    'link' => (string)($link ?? ''),
                    'screen' => 'Notifications',
                ],
                // Its own channel, so a badge does not sound like a message
                // and an announcement does not sound like either of them.
                'channelId' => 'notifications',
                'collapseId' => 'notification-' . $notificationId,
                'threadId' => 'notifications',
            ];
        }

        $this->send($messages, $tokens);
    }

    /**
     * The one call the rest of the app makes for a new message.
     */
    public function notifyNewMessage(int $conversationId, int $senderId, string $body, int $messageId): void
    {
        $recipient = $this->recipientFor($conversationId, $senderId);
        if ($recipient <= 0) return;

        $who = $this->pdo->prepare('SELECT name, profile_picture FROM users WHERE id = ?');
        $who->execute([$senderId]);
        $from = $who->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'Someone', 'profile_picture' => null];

        $tokens = $this->tokensFor($recipient);
        if (!$tokens) return;

        $text = trim(preg_replace('/\s+/u', ' ', $body));
        // Long enough that a paragraph is readable in the notification itself
        // once it is stretched open, short enough that the shade is not filled
        // with a novel. FCM's ceiling is far above this.
        if (mb_strlen($text) > 240) $text = mb_substr($text, 0, 239) . '…';
        if ($text === '') $text = '(attachment)';

        $messages = [];
        $picture = api_push_asset_url($from['profile_picture'] ?? null);
        foreach ($tokens as $t) {
            $content = [
                'to' => $t['token'],
                'title' => (string)$from['name'],
                'body' => $text,
                // iOS plays this; on Android the sound belongs to the channel the
                // app registers, which is the only place a notification may have
                // one since Android 8.
                'sound' => 'default',
                'priority' => 'high',
                // The thread travels with the notification, so opening it lands
                // in the conversation it came from instead of the chat list, and
                // the header there can show the right face without a second call.
                'data' => [
                    'conversation_id' => $conversationId,
                    'message_id' => $messageId,
                    'sender_id' => $senderId,
                    'sender_name' => (string)$from['name'],
                    'picture' => $picture,
                    'screen' => 'conversation',
                ],
                // One notification per thread in the shade, and the thread groups
                // under one conversation, which is how a chat should behave when
                // somebody sends four messages in a row.
                'collapseId' => 'conversation-' . $conversationId,
                'threadId' => 'conversation-' . $conversationId,
                'channelId' => 'messages',
            ];
            if ($picture !== '') {
                // A face on the notification is the difference between a message
                // and a system alert. The phone pulls this URL itself, so the
                // picture never passes through a third-party push service.
                $content['richContent'] = ['image' => $picture];
            }
            if ($t['rich']) {
                // The Reply box and Mark as read are buttons the phone already
                // knows about, registered by the app under this one id. A device
                // running Expo Go never claims `rich`, so it is not sent an id it
                // has no buttons for.
                $content['categoryId'] = 'message';
            }
            $messages[] = $content;
        }

        $this->send($messages, $tokens);
    }

    /**
     * "… is typing" in the shade, WhatsApp-style.
     *
     * Three things keep this honest, because a notice that lies is worse than no
     * notice at all:
     *   · it shares the collapse id of the thread's messages, so the moment the
     *     real message arrives it takes the same slot and the typing line is
     *     gone by itself;
     *   · it is short-lived (25 seconds), so a phone that was offline when the
     *     person stopped typing never wakes up to a claim about a sentence that
     *     was never sent;
     *   · it goes to a silent channel, and only to a device that has one, which
     *     means a built APK. Expo Go has no channel of its own to put it in.
     */
    public function notifyTyping(int $conversationId, int $senderId): void
    {
        $recipient = $this->recipientFor($conversationId, $senderId);
        if ($recipient <= 0) return;

        // The sender's own switch decides whether anybody is told they are
        // typing, so the setting on the chats page means the same everywhere.
        $share = $this->pdo->prepare("SELECT meta_value FROM user_meta WHERE user_id = ? AND meta_key = 'privacy_share_status'");
        $share->execute([$senderId]);
        $raw = $share->fetchColumn();
        if ($raw !== false && $raw !== null && !filter_var($raw, FILTER_VALIDATE_BOOLEAN)) return;

        $name = $this->pdo->prepare('SELECT name FROM users WHERE id = ?');
        $name->execute([$senderId]);
        $from = (string)($name->fetchColumn() ?: 'Someone');

        $tokens = array_values(array_filter($this->tokensFor($recipient), static function ($t) {
            return $t['rich'];
        }));
        if (!$tokens) return;

        $messages = [];
        foreach ($tokens as $t) {
            $messages[] = [
                'to' => $t['token'],
                'title' => $from . ' is typing…',
                'body' => '',
                // No sound is asked for: the silent channel the app registers is
                // what keeps a typing line quiet, and only that does it on the
                // platform that owns notification sound.
                'priority' => 'normal',
                'ttl' => 25,
                'data' => [
                    'conversation_id' => $conversationId,
                    'sender_id' => $senderId,
                    'sender_name' => $from,
                    'notice' => 'typing',
                    'screen' => 'conversation',
                ],
                'collapseId' => 'conversation-' . $conversationId,
                'threadId' => 'conversation-' . $conversationId,
                'channelId' => 'typing',
            ];
        }

        $this->send($messages, $tokens);
    }

    /** @return array<int, array{token:string, rich:bool}> */
    private function tokensFor(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT token, rich FROM device_push_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = ['token' => (string)$r['token'], 'rich' => (int)($r['rich'] ?? 0) === 1];
        }
        return $out;
    }

    /**
     * The block list is the same table the website and the app both write to, so
     * a block taken anywhere is respected by push without a second rule.
     */
    private function blocked(int $userId, int $senderId): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ? LIMIT 1');
            $stmt->execute([$userId, $senderId]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            // A missing table must not swallow the message itself.
            return false;
        }
    }

    /**
     * One notice, every phone that has the app.
     *
     * The admin panel and the terminal command both come through here, so a
     * broadcast is the same thing whoever sends it. Three details matter:
     *
     *   · It goes to device tokens, not to user ids. A person who installed the
     *     app on two phones gets it on both; a person who never installed it
     *     gets nothing, because there is no phone to reach.
     *   · It is chunked. Expo accepts at most a hundred messages in one call,
     *     and one huge call would fail as a whole.
     *   · It is not a message. There is no thread to reply in and nothing to
     *     mark as read, so the notice carries no conversation id and the phone
     *     opens the app instead of a chat.
     *
     * @return array{devices:int, users:int, sent:int, failed:int, dead:int}
     */
    public function broadcastAll(string $title, string $body, array $data = []): array
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        $body = trim(preg_replace('/\s+/u', ' ', $body));
        if ($title === '') {
            return ['devices' => 0, 'users' => 0, 'sent' => 0, 'failed' => 0, 'dead' => 0];
        }
        if (mb_strlen($body) > 240) $body = mb_substr($body, 0, 239) . '…';

        $stmt = $this->pdo->query('SELECT token, user_id FROM device_push_tokens ORDER BY id');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return ['devices' => 0, 'users' => 0, 'sent' => 0, 'failed' => 0, 'dead' => 0];
        }

        $users = [];
        $tokens = [];
        foreach ($rows as $r) {
            $tokens[] = ['token' => (string)$r['token']];
            $users[(int)$r['user_id']] = true;
        }

        $payload = array_merge(['broadcast' => '1', 'screen' => 'Home'], $data);
        $messages = [];
        foreach ($tokens as $t) {
            $messages[] = [
                'to' => $t['token'],
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'priority' => 'high',
                'data' => $payload,
                // Its own channel, so an announcement can be given its own
                // sound and importance without touching the message channel.
                'channelId' => 'announcements',
                // One notice replaces the last notice of the same day rather
                // than stacking a column of them in the shade.
                'collapseId' => 'broadcast-' . date('Ymd'),
                'threadId' => 'broadcasts',
            ];
        }

        $sent = 0;
        $failed = 0;
        $dead = 0;
        $offset = 0;
        foreach (array_chunk($messages, 100) as $chunk) {
            $chunkTokens = array_slice($tokens, $offset, count($chunk));
            $offset += count($chunk);
            $stats = $this->send($chunk, $chunkTokens);
            $sent += $stats['sent'];
            $failed += $stats['failed'];
            $dead += $stats['dead'];
        }

        return [
            'devices' => count($tokens),
            'users' => count($users),
            'sent' => $sent,
            'failed' => $failed,
            'dead' => $dead,
        ];
    }

    /**
     * Expo answers with one ticket per notification, in the order they were sent,
     * so the nth reply belongs to the nth token. A ticket that says the device is
     * gone is the only honest way to learn a token is dead, and this is where the
     * row dies instead of failing every message forever.
     *
     * @return array{sent:int, failed:int, dead:int}
     */
    private function send(array $messages, array $tokens): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'dead' => 0];
        if (!function_exists('curl_init') || !$messages) {
            $stats['failed'] = count($messages);
            return $stats;
        }

        $ch = curl_init('https://exp.host/--/api/v2/push/send');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(array_values($messages), JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Encoding: gzip, deflate',
                'Content-Type: application/json',
                'host: exp.host',
            ],
            CURLOPT_RETURNTRANSFER => true,
            // Measured from this machine: one ticket costs about five seconds.
            // The response to the sender has already gone out by the time this
            // runs, so the only cost is a busy worker - and a timeout short
            // enough to cut Expo off is a notification quietly never sent.
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $res = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($res === false || $status >= 400) {
            $stats['failed'] = count($messages);
            return $stats;
        }

        $body = json_decode((string)$res, true);
        $tickets = array_values((array)($body['data'] ?? []));
        $dead = [];
        foreach ($tickets as $i => $ticket) {
            if (!is_array($ticket)) continue;
            $err = (string)($ticket['details']['error'] ?? '');
            $msg = (string)($ticket['message'] ?? '');
            $gone = $err === 'DeviceNotRegistered' || stripos($msg, 'DeviceNotRegistered') !== false;
            if ($gone) {
                $stats['dead']++;
                if (isset($tokens[$i]['token'])) $dead[] = (string)$tokens[$i]['token'];
                continue;
            }
            // Expo says "error" on a ticket it could not deliver, for any reason
            // other than a device that is gone. Both are failures, but only one
            // of them means the phone should stop being called.
            if ((string)($ticket['status'] ?? '') === 'error') {
                $stats['failed']++;
                continue;
            }
            $stats['sent']++;
        }
        // A ticket Expo did not answer for is a ticket nobody received.
        $stats['failed'] += max(0, count($messages) - count($tickets));

        if ($dead) {
            $in = implode(',', array_fill(0, count($dead), '?'));
            $this->pdo->prepare("DELETE FROM device_push_tokens WHERE token IN ($in)")->execute($dead);
        }
        return $stats;
    }
}

/**
 * The picture lives under the uploads folder with the rest of the site's files,
 * but a phone cannot follow a relative path - it needs the whole address, and the
 * app already knows which host to ask.
 */
function api_push_asset_url(?string $file): string
{
    $file = trim((string)$file);
    if ($file === '') return '';
    if (preg_match('#^(https?:)?//#i', $file)) return $file;
    if (function_exists('api_url')) return (string)api_url($file);
    if (defined('SITE_URL')) {
        return SITE_URL . '/' . ltrim(str_replace('\\', '/', $file), '/');
    }
    return $file;
}

/**
 * Queue work to run after the person has already got their answer.
 *
 * Both notification routes are a conversation with another machine, and shared
 * hosting can spend seconds on one. A message that is stored and already on the
 * sender's screen must not wait for a push gateway, so the send is parked here
 * and the layer that finishes the response runs it: api_out() for the app, and
 * the end of the send block in api_messages.php for the website.
 */
function msb_after_response(callable $job): void
{
    if (!isset($GLOBALS['msb_deferred']) || !is_array($GLOBALS['msb_deferred'])) {
        $GLOBALS['msb_deferred'] = [];
    }
    $GLOBALS['msb_deferred'][] = $job;
}

function msb_flush_response_jobs(): void
{
    $jobs = $GLOBALS['msb_deferred'] ?? [];
    $GLOBALS['msb_deferred'] = [];
    if (!$jobs) return;

    ignore_user_abort(true);
    if (session_status() === PHP_SESSION_ACTIVE) {
        // The lock is the real reason a late push stalls a whole browser tab:
        // every other request from that person waits behind it.
        session_write_close();
    }

    foreach ($jobs as $job) {
        try {
            $job();
        } catch (Throwable $e) {
            // A notification that fails stays invisible. The chat list catches up
            // by itself, which is what makes silence safe here.
        }
    }
}

/**
 * Tell the other side about a message, on every surface they use: the phone,
 * and the browser tab of the website that already had its own web push.
 */
function msb_notify_message(PDO $pdo, int $conversationId, int $senderId, string $body, int $messageId): void
{
    msb_after_response(static function () use ($pdo, $conversationId, $senderId, $body, $messageId) {
        $push = new Push($pdo);
        $push->notifyNewMessage($conversationId, $senderId, $body, $messageId);

        $recipient = $push->recipientFor($conversationId, $senderId);
        if ($recipient <= 0) return;

        $file = __DIR__ . '/../includes/WebPushService.php';
        if (!is_file($file)) return;
        require_once $file;

        $name = $pdo->prepare('SELECT name FROM users WHERE id = ?');
        $name->execute([$senderId]);
        $from = (string)($name->fetchColumn() ?: 'Someone');
        $preview = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($body))), 0, 80);

        (new WebPushService($pdo))->sendToUser(
            $recipient,
            '💬 New message from ' . $from,
            $preview,
            SITE_URL . '/conversation.php?with=' . $senderId
        );
    });
}

/** The typing line, queued the same way as the message itself. */
function msb_notify_typing(PDO $pdo, int $conversationId, int $senderId): void
{
    msb_after_response(static function () use ($pdo, $conversationId, $senderId) {
        (new Push($pdo))->notifyTyping($conversationId, $senderId);
    });
}
