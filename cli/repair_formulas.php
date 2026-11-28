<?php
/**
 * Re-reads the papers whose questions still hold a formula that was not
 * written out properly.
 *
 * A paper can be missed by a harvest run - the site drops a request now and
 * then - and a paper that was missed keeps the old text, with the formula
 * still a mess. This finds those papers and reads them again.
 *
 *     php cli/repair_formulas.php            read them again and store them
 *     php cli/repair_formulas.php --dry-run  say which ones, change nothing
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/study_schema.php';
require_once __DIR__ . '/lib/study_harvest.php';
require_once __DIR__ . '/lib/parse_acadmate.php';
require_once __DIR__ . '/lib/parse_smartest.php';
require_once __DIR__ . '/lib/parse_skolra.php';
require_once __DIR__ . '/lib/parse_schoolngr.php';

$dryRun = in_array('--dry-run', array_slice($argv, 1), true);

$rows = $pdo->query("
    SELECT DISTINCT p.id, p.exam, p.subject_slug, p.subject_name, p.year,
           p.paper_code, p.source, p.source_url, p.title
    FROM study_questions q
    JOIN study_papers p ON p.id = q.paper_id
    WHERE q.question REGEXP '\\\\\\\\[a-zA-Z]+'
       OR q.options_json REGEXP '\\\\\\\\[a-zA-Z]+'
")->fetchAll();

study_say(count($rows) . ' papers still hold a formula that was not written out.');

foreach ($rows as $row) {
    $url = $row['source_url'];
    if (!$url) {
        study_say('  x #' . $row['id'] . ' - no source URL');
        continue;
    }

    $body = study_fetch_retry($url);
    if ($body === null) {
        study_say('  x #' . $row['id'] . ' ' . $row['exam'] . ' ' . $row['subject_slug'] . ' ' . $row['year'] . ' - page not reachable');
        continue;
    }

    switch ($row['source']) {
        case 'acadmate': $list = acadmate_questions($body); break;
        case 'smartest': $list = smartest_questions($body); break;
        case 'skolra':   $list = skolra_questions($body);   break;
        case 'schoolngr': $list = schoolngr_questions($body); break;
        default:
            study_say('  x #' . $row['id'] . ' - no reader for ' . $row['source']);
            continue 2;
    }

    if (!$list) {
        study_say('  x #' . $row['id'] . ' - nothing parsed');
        continue;
    }

    // A question may only be filed under the year the source gave it.
    $year = (int)$row['year'];
    $kept = [];
    foreach ($list as $q) {
        $qYear = (int)($q['year'] ?? 0);
        if ($qYear > 0 && $qYear !== $year) continue;
        $kept[] = $q;
    }
    if (count($kept) !== count($list)) {
        study_say('  . #' . $row['id'] . ' ' . $row['title'] . ': ' . (count($list) - count($kept)) . ' questions were of another year and were left out');
    }
    $list = $kept;

    if (!$list) {
        study_say('  x #' . $row['id'] . ' - no question of ' . $year . ' on this page');
        continue;
    }

    if ($dryRun) {
        study_say('  - #' . $row['id'] . ' ' . $row['title'] . ': ' . count($list) . ' questions (not stored, dry run)');
        continue;
    }

    $n = 0;
    foreach ($list as $q) {
        $q['display_number'] = ++$n;
    }

    $paperId = study_upsert_paper($pdo, [
        'exam'           => $row['exam'],
        'subject_slug'   => $row['subject_slug'],
        'subject_name'   => $row['subject_name'],
        'year'           => (int)$row['year'],
        'paper_code'     => $row['paper_code'],
        'title'          => $row['title'],
        'source'         => $row['source'],
        'source_url'     => $url,
        'question_count' => count($list),
    ]);
    $written = study_replace_questions($pdo, $paperId, $list);

    study_say('  + #' . $row['id'] . ' ' . $row['title'] . ': ' . $written . ' questions');
}

// The same check again, to say whether it worked.
$left = $pdo->query("
    SELECT COUNT(DISTINCT p.id) c
    FROM study_questions q
    JOIN study_papers p ON p.id = q.paper_id
    WHERE q.question REGEXP '\\\\\\\\[a-zA-Z]+'
       OR q.options_json REGEXP '\\\\\\\\[a-zA-Z]+'
")->fetchColumn();

study_say('');
study_say($dryRun
    ? 'Dry run: nothing was changed.'
    : 'Papers still holding a formula that was not written out: ' . $left);
