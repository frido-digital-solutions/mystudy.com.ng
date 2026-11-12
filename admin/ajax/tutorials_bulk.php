<?php
/**
 * Bulk Tutorial Save AJAX Endpoint
 * 
 * Accepts a JSON POST body with an array of tutorial data and creates them all.
 * Request body: { "csrf_token": "...", "tutorials": [ { title, youtube_url, description, level, courses }, ... ] }
 * Response: { ok: true, results: [ { ok: true, id: 123 }, { ok: false, error: "..." }, ... ] }
 */
require_once __DIR__ . '/../../config/config.php';
require_admin();
require_once __DIR__ . '/../../models/Tutorial.php';

header('Content-Type: application/json; charset=utf-8');

if (!admin_can('tutorials')) {
    echo json_encode(['ok' => false, 'error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

// Read JSON body
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body) || empty($body['tutorials'])) {
    echo json_encode(['ok' => false, 'error' => 'Missing tutorials array']);
    exit;
}

// CSRF check
if (empty($body['csrf_token']) || !validate_csrf_token($body['csrf_token'])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$tutorialModel = new Tutorial($pdo);
$tutorials = array_slice($body['tutorials'], 0, 20); // Max 20 at a time
$results = [];
$successCount = 0;

foreach ($tutorials as $t) {
    try {
        $title = trim($t['title'] ?? '');
        $ytUrl = trim($t['youtube_url'] ?? '');
        
        if ($title === '' || $ytUrl === '') {
            $results[] = ['ok' => false, 'error' => 'Missing title or URL', 'title' => $title];
            continue;
        }

        $data = [
            'title'         => $title,
            'slug'          => '',  // Auto-generate from title
            'description'   => trim($t['description'] ?? ''),
            'youtube_url'   => $ytUrl,
            'thumbnail_url' => $t['thumbnail_url'] ?? null,
            'level'         => (($t['level'] ?? 'University') === 'Secondary') ? 'Secondary' : 'University',
        ];

        // Parse courses (comma-separated string)
        $coursesRaw = trim($t['courses'] ?? '');
        $courses = array_values(array_filter(array_map('trim', explode(',', $coursesRaw))));

        $id = $tutorialModel->create($data, $courses);
        $results[] = ['ok' => true, 'id' => $id, 'title' => $title];
        $successCount++;
    } catch (Throwable $e) {
        $errorMsg = $e->getMessage();
        // Handle duplicate slug errors gracefully
        if (strpos($errorMsg, 'Duplicate entry') !== false) {
            // Try with a suffix
            try {
                $data['slug'] = $data['slug'] ?: Tutorial::slugify($title);
                $data['slug'] .= '-' . substr(md5(microtime()), 0, 6);
                $id = $tutorialModel->create($data, $courses);
                $results[] = ['ok' => true, 'id' => $id, 'title' => $title];
                $successCount++;
            } catch (Throwable $e2) {
                $results[] = ['ok' => false, 'error' => 'Save failed: ' . $e2->getMessage(), 'title' => $title];
            }
        } else {
            $results[] = ['ok' => false, 'error' => 'Save failed: ' . $errorMsg, 'title' => $title];
        }
    }
}

echo json_encode([
    'ok'           => true,
    'saved'        => $successCount,
    'total'        => count($tutorials),
    'results'      => $results,
]);
