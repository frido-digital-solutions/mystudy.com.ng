<?php
/**
 * The reader for skolra pages, kept apart so it can be used and tested on its own.
 */
require_once __DIR__ . '/study_harvest.php';
require_once __DIR__ . '/study_html.php';

function skolra_questions(string $html): array
{
    $doc = study_dom($html);
    if (!$doc) return [];

    $xp = new DOMXPath($doc);
    $out = [];
    $number = 0;

    foreach ($xp->query("//ol[contains(@class,'q-opts')]") as $ol) {
        $number++;

        // The question sits in the block just above its options.
        $question = '';
        $stem = null;
        $prev = $ol->previousSibling;
        while ($prev !== null) {
            if ($prev->nodeType === XML_ELEMENT_NODE) {
                $stem = $prev;
                break;
            }
            $prev = $prev->previousSibling;
        }
        if ($stem) {
            $question = skolra_text($stem);
        }
        if ($question === '') continue;

        // The page prints the question number as part of the question text.
        $question = preg_replace('/^\s*\d{1,3}\s*[\.\)]\s*/u', '', $question);
        if ($question === '') continue;

        $options = [];
        $answer  = '';
        foreach ($xp->query('./li[contains(@class,"q-opt")]', $ol) as $li) {
            $labelNode = $xp->query('.//span[contains(@class,"q-l")]', $li)->item(0);
            $textNode  = $xp->query('.//div[contains(@class,"q-t")]', $li)->item(0);
            if (!$labelNode) continue;
            $label = strtoupper(study_node_text($labelNode));
            $text  = $textNode ? skolra_text($textNode) : '';
            if ($label === '') continue;
            $options[] = ['label' => $label, 'text' => $text];
            if (strpos(' ' . $li->getAttribute('class') . ' ', ' q-right ') !== false) {
                $answer = $label;
            }
        }
        if (!$options) continue;

        // The worked explanation sits under the answer.
        $explanation = null;
        $why = $xp->query(".//div[contains(@class,'q-why')]", $ol->parentNode ?: $doc);
        if ($why->length) {
            $explanation = skolra_text($why->item(0));
            if ($explanation === '') $explanation = null;
        }

        $out[$number] = [
            'display_number' => $number,
            'question'       => $question,
            'options'        => $options,
            'correct_option' => $answer,
            'explanation'    => $explanation,
            'topic'          => null,
        ];
    }

    return $out;
}

/**
 * The text of a node, with the maths kept and the duplicated KaTeX
 * rendering taken out.
 *
 * KaTeX writes the formula twice: once as MathML, which reads as text, and
 * once as HTML, which is marked aria-hidden for screen readers. Keeping both
 * would print every formula twice, so the shared reader is used, which takes
 * the formula out on its own and writes it plainly.
 */
function skolra_text($node): string
{
    if ($node === null) return '';

    $doc = $node->ownerDocument;
    if ($doc === null) return study_node_text($node);

    $xp = new DOMXPath($doc);
    $maths = $xp->query(".//*[contains(@class,'katex') or self::math or contains(@class,'math-svg')]", $node);
    if ($maths->length && !$xp->query(".//table", $node)->length) {
        return study_math_text($node);
    }

    // A question can hold a table. A table is read as a table: one line for
    // each row, the cells apart by a bar.
    if ($xp->query(".//table", $node)->length) {
        return study_html_to_text($node);
    }

    $text = study_node_text($node);
    $text = preg_replace('/\s*\n\s*/u', ' ', $text);
    return trim((string)$text);
}
