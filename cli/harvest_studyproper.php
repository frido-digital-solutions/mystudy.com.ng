<?php
/**
 * Harvests the StudyProper papers into the Study library.
 *
 * StudyProper serves its questions through WordPress AJAX, which their
 * robots.txt allows (/wp-admin/admin-ajax.php). Each question comes back
 * with its options, the correct answer and a worked explanation.
 *
 *     php cli/harvest_studyproper.php              harvest everything
 *     php cli/harvest_studyproper.php --limit=20   harvest 20 groups
 *     php cli/harvest_studyproper.php --refresh    re-harvest groups we have
 *     php cli/harvest_studyproper.php --dry-run    parse, print, store nothing
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/study_schema.php';
require_once __DIR__ . '/lib/study_harvest.php';

$dryRun  = false;
$limit   = 0;
$refresh = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run')      { $dryRun = true; }
    elseif ($arg === '--refresh')  { $refresh = true; }
    elseif (strpos($arg, '--limit=') === 0) { $limit = (int)substr($arg, 8); }
}

msb_ensure_study_tables($pdo);

const AJAX_URL = 'https://studyproper.com/wp-admin/admin-ajax.php';

// GCE is the WAEC paper for private candidates. It belongs with WAEC, but it
// is a different paper, so it keeps its own paper code.
$examMap = [
    'waec' => ['exam' => 'WAEC', 'paper_code' => 'objective', 'label' => 'WAEC'],
    'gce'  => ['exam' => 'WAEC', 'paper_code' => 'gce',       'label' => 'WAEC GCE'],
    'jamb' => ['exam' => 'JAMB', 'paper_code' => 'objective', 'label' => 'JAMB'],
    'neco' => ['exam' => 'NECO', 'paper_code' => 'objective', 'label' => 'NECO'],
];

$years = range(2005, 2026);

/**
 * The nonce the site hands to its own scripts.
 */
function studyproper_nonce(): ?string
{
    $ch = curl_init('https://studyproper.com/waec-past-questions/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                              . '(KHTML, like Gecko) Chrome/126.0 Safari/537.36 MyStudyBaseStudyHarvester/1.0',
        CURLOPT_HTTPHEADER     => ['Cookie: hc_js_gate=1'],
    ]);
    $ca = study_ca_bundle();
    if ($ca) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    $html = curl_exec($ch);
    curl_close($ch);
    if (!$html) return null;

    if (preg_match('/var sn_data\s*=\s*(\{.*?\});/s', (string)$html, $m)) {
        $json = json_decode($m[1], true);
        if (!empty($json['nonce'])) return (string)$json['nonce'];
    }
    return null;
}

/**
 * Ask the site for one group of questions.
 */
function studyproper_questions(string $nonce, string $exam, string $subject, string $year): array
{
    $collected = [];
    $total = null;

    // The site answers at most 100 questions a call, so the rest is picked
    // up a page at a time until everything it holds has been read.
    for ($page = 1; $page <= 20; $page++) {
        $ch = curl_init(AJAX_URL);
        $caBundle = study_ca_bundle();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                                  . '(KHTML, like Gecko) Chrome/126.0 Safari/537.36 MyStudyBaseStudyHarvester/1.0',
            CURLOPT_HTTPHEADER     => [
                'X-Requested-With: XMLHttpRequest',
                'Referer: https://studyproper.com/waec-past-questions/',
                'Cookie: hc_js_gate=1',
            ],
            CURLOPT_POSTFIELDS     => http_build_query([
                'action'      => 'sn_get_questions',
                'nonce'       => $nonce,
                'exam'        => $exam,
                'subject'     => $subject,
                'year'        => $year,
                'institution' => '',
                'limit'       => 100,
                'page'        => $page,
                'random'      => 0,
            ]),
        ]);
        if ($caBundle) curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        $body = curl_exec($ch);
        curl_close($ch);
        if ($body === false) break;

        $json = json_decode((string)$body, true);
        if (!is_array($json) || empty($json['success'])) break;

        $batch = $json['data']['questions'] ?? [];
        if ($total === null) $total = (int)($json['data']['count'] ?? 0);
        if (!$batch) break;

        foreach ($batch as $q) {
            $collected[] = $q;
        }
        if (count($collected) >= $total) break;
    }

    return $collected;
}

