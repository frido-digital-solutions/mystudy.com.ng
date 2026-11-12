<?php
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Cgpa.php';

$page_title = 'CGPA Calculator';
$cg = new Cgpa($pdo);

// Handle add entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!validate_csrf_token($_POST['csrf'] ?? '')) {
        set_flash_message('error', 'Invalid CSRF token.');
        header('Location: ' . SITE_URL . '/cgpa-calculator.php');
        exit;
    }
    $code = trim($_POST['course_code'] ?? '');
    $unit = (int)($_POST['course_unit'] ?? 0);
    $grade = strtoupper(trim($_POST['grade'] ?? ''));
    $sem = trim($_POST['semester'] ?? '');
    if ($code !== '' && $unit > 0 && in_array($grade, ['A','B','C','D','E','F'], true)) {
        try {
            $cg->add($_SESSION['user_id'], $code, $unit, $grade, $sem ?: null);
            set_flash_message('success', 'Entry added successfully.');
        } catch (Throwable $e) {
            log_error('CGPA add failed', ['err'=>$e->getMessage()]);
            set_flash_message('error', 'Could not save entry.');
        }
    } else {
        set_flash_message('error', 'Please enter valid course details.');
    }
    header('Location: ' . SITE_URL . '/cgpa-calculator.php');
    exit;
}

// Handle delete entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!validate_csrf_token($_POST['csrf'] ?? '')) {
        set_flash_message('error', 'Invalid CSRF token.');
        header('Location: ' . SITE_URL . '/cgpa-calculator.php');
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $cg->delete($_SESSION['user_id'], $id);
            set_flash_message('success', 'Entry removed.');
            header('Location: ' . SITE_URL . '/cgpa-calculator.php');
            exit;
        } catch (Throwable $e) {
            log_error('CGPA delete failed', ['err'=>$e->getMessage()]);
            set_flash_message('error', 'Could not remove entry.');
        }
    }
    header('Location: ' . SITE_URL . '/cgpa-calculator.php');
    exit;
}

$items = $cg->listByUser($_SESSION['user_id']);
$summary = $cg->compute($items);

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
        border: 1px solid #f1f5f9;
        border-radius: 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border: none;
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

    .table th {
        font-weight: 600;
        color: var(--text-muted);
        border-bottom-width: 1px;
        padding: 1rem;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background-color: #f8fafc;
    }
    
    .table td {
        padding: 1rem;
        vertical-align: middle;
        color: var(--text-dark);
        border-bottom: 1px solid #f1f5f9;
    }

    .gpa-card {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
    }

    .gpa-value {
        font-size: 3rem;
        font-weight: 800;
        line-height: 1;
    }

    .course-badge {
        width: 40px;
        height: 40px;
        background: #f1f5f9;
        color: var(--text-dark);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
    }
</style>

<div class="container py-4">
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">CGPA Calculator</h1>
            <p class="text-muted mb-0">Track your academic performance efficiently</p>
        </div>
        <a href="<?php echo SITE_URL; ?>/tools.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i>Back to Tools
        </a>
    </div>

    <div class="row g-4">
        <!-- Input Column -->
        <div class="col-lg-4">
            <!-- GPA Summary Card (Mobile/Desktop) -->
            <div class="card gpa-card mb-4">
                <div class="card-body text-center p-4">
                    <h6 class="text-white-50 mb-2">CUMULATIVE GPA</h6>
                    <div class="gpa-value mb-2"><?php echo number_format($summary['gpa'], 2); ?></div>
                    <div class="d-flex justify-content-center gap-3 text-white-50 small">
                        <span>Total Units: <strong><?php echo (int)$summary['total_units']; ?></strong></span>
                        <span>|</span>
                        <span>Total Points: <strong><?php echo number_format($summary['total_points'], 2); ?></strong></span>
                    </div>
                </div>
            </div>

            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-plus-circle me-2 text-primary"></i> Add Course
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Course Code</label>
                            <input class="form-control" name="course_code" placeholder="e.g. CSC 201" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Units</label>
                                <input type="number" min="1" class="form-control" name="course_unit" placeholder="e.g 3" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Grade</label>
                                <select class="form-select" name="grade" required>
                                    <option value="" selected disabled>Select</option>
                                    <option value="A">A (5.0)</option>
                                    <option value="B">B (4.0)</option>
                                    <option value="C">C (3.0)</option>
                                    <option value="D">D (2.0)</option>
                                    <option value="E">E (1.0)</option>
                                    <option value="F">F (0.0)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Semester <span class="text-muted fw-normal small">(Optional)</span></label>
                            <input class="form-control" name="semester" placeholder="e.g. 100L First Semester">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i> Save Course
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- List Column -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list-ul me-2 text-primary"></i> Course List</span>
                    <span class="badge bg-light text-dark border">
                        <?php echo count($items); ?> Courses
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Course</th>
                                    <th class="text-center">Units</th>
                                    <th class="text-center">Grade</th>
                                    <th class="text-center">Points</th>
                                    <th>Semester</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <div class="text-muted mb-2"><i class="fas fa-book-open fa-3x opacity-25"></i></div>
                                            <p class="text-muted">No courses added yet.</p>
                                            <small class="text-muted">Add your grades from the left panel to calculate GPA.</small>
                                        </td>
                                    </tr>
                                <?php else: foreach ($items as $it): $gp = Cgpa::gradePoint($it['grade_letter']); ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <div class="course-badge me-3">
                                                    <?php echo substr($it['course_code'], 0, 3); ?>
                                                </div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($it['course_code']); ?></div>
                                            </div>
                                        </td>
                                        <td class="text-center"><?php echo (int)$it['course_unit']; ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary border border-primary bg-opacity-10 rounded-pill px-3">
                                                <?php echo htmlspecialchars($it['grade_letter']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center fw-semibold"><?php echo number_format($gp * (int)$it['course_unit'], 2); ?></td>
                                        <td class="text-muted small"><?php echo htmlspecialchars($it['semester'] ?? '-'); ?></td>
                                        <td class="text-end pe-4">
                                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return confirm('Are you sure you want to delete this course?');" class="d-inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
                                                <button class="btn btn-sm btn-light text-danger border-0" type="submit" data-bs-toggle="tooltip" title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
