<?php
require_once __DIR__ . '/config/config.php';
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance - <?php echo defined('SITE_NAME') ? SITE_NAME : 'MyStudy'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --accent-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --dark-bg: #0f0f23;
            --card-bg: rgba(255, 255, 255, 0.05);
            --text-primary: #ffffff;
            --text-secondary: #b8bcc8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark-bg);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
            background: linear-gradient(45deg, #0f0f23, #1a1a3e, #16213e, #0f3460);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Floating Elements */
        .floating-elements {
            position: fixed;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }

        .floating-element {
            position: absolute;
            border-radius: 50%;
            background: var(--primary-gradient);
            opacity: 0.1;
            animation: float 20s infinite ease-in-out;
        }

        .element-1 {
            width: 300px;
            height: 300px;
            top: -150px;
            left: -150px;
            animation-delay: 0s;
        }

        .element-2 {
            width: 200px;
            height: 200px;
            bottom: -100px;
            right: -100px;
            animation-delay: 5s;
        }

        .element-3 {
            width: 150px;
            height: 150px;
            top: 50%;
            left: 50%;
            animation-delay: 10s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }

        /* Main Container */
        .maintenance-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .maintenance-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 60px 40px;
            text-align: center;
            max-width: 800px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }

        .maintenance-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-gradient);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Icon Animation */
        .maintenance-icon {
            font-size: 120px;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 30px;
            animation: pulse 2s infinite, rotate 10s linear infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Typography */
        .maintenance-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #ffffff, #b8bcc8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .maintenance-subtitle {
            font-size: 1.5rem;
            color: var(--text-secondary);
            margin-bottom: 30px;
            font-weight: 300;
        }

        .maintenance-message {
            font-size: 1.1rem;
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Progress Bar */
        .progress-container {
            margin-bottom: 40px;
        }

        .progress {
            height: 8px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            overflow: hidden;
            position: relative;
        }

        .progress-bar {
            height: 100%;
            background: var(--primary-gradient);
            border-radius: 10px;
            animation: progress 3s ease-in-out infinite;
            position: relative;
            overflow: hidden;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmerProgress 2s infinite;
        }

        @keyframes progress {
            0% { width: 0%; }
            50% { width: 70%; }
            100% { width: 100%; }
        }

        @keyframes shimmerProgress {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Feature Cards */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 30px 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .feature-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .feature-desc {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        /* Contact Section */
        .contact-section {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 30px;
            margin-top: 30px;
        }

        .contact-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-primary);
        }

        .contact-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 15px;
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .contact-item i {
            color: #4facfe;
            font-size: 1.2rem;
        }

        /* Refresh Button */
        .refresh-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 15px 30px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        .refresh-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .refresh-btn i {
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Countdown Timer */
        .countdown {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 20px;
            background: var(--secondary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .countdown-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 30px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .maintenance-card {
                padding: 40px 20px;
            }

            .maintenance-title {
                font-size: 2rem;
            }

            .maintenance-subtitle {
                font-size: 1.2rem;
            }

            .maintenance-icon {
                font-size: 80px;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading Animation */
        .loading-dots {
            display: inline-flex;
            gap: 5px;
        }

        .loading-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary-gradient);
            animation: bounce 1.4s infinite ease-in-out both;
        }

        .loading-dot:nth-child(1) { animation-delay: -0.32s; }
        .loading-dot:nth-child(2) { animation-delay: -0.16s; }

        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation"></div>
    
    <!-- Floating Elements -->
    <div class="floating-elements">
        <div class="floating-element element-1"></div>
        <div class="floating-element element-2"></div>
        <div class="floating-element element-3"></div>
    </div>

    <!-- Main Content -->
    <div class="maintenance-container">
        <div class="maintenance-card">
            <!-- Main Icon -->
            <div class="maintenance-icon">
                <i class="fas fa-tools"></i>
            </div>
            
            <!-- Title Section -->
            <h1 class="maintenance-title">Under Maintenance</h1>
            <p class="maintenance-subtitle">We're making things better for you</p>
            
            <!-- Dynamic Message -->
            <div class="maintenance-message">
                <?php
                $default_message = "We're currently performing scheduled maintenance to enhance your learning experience. Our team is working hard to bring you improved features and better performance.";
                
                try {
                    global $pdo;
                    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'maintenance_message'");
                    $stmt->execute();
                    $db_message = $stmt->fetchColumn();
                    if ($db_message) {
                        echo htmlspecialchars($db_message);
                    } else {
                        echo $default_message;
                    }
                } catch (Exception $e) {
                    echo $default_message;
                }
                ?>
            </div>
            
            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="progress">
                    <div class="progress-bar"></div>
                </div>
                <small class="text-muted mt-2 d-block">
                    Maintenance in progress
                    <div class="loading-dots ms-2">
                        <div class="loading-dot"></div>
                        <div class="loading-dot"></div>
                        <div class="loading-dot"></div>
                    </div>
                </small>
            </div>
            
            <!-- Features Grid -->
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="feature-title">Performance Boost</div>
                    <div class="feature-desc">Lightning fast experience</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="feature-title">Enhanced Security</div>
                    <div class="feature-desc">Your data is safer</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-sparkles"></i>
                    </div>
                    <div class="feature-title">New Features</div>
                    <div class="feature-desc">Exciting updates coming</div>
                </div>
            </div>
            
            <!-- Countdown Timer -->
            <div class="countdown" id="countdown">00:00:00</div>
            <div class="countdown-label">Estimated time remaining</div>
            
            <!-- Contact Section -->
            <div class="contact-section">
                <h3 class="contact-title">Need Assistance?</h3>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <span><?php echo htmlspecialchars(site_contact_email()); ?></span>
                </div>
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <span><?php echo htmlspecialchars(trim((string)site_setting('contact_phone', '')) !== '' ? site_setting('contact_phone', '') : '+' . WHATSAPP_NUMBER); ?></span>
                </div>
                <div class="contact-item">
                    <i class="fas fa-clock"></i>
                    <span>We'll be back shortly</span>
                </div>
            </div>
            
            <!-- Refresh Button -->
            <button class="refresh-btn" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i>
                Check Status
            </button>
        </div>
    </div>

    <script>
        // Countdown Timer
        function updateCountdown() {
            const countdownEl = document.getElementById('countdown');
            const endTime = new Date();
            endTime.setHours(endTime.getHours() + 2); // 2 hours from now
            
            function update() {
                const now = new Date();
                const diff = endTime - now;
                
                if (diff <= 0) {
                    countdownEl.textContent = "00:00:00";
                    location.reload();
                    return;
                }
                
                const hours = Math.floor(diff / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                countdownEl.textContent = 
                    String(hours).padStart(2, '0') + ':' +
                    String(minutes).padStart(2, '0') + ':' +
                    String(seconds).padStart(2, '0');
            }
            
            update();
            setInterval(update, 1000);
        }
        
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
        
        // Initialize countdown
        updateCountdown();
        
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Animate progress bar restart
            const progressBar = document.querySelector('.progress-bar');
            setInterval(() => {
                progressBar.style.animation = 'none';
                setTimeout(() => {
                    progressBar.style.animation = 'progress 3s ease-in-out infinite';
                }, 100);
            }, 6000);
            
            // Add hover effect to feature cards
            const featureCards = document.querySelectorAll('.feature-card');
            featureCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>
