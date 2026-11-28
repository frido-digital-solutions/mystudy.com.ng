<?php
/**
 * Shared helpers for the Study harvesters.
 *
 * A harvester does four things:
 *
 *   1. work out which pages hold which papers (from a sitemap or a list),
 *   2. fetch a page,
 *   3. pull the questions out of it,
 *   4. put them in the library, without ever writing the same question twice.
 *
 * Everything here is written so that a harvester can be stopped at any point
 * and started again: papers already in the library are skipped, so a run that
 * dies half way through loses nothing.
 */

require_once __DIR__ . '/study_math.php';

/**
 * The canonical subject for a source's own name of a subject.
 *
 * @param string $source  acadmate | smartest | studentfirst
 * @param string $slug    the subject as that source spells it
 * @return array{slug:string,name:string}|null
 */
function study_canonical_subject(string $source, string $slug): ?array
{
    static $registry = null;
    if ($registry === null) {
        $registry = require dirname(__DIR__, 2) . '/content/study_subjects.php';
    }

    $slug = strtolower(trim($slug));
    foreach ($registry as $canonical => $meta) {
        $aliases = $meta[$source] ?? [];
        if (in_array($slug, array_map('strtolower', $aliases), true)) {
            return ['slug' => $canonical, 'name' => $meta['name']];
        }
    }
    return null;
}

/**
 * A CA bundle that is still current.
 *
 * The one XAMPP ships with is from 2022 and no longer has the roots some
 * sites use, which makes every request to them fail with "unable to get
 * local issuer certificate". A fresh bundle is downloaded to .scratch/ and
 * used instead when it is there.
 */
function study_ca_bundle(): ?string
{
    static $path = null;
    if ($path !== null) return $path === '' ? null : $path;

    foreach ([
        dirname(__DIR__, 2) . '/.scratch/cacert.pem',
        '/c/Users/USER/Downloads/mystudybase.com.ng-main/.scratch/cacert.pem',
    ] as $candidate) {
        if (@is_file($candidate) && filesize($candidate) > 50000) {
            $path = $candidate;
            return $path;
        }
    }
    $path = '';
    return null;
}

/**
 * Fetch a URL and return the body.
 *
 * @param string $url
 * @param array  $opts  timeout, user agent
 * @return string|null  null when the page could not be read
 */
function study_fetch(string $url, array $opts = []): ?string
{
    $timeout = (int)($opts['timeout'] ?? 45);
    $ua      = $opts['user_agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
             . '(KHTML, like Gecko) Chrome/126.0 Safari/537.36 MyStudyBaseStudyHarvester/1.0';

    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
            'Accept-Language: en-NG,en;q=0.9',
        ],
    ];
    $ca = study_ca_bundle();
    if ($ca) $options[CURLOPT_CAINFO] = $ca;
    curl_setopt_array($ch, $options);

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $code < 200 || $code >= 300) {
        return null;
    }
    return (string)$body;
}

/**
 * Fetch a URL, trying again when the site lets us down.
 *
 * Some of the sites we read are shared hosts that drop a connection now and
 * then. A single failure would otherwise throw away a whole paper, so a
 * failed fetch is tried again a couple of times before it is given up on.
 *
 * @param string $url
 * @param array  $opts  timeout, user agent, tries
 * @return string|null  null when the page could not be read at all
 */
function study_fetch_retry(string $url, array $opts = []): ?string
{
    $tries = max(1, (int)($opts['tries'] ?? 3));
    for ($i = 1; $i <= $tries; $i++) {
        $body = study_fetch($url, $opts);
        if ($body !== null && trim($body) !== '') return $body;
        if ($i < $tries) usleep(600000);
    }
    return null;
}

/**
 * Fetch a lot of URLs at the same time.
 *
 * @param string[] $urls
 * @param array    $opts
 * @return array<string,string|null>  url => body
 */
