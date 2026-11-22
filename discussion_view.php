<?php
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Discussion.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Gamification.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    set_flash_message('error', 'Invalid discussion link.');
    redirect(SITE_URL . '/discussions.php');
}

$discussionModel = new Discussion($pdo);
$userId = (int)$_SESSION['user_id'];
$discussion = $discussionModel->getDiscussionById($id, $userId);

if (!$discussion) {
    set_flash_message('error', 'Discussion not found or deleted.');
    redirect(SITE_URL . '/discussions.php');
}

// Track views
$discussionModel->incrementViews($id);

// Load tree
$commentSort = $_GET['comment_sort'] ?? 'score'; // score, newest, oldest, replies
$comments = $discussionModel->getCommentsThreadedSorted($id, $userId, $commentSort);

$page_title = htmlspecialchars($discussion['title']);
require_once __DIR__ . '/includes/header.php';
?>

<style>
    body { background-color: #f8fafc; }
    
    .thread-container {
        max-width: 900px;
        margin: 2rem auto;
        padding: 0 1rem;
    }
    
    /* Post Header & Breadcrumb */
    .breadcrumb-nav {
        margin-bottom: 1.5rem;
        font-weight: 600;
        font-size: 0.95rem;
    }
    .breadcrumb-nav a { color: #64748b; text-decoration: none; }
    .breadcrumb-nav a:hover { color: #4f46e5; }
    
    .main-post-card {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: white;
        border-radius: 16px;
        padding: 2rem;
        border: 1px solid #334155;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
        margin-bottom: 2rem;
    }
    
    .thread-title {
        font-size: 2rem;
        font-weight: 800;
        color: #ffffff;
        line-height: 1.3;
        margin-bottom: 1.5rem;
    }
    
    .author-meta {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .author-avatar {
        width: 48px !important;
        height: 48px !important;
        min-width: 48px !important;
        max-width: 48px !important;
        flex: 0 0 48px !important;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .author-details { display: flex; flex-direction: column; }
    .author-name { font-weight: 800; color: #f8fafc; font-size: 1.1rem; }
    .author-lvl {
        background: #d1fae5; color: #10b981; font-weight: 700; font-size: 0.75rem;
        padding: 2px 8px; border-radius: 50px; display: inline-block; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px;
    }
    
    .post-time { color: #94a3b8; font-size: 0.9rem; margin-left: auto; }
    
    .post-content {
        font-size: 1.1rem;
        color: #f1f5f9;
        line-height: 1.8;
        margin-bottom: 2rem;
    }
    
    .post-image {
        width: 100%;
        max-width: 100%;
        height: auto;
        max-height: 500px;
        object-fit: contain;
        background: rgba(0, 0, 0, 0.3);
        border-radius: 12px;
        margin-top: 1rem;
        border: 1px solid rgba(255, 255, 255, 0.1);
        display: block;
    }
    
    @media (max-width: 768px) {
        .post-image {
            max-height: 400px;
        }
    }
    
    @media (max-width: 480px) {
        .post-image {
            max-height: 300px;
        }
    }
    
    /* Voting Footer */
    .post-actions {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    
    .vote-box {
        display: inline-flex;
        align-items: center;
        background: #f1f5f9;
        border-radius: 50px;
        border: 1px solid #cbd5e1;
        overflow: hidden;
    }
    
    .vote-btn {
        background: transparent;
        border: none;
        padding: 0.6rem 1.25rem;
        color: #64748b;
        font-size: 1.1rem;
        transition: all 0.2s;
    }
    
    .vote-btn:hover { background: #e2e8f0; color: #0f172a; }
    .vote-btn.active-up { color: #f59e0b; font-weight: 900; }
    .vote-btn.active-down { color: #ef4444; font-weight: 900; }
    
    .vote-count {
        font-weight: 800;
        font-size: 1.1rem;
        min-width: 30px;
        text-align: center;
        color: #0f172a;
    }
    
    .voting-sm {
        background: #f8fafc;
    }
    
    .voting-sm .vote-btn {
        padding: 0.5rem 0.85rem;
        font-size: 0.95rem;
    }
    
    .voting-sm .vote-count {
        font-size: 1rem;
        padding: 0 0.5rem;
    }
    
    .action-btn {
        color: #64748b; background: transparent; border: none; font-weight: 600; font-size: 1rem;
        display: flex; align-items: center; gap: 0.5rem; transition: color 0.2s;
    }
    .action-btn:hover { color: #ef4444; } /* specific for report */
    
    /* Ensure all images stay within their containers */
    .post-content img,
    .comment-text img,
    .comment-image,
    .post-image {
        max-width: 100% !important;
        height: auto !important;
        box-sizing: border-box;
    }
    
    /* Responsive image containers */
    @media (max-width: 576px) {
        .thread-container {
            padding: 0 0.5rem;
        }
        .main-post-card {
            padding: 1.25rem;
        }
        .post-actions {
            flex-wrap: wrap;
        }
        .vote-btn {
            padding: 0.5rem 0.75rem;
            font-size: 0.95rem;
        }
        .vote-count {
            font-size: 1rem;
            min-width: 20px;
        }
        
        .voting-sm .vote-btn {
            padding: 0.4rem 0.6rem;
            font-size: 0.85rem;
        }
        .voting-sm .vote-btn span {
            display: none; /* Hide 'Helpful/Unhelpful' text on tiny screens for nested comments to save space */
        }
        .voting-sm .vote-count {
            font-size: 0.9rem;
            padding: 0 0.4rem;
        }
        
        .main-post-card .vote-btn span {
            font-size: 0.9rem; /* Keep text on main post but slightly smaller */
        }
    }
    
    /* Dark Theme override for Main Post Card */
    .main-post-card .vote-box {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .main-post-card .vote-btn { color: #cbd5e1; }
    .main-post-card .vote-btn:hover { background: rgba(255, 255, 255, 0.1); color: #ffffff; }
    .main-post-card .vote-btn.active-up { color: #f59e0b !important; }
    .main-post-card .vote-btn.active-down { color: #ef4444 !important; }
    .main-post-card .vote-count { color: #ffffff; }
    .main-post-card .action-btn { color: #cbd5e1; }
    .main-post-card .action-btn:hover { color: #ffffff; }
    .main-post-card .action-btn.text-danger { color: #fca5a5 !important; }
    .main-post-card .action-btn.text-danger:hover { color: #ef4444 !important; }

    /* Comment Filter Section */
    .comment-filter-section {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    .comment-filter-tabs {
        display: flex;
        gap: 0.5rem;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 0;
        flex-wrap: wrap;
    }
    
    .filter-tab {
        color: #64748b;
        font-weight: 600;
        text-decoration: none;
        padding: 0.5rem 1rem;
        border-radius: 8px 8px 0 0;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        border: none;
        background: transparent;
        cursor: pointer;
        position: relative;
        top: 2px;
    }
    
    .filter-tab:hover {
        background-color: #f1f5f9;
        color: #0f172a;
    }
    
    .filter-tab.active {
        background-color: #e0e7ff;
        color: #4f46e5;
        border-bottom: 2px solid #4f46e5;
        margin-bottom: -2px;
    }
    
    @media (max-width: 576px) {
        .comment-filter-section {
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
        }
        .comment-filter-tabs {
            gap: 0.25rem;
        }
        .filter-tab {
            padding: 0.4rem 0.75rem;
            font-size: 0.85rem;
        }
    }
    
    /* Comments Section */
    .comments-header {
        font-size: 1.5rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    /* The Reply Box */
    .reply-box {
        background: #ffffff;
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid #e2e8f0;
        margin-bottom: 2.5rem;
        position: relative;
    }
    
    .reply-box textarea {
        width: 100%;
        border: none;
        resize: none;
        background: #f8fafc;
        border-radius: 8px;
        padding: 1rem;
        font-size: 1rem;
        min-height: 120px;
        margin-bottom: 1rem;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
    }
    .reply-box textarea:focus { outline: none; background: #ffffff; border: 1px solid #4f46e5; }
    
    .reply-box-footer { display: flex; justify-content: space-between; align-items: center; }
    .file-input-wrapper {
        position: relative;
        overflow: hidden;
        display: inline-block;
    }
    .file-input-wrapper input[type=file] {
        font-size: 100px; position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer;
    }
    .btn-attach {
        color: #64748b; background: #f1f5f9; border: none; padding: 0.5rem 1rem; border-radius: 50px; font-weight: 600; transition: 0.2s;
    }
    .btn-attach:hover { background: #e2e8f0; color: #0f172a; }
    
    /* Comments Tree - Facebook 2Level Style */
    .comment-thread {
        margin-bottom: 0.75rem;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    }
    .comment-card {
        background: transparent;
        border-radius: 0;
        padding: 0;
        margin-bottom: 0;
    }
    .comment-author-row {
        display: flex; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.5rem;
    }
    .comment-avatar { 
        width: 36px !important; 
        height: 36px !important; 
        min-width: 36px !important; 
        max-width: 36px !important;
        flex: 0 0 36px !important; 
        border-radius: 50%; 
        object-fit: cover; 
    }
    .comment-author-name { font-weight: 700; color: #1e293b; font-size: 1rem; }
    .comment-author-lvl { font-size: 0.75rem; color: #10b981; font-weight: 600; }
    .comment-time { color: #94a3b8; font-size: 0.85rem; margin-left: auto; }
    
    .comment-body {
        margin-left: 48px; /* Indent body past avatar */
    }
    
    .comment-text {
        font-size: 1rem; color: #334155; line-height: 1.6;
        background: #f1f5f9; padding: 0.75rem 1rem; border-radius: 12px; border-top-left-radius: 4px; display: inline-block;
        word-break: break-word; /* Ensure long links don't stretch layout */
    }
    .comment-image {
        display: block; 
        max-width: 100%;
        width: 100%;
        height: auto;
        max-height: 300px; 
        border-radius: 8px; 
        margin-top: 0.5rem; 
        border: 1px solid #e2e8f0;
        object-fit: contain;
    }
    
    @media (max-width: 768px) {
        .comment-image {
            max-height: 250px;
        }
    }
    
    @media (max-width: 480px) {
        .comment-image {
            max-height: 200px;
        }
    }
    
    .comment-actions {
        display: flex; gap: 1rem; align-items: center; margin-top: 0.5rem; font-size: 0.85rem; font-weight: 600; flex-wrap: wrap;
    }
    .comment-actions button { background: none; border: none; color: #64748b; padding: 0; transition: color 0.2s; }
    .comment-actions button:hover { color: #0f172a; }
    .btn-reply-action { color: #4f46e5 !important; }
    .btn-reply-action:hover { text-decoration: underline; }

    /* Share + deep-link highlight (scoped to this page) */
    .comment-share-btn {
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #64748b;
        padding: 4px 8px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s ease;
        cursor: pointer;
        white-space: nowrap;
    }
    .comment-share-btn:hover { color: #0f172a; background: #f8fafc; border-color: #cbd5e1; }
    .deep-link-highlight {
        box-shadow: 0 0 0 2px rgba(79,70,229,0.25), 0 18px 45px rgba(79,70,229,0.14);
        background: linear-gradient(135deg, rgba(79,70,229,0.06), rgba(16,185,129,0.05));
        border-radius: 16px;
    }
    .share-toast {
        position: fixed;
        left: 50%;
        bottom: 22px;
        transform: translateX(-50%);
        background: rgba(15,23,42,0.95);
        color: #e5e7eb;
        padding: 10px 14px;
        border-radius: 999px;
        font-weight: 800;
        font-size: 0.85rem;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.18s ease, transform 0.18s ease;
        z-index: 9999;
    }
    .share-toast.show { opacity: 1; transform: translateX(-50%) translateY(-4px); }
    
    /* Nested Replies Layer */
    .child-replies {
        margin-left: 24px;
        margin-top: 1rem;
        padding-top: 0.5rem;
        padding-left: 1.5rem;
        border-left: 3px solid #cbd5e1;
    }
    .child-comment-avatar { 
        width: 28px !important; 
        height: 28px !important; 
        min-width: 28px !important; 
        max-width: 28px !important;
        flex: 0 0 28px !important; 
    }
    .child-body { margin-left: 40px; }
    
    @media (max-width: 576px) {
        .child-replies {
            margin-left: 8px;
            padding-left: 0.75rem;
        }
        .child-body {
            margin-left: 32px;
        }
        .comment-body {
            margin-left: 40px;
        }
    }
    
    .reply-tag {
        color: #4f46e5; font-weight: 700; background: #e0e7ff; padding: 2px 6px; border-radius: 4px; font-size: 0.85rem; margin-right: 4px;
    }
    
    /* Inline reply form template */
    .inline-reply-form { margin-top: 1rem; display: none; }
    .inline-reply-form.active { display: block; }

    /* Collapsible master reply */
    .btn-toggle-comment {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.65rem 1.5rem;
        border-radius: 50px;
        border: 2px solid #4f46e5;
        background: #fff;
        color: #4f46e5;
        font-weight: 700;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-toggle-comment:hover {
        background: #4f46e5;
        color: #fff;
    }
    .reply-box.collapsed { display: none; }
</style>

<div class="thread-container flex-grow-1">
    
    <div class="breadcrumb-nav">
        <a href="discussions.php"><i class="fa-solid fa-arrow-left me-2"></i>Back to Discussions</a>
    </div>

    <!-- MAIN POST -->
    <div class="main-post-card">
        <h1 class="thread-title"><?= htmlspecialchars($discussion['title']) ?></h1>
        
        <div class="author-meta">
            <a href="profile_public.php?id=<?= $discussion['user_id'] ?>" class="text-decoration-none d-flex align-items-center gap-3">
                <img src="<?= !empty($discussion['author_avatar']) ? htmlspecialchars($discussion['author_avatar']) : 'assets/img/default_avatar.png' ?>" class="author-avatar" alt="Author">
                <div class="author-details">
                    <span class="author-name text-white"><?= htmlspecialchars($discussion['author_name']) ?></span>
                    <span class="author-lvl"><i class="fa-solid fa-star me-1"></i> Level <?= Gamification::getLevel((int)$discussion['author_points']) ?></span>
                </div>
            </a>
            <div class="post-time">
                <i class="fa-regular fa-clock me-1"></i> <?= date('F j, Y, g:i a', strtotime($discussion['created_at'])) ?>
            </div>
        </div>
        
        <div class="post-content">
            <?= nl2br(htmlspecialchars($discussion['content'])) ?>
            
            <?php if (!empty($discussion['image_path'])): ?>
                <a href="<?= SITE_URL . '/' . htmlspecialchars($discussion['image_path']) ?>" target="_blank">
                    <img src="<?= SITE_URL . '/' . htmlspecialchars($discussion['image_path']) ?>" alt="Attached image" class="post-image">
                </a>
            <?php endif; ?>
        </div>
        
        <div class="post-actions">
            <div class="vote-box">
                <!-- User Vote logic applied via PHP to classes -->
                <?php $uv = (int)$discussion['user_vote']; ?>
                <button class="vote-btn <?= $uv === 1 ? 'active-up' : '' ?>" id="btn-up-disc-<?= $id ?>" onclick="vote('discussion', <?= $id ?>, 1)">
                    <i class="fa-solid fa-thumbs-up"></i>
                </button>
                <span class="vote-count" id="score-disc-<?= $id ?>"><?= (int)$discussion['score'] ?></span>
                <button class="vote-btn <?= $uv === -1 ? 'active-down' : '' ?>" id="btn-down-disc-<?= $id ?>" onclick="vote('discussion', <?= $id ?>, -1)">
                    <i class="fa-solid fa-thumbs-down"></i>
                </button>
            </div>
            
            <?php if ((int)$discussion['user_id'] !== $userId): ?>
                <button class="action-btn ms-auto" onclick="reportModal('discussion', <?= $id ?>)">
                    <i class="fa-solid fa-flag"></i> Report
                </button>
            <?php endif; ?>
            <?php if ((int)$discussion['user_id'] === $userId): ?>
                <button class="action-btn text-danger ms-auto" onclick="deleteThread(<?= $id ?>)">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- COMMENT FILTER SECTION -->
    <div class="comment-filter-section">
        <div class="comment-filter-tabs">
            <a href="?id=<?= $id ?>&comment_sort=score" class="filter-tab <?= $commentSort === 'score' ? 'active' : '' ?>">
                <i class="fa-solid fa-fire"></i> Most Voted
            </a>
            <a href="?id=<?= $id ?>&comment_sort=newest" class="filter-tab <?= $commentSort === 'newest' ? 'active' : '' ?>">
                <i class="fa-solid fa-clock"></i> Newest
            </a>
            <a href="?id=<?= $id ?>&comment_sort=oldest" class="filter-tab <?= $commentSort === 'oldest' ? 'active' : '' ?>">
                <i class="fa-solid fa-history"></i> Oldest
            </a>
            <a href="?id=<?= $id ?>&comment_sort=replies" class="filter-tab <?= $commentSort === 'replies' ? 'active' : '' ?>">
                <i class="fa-solid fa-comments"></i> Most Replied
            </a>
        </div>
    </div>

    <!-- COMMENTS SECTION -->
    <h3 class="comments-header"><i class="fa-solid fa-comments text-primary"></i> <?= count($comments) + array_reduce($comments, function($c, $item) { return $c + count($item['replies']); }, 0) ?> Comments</h3>

    <!-- Toggle Comment Button -->
    <button type="button" class="btn-toggle-comment mb-3" id="btnToggleComment" onclick="toggleMasterReply()">
        <i class="fa-solid fa-pen-to-square"></i> Write a Comment
    </button>

    <!-- Master Reply Box (hidden by default) -->
    <div class="reply-box collapsed" id="masterReplyBox">
        <form id="masterReplyForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_comment">
            <input type="hidden" name="discussion_id" value="<?= $id ?>">
            <textarea name="content" placeholder="Write an answer... Help out the community!" required></textarea>
            
            <div class="reply-box-footer">
                <div class="file-input-wrapper">
                    <button type="button" class="btn-attach"><i class="fa-solid fa-image me-2"></i>Attach Image</button>
                    <input type="file" name="image" accept="image/jpeg, image/png, image/gif, image/webp" onchange="this.previousElementSibling.innerHTML='<i class=\'fa-solid fa-check text-success\'></i> Image Selected'">
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light fw-bold px-3 rounded-pill" onclick="toggleMasterReply()">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4 rounded-pill" id="btnSubmitMaster">Post Answer</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Comments Tree -->
    <div class="comments-tree" id="commentsWrapper">
        <?php foreach ($comments as $comment): 
            $isTopDeleted = (int)$comment['is_deleted'] === 1;
        ?>
            <!-- Top Level Comment -->
            <div class="comment-thread" id="comment-<?= $comment['id'] ?>">
                <div class="comment-card">
                    <div class="comment-author-row mb-1 flex-nowrap">
                        <a href="profile_public.php?id=<?= $comment['user_id'] ?>" class="text-decoration-none">
                            <img src="<?= !empty($comment['author_avatar']) ? htmlspecialchars($comment['author_avatar']) : 'assets/img/default_avatar.png' ?>" class="comment-avatar">
                        </a>
                        <a href="profile_public.php?id=<?= $comment['user_id'] ?>" class="text-decoration-none text-dark text-truncate" style="max-width: 120px;">
                            <span class="comment-author-name mb-0"><?= htmlspecialchars($comment['author_name']) ?></span>
                        </a>
                        <span class="comment-author-lvl ms-1"><i class="fa-solid fa-star text-warning"></i> <?= Gamification::getLevel((int)$comment['author_points']) ?></span>
                        <span class="comment-time ms-auto text-nowrap"><?= date('M j, Y, g:ia', strtotime($comment['created_at'])) ?></span>
                    </div>
                    <div class="comment-body">
                        <div class="comment-text <?= $isTopDeleted ? 'text-danger font-italic bg-light' : '' ?>">
                            <?= nl2br(htmlspecialchars($comment['content'])) ?>
                        </div>
                        <?php if (!$isTopDeleted && !empty($comment['image_path'])): ?>
                            <a href="<?= SITE_URL . '/' . htmlspecialchars($comment['image_path']) ?>" target="_blank">
                                <img src="<?= SITE_URL . '/' . htmlspecialchars($comment['image_path']) ?>" class="comment-image">
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!$isTopDeleted): ?>
                            <div class="comment-actions mt-2">
                                <?php $cv = (int)$comment['user_vote']; ?>
                                <div class="vote-box voting-sm me-2">
                                    <button class="vote-btn <?= $cv === 1 ? 'active-up' : '' ?>" id="btn-up-comm-<?= $comment['id'] ?>" onclick="vote('comment', <?= $comment['id'] ?>, 1)"><i class="fa-solid fa-arrow-up"></i> <span class="ms-1 fw-bold fs-6">Helpful</span></button>
                                    <span class="vote-count px-3" id="score-comm-<?= $comment['id'] ?>"><?= (int)$comment['score'] ?></span>
                                    <button class="vote-btn <?= $cv === -1 ? 'active-down' : '' ?>" id="btn-down-comm-<?= $comment['id'] ?>" onclick="vote('comment', <?= $comment['id'] ?>, -1)"><i class="fa-solid fa-arrow-down"></i> <span class="ms-1 fw-bold fs-6">Unhelpful</span></button>
                                </div>
                                
                                <button class="btn-reply-action ms-2" onclick="toggleReplyForm(<?= $comment['id'] ?>, <?= $comment['id'] ?>, '<?= htmlspecialchars(addslashes($comment['author_name'])) ?>', <?= $comment['user_id'] ?>)"><i class="fa-solid fa-reply me-1"></i> Reply</button>
                                <?php if ((int)$comment['user_id'] !== $userId): ?>
                                    <button class="ms-3" onclick="reportModal('comment', <?= $comment['id'] ?>)"><i class="fa-regular fa-flag me-1"></i> Report</button>
                                <?php endif; ?>
                                <button class="comment-share-btn ms-3" type="button" onclick="shareThreadLink('#comment-<?= $comment['id'] ?>'); return false;">
                                    <i class="fa-solid fa-share-nodes"></i> Share
                                </button>
                                
                                <?php if ((int)$comment['user_id'] === $userId): ?>
                                    <button class="text-danger ms-3" onclick="deleteComment(<?= $comment['id'] ?>)"><i class="fa-solid fa-trash me-1"></i> Delete</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Child Replies -->
                <?php if (!empty($comment['replies'])): ?>
                    <div class="child-replies">
                        <?php foreach ($comment['replies'] as $reply): 
                            $isChildDeleted = (int)$reply['is_deleted'] === 1;
                        ?>
                            <div class="comment-card mt-3" id="comment-<?= $reply['id'] ?>">
                                <div class="comment-author-row mb-1 flex-nowrap">
                                    <a href="profile_public.php?id=<?= $reply['user_id'] ?>" class="text-decoration-none">
                                        <img src="<?= !empty($reply['author_avatar']) ? htmlspecialchars($reply['author_avatar']) : 'assets/img/default_avatar.png' ?>" class="comment-avatar child-comment-avatar">
                                    </a>
                                    <a href="profile_public.php?id=<?= $reply['user_id'] ?>" class="text-decoration-none text-dark text-truncate" style="max-width: 100px;">
                                        <span class="comment-author-name mb-0"><?= htmlspecialchars($reply['author_name']) ?></span>
                                    </a>
                                    <span class="comment-author-lvl ms-1"><i class="fa-solid fa-star text-warning"></i> <?= Gamification::getLevel((int)$reply['author_points']) ?></span>
                                    <span class="comment-time ms-auto text-nowrap"><?= date('M j, g:ia', strtotime($reply['created_at'])) ?></span>
                                </div>
                                <div class="comment-body child-body">
                                    <div class="comment-text <?= $isChildDeleted ? 'text-danger font-italic bg-light' : '' ?>">
                                        <?php if (!$isChildDeleted && !empty($reply['reply_to_name'])): ?>
                                            <span class="reply-tag">@<?= htmlspecialchars($reply['reply_to_name']) ?></span>
                                        <?php endif; ?>
                                        <?= nl2br(htmlspecialchars($reply['content'])) ?>
                                    </div>
                                    <?php if (!$isChildDeleted && !empty($reply['image_path'])): ?>
                                        <a href="<?= SITE_URL . '/' . htmlspecialchars($reply['image_path']) ?>" target="_blank"><img src="<?= SITE_URL . '/' . htmlspecialchars($reply['image_path']) ?>" class="comment-image" style="max-height: 200px;"></a>
                                    <?php endif; ?>
                                    
                                    <?php if (!$isChildDeleted): ?>
                                        <div class="comment-actions mt-2">
                                            <button id="btn-up-comm-<?= $reply['id'] ?>" onclick="vote('comment', <?= $reply['id'] ?>, 1)" class="action-btn <?= ((int)$reply['user_vote'] === 1) ? 'active-up' : '' ?>">
                                                <i class="fa-solid fa-thumbs-up me-1"></i> <span id="score-comm-<?= $reply['id'] ?>"><?= (int)$reply['score'] ?></span> Like
                                            </button>
                                            
                                            <span class="ms-2 me-2 text-muted fw-normal">|</span>
                                            
                                            <button class="btn-reply-action" onclick="toggleReplyForm(<?= $comment['id'] ?>, <?= $reply['id'] ?>, '<?= htmlspecialchars(addslashes($reply['author_name'])) ?>', <?= $reply['user_id'] ?>)">Reply</button>
                                            
                                            <?php if ((int)$reply['user_id'] !== $userId): ?>
                                                <button class="ms-2" onclick="reportModal('comment', <?= $reply['id'] ?>)">Report</button>
                                            <?php endif; ?>
                                            <button class="comment-share-btn ms-2" type="button" onclick="shareThreadLink('#comment-<?= $reply['id'] ?>'); return false;">
                                                <i class="fa-solid fa-share-nodes"></i> Share
                                            </button>

                                            <?php if ((int)$reply['user_id'] === $userId): ?>
                                                <button class="text-danger ms-2" onclick="deleteComment(<?= $reply['id'] ?>)">Delete</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Inline Reply Form Container -->
                <div class="inline-reply-form" id="reply-form-container-<?= $comment['id'] ?>">
                    <div class="child-replies mt-2 border-0">
                        <form class="dynamic-reply-form" onsubmit="submitReply(event, <?= $comment['id'] ?>)" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="create_comment">
                            <input type="hidden" name="discussion_id" value="<?= $id ?>">
                            <input type="hidden" name="parent_id" value="<?= $comment['id'] ?>">
                            <input type="hidden" name="reply_to_user_id" id="reply-to-user-<?= $comment['id'] ?>" value="">
                            
                            <textarea name="content" id="reply-textarea-<?= $comment['id'] ?>" class="form-control bg-light mb-2" rows="3" required placeholder="Write a reply..."></textarea>
                            <div class="d-flex justify-content-between">
                                <input type="file" name="image" class="form-control form-control-sm w-50" accept="image/jpeg, image/png, image/webp">
                                <div>
                                    <button type="button" class="btn btn-sm btn-light me-2" onclick="document.getElementById('reply-form-container-<?= $comment['id'] ?>').classList.remove('active')">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-primary fw-bold">Post Reply</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="share-toast" id="shareToastThread">Link copied</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    (function(){
        var toast = document.getElementById('shareToastThread');
        function showToast(t){
            if (!toast) return;
            toast.textContent = t;
            toast.classList.add('show');
            clearTimeout(showToast._t);
            showToast._t = setTimeout(function(){ toast.classList.remove('show'); }, 1800);
        }
        async function copyText(text){
            try { await navigator.clipboard.writeText(text); return true; }
            catch(e){
                try {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    return true;
                } catch(e2){ return false; }
            }
        }

        window.shareThreadLink = async function(hash){
            var base = window.location.href.split('#')[0];
            var url = base + (hash || '');
            if (navigator.share) {
                try { await navigator.share({ title: document.title, url: url }); return; } catch(e) {}
            }
            var ok = await copyText(url);
            if (ok) showToast('Link copied');
        };

        function highlightFromHash(){
            if (!window.location.hash) return;
            var id = window.location.hash.slice(1);
            if (!id) return;
            var el = document.getElementById(id);
            if (!el) return;
            // Highlight the nearest visible card
            var target = el.classList.contains('comment-card') ? el : (el.querySelector('.comment-card') || el);
            target.classList.add('deep-link-highlight');
            try { target.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch(e) {}
            setTimeout(function(){ target.classList.remove('deep-link-highlight'); }, 2600);
        }

        document.addEventListener('DOMContentLoaded', function(){
            setTimeout(highlightFromHash, 250);
            
            // Auto-scroll to comments section when filter is clicked
            const filterTabs = document.querySelectorAll('.filter-tab');
            filterTabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    // Store current scroll position to avoid page jump
                    sessionStorage.setItem('scrollToComments', 'true');
                });
            });
            
            // Check if we need to scroll to comments on page load
            if (sessionStorage.getItem('scrollToComments') === 'true') {
                sessionStorage.removeItem('scrollToComments');
                setTimeout(() => {
                    const commentsSection = document.querySelector('.comment-filter-section');
                    if (commentsSection) {
                        commentsSection.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start' 
                        });
                    }
                }, 100);
            }
        });
    })();

    // Vote Handler
    function vote(type, id, val) {
        const formData = new FormData();
        formData.append('action', 'vote');
        formData.append('target_type', type);
        formData.append('target_id', id);
        formData.append('vote_value', val);

        fetch('<?= SITE_URL ?>/api_discussion.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const prefix = type === 'discussion' ? 'disc' : 'comm';
                document.getElementById('score-' + prefix + '-' + id).textContent = data.counts.score;
                
                // Toggle active classes
                const btnUp = document.getElementById('btn-up-' + prefix + '-' + id);
                const btnDown = document.getElementById('btn-down-' + prefix + '-' + id);
                
                if (val === 1) {
                    if (btnUp) {
                        if (btnUp.classList.contains('active-up')) {
                            btnUp.classList.remove('active-up');
                        } else {
                            btnUp.classList.add('active-up');
                            if (btnDown) btnDown.classList.remove('active-down');
                        }
                    }
                } else if (val === -1) {
                    if (btnDown) {
                        if (btnDown.classList.contains('active-down')) {
                            btnDown.classList.remove('active-down');
                        } else {
                            btnDown.classList.add('active-down');
                            if (btnUp) btnUp.classList.remove('active-up');
                        }
                    }
                }
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        });
    }

    // Report Handler
    function reportModal(type, id) {
        Swal.fire({
            title: 'Report Content',
            html: `
                <div class="text-start mb-3">
                    <label class="form-label fw-bold">Select Reason</label>
                    <select id="reportReasonSelect" class="form-select bg-light mb-2">
                        <option value="Spam / Advertising">Spam / Advertising</option>
                        <option value="Harassment / Hate Speech">Harassment / Hate Speech</option>
                        <option value="Inappropriate Content">Inappropriate Content</option>
                        <option value="Plagiarism">Plagiarism</option>
                        <option value="Custom">Other (Custom Reason)</option>
                    </select>
                    <input type="text" id="reportReasonCustom" class="form-control bg-light d-none" placeholder="Please specify your custom reason...">
                </div>
                <div class="alert alert-warning text-start mb-0" style="font-size: 0.85rem;">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> 
                    <strong>Warning:</strong> Submitting a false report can lead to account restrictions. Please ensure this report is valid before submitting.
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Submit Report',
            confirmButtonColor: '#ef4444',
            preConfirm: () => {
                const selectValue = document.getElementById('reportReasonSelect').value;
                const customValue = document.getElementById('reportReasonCustom').value.trim();
                const finalReason = selectValue === 'Custom' ? customValue : selectValue;
                
                if (selectValue === 'Custom' && !customValue) {
                    Swal.showValidationMessage('Please provide a custom reason.');
                    return false;
                }
                return finalReason;
            },
            didOpen: () => {
                const selectEl = document.getElementById('reportReasonSelect');
                const customInput = document.getElementById('reportReasonCustom');
                selectEl.addEventListener('change', (e) => {
                    if (e.target.value === 'Custom') {
                        customInput.classList.remove('d-none');
                        customInput.focus();
                    } else {
                        customInput.classList.add('d-none');
                    }
                });
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const formData = new FormData();
                formData.append('action', 'report');
                formData.append('target_type', type);
                formData.append('target_id', id);
                formData.append('reason', result.value);
                
                fetch('<?= SITE_URL ?>/api_discussion.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => {
                    if (d.success) Swal.fire('Reported', d.message, 'success');
                    else Swal.fire('Error', d.message, 'error');
                });
            }
        });
    }

    // Toggle Reply Form
    function toggleReplyForm(parentId, targetCommentId, authorName, targetUserId) {
        // Hide all active forms first to keep UI clean
        document.querySelectorAll('.inline-reply-form').forEach(el => el.classList.remove('active'));
        
        let container = document.getElementById('reply-form-container-' + parentId);
        container.classList.add('active');
        
        let ta = document.getElementById('reply-textarea-' + parentId);
        let uidField = document.getElementById('reply-to-user-' + parentId);
        
        // If not replying strictly to the top level comment, we tag the user
        if (parentId !== targetCommentId) {
            uidField.value = targetUserId;
            ta.placeholder = "Replying to @" + authorName + "...";
        } else {
            uidField.value = targetUserId;
            ta.placeholder = "Write a reply to " + authorName + "...";
        }
        ta.focus();
    }

    // Toggle master reply box
    function toggleMasterReply() {
        var box = document.getElementById('masterReplyBox');
        var btn = document.getElementById('btnToggleComment');
        if (box.classList.contains('collapsed')) {
            box.classList.remove('collapsed');
            btn.style.display = 'none';
            box.querySelector('textarea').focus();
        } else {
            box.classList.add('collapsed');
            btn.style.display = '';
        }
    }

    // Submit Master Answer
    document.getElementById('masterReplyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitMaster');
        btn.disabled = true;
        btn.innerHTML = 'Posting...';
        
        fetch('<?= SITE_URL ?>/api_discussion.php', { method: 'POST', body: new FormData(this) })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                // Hide the reply box before reload
                document.getElementById('masterReplyBox').classList.add('collapsed');
                document.getElementById('btnToggleComment').style.display = '';
                window.location.reload();
            } else {
                Swal.fire('Error', data.message, 'error');
                btn.disabled = false;
                btn.innerHTML = 'Post Answer';
            }
        });
    });

    // Submit Inline Nested Reply
    function submitReply(e, parentId) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '...';
        
        fetch('<?= SITE_URL ?>/api_discussion.php', { method: 'POST', body: new FormData(form) })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                window.location.reload(); // Simple refresh to load the tree securely. We can do DOM injection but a refresh ensures exact ranking logic is preserved.
            } else {
                Swal.fire('Error', data.message, 'error');
                btn.disabled = false;
                btn.innerHTML = 'Post Reply';
            }
        });
    }

    // Delete handlers
    function deleteComment(id) {
        Swal.fire({
            title: 'Delete Comment?', text: "You cannot undo this.", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Yes, delete'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData(); fd.append('action', 'delete_comment'); fd.append('comment_id', id);
                fetch('<?= SITE_URL ?>/api_discussion.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d=>{
                    if(d.success) window.location.reload();
                });
            }
        });
    }

    function deleteThread(id) {
        Swal.fire({
            title: 'Delete Discussion?', text: "This will permanently remove the thread and all comments.", icon: 'danger',
            showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Yes, delete everything'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData(); fd.append('action', 'delete_discussion'); fd.append('discussion_id', id);
                fetch('<?= SITE_URL ?>/api_discussion.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d=>{
                    if(d.success) window.location.href = 'discussions.php';
                });
            }
        });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
