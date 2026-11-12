<?php
require_once __DIR__ . '/config/config.php';

$page_title = 'About Us';
include 'includes/header.php';
?>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --primary-color: #10b981;
        --secondary-color: #6366f1;
        --bg-warm: #fefdfb;
        --text-dark: #1e293b;
        --text-muted: #64748b;
    }

    body {
        background-color: var(--bg-warm);
        font-family: 'Poppins', sans-serif;
    }

    .about-hero {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        padding: 5rem 0;
        color: white;
        margin-bottom: 4rem;
        position: relative;
        overflow: hidden;
    }
    
    .about-hero::before {
        content: '';
        position: absolute;
        top: -50px;
        left: -50px;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }

    .about-hero::after {
        content: '';
        position: absolute;
        bottom: -50px;
        right: -50px;
        width: 200px;
        height: 200px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }

    .section-title {
        color: var(--text-dark);
        font-weight: 800;
        margin-bottom: 3rem;
        position: relative;
        display: inline-block;
    }
    
    .section-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 4px;
        background: var(--primary-color);
        border-radius: 2px;
    }
    
    .story-card {
        background: white;
        border-radius: 24px;
        padding: 3rem;
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.05);
        border: 1px solid #f1f5f9;
        margin-bottom: 4rem;
    }

    .value-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        height: 100%;
        transition: all 0.3s ease;
        border: 1px solid #f1f5f9;
        text-align: center;
    }
    
    .value-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.1);
        border-color: var(--primary-color);
    }
    
    .value-icon {
        width: 70px;
        height: 70px;
        background: #ecfdf5;
        color: var(--primary-color);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 1.75rem;
        transition: transform 0.3s ease;
    }
    
    .value-card:hover .value-icon {
        background: var(--primary-color);
        color: white;
        transform: rotate(5deg) scale(1.1);
    }
    
    .stats-section {
        background: var(--primary-color);
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        padding: 4rem 0;
        margin: 4rem 0;
        border-radius: 24px;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .stat-item {
        text-align: center;
    }
    
    .stat-number {
        font-size: 3.5rem;
        font-weight: 800;
        display: block;
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        font-size: 1.1rem;
        opacity: 0.9;
        font-weight: 500;
    }
    
    .cta-section {
        background: #1e293b;
        border-radius: 24px;
        padding: 4rem 2rem;
        text-align: center;
        color: white;
        margin-bottom: 4rem;
        position: relative;
        overflow: hidden;
    }
    
    .cta-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('assets/img/pattern.svg') opacity(0.05);
    }
</style>

<div class="about-hero text-center">
    <div class="container position-relative z-1">
        <h1 class="display-4 fw-bold mb-3">Empowering Nigerian Students</h1>
        <p class="lead mb-0 opacity-90 mx-auto" style="max-width: 700px;">We're on a mission to democratize access to quality education and provide the tools every student needs to succeed.</p>
    </div>
</div>

<div class="container">
    <!-- Our Story Section -->
    <div class="story-card">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <h2 class="fw-bold mb-4" style="color: var(--text-dark);">Our Story</h2>
                <p class="lead text-muted mb-4">
                    <?php echo SITE_NAME; ?> was founded with a simple yet powerful vision: to bridge the gap between ambition and achievement for Nigerian students.
                </p>
                <p class="text-muted mb-4">
                    Recognizing the challenges students face in accessing reliable study materials and effective preparation tools, we built a comprehensive platform that brings everything together. From WAEC and JAMB preparation to university course materials, we support students at every stage of their academic journey.
                </p>
                <div class="d-flex gap-3">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle text-primary me-2"></i>
                        <span class="fw-semibold">Quality Content</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle text-primary me-2"></i>
                        <span class="fw-semibold">Accessible to All</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="position-relative">
                    <img src="https://source.unsplash.com/random/600x400?students,learning" alt="Students Learning" class="img-fluid rounded-4 shadow-lg w-100" style="object-fit: cover; height: 400px;">
                    <div class="position-absolute bottom-0 start-0 bg-white p-3 rounded-top-4 ms-4 mb-n2 shadow-sm d-none d-md-block">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success bg-opacity-10 p-2 me-3">
                                <i class="fas fa-user-graduate text-success fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold">Student Focused</h6>
                                <small class="text-muted">Built for your success</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="stats-section">
        <div class="container position-relative z-1">
            <div class="row g-4">
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <span class="stat-number">10K+</span>
                        <span class="stat-label">Active Students</span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <span class="stat-number">500+</span>
                        <span class="stat-label">Study Materials</span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <span class="stat-number">1K+</span>
                        <span class="stat-label">Practice Quizzes</span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <span class="stat-number">98%</span>
                        <span class="stat-label">Success Rate</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Core Values Section -->
    <div class="mb-5 text-center">
        <h2 class="section-title">Our Core Values</h2>
        <div class="row g-4 mt-2">
            <div class="col-md-4">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Excellence</h4>
                    <p class="text-muted mb-0">
                        We are committed to providing top-tier educational content that meets the highest academic standards.
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-hands-helping"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Accessibility</h4>
                    <p class="text-muted mb-0">
                        Making quality education accessible to every student, regardless of their location or background.
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Innovation</h4>
                    <p class="text-muted mb-0">
                        Continuously evolving our platform with modern tools and technologies to enhance learning.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="cta-section">
        <div class="position-relative z-1">
            <h2 class="fw-bold mb-3 display-6">Ready to Excel in Your Studies?</h2>
            <p class="lead mb-4 opacity-75">Join thousands of students who are already achieving their academic goals with <?php echo SITE_NAME; ?>.</p>
            <?php if (!is_logged_in()): ?>
                <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-primary btn-lg px-5 rounded-pill shadow-lg hover-scale">Get Started Now</a>
            <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/dashboard.php" class="btn btn-primary btn-lg px-5 rounded-pill shadow-lg hover-scale">Go to Dashboard</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .hover-scale { transition: transform 0.2s; }
    .hover-scale:hover { transform: scale(1.05); }
</style>

<?php include 'includes/footer.php'; ?>
