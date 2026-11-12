<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Post.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $postModel = new Post($pdo);

    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'cards';
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $level = isset($_GET['level']) ? (($_GET['level'] === 'University') ? 'University' : (($_GET['level'] === 'Secondary') ? 'Secondary' : null)) : null;
    $universityId = isset($_GET['university_id']) ? (int)$_GET['university_id'] : 0;
    $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 24;

    // Suggestions mode: return list of matching titles
    if ($mode === 'suggest') {
        $rows = method_exists($postModel, 'searchPosts')
            ? $postModel->searchPosts($q, null, null, 10, 0)
            : $postModel->list(10, 0);
        $out = [];
        foreach ($rows as $r) {
            if ($q === '' || stripos($r['title'], $q) !== false || stripos($r['excerpt'] ?? '', $q) !== false || stripos($r['content'] ?? '', $q) !== false) {
                $out[] = [
                    'title' => $r['title'],
                    'slug' => $r['slug'],
                ];
            }
            if (count($out) >= 10) break;
        }
        echo json_encode(['ok' => true, 'suggestions' => $out]);
        exit;
    }

    // Cards HTML mode
    if ($q !== '' && method_exists($postModel, 'searchPosts')) {
        $posts = $postModel->searchPosts($q, $level, $universityId ?: null, $limit, 0);
    } elseif (method_exists($postModel, 'listFiltered')) {
        $posts = $postModel->listFiltered($level, $universityId ?: null, $limit, 0);
    } else {
        $posts = $postModel->list($limit, 0);
    }

    // Build uni map
    $uniMap = [];
    if (method_exists($postModel, 'listUniversities')) {
        foreach ($postModel->listUniversities() as $u) {
            $uniMap[(int)$u['id']] = $u['acronym'];
        }
    }

    // Build HTML for cards (reuse same structure as blog.php)
    ob_start();
    if (empty($posts)) {
        echo '<div class="col-12"><div class="alert alert-info">No posts found.</div></div>';
    } else {
        foreach ($posts as $p) {
            $title = htmlspecialchars($p['title']);
            $slug = urlencode($p['slug']);
            $date = htmlspecialchars(date('M j, Y', strtotime($p['created_at'])));
            $excerpt = htmlspecialchars($p['excerpt'] ?? '');
            $text = trim(($p['content'] ?? '') !== '' ? $p['content'] : ($p['excerpt'] ?? ''));
            $words = str_word_count(strip_tags($text));
            $readMins = max(1, (int)ceil($words / 200));
            $levelV = $p['level'] ?? 'Secondary';
            $isUni = ($levelV === 'University');
            $isGeneral = !empty($p['is_university_general']);
            $uniAcr = '';
            if ($isUni && !$isGeneral) { $uid = (int)($p['university_id'] ?? 0); $uniAcr = $uniMap[$uid] ?? ''; }
            echo '<div class="col-md-6 col-lg-4 reveal-on-scroll">';
            echo '  <div class="post-card h-100">';
            echo '    <div class="card-body d-flex flex-column">';
            echo '      <div class="d-flex justify-content-between align-items-center mb-2">';
            echo '        <div class="d-flex align-items-center gap-2 flex-wrap">';
            if ($isUni) {
                echo '          <span class="badge badge-university">University</span>';
                if ($isGeneral) {
                    echo '      <span class="badge badge-general">General</span>';
                } elseif ($uniAcr) {
                    echo '      <span class="badge badge-soft">' . htmlspecialchars($uniAcr) . '</span>';
                }
            } else {
                echo '          <span class="badge badge-secondary">Secondary</span>';
            }
            echo '        </div>';
            echo '        <span class="post-meta small"><i class="far fa-clock me-1"></i>' . $readMins . ' min read</span>';
            echo '      </div>';
            echo '      <h5 class="card-title mb-2" style="line-height:1.3">' . $title . '</h5>';
            echo '      <div class="small text-muted mb-3"><i class="far fa-calendar-alt me-1"></i>' . $date . '</div>';
            echo '      <p class="text-muted mb-4" style="min-height:64px;">' . mb_strimwidth($excerpt, 0, 140, '...') . '</p>';
            echo '      <div class="mt-auto">';
            echo '        <a class="btn btn-primary w-100" href="' . SITE_URL . '/post.php?slug=' . $slug . '">Read Article <i class="fas fa-arrow-right ms-1"></i></a>';
            echo '      </div>';
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
