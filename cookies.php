<?php
require_once __DIR__ . '/config/config.php';

$page_title = 'Cookie Policy';
include 'includes/header.php';
?>

<style>
    .policy-hero {
        background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);
        padding: 3rem 0;
        color: white;
        margin-bottom: 3rem;
    }
    
    .policy-content {
        background: white;
        border-radius: 16px;
        padding: 3rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        margin-bottom: 3rem;
    }
    
    .policy-content h2 {
        color: #2e7d32;
        font-weight: 700;
        margin-top: 2rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e8f5e9;
    }
    
    .policy-content h3 {
        color: #43a047;
        font-weight: 600;
        margin-top: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .policy-content ul, .policy-content ol {
        margin-left: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .policy-content li {
        margin-bottom: 0.5rem;
        line-height: 1.8;
    }
    
    .last-updated {
        background: #f8fdf9;
        padding: 1rem;
        border-radius: 8px;
        border-left: 4px solid #2e7d32;
        margin-bottom: 2rem;
    }
    
    .cookie-table {
        width: 100%;
        margin: 1.5rem 0;
        border-collapse: collapse;
    }
    
    .cookie-table th {
        background: #2e7d32;
        color: white;
        padding: 1rem;
        text-align: left;
        font-weight: 600;
    }
    
    .cookie-table td {
        padding: 1rem;
        border-bottom: 1px solid #e8f5e9;
    }
    
    .cookie-table tr:hover {
        background: #f8fdf9;
    }
    
    .cookie-type {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .cookie-essential {
        background: #c8e6c9;
        color: #1b5e20;
    }
    
    .cookie-functional {
        background: #b3e5fc;
        color: #01579b;
    }
    
    .cookie-analytics {
        background: #fff9c4;
        color: #f57f17;
    }
</style>

<div class="policy-hero">
    <div class="container">
        <h1><i class="fas fa-cookie-bite me-3"></i>Cookie Policy</h1>
        <p class="lead mb-0">How we use cookies on our platform</p>
    </div>
</div>

<div class="container">
    <div class="policy-content">
        <div class="last-updated">
            <strong><i class="fas fa-calendar-alt me-2"></i>Last Updated:</strong> <?php echo date('F d, Y'); ?>
        </div>

        <p class="lead">
            This Cookie Policy explains how <?php echo SITE_NAME; ?> uses cookies and similar tracking technologies when you visit our platform. Please read this policy carefully to understand how we use cookies and your choices regarding them.
        </p>

        <h2>1. What Are Cookies?</h2>
        <p>
            Cookies are small text files that are stored on your device (computer, smartphone, or tablet) when you visit a website. They help the website remember your preferences, login status, and other information to enhance your browsing experience.
        </p>
        <p>Cookies can be:</p>
        <ul>
            <li><strong>Session Cookies:</strong> Temporary cookies that expire when you close your browser</li>
            <li><strong>Persistent Cookies:</strong> Cookies that remain on your device until they expire or you delete them</li>
            <li><strong>First-Party Cookies:</strong> Set by the website you're visiting (<?php echo SITE_NAME; ?>)</li>
            <li><strong>Third-Party Cookies:</strong> Set by external services we use (e.g., analytics providers)</li>
        </ul>

        <h2>2. Why We Use Cookies</h2>
        <p>We use cookies for several important purposes:</p>
        <ul>
            <li><strong>Authentication:</strong> To keep you logged in during your session</li>
            <li><strong>Security:</strong> To protect against fraud and unauthorized access</li>
            <li><strong>Preferences:</strong> To remember your settings and preferences</li>
            <li><strong>Performance:</strong> To analyze how our platform is used and improve functionality</li>
            <li><strong>User Experience:</strong> To provide personalized content and features</li>
        </ul>

        <h2>3. Types of Cookies We Use</h2>

        <table class="cookie-table">
            <thead>
                <tr>
                    <th>Cookie Type</th>
                    <th>Purpose</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <span class="cookie-type cookie-essential">Essential</span><br>
                        <strong>Session Cookie</strong>
                    </td>
                    <td>
                        Maintains your login session and authentication state. Required for the platform to function properly.
                    </td>
                    <td>24 hours or until logout</td>
                </tr>
                <tr>
                    <td>
                        <span class="cookie-type cookie-essential">Essential</span><br>
                        <strong>CSRF Token</strong>
                    </td>
                    <td>
                        Protects against Cross-Site Request Forgery attacks. Ensures form submissions are legitimate.
                    </td>
                    <td>1 hour</td>
                </tr>
                <tr>
                    <td>
                        <span class="cookie-type cookie-functional">Functional</span><br>
                        <strong>Preferences</strong>
                    </td>
                    <td>
                        Remembers your settings, language preferences, and personalization choices.
                    </td>
                    <td>1 year</td>
                </tr>
                <tr>
                    <td>
                        <span class="cookie-type cookie-analytics">Analytics</span><br>
                        <strong>Usage Tracking</strong>
                    </td>
                    <td>
                        Helps us understand how users interact with the platform to improve user experience.
                    </td>
                    <td>2 years</td>
                </tr>
            </tbody>
        </table>

        <h2>4. Essential Cookies</h2>
        <h3>4.1 Authentication and Security</h3>
        <p>These cookies are necessary for the platform to work:</p>
        <ul>
            <li><strong>mystudy_session:</strong> Stores your login session (24 hours)</li>
            <li><strong>csrf_token:</strong> Prevents security attacks (1 hour)</li>
            <li><strong>secure_cookie:</strong> Enhances connection security (session-based)</li>
        </ul>
        <p><em>These cookies cannot be disabled as they are essential for core functionality.</em></p>

        <h2>5. Functional Cookies</h2>
        <h3>5.1 User Preferences</h3>
        <p>These cookies enhance your experience:</p>
        <ul>
            <li>Remember your quiz preferences and settings</li>
            <li>Store your display preferences (theme, layout)</li>
            <li>Maintain your language and regional settings</li>
            <li>Remember items in your dashboard</li>
        </ul>
        <p><em>You can disable these cookies, but some features may not work as expected.</em></p>

        <h2>6. Analytics Cookies</h2>
        <h3>6.1 Platform Analytics</h3>
        <p>We use analytics to understand how students use our platform:</p>
        <ul>
            <li>Pages visited and time spent on each page</li>
            <li>Quiz completion rates and performance</li>
            <li>Popular study materials and resources</li>
            <li>User navigation patterns and behaviors</li>
        </ul>
        <p>This data is aggregated and anonymous. It helps us improve our services and create better content.</p>

        <h2>7. Third-Party Cookies</h2>
        <p>We may use services from trusted third parties that may set their own cookies:</p>
        <ul>
            <li><strong>Google Analytics:</strong> Website analytics (if enabled)</li>
            <li><strong>Bootstrap CDN:</strong> UI framework delivery</li>
            <li><strong>Font Awesome CDN:</strong> Icon delivery</li>
        </ul>
        <p>These third parties have their own privacy and cookie policies, which we encourage you to review.</p>

        <h2>8. Managing Cookies</h2>
        
        <h3>8.1 Browser Controls</h3>
        <p>Most browsers allow you to control cookies through their settings. You can:</p>
        <ul>
            <li>View and delete existing cookies</li>
            <li>Block all cookies</li>
            <li>Block only third-party cookies</li>
            <li>Clear cookies when you close your browser</li>
            <li>Receive alerts when cookies are being set</li>
        </ul>

        <h3>8.2 How to Disable Cookies</h3>
        <p>Here's how to manage cookies in popular browsers:</p>
        <ul>
            <li><strong>Google Chrome:</strong> Settings → Privacy and Security → Cookies and other site data</li>
            <li><strong>Mozilla Firefox:</strong> Options → Privacy & Security → Cookies and Site Data</li>
            <li><strong>Safari:</strong> Preferences → Privacy → Cookies and website data</li>
            <li><strong>Microsoft Edge:</strong> Settings → Cookies and site permissions → Cookies and site data</li>
        </ul>

        <div class="alert alert-warning mt-3" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Warning:</strong> Disabling cookies may affect the functionality of <?php echo SITE_NAME; ?>. Some features may not work properly or at all without cookies enabled.
        </div>

        <h2>9. Do Not Track Signals</h2>
        <p>
            Some browsers support "Do Not Track" (DNT) signals. Currently, there is no industry standard for how to respond to DNT signals. We do not currently respond to DNT browser settings, but we respect your privacy choices through browser cookie controls.
        </p>

        <h2>10. Mobile Devices</h2>
        <p>
            If you access our platform through a mobile device, your device may allow you to control cookies through its settings app. Check your device's documentation for instructions on managing cookies and tracking.
        </p>

        <h2>11. Cookie Consent</h2>
        <p>
            By using <?php echo SITE_NAME; ?>, you consent to our use of cookies as described in this Cookie Policy. When you first visit our platform, you may see a cookie banner allowing you to accept or customize your cookie preferences.
        </p>

        <h2>12. Updates to This Policy</h2>
        <p>
            We may update this Cookie Policy from time to time to reflect changes in our practices or for legal reasons. We will notify you of any significant changes by posting a notice on our platform or sending an email notification.
        </p>

        <h2>13. Contact Us</h2>
        <p>If you have questions about our use of cookies, please contact us:</p>
        <p class="mb-2">
            <strong><i class="fas fa-envelope me-2"></i>Email:</strong> 
            <a href="mailto:<?php echo ADMIN_EMAIL; ?>"><?php echo ADMIN_EMAIL; ?></a>
        </p>

        <div class="alert alert-info mt-4" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Your Privacy Matters:</strong> We use cookies responsibly to improve your experience on <?php echo SITE_NAME; ?>. You're always in control of your cookie preferences.
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
