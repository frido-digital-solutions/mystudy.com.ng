<?php
/**
 * How far into a material a student has actually got.
 *
 * The reader page writes it, the materials list and the app read it, and nothing
 * else touches it. Only the furthest page reached is kept, so scrolling back to
 * check an earlier answer never makes somebody's progress disappear.
 *
 * The table is created the first time it is needed, exactly like the API's token
 * store, because this is shared hosting where nobody runs SQL by hand.
 */

function material_progress_ready(PDO $pdo): bool
{
    static $ready = false;
    if ($ready) {
        return true;
    }
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS material_progress (
            user_id INT NOT NULL,
            material_id INT NOT NULL,
            page INT NOT NULL DEFAULT 1,
            pages INT NOT NULL DEFAULT 1,
            percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (user_id, material_id),
            KEY material_id (material_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $ready = true;
    } catch (Throwable $e) {
        error_log('material_progress unavailable: ' . $e->getMessage());
        return false;
    }
    return true;
}

/** Record the page in view. Returns the stored percentage. */
function material_progress_save(PDO $pdo, int $userId, int $materialId, int $page, int $pages): int
{
    if ($userId <= 0 || $materialId <= 0 || !material_progress_ready($pdo)) {
        return 0;
    }
    $pages = max(1, $pages);
    $page = min(max(1, $page), $pages);
    // The last page counts as finished, the first page of a long document does not.
    $percent = $page >= $pages ? 100 : (int)floor(($page - 1) / $pages * 100);

    $stmt = $pdo->prepare(
        'INSERT INTO material_progress (user_id, material_id, page, pages, percent, updated_at)
         VALUES (:u, :m, :p, :n, :pct, NOW())
         ON DUPLICATE KEY UPDATE
            page = GREATEST(page, :p2),
            pages = :n2,
            percent = GREATEST(percent, :pct2),
            updated_at = NOW()'
    );
    $stmt->execute([
        ':u' => $userId, ':m' => $materialId, ':p' => $page, ':n' => $pages, ':pct' => $percent,
        ':p2' => $page, ':n2' => $pages, ':pct2' => $percent,
    ]);
    return $percent;
}

/** One query for a whole page of the library: material id => percentage. */
function material_progress_for(PDO $pdo, int $userId, array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if ($userId <= 0 || !$ids || !material_progress_ready($pdo)) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT material_id, page, pages, percent FROM material_progress WHERE user_id = ? AND material_id IN ($in)");
    $stmt->execute(array_merge([$userId], $ids));
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int)$row['material_id']] = [
            'percent' => (int)$row['percent'],
            'page' => (int)$row['page'],
            'pages' => (int)$row['pages'],
        ];
    }
    return $out;
}
