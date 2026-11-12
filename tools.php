<?php
require_once __DIR__ . '/config/config.php';
$page_title = 'Student Tools';
include __DIR__ . '/includes/header.php';
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

    .tools-hero {
        padding: 3rem 0;
        text-align: center;
        margin-bottom: 2rem;
    }

    .tools-title {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
        font-size: 2.5rem;
    }

    .tools-subtitle {
        color: var(--text-muted);
        font-size: 1.1rem;
        max-width: 600px;
        margin: 0 auto;
    }

    .tool-card {
        background: white;
        border: 1px solid #f1f5f9;
        border-radius: 20px;
        padding: 2rem;
        height: 100%;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .tool-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px -5px rgba(0, 0, 0, 0.1);
        border-color: var(--primary-color);
    }

    .tool-icon-wrapper {
        width: 70px;
        height: 70px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        margin-bottom: 1.5rem;
        transition: transform 0.3s ease;
    }

    .tool-card:hover .tool-icon-wrapper {
        transform: scale(1.1) rotate(5deg);
    }

    .bg-soft-primary { background-color: #ecfdf5; color: #10b981; }
    .bg-soft-indigo { background-color: #eef2ff; color: #6366f1; }
    .bg-soft-orange { background-color: #fff7ed; color: #f97316; }

    .tool-name {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
    }

    .tool-desc {
        color: var(--text-muted);
        font-size: 0.95rem;
        margin-bottom: 1.5rem;
        flex-grow: 1;
    }

    .btn-tool {
        width: 100%;
        padding: 0.75rem;
        border-radius: 12px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }

    .btn-outline-primary {
        color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn-outline-primary:hover {
        background-color: var(--primary-color);
        color: white;
    }
    
    .btn-outline-indigo {
        color: var(--secondary-color);
        border: 2px solid var(--secondary-color);
        background: transparent;
    }
    
    .btn-outline-indigo:hover {
        background-color: var(--secondary-color);
        color: white;
    }

</style>

<div class="container pb-5">
    <div class="tools-hero">
        <h1 class="tools-title">Student Tools</h1>
        <p class="tools-subtitle">Enhance your academic journey with our suite of productivity and planning tools.</p>
    </div>

    <div class="row g-4 justify-content-center">
        <!-- CGPA Calculator -->
        <div class="col-md-6 col-lg-4">
            <div class="tool-card">
                <div class="tool-icon-wrapper bg-soft-primary">
                    <i class="fas fa-calculator"></i>
                </div>
                <h3 class="tool-name">CGPA Calculator</h3>
                <p class="tool-desc">Calculate your Cumulative Grade Point Average easily. Track your academic performance semester by semester.</p>
                <a href="<?php echo SITE_URL; ?>/cgpa-calculator.php" class="btn btn-tool btn-outline-primary">
                    Open Calculator <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Timetable -->
        <div class="col-md-6 col-lg-4">
            <div class="tool-card">
                <div class="tool-icon-wrapper bg-soft-indigo">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3 class="tool-name">My Timetable</h3>
                <p class="tool-desc">Organize your weekly schedule. Keep track of lectures, tutorials, and study sessions in one place.</p>
                <a href="<?php echo SITE_URL; ?>/timetable.php" class="btn btn-tool btn-outline-indigo">
                    View Timetable <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
        
        <!-- More tools placeholder -->
        <?php /*
        <div class="col-md-6 col-lg-4">
            <div class="tool-card opacity-75">
                <div class="tool-icon-wrapper bg-soft-orange">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3 class="tool-name">Task Manager</h3>
                <p class="tool-desc">Coming soon! simpler way to manage your assignments and daily tasks.</p>
                <button class="btn btn-tool btn-light" disabled>
                    Coming Soon
                </button>
            </div>
        </div>
        */ ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
