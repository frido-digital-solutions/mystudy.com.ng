<?php
require_once __DIR__ . '/config/config.php';
$page_title = 'Tutorials';
require_once __DIR__ . '/models/Tutorial.php';

$tutorialModel = new Tutorial($pdo);

// Params (GET)
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$level = isset($_GET['level']) ? (($_GET['level'] === 'University') ? 'University' : (($_GET['level'] === 'Secondary') ? 'Secondary' : null)) : null;
$course = isset($_GET['course']) ? trim((string)$_GET['course']) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Data
if ($q !== '' || $level || $course) {
    $tutorials = $tutorialModel->searchTutorials($q, $level, $course ?: null, $limit, $offset);
    $totalCount = $tutorialModel->countSearchTutorials($q, $level, $course ?: null);
} else {
    $tutorials = $tutorialModel->list($limit, $offset);
    $totalCount = $tutorialModel->countAllTutorials();
}
$totalPages = ceil($totalCount / $limit) ?: 1;
$courses = $tutorialModel->listCourses();

include __DIR__ . '/includes/header.php';
?>

<!-- Styles loaded from design-system.css -->

<!-- Page Header -->
<div class="page-header">
    <div class="container d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <h1 class="page-title">Video Tutorials</h1>
            <p class="text-muted mb-0">Curated YouTube lessons for popular courses</p>
        </div>
    </div>
</div>

<div class="container pb-5">
    <!-- Search & Filter -->
    <div class="search-card">
        <form class="row g-3 align-items-end" method="get" action="">
            <div class="col-lg-5 position-relative">
                <label class="form-label">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="qInput" name="q" placeholder="Search by title or description" value="<?php echo htmlspecialchars($q); ?>" autocomplete="off">
                </div>
                <div id="searchSuggest" class="list-group position-absolute w-100 shadow-sm" style="z-index:1000; display:none; max-height: 260px; overflow:auto;"></div>
            </div>
            <div class="col-md-6 col-lg-2">
                <label class="form-label">Level</label>
                <select class="form-select" id="levelFilter" name="level">
                    <option value="" <?php echo $level===null?'selected':''; ?>>All Levels</option>
                    <option value="Secondary" <?php echo $level==='Secondary'?'selected':''; ?>>Secondary</option>
                    <option value="University" <?php echo $level==='University'?'selected':''; ?>>University</option>
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label">Course</label>
                <select class="form-select" id="courseFilter" name="course">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $c): $slug = htmlspecialchars($c['slug']); ?>
                        <option value="<?php echo $slug; ?>" <?php echo ($course===$c['slug'])?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 col-lg-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 rounded-3">Apply Filters</button>
            </div>
        </form>
    </div>

    <!-- Results -->
    <?php if (empty($tutorials)): ?>
        <div class="empty-state">
            <i class="fab fa-youtube empty-icon"></i>
            <h4>No tutorials found</h4>
            <div class="text-muted">
                <?php
                $parts = [];
                if ($q !== '') { $parts[] = "\"" . htmlspecialchars($q) . "\""; }
                if ($level) { $parts[] = htmlspecialchars($level); }
                if ($course !== '') { $parts[] = htmlspecialchars($course); }
                if ($parts) {
                    echo 'No results for ' . implode(' · ', $parts) . '. Try adjusting your filters.';
                } else {
                    echo 'No tutorials available yet. Check back later!';
                }
                ?>
            </div>
        </div>
    <?php else: ?>
        <div id="tutorialsContainer">
            <div class="result-bar mb-4 d-flex justify-content-between align-items-center">
                <div class="result-count">Found <strong><?php echo number_format($totalCount); ?></strong> tutorials</div>
            </div>
            <div class="row g-4" id="tutorialsGrid">
                <?php foreach ($tutorials as $t): ?>
                <?php
                    $ytid = htmlspecialchars($t['youtube_id']);
                    $thumb = htmlspecialchars($t['thumbnail_url'] ?: ('https://img.youtube.com/vi/' . $t['youtube_id'] . '/hqdefault.jpg'));
                    $title = htmlspecialchars($t['title']);
                    $desc  = htmlspecialchars(mb_strimwidth($t['description'] ?? '', 0, 140, '...'));
                    $url   = htmlspecialchars($t['youtube_url']);
                    $levelTxt = htmlspecialchars($t['level']);
                    $courseTags = array_map(function($c){ return ['name'=>$c['name']]; }, $tutorialModel->getCoursesForTutorial((int)$t['id']));
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="tut-card h-100">
                        <div class="tut-thumb" data-ytid="<?php echo $ytid; ?>">
                            <img src="<?php echo $thumb; ?>" alt="<?php echo $title; ?>" loading="lazy">
                            <div class="tut-play">
                                <button type="button" class="tut-play-btn"><i class="fas fa-play"></i></button>
                            </div>
                        </div>
                        <div class="tut-body">
                            <div class="tut-badges">
                                <span class="tut-badge tut-badge-level"><?php echo $levelTxt; ?></span>
                                <?php foreach ($courseTags as $ct): ?>
                                    <span class="tut-badge tut-badge-course"><?php echo htmlspecialchars($ct['name']); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <h5 class="tut-title"><?php echo $title; ?></h5>
                            <p class="tut-desc"><?php echo $desc; ?></p>
                            <div class="tut-footer">
                                <a target="_blank" href="<?php echo $url; ?>" class="tut-yt-link">
                                    <i class="fab fa-youtube"></i> Watch on YouTube
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): 
                $queryParams = $_GET;
                unset($queryParams['page']);
                unset($queryParams['ajax']);
                $qs = http_build_query($queryParams);
                $baseUrl = '?' . ($qs ? $qs . '&' : '') . 'page=';
            ?>
                <!-- Pagination -->
                <nav aria-label="Page navigation" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link shadow-sm" href="<?php echo $baseUrl . ($page - 1); ?>" data-ajax-page="<?php echo $page - 1; ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link shadow-sm" href="<?php echo $baseUrl . $i; ?>" data-ajax-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link shadow-sm" href="<?php echo $baseUrl . ($page + 1); ?>" data-ajax-page="<?php echo $page + 1; ?>">Next <i class="fas fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Lazy embed YouTube on click (autoplay on first click)
