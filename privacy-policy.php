<?php
require_once __DIR__ . '/config/config.php';

$page_title = 'Privacy Policy';
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

    .policy-hero {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        padding: 5rem 0;
        color: white;
        margin-bottom: 4rem;
        position: relative;
        overflow: hidden;
    }
    
    .policy-hero::after {
        content: '';
        position: absolute;
        bottom: -50px;
        right: 10%;
        width: 200px;
        height: 200px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }

    .policy-content {
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
    
    .policy-content h2 {
        color: var(--text-dark);
        font-weight: 700;
        margin-top: 2.5rem;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .policy-content h3 {
        color: var(--primary-color);
        font-weight: 600;
        margin-top: 1.75rem;
        margin-bottom: 1rem;
        font-size: 1.2rem;
    }
    
    .policy-content p {
        color: var(--text-muted);
        line-height: 1.8;
        margin-bottom: 1rem;
    }
    
    .policy-content ul, .policy-content ol {
        margin-bottom: 1.5rem;
        padding-left: 1.5rem;
    }
    
    .policy-content li {
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

<div class="policy-hero text-center">
    <div class="container position-relative z-1">
        <h1 class="display-4 fw-bold mb-3">Privacy Policy</h1>
        <p class="lead mb-0 opacity-90 mx-auto" style="max-width: 600px;">We are committed to protecting your personal data.</p>
    </div>
</div>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="policy-content">
                <div class="last-updated">
                    <i class="far fa-calendar-alt me-2"></i> Last Updated: <?php echo date('F d, Y'); ?>
                </div>

                <p class="lead mb-4">
                    At <?php echo SITE_NAME; ?>, we value your trust and are committed to protecting your privacy. This Privacy Policy outlines how we collect, use, disclose, and safeguard your information when you access our platform.
                </p>

                <h2>1. Information We Collect</h2>
                
                <h3>1.1 Personal Information</h3>
                <p>When you register for an account, we may collect personal information such as:</p>
                <ul>
                    <li>Full Name</li>
                    <li>Email Address</li>
                    <li>Educational Information (Level, University, Department)</li>
                    <li>Profile Picture (Optional)</li>
                </ul>

                <h3>1.2 Usage Data</h3>
                <p>We automatically collect certain information when you visit, use, or navigate the platform. This information does not reveal your specific identity (like your name or contact information) but may include device and usage information, such as:</p>
                <ul>
                    <li>IP Address</li>
                    <li>Browser and device characteristics</li>
                    <li>Operating system</li>
                    <li>Language preferences</li>
                    <li>Referring URLs</li>
                    <li>Country, location, and information about how and when you use our platform</li>
                </ul>

                <h2>2. How We Use Your Information</h2>
                <p>We use the information we collect or receive:</p>
                <ul>
                    <li><strong>To facilitate account creation and logon process:</strong> If you choose to link your account with us to a third-party account (such as your Google or Facebook account), we use the information you allowed us to collect from those third parties to facilitate account creation and logon process.</li>
                    <li><strong>To send administrative information to you:</strong> We may use your personal information to send you product, service and new feature information and/or information about changes to our terms, conditions, and policies.</li>
                    <li><strong>To protect our Services:</strong> We may use your information as part of our efforts to keep our platform safe and secure (for example, for fraud monitoring and prevention).</li>
                    <li><strong>To improve our platform:</strong> We may use your information to analyze usage trends and improve user experience.</li>
                </ul>

                <h2>3. Disclosure of Your Information</h2>
                <p>We may share information we have collected about you in certain situations. Your information may be disclosed as follows:</p>
                
                <h3>3.1 By Law or to Protect Rights</h3>
                <p>If we believe the release of information about you is necessary to respond to legal process, to investigate or remedy potential violations of our policies, or to protect the rights, property, and safety of others, we may share your information as permitted or required by any applicable law, rule, or regulation.</p>

                <h3>3.2 Third-Party Service Providers</h3>
                <p>We may share your information with third parties that perform services for us or on our behalf, including stats analysis, email delivery, hosting services, customer service, and marketing assistance.</p>

                <h2>4. Security of Your Information</h2>
                <p>We use administrative, technical, and physical security measures to help protect your personal information. While we have taken reasonable steps to secure the personal information you provide to us, please be aware that despite our efforts, no security measures are perfect or impenetrable, and no method of data transmission can be guaranteed against any interception or other type of misuse.</p>

                <h2>5. Policy for Children</h2>
                <p>We do not knowingly solicit information from or market to children under the age of 13. If you become aware of any data we have collected from children under age 13, please contact us using the contact information provided below.</p>

                <h2>6. Contacts & Questions</h2>
                <div class="contact-box">
                    <h4 class="fw-bold mb-3">Have questions about this policy?</h4>
                    <p class="mb-4">If you have questions or comments about this policy, you may email us at <a href="mailto:<?php echo ADMIN_EMAIL; ?>" class="fw-bold text-primary text-decoration-none"><?php echo ADMIN_EMAIL; ?></a> or by post to:</p>
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
