<?php
/**
 * Harvests the Skolra papers into the Study library.
 *
 * Skolra keeps its papers at
 *
 *     /waec-past-questions/{subject}/{year}
 *     /jamb-past-questions/{subject}/{year}
 *
 * and renders the question, its options, the correct answer and a worked
 * explanation into the page. Their robots.txt invites crawlers, so this is
 * the one source that gives us explanations as well as answers.
 *
 *     php cli/harvest_skolra.php               harvest everything
 *     php cli/harvest_skolra.php --limit=20    harvest 20 papers
 *     php cli/harvest_skolra.php --refresh     re-harvest papers we have
 *     php cli/harvest_skolra.php --dry-run     parse, print, store nothing
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/study_schema.php';
require_once __DIR__ . '/lib/study_harvest.php';
require_once __DIR__ . '/lib/parse_skolra.php';

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
    'waec' => 'WAEC',
    'jamb' => 'JAMB',
    'neco' => 'NECO',
];

// ---------------------------------------------------------------- the list
study_say('Reading the Skolra sitemap...');
$sitemap = study_fetch('https://skolra.ng/sitemap-past-questions.xml');
if (!$sitemap) {
    study_say('Could not read the sitemap. Stopping.');
    exit(1);
}

preg_match_all('#<loc>(https://skolra\.ng/[^<]+)</loc>#', $sitemap, $m);
$todo = [];
foreach ($m[1] as $url) {
    // A paper with its year in the path: /waec-past-questions/physics/2020
    if (preg_match('#/(waec|jamb|neco)-past-questions/([a-z0-9-]+)/(\d{4})/?$#', $url, $p)) {
        $subject = study_canonical_subject('skolra', $p[2]);
        if (!$subject) continue;
        $todo[] = [
            'url'     => $url,
            'exam'    => $examCodes[$p[1]],
            'year'    => (int)$p[3],
            'subject' => $subject,
        ];
        continue;
    }
    // A subject hub with no year in the path: /neco-past-questions/mathematics.
    // The year is in the page title, so it is read from there.
    if (preg_match('#/(waec|jamb|neco)-past-questions/([a-z0-9-]+)/?$#', $url, $p)) {
        $subject = study_canonical_subject('skolra', $p[2]);
        if (!$subject) continue;
        $todo[] = [
            'url'      => $url,
            'exam'     => $examCodes[$p[1]],
            'year'     => 0,
            'subject'  => $subject,
            'needs_year' => true,
        ];
    }
}
study_say(count($todo) . ' Skolra papers listed.');

$existing = study_existing_paper_keys($pdo, 'skolra');
if ($refresh) {
    $existing = [];
    study_say('Refresh mode: every paper will be harvested again.');
}

$work = [];
foreach ($todo as $paper) {
    $key = study_paper_key($paper['exam'], $paper['subject']['slug'], $paper['year'], 'objective', 'skolra');
    if (isset($existing[$key])) continue;
    $work[] = $paper;
}
study_say(count($work) . ' papers still to harvest.');

if ($limit > 0 && count($work) > $limit) {
    $work = array_slice($work, 0, $limit);
    study_say('Limited to ' . count($work) . ' for this run.');
}
if (!$work) {
    study_say('Nothing to do - the library already holds every Skolra paper.');
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

        // A subject hub carries its year in the title: "... (2008)".
        if (empty($paper['year'])) {
            $year = skolra_year_from_title($body);
            if (!$year) {
                study_say('  x ' . $paper['exam'] . ' ' . $paper['subject']['slug'] . ' - no year on the page, skipped');
                continue;
            }
            $paper['year'] = $year;
        }

        $list = skolra_questions($body);
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
            'source'         => 'skolra',
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
 * The year a subject hub belongs to, taken from its title.
 *
 * A page with no year in its address still names the year it holds:
 * "NECO Mathematics Past Questions and Answers (2008)".
 */
function skolra_year_from_title(string $html): ?int
{
    if (preg_match('#<title>(.*?)</title>#is', $html, $m)) {
        $title = html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8');
        if (preg_match('/\((\d{4})\)/', $title, $y)) {
            return (int)$y[1];
        }
    }
    return null;
}

/**
 * Pull the questions, options, answers and explanations out of one page.
 *
 * @return array<int,array>
 */
