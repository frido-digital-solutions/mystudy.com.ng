<?php
/**
 * The reader for acadmate pages, kept apart so it can be used and tested on its own.
 */
require_once __DIR__ . '/study_harvest.php';

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
