<?php
/**
 * Paystack server-to-server notification.
 *
 * This is what makes "he paid but never got it" impossible rather than unlikely:
 * delivery no longer depends on the buyer's browser staying open. Paystack posts the
 * confirmed transaction here, we match it to the checkout row we opened, and the
 * material (or quiz) is credited and emailed from the server.
 *
 * Point Paystack Dashboard -> Settings -> Integrations -> Webhooks at this URL.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/materials_paid.php';

header('Content-Type: application/json');

function webhook_reply(string $status, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['status' => $status]);
    exit;
}

$raw = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $raw === '') {
    webhook_reply('ignored');
}

// The signature is over the exact bytes we received, so verify before parsing.
$expected = hash_hmac('sha512', $raw, PAYSTACK_SECRET_KEY);
if (!is_string($signature) || $signature === '' || !hash_equals($expected, strtolower($signature))) {
    log_error('Paystack webhook rejected: bad signature', ['ip' => get_client_ip()]);
    webhook_reply('invalid signature', 401);
}

$event = json_decode($raw, true);
if (!is_array($event)) {
    webhook_reply('malformed');
}

$type = (string)($event['event'] ?? '');
$data = is_array($event['data'] ?? null) ? $event['data'] : [];
$reference = trim((string)($data['reference'] ?? ''));

// Anything that is not a completed charge needs no action - but answering 200 keeps
// Paystack from retry-storming this endpoint.
if ($type !== 'charge.success' || $reference === '' || ($data['status'] ?? '') !== 'success') {
    webhook_reply('ignored');
}

try {
    if (strpos($reference, 'MAT-') === 0) {
        $result = material_confirm_ref($GLOBALS['pdo'], $reference, $data);
        log_error('Paystack webhook (material) ' . $reference . ': '
            . ($result['credited'] ? 'credited' : $result['reason']), []);
        webhook_reply($result['credited'] ? 'credited' : 'no-change');
    }

    $result = paystack_credit_quiz_transaction($GLOBALS['pdo'], $reference, $data);
    log_error('Paystack webhook (quiz) ' . $reference . ': '
        . ($result['ok'] ? 'credited' : $result['reason']), []);
    webhook_reply($result['ok'] ? 'credited' : 'no-change');
} catch (Throwable $e) {
    log_error('Paystack webhook failed: ' . $e->getMessage(), ['reference' => $reference]);
    // 200 anyway: a permanent error will not be fixed by retrying, and the student
    // still has the page-load and return-redirect paths.
    webhook_reply('error');
}
