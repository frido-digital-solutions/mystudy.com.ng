<?php
/**
 * Mentor's two outside tools: the web, and the model.
 *
 * These two live in their own file for one reason: they can be tested on their
 * own, without a database and without a signed-in student. A question like
 * "when does JAMB 2027 registration open" is a question about the world, not
 * about this app, and the only honest answer to it is one that was looked up.
 *
 * The search is DuckDuckGo's own HTML page - no key, no account, no quota, and
 * it cannot be switched off by somebody's credit card running out. The page is
 * read as text: the title, the line underneath it and the address behind it.
 * That is all an answer needs, and all the model is given, so the model has
 * nothing to invent beyond the facts it was handed.
 */

/**
 * The model that runs on the free allowance.
 *
 * Kept as a constant here because the fallback file and the endpoint both need
 * to name it, and a model that is spelled two different ways in two files is a
 * model that will one day be paid for in one place and free in the other.
 */
if (!defined('MENTOR_MODEL')) {
    define('MENTOR_MODEL', '@cf/meta/llama-3.1-8b-instruct');
}

/**
 * Look something up.
 *
 * @param string $query what to look for
 * @param int $limit how many results are enough
 * @return array<int, array{title:string, snippet:string, url:string}>
 */
function mentor_web_search(string $query, int $limit = 4): array
{
    $query = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
    if ($query === '') return [];

    $q = rawurlencode($query);
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

    // Two shapes of the same search engine. The first is the plain one; the
    // second is the fuller page, which the first one sometimes refuses to serve
    // (it answers a robot now and then). Either one is enough.
    $attempts = [
        ['https://lite.duckduckgo.com/lite/?q=' . $q, 'result-link', 'result-snippet'],
        ['https://html.duckduckgo.com/html/?q=' . $q, 'result__a', 'result__snippet'],
    ];

    foreach ($attempts as $attempt) {
        $html = mentor_fetch($attempt[0], $ua, 12);
        if (!is_string($html) || $html === '') continue;

        $parsed = mentor_parse_results($html, $attempt[1], $attempt[2], $limit);
        if ($parsed) return $parsed;
    }

    return [];
}

/**
 * Read the results out of the search engine's page.
 *
 * The address in the page is a redirect that hides the real one, so it is
 * unwrapped here rather than being shown to the student.
 *
 * @return array<int, array{title:string, snippet:string, url:string}>
 */
function mentor_parse_results(string $html, string $titleClass, string $snippetClass, int $limit): array
{
    $titles = [];
    $links = [];

    $pattern = '/<a\b[^>]*class=["\'][^"\']*' . preg_quote($titleClass, '/') . '[^"\']*["\'][^>]*>(.*?)<\/a>/is';
    if (preg_match_all($pattern, $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $title = mentor_plain($hit[1]);
            if ($title === '') continue;
            $href = '';
            if (preg_match('/href=["\']([^"\']+)["\']/i', $hit[0], $h)) $href = $h[1];
            $titles[] = $title;
            $links[] = mentor_real_url($href);
        }
    }

    if (!$titles) return [];

    $snippets = [];
    $sPattern = '/<(?:td|div|a)\b[^>]*class=["\'][^"\']*' . preg_quote($snippetClass, '/') . '[^"\']*["\'][^>]*>(.*?)<\/(?:td|div|a)>/is';
    if (preg_match_all($sPattern, $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $snippets[] = mentor_plain($hit[1]);
        }
    }

    $out = [];
    foreach ($titles as $i => $title) {
        if (count($out) >= $limit) break;
        $snippet = $snippets[$i] ?? '';
        if ($snippet === '') {
            // The snippet of one result is not always where it is expected, so
            // the first snippet that has not been used yet is taken instead of
            // leaving the result bare.
            foreach ($snippets as $s) {
                if ($s !== '') { $snippet = $s; break; }
            }
        }
        $out[] = [
            'title' => $title,
            'snippet' => $snippet,
            'url' => $links[$i] ?? '',
        ];
    }

    return $out;
}

/**
 * The real address behind a search result.
 *
 * The page hands out addresses of the form
 * //duckduckgo.com/l/?uddg=https%3A%2F%2Fexample.com%2F&rut=...
 * and the part that matters is the encoded one.
 */
