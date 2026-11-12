<?php
/**
 * YouTube Video Info AJAX Endpoint
 * 
 * Supports both single and bulk URL fetching.
 * Single: GET ?url=<youtube_url>
 * Bulk:   GET ?urls=<json_array_of_urls>
 * 
 * Fallback chain per URL:
 *  1. YouTube oEmbed (title + thumbnail, most reliable)
 *  2. noembed.com (free oEmbed proxy, title + thumbnail)
 *  3. YouTube Data API v3 (if YOUTUBE_API_KEY configured — full details)
 *  4. HTML scraping of watch page (last resort)
 *  5. Thumbnail from img.youtube.com (always works)
 */
require_once __DIR__ . '/../../config/config.php';
require_admin();
require_once __DIR__ . '/../../models/Tutorial.php';

header('Content-Type: application/json; charset=utf-8');

if (!admin_can('tutorials')) {
    echo json_encode(['ok' => false, 'error' => 'Permission denied']);
    exit;
}

/**
 * Perform an HTTP GET request returning JSON.
 */
function yt_http_json(string $url): ?array {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json, text/html, */*',
                'Accept-Language: en-US,en;q=0.9',
            ],
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp !== false && $httpCode === 200) {
            $data = json_decode($resp, true);
            return is_array($data) ? $data : null;
        }
    }
    // Fallback: file_get_contents
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => 8,
            'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\nAccept: application/json\r\n",
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return null;
    $data = json_decode($resp, true);
    return is_array($data) ? $data : null;
}

/**
 * Perform an HTTP GET request returning raw HTML.
 */
function yt_http_text(string $url): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ],
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp !== false && $httpCode === 200) {
            return ($resp !== '') ? $resp : null;
        }
    }
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => 8,
            'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\nAccept: text/html\r\nAccept-Language: en-US,en;q=0.9\r\n",
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    return ($resp !== false && $resp !== '') ? $resp : null;
}

/**
 * Fetch info for a single YouTube video URL.
 * Returns associative array with: ok, youtube_id, title, description, thumbnail_url, duration_text
 */
function fetch_video_info(string $url): array {
    $url = trim($url);
    if ($url === '') {
        return ['ok' => false, 'error' => 'Empty URL', 'url' => ''];
    }

    $ytid = Tutorial::extractYoutubeId($url);
    if (!$ytid) {
        return ['ok' => false, 'error' => 'Invalid YouTube URL', 'url' => $url];
    }

    $out = [
        'ok'            => true,
        'url'           => $url,
        'youtube_id'    => $ytid,
        'title'         => null,
        'description'   => null,
        'thumbnail_url' => 'https://img.youtube.com/vi/' . $ytid . '/hqdefault.jpg',
        'duration_text' => null,
    ];

    // === Strategy 1: YouTube oEmbed ===
    $canonicalUrl = 'https://www.youtube.com/watch?v=' . $ytid;
    $oembedUrl = 'https://www.youtube.com/oembed?url=' . rawurlencode($canonicalUrl) . '&format=json';
    $oembed = yt_http_json($oembedUrl);
    if ($oembed) {
        if (!empty($oembed['title'])) $out['title'] = $oembed['title'];
        if (!empty($oembed['thumbnail_url'])) $out['thumbnail_url'] = $oembed['thumbnail_url'];
    }

    // === Strategy 2: noembed.com (free oEmbed proxy) ===
    if (empty($out['title'])) {
        $noembedUrl = 'https://noembed.com/embed?url=' . rawurlencode($canonicalUrl);
        $noembed = yt_http_json($noembedUrl);
        if ($noembed && empty($noembed['error'])) {
            if (!empty($noembed['title'])) $out['title'] = $noembed['title'];
            if (empty($out['thumbnail_url']) && !empty($noembed['thumbnail_url'])) {
                $out['thumbnail_url'] = $noembed['thumbnail_url'];
            }
        }
    }

    // === Strategy 3: YouTube Data API v3 (if key configured) ===
    if (defined('YOUTUBE_API_KEY') && YOUTUBE_API_KEY) {
        $apiUrl = 'https://www.googleapis.com/youtube/v3/videos?part=contentDetails,snippet&id=' 
                . urlencode($ytid) . '&key=' . urlencode(YOUTUBE_API_KEY);
        $yt = yt_http_json($apiUrl);
        if ($yt && !empty($yt['items'][0])) {
            $item = $yt['items'][0];
            if (!empty($item['snippet']['description'])) {
                $out['description'] = $item['snippet']['description'];
            }
            if (!empty($item['contentDetails']['duration'])) {
                try {
                    $spec = new DateInterval($item['contentDetails']['duration']);
                    $h = $spec->h + $spec->d * 24;
                    $m = $spec->i;
                    $s = $spec->s;
                    $out['duration_text'] = ($h ? $h . 'h ' : '') . ($m ? $m . 'm ' : '') . ($s ? $s . 's' : '');
                } catch (Throwable $e) {}
            }
            if (empty($out['title']) && !empty($item['snippet']['title'])) {
                $out['title'] = $item['snippet']['title'];
            }
            if (!empty($item['snippet']['thumbnails']['high']['url'])) {
                $out['thumbnail_url'] = $item['snippet']['thumbnails']['high']['url'];
            }
        }
    }

    // === Strategy 4: HTML Scraping (last resort for description) ===
    if (empty($out['title']) || empty($out['description'])) {
        $html = yt_http_text('https://www.youtube.com/watch?v=' . urlencode($ytid));
        if ($html) {
            // Try og:title
            if (empty($out['title'])) {
                if (preg_match('~<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']~i', $html, $m)
                    || preg_match('~<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:title["\']~i', $html, $m)) {
                    $out['title'] = html_entity_decode($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }
            }
            // Try og:description
            if (empty($out['description'])) {
                if (preg_match('~<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\']~i', $html, $m)
                    || preg_match('~<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:description["\']~i', $html, $m)) {
                    $out['description'] = html_entity_decode($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }
            }
            // Try <title> tag
            if (empty($out['title']) && preg_match('~<title>([^<]+)</title>~i', $html, $m)) {
                $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $title = preg_replace('~\s*[-–—]\s*YouTube\s*$~i', '', $title);
                if ($title !== '') $out['title'] = $title;
            }
        }
    }

    // Ensure we always have at least a basic title
    if (empty($out['title'])) {
        $out['title'] = 'YouTube Video ' . $ytid;
    }

    return $out;
}

try {
    // === Bulk mode: ?urls=[...] ===
    if (isset($_GET['urls'])) {
        $urlsRaw = $_GET['urls'];
        $urls = json_decode($urlsRaw, true);
        if (!is_array($urls)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid URLs array']);
            exit;
        }
        // Limit to 20 URLs per request
        $urls = array_slice($urls, 0, 20);
        $results = [];
        foreach ($urls as $u) {
            $results[] = fetch_video_info((string)$u);
        }
        echo json_encode(['ok' => true, 'results' => $results]);
        exit;
    }

    // === Single mode: ?url=... ===
    $url = isset($_GET['url']) ? trim((string)$_GET['url']) : '';
    if ($url === '') {
        echo json_encode(['ok' => false, 'error' => 'Missing YouTube URL']);
        exit;
    }

    $result = fetch_video_info($url);
    echo json_encode($result);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
