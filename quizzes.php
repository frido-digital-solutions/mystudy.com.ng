<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/seo.php';
$page_title = 'Free CBT Practice Quizzes';
require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/models/Gamification.php';

$quizModel = new Quiz($pdo);
$gamification = new Gamification($pdo);
$userGamData = $gamification->getUserStats((int)($_SESSION['user_id'] ?? 0));
$userLevel = (int)$userGamData['level'];

$level = isset($_GET['level']) ? trim($_GET['level']) : null;
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Which examination the student is preparing for. This is the search students
// type most often - "waec cbt", "jamb practice test" - so the list answers it
// directly.
$exam = isset($_GET['exam']) ? strtoupper(trim((string)$_GET['exam'])) : '';
$exam = in_array($exam, ['WAEC', 'NECO', 'JAMB'], true) ? $exam : null;
if ($exam) {
    $page_title = sprintf('Free %s CBT Practice Tests and Past Questions', $exam);
    $page_description = sprintf(
        'Take free %s CBT practice tests and free %s past questions with answers. Every subject, timed like the real exam, marked the moment you finish.',
        $exam,
        $exam
    );
} else {
    $page_description = 'Free CBT practice quizzes for WAEC, NECO, JAMB and university students: past questions with answers, timed tests, and your score the moment you finish.';
}
$quizzes = $quizModel->list($limit, $offset, $level ?: null, $exam);
$totalCount = method_exists($quizModel, 'countAll') ? $quizModel->countAll($level ?: null, $exam) : count($quizzes);
$totalPages = ceil($totalCount / $limit) ?: 1;
$challenges = $quizModel->listChallenges($level ?: null, 8);

include __DIR__ . '/includes/header.php';
?>

<!-- Styles loaded from design-system.css -->

<!-- ===========================================
     PAGE HERO
     =========================================== -->
<div class="page-hero">
    <div class="container d-flex align-items-center justify-content-center gap-2 flex-wrap" style="position: relative; z-index: 1;">
        <div class="page-badge mb-0" style="margin-bottom: 0 !important;">
            <i class="fas fa-bolt"></i>
            CBT Practice
        </div>
        <h1 style="font-size: 1.3rem; font-weight: 800; color: #1e293b; margin: 0;">
            <?php echo $exam ? sprintf('Free %s CBT Practice Tests', $exam) : 'Practice Quizzes'; ?>
        </h1>
        <span style="color: #94a3b8; font-size: 0.85rem; display: none;" class="d-md-inline">— WAEC, NECO, JAMB & University Exams</span>
    </div>
</div>

<!-- The three examinations, one tap away. These pages carry the free past
     questions with answers, and they link back here - so a student who lands
     on a paper can find every other paper of the same examination. -->
<section class="py-3">
    <div class="container">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach (seo_exam_pages() as $examCard): ?>
                <a class="btn btn-sm <?php echo ($exam && $exam === $examCard['key']) ? 'btn-success' : 'btn-outline-success'; ?> px-3"
                   href="<?php echo SITE_URL; ?>/quizzes.php?exam=<?php echo urlencode($examCard['key']); ?>">
                    <?php echo htmlspecialchars($examCard['name']); ?> past questions
                </a>
                <a class="btn btn-sm btn-outline-secondary px-3" href="<?php echo SITE_URL; ?>/<?php echo $examCard['file']; ?>">
                    <?php echo htmlspecialchars($examCard['name']); ?> answers
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if (!empty($challenges)): ?>
<!-- ===========================================
     CHALLENGE ARENA (MOVED TO TOP)
     =========================================== -->
