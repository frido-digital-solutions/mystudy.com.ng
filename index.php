<?php
require_once 'config/config.php';
require_once __DIR__ . '/includes/seo.php';

// The words a student types. Almost every one of them is an examination, and
// this is the page they land on, so it says so in the title and in the
// description - the same words, said plainly.
$page_title = 'Free WAEC, NECO & JAMB Past Questions and Answers - CBT Practice';
$page_description = 'Free WAEC, NECO and JAMB past questions with answers, CBT practice and study materials for secondary and university students. Practise free - MyStudy Base.';

// Performance: Check if we have cached data
$cache_file = __DIR__ . '/cache/homepage_data.json';
$cache_time = 300; // 5 minutes cache

$use_cache = true;
$cached_data = null;

// Check cache exists and is valid
if ($use_cache && file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    $json_data = file_get_contents($cache_file);
    if ($json_data !== false) {
        $cached_data = json_decode($json_data, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Cache is valid
        } else {
            $cached_data = null; // Invalid JSON, ignore cache
        }
    }
}

// Import Models only if not using cache
if (!$cached_data) {
    require_once 'models/Material.php';
    require_once 'models/Tutorial.php';
    require_once 'models/Post.php';
    require_once 'models/Quiz.php';
    require_once 'models/Battle.php';

    $materialModel = new Material($pdo);
    $tutorialModel = new Tutorial($pdo);
    $postModel = new Post($pdo);
    $quizModel = new Quiz($pdo);
    $battleModel = new Battle($pdo);
}

// Get data from cache or database
if ($cached_data) {
    $latestMaterials = $cached_data['materials'];
    $featuredTutorials = $cached_data['tutorials'];
    $latestPosts = $cached_data['posts'];
    $latestQuizzes = $cached_data['quizzes'];
    $latestChallenges = $cached_data['challenges'];
    $openBattles = $cached_data['battles'];
} else {
    // Limit queries to only what's needed for performance
    $latestMaterials = $materialModel->searchMaterials(null, null, null, null, null, 3, 0, 'approved');
    $featuredTutorials = $tutorialModel->searchTutorials(null, null, null, 2, 0); // 2 tutorials (saves ~200KB of YouTube thumbnails)
    $latestPosts = $postModel->list(3, 0);
    $latestQuizzes = $quizModel->list(3, 0, null); // 3 quizzes
    $latestChallenges = $quizModel->listChallenges(null, 3); // 3 challenges
    $openBattles = $battleModel->getPublicOpenChallenges(3); // 3 battles
    
    // Cache the data
    $cache_data = [
        'materials' => $latestMaterials,
        'tutorials' => $featuredTutorials,
        'posts' => $latestPosts,
        'quizzes' => $latestQuizzes,
        'challenges' => $latestChallenges,
        'battles' => $openBattles
    ];
    
    // Create cache directory if it doesn't exist
    $cache_dir = dirname($cache_file);
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    file_put_contents($cache_file, json_encode($cache_data));
}

// Build mixed hero slider data (posts + quizzes + battles)
$heroSlides = [];

foreach ($latestPosts as $p) {
    // FORCE NO IMAGES for blog cards to prevent massive LCP payloads (e.g. 2.3MB user uploads).
    // Using the ultra-fast CSS gradient instead.
    $heroSlides[] = [
        'type' => 'blog',
        'title' => $p['title'],
        'subtitle' => 'Latest blog insight',
        'image' => null, 
        'gradient' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)', // Blog gradient
        'icon' => 'fas fa-newspaper',
        'cta_label' => 'Read Post',
        'cta_url' => SITE_URL . '/post.php?slug=' . rawurlencode($p['slug']),
    ];
}

foreach ($latestQuizzes as $q) {
    $heroSlides[] = [
        'type' => 'quiz',
        'title' => $q['title'],
        'subtitle' => 'Practice quiz (' . ($q['subject'] ?? 'General') . ')',
        'image' => null, // No image for quizzes - will use gradient background
        'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', // Professional gradient
        'icon' => 'fas fa-brain', // Quiz icon
        'cta_label' => 'Start Quiz',
        'cta_url' => SITE_URL . '/quiz.php?id=' . (int)$q['id'],
    ];
}

