<?php
/**
 * Reading a formula out of a past question.
 *
 * The sites write their formulas with KaTeX, which puts the same formula in
 * the page three times:
 *
 *   - as MathML, the form a screen reader reads;
 *   - as an <annotation> holding the LaTeX source;
 *   - as a picture of the formula, marked as hidden from screen readers.
 *
 * Taking the text of the page as it stands gives all three at once, so a
 * formula arrives as "I21,c00,pm32,m33I\frac{2}{1}..." - the pieces of the
 * formula and its source, mixed together and unreadable.
 *
 * So a formula is taken out on its own and written as plain text a student
 * can read: "I(2/1), c(0/0), pm(3/2), m(3/3)".
 */

/**
 * The text of a node, with every formula in it written out plainly.
 *
 * @param DOMNode|null $node
 * @return string
 */
function study_math_text($node): string
{
    if ($node === null) return '';

    $doc = $node->ownerDocument;
    if ($doc === null) {
        return study_node_text($node);
    }

    $xp = new DOMXPath($doc);

    // Every formula on the page, whether it is a picture of one, a MathML
    // one, or a formula written out in LaTeX.
    $found = $xp->query(".//*[contains(@class,'katex') or self::math or @data-testid='react-katex' or contains(@class,'math-svg')]", $node);
    $maths = [];
    foreach ($found as $el) {
        $maths[] = $el;
    }

    // Take out the outermost one of each, so a formula inside a formula is
    // not read twice.
    $skip = [];
    foreach ($maths as $el) {
        $parent = $el->parentNode;
        while ($parent !== null && $parent !== $node) {
            if (isset($skip[spl_object_id($parent)])) break;
            if ($parent->nodeType === XML_ELEMENT_NODE && isset($skip[spl_object_id($parent)])) break;
            $parent = $parent->parentNode;
        }
    }
    // Simpler and safe: keep only those whose nearest maths ancestor is not
    // also in the list.
    $outer = [];
    foreach ($maths as $el) {
        $inside = false;
        $parent = $el->parentNode;
        while ($parent !== null && $parent !== $node) {
            foreach ($maths as $other) {
                if ($other === $parent) { $inside = true; break 2; }
            }
            $parent = $parent->parentNode;
        }
        if (!$inside) $outer[] = $el;
    }

    foreach ($outer as $el) {
        $text = study_formula_text($el);
        if ($text === null) continue;
        $replacement = $doc->createTextNode(' ' . $text . ' ');
        if ($el->parentNode) {
            $el->parentNode->replaceChild($replacement, $el);
        }
    }

    // The text of what is left. Taken directly, because asking the shared
    // reader for it would bring us back here.
    $text = $node->textContent ?? '';
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = preg_replace('/\s+([,.;:?!])/u', '$1', $text);
    return trim((string)$text);
}

/**
 * One formula, written as plain text.
 *
 * @param DOMElement $el
 * @return string|null  null when this is not really a formula
 */
function study_formula_text(DOMElement $el): ?string
{
    $doc = $el->ownerDocument;
    if ($doc === null) return null;
    $xp = new DOMXPath($doc);

    // A formula written out in LaTeX, kept for screen readers. The label is
    // on the picture itself, so the element itself counts as well.
    $svg = $xp->query("descendant-or-self::*[@aria-label]", $el);
    foreach ($svg as $node) {
        $label = trim($node->getAttribute('aria-label'));
        if ($label !== '' && $label !== 'img') {
            return study_latex_to_text($label);
        }
    }

    // The LaTeX source, kept in the MathML.
    $annotation = $xp->query(".//annotation", $el);
    foreach ($annotation as $node) {
        $enc = strtolower((string)$node->getAttribute('encoding'));
        $src = trim(study_node_text($node));
        if ($src === '') continue;
        if ($enc === '' || strpos($enc, 'tex') !== false) {
            return study_latex_to_text($src);
        }
    }

    // No source: read the MathML itself.
    $math = $xp->query(".//math", $el);
    if ($math->length) {
        return study_mathml_to_text($math->item(0));
    }

    // Nothing but a picture of the formula: keep it as an image.
    return null;
}

