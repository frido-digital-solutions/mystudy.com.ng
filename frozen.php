<?php
require_once __DIR__ . '/config/config.php';

// If user is NOT logged in or NOT frozen, redirect them away to prevent direct viewing
if (!isset($_SESSION['user_id'])) {
    redirect(SITE_URL . '/login.php');
}

// Check database explicitly to ensure they are actually frozen
$stmt = $pdo->prepare("SELECT is_frozen, status FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userStatus = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userStatus || (int)$userStatus['is_frozen'] !== 1) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Account Suspended';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?> - MyStudy Arena</title>
    <!-- Use Bootstrap and FA from CDNs -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;700;800;900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --frozen-bg: #0f172a;
            --frozen-accent: #0284c7;
            --frozen-glow: #38bdf8;
            --text-primary: #f8fafc;
        }

        body {
            background-color: var(--frozen-bg);
            background-image: radial-gradient(circle at 50% -20%, #1e3a8a 0%, #0f172a 60%);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        h1, h2, h3, h4, h5, h6, .outfit-font {
            font-family: 'Outfit', sans-serif;
        }

        .floating-ice {
            position: absolute;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(5px);
            border-radius: 20%;
            animation: float 20s infinite linear;
            z-index: 0;
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 0 20px rgba(56, 189, 248, 0.1);
        }

        @keyframes float {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-20vh) rotate(360deg); opacity: 0; }
        }

        .frozen-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(56, 189, 248, 0.2);
            border-radius: 24px;
            padding: 3rem;
            max-width: 500px;
            width: 90%;
            text-align: center;
            position: relative;
            z-index: 10;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255,255,255,0.1);
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .frozen-icon-wrapper {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(2, 132, 199, 0.2), rgba(56, 189, 248, 0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            position: relative;
            border: 1px solid rgba(56, 189, 248, 0.3);
            box-shadow: 0 0 30px rgba(2, 132, 199, 0.4);
        }

        .frozen-icon-wrapper::after {
            content: '';
            position: absolute;
            inset: -10px;
            border-radius: 50%;
            border: 2px dashed rgba(56, 189, 248, 0.4);
            animation: spin 15s infinite linear;
        }

        @keyframes spin { 100% { transform: rotate(360deg); } }

        .frozen-icon {
            font-size: 3rem;
            color: var(--frozen-glow);
            text-shadow: 0 0 15px rgba(56, 189, 248, 0.8);
        }

        .title {
            font-size: 2.25rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(to right, #e0f2fe, #7dd3fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .description {
            color: #94a3b8;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
            font-weight: 400;
        }

        .stats-box {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
        }

        .stat {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ef4444; /* Red for strikes */
            font-family: 'Outfit', sans-serif;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .action-btn {
            background: linear-gradient(135deg, #0284c7, #0369a1);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(2, 132, 199, 0.4);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(2, 132, 199, 0.6);
            background: linear-gradient(135deg, #0369a1, #075985);
            color: white;
        }
        
        .logout-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
            font-size: 0.95rem;
        }
        
        .logout-link:hover {
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <!-- Background Particles -->
    <div class="floating-ice" style="width: 40px; height: 40px; left: 10%; animation-duration: 25s; animation-delay: 0s;"></div>
    <div class="floating-ice" style="width: 70px; height: 70px; left: 30%; animation-duration: 35s; animation-delay: 5s;"></div>
    <div class="floating-ice" style="width: 25px; height: 25px; left: 60%; animation-duration: 18s; animation-delay: 2s;"></div>
    <div class="floating-ice" style="width: 50px; height: 50px; left: 85%; animation-duration: 28s; animation-delay: 8s;"></div>

    <div class="frozen-card">
        <div class="frozen-icon-wrapper">
            <i class="fa-solid fa-snowflake frozen-icon"></i>
        </div>
        
        <h1 class="title outfit-font">Account Frozen</h1>
        
        <p class="description">
            Your account has been temporarily frozen due to repeated community guideline violations. You currently have limited access to the platform.
        </p>

        <div class="stats-box">
            <div class="stat">
                <span class="stat-value">3</span>
                <span class="stat-label">Strikes Reached</span>
            </div>
            <div style="width: 1px; height: 40px; background: rgba(255,255,255,0.1);"></div>
            <div class="stat text-start">
                <span class="stat-value" style="color: #64748b; font-size: 1rem;"><i class="fa-solid fa-lock me-1"></i> Locked</span>
                <span class="stat-label">Platform Access</span>
            </div>
        </div>

        <a href="mailto:<?php echo ADMIN_EMAIL; ?>?subject=Frozen Account Review: <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>" class="action-btn">
            <i class="fa-solid fa-headset"></i> Contact Support
        </a>
        
        <br>
        <a href="<?php echo SITE_URL; ?>/logout.php" class="logout-link"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Sign out to switch accounts</a>
    </div>
</body>
</html>
