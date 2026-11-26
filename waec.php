<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/exam_landing.php';

$exams = seo_exam_pages();
$exam = $exams['waec'];

$page_title = $exam['title'];
$page_description = $exam['desc'];

require_once __DIR__ . '/includes/header.php';

// The three things a search engine reads on this page.
seo_breadcrumb_jsonld([
    'Home' => SITE_URL . '/',
    'Quizzes' => SITE_URL . '/quizzes.php',
    $exam['name'] => SITE_URL . '/' . $exam['file'],
]);
seo_faq_jsonld($exam['key'], $exam['faq']);
seo_jsonld([
    '@context' => 'https://schema.org',
    '@type'    => 'CollectionPage',
    'name'     => $exam['title'],
    'description' => $exam['desc'],
    'url'      => SITE_URL . '/' . $exam['file'],
    'inLanguage' => 'en',
    'isPartOf' => [
        '@type' => 'WebSite',
        'name'  => SITE_NAME,
        'url'   => SITE_URL,
    ],
]);

seo_render_exam_page($exam);

require_once __DIR__ . '/includes/footer.php';
