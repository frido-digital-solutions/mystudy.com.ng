<?php
/**
 * Checks the Study library for damage.
 *
 * The library is only worth anything if every question in it is a whole
 * question: the text there, the options there, and one of those options
 * marked as the right one. This script counts what is broken and says so.
 *
 *     php cli/audit_study.php
 */
require_once __DIR__ . '/../config/config.php';

$rows = $pdo->query("
    SELECT q.id, q.paper_id, q.question, q.options_json, q.correct_option,
           p.exam, p.subject_slug, p.year, p.source, p.title, p.source_url
    FROM study_questions q
    JOIN study_papers p ON p.id = q.paper_id
")->fetchAll();

$total   = count($rows);
$broken  = 0;
$bySource = [];
$reasons = [
    'empty question text'      => 0,
    'no options at all'        => 0,
    'an option has no text'    => 0,
    'no correct option'        => 0,
    'correct option missing'   => 0,
    'two options share a label'=> 0,
];
$examples = [];

foreach ($rows as $r) {
    $src  = $r['source'];
    $why  = [];

    if (trim((string)$r['question']) === '') {
        $why[] = 'empty question text';
    }

    $opts = json_decode((string)$r['options_json'], true);
    if (!is_array($opts) || count($opts) < 2) {
        $why[] = 'no options at all';
        $labels = [];
    } else {
        $labels = [];
        foreach ($opts as $o) {
            $label = strtoupper(trim((string)($o['label'] ?? '')));
            $text  = trim((string)($o['text'] ?? ''));
            if ($label === '') { $why[] = 'an option has no text'; continue; }
            if ($text === '')  { $why[] = 'an option has no text'; }
            $labels[] = $label;
        }
        if (count(array_unique($labels)) !== count($labels)) {
            $why[] = 'two options share a label';
        }
        $ans = strtoupper(trim((string)$r['correct_option']));
        if ($ans === '') {
            $why[] = 'no correct option';
        } elseif (!in_array($ans, $labels, true)) {
            $why[] = 'correct option missing';
        }
    }

    if (!$why) continue;

    $broken++;
    foreach ($why as $w) $reasons[$w]++;
    $bySource[$src] = ($bySource[$src] ?? 0) + 1;

    if (count($examples) < 10) {
        $examples[] = "#{$r['id']} {$r['exam']} {$r['subject_slug']} {$r['year']} ({$src}): " . implode('; ', $why);
    }
}

$whole = $total - $broken;

echo "Questions in the library: $total\n\n";
echo "Whole questions (text, options with text, and one of them marked correct): $whole\n";
echo "Broken questions: $broken\n\n";

echo "Why they are broken:\n";
foreach ($reasons as $why => $n) {
    if ($n) printf("  %-28s %d\n", $why, $n);
}
echo "\nBy source:\n";
foreach ($bySource as $src => $n) printf("  %-12s %d broken\n", $src, $n);

if ($examples) {
    echo "\nExamples:\n";
    foreach ($examples as $e) echo "  $e\n";
}

// The paper's own count must match the questions actually stored in it.
$off = $pdo->query("
    SELECT p.id, p.title, p.source, p.question_count,
           (SELECT COUNT(*) FROM study_questions q WHERE q.paper_id = p.id) AS actual
    FROM study_papers p
")->fetchAll();
$bad = [];
foreach ($off as $m) {
    if ((int)$m['question_count'] !== (int)$m['actual']) {
        $bad[] = "  #{$m['id']} {$m['title']} ({$m['source']}): says {$m['question_count']}, holds {$m['actual']}";
    }
}
echo "\nPapers whose stored count does not match its questions: " . count($bad) . "\n";
foreach (array_slice($bad, 0, 10) as $o) echo "$o\n";

$dupes = $pdo->query("
    SELECT COUNT(*) FROM (
        SELECT paper_id, question, COUNT(*) c
        FROM study_questions
        GROUP BY paper_id, question
        HAVING c > 1
    ) t
")->fetchColumn();
echo "\nQuestions stored twice in the same paper: $dupes\n";

$empty = $pdo->query("
    SELECT COUNT(*) FROM study_papers p
    WHERE NOT EXISTS (SELECT 1 FROM study_questions q WHERE q.paper_id = p.id)
")->fetchColumn();
echo "Papers with no questions in them: $empty\n";
