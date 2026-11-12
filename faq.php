<?php
require_once __DIR__ . '/config/config.php';

$page_title = 'Frequently Asked Questions';
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

    .faq-hero {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        padding: 5rem 0;
        color: white;
        margin-bottom: 4rem;
        position: relative;
        overflow: hidden;
    }
    
    .faq-hero::after {
        content: '';
        position: absolute;
        bottom: -50px;
        left: 10%;
        width: 150px;
        height: 150px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }

    .category-header {
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        color: var(--primary-color);
        font-weight: 700;
        position: relative;
        padding-bottom: 0.5rem;
    }
    
    .category-header::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 40px;
        height: 3px;
        background: var(--primary-color);
        border-radius: 2px;
    }
    
    .category-icon {
        width: 40px;
        height: 40px;
        background: #ecfdf5;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    
    .faq-wrapper {
        margin-bottom: 3rem;
    }

    .accordion-item {
        border: none;
        border-radius: 12px !important;
        margin-bottom: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        background: white;
        border: 1px solid #f1f5f9;
        overflow: hidden;
    }
    
    .accordion-button {
        padding: 1.25rem 1.5rem;
        font-weight: 600;
        color: var(--text-dark);
        background: white;
        border: none;
        box-shadow: none !important;
    }

    .accordion-button:not(.collapsed) {
        color: var(--primary-color);
        background-color: #f0fdf9;
    }
    
    .accordion-button:focus {
        border-color: #a7f3d0;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1) !important;
    }
    
    .accordion-body {
        padding: 1.5rem;
        color: var(--text-muted);
        line-height: 1.7;
        background: white;
        border-top: 1px solid #f1f5f9;
    }

    .contact-cta {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: white;
        padding: 3rem;
        border-radius: 20px;
        text-align: center;
        margin-top: 2rem;
        box-shadow: 0 10px 30px -5px rgba(30, 41, 59, 0.3);
    }
</style>

<div class="faq-hero text-center">
    <div class="container position-relative z-1">
        <h1 class="display-4 fw-bold mb-3">Frequently Asked Questions</h1>
        <p class="lead mb-0 opacity-90 mx-auto" style="max-width: 600px;">Have questions? We're here to help.</p>
    </div>
</div>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <!-- Getting Started -->
            <div class="faq-wrapper">
                <div class="category-header">
                    <div class="category-icon"><i class="fas fa-rocket"></i></div>
                    <h3 class="h4 mb-0">Getting Started</h3>
                </div>
                
                <div class="accordion" id="accordionStarter">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true">
                                How do I create an account?
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#accordionStarter">
                            <div class="accordion-body">
                                Start by clicking the "Get Started" button in the top right corner. Select your account type (Secondary or University), fill in your basic details, and you'll be ready to go in less than 2 minutes. No credit card required.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                Is <?php echo SITE_NAME; ?> completely free?
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionStarter">
                            <div class="accordion-body">
                                Yes! Access to all study materials, quizzes, educational blog posts, and study tools is completely free. We believe education should be accessible to everyone.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                What's the difference between account types?
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionStarter">
                            <div class="accordion-body">
                                <strong>Secondary Student:</strong> Tailored for WAEC, NECO, and JAMB preparation with relevant subject materials.<br><br>
                                <strong>University Student:</strong> Provides access to university-specific course materials, a CGPA calculator, and department-level resources.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Study Materials -->
            <div class="faq-wrapper">
                <div class="category-header">
                    <div class="category-icon"><i class="fas fa-book-open"></i></div>
                    <h3 class="h4 mb-0">Study Materials</h3>
                </div>
                
                <div class="accordion" id="accordionMaterials">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMat1">
                                Can I download study materials for offline use?
                            </button>
                        </h2>
                        <div id="collapseMat1" class="accordion-collapse collapse" data-bs-parent="#accordionMaterials">
                            <div class="accordion-body">
                                Yes, most study materials (PDFs, Documents) available on our platform can be downloaded directly to your device for offline study. Look for the download icon on the material page.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMat2">
                                How do I contribute my own notes?
                            </button>
                        </h2>
                        <div id="collapseMat2" class="accordion-collapse collapse" data-bs-parent="#accordionMaterials">
                            <div class="accordion-body">
                                You can upload your own study materials by navigating to the "Upload Material" section in your dashboard. Fill in the details about the course or subject, attach your file, and submit. Our team will review it within 24 hours before publishing.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quizzes & Challenges -->
            <div class="faq-wrapper">
                <div class="category-header">
                    <div class="category-icon"><i class="fas fa-trophy"></i></div>
                    <h3 class="h4 mb-0">Quizzes & Challenges</h3>
                </div>
                
                <div class="accordion" id="accordionQuizzes">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseQuiz1">
                                How do challenge quizzes work?
                            </button>
                        </h2>
                        <div id="collapseQuiz1" class="accordion-collapse collapse" data-bs-parent="#accordionQuizzes">
                            <div class="accordion-body">
                                Challenge quizzes are time-limited competitive events where you can test your knowledge against other students. Everyone takes the same quiz, and a leaderboard ranks participants based on score and speed. Top performers may win prizes!
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseQuiz2">
                                Can I retake a quiz?
                            </button>
                        </h2>
                        <div id="collapseQuiz2" class="accordion-collapse collapse" data-bs-parent="#accordionQuizzes">
                            <div class="accordion-body">
                                Regular practice quizzes can be retaken as many times as you like to improve your score. Challenge quizzes, however, are typically limited to a single attempt to ensure fairness.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Issues -->
            <div class="faq-wrapper">
                <div class="category-header">
                    <div class="category-icon"><i class="fas fa-user-shield"></i></div>
                    <h3 class="h4 mb-0">Account & Security</h3>
                </div>
                
                <div class="accordion" id="accordionAccount">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAcc1">
                                I forgot my password, how can I reset it?
                            </button>
                        </h2>
                        <div id="collapseAcc1" class="accordion-collapse collapse" data-bs-parent="#accordionAccount">
                            <div class="accordion-body">
                                Go to the login page and click on the "Forgot Password?" link. Enter the email address associated with your account, and we'll send you a link to reset your password securely.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAcc2">
                                How can I change my profile picture?
                            </button>
                        </h2>
                        <div id="collapseAcc2" class="accordion-collapse collapse" data-bs-parent="#accordionAccount">
                            <div class="accordion-body">
                                Log in to your dashboard, click on your profile name in the top right corner (or the menu on mobile), and navigate to "Settings" or "Edit Profile". You can upload a new photo from there.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact CTA -->
            <div class="contact-cta">
                <h3 class="fw-bold mb-3">Still have questions?</h3>
                <p class="mb-4 opacity-75">Can't find the answer you're looking for? Please contact our friendly support team.</p>
                <a href="<?php echo SITE_URL; ?>/contact.php" class="btn btn-primary btn-lg rounded-pill px-5 shadow-lg">Contact Support</a>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
