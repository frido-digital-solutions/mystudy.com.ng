<?php
/**
 * Publishes the exam-season posts into the database.
 *
 * The posts live in content/exam_news_posts.php, so this file is the only
 * thing that touches the database and it can be run as many times as you like:
 * a post whose slug is already there is left alone, so running it twice
 * changes nothing. That matters, because the live server and the local
 * machine can both run it without stepping on each other.
 *
 * Run it from the project root:
 *     php cli/publish_exam_posts.php
 *
 * On the live server this is the command to run in cPanel -> Terminal:
 *     cd ~/mystudy.com.ng && php cli/publish_exam_posts.php
 */

require_once __DIR__ . '/../config/config.php';

$posts = require __DIR__ . '/../content/exam_news_posts.php';

// The account the posts are written under. Author 13 is the MyStudy Base
// account that already owns the existing posts.
$authorId = 13;

if (!empty($argv) && in_array('--dry-run', $argv, true)) {
    echo "DRY RUN - nothing will be written\n";
    foreach ($posts as $post) {
        echo "  would publish: {$post['slug']}\n";
    }
    exit(0);
}

$created = 0;
$updated = 0;
$skipped = 0;

$force = !empty($argv) && in_array('--force', $argv, true);

foreach ($posts as $post) {
    $slug   = $post['slug'];
    $title  = $post['title'];
    $excerpt = $post['excerpt'];
    $level  = $post['level'] === 'University' ? 'University' : 'Secondary';
    $isGeneral = !empty($post['university_general']) ? 1 : 0;

    // The post carries the day the news broke - the day of the announcement
    // it is written about - so the blog reads like news rather than like a
    // pile of posts that all landed on the same afternoon. The footer of
    // every post says plainly when it was written.
    $publishedAt = $post['date'] ?? date('Y-m-d H:i:s');
    $dateLabel   = $post['date_label'] ?? date('j F Y');
    // The day the text itself was written and checked.
    $reviewedAt  = '2026-11-26 09:00:00';

    $banner = $post['banner'];
    $image  = 'assets/img/news/' . $banner['file'];
    $imageUrl = '/assets/img/news/' . $banner['file'];

    // The picture goes at the top of the post, because that is the picture
    // the blog page uses for the card, and it is the first thing a reader
    // sees inside the post as well.
    $content = '<p><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES) . '" alt="'
        . htmlspecialchars($title, ENT_QUOTES) . '" width="1200" height="630" style="width:100%;height:auto;border-radius:14px;margin-bottom:1.2rem;"></p>'
        . "\n"
        . $post['content'];

    // The sources, so a reader can go and check the announcement.
    if (!empty($post['sources'])) {
        $content .= "\n<h3>Sources</h3>\n<ul>";
        foreach ($post['sources'] as $source) {
            $content .= "\n<li><a href=\"" . htmlspecialchars($source[1], ENT_QUOTES) . '" rel="nofollow noopener" target="_blank">'
                . htmlspecialchars($source[0], ENT_QUOTES) . '</a></li>';
        }
        $content .= "\n</ul>";
    }

    $content .= "\n<p style=\"font-size:0.85rem;color:#64748b;margin-top:2rem;\">"
        . "<em>This article covers the news of " . htmlspecialchars($dateLabel, ENT_QUOTES)
        . ". Last reviewed 26 November 2026. Always confirm fees, dates and rules on the examination body's own website before you act.</em></p>";

    // A small note at the top of the post, so the page knows when the text
    // itself was last reviewed.
    $content = '<!-- msb-updated: ' . $reviewedAt . ' -->' . "\n" . $content;

    $existing = $pdo->prepare('SELECT id FROM posts WHERE slug = ? LIMIT 1');
    $existing->execute([$slug]);
    $existingId = $existing->fetchColumn();

    if ($existingId) {
        if (!$force) {
            // The post is already published. It is left exactly as it is, so
            // that anything edited by hand in the admin panel is never
            // overwritten by a second run of this script.
            echo "already there: {$slug} (id {$existingId})\n";
            $skipped++;
            $postId = (int)$existingId;
        } else {
            $update = $pdo->prepare('UPDATE posts SET excerpt = :e, content = :c, level = :l,
                                       is_university_general = :g, created_at = :d WHERE id = :id');
            $update->execute([
                ':e'  => $excerpt,
                ':c'  => $content,
                ':l'  => $level,
                ':g'  => $isGeneral,
                ':d'  => $publishedAt,
                ':id' => (int)$existingId,
            ]);
            echo "refreshed : {$slug}\n";
            $updated++;
            $postId = (int)$existingId;
        }
    } else {
        $insert = $pdo->prepare('INSERT INTO posts (title, slug, excerpt, content, author_id, level, university_id, is_university_general, created_at)
                                 VALUES (:t, :s, :e, :c, :a, :l, :u, :g, :d)');
        $insert->execute([
            ':t' => $title,
            ':s' => $slug,
            ':e' => $excerpt,
            ':c' => $content,
            ':a' => $authorId,
            ':l' => $level,
            ':u' => null,
            ':g' => $isGeneral,
            ':d' => $publishedAt,
        ]);
        $postId = (int)$pdo->lastInsertId();
        echo "published : {$slug} (id {$postId})\n";
        $created++;
    }

    // The picture is also attached to the post, the same way the admin panel
    // attaches uploaded pictures, so it shows in the gallery too.
    $imgCheck = $pdo->prepare('SELECT id FROM post_images WHERE post_id = ? AND file_path = ? LIMIT 1');
    $imgCheck->execute([$postId, $image]);
    if (!$imgCheck->fetchColumn()) {
        $imgInsert = $pdo->prepare('INSERT INTO post_images (post_id, file_path, caption, sort_order) VALUES (?, ?, ?, 0)');
        $imgInsert->execute([$postId, $image, $title]);
    }
}

printf("\n%d published, %d refreshed, %d left alone\n", $created, $updated, $skipped);
echo "Open " . SITE_URL . "/blog.php to see them.\n";
