<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Post.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$postModel = new Post($pdo);
$post = $slug ? $postModel->getBySlug($slug) : null;

if (!$post) {
    header('Location: ' . SITE_URL . '/blog.php');
    exit;
}

$page_title = htmlspecialchars($post['title']);
include __DIR__ . '/includes/header.php';

$date_published = date('F j, Y', strtotime($post['created_at']));
$reading_time = ceil(str_word_count(strip_tags($post['content'])) / 200) . " min read";
if ($reading_time == "0 min read") $reading_time = "1 min read";
// Fetch related posts (same level/university, excluding this one)
$relatedPosts = [];
if (method_exists($postModel, 'getRelatedPosts')) {
    $relatedPosts = $postModel->getRelatedPosts($post, 4);
}

$currentUrl = urlencode(SITE_URL . '/post.php?slug=' . $post['slug']);
$encodedTitle = urlencode($page_title);
?>
<style>
/* Clean & Premium Post Design */
body {
    background-color: #f4f7f6;
    font-family: 'Inter', 'Segoe UI', sans-serif;
    color: #333;
}

.post-layout {
    max-width: 960px;
    margin: 40px auto 72px;
    padding: 0 20px;
}

.post-content-area {
    background: transparent;
}

.post-category-tag {
    display: inline-block;
    padding: 6px 14px;
    background: #e8f5e9;
    color: #2e7d32;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 20px;
}

.post-title-main {
    font-size: 2.4rem;
    font-weight: 800;
    line-height: 1.3;
    color: #1a1a1a;
    margin-bottom: 16px;
}

.post-meta-strip {
    display: flex;
    align-items: center;
    gap: 15px;
    padding-bottom: 18px;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 28px;
}

.author-pic {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #888;
    font-size: 1.2rem;
}

.author-info p {
    margin: 0;
    line-height: 1.4;
}
.author-name {
    font-weight: 700;
    color: #333;
}
.author-meta-details {
    font-size: 0.85rem;
    color: #777;
}

/* Typography styles for user content */
.article-body {
    font-size: 1.15rem;
    line-height: 1.8;
    color: #333;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.article-body p {
    margin-bottom: 1.5rem;
}

.article-body h2 {
    font-size: 1.8rem;
    font-weight: 700;
    margin-top: 2.5rem;
    margin-bottom: 1rem;
    color: #111;
}

.article-body h3 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-top: 2rem;
    margin-bottom: 1rem;
    color: #222;
}

.article-body img, .article-body iframe, .article-body video {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 20px 0;
    display: block;
}

.article-body blockquote {
    border-left: 5px solid #2e7d32;
    padding: 15px 25px;
    background: #fafafa;
    border-radius: 0 8px 8px 0;
    font-style: italic;
    font-size: 1.1rem;
    margin: 30px 0;
    color: #555;
}

/* Share Section inside content */
.share-section {
    margin-top: 50px;
    padding-top: 30px;
    border-top: 1px solid #eee;
    text-align: center;
}
.share-title {
    font-size: 1rem;
    font-weight: 700;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 15px;
}

.share-buttons {
    display: flex;
    justify-content: center;
    gap: 15px;
}

.share-btn {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #fff;
    text-decoration: none !important;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}
