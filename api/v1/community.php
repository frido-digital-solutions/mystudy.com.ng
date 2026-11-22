<?php
/**
 * The community tab, fully native: feed, single post with threaded comments,
 * posting, commenting, voting, reporting and deleting.
 *
 * Everything here calls the same Discussion/Gamification/Notification models
 * the website's api_discussion.php uses, so the app and the web cannot drift
 * apart on rules (XP amounts, vote toggling, notification wording, upload
 * limits). GET reads; POST acts.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Discussion.php';
require_once __DIR__ . '/../../models/Gamification.php';
require_once __DIR__ . '/../../models/Message.php';
require_once __DIR__ . '/../../models/Notification.php';

$user = api_auth();
$userId = (int)$user['id'];

$discussionModel = new Discussion($pdo);
$gamification = new Gamification($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    community_post($discussionModel, $gamification, $userId);
}

// A number in ?id= means "open one post"; anything else is the feed.
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    community_detail($discussionModel, $userId, $id);
}

community_feed($discussionModel, $userId);

/** Build one comment for a device, replies included. */
function community_comment_payload(array $c, int $userId): array {
    return [
        'id' => (int)$c['id'],
        'content' => (string)$c['content'],
        'image' => api_url($c['image_path'] ?? null),
        'author' => (string)($c['author_name'] ?? ''),
        'author_id' => (int)($c['user_id'] ?? 0),
        'avatar' => api_url($c['author_avatar'] ?? null),
        'author_level' => Gamification::getLevel((int)($c['author_points'] ?? 0)),
        'score' => (int)($c['score'] ?? 0),
        'upvotes' => (int)($c['upvotes'] ?? 0),
        'downvotes' => (int)($c['downvotes'] ?? 0),
        'my_vote' => (int)($c['user_vote'] ?? 0),
        'is_mine' => (int)($c['user_id'] ?? 0) === $userId,
        'reply_to_name' => $c['reply_to_name'] ?? null,
        'reply_to_user_id' => isset($c['reply_to_user_id']) ? (int)$c['reply_to_user_id'] : null,
        'created_at' => $c['created_at'] ?? null,
        'replies' => array_map(function ($r) use ($userId) {
            return community_comment_payload($r, $userId);
        }, $c['replies'] ?? []),
    ];
}

/** One posted question with its whole comment tree. */
function community_detail(Discussion $discussionModel, int $userId, int $id): void {
    $post = $discussionModel->getDiscussionById($id, $userId);
    if (!$post) {
        api_err('not_found', 'That discussion no longer exists.', 404);
    }

    $discussionModel->incrementViews($id);

    $sort = api_field($_GET, 'comment_sort');
    $commentSort = in_array($sort, ['newest', 'oldest', 'replies'], true) ? $sort : 'score';
    $comments = $discussionModel->getCommentsThreadedSorted($id, $userId, $commentSort);

    // The website's header counts every reply too, not just top-level rows.
    $stmt = $GLOBALS['pdo']->prepare('SELECT COUNT(*) FROM discussion_comments WHERE discussion_id = ? AND is_deleted = 0');
    $stmt->execute([$id]);
    $totalComments = (int)$stmt->fetchColumn();

    api_out([
        'ok' => true,
        'post' => [
            'id' => (int)$post['id'],
            'title' => (string)$post['title'],
            'content' => (string)$post['content'],
            'image' => api_url($post['image_path'] ?? null),
            'author' => (string)($post['author_name'] ?? ''),
            'author_id' => (int)($post['user_id'] ?? 0),
            'avatar' => api_url($post['author_avatar'] ?? null),
            'author_level' => Gamification::getLevel((int)($post['author_points'] ?? 0)),
            'level' => $post['level'] ?? null,
            'subject' => $post['subject'] ?? null,
            'views' => (int)($post['views'] ?? 0) + 1,
            'score' => (int)($post['score'] ?? 0),
            'upvotes' => (int)($post['upvotes'] ?? 0),
            'downvotes' => (int)($post['downvotes'] ?? 0),
            'my_vote' => (int)($post['user_vote'] ?? 0),
            'is_mine' => (int)$post['user_id'] === $userId,
            'created_at' => $post['created_at'] ?? null,
            'share_url' => api_url('discussion_view.php?id=' . (int)$post['id']),
        ],
        'comments' => array_map(function ($c) use ($userId) {
            return community_comment_payload($c, $userId);
        }, $comments),
        'total_comments' => $totalComments,
        'comment_sort' => $commentSort,
    ]);
}

