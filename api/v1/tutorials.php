<?php
/**
 * Video tutorials for the app. The same model, the same search and the same
 * "newest first" order as tutorials.php on the website, re-shaped for a phone:
 * every row carries a picture that is certain to exist and a link that is
 * certain to open, because the app shows a card rather than a list of titles.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_tutorial.php';
require_once __DIR__ . '/../../models/Tutorial.php';

api_auth();

$tutorialModel = new Tutorial($pdo);

$q = api_field($_GET, 'q');
$rawLevel = api_field($_GET, 'level');
$level = in_array($rawLevel, ['Secondary', 'University'], true) ? $rawLevel : null;
$course = api_field($_GET, 'course');
$limit = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$rows = $tutorialModel->searchTutorials($q !== '' ? $q : null, $level, $course !== '' ? $course : null, $limit, $offset);
$total = $tutorialModel->countSearchTutorials($q !== '' ? $q : null, $level, $course !== '' ? $course : null);

api_out([
    'ok' => true,
    'items' => array_map(function ($t) use ($tutorialModel) {
        return api_tutorial_payload($t, $tutorialModel->getCoursesForTutorial((int)$t['id']));
    }, $rows),
    'total' => $total,
    'page' => $page,
    'pages' => (int)ceil($total / $limit) ?: 1,
    'levels' => ['Secondary', 'University'],
    'courses' => array_map(function ($c) {
        return ['id' => (int)$c['id'], 'name' => (string)$c['name'], 'slug' => (string)($c['slug'] ?? '')];
    }, $tutorialModel->listCourses()),
]);
