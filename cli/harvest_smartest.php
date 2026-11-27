<?php
/**
 * Harvests the SmarTest papers into the Study library.
 *
 * SmarTest keeps its free papers at
 *
 *     /past-questions/{subject}/{utme|waec|neco}-{year}/
 *
 * and renders every question, its options, and the correct answer into the
 * page. The worked explanation sits behind their paid plan, so this harvester
 * takes the question, the options and the answer, and leaves the explanation
 * empty for us to fill in later.
 *
 *     php cli/harvest_smartest.php               harvest everything
 *     php cli/harvest_smartest.php --limit=20    harvest 20 papers
 *     php cli/harvest_smartest.php --refresh     re-harvest papers we have
 *     php cli/harvest_smartest.php --dry-run     parse, print, store nothing
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/study_schema.php';
require_once __DIR__ . '/lib/study_harvest.php';
require_once __DIR__ . '/lib/parse_smartest.php';

$dryRun   = false;
$limit    = 0;
$parallel = 8;
$refresh  = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run')      { $dryRun = true; }
    elseif ($arg === '--refresh')  { $refresh = true; }
    elseif (strpos($arg, '--limit=') === 0)   { $limit = (int)substr($arg, 8); }
    elseif (strpos($arg, '--parallel=') === 0) { $parallel = (int)substr($arg, 11); }
}

msb_ensure_study_tables($pdo);

$examCodes = [
    'utme' => 'JAMB',
    'waec' => 'WAEC',
    'neco' => 'NECO',
];

// ---------------------------------------------------------------- the list
study_say('Reading the SmarTest sitemap...');
$sitemap = study_fetch('https://smartest.ng/sitemap.xml');
if (!$sitemap) {
    study_say('Could not read the sitemap. Stopping.');
    exit(1);
}

preg_match_all('#<loc>(https://smartest\.ng/past-questions/[^<]+)</loc>#', $sitemap, $m);
$todo = [];
foreach ($m[1] as $url) {
    if (!preg_match('#/past-questions/([a-z0-9-]+)/([a-z]+)-(\d{4})/?$#', $url, $p)) continue;
    if (!isset($examCodes[$p[2]])) continue;              // skips mocks and pages
    $subject = study_canonical_subject('smartest', $p[1]);
    if (!$subject) continue;
    $todo[] = [
        'url'        => $url,
        'exam'       => $examCodes[$p[2]],
        'year'       => (int)$p[3],
        'subject'    => $subject,
    ];
}
study_say(count($todo) . ' SmarTest papers listed.');

$existing = study_existing_paper_keys($pdo, 'smartest');
if ($refresh) {
    $existing = [];
    study_say('Refresh mode: every paper will be harvested again.');
}

$work = [];
foreach ($todo as $paper) {
    $key = study_paper_key($paper['exam'], $paper['subject']['slug'], $paper['year'], 'objective', 'smartest');
    if (isset($existing[$key])) continue;
    $work[] = $paper;
}
study_say(count($work) . ' papers still to harvest.');

if ($limit > 0 && count($work) > $limit) {
    $work = array_slice($work, 0, $limit);
    study_say('Limited to ' . count($work) . ' for this run.');
}
if (!$work) {
    study_say('Nothing to do - the library already holds every SmarTest paper.');
    exit(0);
}

// ---------------------------------------------------------------- the work
$papers = 0;
$questions = 0;
$batch = 20;
foreach (array_chunk($work, $batch) as $chunk) {
    $urls = array_column($chunk, 'url');
    $bodies = study_fetch_many($urls, ['parallel' => $parallel]);

    foreach ($chunk as $paper) {
        $body = $bodies[$paper['url']] ?? null;
        if ($body === null) {
            study_say('  x ' . $paper['exam'] . ' ' . $paper['subject']['slug'] . ' ' . $paper['year'] . ' - page not reachable');
            continue;
        }
        $list = smartest_questions($body);
        if (!$list) {
            study_say('  x ' . $paper['exam'] . ' ' . $paper['subject']['slug'] . ' ' . $paper['year'] . ' - nothing parsed');
            continue;
        }

        $title = $paper['exam'] . ' ' . $paper['subject']['name'] . ' ' . $paper['year'] . ' Past Questions and Answers';

        if ($dryRun) {
            study_say('  - ' . $title . ': ' . count($list) . ' questions (not stored, dry run)');
            continue;
        }

        $paperId = study_upsert_paper($pdo, [
            'exam'           => $paper['exam'],
            'subject_slug'   => $paper['subject']['slug'],
            'subject_name'   => $paper['subject']['name'],
            'year'           => $paper['year'],
            'paper_code'     => 'objective',
            'title'          => $title,
            'source'         => 'smartest',
            'source_url'     => $paper['url'],
            'question_count' => count($list),
        ]);
        $n = study_replace_questions($pdo, $paperId, $list);

        $papers++;
        $questions += $n;
        study_say('  + ' . $title . ': ' . $n . ' questions');
    }
}

study_say('');
study_say($dryRun
    ? 'Dry run: ' . $papers . ' papers would have been written.'
    : 'Done: ' . $papers . ' papers, ' . $questions . ' questions.');

/**
 * Pull the questions, options and answers out of one SmarTest page.
 *
 * @return array<int,array>
 */
