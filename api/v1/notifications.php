<?php
/**
 * Notifications for the app. GET lists them, POST marks one or all as read -
 * both paths go through the same Notification model the website bell uses.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Notification.php';
require_once __DIR__ . '/../../includes/notification_target.php';

$user = api_auth();
$userId = (int)$user['id'];
$notificationModel = new Notification($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    api_require_post();
    $in = api_input();
    $id = (int)api_field($in, 'id');

    if (api_field($in, 'action') === 'read_all') {
        $notificationModel->markAllAsRead($userId);
    } elseif ($id > 0) {
        $notificationModel->markAsRead($id, $userId);
    }

    api_out(['ok' => true, 'unread' => $notificationModel->getUnreadCount($userId)]);
}

$limit = max(10, min(100, (int)($_GET['limit'] ?? 50)));

api_out([
    'ok' => true,
    'items' => array_map(static function ($n) {
        return array_merge([
            'id' => (int)$n['id'],
            'type' => (string)($n['type'] ?? ''),
            'title' => (string)($n['title'] ?? ''),
            'body' => (string)($n['message'] ?? ''),
            'link' => $n['link'] ?? null,
            'read' => (int)($n['is_read'] ?? 0) === 1,
            'created_at' => $n['created_at'] ?? null,
        ], notification_target_fields($n['link'] ?? null));
    }, $notificationModel->getUserNotifications($userId, $limit)),
    'unread' => $notificationModel->getUnreadCount($userId),
]);
