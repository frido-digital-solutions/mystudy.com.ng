<?php
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Timetable.php';

$page_title = 'My Timetable';
$tt = new Timetable($pdo);

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!validate_csrf_token($_POST['csrf'] ?? '')) {
        set_flash_message('error', 'Invalid CSRF token.');
        header('Location: ' . SITE_URL . '/timetable.php');
        exit;
    }
    $title = trim($_POST['title'] ?? '');
    $day   = (int)($_POST['day'] ?? 0);
    $start = trim($_POST['start_time'] ?? '');
    $end   = trim($_POST['end_time'] ?? '');
    $loc   = trim($_POST['location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    if ($title !== '' && $day >=1 && $day <=7 && $start !== '' && $end !== '') {
        try {
            $tt->add($_SESSION['user_id'], $title, $day, $start, $end, $loc ?: null, $notes ?: null);
            set_flash_message('success', 'Timetable entry added.');
        } catch (Throwable $e) {
            log_error('Timetable add failed', ['err'=>$e->getMessage()]);
            set_flash_message('error', 'Could not save timetable entry.');
        }
    } else {
        set_flash_message('error', 'Please fill in the required fields.');
    }
    header('Location: ' . SITE_URL . '/timetable.php');
    exit;
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!validate_csrf_token($_POST['csrf'] ?? '')) {
        set_flash_message('error', 'Invalid CSRF token.');
        header('Location: ' . SITE_URL . '/timetable.php');
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $tt->delete($_SESSION['user_id'], $id);
            set_flash_message('success', 'Entry removed.');
        } catch (Throwable $e) {
            log_error('Timetable delete failed', ['err'=>$e->getMessage()]);
            set_flash_message('error', 'Could not remove entry.');
        }
    }
    header('Location: ' . SITE_URL . '/timetable.php');
    exit;
}

$entries = $tt->listByUser($_SESSION['user_id']);
$days = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'];

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
        --card-bg: #ffffff;
        --text-dark: #1e293b;
        --text-muted: #64748b;
    }

    body {
        background-color: var(--bg-warm);
        font-family: 'Poppins', sans-serif;
    }

    .page-header {
        margin-bottom: 2rem;
    }

    .page-title {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
    }

    .card {
        border-radius: 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border: 1px solid #f1f5f9;
        margin-bottom: 1.5rem;
        background: var(--card-bg);
    }

    .card-header {
        background: white;
        border-bottom: 1px solid #f1f5f9;
        padding: 1.25rem 1.5rem;
        border-radius: 20px 20px 0 0 !important;
        font-weight: 600;
    }

    .card-body {
        padding: 1.5rem;
    }

    .form-control, .form-select {
        border-radius: 12px;
        padding: 0.75rem 1rem;
        border-color: #e2e8f0;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .form-label {
        font-weight: 500;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
    }

    .btn-primary {
        background: var(--primary-color);
        border-color: var(--primary-color);
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
    }

    .btn-primary:hover {
        background-color: #059669;
        border-color: #059669;
    }

    /* Timetable Specific */
    .day-header {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #f1f5f9;
    }

    .day-title {
        font-weight: 700;
        color: var(--text-dark);
        margin: 0;
        font-size: 1.1rem;
    }

    .class-card {
        background: #f8fafc;
        border-left: 4px solid var(--primary-color);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1rem;
        transition: transform 0.2s;
    }

    .class-card:hover {
        transform: translateX(5px);
        background: white;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    .class-time {
        font-weight: 600;
        color: var(--primary-color);
        font-size: 0.9rem;
        margin-bottom: 0.25rem;
    }

    .class-title {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.25rem;
    }

    .class-location {
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    .class-notes {
        font-size: 0.85rem;
        color: var(--text-muted);
        font-style: italic;
        margin-top: 0.5rem;
    }
    
    .btn-delete {
        color: #ef4444;
        background: #fef2f2;
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    
    .btn-delete:hover {
        background: #fee2e2;
        color: #dc2626;
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-muted);
    }
</style>

<div class="container py-4">
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">My Timetable</h1>
            <p class="text-muted mb-0">Manage your weekly schedule</p>
        </div>
        <a href="<?php echo SITE_URL; ?>/tools.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i>Back to Tools
        </a>
    </div>

    <div class="row g-4">
        <!-- Add Entry Column -->
        <div class="col-lg-4">
            <div class="card bg-white h-100">
                <div class="card-header">
                    <i class="fas fa-plus-circle me-2 text-primary"></i> Add Class/Event
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" placeholder="e.g. CSC 201" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Day</label>
                            <select class="form-select" name="day" required>
                                <option value="" selected disabled>Select Day</option>
                                <?php foreach ($days as $val=>$name): ?>
                                    <option value="<?php echo $val; ?>"><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control" name="start_time" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" name="end_time" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" placeholder="e.g. Hall A">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Optional notes..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-calendar-plus me-2"></i> Add to Timetable
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Timetable Display Column -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="far fa-calendar-alt me-2 text-primary"></i> Weekly Schedule</span>
                </div>
                <div class="card-body">
                    <?php if (empty($entries)): ?>
                        <div class="empty-state">
                            <i class="far fa-calendar-times fa-3x mb-3 opacity-25"></i>
                            <h5>No classes added yet</h5>
                            <p>Add your lectures and study sessions using the form.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                        <?php foreach ($days as $dval=>$dname):
                            $dayEntries = array_values(array_filter($entries, fn($e) => (int)$e['day_of_week'] === $dval));
                            if (!$dayEntries) continue; 
                            
                            // Sort by start time
                            usort($dayEntries, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));
                            ?>
                            <div class="col-md-6 col-12 mb-4">
                                <div class="day-header">
                                    <h5 class="day-title"><?php echo $dname; ?></h5>
                                    <span class="badge bg-light text-muted ms-2 rounded-pill"><?php echo count($dayEntries); ?></span>
                                </div>
                                
                                <?php foreach ($dayEntries as $e): ?>
                                <div class="class-card position-relative">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="class-time">
                                                <i class="far fa-clock me-1"></i>
                                                <?php echo date('g:ia', strtotime($e['start_time'])); ?> - <?php echo date('g:ia', strtotime($e['end_time'])); ?>
                                            </div>
                                            <div class="class-title"><?php echo htmlspecialchars($e['title']); ?></div>
                                            <?php if(!empty($e['location'])): ?>
                                                <div class="class-location">
                                                    <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($e['location']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($e['notes'])): ?>
                                                <div class="class-notes"><?php echo htmlspecialchars($e['notes']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return confirm('Remove this entry?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int)$e['id']; ?>">
                                            <button class="btn-delete" type="submit" data-bs-toggle="tooltip" title="Remove">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