<section class="challenge-section">
    <div class="container">
        <div class="challenge-header">
            <div class="challenge-badge">
                <i class="fas fa-trophy"></i>
                Win Cash Prizes
            </div>
            <h2 class="challenge-title-main">Challenge Arena</h2>
            <p class="challenge-subtitle-main">Compete in live challenges and win amazing prizes</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($challenges as $cq): 
                $cid = (int)$cq['id'];
                $cTitle = htmlspecialchars($cq['title'] ?? '');
                $cSubject = htmlspecialchars($cq['subject'] ?? 'General');
                $cLevel = htmlspecialchars($cq['level'] ?? '');
                $startTs = !empty($cq['event_start']) ? strtotime($cq['event_start']) : null;
                $endTs = !empty($cq['event_end']) ? strtotime($cq['event_end']) : null;
                $nowTs = time();
                $hasStarted = $startTs ? ($nowTs >= $startTs) : true;
                $isOver = $endTs ? ($nowTs >= $endTs) : false;
                $prizeType = $cq['prize_type'] ?? '';
                $prizeAmount = $cq['prize_amount'] ?? null;
                $prizeText = $cq['prize_text'] ?? '';
                $isPaid = !empty($cq['is_paid']);
                $detailUrl = SITE_URL . '/quiz_special.php?id=' . $cid;

                $reqLevel = (int)($cq['required_level'] ?? 0);
                $isLocked = ($reqLevel > 0 && $userLevel < $reqLevel);
                $reqLevelName = Gamification::getLevelName($reqLevel, ($cLevel === 'University' ? 'university' : 'secondary'));

                // Fetch attached study materials for this quiz
                $matStmt = $pdo->prepare("SELECT m.id, m.title, m.original_name, m.file_path, m.file_size FROM quiz_materials qm JOIN materials m ON m.id = qm.material_id WHERE qm.quiz_id = ? AND m.status = 'approved' ORDER BY m.title ASC");
                $matStmt->execute([$cid]);
                $quizMaterials = $matStmt->fetchAll(PDO::FETCH_ASSOC);
                $hasMaterials = !empty($quizMaterials);
                
                $stmtCount = $pdo->prepare("SELECT COUNT(DISTINCT u) FROM (
                    SELECT used_by_user_id as u FROM quiz_access_codes WHERE quiz_id = ? AND used_by_user_id IS NOT NULL
                    UNION
                    SELECT user_id as u FROM quiz_attempts WHERE quiz_id = ?
                ) as participants");
                $stmtCount->execute([$cid, $cid]);
                $participantCount = (int)$stmtCount->fetchColumn();
                $maxParticipants = !empty($cq['max_participants']) ? (int)$cq['max_participants'] : null;
                $isFull = $maxParticipants && $participantCount >= $maxParticipants;
            ?>
            <div class="col-lg-6">
                <div class="challenge-card">
                    <div class="challenge-card-header">
                        <?php if ($prizeAmount !== null && $prizeType !== 'text'): ?>
                        <div class="prize-box">
                            <div class="prize-amount"><?php echo format_prize($prizeAmount, $prizeType); ?></div>
                            <div class="prize-label">Prize Pool</div>
                        </div>
                        <?php elseif ($prizeType === 'text' && $prizeText): ?>
                        <div class="prize-box">
                            <div class="prize-amount" style="font-size: 0.9rem;"><?php echo htmlspecialchars($prizeText); ?></div>
                            <div class="prize-label">Prize</div>
                        </div>
                        <?php else: ?>
                        <div></div>
                        <?php endif; ?>
                        
                        <div>
                            <?php if ($isOver): ?>
                                <span class="status-badge ended"><i class="fas fa-check me-1"></i> Ended</span>
                            <?php elseif (!$hasStarted): ?>
                                <span class="status-badge upcoming"><i class="fas fa-clock me-1"></i> Upcoming</span>
                            <?php elseif ($isFull): ?>
                                <span class="status-badge" style="background: rgba(239,68,68,0.2); color: #fca5a5; border: 1px solid rgba(239,68,68,0.4);"><i class="fas fa-ban me-1" style="font-size: 0.8rem;"></i> Full</span>
                            <?php else: ?>
                                <span class="status-badge live"><i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i> Live</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="challenge-card-body">
                        <div class="challenge-tags">
                            <span class="challenge-tag subject"><?php echo $cSubject ?: 'General'; ?></span>
                            <span class="challenge-tag <?php echo $cLevel === 'Secondary' ? 'level-secondary' : ($cLevel === 'University' ? 'level-university' : 'level-both'); ?>" style="<?php echo $cLevel === 'General' ? 'background:linear-gradient(135deg, #059669, #3b82f6); color:#fff; border:none;' : ''; ?>">
                                <?php echo $cLevel === 'General' ? 'General (Secondary & Univ)' : ($cLevel ?: 'All Levels'); ?>
                            </span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="challenge-card-title mb-0"><?php echo $cTitle; ?></h3>
                            <?php if ($maxParticipants): ?>
                            <span class="badge rounded-pill <?php echo $isFull ? 'bg-danger' : 'bg-primary'; ?>" style="font-size: 0.75rem;">
                                <?php echo $participantCount; ?> / <?php echo $maxParticipants; ?> Joined
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="challenge-timer">
                            <div class="timer-label" data-status-label>
                                <?php if ($isOver): ?>Challenge Ended
                                <?php elseif (!$hasStarted): ?>Time Left to Start
                                <?php else: ?>Time Left to End<?php endif; ?>
                            </div>
                            <div class="timer-display" data-countdown
                                 data-start="<?php echo $startTs ? (int)$startTs : ''; ?>"
                                 data-end="<?php echo $endTs ? (int)$endTs : ''; ?>">
                                <div class="timer-block">
                                    <div class="value" data-timer-days>00</div>
                                    <div class="unit">Days</div>
                                </div>
                                <span class="timer-separator">:</span>
                                <div class="timer-block">
                                    <div class="value" data-timer-hours>00</div>
                                    <div class="unit">Hrs</div>
                                </div>
                                <span class="timer-separator">:</span>
                                <div class="timer-block">
                                    <div class="value" data-timer-mins>00</div>
                                    <div class="unit">Min</div>
                                </div>
                                <span class="timer-separator">:</span>
                                <div class="timer-block">
                                    <div class="value" data-timer-secs>00</div>
                                    <div class="unit">Sec</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="challenge-card-footer">
                        <?php if ($isOver): ?>
                            <a href="<?php echo $detailUrl; ?>" class="btn-challenge btn-challenge-disabled">
                                <i class="fas fa-flag-checkered"></i> View Results
                            </a>
                        <?php elseif ($isFull): ?>
                            <a href="<?php echo $detailUrl; ?>" class="btn-challenge" style="background: #ef4444; color: #fff;">
                                <i class="fas fa-ban"></i> Challenge Full
                            </a>
                        <?php elseif (!$hasStarted): ?>
                            <?php if ($hasMaterials): ?>
                            <a href="#" class="btn-challenge btn-challenge-secondary" data-bs-toggle="modal" data-bs-target="#studyModal<?php echo $cid; ?>" onclick="event.preventDefault()">
                                <i class="fas fa-book-reader"></i> View Details
                            </a>
                            <?php else: ?>
                            <a href="<?php echo $detailUrl; ?>" class="btn-challenge btn-challenge-secondary">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <?php endif; ?>
                        <?php elseif ($isLocked): ?>
                            <button type="button" class="btn-challenge" onclick="showLevelLockModal(<?php echo $reqLevel; ?>, '<?php echo htmlspecialchars(addslashes($reqLevelName)); ?>')" style="background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.3);">
                                <i class="fas fa-lock"></i> Locked (Lvl <?php echo $reqLevel; ?>+ Required)
                            </button>
                        <?php else: ?>
                            <a href="<?php echo $detailUrl; ?>" class="btn-challenge btn-challenge-primary">
                                <i class="fas fa-bolt"></i> Join Challenge
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Study Materials Modals (rendered outside the grid for proper stacking) -->
        <?php foreach ($challenges as $cq2):
            $cid2 = (int)$cq2['id'];
            $cTitle2 = htmlspecialchars($cq2['title'] ?? '');
            $startTs2 = !empty($cq2['event_start']) ? strtotime($cq2['event_start']) : null;
            $endTs2 = !empty($cq2['event_end']) ? strtotime($cq2['event_end']) : null;
            $hasStarted2 = $startTs2 ? (time() >= $startTs2) : true;
            $isOver2 = $endTs2 ? (time() >= $endTs2) : false;
            if ($hasStarted2 || $isOver2) continue; // Only render modals for upcoming quizzes
            
            $matStmt2 = $pdo->prepare("SELECT m.id, m.title, m.original_name, m.file_path, m.file_size FROM quiz_materials qm JOIN materials m ON m.id = qm.material_id WHERE qm.quiz_id = ? AND m.status = 'approved' ORDER BY m.title ASC");
            $matStmt2->execute([$cid2]);
            $quizMaterials2 = $matStmt2->fetchAll(PDO::FETCH_ASSOC);
            if (empty($quizMaterials2)) continue;
        ?>
        <div class="modal fade" id="studyModal<?php echo $cid2; ?>" tabindex="-1" aria-labelledby="studyModalLabel<?php echo $cid2; ?>" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden;">
              <div class="modal-header border-0 pb-0" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 2rem 2rem 1.5rem;">
                <div>
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge rounded-pill" style="background: rgba(16,185,129,0.2); color: #34d399; font-size: 0.75rem; padding: 0.4rem 0.8rem;"><i class="fas fa-clock me-1"></i> Upcoming Quiz</span>
                  </div>
                  <h5 class="modal-title text-white fw-bold" id="studyModalLabel<?php echo $cid2; ?>" style="font-size: 1.3rem;">
                    <i class="fas fa-book-reader me-2" style="color: #60a5fa;"></i><?php echo $cTitle2; ?>
                  </h5>
                  <?php if ($startTs2): ?>
                  <p class="text-white-50 mb-0 mt-1" style="font-size: 0.85rem;"><i class="fas fa-calendar-alt me-1"></i> Starts: <?php echo date('M j, Y \a\t g:i A', $startTs2); ?></p>
                  <?php endif; ?>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body" style="padding: 1.5rem 2rem;">
                <div class="alert border-0 mb-4" style="background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(16,185,129,0.08)); border-radius: 12px; padding: 1rem 1.25rem;">
                  <div class="d-flex align-items-start gap-2">
                    <i class="fas fa-info-circle mt-1" style="color: #3b82f6;"></i>
                    <div>
                      <strong style="color: #1e293b;">Study These Materials</strong>
                      <p class="mb-0 text-muted" style="font-size: 0.85rem;">The questions in this quiz will be based on the materials below. Download and study them before the quiz starts.</p>
                    </div>
                  </div>
                </div>
                
                <div class="list-group" style="gap: 0.75rem;">
                  <?php foreach ($quizMaterials2 as $qm): 
                    $displayName = !empty($qm['original_name']) ? $qm['original_name'] : $qm['title'];
                    $ext = strtoupper(pathinfo($qm['file_path'], PATHINFO_EXTENSION));
                    $sizeKB = $qm['file_size'] ? round($qm['file_size'] / 1024) : null;
                    $sizeDisplay = $sizeKB ? ($sizeKB > 1024 ? round($sizeKB / 1024, 1) . ' MB' : $sizeKB . ' KB') : '';
                    $iconClass = 'fas fa-file';
                    $iconColor = '#64748b';
                    if (in_array($ext, ['PDF'])) { $iconClass = 'fas fa-file-pdf'; $iconColor = '#ef4444'; }
                    if (in_array($ext, ['DOC','DOCX'])) { $iconClass = 'fas fa-file-word'; $iconColor = '#3b82f6'; }
                    if (in_array($ext, ['PPT','PPTX'])) { $iconClass = 'fas fa-file-powerpoint'; $iconColor = '#f59e0b'; }
                    if (in_array($ext, ['XLS','XLSX'])) { $iconClass = 'fas fa-file-excel'; $iconColor = '#10b981'; }
                  ?>
                  <div class="list-group-item border-0 d-flex align-items-center justify-content-between" style="background: #f8fafc; border-radius: 12px; padding: 1rem 1.25rem;">
                    <div class="d-flex align-items-center gap-3">
                      <div style="width: 44px; height: 44px; border-radius: 10px; background: <?php echo $iconColor; ?>15; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="<?php echo $iconClass; ?>" style="font-size: 1.2rem; color: <?php echo $iconColor; ?>;"></i>
                      </div>
                      <div>
                        <div class="fw-semibold" style="font-size: 0.9rem; color: #1e293b;"><?php echo htmlspecialchars($qm['title']); ?></div>
                        <div class="text-muted" style="font-size: 0.78rem;">
                          <?php echo $ext; ?>
                          <?php if ($sizeDisplay): ?> · <?php echo $sizeDisplay; ?><?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/download.php?id=<?php echo (int)$qm['id']; ?>" class="btn btn-sm px-3" style="background: linear-gradient(135deg, #10b981, #059669); color: #fff; border-radius: 8px; font-weight: 600; font-size: 0.8rem;">
                      <i class="fas fa-download me-1"></i> Download
                    </a>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="modal-footer border-0" style="padding: 0.75rem 2rem 1.5rem; justify-content: center;">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Close</button>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
(function(){
    function updateChallengeTimers(){
        var now = Math.floor(Date.now()/1000);
        document.querySelectorAll('[data-countdown]').forEach(function(el){
            var start = parseInt(el.getAttribute('data-start'), 10) || 0;
            var end = parseInt(el.getAttribute('data-end'), 10) || 0;
            var label = el.closest('.challenge-timer').querySelector('[data-status-label]');
            
            var currentState = el.getAttribute('data-current-state');
            var newState = '';
            var timeLeft = 0;
            
            if (end && now >= end) { 
                newState = 'ended';
                label && (label.textContent = 'Challenge Ended'); 
                timeLeft = 0; 
            } else if (start && now < start) { 
                newState = 'upcoming';
                label && (label.textContent = 'Time Left to Start'); 
                timeLeft = start - now; 
            } else if (end) { 
                newState = 'active';
                label && (label.textContent = 'Time Left to End'); 
                timeLeft = end - now; 
            }
            
            if (!currentState) {
                el.setAttribute('data-current-state', newState);
            } else if (currentState !== newState) {
                // State transitioned (e.g. upcoming -> active). Auto reload!
                setTimeout(function(){ window.location.reload(); }, 500);
                return;
            }
            
            var days = Math.floor(timeLeft / 86400);
            timeLeft %= 86400;
            var hours = Math.floor(timeLeft / 3600);
            timeLeft %= 3600;
            var mins = Math.floor(timeLeft / 60);
            var secs = timeLeft % 60;
            
            function pad(n){ return (n<10?'0':'')+n; }
            
            var daysEl = el.querySelector('[data-timer-days]');
            var hoursEl = el.querySelector('[data-timer-hours]');
            var minsEl = el.querySelector('[data-timer-mins]');
            var secsEl = el.querySelector('[data-timer-secs]');
            
            if (daysEl) daysEl.textContent = pad(days);
            if (hoursEl) hoursEl.textContent = pad(hours);
            if (minsEl) minsEl.textContent = pad(mins);
            if (secsEl) secsEl.textContent = pad(secs);
        });
    }
    updateChallengeTimers();
    setInterval(updateChallengeTimers, 1000);
})();
</script>
<?php endif; ?>

<!-- ===========================================
     COMPACT MULTI-SUBJECT BUILDER STRIP
     =========================================== -->
<div class="container" style="margin-top: 1.5rem; margin-bottom: 1.5rem; position: relative; z-index: 2;">
    <a href="quiz_multi.php" class="text-decoration-none d-block" style="border-radius: 14px; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(16,185,129,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
        <div class="d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); border-radius: 14px; padding: 0.85rem 1.25rem;">
            <div class="d-flex align-items-center gap-3" style="min-width: 0;">
                <div style="width: 36px; height: 36px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="fas fa-magic" style="color: #fff; font-size: 1rem;"></i>
                </div>
                <div style="min-width: 0;">
                    <div style="font-weight: 800; font-size: 0.9rem; color: #fff; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Custom Exam Builder</div>
                    <div style="font-size: 0.75rem; color: rgba(255,255,255,0.75); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Mix up to 4 subjects • Set your own timer</div>
                </div>
            </div>
            <div style="width: 30px; height: 30px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-left: 0.75rem;">
                <i class="fas fa-arrow-right" style="color: #fff; font-size: 0.75rem;"></i>
            </div>
        </div>
    </a>
</div>

<!-- ===========================================
     QUIZ GRID
     =========================================== -->
<section class="quiz-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Available Practice Quizzes</h2>
            <form class="filter-form" method="get" action="">
                <select name="level" class="filter-select">
                    <option value="">All Levels</option>
                    <option value="Secondary" <?php echo $level === 'Secondary' ? 'selected' : ''; ?>>Secondary School</option>
                    <option value="University" <?php echo $level === 'University' ? 'selected' : ''; ?>>University</option>
                </select>
            </form>
        </div>
        
        <?php if (empty($quizzes)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <h3 class="empty-state-title">No Quizzes Found</h3>
                <p class="empty-state-text">There are no quizzes available at the moment. Check back soon!</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($quizzes as $q): 
                    $qid = (int)$q['id'];
                    $subject = htmlspecialchars($q['subject'] ?? '');
                    $levelTxt = htmlspecialchars($q['level'] ?? '');
                    $count = $quizModel->getQuestionCount($qid);
                    $minutes = (int)ceil(((int)($q['duration_seconds'] ?? 900))/60);
                    $hasQuestions = $count > 0;
                    $isSecondary = ($q['level'] ?? '') === 'Secondary';

                    $reqLevel = (int)($q['required_level'] ?? 0);
                    $isLocked = ($reqLevel > 0 && $userLevel < $reqLevel);
                    $reqLevelName = Gamification::getLevelName($reqLevel, ($levelTxt === 'University' ? 'university' : 'secondary'));
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="quiz-card">
                        <div class="quiz-card-header">
                            <div class="quiz-tags">
                                <span class="quiz-subject-tag"><?php echo $subject ?: 'General'; ?></span>
                                <div class="exam-tags">
                                    <?php if ($levelTxt === 'Secondary'): ?>
                                        <?php 
                                        $et = array_key_exists('exam_types', $q) ? $q['exam_types'] : 'WAEC,NECO,JAMB';
                                        if ($et === null) $et = 'WAEC,NECO,JAMB';
                                        $etArr = $et === '' ? [] : explode(',', $et);
                                        ?>
                                        <?php if(in_array('WAEC', $etArr)): ?><span class="exam-tag waec">WAEC</span><?php endif; ?>
                                        <?php if(in_array('NECO', $etArr)): ?><span class="exam-tag neco">NECO</span><?php endif; ?>
                                        <?php if(in_array('JAMB', $etArr)): ?><span class="exam-tag jamb">JAMB</span><?php endif; ?>
                                    <?php elseif ($levelTxt === 'University'): ?>
                                        <span class="exam-tag uni">University</span>
                                    <?php else: ?>
                                        <span class="exam-tag" style="background:linear-gradient(135deg, #059669, #3b82f6); color:#fff; border:none; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">General (Secondary & Univ)</span>
                                    <?php endif; ?>

                                    <?php if ($reqLevel > 0): ?>
                                    <span class="exam-tag ms-auto" style="background:linear-gradient(135deg, #ef4444, #f87171); color:#fff; border:none; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 2px 6px rgba(239,68,68,0.3);">
                                        <i class="fas fa-lock me-1"></i> Lvl <?php echo $reqLevel; ?>+
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="quiz-card-body">
                            <h3 class="quiz-title"><?php echo htmlspecialchars($q['title']); ?></h3>
                            <p class="quiz-description">
                                <?php echo htmlspecialchars(mb_strimwidth($q['description'] ?? '', 0, 100, '...')); ?>
                            </p>
                            <div class="quiz-meta">
                                <span class="quiz-meta-item">
                                    <i class="far fa-clock"></i>
                                    <?php echo $hasQuestions ? $minutes . ' mins' : 'N/A'; ?>
                                </span>
                                <span class="quiz-meta-item">
                                    <i class="fas fa-question-circle"></i>
                                    <?php echo $count; ?> questions
                                </span>
                            </div>
                        </div>
                        
                        <div class="quiz-card-footer">
                            <?php if ($hasQuestions): ?>
                                <?php if ($isLocked): ?>
                                    <button type="button" class="btn-quiz btn-quiz-disabled" onclick="showLevelLockModal(<?php echo $reqLevel; ?>, '<?php echo htmlspecialchars(addslashes($reqLevelName)); ?>')" style="background: rgba(239,68,68,0.1); color: #ef4444; border-color: rgba(239,68,68,0.3);">
                                        <i class="fas fa-lock"></i> Locked (Lvl <?php echo $reqLevel; ?>+ Required)
                                    </button>
                                <?php else: ?>
                                    <a href="<?php echo SITE_URL; ?>/quiz.php?id=<?php echo $qid; ?>" class="btn-quiz btn-quiz-primary">
                                        <i class="fas fa-play"></i> Start Quiz
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn-quiz btn-quiz-disabled" disabled>
                                    Coming Soon
                                </button>
                            <?php endif; ?>
                            <div class="quiz-date">
                                Added <?php echo date('M j, Y', strtotime($q['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): 
                $queryParams = $_GET;
                unset($queryParams['page']);
                $qs = http_build_query($queryParams);
                $baseUrl = '?' . ($qs ? $qs . '&' : '') . 'page=';
            ?>
                <!-- Beautiful Pagination -->
                <nav class="mt-5 mb-3 d-flex justify-content-center">
                    <ul class="pagination pagination-lg shadow-sm" style="border-radius: 50px; overflow: hidden;">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link border-0 text-dark fw-bold px-4" href="<?php echo $baseUrl . ($page - 1); ?>">Prev</a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link border-0 fw-bold <?php echo $i === $page ? 'bg-primary text-white' : 'text-dark'; ?>" href="<?php echo $baseUrl . $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link border-0 text-dark fw-bold px-4" href="<?php echo $baseUrl . ($page + 1); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</section>

<!-- Level Lock Modal -->
<div class="modal fade" id="levelLockModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; background: #fff;">
      <div class="modal-header border-0 pb-0 justify-content-center pt-4">
        <div style="width: 80px; height: 80px; background: rgba(239,68,68,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; box-shadow: 0 4px 15px rgba(239,68,68,0.15);">
          <i class="fas fa-lock" style="font-size: 2.5rem; color: #ef4444;"></i>
        </div>
      </div>
      <div class="modal-body text-center" style="padding: 2rem;">
        <h4 class="fw-bold mb-3" style="color: #1e293b; font-family: 'Outfit', sans-serif;">Assessment Locked</h4>
        <p class="text-muted mb-4" style="font-size: 1rem; line-height: 1.6;">
          You need to be <strong style="color: #ef4444; font-weight: 800;">Level <span id="lockMissingLevel">X</span> (<span id="lockMissingName">Name</span>)</strong> or higher to unlock this assessment.<br><br>
          You are currently <strong style="color: #059669;">Level <span id="currentUserLevel"><?php echo $userLevel; ?></span></strong>. <br>Take more practice quizzes to earn XP and rank up!
        </p>
        <button type="button" class="btn btn-primary px-5 py-2 rounded-pill fw-bold" data-bs-dismiss="modal" style="background: linear-gradient(135deg, #10b981, #059669); border: none; font-family: 'Outfit', sans-serif;">I Understand</button>
      </div>
    </div>
  </div>
</div>

<script>
function showLevelLockModal(reqLevel, reqName) {
    document.getElementById('lockMissingLevel').textContent = reqLevel;
    document.getElementById('lockMissingName').textContent = reqName;
    var lockModal = new bootstrap.Modal(document.getElementById('levelLockModal'));
    lockModal.show();
}
</script>

<?php $hide_main_footer = true;
include __DIR__ . '/includes/footer.php'; ?>
