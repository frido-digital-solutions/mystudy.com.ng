<?php
/**
 * The blog, for the app.
 *
 * Same tables, same newest-first order, same search and level filters the
 * website's blog page runs. Two shapes come out of one file: a page of cards
 * when nothing is asked for, and one whole article - with the four related to it
 * - when a slug is.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_blog.php';
require_once __DIR__ . '/../../models/Post.php';

api_auth();

$postModel = new Post($pdo);

$rawLevel = api_field($_GET, 'level');
$level = in_array($rawLevel, ['Secondary', 'University'], true) ? $rawLevel : null;
$q = api_field($_GET, 'q');
$slug = api_field($_GET, 'slug');
$id = (int)($_GET['id'] ?? 0);

/**
 * One article. The content goes over as the clean HTML the app's reader draws,
 * with the pictures already carrying this site's address, so a phone never has
 * to guess where an image lives.
 */
if ($slug !== '' || $id > 0) {
    $post = $slug !== '' ? $postModel->getBySlug($slug) : $postModel->getById($id);
    if (!$post) {
        api_err('not_found', 'That article is no longer on the site.', 404);
    }

    $authors = api_blog_authors($pdo, array_merge([$post], $postModel->getRelatedPosts($post, 4)));
    $card = api_blog_card_payload($pdo, $post, $authors[(int)($post['author_id'] ?? 0)] ?? null);
    $card['content'] = api_blog_clean_html($post['content'] ?? '');

    $related = array_map(function ($r) use ($pdo, $authors) {
        $out = api_blog_card_payload($pdo, $r, $authors[(int)($r['author_id'] ?? 0)] ?? null);
        unset($out['excerpt']);
        return $out;
    }, $postModel->getRelatedPosts($post, 4));

    api_out(['ok' => true, 'post' => $card, 'related' => $related]);
}

$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

if ($q !== '') {
    $rows = $postModel->searchPosts($q, $level, null, $limit, $offset);
    $total = $postModel->countSearchPosts($q, $level, null);
} else {
    $rows = $postModel->listFiltered($level, null, $limit, $offset);
    $total = $postModel->countFilteredPosts($level, null);
}

$authors = api_blog_authors($pdo, $rows);

api_out([
    'ok' => true,
    'items' => array_map(function ($p) use ($pdo, $authors) {
        return api_blog_card_payload($pdo, $p, $authors[(int)($p['author_id'] ?? 0)] ?? null);
    }, $rows),
    'total' => $total,
    'page' => $page,
    'pages' => (int)ceil($total / $limit) ?: 1,
    'levels' => ['Secondary', 'University'],
]);