// ---------------------------------------------------------------- the list
$nonce = studyproper_nonce();
if (!$nonce) {
    study_say('Could not get a session nonce from StudyProper. Stopping.');
    exit(1);
}
study_say('Session nonce obtained.');

// The subjects each examination holds, straight from the site.
$subjectLists = [];
foreach (array_keys($examMap) as $examCode) {
    $ch = curl_init(AJAX_URL);
    $caBundle = study_ca_bundle();
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) MyStudyBaseStudyHarvester/1.0',
        CURLOPT_HTTPHEADER     => [
            'X-Requested-With: XMLHttpRequest',
            'Referer: https://studyproper.com/',
            'Cookie: hc_js_gate=1',
        ],
        CURLOPT_POSTFIELDS     => http_build_query([
            'action' => 'sn_get_subjects',
            'nonce'  => $nonce,
            'exam'   => $examCode,
        ]),
    ]);
    if ($caBundle) curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
    $body = curl_exec($ch);
    curl_close($ch);
    $json = json_decode((string)$body, true);
    $subjectLists[$examCode] = array_column($json['data']['subjects'] ?? [], 'value');
    study_say('  ' . $examCode . ': ' . count($subjectLists[$examCode]) . ' subjects');
}

// ---------------------------------------------------------------- the work
$existing = study_existing_paper_keys($pdo, 'studyproper');
if ($refresh) {
    $existing = [];
    study_say('Refresh mode: every group will be harvested again.');
}

$papers = 0;
$questions = 0;
$groups = 0;

foreach ($examMap as $examCode => $meta) {
    foreach ($subjectLists[$examCode] as $subjectValue) {
        $subject = study_canonical_subject('studyproper', $subjectValue);
        if (!$subject) continue;   // not an examination subject we keep

        foreach ($years as $year) {
            $key = $meta['exam'] . '|' . $subject['slug'] . '|' . $year . '|' . $meta['paper_code'];
            if (isset($existing[$key])) continue;

            $groups++;
            if ($limit > 0 && $groups > $limit) {
                study_say('Limit reached (' . $limit . ' groups). Stopping.');
                break 3;
            }

            $raw = studyproper_questions($nonce, $examCode, $subjectValue, (string)$year);
            if (!$raw) continue;

            $list = [];
            $n = 0;
            foreach ($raw as $q) {
                $n++;
                $options = [];
                foreach (['a', 'b', 'c', 'd', 'e'] as $letter) {
                    $key2 = 'option_' . $letter;
                    if (!empty($q[$key2])) {
                        $options[] = ['label' => strtoupper($letter), 'text' => (string)$q[$key2]];
                    }
                }
                if (!$options) continue;
                $list[] = [
                    'display_number'   => $n,
                    'question'         => trim((string)$q['question']),
                    'options'          => $options,
                    'correct_option'   => strtoupper(trim((string)($q['correct_answer'] ?? ''))),
                    'explanation'      => !empty($q['explanation']) ? trim((string)$q['explanation']) : null,
                    'topic'            => !empty($q['topic']) ? trim((string)$q['topic']) : null,
                    'source_question_id' => isset($q['id']) ? (string)$q['id'] : null,
                ];
            }
            if (!$list) continue;

            $title = $meta['label'] . ' ' . $subject['name'] . ' ' . $year . ' Past Questions and Answers';

            if ($dryRun) {
                study_say('  - ' . $title . ': ' . count($list) . ' questions (not stored, dry run)');
                continue;
            }

            $paperId = study_upsert_paper($pdo, [
                'exam'           => $meta['exam'],
                'subject_slug'   => $subject['slug'],
                'subject_name'   => $subject['name'],
                'year'           => $year,
                'paper_code'     => $meta['paper_code'],
                'title'          => $title,
                'source'         => 'studyproper',
                'source_url'     => 'https://studyproper.com/' . ($examCode === 'gce' ? 'gce' : $examCode) . '-past-questions/',
                'question_count' => count($list),
            ]);
            $written = study_replace_questions($pdo, $paperId, $list);

            $papers++;
            $questions += $written;
            study_say('  + ' . $title . ': ' . $written . ' questions');
        }
    }
}

study_say('');
study_say($dryRun
    ? 'Dry run: ' . $papers . ' papers would have been written.'
    : 'Done: ' . $papers . ' papers, ' . $questions . ' questions.');