/**
 * LaTeX written as plain text.
 *
 * This covers what a school paper actually uses: fractions, roots, powers,
 * subscripts, Greek letters and the words inside \text{...}.
 */
function study_latex_to_text(string $latex): string
{
    $latex = trim($latex);
    if ($latex === '') return '';

    // A formula written inline: \( ... \) or $ ... $ or \[ ... \].
    $latex = preg_replace('/\\\\\((.*?)\\\\\)/s', '$1', $latex);
    $latex = preg_replace('/\\\\\[(.*?)\\\\\]/s', '$1', $latex);
    $latex = preg_replace('/\$(.*?)\$/s', '$1', $latex);

    // A table: keep the rows apart so the numbers stay readable.
    $latex = preg_replace('/\\\\begin\{array\}\{[^}]*\}/', ' ', $latex);
    $latex = preg_replace('/\\\\end\{array\}/', '; ', $latex);
    $latex = preg_replace('/\\\\hline/', ' | ', $latex);
    $latex = preg_replace('/\\\\begin\{[^}]*\}(\{[^}]*\})?/', ' ', $latex);
    $latex = preg_replace('/\\\\end\{[^}]*\}/', '; ', $latex);
    $latex = str_replace('\\\\', '; ', $latex);
    $latex = str_replace('&', '  ', $latex);

    // The words inside \text{...} are just words.
    $latex = preg_replace('/\\\\text\{([^{}]*)\}/', '$1', $latex);
    $latex = preg_replace('/\\\\mathrm\{([^{}]*)\}/', '$1', $latex);
    $latex = preg_replace('/\\\\mathbf\{([^{}]*)\}/', '$1', $latex);
    $latex = preg_replace('/\\\\mathit\{([^{}]*)\}/', '$1', $latex);
    $latex = preg_replace('/\\\\operatorname\{([^{}]*)\}/', '$1', $latex);
    $latex = preg_replace('/\\\\mbox\{([^{}]*)\}/', '$1', $latex);

    // Fractions: \frac{a}{b} becomes (a/b).
    // A fraction written without braces (\frac34) is read as well.
    $latex = preg_replace_callback('/\\\\(?:d?frac|tfrac)(\d)(\d)/', function ($m) {
        return '(' . $m[1] . '/' . $m[2] . ')';
    }, $latex);
    for ($i = 0; $i < 6; $i++) {
        $before = $latex;
        $latex = preg_replace_callback('/\\\\(?:d?frac|tfrac)\{([^{}]*)\}\{([^{}]*)\}/', function ($m) {
            $a = study_latex_to_text($m[1]);
            $b = study_latex_to_text($m[2]);
            return '(' . $a . '/' . $b . ')';
        }, $latex);
        $latex = preg_replace_callback('/\\\\(?:d?frac|tfrac)\{(\d)\}(\d)/', function ($m) {
            return '(' . $m[1] . '/' . $m[2] . ')';
        }, $latex);
        if ($latex === $before) break;
    }

    // Roots: \sqrt{x} becomes √(x).
    $latex = preg_replace_callback('/\\\\sqrt\[(\d+)\]\{([^{}]*)\}/', function ($m) {
        return '√[' . $m[1] . '](' . study_latex_to_text($m[2]) . ')';
    }, $latex);
    $latex = preg_replace_callback('/\\\\sqrt\{([^{}]*)\}/', function ($m) {
        return '√(' . study_latex_to_text($m[1]) . ')';
    }, $latex);

    // A degree sign written as a power is the sign itself: 100^\circ C is
    // 100°C, not 100^° C. This is done before the powers are read, so that
    // the sign is not turned into a power first.
    $latex = preg_replace('/\^\s*\{?\s*\\\\(?:circ|degree)\s*\}?/', '°', $latex);

    // Powers and subscripts.
    $latex = preg_replace_callback('/\^\{([^{}]*)\}/', function ($m) {
        return '^(' . study_latex_to_text($m[1]) . ')';
    }, $latex);
    $latex = preg_replace_callback('/_\{([^{}]*)\}/', function ($m) {
        return '_(' . study_latex_to_text($m[1]) . ')';
    }, $latex);
    $latex = preg_replace('/\^(\w)/', '^($1)', $latex);
    $latex = preg_replace('/_(\w)/', '_($1)', $latex);

    // A power or a subscript of a number is written as it is written on
    // paper: y^(2) becomes y², not y^(2).
    $superscripts = ['0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴',
                     '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹',
                     'n' => 'ⁿ', '+' => '⁺', '-' => '⁻'];
    $subscripts  = ['0' => '₀', '1' => '₁', '2' => '₂', '3' => '₃', '4' => '₄',
                     '5' => '₅', '6' => '₆', '7' => '₇', '8' => '₈', '9' => '₉',
                     'n' => 'ₙ', '+' => '₊', '-' => '₋'];
    $latex = preg_replace_callback('/\^\(([0-9n+\-]{1,2})\)/', function ($m) use ($superscripts) {
        return strtr($m[1], $superscripts);
    }, $latex);
    $latex = preg_replace_callback('/_\(([0-9n+\-]{1,2})\)/', function ($m) use ($subscripts) {
        return strtr($m[1], $subscripts);
    }, $latex);

    // Greek letters and the symbols a paper uses.
    $symbols = [
        '\\alpha' => 'α', '\\beta' => 'β', '\\gamma' => 'γ', '\\delta' => 'δ',
        '\\epsilon' => 'ε', '\\varepsilon' => 'ε', '\\theta' => 'θ', '\\lambda' => 'λ',
        '\\mu' => 'μ', '\\pi' => 'π', '\\rho' => 'ρ', '\\sigma' => 'σ',
        '\\tau' => 'τ', '\\phi' => 'φ', '\\omega' => 'ω', '\\Delta' => 'Δ',
        '\\Sigma' => 'Σ', '\\Omega' => 'Ω', '\\times' => '×', '\\cdot' => '·',
        '\\div' => '÷', '\\pm' => '±', '\\leq' => '≤', '\\geq' => '≥',
        '\\neq' => '≠', '\\approx' => '≈', '\\equiv' => '≡', '\\propto' => '∝',
        '\\circ' => '°', '\\degree' => '°', '\\ldots' => '…', '\\cdots' => '…',
        '\\rightarrow' => '→', '\\to' => '→', '\\leftarrow' => '←',
        '\\Rightarrow' => '⇒', '\\therefore' => '∴', '\\because' => '∵',
        '\\infty' => '∞', '\\prime' => '′', '\\angle' => '∠', '\\perp' => '⊥',
        '\\parallel' => '∥', '\\in' => '∈', '\\cup' => '∪', '\\cap' => '∩',
        '\\dm' => ' dm', '\\cm' => ' cm', '\\mm' => ' mm', '\\kg' => ' kg',
        '\\mol' => ' mol', '\\km' => ' km', '\\cm2' => ' cm²', '\\cm3' => ' cm³',
        '\\m2' => ' m²', '\\m3' => ' m³', '\\s' => ' s', '\\min' => ' min', '\\h' => ' h',
        '\\leftrightarrow' => '↔', '\\mapsto' => '↦', '\\hookleftarrow' => '↩',
        '\\longleftarrow' => '←', '\\longrightarrow' => '→', '\\longleftrightarrow' => '↔',
        '\\partial' => '∂', '\\nabla' => '∇', '\\int' => '∫', '\\sum' => '∑',
        '\\prod' => '∏', '\\forall' => '∀', '\\exists' => '∃', '\\neg' => '¬',
        '\\land' => '∧', '\\lor' => '∨', '\\subseteq' => '⊆', '\\supseteq' => '⊇',
        '\\notin' => '∉', '\\ldots' => '…', '\\ast' => '*', '\\star' => '★',
        '\\quad' => ' ', '\\qquad' => ' ', '\\,' => ' ', '\\;' => ' ',
        '\\:' => ' ', '\\!' => '', '\\ ' => ' ',
        '\\left' => '', '\\right' => '', '\\displaystyle' => '', '\\limits' => '',
        '\\%' => '%', '\\_' => '_', '\\&' => '&', '\\#' => '#', '\\$' => '',
        '\\{' => '{', '\\}' => '}',
    ];
    foreach ($symbols as $from => $to) {
        $latex = str_replace((string)$from, (string)$to, $latex);
    }

    // Anything still in LaTeX is dropped rather than shown raw.
    // A letter that was escaped in LaTeX (O\Obasanjo) is the letter itself.
    $latex = preg_replace('/\\\\([A-Za-z])/', '$1', $latex);
    $latex = preg_replace('/\\\\[a-zA-Z]+/', '', $latex);
    $latex = str_replace(['{', '}', '$'], '', $latex);

    // A bracket of the formula that was left over.
    $latex = str_replace(['\\(', '\\)', '\\[', '\\]'], '', $latex);

    $latex = preg_replace('/\s+/u', ' ', $latex);
    return trim((string)$latex);
}

