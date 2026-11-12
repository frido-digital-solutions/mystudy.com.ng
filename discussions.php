<?php
require_once __DIR__ . '/config/config.php';
// No require_login() — guests can browse discussions
require_once __DIR__ . '/models/Discussion.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Gamification.php';

$discussionModel = new Discussion($pdo);

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$sortBy = $_GET['sort'] ?? 'score'; // score, newest, unanswered
$levelFilter = $_GET['level'] ?? 'All';
$subjectFilter = $_GET['subject'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');

$userId = is_logged_in() ? (int)$_SESSION['user_id'] : 0;
$discussions = $discussionModel->getDiscussions($limit, $offset, $sortBy, $levelFilter, $subjectFilter, $searchQuery, $userId);
$trendingTags = $discussionModel->getTrendingTags();

$page_title = 'Community Discussions';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Add Lightbox CSS for images if needed -->
<style>
    /* Premium Community Design */
    body {
        background-color: #f8fafc;
    }
    
    .forum-container {
        padding: 2rem 1rem;
        max-width: 1280px;
        margin: 0 auto;
        overflow-x: hidden;
    }

    .forum-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-radius: 16px;
        padding: 2.5rem 2rem;
        color: white;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        position: relative;
        overflow: hidden;
    }
    
    .forum-hero::before {
        content: '\f086';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        right: -20px;
        bottom: -40px;
        font-size: 15rem;
        opacity: 0.05;
        transform: rotate(-15deg);
        pointer-events: none;
    }

    .forum-hero-text h1 {
        font-weight: 800;
        font-size: 2.2rem;
        margin-bottom: 0.5rem;
    }

    .forum-hero-text p {
        color: #94a3b8;
        font-size: 1.1rem;
        margin-bottom: 0;
    }
    
    .btn-ask {
        background: #fbbf24;
        color: #0f172a;
        font-weight: 700;
        padding: 0.75rem 1.5rem;
        border-radius: 50px;
        border: none;
        box-shadow: 0 4px 6px -1px rgba(251, 191, 36, 0.4);
        transition: all 0.3s ease;
        font-size: 1.1rem;
    }

    .btn-ask:hover {
        background: #f59e0b;
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(251, 191, 36, 0.4);
    }

    /* Layout structure */
    .forum-layout {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 2rem;
    }
    
    @media (max-width: 991px) {
        .forum-layout {
            grid-template-columns: 1fr;
        }
        .forum-sidebar {
            order: -1; /* Sidebar search/rules above feed on mobile */
        }
    }
    
    @media (max-width: 767px) {
        .forum-layout {
            gap: 1rem;
        }
        .forum-sidebar {
            margin-top: 1rem;
        }
    }
    
    @media (max-width: 480px) {
        .forum-layout {
            gap: 0.75rem;
        }
    }

    @media (max-width: 768px) {
        .forum-container {
            padding: 0;
            overflow-x: hidden;
        }
        .forum-hero {
            border-radius: 0;
            padding: 1.5rem 1rem;
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        .forum-hero-text h1 {
            font-size: 1.8rem;
        }
        .forum-layout {
            padding: 0 1rem;
        }
        body { overflow-x: hidden; }
    }
    
    @media (max-width: 480px) {
        .forum-container {
            padding: 0;
        }
        .forum-hero {
            padding: 1rem 0.75rem;
            margin-bottom: 1rem;
        }
        .forum-hero-text h1 {
            font-size: 1.5rem;
        }
        .forum-hero-text p {
            font-size: 0.95rem;
        }
        .btn-ask {
            padding: 0.6rem 1.25rem;
            font-size: 0.95rem;
            width: 100%;
            justify-content: center;
        }
        .forum-layout {
            padding: 0 0.75rem;
        }
    }

    /* Feed Filters */
    .feed-filters {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 1rem;
        flex-wrap: wrap;
    }

    .filter-tab {
        color: #64748b;
        font-weight: 600;
        text-decoration: none;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .filter-tab:hover {
        background-color: #f1f5f9;
        color: #0f172a;
    }

    .filter-tab.active {
        background-color: #e0e7ff;
        color: #4f46e5;
    }

    /* Mobile: keep filters compact (no wasted vertical space) */
    @media (max-width: 576px) {
        .feed-filters { 
            gap: 0.75rem; 
            display: block; 
        }
        .filter-tab { 
            padding: 0.45rem 0.75rem; 
            font-size: 0.85rem; 
        }
    }
    
    @media (max-width: 480px) {
        .feed-filters {
            gap: 0.5rem;
        }
        .filter-tab {
            padding: 0.4rem 0.6rem;
            font-size: 0.8rem;
        }
        .scrolling-track {
            padding-bottom: 0.5rem;
        }
    }
    
    .scrolling-track {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none; /* Firefox */
    }
    .scrolling-track::-webkit-scrollbar {
        display: none; /* Chrome/Safari */
    }

    /* Share button (safe + minimal) */
    .share-btn {
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #64748b;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
        cursor: pointer;
    }
    .share-btn:hover { color: #0f172a; border-color: #cbd5e1; background: #f8fafc; }
    .share-toast {
        position: fixed;
        left: 50%;
        bottom: 22px;
        transform: translateX(-50%);
        background: rgba(15,23,42,0.95);
        color: #e5e7eb;
        padding: 10px 14px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 0.85rem;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.18s ease, transform 0.18s ease;
        z-index: 9999;
    }
    .share-toast.show { opacity: 1; transform: translateX(-50%) translateY(-4px); }

    /* Post Card */
    .post-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
        overflow: hidden;
    }
    
    @media (max-width: 480px) {
        .post-card {
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-radius: 8px;
        }
    }

    .post-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.08);
        border-color: #cbd5e1;
    }

    .post-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
        flex-wrap: wrap;
    }
    
    @media (max-width: 480px) {
        .post-header {
            gap: 0.5rem;
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }
    }

    .author-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
    }

    .author-name {
        font-weight: 700;
        color: #1e293b;
    }

    .author-level {
        color: #10b981;
        font-weight: 600;
        font-size: 0.8rem;
        background: #d1fae5;
        padding: 2px 6px;
        border-radius: 4px;
    }

    .post-time {
        color: #94a3b8;
    }

    .post-subject-tag {
        margin-left: auto;
        font-size: 0.75rem;
        font-weight: 700;
        color: #3b82f6;
        background: #eff6ff;
        padding: 4px 8px;
        border-radius: 50px;
    }

    .post-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.5rem;
        line-height: 1.4;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    
    @media (max-width: 480px) {
        .post-title {
            font-size: 1.1rem;
            line-height: 1.3;
        }
    }

    .post-excerpt {
        color: #475569;
        font-size: 0.95rem;
        line-height: 1.6;
        margin-bottom: 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .post-thumbnail {
        width: 100%;
        max-height: 250px;
        object-fit: cover;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .post-footer {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        border-top: 1px solid #f1f5f9;
        padding-top: 1rem;
        flex-wrap: wrap;
    }
    
    @media (max-width: 480px) {
        .post-footer {
            gap: 1rem;
            padding-top: 0.75rem;
        }
    }

    /* Reddit-style upvote chunk */
    .vote-box {
        display: inline-flex;
        align-items: center;
        background: #f1f5f9;
        border-radius: 50px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }

    .vote-btn {
        background: transparent;
        border: none;
        padding: 0.6rem 1.25rem;
        color: #64748b;
        transition: all 0.2s;
    }

    .vote-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    /* Used via JS toggles */
    .vote-btn.active-up { color: #f59e0b; font-weight: 900; }
    .vote-btn.active-down { color: #ef4444; font-weight: 900; }

    .vote-count {
        font-weight: 700;
        font-size: 0.95rem;
        min-width: 20px;
        text-align: center;
        color: #1e293b;
    }

    .action-btn {
        background: transparent;
        border: none;
        color: #64748b;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem;
        border-radius: 8px;
        transition: background 0.2s;
        text-decoration: none;
        font-size: 0.95rem;
        white-space: nowrap;
    }
    
    @media (max-width: 480px) {
        .action-btn {
            font-size: 0.85rem;
            padding: 0.4rem;
            gap: 0.4rem;
        }
        .action-btn i {
            font-size: 0.85rem;
        }
    }

    .action-btn:hover {
        background: #f1f5f9;
        color: #0f172a;
    }

    /* Sidebar blocks */
    .sidebar-block {
        background: #ffffff;
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid #e2e8f0;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    .sidebar-title {
        font-weight: 800;
        font-size: 1.1rem;
        color: #0f172a;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Mobile Accordion Styles */
    .sidebar-collapse-icon {
        display: none;
        transition: transform 0.3s ease;
    }
    .sidebar-collapse-btn {
        background: none;
        border: none;
        padding: 0;
        width: 100%;
        text-align: left;
        color: inherit;
    }
    .sidebar-collapse-btn:focus { outline: none; }
    .sidebar-collapse-btn[aria-expanded="true"] .sidebar-collapse-icon {
        transform: rotate(180deg);
    }

    @media (max-width: 991px) {
        .sidebar-collapse-icon {
            display: inline-block;
        }
        .mobile-collapse-body {
            display: none;
        }
        .mobile-collapse-body.show {
            display: block;
        }
        .sidebar-block {
            padding: 1.25rem;
            margin-bottom: 0.75rem;
            border-radius: 12px;
        }
        .sidebar-title {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }
        .mobile-collapse-body.show {
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 2px solid #f1f5f9;
        }
    }
    
    @media (min-width: 992px) {
        .mobile-collapse-body {
            display: block !important;
        }
        .sidebar-collapse-btn {
            pointer-events: none;
        }
    }

    .search-input {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 50px;
        padding: 0.75rem 1.25rem;
        width: 100%;
        color: #1e293b;
        font-weight: 500;
    }

    .search-input:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .tag-pill {
        display: inline-block;
        background: #f1f5f9;
        color: #475569;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        margin: 0 6px 8px 0;
        text-decoration: none;
        transition: all 0.2s;
    }
    
    .tag-pill:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    /* Ensure no horizontal overflow on all elements */
    .forum-container,
    .forum-layout,
    .feed-column,
    .forum-sidebar,
    .posts-wrapper,
    .post-card,
    .sidebar-block {
        max-width: 100%;
        overflow-x: hidden;
        box-sizing: border-box;
    }
    
    /* Modal Styling */
    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    
    @media (max-width: 480px) {
        .modal-content {
            margin: 0.5rem;
            max-width: calc(100vw - 1rem);
        }
    }
    .modal-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        border-radius: 16px 16px 0 0;
    }
    .modal-title { font-weight: 800; color: #0f172a; }
</style>

<div class="forum-container w-100">
    <!-- Hero Header -->
    <div class="forum-hero">
        <div class="forum-hero-text">
            <h1>Community Discussions</h1>
            <p>Ask questions, share knowledge, and level up together.</p>
        </div>
        <?php if (is_logged_in()): ?>
        <button class="btn-ask" data-bs-toggle="modal" data-bs-target="#askQuestionModal">
            <i class="fa-solid fa-pen-nib me-2"></i> Ask a Question
        </button>
        <?php endif; ?>
    </div>

    <div class="forum-layout">
        <!-- Main Feed Column -->
        <div class="feed-column">
            
            <div class="feed-filters border-0 mb-0 pb-0 gap-3">
                <!-- Level Filters -->
                <div class="gap-2 border-bottom border-secondary border-opacity-10 pb-3 w-100 scrolling-track" style="display: flex !important; flex-direction: row !important; flex-wrap: nowrap !important; align-items: center !important; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; scrollbar-width: none !important;">
                    <span class="text-muted fw-bold me-2" style="display: inline-flex !important; align-items: center !important; white-space: nowrap !important; flex-shrink: 0 !important;"><i class="fa-solid fa-layer-group me-2"></i> Level:</span>
                    <a href="?level=All&sort=<?= $sortBy ?><?= $subjectFilter ? '&subject='.urlencode($subjectFilter) : '' ?><?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="filter-tab <?= $levelFilter === 'All' ? 'active' : '' ?>" style="display: inline-flex !important; white-space: nowrap !important; flex-shrink: 0 !important;">All Levels</a>
                    <a href="?level=University&sort=<?= $sortBy ?><?= $subjectFilter ? '&subject='.urlencode($subjectFilter) : '' ?><?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="filter-tab <?= $levelFilter === 'University' ? 'active' : '' ?>" style="display: inline-flex !important; white-space: nowrap !important; flex-shrink: 0 !important;">University</a>
                    <a href="?level=Secondary&sort=<?= $sortBy ?><?= $subjectFilter ? '&subject='.urlencode($subjectFilter) : '' ?><?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="filter-tab <?= $levelFilter === 'Secondary' ? 'active' : '' ?>" style="display: inline-flex !important; white-space: nowrap !important; flex-shrink: 0 !important;">Secondary</a>
                </div>
                
                <!-- Sort Filters -->
                <div class="gap-2 pb-3 border-bottom border-secondary border-opacity-10 w-100 mb-3 scrolling-track" style="display: flex !important; flex-direction: row !important; flex-wrap: nowrap !important; align-items: center !important; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; scrollbar-width: none !important;">
                    <span class="text-muted fw-bold me-2" style="display: inline-flex !important; align-items: center !important; white-space: nowrap !important; flex-shrink: 0 !important;"><i class="fa-solid fa-sort me-2"></i> Sort:</span>
                    <a href="?level=<?= urlencode($levelFilter) ?>&sort=score<?= $subjectFilter ? '&subject='.urlencode($subjectFilter) : '' ?><?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="filter-tab <?= $sortBy === 'score' ? 'active' : '' ?>" style="display: inline-flex !important; white-space: nowrap !important; flex-shrink: 0 !important;">
                        <i class="fa-solid fa-fire me-1"></i> Trending
                    </a>
                    <a href="?level=<?= urlencode($levelFilter) ?>&sort=newest<?= $subjectFilter ? '&subject='.urlencode($subjectFilter) : '' ?><?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="filter-tab <?= $sortBy === 'newest' ? 'active' : '' ?>" style="display: inline-flex !important; white-space: nowrap !important; flex-shrink: 0 !important;">
                        <i class="fa-solid fa-clock me-1"></i> Newest
                    </a>
                    <a href="?level=<?= urlencode($levelFilter) ?>&sort=unanswered<?= $subjectFilter ? '&subject='.urlencode($subjectFilter) : '' ?><?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="filter-tab <?= $sortBy === 'unanswered' ? 'active' : '' ?>" style="display: inline-flex !important; white-space: nowrap !important; flex-shrink: 0 !important;">
                        <i class="fa-solid fa-comment-slash me-1"></i> Unanswered
                    </a>
                </div>
                
                <div class="d-flex flex-wrap gap-2 w-100 align-items-center mb-3">
                
                <?php if ($searchQuery): ?>
                    <span class="ms-auto d-flex align-items-center text-muted fw-bold">
                        Search: "<?= htmlspecialchars($searchQuery) ?>"
                        <a href="discussions.php" class="text-danger ms-2"><i class="fa-solid fa-times-circle"></i></a>
                    </span>
                <?php endif; ?>
                <?php if ($subjectFilter): ?>
                    <span class="ms-auto d-flex align-items-center text-muted fw-bold">
                        Topic: <?= htmlspecialchars($subjectFilter) ?>
                        <a href="discussions.php" class="text-danger ms-2"><i class="fa-solid fa-times-circle"></i></a>
                    </span>
                <?php endif; ?>
                </div>
            </div>

            <div class="posts-wrapper">
                <?php if (empty($discussions)): ?>
                    <div class="text-center py-5">
                        <i class="fa-solid fa-ghost fs-1 text-muted mb-3 opacity-25"></i>
                        <h4 class="fw-bold text-muted">No discussions found</h4>
                        <p class="text-secondary">Be the first to start a conversation about this topic!</p>
                        <?php if (is_logged_in()): ?>
                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#askQuestionModal">Ask a Question</button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($discussions as $post): ?>
                        <div class="post-card" onclick="window.location.href='<?= is_logged_in() ? 'discussion_view.php?id=' . $post['id'] : 'login.php?redirect=' . urlencode('discussion_view.php?id=' . $post['id']) ?>'">
                            <div class="post-header">
                                <?php if (is_logged_in()): ?>
                                <a href="profile_public.php?id=<?= $post['user_id'] ?>" class="text-decoration-none text-dark d-flex align-items-center me-2" onclick="event.stopPropagation();">
                                    <img src="<?= !empty($post['author_avatar']) ? htmlspecialchars($post['author_avatar']) : 'assets/img/default_avatar.png' ?>" alt="Avatar" class="author-avatar me-2">
                                    <span class="author-name fw-bold"><?= htmlspecialchars($post['author_name']) ?></span>
                                </a>
                                <?php else: ?>
                                <div class="d-flex align-items-center me-2">
                                    <img src="<?= !empty($post['author_avatar']) ? htmlspecialchars($post['author_avatar']) : 'assets/img/default_avatar.png' ?>" alt="Avatar" class="author-avatar me-2">
                                    <span class="author-name fw-bold"><?= htmlspecialchars($post['author_name']) ?></span>
                                </div>
                                <?php endif; ?>
                                <span class="author-level" title="Gamification Level">
                                    Lvl <?= Gamification::getLevel((int)$post['author_points']) ?>
                                </span>
                                <span class="post-time">• <?= date('M j, Y', strtotime($post['created_at'])) ?></span>
                                <?php if (!empty($post['subject'])): ?>
                                    <span class="post-subject-tag"><?= htmlspecialchars($post['subject']) ?></span>
                                <?php endif; ?>
                                <button type="button"
                                        class="share-btn ms-2"
                                        title="Share"
                                        aria-label="Share"
                                        onclick="event.stopPropagation(); shareDiscussion(<?= (int)$post['id'] ?>, '<?= htmlspecialchars(addslashes($post['title'])) ?>');">
                                    <i class="fa-solid fa-share-nodes"></i>
                                </button>
                            </div>
                            
                            <h2 class="post-title"><?= htmlspecialchars($post['title']) ?></h2>
                            <div class="post-excerpt">
                                <?= nl2br(htmlspecialchars(mb_strimwidth($post['content'], 0, 300, '...'))) ?>
                            </div>
                            
                            <?php if (!empty($post['image_path'])): ?>
                                <img src="<?= SITE_URL . '/' . htmlspecialchars($post['image_path']) ?>" alt="Post Attachment" class="post-thumbnail">
                            <?php endif; ?>

                            <div class="post-footer">
                                <div class="vote-box" onclick="event.stopPropagation();">
                                    <?php $uv = is_logged_in() ? (int)$post['user_vote'] : 0; ?>
                                    <button class="vote-btn <?= $uv === 1 ? 'active-up' : '' ?>" id="btn-up-disc-<?= $post['id'] ?>" onclick="<?= is_logged_in() ? "submitVote('discussion', {$post['id']}, 1)" : "window.location.href='login.php?redirect=discussions.php'" ?>">
                                        <i class="fa-solid fa-thumbs-up"></i>
                                    </button>
                                    <span class="vote-count" id="score-discussion-<?= $post['id'] ?>"><?= (int)$post['score'] ?></span>
                                    <button class="vote-btn <?= $uv === -1 ? 'active-down' : '' ?>" id="btn-down-disc-<?= $post['id'] ?>" onclick="<?= is_logged_in() ? "submitVote('discussion', {$post['id']}, -1)" : "window.location.href='login.php?redirect=discussions.php'" ?>">
                                        <i class="fa-solid fa-thumbs-down"></i>
                                    </button>
                                </div>
                                <div class="action-btn">
                                    <i class="fa-regular fa-comment-dots fs-5"></i>
                                    <?= (int)$post['comment_count'] ?> Comments
                                </div>
                                <div class="action-btn ms-auto text-muted" style="pointer-events: none;">
                                    <i class="fa-regular fa-eye"></i> <?= (int)$post['views'] ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Basic Pagination -->
                    <?php if (count($discussions) === $limit || $page > 1): ?>
                        <div class="d-flex justify-content-between mt-4">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&level=<?= urlencode($levelFilter) ?>&sort=<?= $sortBy ?><?= $subjectFilter ? '&subject='.urlencode($subjectFilter) : '' ?>" class="btn btn-outline-primary fw-bold px-4">Previous</a>
                            <?php else: ?>
                                <div></div>
                            <?php endif; ?>
                            
                            <?php if (count($discussions) === $limit): ?>
                                <a href="?page=<?= $page + 1 ?>&level=<?= urlencode($levelFilter) ?>&sort=<?= $sortBy ?><?= $subjectFilter ? '&subject='.urlencode($subjectFilter) : '' ?>" class="btn btn-outline-primary fw-bold px-4">Next</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="forum-sidebar">
            <div class="sidebar-block">
                <form action="discussions.php" method="GET">
                    <div class="position-relative">
                        <i class="fa-solid fa-search position-absolute text-muted" style="left: 15px; top: 15px;"></i>
                        <input type="text" name="q" class="search-input ps-5" placeholder="Search discussions..." value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>
                </form>
            </div>

            <div class="share-toast" id="shareToastForum">Link copied</div>
            <script>
            (function(){
                var toast = document.getElementById('shareToastForum');
                function showToast(t){
                    if (!toast) return;
                    toast.textContent = t;
                    toast.classList.add('show');
                    clearTimeout(showToast._t);
                    showToast._t = setTimeout(function(){ toast.classList.remove('show'); }, 1800);
                }
                async function copyText(text){
                    try {
                        await navigator.clipboard.writeText(text);
                        return true;
                    } catch(e) {
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
                        } catch(e2) { return false; }
                    }
                }
                window.shareDiscussion = async function(id, title){
                    var url = (typeof SITE_URL !== 'undefined' && SITE_URL) ? (SITE_URL + '/discussion_view.php?id=' + id) : (window.location.origin + '/discussion_view.php?id=' + id);
                    if (navigator.share) {
                        try { await navigator.share({ title: title || document.title, url: url }); return; } catch(e) {}
                    }
                    var ok = await copyText(url);
                    if (ok) showToast('Link copied');
                };
            })();
            </script>
            <div class="sidebar-block">
                <button class="sidebar-collapse-btn" type="button" data-bs-toggle="collapse" data-bs-target="#trendingCollapse" aria-expanded="false" aria-controls="trendingCollapse">
                    <h3 class="sidebar-title m-0 p-0 border-0">
                        <span><i class="fa-solid fa-fire text-danger me-2"></i> Trending Topics</span>
                        <i class="fa-solid fa-chevron-down sidebar-collapse-icon text-muted"></i>
                    </h3>
                </button>
                <div class="mobile-collapse-body collapse" id="trendingCollapse">
                    <a href="discussions.php" class="tag-pill <?= empty($subjectFilter) ? 'bg-primary text-white' : '' ?>">All Topics</a>
                    <?php foreach ($trendingTags as $tag): ?>
                        <a href="?subject=<?= urlencode($tag['subject']) ?>" class="tag-pill <?= $subjectFilter === $tag['subject'] ? 'bg-primary text-white' : '' ?>">
                            <?= htmlspecialchars($tag['subject']) ?> <span class="opacity-50 text-xs ms-1"><?= $tag['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sidebar-block bg-primary text-white border-0">
                <button class="sidebar-collapse-btn" type="button" data-bs-toggle="collapse" data-bs-target="#rankCollapse" aria-expanded="false" aria-controls="rankCollapse">
                    <h3 class="sidebar-title text-white m-0 p-0 border-0">
                        <span><i class="fa-solid fa-award me-2"></i> Rank Up!</span>
                        <i class="fa-solid fa-chevron-down sidebar-collapse-icon text-white opacity-75"></i>
                    </h3>
                </button>
                <div class="mobile-collapse-body collapse" id="rankCollapse">
                    <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid rgba(255,255,255,0.2);">
                        <p class="mb-0 fw-medium opacity-75" style="font-size: 0.95rem;">
                            Did you know? You earn <strong class="text-warning">+5 XP</strong> for posting a helpful discussion, and <strong class="text-warning">+1 XP</strong> every time someone upvotes your answers. Keep helping the community!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ask Question Modal -->
<div class="modal fade" id="askQuestionModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="askQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="askQuestionModalLabel">Ask the Community</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="createDiscussionForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_discussion">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-lg bg-light" placeholder="What's your question? Be specific." required maxlength="250">
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold">Category Level</label>
                            <select name="level" class="form-select bg-light" data-toggle-init="1">
                                <option value="General">General / Both</option>
                                <option value="University">University Level</option>
                                <option value="Secondary">Secondary Level</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Subject / Tag</label>
                            <input type="text" name="subject" class="form-control bg-light" placeholder="e.g. Mathematics, Admission, JAMB" maxlength="100">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Details <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control bg-light" rows="6" placeholder="Provide all the details needed to answer your question..." required></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Attach Image (Optional, Max 1MB)</label>
                        <input type="file" name="image" class="form-control bg-light" accept="image/jpeg, image/png, image/gif, image/webp">
                        <div class="form-text">JPG, PNG, WEBP allowed. Keeps posts visual and helpful.</div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold" id="btnSubmitPost">
                            Post to Community
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Handle form submission via AJAX
    document.getElementById('createDiscussionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const btn = document.getElementById('btnSubmitPost');
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Posting...';
        
        const formData = new FormData(form);
        
        fetch('<?= SITE_URL ?>/api_discussion.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                Swal.fire({
                    title: 'Posted!',
                    text: '+5 XP Earned! Redirecting to your discussion...',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'discussion_view.php?id=' + data.id;
                });
            } else {
                Swal.fire('Error', data.message || 'Something went wrong.', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Post to Community';
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Network error occurred. The image might be too large.', 'error');
            btn.disabled = false;
            btn.innerHTML = 'Post to Community';
        });
    });

    // Global vote function for the feed (doesn't require full auth logic since view handles detailed states, this is just quick up/down)
    function submitVote(type, id, val) {
        const formData = new FormData();
        formData.append('action', 'vote');
        formData.append('target_type', type);
        formData.append('target_id', id);
        formData.append('vote_value', val);

        fetch('<?= SITE_URL ?>/api_discussion.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Update score natively in feed
                document.getElementById('score-' + type + '-' + id).textContent = data.counts.score;
                
                // Toggle active classes
                const btnUp = document.getElementById('btn-up-disc-' + id);
                const btnDown = document.getElementById('btn-down-disc-' + id);
                
                if (val === 1) {
                    if (btnUp.classList.contains('active-up')) {
                        btnUp.classList.remove('active-up');
                    } else {
                        btnUp.classList.add('active-up');
                        btnDown.classList.remove('active-down');
                    }
                } else if (val === -1) {
                    if (btnDown.classList.contains('active-down')) {
                        btnDown.classList.remove('active-down');
                    } else {
                        btnDown.classList.add('active-down');
                        btnUp.classList.remove('active-up');
                    }
                }
            } else {
                Swal.fire('Notice', data.message, 'warning');
            }
        });
    }

    // Auto-scroll to feed section when filters are clicked
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we need to scroll to feed on page load (after filter change)
        if (sessionStorage.getItem('scrollToFeed') === 'true') {
            sessionStorage.removeItem('scrollToFeed');
            setTimeout(() => {
                const feedSection = document.querySelector('.feed-column');
                if (feedSection) {
                    feedSection.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'start' 
                    });
                }
            }, 100);
        }
        
        // Add click listeners to all filter links
        const filterLinks = document.querySelectorAll('.filter-tab, .scrolling-track a');
        filterLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Store flag to trigger scroll on page load
                sessionStorage.setItem('scrollToFeed', 'true');
            });
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
