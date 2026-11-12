<?php
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Quiz.php';

$page_title = 'Create Multi-Subject Quiz';
$quizModel = new Quiz($pdo);

$freeQuizzes = $quizModel->getValidFreeQuizzes();

// Fallback icons if no mapping exists for the quiz subject
$iconMap = [
    'Mathematics' => 'fa-calculator text-primary',
    'Physics' => 'fa-atom text-info',
    'Chemistry' => 'fa-flask text-success',
    'Biology' => 'fa-dna text-success',
    'English' => 'fa-book-open text-warning',
    'Economics' => 'fa-chart-line text-primary',
    'Government' => 'fa-landmark text-secondary',
    'History' => 'fa-monument text-warning',
    'Geography' => 'fa-globe-africa text-success',
    'Computer Science' => 'fa-laptop-code text-dark',
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedIds = $_POST['quiz_ids'] ?? [];
    
    // Strict validation
    if (!is_array($selectedIds)) {
        $selectedIds = [];
    }
    
    $availableQuizIds = array_column($freeQuizzes, 'id');
    $validSelectedIds = array_intersect($selectedIds, $availableQuizIds);
    
    if (empty($validSelectedIds) || count($validSelectedIds) > 4) {
        $error = "Please select between 1 and 4 subjects/quizzes.";
    } else {
        // Custom timer input logic: convert minutes to seconds
        $customMins = (int)($_POST['custom_duration'] ?? 60);
        if ($customMins < 1) $customMins = 1;
        if ($customMins > 180) $customMins = 180; // Allow up to 3 hours just in case
        $duration = $customMins * 60;

        // Fetch questions evenly distributed across valid selected quizzes
        $limitPerQuiz = max(1, floor(40 / count($validSelectedIds))); // roughly 40 questions total
        $questions = $quizModel->getRandomQuestionsByQuizIds($validSelectedIds, $limitPerQuiz);
        if (empty($questions)) {
            $error = "Could not generate questions for the selected quizzes. They might be empty.";
        } else {
            // Find names of selected quizzes to build a dynamic title
            $selectedTitles = [];
            foreach ($freeQuizzes as $fq) {
                if (in_array($fq['id'], $validSelectedIds)) {
                    $selectedTitles[] = $fq['title'];
                }
            }
            $customTitle = count($selectedTitles) > 1 ? implode(' & ', $selectedTitles) : ($selectedTitles[0] ?? 'Custom Quiz');
            $customSubjectString = implode(', ', $selectedTitles);

            // Fetch the multi-subject sandbox quiz metadata
            $sandbox = $quizModel->getBySlug('multi-subject-sandbox');
            if (!$sandbox) {
                // quiz_attempts.quiz_id is a FK to quizzes(id) and quiz_submit.php
                // rejects id 0, so carry the attempt on a real selected quiz row
                $sandbox = ['id' => (int)reset($validSelectedIds), 'title' => 'Multi-Subject Sandbox'];
            }
            
            $_SESSION['custom_quiz'] = [
                'quiz_id' => $sandbox['id'],
                'custom_title' => mb_strimwidth($customTitle, 0, 100, '...'),
                'subjects' => $customSubjectString, // Use actual titles for the modal
                'questions' => $questions,
                'duration' => $duration,
                'expires' => time() + 86400 // Expire in 24 hours
            ];
            header("Location: " . SITE_URL . "/quiz.php?mode=multi");
            exit;
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
/* Core Layout Variations */
body {
    background-color: #f8fafc; /* Softer background */
}

/* Premium Hero Section */
.multi-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    color: #fff;
    padding: 6rem 0 4rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    margin-bottom: 4rem;
}

.multi-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 60%);
    animation: pulseGlow 15s infinite alternate linear;
    pointer-events: none;
}

@keyframes pulseGlow {
    0% { transform: scale(1) translate(0, 0); }
    100% { transform: scale(1.1) translate(5%, 5%); }
}

.multi-title-badge {
    background: rgba(16, 185, 129, 0.2);
    border: 1px solid rgba(16, 185, 129, 0.5);
    color: #10b981;
    padding: 0.5rem 1.25rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    display: inline-flex;
    align-items: center;
    margin-bottom: 1.5rem;
    backdrop-filter: blur(10px);
}

.multi-hero h1 {
    font-weight: 900;
    font-size: 3.5rem;
    letter-spacing: -1.5px;
    margin-bottom: 1rem;
    text-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.multi-hero .lead {
    font-size: 1.25rem;
    color: #cbd5e1;
    max-width: 600px;
    margin: 0 auto;
    font-weight: 400;
    line-height: 1.6;
}

/* Premium Subject Cards */
.subject-card {
    background: #fff;
    border: 2px solid transparent;
    border-radius: 20px;
    padding: 1.25rem 1rem;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    text-align: center;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.03);
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 1;
}

