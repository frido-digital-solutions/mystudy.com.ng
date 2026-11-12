<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Discussion.php';
require_once __DIR__ . '/models/Gamification.php';
require_once __DIR__ . '/models/Notification.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'A student';
$discussionModel = new Discussion($pdo);
$gamification = new Gamification($pdo);
$notification = new Notification($pdo);

// Helper function to handle 1MB max image uploads safely
function handleDiscussionUpload($fileArray) {
    if (empty($fileArray['name'])) return null;

    if (($fileArray['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception("Upload error. Please try again.");
    }

    if (empty($fileArray['tmp_name']) || !is_uploaded_file($fileArray['tmp_name'])) {
        throw new Exception("Invalid upload.");
    }

    // 1MB max
    if ($fileArray['size'] > 1048576 || filesize($fileArray['tmp_name']) > 1048576) {
        throw new Exception("Image exceeds maximum allowed size of 1MB.");
    }

    // Sniff the real file content; the client-supplied type/name are never trusted.
    $mime = mime_content_type($fileArray['tmp_name']);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    if (!is_string($mime) || !isset($allowedTypes[$mime])) {
        throw new Exception("Invalid image format. Only JPG, PNG, GIF, and WEBP are allowed.");
    }

    $uploadDir = __DIR__ . '/uploads/discussions/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new Exception("Failed to save the uploaded image.");
    }

    // Extension is derived from the verified MIME type so no script can ever be stored.
    $filename = uniqid('disc_', true) . '_' . time() . '.' . $allowedTypes[$mime];
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($fileArray['tmp_name'], $targetPath)) {
        return 'uploads/discussions/' . $filename;
    }

    throw new Exception("Failed to save the uploaded image.");
}

try {
    switch ($action) {
        case 'create_discussion':
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $level = $_POST['level'] ?? 'General';
            $subject = $_POST['subject'] ?? '';

            if (empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Title and content are required.']);
                exit;
            }

            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imagePath = handleDiscussionUpload($_FILES['image']);
            }

            $discussionId = $discussionModel->createDiscussion($userId, $title, $content, $imagePath, $level, $subject);
            
            // Gamification hook: +5 XP for posting
            $gamification->addXP($userId, 5);

            echo json_encode(['success' => true, 'id' => $discussionId, 'message' => 'Discussion posted successfully!']);
            break;

        case 'create_comment':
            $discussionId = (int)($_POST['discussion_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $replyToUserId = !empty($_POST['reply_to_user_id']) ? (int)$_POST['reply_to_user_id'] : null;

            if ($discussionId <= 0 || empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Discussion ID and content are required.']);
                exit;
            }

            $discussion = $discussionModel->getDiscussionById($discussionId);
            if (!$discussion) {
                echo json_encode(['success' => false, 'message' => 'Discussion not found.']);
                exit;
            }

            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imagePath = handleDiscussionUpload($_FILES['image']);
            }

            $commentId = $discussionModel->addComment($discussionId, $userId, $content, $imagePath, $parentId, $replyToUserId);

            // Notification hooks
            $link = SITE_URL . "/discussion_view.php?id=" . $discussionId . "#comment-" . $commentId;
            $titleTrimmed = mb_strimwidth($discussion['title'], 0, 40, '...');

            if ($parentId && $replyToUserId && $replyToUserId !== $userId) {
                // Reply to a specific user
                $msg = "$userName replied to your comment in '$titleTrimmed'.";
                $notification->create($replyToUserId, 'discussion_reply', 'New Reply', $msg, $link);
            } 
            elseif (empty($parentId) && (int)$discussion['user_id'] !== $userId) {
                // Top-level comment -> notify thread author
                $msg = "$userName commented on your discussion '$titleTrimmed'.";
                $notification->create((int)$discussion['user_id'], 'discussion_comment', 'New Comment', $msg, $link);
            }

            echo json_encode(['success' => true, 'message' => 'Comment posted!', 'comment_id' => $commentId]);
            break;

        case 'vote':
            $targetType = $_POST['target_type'] ?? '';
            $targetId = (int)($_POST['target_id'] ?? 0);
            $voteValue = (int)($_POST['vote_value'] ?? 0); // 1, 0, -1

            if (!in_array($targetType, ['discussion', 'comment']) || $targetId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                exit;
            }
            
            // Register vote
            $newCounts = $discussionModel->vote($userId, $targetType, $targetId, $voteValue);
            
            // Check if it's an upvote to reward the author
            if ($voteValue === 1) {
                $authorId = $discussionModel->getAuthorIdByTarget($targetType, $targetId);
                if ($authorId && $authorId !== $userId) {
                    $gamification->addXP($authorId, 1); // +1 XP per upvote
                }
            }

            echo json_encode(['success' => true, 'counts' => $newCounts]);
            break;

        case 'report':
            $targetType = $_POST['target_type'] ?? '';
            $targetId = (int)($_POST['target_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');

            if (!in_array($targetType, ['discussion', 'comment']) || $targetId <= 0 || empty($reason)) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters or missing reason.']);
                exit;
            }

            $discussionModel->report($userId, $targetType, $targetId, $reason);
            echo json_encode(['success' => true, 'message' => 'Report submitted successfully. Our moderators will review it.']);
            break;

        case 'delete_comment':
            $commentId = (int)($_POST['comment_id'] ?? 0);
            if ($commentId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid comment ID.']);
                exit;
            }
            // Soft delete user's own comment
            $success = $discussionModel->softDeleteComment($commentId, $userId, false);
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Comment deleted.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Could not delete comment.']);
            }
            break;
            
        case 'delete_discussion':
            $discussionId = (int)($_POST['discussion_id'] ?? 0);
            if ($discussionId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid discussion ID.']);
                exit;
            }
            $success = $discussionModel->deleteDiscussion($discussionId, $userId, false);
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Discussion deleted.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Could not delete discussion.']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