.share-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    color: #fff;
}
.s-fb { background: #3b5998; }
.s-tw { background: #1da1f2; }
.s-wa { background: #25d366; }
.s-cp { background: #607d8b; }

/* Related posts */
.related-section {
    max-width: 960px;
    margin: 0 auto 60px;
    padding: 0 20px;
}
.related-header {
    margin-bottom: 1rem;
}
.related-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.related-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.related-item {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #ffffff;
    border-radius: 10px;
    padding: 8px;
    border: 1px solid #e5e7eb;
    text-decoration: none !important;
    color: inherit !important;
    transition: background 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}
.related-item:hover {
    background: #f9fafb;
    box-shadow: 0 10px 25px -10px rgba(15,23,42,0.2);
    transform: translateY(-1px);
}
.related-thumb-small {
    width: 96px;
    height: 64px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
}
.related-thumb-small img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.related-text {
    flex: 1;
}
.related-title-text {
    font-size: 0.95rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 2px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.related-meta-row {
    font-size: 0.82rem;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 12px;
}
.related-meta-row i {
    margin-right: 4px;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .post-title-main {
        font-size: 1.8rem;
    }
    .article-body {
        font-size: 1.05rem;
    }
}
</style>

<div class="post-layout">
    <div class="post-content-area">
        <div>
            <a href="<?php echo SITE_URL; ?>/blog.php" class="text-muted text-decoration-none fw-bold" style="font-size: 0.9rem; margin-bottom: 25px; display: inline-block;">
                <i class="fas fa-arrow-left me-2"></i> Back to Blog
            </a>
        </div>
        
        <span class="post-category-tag">
            <?php echo $post['level'] ? htmlspecialchars($post['level']) : 'General'; ?>
        </span>
        
        <h1 class="post-title-main"><?php echo $page_title; ?></h1>
        
        <div class="post-meta-strip">
            <div class="author-pic"><i class="fas fa-user-edit"></i></div>
            <div class="author-info">
                <p class="author-name"><?php echo SITE_NAME; ?> Editorial</p>
                <p class="author-meta-details"><?php echo $date_published; ?> &bull; <?php echo $reading_time; ?></p>
            </div>
        </div>
        
        <article class="article-body">
            <?php 
                // Content is stored as HTML from the rich editor
                echo $post['content'] ?: 'This article has no content.';
            ?>
        </article>
        
        <div class="share-section">
            <div class="share-title">Share this article</div>
            <div class="share-buttons">
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $currentUrl; ?>" target="_blank" class="share-btn s-fb"><i class="fab fa-facebook-f"></i></a>
                <a href="https://twitter.com/intent/tweet?url=<?php echo $currentUrl; ?>&text=<?php echo $encodedTitle; ?>" target="_blank" class="share-btn s-tw"><i class="fab fa-twitter"></i></a>
                <a href="https://api.whatsapp.com/send?text=<?php echo $encodedTitle; ?>%20<?php echo $currentUrl; ?>" target="_blank" class="share-btn s-wa"><i class="fab fa-whatsapp"></i></a>
                <button class="share-btn s-cp" onclick="copyLink()" aria-label="Copy Link"><i class="fas fa-link"></i></button>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($relatedPosts)): ?>
<section class="related-section">
  <div class="related-header">
    <h2 class="related-title">You can also read</h2>
  </div>
  <div class="related-list">
    <?php foreach ($relatedPosts as $rp): ?>
      <?php
        $relTitle = htmlspecialchars($rp['title']);
        $relUrl = SITE_URL . '/post.php?slug=' . rawurlencode($rp['slug']);
        $relDate = date('M j, Y', strtotime($rp['created_at']));
        $relExcerpt = htmlspecialchars($rp['excerpt'] ?? '');
        if (!$relExcerpt) {
            $txt = strip_tags($rp['content'] ?? '');
            $relExcerpt = mb_strimwidth($txt, 0, 80, '...');
        }
        $relThumb = null;
        if (!empty($rp['content']) && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $rp['content'], $mm)) {
            $relThumb = $mm[1];
            if (strpos($relThumb, 'http://') !== 0 && strpos($relThumb, 'https://') !== 0) {
                $relThumb = SITE_URL . '/' . ltrim($relThumb, '/');
            }
        }
        $relSeed = crc32($rp['id']);
        $relThumb = $relThumb ?: 'https://source.unsplash.com/random/600x400?books,learning&sig=' . $relSeed;
        $relAuthor = SITE_NAME . ' Editorial';
      ?>
      <a href="<?php echo $relUrl; ?>" class="related-item">
        <div class="related-thumb-small">
          <img src="<?php echo $relThumb; ?>" alt="<?php echo $relTitle; ?>">
        </div>
        <div class="related-text">
          <div class="related-title-text"><?php echo $relTitle; ?></div>
          <div class="related-meta-row">
            <span><i class="fas fa-user"></i><?php echo htmlspecialchars($relAuthor); ?></span>
            <span><i class="far fa-calendar"></i><?php echo $relDate; ?></span>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- Copy Toast -->
<div class="position-fixed bottom-0 start-50 translate-middle-x p-3" style="z-index: 9999">
  <div id="copyToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body fw-bold">
        <i class="fas fa-check-circle me-2"></i> Link copied to clipboard!
      </div>
    </div>
  </div>
</div>

<script>
function copyLink() {
    var url = window.location.href;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(showSuccessToast);
    } else {
        var ta = document.createElement('textarea');
        ta.value = url;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); showSuccessToast(); } catch(e) {}
        document.body.removeChild(ta);
    }
}
function showSuccessToast() {
    if (typeof bootstrap !== 'undefined') {
        var toastEl = document.getElementById('copyToast');
        var toast = new bootstrap.Toast(toastEl, {delay: 2500});
        toast.show();
    } else {
        alert("Link copied!");
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