/** The scrolling list of questions, with the sidebar metadata in one trip. */
function community_feed(Discussion $discussionModel, int $userId): void {
    $sort = api_field($_GET, 'sort');
    $sortBy = in_array($sort, ['newest', 'unanswered'], true) ? $sort : 'score';
    $level = api_field($_GET, 'level');
    $levelParam = in_array($level, ['Secondary', 'University'], true) ? $level : null;
    $subject = api_field($_GET, 'subject');
    $q = api_field($_GET, 'q');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 15;

    $rows = $discussionModel->getDiscussions($limit, ($page - 1) * $limit, $sortBy, $levelParam, $subject ?: null, $q, $userId);

    // The phone's list needs to know when to stop asking for pages.
    $countWhere = [];
    $countParams = [];
    if ($levelParam) {
        $countWhere[] = 'd.level = ?';
        $countParams[] = $levelParam;
    }
    if ($subject) {
        $countWhere[] = 'd.subject = ?';
        $countParams[] = $subject;
    }
    if ($q !== '') {
        $countWhere[] = '(d.title LIKE ? OR d.content LIKE ?)';
        $countParams[] = "%$q%";
        $countParams[] = "%$q%";
    }
    if ($sortBy === 'unanswered') {
        $countWhere[] = '(SELECT COUNT(*) FROM discussion_comments WHERE discussion_id = d.id AND is_deleted = 0) = 0';
    }
    $stmt = $GLOBALS['pdo']->prepare('SELECT COUNT(*) FROM discussions d' . ($countWhere ? ' WHERE ' . implode(' AND ', $countWhere) : ''));
    $stmt->execute($countParams);
    $total = (int)$stmt->fetchColumn();

    $messageModel = new Message($GLOBALS['pdo']);
    $unreadMessages = 0;
    try {
        $messageModel->ensureTables();
        $unreadMessages = $messageModel->getUnreadTotal($userId);
    } catch (Throwable $e) {
        error_log('api community messages: ' . $e->getMessage());
    }

    $unreadNotifications = 0;
    try {
        $unreadNotifications = (new Notification($GLOBALS['pdo']))->getUnreadCount($userId);
    } catch (Throwable $e) {
        error_log('api community notifications: ' . $e->getMessage());
    }

    api_out([
        'ok' => true,
        'items' => array_map(function ($d) {
            return [
                'id' => (int)$d['id'],
                'title' => (string)$d['title'],
                'excerpt' => mb_strimwidth(strip_tags((string)$d['content']), 0, 180, '…'),
                'image' => api_url($d['image_path'] ?? null),
                'author' => (string)($d['author_name'] ?? ''),
                'author_id' => (int)($d['user_id'] ?? 0),
                'avatar' => api_url($d['author_avatar'] ?? null),
                'author_level' => Gamification::getLevel((int)($d['author_points'] ?? 0)),
                'level' => $d['level'] ?? null,
                'subject' => $d['subject'] ?? null,
                'views' => (int)($d['views'] ?? 0),
                'score' => (int)($d['score'] ?? 0),
                'upvotes' => (int)($d['upvotes'] ?? 0),
                'downvotes' => (int)($d['downvotes'] ?? 0),
                'comments' => (int)($d['comment_count'] ?? 0),
                'my_vote' => (int)($d['user_vote'] ?? 0),
                'created_at' => $d['created_at'] ?? null,
            ];
        }, $rows),
        'tags' => $discussionModel->getTrendingTags(),
        'page' => $page,
        'total' => $total,
        'unread_messages' => $unreadMessages,
        'unread_notifications' => $unreadNotifications,
    ]);
}

/**
 * Same image rules as the website's handleDiscussionUpload(): 1MB, the real
 * content type sniffed from bytes (never the client's claim), image extensions
 * only, and a server-made filename.
 */
function community_upload(string $field): ?string {
    $file = $_FILES[$field] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        api_err('upload_failed', 'The image could not be uploaded. Please try again.', 400);
    }
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        api_err('upload_failed', 'That was not a real upload.', 400);
    }
    if ((int)($file['size'] ?? 0) > 1048576 || filesize($file['tmp_name']) > 1048576) {
        api_err('upload_too_large', 'Image exceeds maximum allowed size of 1MB.', 400);
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $mime = mime_content_type($file['tmp_name']);
    if (!is_string($mime) || !isset($allowed[$mime])) {
        api_err('bad_image', 'Invalid image format. Only JPG, PNG, GIF, and WEBP are allowed.', 400);
    }

    $uploadDir = dirname(__DIR__, 2) . '/uploads/discussions/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        api_err('upload_failed', 'Failed to save the uploaded image.', 500);
    }

    $filename = uniqid('disc_', true) . '_' . time() . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        api_err('upload_failed', 'Failed to save the uploaded image.', 500);
    }
    return 'uploads/discussions/' . $filename;
}

