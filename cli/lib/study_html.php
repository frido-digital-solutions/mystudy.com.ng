<?php
/**
 * Turns the HTML of a question into text a student can read.
 *
 * A question on some sites is not only words. It can be a table - a balance
 * sheet, a list of prices - and it can carry the site's own buttons and
 * branding inside the question itself.
 *
 * So the HTML is read properly:
 *
 *   - a table becomes one line per row, the cells apart by a bar
 *   - a line break becomes a real line break
 *   - bold and italic are dropped, the words they held are kept
 *   - the site's own buttons, its branding and its scripts are taken out
 */

if (!function_exists('study_html_to_text')) {

    /**
     * The text of a node, with the tables and the site's own furniture dealt
     * with. The node is not changed: a copy of it is worked on.
     *
     * @param DOMNode $node the node to read
     * @return string
     */
    function study_html_to_text(DOMNode $node): string
    {
        require_once __DIR__ . '/study_math.php';

        $doc = $node->ownerDocument;
        if (!$doc) return '';

        $copy = $node->cloneNode(true);

        // The furniture of the page: its buttons, its branding, its scripts.
        // The queries are relative, so that only the copy is touched.
        $xp = new DOMXPath($doc);
        foreach ([
            ".//*[contains(@class,'view-answer-btn')]",
            ".//*[contains(@class,'label-text')]",
            ".//*[contains(@class,'stickyElement')]",
            ".//*[contains(@class,'fx_tp')]",
            ".//script",
            ".//style",
        ] as $query) {
            foreach ($xp->query($query, $copy) as $remove) {
                if ($remove->parentNode) $remove->parentNode->removeChild($remove);
            }
        }

        // A table becomes lines of text, one line for each row.
        foreach ($xp->query(".//table", $copy) as $table) {
            $rows = [];
            foreach ($xp->query(".//tr", $table) as $tr) {
                $cells = [];
                foreach ($xp->query("./td|./th", $tr) as $cell) {
                    $cells[] = trim(preg_replace('/\s+/u', ' ', $cell->textContent));
                }
                $cells = array_values(array_filter($cells, fn($c) => $c !== ''));
                if ($cells) $rows[] = implode(' | ', $cells);
            }
            $text = $rows ? implode("\n", $rows) : '';
            $table->parentNode->replaceChild($doc->createTextNode($text), $table);
        }

        // A line break is a line break.
        foreach ($xp->query(".//br", $copy) as $br) {
            $br->parentNode->replaceChild($doc->createTextNode("\n"), $br);
        }

        // Any tag that is left is a tag that only dressed the words up.
        $text = $copy->textContent;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\n{3,}/u', "\n\n", $text);
        $lines = array_map('trim', explode("\n", $text));

        // A formula written into the text is written out, line by line, so
        // that the line breaks of a table survive.
        foreach ($lines as $i => $line) {
            if ($line !== '' && preg_match('/\\\\\(|\\\\\[|\\\\[a-zA-Z]+|\$[^$]+\$/', $line)) {
                $lines[$i] = study_latex_to_text($line);
            }
        }

        return trim(implode("\n", $lines));
    }

    /**
     * The text of a piece of HTML held as text.
     *
     * Some sites write the markup of a table as text inside the question.
     * The tags are read here as the table they are.
     *
     * @param string $html
     * @return string
     */
    function study_html_string_to_text(string $html): string
    {
        if (trim($html) === '') return '';
        if (!preg_match('/<[a-zA-Z\/!][^>]*>/', $html)) {
            // No markup: the line breaks that are already there are kept.
            $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/[ \t]+/u', ' ', $text);
            $text = preg_replace('/ *\n */u', "\n", $text);
            return trim($text);
        }

        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="msb-html">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('msb-html');
        if (!$root) {
            $root = $doc->getElementsByTagName('div')->item(0);
        }
        if (!$root) {
            return trim(preg_replace('/\s+/u', ' ', html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }
        return study_html_to_text($root);
    }

    /**
     * Text that is read into the plain text a student should see.
     *
     * The markup is read first, and then a formula written into the text is
     * written out. A formula is only looked for where there is LaTeX to
     * find, so a price written with a naira or a dollar sign is left alone.
     *
     * @param string $text
     * @return string
     */
    function study_text_to_plain(string $text): string
    {
        require_once __DIR__ . '/study_math.php';

        $text = study_html_string_to_text($text);
        if (trim($text) === '') return '';

        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            if ($line !== '' && preg_match('/\\\\\(|\\\\\[|\\\\[a-zA-Z]+/', $line)) {
                $lines[$i] = study_latex_to_text($line);
            }
        }
        return trim(implode("\n", $lines));
    }
}
