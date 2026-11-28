<?php
/**
 * Repairs the Study library: removes the questions that are not whole
 * questions, and removes questions stored twice in the same paper.
 *
 * A question is only kept when it has its text, at least two options that
 * each have something to read, and one of those options marked as the right
 * one. Anything else is a broken question and is deleted.
 *
 * The harvesters now refuse to store a broken question in the first place,
 * so this only has to deal with what was stored before that rule existed.
 *
 *     php cli/repair_study.php            repair it
 *     php cli/repair_study.php --dry-run  say what would go, change nothing
 */
require_once __DIR__ . '/../config/config.php';

$dryRun = in_array('--dry-run', array_slice($argv, 1), true);

$rows = $pdo->query("
    SELECT q.id, q.paper_id, q.question, q.options_json, q.correct_option,
           p.exam, p.subject_slug, p.year, p.source
    FROM study_questions q
    JOIN study_papers p ON p.id = q.paper_id
")->fetchAll();

$keep = [];
$drop = [];

foreach ($rows as $r) {
    $question = trim((string)$r['question']);
    $opts = json_decode((string)$r['options_json'], true);
    $answer = strtoupper(trim((string)$r['correct_option']));

    $whole = $question !== ''
        && is_array($opts)
        && count($opts) >= 2;

    $labels = [];
    if ($whole) {
        foreach ($opts as $o) {
            $label = strtoupper(trim((string)($o['label'] ?? '')));
            $text  = trim((string)($o['text'] ?? ''));
            if ($label === '' || $text === '') { $whole = false; break; }
            $labels[] = $label;
        }
    }
    if ($whole && ($answer === '' || !in_array($answer, $labels, true))) {
        $whole = false;
    }

    if (!$whole) {
        $drop[$r['id']] = $r;
        continue;
    }

    // The same question twice in one paper is stored once.
    $fingerprint = md5(strtolower(preg_replace('/\s+/', ' ', $question)));
    $key = $r['paper_id'] . ':' . $fingerprint;
    if (isset($keep[$key])) {
        $drop[$r['id']] = $r;      // the second copy
        continue;
    }
    $keep[$key] = $r['id'];
}

echo 'Questions in the library: ' . count($rows) . "\n";
echo 'Broken questions to remove: ' . count($drop) . "\n";

$bySource = [];
foreach ($drop as $r) {
    $bySource[$r['source']] = ($bySource[$r['source']] ?? 0) + 1;
}
foreach ($bySource as $src => $n) echo "  $src: $n\n";

if ($dryRun) {
    echo "\nDry run - nothing was changed.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("DELETE FROM study_questions WHERE id = ?");
    foreach (array_keys($drop) as $id) {
        $stmt->execute([$id]);
    }

    // Every paper must say how many questions it really holds.
    $counts = $pdo->query("
        SELECT p.id, p.question_count,
               (SELECT COUNT(*) FROM study_questions q WHERE q.paper_id = p.id) AS actual
        FROM study_papers p
    ")->fetchAll();
    $fix = $pdo->prepare("UPDATE study_papers SET question_count = ? WHERE id = ?");
    $fixed = 0;
    $empty = [];
    foreach ($counts as $c) {
        if ((int)$c['actual'] === 0) {
            // A paper with no questions in it is an empty page for a student.
            $empty[] = (int)$c['id'];
            continue;
        }
        if ((int)$c['question_count'] === (int)$c['actual']) continue;
        $fix->execute([(int)$c['actual'], (int)$c['id']]);
        $fixed++;
    }

    // A paper with no questions is taken out of the library.
    $rmPaper = $pdo->prepare("DELETE FROM study_papers WHERE id = ?");
    foreach ($empty as $id) {
        $rmPaper->execute([$id]);
    }

    $pdo->commit();
    echo "\nRemoved " . count($drop) . " questions, corrected $fixed paper counts, removed " . count($empty) . " empty papers.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo 'Repair failed and was rolled back: ' . $e->getMessage() . "\n";
    exit(1);
}
