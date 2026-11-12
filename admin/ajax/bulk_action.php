<?php
/**
 * Admin Bulk Operations AJAX Endpoint
 * Handles bulk delete/approve/reject across multiple admin content types.
 * 
 * POST JSON: { csrf_token, action: "delete"|"approve"|"reject", type: "discussion"|"quiz"|"tutorial"|"material"|"post", ids: [1,2,3] }
 */
require_once __DIR__ . '/../../config/config.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

// CSRF check
if (empty($body['csrf_token']) || !validate_csrf_token($body['csrf_token'])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$action = $body['action'] ?? '';
$type = $body['type'] ?? '';
$ids = $body['ids'] ?? [];

if (!is_array($ids) || empty($ids)) {
    echo json_encode(['ok' => false, 'error' => 'No items selected']);
    exit;
}

// Sanitize IDs
$ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
if (empty($ids)) {
    echo json_encode(['ok' => false, 'error' => 'No valid IDs']);
    exit;
}

// Limit to 100 at a time
$ids = array_slice($ids, 0, 100);

$success = 0;
$failed = 0;

try {
    switch ($type) {
        case 'discussion':
            if (!admin_can('discussions')) {
                echo json_encode(['ok' => false, 'error' => 'Permission denied']);
                exit;
            }
            if ($action === 'delete') {
                require_once __DIR__ . '/../../models/Discussion.php';
                $model = new Discussion($pdo);
                foreach ($ids as $id) {
                    try {
                        $model->deleteDiscussion($id, 0, true);
                        $success++;
                    } catch (Throwable $e) { $failed++; }
                }
            }
            break;
        
        case 'quiz':
            if (!admin_can('quizzes')) {
                echo json_encode(['ok' => false, 'error' => 'Permission denied']);
                exit;
            }
            if ($action === 'delete') {
                require_once __DIR__ . '/../../models/Quiz.php';
                $model = new QuizAdmin($pdo);
                foreach ($ids as $id) {
                    try {
                        $model->deleteQuiz($id);
                        $success++;
                    } catch (Throwable $e) { $failed++; }
                }
            }
            break;
        
        case 'tutorial':
            if (!admin_can('tutorials')) {
                echo json_encode(['ok' => false, 'error' => 'Permission denied']);
                exit;
            }
            if ($action === 'delete') {
                require_once __DIR__ . '/../../models/Tutorial.php';
                $model = new Tutorial($pdo);
                foreach ($ids as $id) {
                    try {
                        $model->delete($id);
                        $success++;
                    } catch (Throwable $e) { $failed++; }
                }
            }
            break;
        
        case 'material':
            if (!admin_can('materials')) {
                echo json_encode(['ok' => false, 'error' => 'Permission denied']);
                exit;
            }
            require_once __DIR__ . '/../../models/Material.php';
            $model = new Material($pdo);
            if ($action === 'delete') {
                foreach ($ids as $id) {
                    try {
                        $stmt = $pdo->prepare('DELETE FROM materials WHERE id = ?');
                        $stmt->execute([$id]);
                        $success++;
                    } catch (Throwable $e) { $failed++; }
                }
            } elseif ($action === 'approve' || $action === 'reject') {
                $status = ($action === 'approve') ? 'approved' : 'rejected';
                $reviewerId = (int)($_SESSION['user_id'] ?? 0);
                foreach ($ids as $id) {
                    try {
                        $model->updateStatus($id, $status, $reviewerId);
                        $success++;
                    } catch (Throwable $e) { $failed++; }
                }
            }
            break;
        
        case 'post':
            if (!admin_can('posts')) {
                echo json_encode(['ok' => false, 'error' => 'Permission denied']);
                exit;
            }
            if ($action === 'delete') {
                require_once __DIR__ . '/../../models/Post.php';
                $model = new PostAdmin($pdo);
                foreach ($ids as $id) {
                    try {
                        $model->delete($id);
                        $success++;
                    } catch (Throwable $e) { $failed++; }
                }
            }
            break;
        
        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown type: ' . $type]);
            exit;
    }

    // Log the action for activity tracking
    if ($success > 0) {
        $logAction = ucfirst($action) . ' ' . ucfirst($type) . ($success > 1 ? 's' : '');
        $logDetails = ucfirst($action) . 'd ' . $success . ' ' . $type . '(s). IDs: ' . implode(', ', array_slice($ids, 0, 20));
        log_admin_activity($pdo, $logAction, $logDetails);
    }

    echo json_encode([
        'ok' => true,
        'success' => $success,
        'failed' => $failed,
        'total' => count($ids),
    ]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