function study_fetch_many(array $urls, array $opts = []): array
{
    $timeout = (int)($opts['timeout'] ?? 45);
    $ua      = $opts['user_agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
             . '(KHTML, like Gecko) Chrome/126.0 Safari/537.36 MyStudyBaseStudyHarvester/1.0';
    $max     = (int)($opts['parallel'] ?? 8);

    $multi   = curl_multi_init();
    $handles = [];
    $queue   = array_values($urls);

    while ($queue !== [] || $handles !== []) {
        while (count($handles) < $max && $queue !== []) {
            $url = array_shift($queue);
            $ch  = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_USERAGENT      => $ua,
                CURLOPT_ENCODING       => '',
                CURLOPT_HTTPHEADER     => [
                    'Accept: text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-NG,en;q=0.9',
                ],
            ];
            $ca = study_ca_bundle();
            if ($ca) $options[CURLOPT_CAINFO] = $ca;
            curl_setopt_array($ch, $options);
            curl_multi_add_handle($multi, $ch);
            $handles[$url] = $ch;
        }
        $running = null;
        do {
            curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running > 0);

        foreach ($handles as $url => $ch) {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $body = curl_multi_getcontent($ch);
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
            $results[$url] = ($body !== false && $code >= 200 && $code < 300) ? (string)$body : null;
        }
        $handles = [];
    }
    curl_multi_close($multi);

    return $results ?? [];
}

/**
 * Fetch a lot of URLs at the same time, trying again the ones that failed.
 *
 * A shared host will drop a request now and then, and a page that quietly
 * fails would leave a paper half written. So anything that comes back empty
 * is fetched again, one at a time and with a pause between attempts.
 *
 * @param string[] $urls
 * @param array    $opts  parallel, timeout, tries
 * @return array<string,string|null>  url => body
 */
function study_fetch_many_retry(array $urls, array $opts = []): array
{
    $tries = max(1, (int)($opts['tries'] ?? 3));
    $bodies = study_fetch_many($urls, $opts);

    $missing = [];
    foreach ($urls as $url) {
        if (($bodies[$url] ?? null) === null || trim((string)$bodies[$url]) === '') {
            $missing[] = $url;
        }
    }

    for ($round = 1; $round < $tries && $missing; $round++) {
        usleep(700000);
        foreach ($missing as $url) {
            $body = study_fetch($url, $opts);
            if ($body !== null && trim($body) !== '') {
                $bodies[$url] = $body;
            }
        }
        $still = [];
        foreach ($missing as $url) {
            if (!isset($bodies[$url]) || trim((string)$bodies[$url]) === '') {
                $still[] = $url;
            }
        }
        $missing = $still;
    }

    return $bodies;
}

/**
 * The text of a node, with the tags gone and the spacing kept sane.
 *
 * A formula on the page is written three times over - as MathML, as its
 * LaTeX source, and as a picture of the formula. Reading the page as it
 * stands gives all three at once, so a formula arrives as a mess. The
 * shared reader in cli/lib/study_math.php takes the formula out on its own
 * and writes it as plain text a student can read: "1/2", not "\frac{1}{2}".
 */
function study_node_text($node): string
{
    if ($node === null) return '';

    $plain = function ($n): string {
        $text = $n->textContent ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string)$text);
    };

    if (!is_object($node) || !isset($node->ownerDocument) || $node->ownerDocument === null) {
        return $plain($node);
    }

    $xp = new DOMXPath($node->ownerDocument);
    $maths = $xp->query(".//*[contains(@class,'katex') or self::math or @data-testid='react-katex' or contains(@class,'math-svg')]", $node);
    if ($maths->length) {
        return study_math_text($node);
    }

    // Some sources write the formula straight into the text, as LaTeX.
    $text = $node->textContent ?? '';
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim((string)$text);

    if (preg_match('/\\\\\(|\\\\\[|\\\\[a-zA-Z]+\{/', $text)) {
        $text = study_latex_to_text($text);
    }
    return $text;
}

/**
 * A DOMDocument for a page of HTML.
 */
function study_dom(string $html): ?DOMDocument
{
    if (trim($html) === '') return null;
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    return $doc;
}

/**
 * Put a paper into the library, or find the one already there.
 *
 * The paper is keyed by exam, subject, year, paper code and source, so the
 * same paper from two different sources is two papers, and the same paper
 * from the same source is one.
 *
 * @return int the paper id
 */
