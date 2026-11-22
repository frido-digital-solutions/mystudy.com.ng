<?php
/**
 * The people directory for the app - the same list the website's Find Friends
 * page (users.php) shows, from the same columns, in the same order.
 *
 *   GET people.php                      -> the most recently active people
 *   GET people.php?filter=newest        -> newest members first
 *   GET people.php?filter=university    -> only university students
 *   GET people.php?filter=secondary     -> only secondary students
 *   GET people.php?q=adu                -> names containing that text
 *
 * A level is only shown when that person lets it be shown, and presence comes
 * from presence_for_users(), so someone who switched off their active status is
 * Offline here exactly as they are in a chat. Nobody's email, password or
 * session detail leaves this file.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Gamification.php';

$viewer = api_auth();
$viewerId = (int)$viewer['id'];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$filter = (string)($_GET['filter'] ?? 'active');
$query = trim((string)($_GET['q'] ?? ''));

$where = "WHERE u.status = 'active' AND u.id != ?";
$params = [$viewerId];

// The web's "Online" tab does not actually filter - it sorts the most recently
// active people first. That is the behaviour being copied, not invented.
$orderBy = 'u.last_active IS NULL, u.last_active DESC, u.id DESC';
if ($filter === 'newest') {
    $orderBy = 'u.created_at DESC';
} elseif ($filter === 'oldest') {
    $orderBy = 'u.created_at ASC';
} elseif ($filter === 'university') {
    $where .= " AND u.user_type = 'university'";
} elseif ($filter === 'secondary') {
    $where .= " AND u.user_type = 'secondary'";
}

if ($query !== '') {
    $where .= ' AND u.name LIKE ?';
    $params[] = '%' . $query . '%';
}

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.profile_picture, u.user_type, u.points, u.created_at,
               u.show_level_on_users_page,
               lm.meta_value AS level_of_study,
               ls.meta_value AS share_status
          FROM users u
          LEFT JOIN user_meta lm ON lm.user_id = u.id AND lm.meta_key = 'level'
          LEFT JOIN user_meta ls ON ls.user_id = u.id AND ls.meta_key = 'privacy_share_status'
         {$where}
         ORDER BY {$orderBy}
         LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('api people: ' . $e->getMessage());
    api_err('storage_unavailable', 'The people list is not ready yet. Please retry shortly.', 503);
}

// One query for the page for presence, and with it the withholding rule.
$presence = presence_for_users(array_column($rows, 'id'), $pdo);

// Who this viewer has already blocked, so a card can say so instead of offering
// a Message button the server would refuse.
$blockedSet = [];
try {
    $stmt = $pdo->prepare('SELECT blocked_id FROM user_blocks WHERE blocker_id = ?');
    $stmt->execute([$viewerId]);
    $blockedSet = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Throwable $e) {
    error_log('api people blocks: ' . $e->getMessage());
}

$items = array_map(static function ($u) use ($presence, $blockedSet) {
    $id = (int)$u['id'];
    $state = $presence[$id] ?? ['online' => false, 'last_active' => null, 'share' => true];
    $type = (string)($u['user_type'] ?? '');
    $level = Gamification::getLevel((int)($u['points'] ?? 0));
    $rank = $type === 'secondary'
        ? (Gamification::SEC_LEVEL_NAMES[$level] ?? 'Student')
        : (Gamification::UNI_LEVEL_NAMES[$level] ?? 'Scholar');

    return [
        'id' => $id,
        'name' => (string)$u['name'],
        'picture' => api_url($u['profile_picture'] ?? null),
        'user_type' => $type,
        'points' => (int)($u['points'] ?? 0),
        // Their own course level if they set one, and the gamification level the
        // whole site calculates from points. The card shows whichever it has.
        'level_of_study' => $u['level_of_study'] ?? null,
        'level' => (int)$u['show_level_on_users_page'] === 0 ? null : $level,
        'rank' => (int)$u['show_level_on_users_page'] === 0 ? null : $rank,
        'online' => $state['online'],
        'last_seen' => $state['last_active'],
        'status_hidden' => !$state['share'],
        'i_blocked' => in_array($id, $blockedSet, true),
        'member_since' => $u['created_at'] ?? null,
    ];
}, $rows);

api_out([
    'ok' => true,
    'items' => $items,
    'page' => $page,
    'total' => $total,
    'has_more' => $offset + count($items) < $total,
]);