/** Every write the community can do, mirroring api_discussion.php action for action. */
function community_post(Discussion $discussionModel, Gamification $gamification, int $userId): void {
    global $pdo;

    $in = api_input();
    $action = api_field($in, 'action');
    $userName = $_SESSION['user_name'] ?? 'A student';
    $notification = new Notification($pdo);

    switch ($action) {
        case 'create_discussion': {
            $title = api_field($in, 'title');
            $content = api_field($in, 'content');
            $level = api_field($in, 'level', 'General');
            $subject = api_field($in, 'subject');

            if ($title === '' || $content === '') {
                api_err('validation', 'Title and details are both required.');
            }
            if (mb_strlen($title) > 250) {
                api_err('validation', 'Keep the title under 250 characters.');
            }
            if (!in_array($level, ['General', 'University', 'Secondary'], true)) {
                $level = 'General';
            }
            if (mb_strlen($subject) > 100) {
                $subject = mb_substr($subject, 0, 100);
            }

            $imagePath = community_upload('image');
            $discussionId = $discussionModel->createDiscussion($userId, $title, $content, $imagePath, $level, $subject ?: null);
            $gamification->addXP($userId, 5);

            api_out(['ok' => true, 'id' => $discussionId, 'xp_earned' => 5, 'message' => 'Posted to the community.']);
        }

        case 'create_comment': {
            $discussionId = (int)($in['discussion_id'] ?? 0);
            $content = api_field($in, 'content');
            $parentId = !empty($in['parent_id']) ? (int)$in['parent_id'] : null;
            $replyToUserId = !empty($in['reply_to_user_id']) ? (int)$in['reply_to_user_id'] : null;

            if ($discussionId <= 0 || $content === '') {
                api_err('validation', 'A comment needs something to say.');
            }
            $discussion = $discussionModel->getDiscussionById($discussionId);
            if (!$discussion) {
                api_err('not_found', 'That discussion no longer exists.', 404);
            }
            // A reply can only attach to a comment on the same discussion; the
            // phone could otherwise strand a reply under a foreign or deleted id.
            if ($parentId) {
                $parent = $discussionModel->getCommentById($parentId);
                if (!$parent || (int)$parent['discussion_id'] !== $discussionId) {
                    api_err('validation', 'The comment you were replying to is gone.');
                }
            }

            $imagePath = community_upload('image');
            $commentId = $discussionModel->addComment($discussionId, $userId, $content, $imagePath, $parentId, $replyToUserId);

            $link = SITE_URL . '/discussion_view.php?id=' . $discussionId . '#comment-' . $commentId;
            $titleTrimmed = mb_strimwidth($discussion['title'], 0, 40, '...');

            if ($parentId && $replyToUserId && $replyToUserId !== $userId) {
                $notification->create($replyToUserId, 'discussion_reply', 'New Reply', "$userName replied to your comment in '$titleTrimmed'.", $link);
            } elseif (!$parentId && (int)$discussion['user_id'] !== $userId) {
                $notification->create((int)$discussion['user_id'], 'discussion_comment', 'New Comment', "$userName commented on your discussion '$titleTrimmed'.", $link);
            }

            api_out(['ok' => true, 'id' => $commentId, 'message' => 'Comment posted.']);
        }

        case 'vote': {
            $targetType = api_field($in, 'target_type');
            $targetId = (int)($in['target_id'] ?? 0);
            $voteValue = (int)($in['vote_value'] ?? 0);

            if (!in_array($targetType, ['discussion', 'comment'], true) || $targetId <= 0 || !in_array($voteValue, [-1, 0, 1], true)) {
                api_err('validation', 'Invalid vote.');
            }

            $counts = $discussionModel->vote($userId, $targetType, $targetId, $voteValue);

            if ($voteValue === 1) {
                $authorId = $discussionModel->getAuthorIdByTarget($targetType, $targetId);
                if ($authorId && $authorId !== $userId) {
                    $gamification->addXP($authorId, 1);
                }
            }

            api_out(['ok' => true, 'upvotes' => (int)$counts['upvotes'], 'downvotes' => (int)$counts['downvotes'], 'score' => (int)$counts['score']]);
        }

        case 'report': {
            $targetType = api_field($in, 'target_type');
            $targetId = (int)($in['target_id'] ?? 0);
            $reason = api_field($in, 'reason');

            if (!in_array($targetType, ['discussion', 'comment'], true) || $targetId <= 0 || $reason === '') {
                api_err('validation', 'Pick a reason so moderators know what to look at.');
            }

            // The same rule as the website: your own post is not something you
            // report to the moderators.
            if ($discussionModel->getAuthorIdByTarget($targetType, $targetId) === $userId) {
                api_err('own_content', 'You cannot report your own content.');
            }

            $discussionModel->report($userId, $targetType, $targetId, $reason);
            api_out(['ok' => true, 'message' => 'Report submitted. Our moderators will review it.']);
        }

        case 'delete_comment': {
            $commentId = (int)($in['comment_id'] ?? 0);
            if ($commentId <= 0) {
                api_err('validation', 'Invalid comment.');
            }
            // The model scopes the delete to the caller's own rows; a foreign id
            // simply removes nothing.
            $ok = $discussionModel->softDeleteComment($commentId, $userId, false);
            api_out(['ok' => (bool)$ok, 'message' => $ok ? 'Comment deleted.' : 'Could not delete that comment.']);
        }

        case 'delete_discussion': {
            $discussionId = (int)($in['discussion_id'] ?? 0);
            if ($discussionId <= 0) {
                api_err('validation', 'Invalid discussion.');
            }
            $ok = $discussionModel->deleteDiscussion($discussionId, $userId, false);
            api_out(['ok' => (bool)$ok, 'message' => $ok ? 'Discussion deleted.' : 'Could not delete that discussion.']);
        }

        default:
            api_err('unknown_action', 'The app asked for something the community does not do.');
    }
}
