<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Material.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $materialModel = new Material($pdo);

    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'cards';
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $level = isset($_GET['level']) ? (($_GET['level'] === 'University') ? 'University' : (($_GET['level'] === 'Secondary') ? 'Secondary' : null)) : null;
    $secondaryBoard = isset($_GET['secondary_board']) ? (string)$_GET['secondary_board'] : '';
    $isUniGen = isset($_GET['is_university_general']) ? (int)!!$_GET['is_university_general'] : null;
    $universityId = isset($_GET['university_id']) ? (int)$_GET['university_id'] : 0;
    $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 12;

    // Suggestions mode: return id + title list
    if ($mode === 'suggest') {
        $rows = $materialModel->searchMaterials(
            $q !== '' ? $q : null,
            null,
            null,
            null,
            null,
            20,
            0,
            'approved'
        );
        $out = [];
        foreach ($rows as $r) {
            if ($q === '' || stripos($r['title'], $q) !== false || stripos($r['description'] ?? '', $q) !== false) {
                $out[] = [
                    'id' => (int)$r['id'],
                    'title' => $r['title'],
                ];
            }
            if (count($out) >= 10) break;
        }
        echo json_encode(['ok' => true, 'suggestions' => $out]);
        exit;
    }

    // Cards HTML mode
    $materials = $materialModel->searchMaterials(
        $q !== '' ? $q : null,
        $level,
        $secondaryBoard ?: null,
        $isUniGen,
        $universityId ?: null,
        $limit,
        0,
        'approved'
    );

    // Build university id => acronym map
    $uniMap = [];
    foreach ($materialModel->listUniversities() as $u) {
        $uniMap[(int)$u['id']] = $u['acronym'] ?: $u['name'];
    }

    // Build HTML for cards (reuse same structure as materials.php)
    ob_start();
    if (empty($materials)) {
        $parts = [];
        if ($q !== '') { $parts[] = "search ‘" . htmlspecialchars($q) . "’"; }
        if ($level === 'Secondary') {
            $parts[] = 'level Secondary';
            if ($secondaryBoard !== '') { $parts[] = 'scope ' . htmlspecialchars($secondaryBoard); }
        } elseif ($level === 'University') {
            $parts[] = 'level University';
            if ($isUniGen === 1) { $parts[] = 'University General'; }
            if (!empty($universityId)) { 
                $ac = $uniMap[(int)$universityId] ?? ('ID ' . (int)$universityId);
                $parts[] = 'university ' . htmlspecialchars($ac); 
            }
        }
        $detail = $parts ? (' matching ' . implode(' · ', $parts)) : '';
        echo '<div class="col-12"><div class="alert alert-info">No materials' . $detail . ' found.</div></div>';
    } else {
        foreach ($materials as $m) {
            $title = htmlspecialchars($m['title']);
            $desc = htmlspecialchars(mb_strimwidth($m['description'] ?? '', 0, 100, '...'));
            $levelV = $m['level'] ?? 'Secondary';
            $isUni = ($levelV === 'University');
            $isUniGenV = !empty($m['is_university_general']);
            $secBoard = htmlspecialchars($m['secondary_board'] ?? 'General');
            $viewHref = SITE_URL . '/material.php?id=' . (int)$m['id'];
            $downloadUrl = SITE_URL . '/' . ltrim(($m['file_path'] ?? ''), '/');
            $downloadName = !empty($m['original_name']) ? $m['original_name'] : basename($m['file_path'] ?? '');

            echo '<div class="col-md-4">';
            echo '  <div class="card h-100">';
            echo '    <div class="card-body">';
            echo '      <h5 class="card-title mb-2">' . $title . '</h5>';
            echo '      <div class="small text-muted mb-2">';
            echo          htmlspecialchars($levelV);
            if ($levelV === 'Secondary') {
                echo ' · Scope: ' . $secBoard;
            } else {
                if ($isUniGenV) {
                    echo ' · University General';
                } elseif (!empty($m['university_id'])) {
                    echo ' · University-specific';
                }
            }
            echo '      </div>';
            echo '      <p class="card-text text-muted" style="min-height:48px;">' . $desc . '</p>';
            echo '    </div>';
            echo '    <div class="card-footer bg-transparent border-0 d-flex justify-content-between">';
            echo '      <a href="' . htmlspecialchars($viewHref) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i> View</a>';
            echo '      <a href="' . htmlspecialchars($downloadUrl) . '" class="btn btn-sm btn-primary" download="' . htmlspecialchars($downloadName) . '"><i class="fas fa-download me-1"></i> Download</a>';
            echo '    </div>';
            echo '  </div>';
            echo '</div>';
        }
    }
    $html = ob_get_clean();

    echo json_encode(['ok' => true, 'html' => $html]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
