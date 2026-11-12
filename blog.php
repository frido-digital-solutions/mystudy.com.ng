<?php
require_once __DIR__ . '/config/config.php';
$page_title = 'Educational Blog';
require_once __DIR__ . '/models/Post.php';

$postModel = new Post($pdo);
// Inputs
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$level = isset($_GET['level']) ? ($_GET['level'] === 'University' ? 'University' : ($_GET['level'] === 'Secondary' ? 'Secondary' : null)) : null;
$universityId = isset($_GET['university_id']) ? (int)$_GET['university_id'] : 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Data
if ($q !== '' && method_exists($postModel, 'searchPosts')) {
    $posts = $postModel->searchPosts($q, $level, $universityId ?: null, $limit, $offset);
    $totalCount = $postModel->countSearchPosts($q, $level, $universityId ?: null);
} elseif (method_exists($postModel, 'listFiltered')) {
    $posts = $postModel->listFiltered($level, $universityId ?: null, $limit, $offset);
    $totalCount = $postModel->countFilteredPosts($level, $universityId ?: null);
} else {
    $posts = $postModel->list($limit, $offset);
    $totalCount = $postModel->countAllPosts();
}
$totalPages = ceil($totalCount / $limit) ?: 1;
// Build university id=>acronym map for tag display
$uniMap = [];
if (method_exists($postModel, 'listUniversities')) {
    foreach ($postModel->listUniversities() as $u) {
        $uniMap[(int)$u['id']] = $u['acronym'];
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
    /* Premium Blog Redesign */
    body { background-color: #f8fafc; }
    
    .blog-hero-section {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        padding: 5rem 0 7rem;
        position: relative;
        overflow: hidden;
    }
    .blog-hero-section::before {
        content: ''; position: absolute; top: -50%; left: -50%;
        width: 200%; height: 200%;
        background: radial-gradient(circle, rgba(16,185,129,0.1) 0%, transparent 60%);
        animation: rotateBg 40s linear infinite;
        pointer-events: none;
    }
    @keyframes rotateBg { 100% { transform: rotate(360deg); } }
    
    .hero-title {
        color: #ffffff;
        font-weight: 800;
        font-size: clamp(2.5rem, 5vw, 4rem);
        letter-spacing: -1px;
        margin-bottom: 1rem;
    }
    .hero-subtitle { color: #cbd5e1; font-size: 1.15rem; max-width: 600px; margin: 0 auto; line-height: 1.6; }

    /* Search & Filter Floating Card */
    .filter-card {
        background: #ffffff;
        border-radius: 20px;
        padding: 1.5rem 2rem;
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.08);
        border: 1px solid rgba(226,232,240,0.8);
        margin-top: -3.5rem;
        position: relative;
        z-index: 10;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
        justify-content: space-between;
    }
    .filter-input-group {
        flex: 1;
        min-width: 250px;
        position: relative;
    }
    .filter-input-group i {
        position: absolute;
        left: 18px; top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }
    .filter-input-group input {
        width: 100%;
        padding: 14px 14px 14px 45px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }
    .filter-input-group input:focus {
        background: #ffffff;
        border-color: #10b981;
        box-shadow: 0 0 0 4px rgba(16,185,129,0.1);
        outline: none;
    }
    .filter-select {
        padding: 14px 35px 14px 18px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background-color: #f8fafc;
        color: #475569;
        font-weight: 500;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.3s ease;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
    }
    .filter-select:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 4px rgba(16,185,129,0.1); background-color: #ffffff; }

    /* Post Grid */
    .blog-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 30px;
        padding: 3rem 0;
    }
    
    /* Modern Premium Card */
    .premium-post-card {
        background: #ffffff;
        border-radius: 24px;
        overflow: hidden;
        border: 1px solid #f1f5f9;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.02);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
        position: relative;
        text-decoration: none !important;
        color: inherit !important;
        height: 100%;
    }
    .premium-post-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1);
        border-color: #e2e8f0;
    }
    
    .card-img-wrapper {
        position: relative;
        height: 220px;
        overflow: hidden;
    }
    .card-img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s ease;
    }
    .premium-post-card:hover .card-img-wrapper img { transform: scale(1.05); }

    .card-category-badge {
        position: absolute;
        top: 16px; left: 16px;
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(8px);
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        z-index: 2;
    }
    
    .post-share-btn {
        position: absolute;
        top: 16px; right: 16px;
        width: 36px; height: 36px;
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(8px);
        border: none;
        border-radius: 50%;
        color: #475569;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        cursor: pointer;
        transition: all 0.2s;
        z-index: 5;
    }
    .post-share-btn:hover { background: #10b981; color: #fff; transform: scale(1.05); }

    .card-content-wrap {
        padding: 1.8rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .card-meta {
        font-size: 0.85rem;
        color: #94a3b8;
        font-weight: 500;
        margin-bottom: 0.8rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .card-title {
        font-size: 1.35rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.4;
        margin-bottom: 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        transition: color 0.2s;
    }
    .premium-post-card:hover .card-title { color: #10b981; }
    
    .card-excerpt {
        color: #64748b;
        font-size: 0.95rem;
        line-height: 1.6;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        margin-bottom: 1.5rem;
        flex: 1;
    }
    
    .card-footer-box {
        margin-top: auto;
        padding-top: 1.2rem;
        border-top: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .read-more-text {
        color: #10b981;
        font-weight: 600;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        transition: gap 0.2s;
        gap: 6px;
    }
    .premium-post-card:hover .read-more-text { gap: 10px; }

    /* Share Toast */
    .share-toast {
        position: fixed; bottom: 30px; left: 50%;
        transform: translateX(-50%) translateY(20px);
        background: #0f172a; color: white;
        padding: 12px 24px; border-radius: 50px;
        font-weight: 600; font-size: 0.9rem;
        box-shadow: 0 10px 25px rgba(15,23,42,0.3);
        opacity: 0; pointer-events: none;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        z-index: 9999;
    }
    .share-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

    /* Empty state */
    .glass-empty-state {
        background: #ffffff;
        border-radius: 24px;
        padding: 5rem 2rem;
        text-align: center;
        border: 1px dashed #cbd5e1;
        box-shadow: 0 10px 30px rgba(0,0,0,0.02);
    }
</style>

<section class="blog-hero-section">
    <div class="container text-center">
        <h1 class="hero-title">Educational Hub</h1>
        <p class="hero-subtitle">Discover powerful insights, expert study techniques, and the latest updates from the academic world.</p>
    </div>
</section>

<div class="container pb-5">
    <!-- Filter Bar -->
    <form method="get" action="" id="filterForm">
        <div class="filter-card">
            <div class="filter-input-group">
                <i class="fas fa-search"></i>
                <input type="text" name="q" placeholder="Search articles, topics..." value="<?php echo htmlspecialchars($q); ?>" onchange="document.getElementById('filterForm').submit()">
            </div>
            
            <div class="d-flex gap-3 flex-wrap">
                <select class="filter-select" id="levelFilter" name="level" onchange="document.getElementById('filterForm').submit()">
                    <option value="" <?php echo $level===null?'selected':''; ?>>All Education Levels</option>
                    <option value="Secondary" <?php echo $level==='Secondary'?'selected':''; ?>>Secondary Level</option>
                    <option value="University" <?php echo $level==='University'?'selected':''; ?>>University Level</option>
                </select>
                
                <div id="uniFilterWrap" style="display: <?php echo ($level==='University')?'block':'none'; ?>;">
                    <select class="filter-select" name="university_id" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All Universities</option>
                        <?php foreach ($postModel->listUniversities() as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>" <?php echo ($universityId===(int)$u['id'])?'selected':''; ?>><?php echo htmlspecialchars($u['acronym']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </form>

    <!-- Results count -->
    <?php if (!empty($posts)): ?>
    <div class="d-flex justify-content-between align-items-center mt-4 mb-2 px-2">
        <span class="text-muted fw-bold small text-uppercase" style="letter-spacing: 1px;">Showing <?php echo number_format(count($posts)); ?> of <?php echo number_format($totalCount); ?> articles</span>
    </div>
    <?php endif; ?>

    <!-- Posts Grid -->
    <?php if (empty($posts)): ?>
        <div class="glass-empty-state mt-5">
            <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-4" style="width: 80px; height: 80px;">
                <i class="fas fa-book-open fs-1 text-muted opacity-50"></i>
            </div>
            <h3 class="fw-bold text-dark mb-2">No articles found</h3>
            <p class="text-muted mb-4">We couldn't find any articles matching your search criteria.</p>
            <a href="blog.php" class="btn btn-primary rounded-pill px-4 py-2 fw-bold">Clear Filters</a>
        </div>
    <?php else: ?>
        <div class="blog-grid">
            <?php foreach ($posts as $p): ?>
                <?php 
                    $title = htmlspecialchars($p['title']);
                    $date  = date('M j, Y', strtotime($p['created_at']));
                    $excerpt = htmlspecialchars($p['excerpt'] ?? '');
                    if (!$excerpt) {
                         $text = strip_tags($p['content'] ?? '');
                         $excerpt = mb_strimwidth($text, 0, 140, '...');
                    }
                    $postUrl = SITE_URL . '/post.php?slug=' . rawurlencode($p['slug']);
                    
                    $badgeText = 'General';
                    if ($p['level'] === 'University') {
                        $badgeText = 'University';
                        if (!empty($p['university_id']) && isset($uniMap[$p['university_id']])) {
                            $badgeText = $uniMap[$p['university_id']];
                        }
                    } elseif ($p['level'] === 'Secondary') {
                        $badgeText = 'Secondary';
                    }
                    
                    // Determine thumbnail from first inline image; fallback to Unsplash
                    $thumb = null;
                    if (!empty($p['content']) && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $p['content'], $m)) {
                        $thumb = $m[1];
                        if (strpos($thumb, 'http://') !== 0 && strpos($thumb, 'https://') !== 0) {
                            $thumb = SITE_URL . '/' . ltrim($thumb, '/');
                        }
                    }
                    $imgSeed = crc32($p['id']); 
                ?>
                <a href="<?php echo $postUrl; ?>" class="premium-post-card">
                    <div class="card-img-wrapper">
                        <span class="card-category-badge"><?php echo $badgeText; ?></span>
                        <button type="button" class="post-share-btn" 
                                aria-label="Share"
                                data-url="<?php echo htmlspecialchars($postUrl); ?>" 
                                data-title="<?php echo $title; ?>">
                            <i class="fas fa-share-nodes"></i>
                        </button>
                        <img src="<?php echo $thumb ?: 'https://source.unsplash.com/random/600x400?education,student&sig=' . $imgSeed; ?>" alt="<?php echo $title; ?>" loading="lazy">
                    </div>
                    
                    <div class="card-content-wrap">
                        <div class="card-meta">
                            <span><i class="far fa-calendar text-muted"></i> <?php echo $date; ?></span>
                            <span class="ms-auto"><i class="far fa-clock text-muted"></i> 5 min read</span>
                        </div>
                        <h3 class="card-title"><?php echo $title; ?></h3>
                        <p class="card-excerpt"><?php echo $excerpt; ?></p>
                        
                        <div class="card-footer-box">
                            <span class="read-more-text">Read Article <i class="fas fa-arrow-right"></i></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): 
            $queryParams = $_GET;
            unset($queryParams['page']);
            $qs = http_build_query($queryParams);
            $baseUrl = '?' . ($qs ? $qs . '&' : '') . 'page=';
        ?>
            <!-- Beautiful Pagination -->
            <nav class="mt-5 mb-3 d-flex justify-content-center">
                <ul class="pagination pagination-lg shadow-sm" style="border-radius: 50px; overflow: hidden;">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link border-0 text-dark fw-bold px-4" href="<?php echo $baseUrl . ($page - 1); ?>">Prev</a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link border-0 fw-bold <?php echo $i === $page ? 'bg-primary text-white' : 'text-dark'; ?>" href="<?php echo $baseUrl . $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link border-0 text-dark fw-bold px-4" href="<?php echo $baseUrl . ($page + 1); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="share-toast" id="shareToast"><i class="fas fa-check-circle me-2 text-success"></i> Link copied to clipboard</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dynamic select show/hide
    const levelFilter = document.getElementById('levelFilter');
    const uniFilterWrap = document.getElementById('uniFilterWrap');
    if(levelFilter) {
        levelFilter.addEventListener('change', function() {
            uniFilterWrap.style.display = this.value === 'University' ? 'block' : 'none';
        });
    }

    // Modern Share Logic
    const shareToast = document.getElementById('shareToast');
    let toastTimeout;
    
    function showToast() {
        if (!shareToast) return;
        shareToast.classList.add('show');
        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(() => shareToast.classList.remove('show'), 2500);
    }

    document.querySelectorAll('.post-share-btn').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault(); // prevent navigation
            e.stopPropagation(); // prevent card click
            
            const url = this.getAttribute('data-url');
            const title = this.getAttribute('data-title');
            
            if (navigator.share) {
                try {
                    await navigator.share({ title: title, url: url });
                } catch (err) {
                    copyToClipboard(url);
                }
            } else {
                copyToClipboard(url);
            }
        });
    });

    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(showToast);
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); showToast(); } catch(e) {}
            document.body.removeChild(ta);
        }
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
