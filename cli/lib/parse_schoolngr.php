<?php
/**
 * The reader for SchoolNGR pages, kept apart so it can be used and tested on its own.
 */
require_once __DIR__ . '/study_harvest.php';
require_once __DIR__ . '/study_html.php';

function schoolngr_questions(string $html): array
{
    $doc = study_dom($html);
    if (!$doc) return [];

    $xp = new DOMXPath($doc);
    $out = [];

    foreach ($xp->query("//div[contains(@class,'question-block')]") as $block) {
        $yearNode = $xp->query(".//div[contains(@class,'question-year')]//a", $block)->item(0);
        $year = 0;
        if ($yearNode) {
            if (preg_match('/(\d{4})/', study_node_text($yearNode), $y)) {
                $year = (int)$y[1];
            }
        }

        $textNode = $xp->query(".//div[contains(@class,'question-text')]", $block)->item(0);
        $question = $textNode ? study_html_to_text($textNode) : '';
        if ($question === '') continue;

        // Some questions carry the table of the question inside the question
        // itself, as text. The tags are read here as the table they are.
        $question = study_html_string_to_text($question);
        if ($question === '') continue;

        $options = [];
        $answer  = '';
        foreach ($xp->query(".//div[contains(@class,'options')]//li", $block) as $li) {
            $label = strtoupper(trim($li->getAttribute('data-option')));
            if ($label === '') continue;
            $text = study_html_to_text($li);
            // The label is printed inside the option, so drop that copy.
            $text = preg_replace('/^\s*' . preg_quote($label, '/') . '\s*/u', '', $text);
            $options[] = ['label' => $label, 'text' => trim((string)$text)];
            if ($li->getAttribute('data-correct') === 'true') {
                $answer = $label;
            }
        }
        if (!$options || $answer === '') continue;

        // The link to the question's own page is its identifier there.
        $idNode = $xp->query(".//a[contains(@class,'view-answer-btn')]", $block)->item(0);
        $sourceId = null;
        if ($idNode) {
            if (preg_match('#/classroom/[a-z0-9-]+/(\d+)#', $idNode->getAttribute('href'), $m)) {
                $sourceId = $m[1];
            }
        }

        $out[] = [
            'display_number'   => 0,
            'question'         => $question,
            'options'          => $options,
            'correct_option'   => $answer,
            'explanation'      => null,
            'topic'            => null,
            'source_question_id' => $sourceId,
            'year'             => $year,
        ];
    }

    return $out;
}
