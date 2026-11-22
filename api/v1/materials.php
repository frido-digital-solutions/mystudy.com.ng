<?php
/**
 * Study materials list for the app. Same model, same filters and the same
 * "approved only" rule as materials.php, so the phone can neither show an
 * unreviewed file nor hide one the website shows.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Material.php';
require_once __DIR__ . '/../../models/MaterialPayment.php';
require_once __DIR__ . '/../../includes/material_progress.php';

$user = api_auth();
$userId = (int)$user['id'];

$materialModel = new Material($pdo);
$payments = new MaterialPayment($pdo);

$q = api_field($_GET, 'q');
$level = api_field($_GET, 'level');
$board = api_field($_GET, 'board');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;
$purchased = api_field($_GET, 'view') === 'purchased';

$levelParam = in_array($level, ['Secondary', 'University'], true) ? $level : null;
$boardParam = ($levelParam === 'Secondary' && in_array($board, ['General', 'WAEC', 'NECO', 'JAMB', 'GCE'], true)) ? $board : null;

// The phone's shelf also holds free files THIS device downloaded, and the server
// has no list of those. The app sends the ids it kept so their reading position
// (and nothing else) can come back with them; a device cannot ask for a file it
// does not hold, because the same access rule is applied to every row below.
$heldIds = [];
preg_match_all('/\d+/', (string)api_field($_GET, 'ids'), $held);
foreach ($held[0] as $raw) {
    $heldIds[] = (int)$raw;
}
$heldIds = array_slice(array_values(array_unique($heldIds)), 0, 80);

// The app's shelf offers Free / Paid / Mixed. Mixed is the absence of a filter,
// so an unrecognised value must not quietly become "free only".
$priceParam = ['free' => 0, 'paid' => 1][strtolower((string)api_field($_GET, 'price'))] ?? null;

if ($heldIds) {
    $rows = $materialModel->listByIds($heldIds, count($heldIds), 0);
    $total = count($rows);
} elseif ($purchased) {
    $ownedAll = $payments->allOwnedIds($userId);
    $rows = $ownedAll ? $materialModel->listByIds($ownedAll, $limit, $offset) : [];
    $total = count($ownedAll);
} else {
    $rows = $materialModel->searchMaterials($q !== '' ? $q : null, $levelParam, $boardParam, null, null, $limit, $offset, 'approved', $priceParam);
    $total = $materialModel->countSearchMaterials($q !== '' ? $q : null, $levelParam, $boardParam, null, null, 'approved', $priceParam);
}

$ownedIds = array_map('intval', $payments->ownedIds($userId, array_column($rows, 'id')));
$readProgress = material_progress_for($pdo, $userId, array_column($rows, 'id'));

api_out([
    'ok' => true,
    'items' => array_map(function ($m) use ($ownedIds, $readProgress) {
        $mid = (int)$m['id'];
        $price = isset($m['price']) ? (float)$m['price'] : 0.0;
        $bytes = (int)($m['file_size'] ?? 0);
        $extension = strtoupper(pathinfo((string)($m['file_path'] ?? ''), PATHINFO_EXTENSION));
        $read = $readProgress[$mid] ?? null;
        $isPaid = (int)($m['is_paid'] ?? 0) === 1 && $price > 0;
        $mine = in_array($mid, $ownedIds, true);
        return [
            'id' => $mid,
            'title' => (string)$m['title'],
            'description' => mb_strimwidth(strip_tags((string)($m['description'] ?? '')), 0, 150, '…'),
            'category' => $m['category_name'] ?? null,
            'level' => $m['level'] ?? null,
            'subject' => $m['subject'] ?? null,
            'author' => $m['author_name'] ?? null,
            'downloads' => (int)($m['download_count'] ?? 0),
            'format' => $extension === '' ? 'FILE' : $extension,
            'readable' => $extension === 'PDF',
            'size_bytes' => $bytes,
            'size_label' => $bytes > 0 ? ($bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int)round($bytes / 1024)) . ' KB') : '',
            'is_paid' => $isPaid,
            'price' => $price,
            'price_label' => $price > 0 ? '₦' . number_format($price, 0) : 'Free',
            'owned' => $mine,
            // The reader and the downloader both decide with material_can_access();
            // this is the same rule stated for the list, so no row promises a file
            // the ticket endpoint will then refuse.
            'can_read' => $mine || !$isPaid,
            // null means this account has never opened the file, which is a
            // different thing from having opened it and stopped on page one.
            'percent_read' => $read === null ? null : (int)$read['percent'],
            'read_page' => $read === null ? 0 : (int)$read['page'],
            'read_pages' => $read === null ? 0 : (int)$read['pages'],
            'created_at' => $m['created_at'] ?? null,
        ];
    }, $rows),
    'total' => $total,
    'page' => $page,
    'pages' => (int)ceil($total / $limit) ?: 1,
    'boards' => ['General', 'WAEC', 'NECO', 'JAMB', 'GCE'],
]);
