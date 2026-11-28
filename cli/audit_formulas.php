<?php
/**
 * Checks how formulas and other formatting came through.
 *
 * A formula in a past question has to arrive as something a student can
 * read: "1/2", not "\frac{1}{2}", and not a leftover tag from the page it
 * was copied from.
 */
require_once __DIR__ . '/../config/config.php';

$rows = $pdo->query("
    SELECT q.id, q.question, q.options_json, q.explanation,
           p.exam, p.subject_slug, p.year, p.source
    FROM study_questions q
    JOIN study_papers p ON p.id = q.paper_id
")->fetchAll();

$latexQ = 0; $latexO = 0; $tagQ = 0; $tagO = 0;
$htmlQ = 0; $htmlO = 0; $mathy = 0;
$examples = [];
$bySubject = [];

foreach ($rows as $r) {
    $opts = json_decode((string)$r['options_json'], true);
    $otext = is_array($opts)
        ? implode(' | ', array_map(fn($o) => ($o['label'] ?? '') . ': ' . ($o['text'] ?? ''), $opts))
        : '';

    $hasLatexQ = (bool)preg_match('/\\\\[a-zA-Z]+/', $r['question']);
    $hasLatexO = (bool)preg_match('/\\\\[a-zA-Z]+/', $otext);
    // A tag is a name in angle brackets. A sign of "less than" is not a tag:
    // "y < 6" is a piece of mathematics, not markup.
    $hasTagQ   = (bool)preg_match('/<\/?[a-zA-Z][a-zA-Z0-9]*(\s[^<>]*)?\/?>/', $r['question']);
    $hasTagO   = (bool)preg_match('/<\/?[a-zA-Z][a-zA-Z0-9]*(\s[^<>]*)?\/?>/', $otext);

    if ($hasLatexQ) $latexQ++;
    if ($hasLatexO) $latexO++;
    if ($hasTagQ)   $tagQ++;
    if ($hasTagO)   $tagO++;

    // A subject that carries formulas, to see how many of its questions do.
    $isMathy = in_array($r['subject_slug'], [
        'mathematics', 'further-mathematics', 'physics', 'chemistry', 'data-processing', 'computer-studies',
    ], true);
    if ($isMathy) {
        $mathy++;
        $bySubject[$r['subject_slug']] = ($bySubject[$r['subject_slug']] ?? 0) + 1;
    }

    if (($hasLatexQ || $hasLatexO || $hasTagQ || $hasTagO) && count($examples) < 10) {
        $examples[] = "#{$r['id']} {$r['exam']} {$r['subject_slug']} {$r['year']} ({$r['source']})\n"
            . "   q: " . substr($r['question'], 0, 180) . "\n"
            . "   o: " . substr($otext, 0, 220);
    }
}

echo "Questions in the library: " . count($rows) . "\n\n";
echo "Raw LaTeX left in a question:  $latexQ\n";
echo "Raw LaTeX left in an option:   $latexO\n";
echo "A leftover tag in a question:   $tagQ\n";
echo "A leftover tag in an option:    $tagO\n\n";

echo "Questions in the subjects that carry formulas:\n";
foreach ($bySubject as $s => $n) echo "  $s: $n\n";
echo "  total: $mathy\n";

if ($examples) {
    echo "\nExamples:\n";
    foreach ($examples as $e) echo "$e\n";
}
