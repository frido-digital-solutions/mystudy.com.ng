<?php
/**
 * Harvests the SchoolNGR papers into the Study library.
 *
 * SchoolNGR keeps its question bank at
 *
 *     /classroom/{exam}/{subject}
 *
 * with the questions listed five to a page. Every question on the page names
 * the exam and the year it came from - "NECO 2000", "WAEC 1989" - and the
 * page marks the correct option on the option itself, so a question, its
 * options and its answer all come from the one request.
 *
 * The listing is ordered by year, so one pass over the pages of a subject
 * gives every question of that subject with the year each one belongs to.
 * The questions are then grouped into papers by year.
 *
 *     php cli/harvest_schoolngr.php                    harvest everything
 *     php cli/harvest_schoolngr.php --exam=neco        harvest one exam
 *     php cli/harvest_schoolngr.php --limit=5          harvest 5 subjects
 *     php cli/harvest_schoolngr.php --refresh          re-harvest what we have
 *     php cli/harvest_schoolngr.php --dry-run          parse, print, store nothing
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/study_schema.php';
require_once __DIR__ . '/lib/study_harvest.php';

$dryRun   = false;
$limit    = 0;
$parallel = 6;
$refresh  = false;
$examOnly = '';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run')      { $dryRun = true; }
    elseif ($arg === '--refresh')  { $refresh = true; }
    elseif (strpos($arg, '--limit=') === 0)      { $limit = (int)substr($arg, 8); }
    elseif (strpos($arg, '--parallel=') === 0)   { $parallel = (int)substr($arg, 11); }
    elseif (strpos($arg, '--exam=') === 0)       { $examOnly = strtolower(substr($arg, 7)); }
}

msb_ensure_study_tables($pdo);

$examCodes = [
    'neco' => 'NECO',
    'waec' => 'WAEC',
    'jamb' => 'JAMB',
];
if ($examOnly !== '' && !isset($examCodes[$examOnly])) {
    study_say('Unknown exam. Use one of: ' . implode(', ', array_keys($examCodes)));
    exit(1);
}

// ------------------------------------------------------------- the subjects
$subjects = [];
foreach (array_keys($examCodes) as $code) {
    if ($examOnly !== '' && $code !== $examOnly) continue;

    $listing = study_fetch_retry("https://www.schoolngr.com/classroom/$code");
    if (!$listing) {
        study_say("Could not read the $code subject list. Skipping.");
        continue;
    }

    preg_match_all("#href='/classroom/$code/([a-z0-9-]+)'#", $listing, $m);
    $found = 0;
    foreach (array_unique($m[1]) as $slug) {
        $canonical = study_canonical_subject('schoolngr', $slug);
        if (!$canonical) continue;
        $subjects[] = [
            'code'    => $code,
            'exam'    => $examCodes[$code],
            'slug'    => $slug,
            'subject' => $canonical,
        ];
        $found++;
    }
    study_say("$code: $found subjects with questions we can use.");
}

if ($limit > 0 && count($subjects) > $limit) {
    $subjects = array_slice($subjects, 0, $limit);
    study_say('Limited to ' . count($subjects) . ' subjects for this run.');
}
if (!$subjects) {
    study_say('Nothing to harvest.');
    exit(0);
}

// ---------------------------------------------------------------- the work
$existing = study_existing_paper_keys($pdo, 'schoolngr');
if ($refresh) {
    $existing = [];
    study_say('Refresh mode: every paper will be harvested again.');
}

$papers   = 0;
$questions = 0;

foreach ($subjects as $subject) {
    $code = $subject['code'];
    $slug = $subject['slug'];

    $last = schoolngr_last_page($code, $slug);
    if ($last < 1) {
        study_say("  x $code $slug - no questions");
        continue;
    }
    study_say("  . $code $slug - $last pages");

    // Fetch the pages of the subject, a window of them at a time.
    $collected = [];
    for ($from = 1; $from <= $last; $from += 10) {
        $urls = [];
        for ($i = 0; $i < 10 && $from + $i <= $last; $i++) {
            $urls[] = "https://www.schoolngr.com/classroom/$code/$slug?page=" . ($from + $i);
        }
        $bodies = study_fetch_many($urls, ['parallel' => $parallel]);

        foreach ($urls as $url) {
            $body = $bodies[$url] ?? null;
            if ($body === null) continue;
            foreach (schoolngr_questions($body) as $q) {
                if ($q['year'] <= 0) continue;      // no year on the question: skip it
                $collected[] = $q;
            }
        }
        // A short pause between windows: the site is a shared host and will
        // start refusing requests if it is hit too hard.
        usleep(400000);
    }

    if (!$collected) {
        study_say("  x $code $slug - no questions with a year");
        continue;
    }

    // Group the questions into papers by the year they name.
    $byYear = [];
    foreach ($collected as $q) {
        $byYear[$q['year']][] = $q;
    }
    ksort($byYear);

    foreach ($byYear as $year => $list) {
        $key = study_paper_key($subject['exam'], $subject['subject']['slug'], (int)$year, 'objective', 'schoolngr');
        if (!$refresh && isset($existing[$key])) continue;

        $title = $subject['exam'] . ' ' . $subject['subject']['name'] . ' ' . $year . ' Past Questions and Answers';

        if ($dryRun) {
            study_say("  - $title: " . count($list) . " questions (not stored, dry run)");
            continue;
        }

        // Number the questions afresh inside the paper.
        $numbered = [];
        $n = 0;
        foreach ($list as $q) {
            $n++;
            $q['display_number'] = $n;
            $numbered[] = $q;
        }

        $paperId = study_upsert_paper($pdo, [
            'exam'           => $subject['exam'],
            'subject_slug'   => $subject['subject']['slug'],
            'subject_name'   => $subject['subject']['name'],
            'year'           => (int)$year,
            'paper_code'     => 'objective',
            'title'          => $title,
            'source'         => 'schoolngr',
            'source_url'     => "https://www.schoolngr.com/classroom/$code/$slug?examyear=$year",
            'question_count' => count($numbered),
        ]);
        $written = study_replace_questions($pdo, $paperId, $numbered);

        $papers++;
        $questions += $written;
        study_say("  + $title: $written questions");
    }
}

study_say('');
study_say($dryRun
    ? 'Dry run: ' . $papers . ' papers would have been written.'
    : 'Done: ' . $papers . ' papers, ' . $questions . ' questions.');

/**
 * The last page of a subject's listing that still holds questions.
 *
 * The pages are found by halving the way down, which takes about nine
 * requests instead of one request per page.
 */
