<?php
/**
 * One subject of one examination.
 *
 * "WAEC Mathematics past questions and answers" is its own search, and its own
 * page. Every subject of every examination has one, and each one lists the
 * papers for that subject with their answers - so a student who searches for
 * one subject finds exactly that subject, not the front page of the site.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/exam_landing.php';

$exams = seo_exam_pages();

$examKey = strtoupper(trim((string)($_GET['exam'] ?? '')));
$subject = trim((string)($_GET['subject'] ?? ''));

$exam = null;
foreach ($exams as $one) {
    if (strtoupper($one['key']) === $examKey) {
        $exam = $one;
        break;
    }
}

if (!$exam || $subject === '') {
    header('Location: ' . SITE_URL . '/quizzes.php');
    exit;
}

$page_title = sprintf(
    'Free %s %s Past Questions and Answers | %s CBT Practice',
    $examKey,
    $subject,
    $examKey
);
$page_description = sprintf(
    'Download free %s %s past questions with answers and take a timed %s CBT practice test. %s past questions, solved papers and answer keys for %s students.',
    $examKey,
    $subject,
    $examKey,
    $examKey,
    $subject
);

require_once __DIR__ . '/includes/header.php';

seo_breadcrumb_jsonld([
    'Home' => SITE_URL . '/',
    'Quizzes' => SITE_URL . '/quizzes.php',
    $exam['name'] => SITE_URL . '/' . $exam['file'],
    $subject => SITE_URL . '/exam-subject.php?exam=' . urlencode($examKey) . '&subject=' . urlencode($subject),
]);
seo_jsonld([
    '@context' => 'https://schema.org',
    '@type'    => 'CollectionPage',
    'name'     => $page_title,
    'description' => $page_description,
    'url'      => SITE_URL . '/exam-subject.php?exam=' . urlencode($examKey) . '&subject=' . urlencode($subject),
    'inLanguage' => 'en',
]);

seo_render_exam_page($exam, $subject);

require_once __DIR__ . '/includes/footer.php';
