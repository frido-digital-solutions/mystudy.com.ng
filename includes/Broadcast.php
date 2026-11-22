<?php
/**
 * One broadcast, two places it can be sent from.
 *
 * The admin panel and the terminal command are the same action seen from
 * different angles, so the work lives here once: the notice is written into
 * every account's notification list (where the app's Notifications tab reads
 * it) and sent to every phone that has the app installed (where it arrives in
 * the shade, even if the app is closed).
 *
 * Nothing here decides anything the user did not type. The title and the body
 * are the message; the rest is bookkeeping.
 */

if (!function_exists('msb_broadcast_all')) {

    /**
     * Send one notice to everybody.
     *
     * @param PDO $pdo
     * @param string $title      The headline on the phone.
     * @param string $body       The sentence under it.
     * @param array $options
     *   - screen: string  the app screen the notification opens (default Home)
     *   - inapp:  bool    also write it into every account's notification list
     *   - push:   bool    send it to the phones (default true)
     * @return array{
     *   devices:int, users:int, sent:int, failed:int, dead:int,
     *   inapp:int, push_error:string|null
     * }
     */
    function msb_broadcast_all(PDO $pdo, string $title, string $body, array $options = []): array
    {
        $title = trim((string)$title);
        $body = trim((string)$body);
        $screen = isset($options['screen']) && $options['screen'] !== '' ? (string)$options['screen'] : 'Home';
        $inApp = array_key_exists('inapp', $options) ? (bool)$options['inapp'] : true;
        $push = array_key_exists('push', $options) ? (bool)$options['push'] : true;

        $result = [
            'devices' => 0, 'users' => 0, 'sent' => 0, 'failed' => 0, 'dead' => 0,
            'inapp' => 0, 'push_error' => null,
        ];

        if ($title === '' || $body === '') {
            $result['push_error'] = 'A title and a message are both required.';
            return $result;
        }

        // Every account that can still sign in. The app's notification list is
        // read per account, so this is a row per account rather than one row
        // for everybody.
        $stmt = $pdo->query("SELECT id FROM users WHERE status = 'active'");
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($inApp && $ids) {
            require_once __DIR__ . '/../models/Notification.php';
            $notifModel = new Notification($pdo);
            foreach ($ids as $uid) {
                try {
                    $notifModel->create((int)$uid, 'admin_broadcast', $title, $body, null);
                    $result['inapp']++;
                } catch (Throwable $e) {
                    // One account failing to be written is not a reason to stop
                    // telling everybody else.
                    log_error('Broadcast in-app notice failed', [
                        'user_id' => (int)$uid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($push) {
            require_once __DIR__ . '/../models/Push.php';
            try {
                $stats = (new Push($pdo))->broadcastAll($title, $body, [
                    'screen' => $screen,
                    'sent_by' => 'admin',
                ]);
                $result['devices'] = $stats['devices'];
                $result['users'] = $stats['users'];
                $result['sent'] = $stats['sent'];
                $result['failed'] = $stats['failed'];
                $result['dead'] = $stats['dead'];
            } catch (Throwable $e) {
                log_error('Broadcast push failed', ['error' => $e->getMessage()]);
                $result['push_error'] = 'The push could not be sent: ' . $e->getMessage();
            }
        }

        return $result;
    }
}