function schoolngr_last_page(string $code, string $slug): int
{
    $has = function (int $page) use ($code, $slug): bool {
        $body = study_fetch_retry("https://www.schoolngr.com/classroom/$code/$slug?page=$page");
        if ($body === null) return false;
        return count(schoolngr_questions($body)) > 0;
    };

    if (!$has(1)) return 0;

    $low  = 1;
    $high = 400;
    while ($low < $high) {
        $mid = (int)ceil(($low + $high + 1) / 2);
        if ($has($mid)) {
            $low = $mid;
        } else {
            $high = $mid - 1;
        }
        usleep(250000);
    }
    return $low;
}

/**
 * Pull the questions out of one SchoolNGR listing page.
 *
 * Each question sits in a block like this:
 *
 *   <div class="question-block">
 *     <div class="question-year"><a href="...examyear=2000">NECO 2000</a></div>
 *     <div class="question-text">An instrument of measuring wind speed ...</div>
 *     <div class="options quiz-options"><ul>
 *       <li data-option="A" data-correct="true"><span class="option-label">A</span> ...</li>
 *       ...
 *     </ul></div>
 *     <a href="/classroom/biology/87483" class="view-answer-btn">View Answer & Explanation</a>
 *   </div>
 *
 * So the year and the correct option are both on the page: the year in the
 * link text, the answer in the "data-correct" mark on the option itself.
 *
 * @return array<int,array>
 */
function schoolngr_questions(string $html): array
{
    $doc = study_dom($html);
    if (!$doc) return [];

    $xp = new DOMXPath($doc);
    $out = [];

    foreach ($xp->query("//div[contains(@class,'question-block')]") as $block) {
        $yearNode = $xp->query(".//div[contains(@class,'question-year')]//a", $block)->item(0);
        $year = 0;
        if ($yearNode) {
            if (preg_match('/(\d{4})/', study_node_text($yearNode), $y)) {
                $year = (int)$y[1];
            }
        }

        $textNode = $xp->query(".//div[contains(@class,'question-text')]", $block)->item(0);
        $question = $textNode ? study_node_text($textNode) : '';
        if ($question === '') continue;

        $options = [];
        $answer  = '';
        foreach ($xp->query(".//div[contains(@class,'options')]//li", $block) as $li) {
            $label = strtoupper(trim($li->getAttribute('data-option')));
            if ($label === '') continue;
            $text = study_node_text($li);
            // The label is printed inside the option, so drop that copy.
            $text = preg_replace('/^\s*' . preg_quote($label, '/') . '\s*/u', '', $text);
            $options[] = ['label' => $label, 'text' => trim((string)$text)];
            if ($li->getAttribute('data-correct') === 'true') {
                $answer = $label;
            }
        }
        if (!$options || $answer === '') continue;

        // The link to the question's own page is its identifier there.
        $idNode = $xp->query(".//a[contains(@class,'view-answer-btn')]", $block)->item(0);
        $sourceId = null;
        if ($idNode) {
            if (preg_match('#/classroom/[a-z0-9-]+/(\d+)#', $idNode->getAttribute('href'), $m)) {
                $sourceId = $m[1];
            }
        }

        $out[] = [
            'display_number'   => 0,
            'question'         => $question,
            'options'          => $options,
            'correct_option'   => $answer,
            'explanation'      => null,
            'topic'            => null,
            'source_question_id' => $sourceId,
            'year'             => $year,
        ];
    }

    return $out;
}
