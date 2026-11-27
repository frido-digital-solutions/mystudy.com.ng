<?php
/**
 * The reader for smartest pages, kept apart so it can be used and tested on its own.
 */
require_once __DIR__ . '/study_harvest.php';

function smartest_questions(string $html): array
{
    $doc = study_dom($html);
    if (!$doc) return [];

    $xp = new DOMXPath($doc);
    $out = [];

    $lists = $xp->query("//ol[contains(@class,'pq-guard')]");
    if ($lists->length === 0) {
        $lists = $xp->query("//ol[contains(@class,'paper')]");
    }
    if ($lists->length === 0) return [];

    foreach ($lists->item(0)->childNodes as $li) {
        if ($li->nodeType !== XML_ELEMENT_NODE || strtolower($li->tagName) !== 'li') continue;

        $stemNode = (new DOMXPath($doc))->query(".//div[contains(@class,'q-stem')]", $li)->item(0);
        $question = study_node_text($stemNode);
        if ($question === '') continue;

        $number = 0;
        $numberNode = (new DOMXPath($doc))->query(".//span[contains(@class,'tabular-nums')]", $li)->item(0);
        if ($numberNode) {
            $number = (int)str_replace('.', '', study_node_text($numberNode));
        }
        if ($number === 0) $number = count($out) + 1;

        $options = [];
        $answer  = '';
        foreach ((new DOMXPath($doc))->query(".//ol[@type='A']/li", $li) as $optLi) {
            $spans = (new DOMXPath($doc))->query('./span', $optLi);
            if ($spans->length < 2) continue;
            $label = strtoupper(rtrim(study_node_text($spans->item(0)), '.'));
            $text  = smartest_option_text($spans->item(1));
            if ($label === '') continue;
            $options[] = ['label' => $label, 'text' => $text];
        }

        foreach ((new DOMXPath($doc))->query(".//p[contains(@class,'text-ink')]", $li) as $p) {
            $text = study_node_text($p);
            if (stripos($text, 'Answer:') !== 0) continue;
            $strong = (new DOMXPath($doc))->query('.//strong', $p)->item(0);
            if ($strong) {
                $answer = strtoupper(study_node_text($strong));
            }
            break;
        }

        $out[$number] = [
            'display_number' => $number,
            'question'       => $question,
            'options'        => $options,
            'correct_option' => $answer,
            'explanation'    => null,
            'topic'          => null,
        ];
    }

    return $out;
}

/**
 * The text of one option.
 *
 * A formula is written as a picture of the formula, so the shared reader
 * takes the formula out on its own and writes it plainly.
 */
function smartest_option_text($node): string
{
    if ($node === null) return '';
    return study_node_text($node);
}
