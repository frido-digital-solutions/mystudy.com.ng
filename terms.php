<?php
require_once __DIR__ . '/config/config.php';

$page_title = 'Terms of Service';
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

    .terms-hero {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        padding: 5rem 0;
        color: white;
        margin-bottom: 4rem;
        position: relative;
        overflow: hidden;
    }
    
    .terms-hero::after {
        content: '';
        position: absolute;
        bottom: -50px;
        right: 15%;
        width: 180px;
        height: 180px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }

    .terms-content {
        background: white;
        border-radius: 20px;
        padding: 3rem;
        box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.05);
        border: 1px solid #f1f5f9;
        margin-bottom: 3rem;
    }

    .last-updated {
        background: #ecfdf5;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        border-left: 5px solid var(--primary-color);
        margin-bottom: 2.5rem;
        color: #064e3b;
        font-weight: 500;
        display: inline-block;
    }
    
    .terms-content h2 {
        color: var(--text-dark);
        font-weight: 700;
        margin-top: 2.5rem;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .terms-content h3 {
        color: var(--primary-color);
        font-weight: 600;
        margin-top: 1.75rem;
        margin-bottom: 1rem;
        font-size: 1.2rem;
    }
    
    .terms-content p {
        color: var(--text-muted);
        line-height: 1.8;
        margin-bottom: 1rem;
    }
    
    .terms-content ul, .terms-content ol {
        margin-bottom: 1.5rem;
        padding-left: 1.5rem;
    }
    
    .terms-content li {
        color: var(--text-muted);
        margin-bottom: 0.5rem;
        line-height: 1.7;
    }
    
    .contact-box {
        background: #f8fafc;
        padding: 2rem;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        text-align: center;
        margin-top: 3rem;
    }
</style>

<div class="terms-hero text-center">
    <div class="container position-relative z-1">
        <h1 class="display-4 fw-bold mb-3">Terms of Service</h1>
        <p class="lead mb-0 opacity-90 mx-auto" style="max-width: 600px;">Please read these terms carefully before using our platform.</p>
    </div>
</div>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="terms-content">
                <div class="last-updated">
                    <i class="far fa-calendar-alt me-2"></i> Last Updated: <?php echo date('F d, Y'); ?>
                </div>

                <p class="lead mb-4">
                    Welcome to <?php echo SITE_NAME; ?>! By accessing or using our website, services, and tools, you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use our platform.
                </p>

                <h2>1. Acceptance of Terms</h2>
                <p>These Terms of Service constitute a legally binding agreement made between you, whether personally or on behalf of an entity ("you") and <?php echo SITE_NAME; ?> ("we," "us" or "our"), concerning your access to and use of our website as well as any other media form, media channel, mobile website or mobile application related, linked, or otherwise connected thereto.</p>

                <h2>2. User Accounts</h2>
                
                <h3>2.1 Registration</h3>
                <p>You may be required to register with the Site. You agree to keep your password confidential and will be responsible for all use of your account and password.</p>

                <h3>2.2 Account Responsibilities</h3>
                <p>You agree to provide accurate, current, and complete information during the registration process and to update such information to keep it accurate, current, and complete.</p>

                <h2>3. Intellectual Property Rights</h2>
                <p>Unless otherwise indicated, the Site is our proprietary property and all source code, databases, functionality, software, website designs, audio, video, text, photographs, and graphics on the Site (collectively, the "Content") and the trademarks, service marks, and logos contained therein (the "Marks") are owned or controlled by us or licensed to us, and are protected by copyright and trademark laws.</p>

                <h2>4. User Representations</h2>
                <p>By using the Site, you represent and warrant that:</p>
                <ul>
                    <li>All registration information you submit will be true, accurate, current, and complete.</li>
                    <li>You will maintain the accuracy of such information and promptly update such registration information as necessary.</li>
                    <li>You have the legal capacity and you agree to comply with these Terms of Service.</li>
                    <li>You are not a minor in the jurisdiction in which you reside, or if a minor, you have received parental permission to use the Site.</li>
                </ul>

                <h2>5. Prohibited Activities</h2>
                <p>You may not access or use the Site for any purpose other than that for which we make the Site available. The Site may not be used in connection with any commercial endeavors except those that are specifically endorsed or approved by us.</p>
                <p>As a user of the Site, you agree not to:</p>
                <ul>
                    <li>Systematically retrieve data or other content from the Site to create or compile, directly or indirectly, a collection, compilation, database, or directory without written permission from us.</li>
                    <li>Make any unauthorized use of the Site, including collecting usernames and/or email addresses of users by electronic or other means for the purpose of sending unsolicited email, or creating user accounts by automated means or under false pretenses.</li>
                    <li>Circumvent, disable, or otherwise interfere with security-related features of the Site.</li>
                </ul>

                <h2>6. Termination</h2>
                <p>We may terminate or suspend your account and bar access to the Service immediately, without prior notice or liability, under our sole discretion, for any reason whatsoever and without limitation, including but not limited to a breach of the Terms.</p>

                <h2>7. Limitation of Liability</h2>
                <p>In no event shall we, nor our directors, employees, partners, agents, suppliers, or affiliates, be liable for any indirect, incidental, special, consequential or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses, resulting from either your access to or use of or inability to access or use the Service.</p>

                <h2>8. Changes to Terms</h2>
                <p>We reserve the right, at our sole discretion, to modify or replace these Terms at any time. If a revision is material we will provide at least 30 days notice prior to any new terms taking effect. What constitutes a material change will be determined at our sole discretion.</p>

                <h2>9. Contact Us</h2>
                <div class="contact-box">
                    <h4 class="fw-bold mb-3">Questions about these Terms?</h4>
                    <p class="mb-4">Please contact us at <a href="mailto:<?php echo ADMIN_EMAIL; ?>" class="fw-bold text-primary text-decoration-none"><?php echo ADMIN_EMAIL; ?></a>.</p>
                    <address class="fst-normal text-muted mb-0">
                        <strong><?php echo SITE_NAME; ?></strong><br>
                        123 Education Way, Tech Hub<br>
                        Lagos, Nigeria
                    </address>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