function mentor_real_url(string $href): string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES, 'UTF-8'));
    if ($href === '') return '';
    if (strpos($href, '//') === 0) $href = 'https:' . $href;
    if (preg_match('/[?&]uddg=([^&]+)/', $href, $m)) {
        $decoded = rawurldecode($m[1]);
        if ($decoded !== '') return $decoded;
    }
    return $href;
}

/**
 * A page as plain text: no tags, no entities, no shouting.
 */
function mentor_plain(string $html): string
{
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

/**
 * Fetch a page, with the same manners as the rest of this server's calls:
 * a deadline, and no fuss if it fails.
 */
function mentor_fetch(string $url, string $ua, int $timeout = 12): ?string
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: ' . $ua,
        'Accept: text/html,application/xhtml+xml',
        'Accept-Language: en-US,en;q=0.9',
        'Expect:',
    ]);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // HTTP/1.1 on purpose: the search engine's servers answer some clients
    // over HTTP/2 with an empty "are you a robot" page instead of results,
    // and PHP's curl speaks HTTP/2 by default. One line, and the page comes
    // back with the results in it.
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno !== 0 || !is_string($body) || $body === '') return null;
    return $body;
}

/**
 * One call to the model.
 *
 * The messages are the whole conversation. The tools are optional: when a tool
 * is offered the model may ask for a search, and the answer to that request is
 * given back to it as a normal message - the model then writes the reply in
 * its own words.
 *
 * @param array<int, array<string, mixed>> $messages
 * @param array<int, array<string, mixed>> $tools
 * @return array{text:?string, tool_query:?string, neurons:float}|null
 */
function mentor_model_call(array $messages, array $tools = [], int $maxTokens = 420): ?array
{
    if (!defined('CLOUDFLARE_ACCOUNT_ID') || !defined('CLOUDFLARE_AI_TOKEN')
        || CLOUDFLARE_ACCOUNT_ID === '' || CLOUDFLARE_AI_TOKEN === '') {
        return null;
    }

    $payload = [
        'messages' => $messages,
        'max_tokens' => $maxTokens,
        'temperature' => 0.2,
    ];
    if ($tools) $payload['tools'] = $tools;

    // The body has to be posted, not fetched, so the call is made here.
    $body = mentor_post_json(
        'https://api.cloudflare.com/client/v4/accounts/' . CLOUDFLARE_ACCOUNT_ID . '/ai/run/' . MENTOR_MODEL,
        json_encode($payload)
    );

    if (!is_string($body) || $body === '') return null;

    $json = json_decode($body, true);
    if (!is_array($json) || !($json['success'] ?? false)) {
        error_log('mentor ai refused: ' . substr((string)$body, 0, 300));
        return null;
    }

    $message = $json['result']['choices'][0]['message'] ?? [];
    $text = $message['content'] ?? null;

    // The model may ask for a search instead of answering.
    $query = null;
    $calls = $message['tool_calls'] ?? ($json['result']['tool_calls'] ?? []);
    if (is_array($calls) && isset($calls[0]['function']['arguments'])) {
        $args = json_decode((string)$calls[0]['function']['arguments'], true);
        if (is_array($args) && !empty($args['query'])) {
            $query = trim((string)$args['query']);
        }
    }

    return [
        'text' => is_string($text) ? $text : null,
        'tool_query' => $query,
        'neurons' => (float)($json['result']['usage']['neurons'] ?? 0),
    ];
}

/**
 * POST a JSON body and return what came back.
 */
function mentor_post_json(string $url, string $json): ?string
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . CLOUDFLARE_AI_TOKEN,
        'Content-Type: application/json',
        'Expect:',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno !== 0) {
        error_log('mentor ai call failed: errno=' . $errno);
        return null;
    }
    return is_string($body) ? $body : null;
}

/**
 * The results as one block of text for the model to read.
 */
function mentor_search_block(array $results): string
{
    $lines = [];
    $i = 0;
    foreach ($results as $r) {
        $i++;
        $lines[] = '[' . $i . '] ' . $r['title']
            . ($r['snippet'] !== '' ? "\n" . $r['snippet'] : '')
            . ($r['url'] !== '' ? "\n" . $r['url'] : '');
    }
    return implode("\n\n", $lines);
}