.subject-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    z-index: -1;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.subject-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
}

.subject-card:hover::before {
    opacity: 1;
}

.subject-card.selected {
    border-color: #10b981;
    background: #f0fdf4;
    box-shadow: 0 15px 35px rgba(16, 185, 129, 0.15);
    transform: translateY(-5px);
}

.subject-card.selected::before {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    opacity: 1;
}

.subject-card .check-indicator {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 28px;
    height: 28px;
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    color: transparent;
    font-size: 0.8rem;
}

.subject-card.selected .check-indicator {
    background: #10b981;
    border-color: #10b981;
    color: #fff;
    transform: scale(1.1);
}

.subject-icon {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    transition: transform 0.3s ease;
}

.subject-card:hover .subject-icon {
    transform: scale(1.1) rotate(5deg);
}

.subject-name {
    font-weight: 800;
    font-size: 1.15rem;
    color: #1e293b;
    letter-spacing: -0.5px;
}

.subject-checkbox {
    display: none;
}

/* Step Headers */
.step-header {
    display: inline-block;
    background: #1e293b;
    color: #fff;
    width: 40px;
    height: 40px;
    line-height: 40px;
    border-radius: 50%;
    text-align: center;
    font-weight: 800;
    font-size: 1.2rem;
    margin-right: 15px;
    box-shadow: 0 4px 10px rgba(30, 41, 59, 0.2);
}

.step-title {
    font-weight: 900;
    font-size: 1.8rem;
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.5rem;
    letter-spacing: -0.5px;
}

/* Premium Duration Pills */
.duration-selector {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.duration-selector .btn-check + .btn {
    border: 2px solid #e2e8f0;
    background: #fff;
    color: #475569;
    border-radius: 16px;
    padding: 1.25rem 2rem;
    font-weight: 700;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0,0,0,0.02);
}

.duration-selector .btn-check:checked + .btn {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-color: transparent;
    color: #fff;
    box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
    transform: translateY(-3px);
}

.duration-selector .btn:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.duration-selector .btn-check:checked + .btn:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
}

/* Submit Button CTA */
.cta-container {
    padding: 3rem 0;
    position: relative;
    border-top: 1px dashed #cbd5e1;
}

.btn-start-quiz {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    border-radius: 50px;
    padding: 1.25rem 3.5rem;
    font-weight: 800;
    font-size: 1.3rem;
    color: #fff;
    box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4);
    transition: all 0.3s ease;
    letter-spacing: 0.5px;
}

.btn-start-quiz:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 15px 35px rgba(16, 185, 129, 0.5);
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
    color: #fff;
}
</style>

<div class="multi-hero">
    <div class="container position-relative z-1">
        <div class="multi-title-badge">
            <i class="fas fa-magic me-2"></i> Custom Generator
        </div>
        <h1>Build Your Exam</h1>
        <p class="lead">Select up to 4 subjects to dynamically generate a hybrid practice test tailored to your WAEC, NECO, or JAMB needs.</p>
    </div>
</div>

