<?php
/**
 * Push subscription management endpoint
 * Handles subscribe, unsubscribe, and public key retrieval
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/WebPushService.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$pushService = new WebPushService($pdo);

switch ($action) {

    // Return the VAPID public key so the frontend can subscribe
    case 'get_public_key':
        echo json_encode([
            'success'   => true,
            'publicKey' => $pushService->getPublicKey(),
        ]);
        break;

    // Save a new push subscription
    case 'subscribe':
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input)) {
            $input = $_POST;
        }

        $endpoint = $input['endpoint'] ?? '';
        $p256dh   = $input['p256dh']   ?? ($input['keys']['p256dh'] ?? '');
        $auth     = $input['auth']     ?? ($input['keys']['auth']   ?? '');

        if (empty($endpoint) || empty($p256dh) || empty($auth)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing subscription fields']);
            exit;
        }

        $ok = $pushService->saveSubscription($userId, $endpoint, $p256dh, $auth);
        echo json_encode(['success' => $ok]);
        break;

    // Remove a push subscription
    case 'unsubscribe':
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input)) $input = $_POST;
        $endpoint = $input['endpoint'] ?? '';

        if (empty($endpoint)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing endpoint']);
            exit;
        }

        $ok = $pushService->removeSubscription($endpoint);
        echo json_encode(['success' => $ok]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
