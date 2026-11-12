<?php
require_once 'config/config.php';
$page_title = 'Home - ' . SITE_NAME;

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
?>

<!-- Styles loaded from design-system.css -->

<!-- ===========================================
     HERO SECTION - Rich & Professional
     =========================================== -->
<section class="hero-warm">
    <!-- Floating Decorative Elements -->
    <i class="fas fa-book float-element float-book"></i>
    <i class="fas fa-pencil-alt float-element float-pencil"></i>
    <i class="fas fa-graduation-cap float-element float-cap"></i>
    <i class="fas fa-star float-element float-star"></i>
    <i class="fas fa-cube float-element float-cube"></i>
    
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 hero-content-wrap">
                <!-- Education Level Badge -->
                <div class="edu-badge">
                    <i class="fas fa-university"></i>
                    <span>University</span>
                    <span class="edu-badge-divider"></span>
                    <i class="fas fa-school"></i>
                    <span>Secondary School</span>
                </div>
                
                <h1 class="hero-tagline">
                    Unlock Your Potential with
                    <span class="highlight"><?php echo SITE_NAME; ?></span>
                </h1>
                <p class="hero-description">
                    Your all-in-one learning platform for Nigerian students. Access premium study materials, practice CBT exams, watch expert video tutorials, and track your progress—whether you're preparing for WAEC, JAMB, or university exams.
                </p>
                
                <div class="hero-cta-group">
                    <?php if (!is_logged_in()): ?>
                        <a href="register.php" class="btn-start">
                            <i class="fas fa-rocket"></i> Start Learning Free
                        </a>
                        <a href="materials.php" class="btn-outline-hero">
                            <i class="fas fa-book-open"></i> Browse Materials
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn-start">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </a>
                        <a href="materials.php" class="btn-outline-hero">
                            <i class="fas fa-book-open"></i> Browse Materials
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Trust Stats -->
                <div class="trust-stats">
                    <div class="trust-stat">
                        <div class="trust-stat-number">10,000+</div>
                        <div class="trust-stat-label">Active Students</div>
                    </div>
                    <div class="trust-stat">
                        <div class="trust-stat-number">5,000+</div>
                        <div class="trust-stat-label">Study Materials</div>
                    </div>
                    <div class="trust-stat">
                        <div class="trust-stat-number">98%</div>
                        <div class="trust-stat-label">Pass Rate</div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 d-none d-lg-block">
                <div class="hero-image-wrapper">
                    <!-- Floating Stat Cards -->
                    <div class="floating-stat-card card-1">
                        <div class="floating-stat-icon green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="floating-stat-text">
                            <h4>CBT Ready</h4>
                            <span>Practice anytime</span>
                        </div>
                    </div>
                    
                    <div class="floating-stat-card card-2">
                        <div class="floating-stat-icon blue">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <div class="floating-stat-text">
                            <h4>1,500+ Videos</h4>
                            <span>Expert tutorials</span>
                        </div>
                    </div>
                    
                    <!-- Main Hero Image -->
                    <div class="hero-main-image">
                        <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=400&h=267&fit=crop&crop=faces&q=60&fm=webp" 
                             alt="Students learning together" 
                             fetchpriority="high"
                             width="400" height="267"
                             decoding="async">
                    </div>
                </div>
            </div>
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
                <div class="edu-level-card">
                    <div class="edu-level-icon">🎓</div>
                    <h3 class="edu-level-title">Secondary School</h3>
                    <p class="edu-level-desc">Complete preparation for WAEC, NECO, and JAMB exams.</p>
                    <ul class="edu-level-features">
                        <li><i class="fas fa-check"></i> WAEC Past Questions & Answers</li>
                        <li><i class="fas fa-check"></i> JAMB CBT Practice Tests</li>
                        <li><i class="fas fa-check"></i> SS1 - SS3 Study Notes</li>
                        <li><i class="fas fa-check"></i> Video Lessons for All Subjects</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="edu-level-card">
                    <div class="edu-level-icon">🏛️</div>
                    <h3 class="edu-level-title">University</h3>
                    <p class="edu-level-desc">Resources for Nigerian university students across all levels.</p>
                    <ul class="edu-level-features">
                        <li><i class="fas fa-check"></i> Course Materials & Lecture Notes</li>
                        <li><i class="fas fa-check"></i> Past Exam Questions</li>
                        <li><i class="fas fa-check"></i> 100 - 500 Level Resources</li>
                        <li><i class="fas fa-check"></i> Project & Research Materials</li>
                    </ul>
                </div>
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
        <p class="cta-desc-warm">Join thousands of Nigerian students achieving their academic goals today.</p>
        <?php if (!is_logged_in()): ?>
            <a href="register.php" class="btn-cta-white">Create Free Account</a>
        <?php else: ?>
            <a href="dashboard.php" class="btn-cta-white">Go to Dashboard</a>
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