<div class="container pb-5">
    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm border-0 mb-5 rounded-4 p-4 d-flex align-items-center"><i class="fas fa-exclamation-triangle fa-2x me-3 text-danger"></i> <div><strong>Hold on!</strong><br><?php echo htmlspecialchars($error); ?></div></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="row mb-5">
            <div class="col-md-10 mx-auto">
                <div class="text-center mb-4">
                    <h2 class="step-title"><span class="step-header">1</span> Choose Subjects</h2>
                    <p class="text-muted fs-5"><span id="subjectCount" class="fw-bold text-success">0</span>/4 Selected. Mix and match to create your perfect combo.</p>
                </div>
                
                <!-- Subject Filters -->
                <div class="d-flex justify-content-center flex-wrap gap-2 mb-4" id="subjectFilters">
                    <button type="button" class="btn btn-outline-success rounded-pill px-4 filter-btn active" data-filter="all">All Quizzes</button>
                    <button type="button" class="btn btn-outline-success rounded-pill px-4 filter-btn" data-filter="level-University">University</button>
                    <button type="button" class="btn btn-outline-success rounded-pill px-4 filter-btn" data-filter="level-Secondary">Secondary</button>
                    <button type="button" class="btn btn-outline-success rounded-pill px-4 filter-btn" data-filter="exam-waec">WAEC</button>
                    <button type="button" class="btn btn-outline-success rounded-pill px-4 filter-btn" data-filter="exam-neco">NECO</button>
                    <button type="button" class="btn btn-outline-success rounded-pill px-4 filter-btn" data-filter="exam-jamb">JAMB</button>
                </div>

                <div class="row g-3 mt-2" id="subjectGrid">
                    <?php if (empty($freeQuizzes)): ?>
                        <div class="col-12 text-center text-muted py-4">No available practice quizzes found for this level.</div>
                    <?php else: ?>
                        <?php foreach ($freeQuizzes as $fq): 
                            $rawSub = $fq['subject'] ?? 'General';
                            $iconClass = $iconMap[$rawSub] ?? 'fa-book text-secondary';
                            $safeLevel = htmlspecialchars($fq['level']);
                            $rawExamsStr = $fq['exam_types'] ?? '';
                            $examsArr = array_filter(array_map('trim', explode(',', $rawExamsStr)));
                            $safeExams = implode(',', array_map('strtolower', $examsArr));
                        ?>
                        <div class="col-6 col-md-4 col-lg-3 subject-col" data-level="<?php echo $safeLevel; ?>" data-exams="<?php echo htmlspecialchars($safeExams); ?>">
                            <label class="w-100 h-100 m-0">
                                <input type="checkbox" name="quiz_ids[]" value="<?php echo (int)$fq['id']; ?>" class="subject-checkbox" onchange="toggleSubjectCard(this)">
                                <div class="subject-card">
                                    <div class="check-indicator"><i class="fas fa-check"></i></div>
                                    <div class="subject-icon"><i class="fas <?php echo $iconClass; ?>"></i></div>
                                    <div class="subject-name" style="font-size: 1rem;"><?php echo htmlspecialchars($fq['title']); ?></div>
                                    <small class="text-muted d-block mt-2" style="font-size: 0.75rem;"><?php 
                                        echo ($fq['level'] === 'Secondary' ? 'Secondary' : ($fq['level']==='University' ? 'University' : 'General')); 
                                    ?></small>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row mb-5 pt-4">
            <div class="col-md-8 mx-auto text-center">
                <h2 class="step-title"><span class="step-header">2</span> Set Time Limit</h2>
                <p class="text-muted d-block mb-4 fs-5">Choose how long you want your practice session to be.</p>
                
                <div class="d-flex justify-content-center mt-3">
                    <div class="input-group" style="max-width: 300px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-radius: 16px; overflow: hidden; border: 2px solid #e2e8f0;">
                        <span class="input-group-text bg-white border-0 text-muted ps-4"><i class="fas fa-clock"></i></span>
                        <input type="number" class="form-control border-0 fw-bold text-center" name="custom_duration" min="1" max="60" value="60" style="font-size: 1.25rem; color: #1e293b;" required>
                        <span class="input-group-text bg-white border-0 text-muted fw-bold pe-4">Minutes</span>
                    </div>
                </div>
                <div class="mt-4 text-secondary bg-light d-inline-block px-4 py-2 rounded-pill"><i class="fas fa-info-circle me-1"></i> Enter a value between 1 and 60.</div>
            </div>
        </div>

        <div class="cta-container text-center mt-5">
            <button type="submit" class="btn btn-start-quiz">
                Generate & Start Quiz <i class="fas fa-arrow-right ms-2"></i>
            </button>
        </div>
    </form>
</div>

<script>
function toggleSubjectCard(checkbox) {
    var card = checkbox.nextElementSibling;
    
    // Check limit
    var checkedBoxes = document.querySelectorAll('.subject-checkbox:checked');
    if (checkedBoxes.length > 4 && checkbox.checked) {
        checkbox.checked = false;
        alert("You can only select up to 4 subjects.");
        return;
    }
    
    if (checkbox.checked) {
        card.classList.add('selected');
    } else {
        card.classList.remove('selected');
    }
    
    document.getElementById('subjectCount').textContent = checkedBoxes.length;
}

// Initial count on load
document.addEventListener('DOMContentLoaded', function() {
    var checkedBoxes = document.querySelectorAll('.subject-checkbox:checked');
    document.getElementById('subjectCount').textContent = checkedBoxes.length;
    checkedBoxes.forEach(function(box) {
        box.nextElementSibling.classList.add('selected');
    });
});

// Filter Logic
document.querySelectorAll('.filter-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        var filterVal = this.getAttribute('data-filter');
        var isLevelFilter = filterVal.startsWith('level-');
        var isExamFilter = filterVal.startsWith('exam-');
        var cleanFilter = filterVal.replace('level-', '').replace('exam-', '');
        
        document.querySelectorAll('.subject-col').forEach(function(col) {
            if (filterVal === 'all') {
                col.style.display = '';
                return;
            }
            
            var colLevel = col.getAttribute('data-level');
            var colExams = col.getAttribute('data-exams'); // comma separated
            
            if (isLevelFilter && colLevel === cleanFilter) {
                col.style.display = '';
            } else if (isExamFilter && colExams.includes(cleanFilter)) {
                col.style.display = '';
            } else {
                col.style.display = 'none';
            }
        });
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
