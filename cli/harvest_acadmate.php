<?php
/**
 * Harvests the Acadmate JAMB papers into the Study library.
 *
 * Acadmate keeps its papers at
 *
 *     /past-questions/jamb/{subject}/{year}
 *     /past-questions/jamb/{subject}/{year}/2      (page 2 and so on)
 *
 * and renders every question, its options and its correct answer into the
 * page. The pages of one paper are gathered together and stored as one
 * paper, because a paper is one thing a student sits.
 *
 *     php cli/harvest_acadmate.php              harvest everything
 *     php cli/harvest_acadmate.php --limit=20   harvest 20 papers
 *     php cli/harvest_acadmate.php --dry-run    parse, print, store nothing
 *
 * The run can be stopped and started again: papers already in the library
 * are skipped.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/study_schema.php';
require_once __DIR__ . '/lib/study_harvest.php';

$opts      = [];
$dryRun    = false;
$limit     = 0;
$parallel  = 8;
$refresh   = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run')      { $dryRun = true; }
    elseif ($arg === '--refresh')  { $refresh = true; }
    elseif (strpos($arg, '--limit=') === 0)   { $limit = (int)substr($arg, 8); }
    elseif (strpos($arg, '--parallel=') === 0) { $parallel = (int)substr($arg, 11); }
}

msb_ensure_study_tables($pdo);

// ---------------------------------------------------------------- the list
study_say('Reading the Acadmate sitemap...');
$sitemap = study_fetch('https://www.acadmate.com.ng/sitemap.xml');
if (!$sitemap) {
    study_say('Could not read the sitemap. Stopping.');
    exit(1);
}

preg_match_all('#<loc>(https://acadmate\.com\.ng/past-questions/jamb/[^<]+)</loc>#', $sitemap, $m);
$pages = [];
foreach ($m[1] as $url) {
    if (!preg_match('#/past-questions/jamb/([a-z0-9-]+)/(\d{4})(?:/(\d+))?$#', $url, $p)) continue;
    $key = $p[1] . '|' . $p[2];
    $pages[$key][] = ['url' => $url, 'page' => (int)($p[3] ?? 1)];
}
study_say(count($pages) . ' JAMB papers listed.');

if (!$pages) {
    study_say('No papers found in the sitemap.');
    exit(1);
}

// ---------------------------------------------------------------- the papers
$existing = study_existing_paper_keys($pdo, 'acadmate');
if ($refresh) {
    $existing = [];
    study_say('Refresh mode: every paper will be harvested again.');
}
$todo     = [];
foreach ($pages as $key => $pageList) {
    [$sourceSlug, $year] = explode('|', $key);
    $subject = study_canonical_subject('acadmate', $sourceSlug);
    if (!$subject) continue;
    $paperKey = study_paper_key('JAMB', $subject['slug'], $year, 'objective', 'acadmate');
    if (isset($existing[$paperKey])) continue;
    $todo[] = [
        'source_slug' => $sourceSlug,
        'year'        => (int)$year,
        'subject'     => $subject,
        'pages'       => $pageList,
    ];
}

study_say(count($todo) . ' papers still to harvest.');
if ($limit > 0 && count($todo) > $limit) {
    $todo = array_slice($todo, 0, $limit);
    study_say('Limited to ' . count($todo) . ' for this run.');
}
if (!$todo) {
    study_say('Nothing to do - the library already holds every Acadmate paper.');
    exit(0);
}

// ---------------------------------------------------------------- the work
$papers = 0;
$questions = 0;
foreach ($todo as $paper) {
    $urls = [];
    foreach ($paper['pages'] as $p) {
        $urls[] = $p['url'];
    }
    $bodies = study_fetch_many($urls, ['parallel' => $parallel]);

    $collected = [];
    $firstBody = null;
    foreach ($paper['pages'] as $p) {
        $body = $bodies[$p['url']] ?? null;
        if ($body === null) continue;
        if ($firstBody === null) $firstBody = $body;
        foreach (acadmate_questions($body) as $q) {
            $collected[$q['display_number']] = $q;
        }
    }
    if (!$collected) {
        study_say('  x ' . $paper['source_slug'] . ' ' . $paper['year'] . ' - nothing parsed');
        continue;
    }
    ksort($collected);
    $list = array_values($collected);

    $title = 'JAMB ' . $paper['subject']['name'] . ' ' . $paper['year'] . ' Past Questions and Answers';

    if ($dryRun) {
        study_say('  - ' . $title . ': ' . count($list) . ' questions (not stored, dry run)');
        continue;
    }

    $paperId = study_upsert_paper($pdo, [
        'exam'           => 'JAMB',
        'subject_slug'   => $paper['subject']['slug'],
        'subject_name'   => $paper['subject']['name'],
        'year'           => $paper['year'],
        'paper_code'     => 'objective',
        'title'          => $title,
        'source'         => 'acadmate',
        'source_url'     => $paper['pages'][0]['url'],
        'question_count' => count($list),
    ]);
    $n = study_replace_questions($pdo, $paperId, $list);

    $papers++;
    $questions += $n;
    study_say('  + ' . $title . ': ' . $n . ' questions');
}

study_say('');
study_say($dryRun
    ? 'Dry run: ' . $papers . ' papers would have been written.'
    : 'Done: ' . $papers . ' papers, ' . $questions . ' questions.');

/**
 * Pull the questions, options and answers out of one Acadmate page.
 *
 * @return array<int,array>
 */
function acadmate_questions(string $html): array
{
    $doc = study_dom($html);
    if (!$doc) return [];

    $xp = new DOMXPath($doc);
    $out = [];

    foreach ($xp->query("//ol[@aria-label='Options']") as $ol) {
        $number = 0;
        $questionText = '';

        // The question sits directly above its list of options.
        $prev = $ol->previousSibling;
        while ($prev !== null) {
            if ($prev->nodeType === XML_ELEMENT_NODE) {
                $text = study_node_text($prev);
                if (preg_match('/^\s*(\d{1,3})\.\s*(.*)$/s', $text, $m)) {
                    $number = (int)$m[1];
                    $questionText = trim($m[2]);
                }
                break;
            }
            $prev = $prev->previousSibling;
        }
        if ($number === 0 || $questionText === '') continue;

        $options = [];
        $answer  = '';
        foreach ($xp->query('./li', $ol) as $li) {
            $spans = $xp->query('./span', $li);
            if ($spans->length < 2) continue;
            $label = rtrim(study_node_text($spans->item(0)), '.');
            $text  = study_node_text($spans->item(1));
            if ($label === '') continue;

            $options[] = ['label' => strtoupper($label), 'text' => $text];

            foreach ($xp->query('.//span', $li) as $span) {
                if (study_node_text($span) === 'Correct') {
                    $answer = strtoupper($label);
                    break;
                }
            }
        }
        if (!$options) continue;

        // The topic badge sits with the question, in a span of its own.
        $topic = null;
        $container = $ol->parentNode;
        if ($container) {
            foreach ($xp->query(".//span[contains(@class,'bg-brand-100')]", $container) as $span) {
                $t = study_node_text($span);
                if ($t !== '') { $topic = $t; break; }
            }
        }

        $out[$number] = [
            'display_number'   => $number,
            'question'         => $questionText,
            'options'          => $options,
            'correct_option'   => $answer,
            'explanation'      => null,
            'topic'            => $topic,
        ];
    }

    return $out;
}
