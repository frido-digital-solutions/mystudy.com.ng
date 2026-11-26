<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Post.php';
require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/includes/seo.php';

header('Content-Type: application/xml; charset=utf-8');

$urls = [];
$base = rtrim(SITE_URL, '/');
$now  = date('c');

function add_url(array &$urls, string $loc, string $changefreq = 'weekly', float $priority = 0.5, ?string $lastmod = null): void {
    $urls[] = [
        'loc' => $loc,
        'changefreq' => $changefreq,
        'priority' => $priority,
        'lastmod' => $lastmod,
    ];
}

// Static core pages
add_url($urls, $base . '/', 'daily', 1.0);
add_url($urls, $base . '/about.php', 'yearly', 0.4);
add_url($urls, $base . '/leaderboard.php', 'daily', 0.7);
add_url($urls, $base . '/materials.php', 'weekly', 0.7);
add_url($urls, $base . '/tutorials.php', 'weekly', 0.7);
add_url($urls, $base . '/quizzes.php', 'daily', 0.9);
add_url($urls, $base . '/discussions.php', 'daily', 0.8);
add_url($urls, $base . '/battle_hub.php', 'daily', 0.7);
add_url($urls, $base . '/blog.php', 'daily', 0.8);
add_url($urls, $base . '/login.php', 'yearly', 0.3);
add_url($urls, $base . '/register.php', 'yearly', 0.3);

// Dynamic posts
$postModel = new Post($pdo);
if (method_exists($postModel, 'list')) {
    $posts = $postModel->list(5000, 0);
    foreach ($posts as $p) {
        $loc = $base . '/post.php?slug=' . rawurlencode($p['slug']);
        $last = !empty($p['created_at']) ? date('c', strtotime($p['created_at'])) : $now;
        add_url($urls, $loc, 'weekly', 0.8, $last);
    }
}

// The three examination pages and every subject of every examination. These
// are the pages students search for by name - "waec past questions", "neco
// cbt", "jamb english language" - so they are listed first and given the
// highest priority.
foreach (seo_exam_pages() as $examPage) {
    add_url($urls, $base . '/' . $examPage['file'], 'daily', 0.9);
    foreach (seo_exam_subjects($pdo, $examPage['key']) as $subjectRow) {
        add_url(
            $urls,
            $base . '/exam-subject.php?exam=' . rawurlencode($examPage['key']) . '&subject=' . rawurlencode($subjectRow['subject']),
            'weekly',
            0.8
        );
    }
}

// Dynamic quizzes (public)
$quizAdmin = new QuizAdmin($pdo);
if (method_exists($quizAdmin, 'listAllPublic')) {
    $quizzes = $quizAdmin->listAllPublic(5000, 0);
    foreach ($quizzes as $q) {
        $loc = $base . '/quiz.php?id=' . (int)$q['id'];
        $last = !empty($q['created_at']) ? date('c', strtotime($q['created_at'])) : $now;
        add_url($urls, $loc, 'weekly', 0.7, $last);
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?php echo htmlspecialchars($u['loc'], ENT_XML1); ?></loc>
    <?php if (!empty($u['lastmod'])): ?><lastmod><?php echo $u['lastmod']; ?></lastmod><?php endif; ?>
    <changefreq><?php echo $u['changefreq']; ?></changefreq>
    <priority><?php echo number_format($u['priority'], 1); ?></priority>
  </url>
<?php endforeach; ?>
</urlset>