(function(){
  function embed(container, id){
    if (!id) return;
    var card = container.closest('.tut-card');
    var p = document.createElement('div');
    p.className = 'tut-embed';
    p.innerHTML = '<iframe src="https://www.youtube.com/embed/'+encodeURIComponent(id)+'?rel=0&modestbranding=1&autoplay=1" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    container.replaceWith(p);
  }
  document.addEventListener('click', function(e){
    var thumb = e.target.closest('.tut-thumb');
    if (!thumb) return;
    embed(thumb, thumb.getAttribute('data-ytid'));
  });
})();

// AJAX filtering & suggestions
(function(){
  var qInput = document.getElementById('qInput');
  var levelSel = document.getElementById('levelFilter');
  var courseSel = document.getElementById('courseFilter');
  var grid = document.getElementById('tutorialsGrid');
  var suggest = document.getElementById('searchSuggest');
  var controller;

  function params(mode){
    var p = new URLSearchParams();
    p.set('mode', mode || 'cards');
    if (qInput && qInput.value.trim()) p.set('q', qInput.value.trim());
    if (levelSel && levelSel.value) p.set('level', levelSel.value);
    if (courseSel && courseSel.value) p.set('course', courseSel.value);
    p.set('limit','12');
    return p.toString();
  }
  function fetchCards(){
    if (controller) controller.abort();
    controller = new AbortController();
    fetch('<?php echo SITE_URL; ?>/ajax/tutorials.php?' + params('cards'), { signal: controller.signal })
      .then(function(r){ return r.json(); })
      .then(function(data){ if (!data.ok) return; if (grid) grid.innerHTML = data.html; })
      .catch(function(){});
  }
  var suggestTimer;
  function fetchSuggest(){
    clearTimeout(suggestTimer);
    suggestTimer = setTimeout(function(){
      if (!qInput.value.trim()) { suggest.style.display='none'; suggest.innerHTML=''; return; }
      fetch('<?php echo SITE_URL; ?>/ajax/tutorials.php?' + params('suggest'))
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (!data.ok) return;
          var html = '';
          (data.suggestions||[]).forEach(function(s){
            var href = '<?php echo SITE_URL; ?>/tutorials.php?q=' + encodeURIComponent(s.title);
            html += '<a class="list-group-item list-group-item-action" href="'+href+'">'+ s.title.replace(/</g,'&lt;') +'</a>';
          });
          suggest.innerHTML = html || '<div class="list-group-item text-muted">No matches</div>';
          suggest.style.display = 'block';
        });
    }, 250);
  }
  if (qInput){
    qInput.addEventListener('input', function(){ fetchSuggest(); fetchCards(); });
    qInput.addEventListener('focus', function(){ if (suggest.innerHTML) suggest.style.display='block'; });
    document.addEventListener('click', function(e){ if (!suggest.contains(e.target) && e.target !== qInput) { suggest.style.display='none'; } });
  }
  if (levelSel){ levelSel.addEventListener('change', function(){ fetchCards(); }); }
  if (courseSel){ courseSel.addEventListener('change', function(){ fetchCards(); }); }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