function study_upsert_paper(PDO $pdo, array $paper): int
{
    $sql = "
        INSERT INTO study_papers
            (exam, subject_slug, subject_name, year, paper_code, title, source, source_url, question_count)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            subject_name   = VALUES(subject_name),
            title          = VALUES(title),
            source_url     = VALUES(source_url),
            question_count = VALUES(question_count)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $paper['exam'],
        $paper['subject_slug'],
        $paper['subject_name'],
        (int)$paper['year'],
        $paper['paper_code'] ?? 'objective',
        $paper['title'],
        $paper['source'],
        $paper['source_url'] ?? null,
        (int)($paper['question_count'] ?? 0),
    ]);

    $find = $pdo->prepare("
        SELECT id FROM study_papers
        WHERE exam = ? AND subject_slug = ? AND year = ? AND paper_code = ? AND source = ?
    ");
    $find->execute([
        $paper['exam'],
        $paper['subject_slug'],
        (int)$paper['year'],
        $paper['paper_code'] ?? 'objective',
        $paper['source'],
    ]);
    return (int)$find->fetchColumn();
}

/**
 * Replace the questions of a paper with a fresh set.
 *
 * Replacing rather than adding means a re-run of the harvester fixes a
 * half-written paper instead of leaving the broken first attempt in place.
 */
function study_replace_questions(PDO $pdo, int $paperId, array $questions): int
{
    $pdo->prepare("DELETE FROM study_questions WHERE paper_id = ?")->execute([$paperId]);
    if (!$questions) return 0;

    $stmt = $pdo->prepare("
        INSERT INTO study_questions
            (paper_id, display_number, question, options_json, correct_option, explanation, topic, source_question_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $n = 0;
    $seen = [];
    foreach ($questions as $q) {
        $question = trim((string)($q['question'] ?? ''));
        if ($question === '') continue;

        // The same question listed twice in one paper is stored once.
        $fingerprint = md5(strtolower(preg_replace('/\s+/', ' ', $question)));
        if (isset($seen[$fingerprint])) continue;
        $seen[$fingerprint] = true;

        // An option must have a label and something to read.
        $options = [];
        foreach (($q['options'] ?? []) as $o) {
            $label = strtoupper(trim((string)($o['label'] ?? '')));
            $text  = trim((string)($o['text'] ?? ''));
            if ($label === '' || $text === '') continue;
            $options[] = ['label' => $label, 'text' => $text];
        }
        if (count($options) < 2) continue;

        // The answer must be one of the options that are actually there.
        $answer = strtoupper(trim((string)($q['correct_option'] ?? '')));
        $labels = array_column($options, 'label');
        if ($answer === '' || !in_array($answer, $labels, true)) continue;

        $stmt->execute([
            $paperId,
            (int)$q['display_number'],
            $question,
            json_encode(array_values($options), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $answer,
            $q['explanation'] ?? null,
            $q['topic'] ?? null,
            $q['source_question_id'] ?? null,
        ]);
        $n++;
    }

    // The paper must say how many questions it really holds, not how many
    // were offered to it.
    $pdo->prepare("UPDATE study_papers SET question_count = ? WHERE id = ?")->execute([$n, $paperId]);

    return $n;
}

/**
 * The papers already in the library for a source, as
 * "exam|subject|year|paper_code|source" keys. A harvester uses this to skip
 * work it has already done.
 *
 * The source is part of the key: the same paper from two different sources
 * is two papers, and one source must not stop another from being stored.
 */
function study_existing_paper_keys(PDO $pdo, string $source): array
{
    $stmt = $pdo->prepare("
        SELECT exam, subject_slug, year, paper_code FROM study_papers WHERE source = ?
    ");
    $stmt->execute([$source]);
    $keys = [];
    foreach ($stmt->fetchAll() as $row) {
        $keys[$row['exam'] . '|' . $row['subject_slug'] . '|' . $row['year'] . '|' . $row['paper_code'] . '|' . $source] = true;
    }
    return $keys;
}

/**
 * The key that identifies one paper.
 */
function study_paper_key(string $exam, string $subjectSlug, int $year, string $paperCode, string $source): string
{
    return $exam . '|' . $subjectSlug . '|' . $year . '|' . $paperCode . '|' . $source;
}

/**
 * Print a line that says what the harvester is doing.
 */
function study_say(string $message): void
{
    echo $message . PHP_EOL;
    @flush();
}
