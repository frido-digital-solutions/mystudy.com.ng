<?php
require_once __DIR__ . '/config/config.php';

$page_title = 'Contact Us';
include 'includes/header.php';

// Handle Form Submission — store in DB
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if ($name && $email && $message) {
        try {
            // Ensure contact_messages table exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                subject VARCHAR(500) DEFAULT '',
                message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message]);
            
            set_flash_message('success', 'Thank you for contacting us! We will get back to you shortly.');
            header('Location: ' . SITE_URL . '/contact.php');
            exit;
        } catch (Exception $e) {
            $msg = 'Something went wrong. Please try again later.';
            $msgType = 'danger';
        }
    } else {
        $msg = 'Please fill in all required fields.';
        $msgType = 'danger';
    }
}
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

    .contact-hero {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        padding: 4rem 0;
        color: white;
        margin-bottom: 3rem;
        position: relative;
        overflow: hidden;
    }
    
    .contact-hero::after {
        content: '';
        position: absolute;
        bottom: -50px;
        right: -50px;
        width: 300px;
        height: 300px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }

    .contact-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 40px -10px rgba(0,0,0,0.1);
        padding: 2.5rem;
        height: 100%;
        border: 1px solid #f1f5f9;
    }

    .info-item {
        display: flex;
        align-items: flex-start;
        margin-bottom: 2rem;
    }

    .info-icon {
        width: 50px;
        height: 50px;
        background: #ecfdf5;
        color: var(--primary-color);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin-right: 1.25rem;
        flex-shrink: 0;
    }

    .info-content h5 {
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: var(--text-dark);
    }

    .info-content p, .info-content a {
        color: var(--text-muted);
        text-decoration: none;
        font-size: 0.95rem;
    }

    .form-label {
        font-weight: 500;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
    }

    .form-control {
        border-radius: 12px;
        padding: 0.8rem 1rem;
        border: 2px solid #e2e8f0;
        background-color: #f8fafc;
    }

    .form-control:focus {
        border-color: var(--primary-color);
        background-color: white;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
    }

    .btn-submit {
        background: var(--primary-color);
        color: white;
        padding: 0.8rem 2rem;
        border-radius: 12px;
        font-weight: 600;
        border: none;
        transition: all 0.3s;
        width: 100%;
    }

    .btn-submit:hover {
        background: #059669;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .map-container {
        border-radius: 20px;
        overflow: hidden;
        height: 100%;
        min-height: 300px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }
</style>

<div class="contact-hero">
    <div class="container text-center">
        <h1 class="fw-bold display-5 mb-3">Get in Touch</h1>
        <p class="lead mb-0 opacity-75">We'd love to hear from you. Here's how you can reach us.</p>
    </div>
</div>

<div class="container pb-5">
    <?php echo display_flash_message(); ?>

    <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show mb-4 rounded-3 shadow-sm" role="alert">
            <?php echo htmlspecialchars($msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-5">
        <!-- Contact Info -->
        <div class="col-lg-5">
            <div class="contact-card">
                <h3 class="fw-bold mb-4">Contact Information</h3>
                
                                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="info-content">
                        <h5>Email Us</h5>
                        <p><a href="mailto:<?php echo htmlspecialchars(site_contact_email()); ?>"><?php echo htmlspecialchars(site_contact_email()); ?></a></p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <div class="info-content">
                        <h5>Call Us</h5>
                        <?php
                          $phoneDisplay = trim((string)site_setting('contact_phone', ''));
                          $phoneDigits = preg_replace('/\D+/', '', $phoneDisplay !== '' ? $phoneDisplay : WHATSAPP_NUMBER);
                          if ($phoneDisplay === '') { $phoneDisplay = '+' . $phoneDigits; }
                        ?>
                        <p><?php if ($phoneDigits !== ''): ?><a href="tel:+<?php echo htmlspecialchars($phoneDigits); ?>"><?php echo htmlspecialchars($phoneDisplay); ?></a><?php endif; ?><br>
                        <?php echo htmlspecialchars(site_setting('business_hours', 'Mon-Fri from 8am to 5pm')); ?></p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div class="info-content">
                        <h5>WhatsApp Us</h5>
                        <p><a href="https://wa.me/<?php echo htmlspecialchars(WHATSAPP_NUMBER); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-sm text-white">
                            <i class="fab fa-whatsapp me-2"></i>Chat on WhatsApp
                        </a></p>
                    </div>
                </div>
                
                <hr class="my-4 text-muted opacity-25">
                
                <h5 class="fw-bold mb-3">Follow Us</h5>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach (site_socials() as $network => $social): ?>
                    <a href="<?php echo htmlspecialchars($social['url']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary rounded-pill px-3 d-flex align-items-center gap-2">
                        <i class="fab <?php echo $social['icon']; ?>" style="color:<?php echo $social['color']; ?>;"></i> <?php echo htmlspecialchars($social['label']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Contact Form -->
        <div class="col-lg-7">
            <div class="contact-card">
                <h3 class="fw-bold mb-4">Send a Message</h3>
                <form method="post" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Your Name</label>
                            <input type="text" name="name" class="form-control" placeholder="John Doe" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="john@example.com" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-control" placeholder="How can we help?">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="5" placeholder="Type your message here..." required></textarea>
                        </div>
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-submit">
                                Send Message <i class="fas fa-paper-plane ms-2"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Map Section (Placeholder) -->
    <div class="row">
        <div class="col-12">
            <div class="map-container">
                <?php
                  $mapEmbed = trim((string)site_setting('map_embed_url', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d126839.06649725832!2d3.3106297314545564!3d6.524379346610196!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x103b8b2ae68280c1%3A0xdc9e87a367c3d9cb!2sLagos!5e0!3m2!1sen!2sng!4v1645524652233!5m2!1sen!2sng'));
                  // Only Google Maps embeds are allowed, so a saved value can never turn into an arbitrary iframe.
                  if (preg_match('~^https://(www\.google\.[a-z.]+|google\.[a-z.]+)/maps~i', $mapEmbed)):
                ?>
                <iframe src="<?php echo htmlspecialchars($mapEmbed); ?>" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
