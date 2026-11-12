<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Tutorial.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $tutorialModel = new Tutorial($pdo);

    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'cards';
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $level = isset($_GET['level']) ? (($_GET['level'] === 'University') ? 'University' : (($_GET['level'] === 'Secondary') ? 'Secondary' : null)) : null;
    $course = isset($_GET['course']) ? trim((string)$_GET['course']) : '';
    $limit = 12;
    $page = isset($_GET['page']) ? max(1, (int)($_GET['page'])) : 1;
    $offset = ($page - 1) * $limit;

    if ($mode === 'suggest') {
        $rows = $tutorialModel->searchTutorials($q !== '' ? $q : null, null, null, 20, 0);
        $out = [];
        foreach ($rows as $r) {
            if ($q === '' || stripos($r['title'], $q) !== false || stripos($r['description'] ?? '', $q) !== false) {
                $out[] = ['title' => $r['title']];
            }
            if (count($out) >= 10) break;
        }
        echo json_encode(['ok' => true, 'suggestions' => $out]);
        exit;
    }

    if ($q !== '' || $level || $course) {
        $tutorials = $tutorialModel->searchTutorials($q, $level, $course ?: null, $limit, $offset);
        $totalCount = $tutorialModel->countSearchTutorials($q, $level, $course ?: null);
    } else {
        $tutorials = $tutorialModel->list($limit, $offset);
        $totalCount = $tutorialModel->countAllTutorials();
    }
    $totalPages = ceil($totalCount / $limit) ?: 1;

    ob_start();
    if (empty($tutorials)) {
        $parts = [];
        if ($q !== '') { $parts[] = "search ‘" . htmlspecialchars($q) . "’"; }
        if ($level === 'Secondary') { $parts[] = 'level Secondary'; }
        elseif ($level === 'University') { $parts[] = 'level University'; }
        if ($course !== '') { $parts[] = 'course ' . htmlspecialchars($course); }
        $detail = $parts ? (' matching ' . implode(' · ', $parts)) : '';
        echo '<div class="col-12"><div class="alert alert-info border-0 shadow-sm rounded-3"><i class="fas fa-info-circle me-2"></i>No tutorials' . $detail . ' found.</div></div>';
    } else {
        echo '<div id="tutorialsContainer">';
        echo '  <div class="result-bar mb-4 d-flex justify-content-between align-items-center">';
        echo '      <div class="result-count">Found <strong>' . number_format($totalCount) . '</strong> tutorials</div>';
        echo '  </div>';
        echo '  <div class="row g-4" id="tutorialsGrid">';
        foreach ($tutorials as $t) {
            $ytid = htmlspecialchars($t['youtube_id']);
            $thumb = htmlspecialchars($t['thumbnail_url'] ?: ('https://img.youtube.com/vi/' . $t['youtube_id'] . '/hqdefault.jpg'));
            $title = htmlspecialchars($t['title']);
            $desc  = htmlspecialchars(mb_strimwidth($t['description'] ?? '', 0, 140, '...'));
            $url   = htmlspecialchars($t['youtube_url']);
            $levelTxt = htmlspecialchars($t['level']);
            $courseTags = array_map(function($c){ return ['name'=>$c['name']]; }, $tutorialModel->getCoursesForTutorial((int)$t['id']));
            
            echo '<div class="col-md-6 col-lg-4">';
            echo '  <div class="tut-card h-100">';
            echo '      <div class="tut-thumb" data-ytid="'.$ytid.'">';
            echo '          <img src="'.$thumb.'" alt="'.$title.'" loading="lazy">';
            echo '          <div class="tut-play"><button type="button" class="tut-play-btn"><i class="fas fa-play"></i></button></div>';
            echo '      </div>';
            echo '      <div class="tut-body">';
            echo '          <div class="tut-badges">';
            echo '              <span class="tut-badge tut-badge-level">'.$levelTxt.'</span>';
            foreach ($courseTags as $ct) {
                echo '              <span class="tut-badge tut-badge-course">'.htmlspecialchars($ct['name']).'</span>';
            }
            echo '          </div>';
            echo '          <h5 class="tut-title">'.$title.'</h5>';
            echo '          <p class="tut-desc">'.$desc.'</p>';
            echo '          <div class="tut-footer">';
            echo '              <a target="_blank" href="'.$url.'" class="tut-yt-link"><i class="fab fa-youtube"></i> Watch on YouTube</a>';
            echo '          </div>';
            echo '      </div>';
            echo '  </div>';
            echo '</div>';
        }
        echo '  </div>'; // close row

        if ($totalPages > 1) {
            $queryParams = $_GET;
            unset($queryParams['page']);
            unset($queryParams['ajax']);
            $qs = http_build_query($queryParams);
            $baseUrl = '?' . ($qs ? $qs . '&' : '') . 'page=';
            
            echo '<nav aria-label="Page navigation" class="mt-5">';
            echo '  <ul class="pagination justify-content-center">';
            echo '      <li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '">';
            echo '          <a class="page-link shadow-sm" href="'.$baseUrl.($page-1).'" data-ajax-page="'.($page-1).'"><i class="fas fa-chevron-left"></i> Previous</a>';
            echo '      </li>';
            for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
                echo '      <li class="page-item ' . ($i === $page ? 'active' : '') . '">';
                echo '          <a class="page-link shadow-sm" href="'.$baseUrl.$i.'" data-ajax-page="'.$i.'">'.$i.'</a>';
                echo '      </li>';
            }
            echo '      <li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '">';
            echo '          <a class="page-link shadow-sm" href="'.$baseUrl.($page+1).'" data-ajax-page="'.($page+1).'">Next <i class="fas fa-chevron-right"></i></a>';
            echo '      </li>';
            echo '  </ul>';
            echo '</nav>';
        }
        echo '</div>'; // close tutorialsContainer

    }
    $html = ob_get_clean();
    echo json_encode(['ok' => true, 'html' => $html]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
