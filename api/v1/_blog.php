<?php
/**
 * Blog payload shared by api/v1/blogs.php and the home feed.
 *
 * The website pulls a card picture out of whatever the writer left in the post,
 * so the app does the same rather than inventing one: the image uploaded for the
 * post first, then the first picture inside the article, then nothing at all. No
 * stock photo stands in for a real one - an article about WAEC maths does not get
 * a random picture of a library, it gets the app's own designed tile.
 *
 * Content is stored as HTML the site team wrote. It is handed over as HTML
 * because the app renders it into native text, so the tags that carry no meaning
 * there - scripts, styles, inline colours, click handlers - are stripped here
 * rather than being trusted to the reader.
 */

/** The picture for a post row, or null when the post genuinely has none. */
function api_blog_image(PDO $pdo, array $post): ?string {
    try {
        $stmt = $pdo->prepare('SELECT file_path FROM post_images WHERE post_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1');
        $stmt->execute([(int)$post['id']]);
        $path = $stmt->fetchColumn();
        if ($path) {
            return api_url((string)$path);
        }
    } catch (Throwable $e) {
        error_log('api blog image: ' . $e->getMessage());
    }

    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', (string)($post['content'] ?? ''), $m)) {
        $src = trim($m[1]);
        // A picture already pasted in from the site comes with the site's own
        // address on it; anything relative belongs to this same root.
        return api_url($src);
    }

    return null;
}

/** Minutes a grown person needs to read it - the website's own 200 words rule. */
function api_blog_minutes(array $post): int {
    $words = str_word_count(strip_tags((string)($post['content'] ?? '')));
    return $words > 0 ? max(1, (int)ceil($words / 200)) : 1;
}

/**
 * One blog card. Kept small on purpose: a list of thirty articles must not
 * carry thirty copies of the full text just to draw a title and a picture.
 */
function api_blog_card_payload(PDO $pdo, array $post, ?array $author = null): array {
    $excerpt = trim(preg_replace('/\s+/', ' ', strip_tags((string)($post['excerpt'] ?? ''))));
    if ($excerpt === '') {
        $excerpt = trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags((string)($post['content'] ?? ''))), 0, 160));
    }

    return [
        'id' => (int)$post['id'],
        'slug' => (string)($post['slug'] ?? ''),
        'title' => (string)$post['title'],
        'excerpt' => $excerpt,
        'level' => $post['level'] ?? null,
        'image' => api_blog_image($pdo, $post),
        'date' => $post['created_at'] ?? null,
        'minutes' => api_blog_minutes($post),
        'author' => $author ? [
            'name' => (string)($author['name'] ?? 'MyStudy Base'),
            'picture' => api_url($author['profile_picture'] ?? null),
        ] : null,
    ];
}

/**
 * Tags the app's reader understands, everything else it does not.
 *
 * The reader draws headings, paragraphs, lists, quotes, pictures, links and bold
 * and italic text. Anything outside that list is taken apart for what it is
 * worth: a <div> keeps its text, a <script> keeps nothing.
 */
function api_blog_clean_html(?string $html): string {
    $html = (string)$html;

    $html = preg_replace('/<(script|style|iframe|video|audio|form|input|button|svg|noscript|object|embed)\b[^>]*>.*?<\/\1>/is', '', $html);
    // The same tags when they were never closed, so no leftover text of a
    // script survives as a paragraph.
    $html = preg_replace('/<\/?(script|style|iframe|video|audio|form|input|button|svg|noscript|object|embed)\b[^>]*>/i', '', $html);
    // The survivors are tags the reader cannot draw, so their names go but
    // their text stays: a <div> that only wrapped a paragraph still reads.
    $html = strip_tags($html, '<h1><h2><h3><h4><p><br><ul><ol><li><blockquote><strong><b><em><i><u><s><a><img><pre><code>');

    // Only the attributes that change how a line looks or where it goes.
    $html = preg_replace_callback('/<a\b([^>]*)>/i', static function ($m) {
        $href = preg_match('/href=["\']([^"\']+)["\']/i', $m[1], $h) ? trim($h[1]) : '';
        if ($href === '' || !preg_match('/^(https?:)?\/\//i', $href)) {
            return '<span>';
        }
        return '<a href="' . str_replace('"', '&quot;', $href) . '">';
    }, $html);
    $html = preg_replace_callback('/<img\b([^>]*)>/i', static function ($m) {
        $src = preg_match('/src=["\']([^"\']+)["\']/i', $m[1], $s) ? trim($s[1]) : '';
        if ($src === '') {
            return '';
        }
        $alt = preg_match('/alt=["\']([^"\']*)["\']/i', $m[1], $a) ? trim($a[1]) : '';
        return '<img src="' . str_replace('"', '&quot;', $src) . '" alt="' . str_replace('"', '&quot;', $alt) . '">';
    }, $html);

    // Pictures inside the article are served from this site, so the phone gets
    // the whole address rather than one it has to guess the root of.
    $html = preg_replace_callback('/<img src="([^"]+)"/i', static function ($m) {
        return '<img src="' . str_replace('"', '&quot;', (string)api_url($m[1])) . '"';
    }, $html);

    return trim($html);
}

/** Fetch the author rows a set of posts needs, keyed by user id. */
function api_blog_authors(PDO $pdo, array $posts): array {
    $ids = [];
    foreach ($posts as $p) {
        $id = (int)($p['author_id'] ?? 0);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    if (!$ids) {
        return [];
    }
    try {
        $in = implode(',', array_map('intval', array_values($ids)));
        $rows = $pdo->query("SELECT id, name, profile_picture FROM users WHERE id IN ($in)")->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['id']] = $r;
        }
        return $out;
    } catch (Throwable $e) {
        error_log('api blog authors: ' . $e->getMessage());
        return [];
    }
}