/**
 * MathML written as plain text.
 *
 * Used when a formula has no LaTeX source to read.
 */
function study_mathml_to_text($math): string
{
    if ($math === null) return '';

    $doc = $math->ownerDocument;
    if ($doc === null) return study_node_text($math);
    $xp = new DOMXPath($doc);

    $out = '';
    foreach ($math->childNodes as $child) {
        if ($child->nodeType !== XML_ELEMENT_NODE) continue;
        $name = strtolower($child->localName);

        switch ($name) {
            case 'semantics':
                $out .= study_mathml_to_text($child);
                break;
            case 'mrow':
            case 'math':
                $out .= study_mathml_to_text($child);
                break;
            case 'mfrac':
                $num = $xp->query('./mn|./mi|./mrow', $child)->item(0);
                $den = $xp->query('./mn|./mi|./mrow', $child)->item(1);
                $out .= '(' . study_mathml_to_text($num) . '/' . study_mathml_to_text($den) . ')';
                break;
            case 'msup':
                $base = $xp->query('./mn|./mi|./mrow', $child)->item(0);
                $sup  = $xp->query('./mn|./mi|./mrow', $child)->item(1);
                $out .= study_mathml_to_text($base) . '^(' . study_mathml_to_text($sup) . ')';
                break;
            case 'msub':
                $base = $xp->query('./mn|./mi|./mrow', $child)->item(0);
                $sub  = $xp->query('./mn|./mi|./mrow', $child)->item(1);
                $out .= study_mathml_to_text($base) . '_(' . study_mathml_to_text($sub) . ')';
                break;
            case 'msubsup':
                $base = $xp->query('./mn|./mi|./mrow', $child)->item(0);
                $sub  = $xp->query('./mn|./mi|./mrow', $child)->item(1);
                $sup  = $xp->query('./mn|./mi|./mrow', $child)->item(2);
                $out .= study_mathml_to_text($base) . '_(' . study_mathml_to_text($sub) . ')'
                      . '^(' . study_mathml_to_text($sup) . ')';
                break;
            case 'msqrt':
                $out .= '√(' . study_mathml_to_text($child) . ')';
                break;
            case 'mtext':
                $out .= study_node_text($child);
                break;
            case 'annotation':
                break;      // the LaTeX source is read elsewhere
            default:
                $out .= study_mathml_to_text($child);
        }
    }

    $out = preg_replace('/\s+/u', ' ', $out);
    return trim((string)$out);
}
