<?php
/**
 * Tidies the questions that are already in the library.
 *
 * Two faults are dealt with:
 *
 *   1. a question whose text still holds the markup of a table or a tag,
 *      which is read here into the text a student should see;
 *   2. an option list that holds the same label twice, which happens when a
 *      question carries its own options inside it as well as beside it. The
 *      second copy is dropped.
 *
 * The same readers that the harvesters use are used here, so a question
 * tidied this way reads exactly as it would if it were fetched again.
 *
 *     php cli/clean_study_text.php            tidy the library
 *     php cli/clean_study_text.php --dry-run  say what would change, change nothing
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/study_harvest.php';
require_once __DIR__ . '/lib/study_html.php';

$dryRun = in_array('--dry-run', array_slice($argv, 1), true);

$rows = $pdo->query("
    SELECT id, paper_id, display_number, question, options_json, correct_option
    FROM study_questions
")->fetchAll();

$changedQuestions = 0;
$changedOptions   = 0;
$droppedLabels    = 0;

$updateQ = $pdo->prepare("UPDATE study_questions SET question = ? WHERE id = ?");
$updateO = $pdo->prepare("UPDATE study_questions SET options_json = ? WHERE id = ?");

$pdo->beginTransaction();
try {
    foreach ($rows as $r) {
        $question = (string)$r['question'];
        $options  = json_decode((string)$r['options_json'], true);
        if (!is_array($options)) continue;

        // 1. The text of the question, read into plain text.
        $newQuestion = study_text_to_plain($question);

        // 2. The options, read into plain text, and the copy of an option
        //    that carries a label already there taken out.
        $newOptions = [];
        $seen = [];
        $answer = strtoupper(trim((string)$r['correct_option']));
        foreach ($options as $o) {
            $label = strtoupper(trim((string)($o['label'] ?? '')));
            $text  = study_text_to_plain((string)($o['text'] ?? ''));
            if ($label === '' || $text === '') continue;
            if (isset($seen[$label])) {
                $droppedLabels++;
                continue;
            }
            $seen[$label] = true;
            $newOptions[] = ['label' => $label, 'text' => $text];
        }

        // The answer must still be one of the options that are there.
        $labels = array_column($newOptions, 'label');
        if ($answer !== '' && !in_array($answer, $labels, true)) {
            $answer = '';
        }

        $questionChanged = ($newQuestion !== $question);
        $optionsChanged  = (json_encode($newOptions) !== json_encode(array_values($options)));

        if (!$questionChanged && !$optionsChanged) continue;

        if ($dryRun) {
            if ($questionChanged) $changedQuestions++;
            if ($optionsChanged)  $changedOptions++;
            continue;
        }

        if ($questionChanged) {
            $updateQ->execute([$newQuestion, $r['id']]);
            $changedQuestions++;
        }
        if ($optionsChanged) {
            $updateO->execute([
                json_encode($newOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $r['id'],
            ]);
            $changedOptions++;
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    echo 'Cleaning failed and was rolled back: ' . $e->getMessage() . "\n";
    exit(1);
}

echo 'Questions looked at: ' . count($rows) . "\n";
echo 'Questions tidied:   ' . $changedQuestions . "\n";
echo 'Option lists tidied: ' . $changedOptions . "\n";
echo 'Repeated labels dropped: ' . $droppedLabels . "\n";
echo $dryRun ? "\nDry run - nothing was changed.\n" : "\nDone.\n";
