<?php
/**
 * Reads one page of a source and prints the questions as they would be
 * stored, so the reading of a page can be checked by eye.
 *
 *     php cli/preview_source.php acadmate https://www.acadmate.com.ng/past-questions/jamb/biology/2014
 *     php cli/preview_source.php smartest https://smartest.ng/past-questions/biology/utme-2024/
 *     php cli/preview_source.php skolra   https://skolra.ng/jamb-past-questions/biology/2015
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/study_harvest.php';
require_once __DIR__ . '/lib/parse_acadmate.php';
require_once __DIR__ . '/lib/parse_smartest.php';
require_once __DIR__ . '/lib/parse_skolra.php';

$source = $argv[1] ?? 'acadmate';
$url    = $argv[2] ?? '';

$body = study_fetch_retry($url);
if ($body === null) {
    echo "Could not read the page.\n";
    exit(1);
}

switch ($source) {
    case 'acadmate':  $list = acadmate_questions($body);  break;
    case 'smartest':  $list = smartest_questions($body);  break;
    case 'skolra':    $list = skolra_questions($body);    break;
    default:
        echo "Unknown source.\n";
        exit(1);
}

echo "Questions read: " . count($list) . "\n\n";
foreach ($list as $q) {
    echo ($q['display_number'] ?? '?') . ". " . $q['question'] . "\n";
    foreach (($q['options'] ?? []) as $o) {
        $mark = (($q['correct_option'] ?? '') === $o['label']) ? '  <- answer' : '';
        echo "   " . $o['label'] . ". " . $o['text'] . $mark . "\n";
    }
    if (!empty($q['explanation'])) {
        echo "   why: " . substr($q['explanation'], 0, 160) . "\n";
    }
    echo "\n";
}
