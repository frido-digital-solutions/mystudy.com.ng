<?php
/**
 * POST api/v1/push.php — a phone tells the account it belongs to about itself.
 *
 *   {action:'register',   token, platform, rich}  -> start notifying this device
 *   {action:'unregister', token}                   -> stop notifying this device
 *   {action:'report',     step, detail}            -> this phone could not register
 *   {action:'test'}                                -> send this account one test notice
 *
 * A token is only ever written, never read back out of here. It is a standing
 * instruction to deliver messages to that one phone, so handing it to any other
 * account would be handing over that person's message previews.
 *
 * `rich` is the device saying it can carry the full chat notification - the
 * sender's face, a Reply box and Mark as read in the shade. That needs a built
 * APK with its own push credentials; Expo Go cannot, and says so by sending
 * false. Storing it per device rather than per account means one phone on an old
 * build cannot flatten the notifications of every other device on the account.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Push.php';

api_require_post();
$user = api_auth();
$userId = (int)$user['id'];

$in = api_input();
$action = api_field($in, 'action', 'register');
$token = api_field($in, 'token');

$push = new Push($pdo);

if ($action === 'test') {
    // The phone asks the server to send it one ordinary notice, so that
    // "notifications are not working" becomes a question with an answer.
    // No device address is involved: the server already knows which phones
    // belong to this account.
    $stats = $push->notifyTest($userId);
    api_out(['ok' => true, 'test' => $stats]);
}

// Expo hands out tokens in two shapes, both bracketed and around 60 characters.
// Anything longer is somebody probing the endpoint, not a phone.
if ($token === '' || strlen($token) > 250) {
    api_err('bad_token', 'That device address is not usable.', 422);
}

if ($action === 'unregister') {
    // Scoped to this account: a guessed token must not be able to silence
    // somebody else's phone.
    $stmt = $pdo->prepare('DELETE FROM device_push_tokens WHERE token = ? AND user_id = ?');
    $stmt->execute([$token, $userId]);
    api_out(['ok' => true, 'registered' => false]);
}

if ($action === 'report') {
    // The phone tells the server why it could not register. A phone that is
    // not registered is invisible from this side - it simply is not in the
    // list of devices - and the reason it is not there is on the phone. So
    // the phone sends the reason back, and it is kept where it can be read.
    // Nothing about a message is ever sent here: only the name of the step
    // that failed and what it said.
    $step = (string)api_field($in, 'step', 'unknown');
    $detail = (string)api_field($in, 'detail', '');
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO push_diagnostics (user_id, step, detail) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, mb_substr($step, 0, 60), mb_substr($detail, 0, 500)]);
    } catch (Throwable $e) {
        // A report that cannot be stored is not worth breaking the app over.
    }
    api_out(['ok' => true, 'reported' => true]);
}

if ($action === 'test') {
    // The phone asks the server to send it one ordinary notice, so that
    // "notifications are not working" becomes a question with an answer.
    $stats = $push->notifyTest($userId);
    api_out(['ok' => true, 'test' => $stats]);
}

if ($action !== 'register') {
    api_err('unknown_action', 'Only register, unregister, report and test are known here.', 422);
}

$platform = api_field($in, 'platform', 'android') === 'ios' ? 'ios' : 'android';
$rich = api_field($in, 'rich', '0');
$rich = in_array(strtolower($rich), ['1', 'true', 'yes'], true);

if (!$push->register($userId, $token, $platform, $rich)) {
    api_err('storage_failed', 'The device could not be remembered. Try again shortly.', 502);
}

api_out(['ok' => true, 'registered' => true, 'rich' => $rich]);