foreach ($latestChallenges as $c) {
    $heroSlides[] = [
        'type' => 'challenge',
        'title' => $c['title'],
        'subtitle' => 'Live challenge event',
        'image' => null, // No image for challenges - will use gradient background
        'gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)', // Challenge gradient
        'icon' => 'fas fa-trophy', // Challenge icon
        'cta_label' => 'View Challenge',
        'cta_url' => SITE_URL . '/quiz.php?id=' . (int)$c['id'],
    ];
}

foreach ($openBattles as $b) {
    $heroSlides[] = [
        'type' => 'battle',
        'title' => $b['quiz_title'],
        'subtitle' => 'Open battle by ' . ($b['challenger_name'] ?? 'Player'),
        'image' => null, // No image for battles - will use gradient background
        'gradient' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)', // Battle gradient
        'icon' => 'fas fa-gamepad', // Battle icon
        'cta_label' => 'Join Battle',
        'cta_url' => SITE_URL . '/battle_invite.php?code=' . urlencode($b['invite_code']),
    ];
}

if (!empty($heroSlides)) {
    shuffle($heroSlides);
    $heroSlides = array_slice($heroSlides, 0, 5); // 5 max (was 8) to reduce DOM size
}

include 'includes/header.php';

// What this page is, in the words a search engine reads.
seo_jsonld([
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    'name'     => SITE_NAME,
    'alternateName' => 'MyStudy Base, My Study Base, Study Base',
    'url'      => SITE_URL,
    'description' => $page_description,
    'inLanguage' => 'en',
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => [
            '@type'       => 'EntryPoint',
            'urlTemplate' => SITE_URL . '/quizzes.php?search={search_term_string}',
        ],
        'query-input' => 'required name=search_term_string',
    ],
]);
seo_jsonld([
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => SITE_NAME,
    'url'      => SITE_URL,
    'logo'     => SITE_URL . '/assets/img/favicon.png',
    'description' => $page_description,
]);
$examListItems = [];
$examPosition = 1;
foreach (seo_exam_pages() as $examCard) {
    $examListItems[] = [
        '@type'    => 'ListItem',
        'position' => $examPosition++,
        'name'     => $examCard['tagline'],
        'url'      => SITE_URL . '/' . $examCard['file'],
    ];
}
seo_jsonld([
    '@context' => 'https://schema.org',
    '@type'    => 'ItemList',
    'name'     => 'Practice papers by examination',
    'itemListElement' => $examListItems,
]);
?>

<!-- Styles loaded from design-system.css -->

<!-- ===========================================
     HERO SECTION - Rich & Professional
     =========================================== -->
<?php
// The real numbers on the site, read from the database once, so the home page
// can state what is actually there instead of guessing.
$msbCount = function (string $table) use ($pdo): int {
    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
};

$heroStats = [
    'questions'   => $msbCount('study_questions'),
    'papers'      => $msbCount('study_papers'),
    'quizzes'     => $msbCount('quizzes'),
    'materials'   => $msbCount('materials'),
    'tutorials'   => $msbCount('tutorials'),
    'discussions' => $msbCount('discussions'),
    'conversations' => $msbCount('conversations'),
];

$heroExamLogos = [];
foreach (['JAMB', 'WAEC', 'NECO'] as $heroExamKey) {
    $heroExamLogos[] = [
        'name' => $heroExamKey,
        'logo' => seo_exam_logo($heroExamKey),
    ];
}

// Where the buttons go, for a visitor and for a signed-in student.
$heroStudyHref  = SITE_URL . '/study.php';
$heroStartHref  = is_logged_in() ? SITE_URL . '/dashboard.php' : msb_auth_url('register');
$heroStartLabel = is_logged_in() ? 'Go to Dashboard' : 'Start Your Study';
$heroChatHref   = is_logged_in() ? SITE_URL . '/messages.php' : msb_auth_url('login');
$heroCommHref   = is_logged_in() ? SITE_URL . '/discussions.php' : msb_auth_url('login');
$heroMatHref    = SITE_URL . '/materials.php';

?>
<!-- ===========================================
     HERO - a card that slides through what the
     site holds: papers, chat, community, materials
     =========================================== -->
<style>
.hs-section{padding:34px 0 10px;background:linear-gradient(180deg,#f8fafc 0%,#ffffff 100%)}
.hs-card{position:relative;background:#fff;border:1px solid #e2e8f0;border-radius:26px;box-shadow:0 20px 50px rgba(15,23,42,.08);overflow:hidden;min-height:430px}
.hs-slide{display:none;padding:38px 40px 44px;box-sizing:border-box;min-height:430px}
.hs-slide.is-active{display:block;animation:hsFade .5s ease both}
@keyframes hsFade{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.hs-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:.74rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#047857;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:999px;padding:5px 13px;margin-bottom:16px}
.hs-title{margin:0 0 12px;font-size:clamp(1.6rem,3.4vw,2.5rem);font-weight:800;color:#0f172a;letter-spacing:-.025em;line-height:1.15}
.hs-title .hl{color:#059669}
.hs-text{margin:0 0 22px;max-width:64ch;font-size:1.03rem;line-height:1.7;color:#475569}
.hs-logos{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:22px}
.hs-logo{display:flex;align-items:center;gap:11px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:10px 16px 10px 12px}
.hs-logo img{width:40px;height:40px;object-fit:contain}
.hs-logo span{font-weight:800;color:#0f172a;font-size:.98rem;letter-spacing:.01em}
.hs-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}
.hs-stat{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px}
.hs-stat b{display:block;font-size:1.45rem;font-weight:800;color:#0f172a;line-height:1.1;letter-spacing:-.02em}
.hs-stat span{display:block;margin-top:4px;font-size:.78rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.05em}
.hs-ctas{display:flex;flex-wrap:wrap;gap:12px}
.hs-btn{display:inline-flex;align-items:center;gap:9px;text-decoration:none;font-weight:700;font-size:.95rem;padding:13px 26px;border-radius:999px;transition:all .18s ease;border:1.5px solid transparent}
.hs-btn.primary{background:#10b981;color:#fff;box-shadow:0 10px 24px rgba(16,185,129,.3)}
.hs-btn.primary:hover{background:#059669;color:#fff;transform:translateY(-2px)}
.hs-btn.ghost{background:#fff;color:#047857;border-color:#a7f3d0}
.hs-btn.ghost:hover{background:#ecfdf5;color:#065f46;transform:translateY(-2px)}
.hs-feats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px}
.hs-feat{display:flex;gap:12px;align-items:flex-start;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px}
.hs-feat i{width:38px;height:38px;flex:0 0 auto;border-radius:11px;background:#ecfdf5;color:#059669;display:flex;align-items:center;justify-content:center}
.hs-feat b{display:block;color:#0f172a;font-size:.94rem;font-weight:700}
.hs-feat span{display:block;font-size:.82rem;color:#64748b;margin-top:2px;line-height:1.5}
.hs-dots{position:absolute;left:0;right:0;bottom:18px;display:flex;justify-content:center;gap:9px}
.hs-dot{width:9px;height:9px;border-radius:50%;border:none;background:#cbd5e1;cursor:pointer;padding:0;transition:all .2s ease}
.hs-dot.is-active{background:#10b981;width:26px;border-radius:999px}
.hs-dot:focus-visible{outline:3px solid rgba(16,185,129,.45);outline-offset:2px}
@media (max-width:900px){
  .hs-slide{padding:30px 24px 44px;min-height:0}
  .hs-card{min-height:0}
  .hs-stats{grid-template-columns:repeat(2,1fr)}
  .hs-feats{grid-template-columns:1fr}
  .hs-ctas{flex-direction:column}
  .hs-btn{justify-content:center}
}
</style>

<section class="hs-section">
    <div class="container">
        <div class="hs-card" id="heroSlides">

            <!-- Slide 1: the papers -->
            <div class="hs-slide is-active" data-slide="0">
                <span class="hs-eyebrow"><i class="fas fa-sparkles"></i> Free for every student</span>
                <h1 class="hs-title">Study Real Past Questions and Answers <span class="hl">for JAMB, WAEC and NECO</span></h1>
                <p class="hs-text">
                    Read the real examination papers with the options and the correct answer under every
                    question. Then sit the same papers as a timed CBT and get your score the moment you finish.
                </p>

                <div class="hs-logos">
                    <?php foreach ($heroExamLogos as $heroLogo): ?>
                        <div class="hs-logo">
                            <?php if ($heroLogo['logo'] !== ''): ?>
                                <img src="<?php echo htmlspecialchars($heroLogo['logo']); ?>" alt="<?php echo htmlspecialchars($heroLogo['name']); ?>" width="40" height="40" loading="lazy">
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($heroLogo['name']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="hs-stats">
                    <div class="hs-stat"><b><?php echo number_format($heroStats['questions']); ?></b><span>Past questions &amp; answers</span></div>
                    <div class="hs-stat"><b><?php echo number_format($heroStats['papers']); ?></b><span>Full papers</span></div>
                    <div class="hs-stat"><b><?php echo number_format($heroStats['quizzes']); ?></b><span>CBT quizzes</span></div>
                    <div class="hs-stat"><b><?php echo number_format($heroStats['materials']); ?></b><span>Study materials</span></div>
                </div>

                <div class="hs-ctas">
                    <a class="hs-btn primary" href="<?php echo htmlspecialchars($heroStartHref); ?>">
                        <i class="fas fa-rocket"></i> <?php echo htmlspecialchars($heroStartLabel); ?>
                    </a>
                    <a class="hs-btn ghost" href="<?php echo htmlspecialchars($heroStudyHref); ?>">
                        <i class="fas fa-book-open"></i> Study Past Questions
                    </a>
                </div>
            </div>

            <!-- Slide 2: the chat -->
            <div class="hs-slide" data-slide="1">
                <span class="hs-eyebrow"><i class="fas fa-comment-dots"></i> Chat with friends</span>
                <h2 class="hs-title">Ask the students doing <span class="hl">the same paper</span></h2>
                <p class="hs-text">
                    A question you cannot answer is often one message away. Talk to other students, share a
                    tip, and get unstuck before the exam, not on the day.
                </p>
                <div class="hs-feats">
                    <div class="hs-feat"><i class="fas fa-users"></i><div><b>Private messages</b><span>Talk to a student directly and keep the conversation.</span></div></div>
                    <div class="hs-feat"><i class="fas fa-bolt"></i><div><b>Quick replies</b><span>Send a picture of the question you are stuck on.</span></div></div>
                    <div class="hs-feat"><i class="fas fa-mobile-screen"></i><div><b>On your phone</b><span>The app keeps the conversation open offline.</span></div></div>
                </div>
                <div class="hs-ctas">
                    <a class="hs-btn primary" href="<?php echo htmlspecialchars($heroChatHref); ?>">
                        <i class="fas fa-comment-dots"></i> Open the chat
                    </a>
                    <a class="hs-btn ghost" href="<?php echo htmlspecialchars($heroStudyHref); ?>">
                        <i class="fas fa-book-open"></i> Study Past Questions
                    </a>
                </div>
            </div>

            <!-- Slide 3: the community -->
            <div class="hs-slide" data-slide="2">
                <span class="hs-eyebrow"><i class="fas fa-users"></i> The community</span>
                <h2 class="hs-title">One community for <span class="hl">every subject</span></h2>
                <p class="hs-text">
                    Ask a question in the community, answer somebody else's, and keep the notes that come
                    out of it. The subjects people ask about most are the ones worth reading twice.
                </p>
                <div class="hs-stats">
                    <div class="hs-stat"><b><?php echo number_format($heroStats['discussions']); ?></b><span>Discussions</span></div>
                    <div class="hs-stat"><b><?php echo number_format($heroStats['conversations']); ?></b><span>Conversations</span></div>
                    <div class="hs-stat"><b><?php echo number_format($heroStats['tutorials']); ?></b><span>Video tutorials</span></div>
                    <div class="hs-stat"><b><?php echo number_format($heroStats['materials']); ?></b><span>Study materials</span></div>
                </div>
                <div class="hs-ctas">
                    <a class="hs-btn primary" href="<?php echo htmlspecialchars($heroCommHref); ?>">
                        <i class="fas fa-comments"></i> Join the community
                    </a>
                    <a class="hs-btn ghost" href="<?php echo htmlspecialchars($heroStudyHref); ?>">
                        <i class="fas fa-book-open"></i> Study Past Questions
                    </a>
                </div>
            </div>

            <!-- Slide 4: materials -->
            <div class="hs-slide" data-slide="3">
                <span class="hs-eyebrow"><i class="fas fa-folder-open"></i> Materials and lessons</span>
                <h2 class="hs-title">Notes, summaries and <span class="hl">video lessons</span></h2>
                <p class="hs-text">
                    When a paper is not enough, the notes are there: downloadable study materials for each
                    subject, and tutorials that explain the part you keep getting wrong.
                </p>
                <div class="hs-stats">
                    <div class="hs-stat"><b><?php echo number_format($heroStats['materials']); ?></b><span>Study materials</span></div>
                    <div class="hs-stat"><b><?php echo number_format($heroStats['tutorials']); ?></b><span>Video tutorials</span></div>
                    <div class="hs-stat"><b><?php echo number_format($heroStats['questions']); ?></b><span>Past questions</span></div>
                    <div class="hs-stat"><b><?php echo number_format($heroStats['quizzes']); ?></b><span>CBT quizzes</span></div>
                </div>
                <div class="hs-ctas">
                    <a class="hs-btn primary" href="<?php echo htmlspecialchars($heroMatHref); ?>">
                        <i class="fas fa-book-open"></i> Browse materials
                    </a>
                    <a class="hs-btn ghost" href="<?php echo htmlspecialchars($heroStudyHref); ?>">
                        <i class="fas fa-book-open"></i> Study Past Questions
                    </a>
                </div>
            </div>

            <div class="hs-dots" id="heroDots" role="tablist" aria-label="Home page highlights"></div>
        </div>
    </div>
</section>

<script>
(function () {
    var card = document.getElementById('heroSlides');
    if (!card) return;
    var slides = Array.prototype.slice.call(card.querySelectorAll('.hs-slide'));
    var dotsBox = document.getElementById('heroDots');
    var dots = [];
    var current = 0;
    var timer = null;

    slides.forEach(function (slide, i) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'hs-dot' + (i === 0 ? ' is-active' : '');
        dot.setAttribute('aria-label', 'Show highlight ' + (i + 1));
        dot.addEventListener('click', function () { go(i); restart(); });
        dotsBox.appendChild(dot);
        dots.push(dot);
    });

    function go(i) {
        if (i < 0 || i >= slides.length) return;
        slides[current].classList.remove('is-active');
        dots[current].classList.remove('is-active');
        current = i;
        slides[current].classList.add('is-active');
        dots[current].classList.add('is-active');
    }

    function next() { go((current + 1) % slides.length); }

    function restart() {
        if (timer) clearInterval(timer);
        timer = setInterval(next, 7000);
    }

    card.addEventListener('mouseenter', function () { if (timer) clearInterval(timer); });
    card.addEventListener('mouseleave', restart);
    card.addEventListener('focusin', function () { if (timer) clearInterval(timer); });

    restart();
})();
</script>

<!-- ===========================================
     CHOOSE YOUR EXAMINATION - the three doors
     =========================================== -->
<section class="py-4 py-lg-5" style="background: rgba(16,185,129,0.04);">
    <div class="container">
        <div class="text-center mb-4">
            <h2 style="font-size: clamp(1.4rem, 3vw, 2rem); font-weight: 800;">Start with your examination</h2>
            <p class="text-muted mt-2" style="max-width: 62ch; margin-left: auto; margin-right: auto;">
                Free past questions with answers and free CBT practice tests for every subject, the way the real exam is written.
            </p>
        </div>
        <div class="row g-3">
            <?php foreach (seo_exam_pages() as $examCard): ?>
                <div class="col-12 col-md-4">
                    <a class="d-block h-100 text-decoration-none" href="<?php echo SITE_URL; ?>/<?php echo $examCard['file']; ?>">
                        <div style="border: 1px solid rgba(16,185,129,0.28); border-radius: 18px; padding: 1.4rem 1.3rem; height: 100%; background: #fff;">
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?php echo htmlspecialchars(seo_exam_logo($examCard['key'])); ?>"
                                     alt="<?php echo htmlspecialchars($examCard['key']); ?> logo" width="52" height="52" loading="lazy"
                                     style="height: 52px; width: auto; max-width: 52px; object-fit: contain; flex-shrink: 0;">
                                <div style="font-size: 1.15rem; font-weight: 800; color: #047857;"><?php echo htmlspecialchars($examCard['tagline']); ?></div>
                            </div>
                            <div style="font-size: 0.86rem; color: var(--bs-secondary-color); margin-top: 10px;">
                                <?php echo htmlspecialchars($examCard['long']); ?> practice papers, answers and CBT tests.
                            </div>
                            <div style="font-size: 0.82rem; font-weight: 700; color: #047857; margin-top: 10px;">
                                Start practising <i class="fas fa-arrow-right"></i>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a class="btn btn-outline-success" href="<?php echo SITE_URL; ?>/study.php">
                <i class="fas fa-layer-group"></i> Browse every past question by subject and year
            </a>
        </div>
    </div>
</section>

<?php if (!empty($heroSlides)): ?>
<section class="hero-slider-section">
    <div class="container">
        <div class="hero-slider" id="heroSlider">
            <?php foreach ($heroSlides as $idx => $slide): ?>
            <div class="hero-slide<?php echo $idx === 0 ? ' active' : ''; ?>">
                <div class="hero-slide-media">
                    <?php if (!empty($slide['image'])): ?>
                        <img src="<?php echo htmlspecialchars($slide['image']); ?>" alt="<?php echo htmlspecialchars($slide['title']); ?>">
                    <?php else: ?>
                        <div class="hero-slide-gradient" style="background: <?php echo htmlspecialchars($slide['gradient'] ?? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'); ?>">
                            <i class="<?php echo htmlspecialchars($slide['icon'] ?? 'fas fa-star'); ?>"></i>
                        </div>
                    <?php endif; ?>
                    <span class="hero-slide-tag">
                        <?php
                        if ($slide['type'] === 'blog') echo 'Blog Post';
                        elseif ($slide['type'] === 'battle') echo 'Battle Hub';
                        elseif ($slide['type'] === 'challenge') echo 'Challenge Quiz';
                        else echo 'Quiz';
                        ?>
                    </span>
                </div>
                <div class="hero-slide-body">
                    <h3 class="hero-slide-title"><?php echo htmlspecialchars($slide['title']); ?></h3>
                    <p class="hero-slide-subtitle"><?php echo htmlspecialchars($slide['subtitle']); ?></p>
                    <a href="<?php echo htmlspecialchars($slide['cta_url']); ?>" class="hero-slide-cta">
                        <?php echo htmlspecialchars($slide['cta_label']); ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="hero-slider-dots" id="heroSliderDots">
            <?php foreach ($heroSlides as $idx => $_): ?>
            <button type="button" class="hero-dot<?php echo $idx === 0 ? ' active' : ''; ?>" data-index="<?php echo $idx; ?>" aria-label="Go to slide <?php echo $idx + 1; ?>"></button>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===========================================
     FEATURES ROW - Clickable Cards
     =========================================== -->
<section class="features-row">
    <div class="container">
        <div class="row g-4">
            <div class="col-6 col-lg-3">
                <a href="materials.php" class="feature-card">
                    <div class="feature-icon-circle green">
                        <i class="fas fa-laptop"></i>
                    </div>
                    <h3 class="feature-title">Interactive Courses</h3>
                    <p class="feature-desc">Learn at your own pace with structured courses.</p>
                    <span class="feature-link">Explore <i class="fas fa-arrow-right"></i></span>
                </a>
            </div>
            <div class="col-6 col-lg-3">
                <a href="quizzes.php" class="feature-card">
                    <div class="feature-icon-circle orange">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3 class="feature-title">Practice Quizzes</h3>
                    <p class="feature-desc">CBT practice for WAEC, JAMB & more.</p>
                    <span class="feature-link">Start Quiz <i class="fas fa-arrow-right"></i></span>
                </a>
            </div>
            <div class="col-6 col-lg-3">
                <a href="tutorials.php" class="feature-card">
                    <div class="feature-icon-circle red">
                        <i class="fab fa-youtube"></i>
                    </div>
                    <h3 class="feature-title">Video Tutorials</h3>
                    <p class="feature-desc">Watch expert explanations on topics.</p>
                    <span class="feature-link">Watch <i class="fas fa-arrow-right"></i></span>
                </a>
            </div>
            <div class="col-6 col-lg-3">
                <a href="materials.php" class="feature-card">
                    <div class="feature-icon-circle blue">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3 class="feature-title">Study Materials</h3>
                    <p class="feature-desc">Notes, textbooks & past questions.</p>
                    <span class="feature-link">Download <i class="fas fa-arrow-right"></i></span>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ===========================================
     EDUCATION LEVELS SECTION
     =========================================== -->
<section class="edu-levels-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title-main mb-2">Built for Every Student</h2>
            <p class="text-muted">Whether you're in secondary school or university, we've got you covered.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6">
                <a class="edu-level-card d-block text-decoration-none" style="color:inherit;" href="materials.php?level=Secondary">
                    <div class="edu-level-icon">🎓</div>
                    <h3 class="edu-level-title">Secondary School</h3>
                    <p class="edu-level-desc">Complete preparation for WAEC, NECO, and JAMB exams.</p>
                    <ul class="edu-level-features">
                        <li><i class="fas fa-check"></i> WAEC Past Questions & Answers</li>
                        <li><i class="fas fa-check"></i> JAMB CBT Practice Tests</li>
                        <li><i class="fas fa-check"></i> SS1 - SS3 Study Notes</li>
                        <li><i class="fas fa-check"></i> Video Lessons for All Subjects</li>
                    </ul>
                </a>
            </div>
            <div class="col-md-6">
                <a class="edu-level-card d-block text-decoration-none" style="color:inherit;" href="materials.php?level=University">
                    <div class="edu-level-icon">🏛️</div>
                    <h3 class="edu-level-title">University</h3>
                    <p class="edu-level-desc">Resources for Nigerian university students across all levels.</p>
                    <ul class="edu-level-features">
                        <li><i class="fas fa-check"></i> Course Materials & Lecture Notes</li>
                        <li><i class="fas fa-check"></i> Past Exam Questions</li>
                        <li><i class="fas fa-check"></i> 100 - 500 Level Resources</li>
                        <li><i class="fas fa-check"></i> Project & Research Materials</li>
                    </ul>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ===========================================
     MAIN CONTENT WITH SIDEBAR
     =========================================== -->
<section class="py-4">
    <div class="container">
        <div class="content-with-sidebar">
            <!-- Main Content -->
            <div class="main-area">
                <!-- Latest Tutorials -->
                <div class="mb-5">
                    <div class="section-header-flex">
                        <h2 class="section-title-main">Latest Tutorials</h2>
                        <div class="carousel-nav">
                        <button class="carousel-btn" aria-label="Previous tutorial"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                            <button class="carousel-btn" aria-label="Next tutorial"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    
                    <div class="tutorials-grid">
                        <?php foreach (array_slice($featuredTutorials, 0, 2) as $t): ?>
                        <a href="<?php echo htmlspecialchars($t['youtube_url']); ?>" target="_blank" class="tutorial-card-warm">
                            <div class="tutorial-card-thumb">
                                <?php if (!empty($t['thumbnail_url']) || !empty($t['youtube_id'])): ?>
                                    <img src="<?php echo htmlspecialchars($t['thumbnail_url'] ?: 'https://img.youtube.com/vi/'.$t['youtube_id'].'/mqdefault.jpg'); ?>" alt="<?php echo htmlspecialchars($t['title']); ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="thumb-placeholder">📚</div>
                                <?php endif; ?>
                            </div>
                            <div class="tutorial-card-body">
                                <h4 class="tutorial-card-title"><?php echo htmlspecialchars(mb_strimwidth($t['title'], 0, 50, '...')); ?></h4>
                                <p class="tutorial-card-desc"><?php echo htmlspecialchars(mb_strimwidth($t['description'] ?? '', 0, 80, '...')); ?></p>
                                <span class="learn-more-link">Learn More <i class="fas fa-arrow-right"></i></span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Resource Library -->
                <div class="mb-5">
                    <div class="section-header-flex">
                        <h2 class="section-title-main">Resource Library</h2>
                        <a href="materials.php" class="learn-more-link">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="row g-3">
                        <?php foreach ($latestMaterials as $m): ?>
                        <div class="col-md-6 col-lg-3">
                            <a href="material.php?id=<?php echo $m['id']; ?>" class="tutorial-card-warm">
                                <div class="tutorial-card-thumb">
                                    <div class="thumb-placeholder">📄</div>
                                </div>
                                <div class="tutorial-card-body">
                                    <h4 class="tutorial-card-title"><?php echo htmlspecialchars(mb_strimwidth($m['title'], 0, 40, '...')); ?></h4>
                                    <span class="learn-more-link">Read More <i class="fas fa-arrow-right"></i></span>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <aside class="sidebar">
                <!-- Latest Tutorials Sidebar -->
                <div class="sidebar-card">
                    <div class="sidebar-header">
                        <h3 class="sidebar-title">Latest Tutorials</h3>
                        <a href="tutorials.php" class="sidebar-link">See More Tutorials</a>
                    </div>
                    <?php foreach (array_slice($featuredTutorials, 0, 3) as $t): ?>
                    <a href="<?php echo htmlspecialchars($t['youtube_url']); ?>" target="_blank" class="sidebar-item">
                        <div class="sidebar-item-thumb">
                            <i class="fas fa-play"></i>
                        </div>
                        <div class="sidebar-item-content">
                            <h4><?php echo htmlspecialchars(mb_strimwidth($t['title'], 0, 40, '...')); ?></h4>
                            <span>Learn More →</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Resource Library Sidebar -->
                <div class="sidebar-card">
                    <div class="sidebar-header">
                        <h3 class="sidebar-title">Resource Library</h3>
                        <a href="materials.php" class="sidebar-link">Browse All Materials</a>
                    </div>
                    <?php foreach (array_slice($latestMaterials, 0, 2) as $m): ?>
                    <a href="material.php?id=<?php echo $m['id']; ?>" class="sidebar-item">
                        <div class="sidebar-item-thumb">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="sidebar-item-content">
                            <h4><?php echo htmlspecialchars(mb_strimwidth($m['title'], 0, 40, '...')); ?></h4>
                            <span>Read More →</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Community Blog Posts Sidebar -->
                <div class="sidebar-card">
                    <div class="sidebar-header">
                        <h3 class="sidebar-title">Community Blog Posts</h3>
                        <a href="blog.php" class="sidebar-link">Read Blog Posts</a>
                    </div>
                    <?php foreach (array_slice($latestPosts, 0, 3) as $p): ?>
                    <a href="post.php?slug=<?php echo urlencode($p['slug']); ?>" class="sidebar-item">
                        <div class="sidebar-item-thumb">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <div class="sidebar-item-content">
                            <h4><?php echo htmlspecialchars(mb_strimwidth($p['title'], 0, 40, '...')); ?></h4>
                            <span>Learn More →</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>
    </div>
</section>

<!-- ===========================================
     COMMUNITY BLOG SECTION
     =========================================== -->
<section class="blog-section-warm">
    <div class="container">
        <div class="section-header-flex mb-4">
            <h2 class="section-title-main">Community Blog</h2>
            <a href="blog.php" class="learn-more-link">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        
        <div class="blog-grid">
            <?php foreach ($latestPosts as $p): ?>
            <a href="post.php?slug=<?php echo urlencode($p['slug']); ?>" class="blog-card-warm">
                <div class="blog-card-thumb">📰</div>
                <div class="blog-card-body">
                    <span class="blog-card-tag"><?php echo htmlspecialchars($p['level'] ?? 'General'); ?></span>
                    <h4 class="blog-card-title"><?php echo htmlspecialchars(mb_strimwidth($p['title'], 0, 60, '...')); ?></h4>
                    <div class="blog-card-meta">
                        <i class="far fa-calendar me-1"></i> <?php echo date('M j, Y', strtotime($p['created_at'])); ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===========================================
     CTA SECTION
     =========================================== -->
<section class="cta-warm">
    <div class="container cta-content">
        <h2 class="cta-title-warm">Ready to Start Learning?</h2>
        <p class="cta-desc-warm">Every paper, quiz and material on this site is free. An account only keeps your scores and your progress.</p>
        <?php if (!is_logged_in()): ?>
            <a href="<?php echo htmlspecialchars(msb_auth_url('register')); ?>" class="btn-cta-white">Create Free Account</a>
        <?php else: ?>
            <a href="<?php echo SITE_URL; ?>/dashboard.php" class="btn-cta-white">Go to Dashboard</a>
        <?php endif; ?>
    </div>
</section>

<script>
(function(){
  var slider = document.getElementById('heroSlider');
  var dotsWrap = document.getElementById('heroSliderDots');
  if (!slider || !dotsWrap) return;
  var slides = Array.prototype.slice.call(slider.querySelectorAll('.hero-slide'));
  var dots = Array.prototype.slice.call(dotsWrap.querySelectorAll('.hero-dot'));
  if (!slides.length) return;
  var current = 0;

  function goTo(index) {
    slides[current].classList.remove('active');
    dots[current].classList.remove('active');
    current = index;
    slides[current].classList.add('active');
    dots[current].classList.add('active');
  }

  dots.forEach(function(dot, idx){
    dot.addEventListener('click', function(){
      goTo(idx);
      restartAuto();
    });
  });

  var timer;
  function startAuto() {
    timer = setInterval(function(){
      var next = (current + 1) % slides.length;
      goTo(next);
    }, 7000);
  }
  function restartAuto() {
    if (timer) clearInterval(timer);
    startAuto();
  }
  startAuto();
})();
</script>

<?php include 'includes/footer.php'; ?>
