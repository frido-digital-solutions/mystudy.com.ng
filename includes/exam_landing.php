<?php
/**
 * The page one examination gets.
 *
 * Every page here answers the same four questions in the same order - what is
 * this, what is on it, how do I use it, and how do other students do it - and
 * every one of them ends with a way to start a paper, because a page that
 * cannot be acted on is a page a search engine sends people away from.
 *
 * The look lives here, in its own stylesheet, the way the Study pages do it,
 * so that these pages look like one family and cannot be broken by another
 * page's change to the shared stylesheet.
 *
 * @param array $exam one of seo_exam_pages()
 * @param string $subject when set, the page is about one subject of the exam
 */
function seo_render_exam_page(array $exam, ?string $subject = null): void {
    // The page's own database handle. The function is called from the page,
    // so the connection is the one that page opened.
    global $pdo;
    $examKey = $exam['key'];
    $subjects = seo_exam_subjects($pdo, $examKey);
    $papers = seo_exam_papers($pdo, $examKey, $subject ?: '', 12);
    $others = array_values(array_filter(seo_exam_pages(), static fn($e) => $e['key'] !== $examKey));

    // The Study library holds the same subject under its own name. Finding it
    // lets a student move from a timed test to the real paper, year by year.
    $studySlug = null;
    try {
        require_once __DIR__ . '/../models/Study.php';
        $studySlug = (new Study($pdo))->slugForSubjectName($examKey, (string)$subject);
    } catch (Throwable $e) {
        $studySlug = null;
    }

    $h1 = $subject
        ? sprintf('Free %s %s past questions and answers', $examKey, $subject)
        : sprintf('Free %s past questions and answers', $examKey);

    // Two short sentences. A student reads the first line and acts; anything
    // longer is filler that pushes the buttons off the screen.
    $intro = $subject
        ? sprintf(
            'Every %s %s past question with its answers. Take it as a timed CBT test and see your score the moment you finish.',
            $examKey,
            $subject
        )
        : sprintf(
            'Every %s past question with its answers, and a timed CBT test you can take right now. Free, for every subject.',
            $examKey
        );
    $logo = seo_exam_logo($examKey);
    ?>
    <style>
    .el-wrap{max-width:1080px;margin:0 auto;padding:26px 16px 60px}
    .el-crumbs{font-size:.86rem;color:#64748b;margin-bottom:22px}
    .el-crumbs a{color:#475569;text-decoration:none}
    .el-crumbs a:hover{color:#059669}
    .el-crumbs .sep{margin:0 7px;color:#cbd5e1}
    .el-crumbs .here{color:#0f172a;font-weight:600}

    .el-head{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:26px 28px;margin-bottom:30px;box-shadow:0 1px 3px rgba(15,23,42,.05)}
    .el-head-top{display:flex;align-items:center;gap:15px;flex-wrap:wrap}
    .el-head-top img{width:58px;height:58px;object-fit:contain;flex:0 0 auto}
    .el-head h1{margin:0;font-size:clamp(1.5rem,3.4vw,2.25rem);font-weight:800;color:#0f172a;letter-spacing:-.025em;line-height:1.15}
    .el-head p{margin:14px 0 0;max-width:68ch;font-size:1.01rem;line-height:1.65;color:#475569}
    .el-cta{display:flex;flex-wrap:wrap;gap:11px;margin-top:20px}
    .el-btn{display:inline-flex;align-items:center;gap:9px;text-decoration:none;font-weight:700;font-size:.93rem;padding:12px 24px;border-radius:999px;border:1.5px solid transparent;transition:all .18s ease}
    .el-btn.primary{background:#10b981;color:#fff;box-shadow:0 8px 20px rgba(16,185,129,.26)}
    .el-btn.primary:hover{background:#059669;color:#fff;transform:translateY(-2px)}
    .el-btn.ghost{background:#fff;color:#047857;border-color:#a7f3d0}
    .el-btn.ghost:hover{background:#ecfdf5;color:#065f46;transform:translateY(-2px)}

    .el-section{margin-bottom:38px}
    .el-section h2{margin:0 0 6px;font-size:1.35rem;font-weight:800;color:#0f172a;letter-spacing:-.02em}
    .el-section > p{margin:0 0 18px;font-size:.95rem;color:#64748b;line-height:1.6}

    .el-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px}
    .el-card{display:block;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px 18px;text-decoration:none;color:inherit;box-shadow:0 1px 2px rgba(15,23,42,.04);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
    .el-card:hover{transform:translateY(-3px);border-color:#34d399;box-shadow:0 12px 26px rgba(16,185,129,.12)}
    .el-card-title{font-weight:700;color:#0f172a;font-size:.98rem;line-height:1.35}
    .el-card-sub{margin-top:5px;font-size:.82rem;color:#64748b}
    .el-card-go{margin-top:9px;font-size:.8rem;font-weight:700;color:#047857;display:inline-flex;align-items:center;gap:7px}
    .el-card:hover .el-card-go{color:#059669}
    .el-card.paper .el-card-title{font-size:.95rem}

    .el-exams{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
    .el-exam{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:15px 18px;text-decoration:none;color:inherit;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
    .el-exam:hover{transform:translateY(-3px);border-color:#34d399;box-shadow:0 12px 26px rgba(16,185,129,.12)}
    .el-exam img{width:46px;height:46px;object-fit:contain;flex:0 0 auto}
    .el-exam b{display:block;font-size:.98rem;font-weight:700;color:#0f172a;line-height:1.35}
    .el-exam span{display:block;margin-top:3px;font-size:.82rem;color:#64748b}

    .el-faq{background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden}
    .el-faq details{border-bottom:1px solid #f1f5f9}
    .el-faq details:last-child{border-bottom:0}
    .el-faq summary{cursor:pointer;list-style:none;padding:17px 22px;font-weight:700;font-size:.98rem;color:#0f172a;display:flex;justify-content:space-between;gap:14px;align-items:center}
    .el-faq summary::-webkit-details-marker{display:none}
    .el-faq summary i{color:#059669;font-size:.85rem;flex:0 0 auto}
    .el-faq details[open] summary{background:#f8fafc}
    .el-faq p{margin:0;padding:0 22px 18px;font-size:.93rem;line-height:1.7;color:#475569}

    @media (max-width:640px){
      .el-wrap{padding:20px 14px 44px}
      .el-head{padding:20px}
      .el-cta{flex-direction:column}
      .el-btn{justify-content:center}
    }
    </style>

    <div class="el-wrap">
        <nav aria-label="Breadcrumb" class="el-crumbs">
            <a href="<?php echo SITE_URL; ?>/">Home</a>
            <span class="sep">/</span>
            <a href="<?php echo SITE_URL; ?>/quizzes.php">Quizzes</a>
            <span class="sep">/</span>
            <span class="here"><?php echo $subject ? htmlspecialchars($subject) : htmlspecialchars($examKey); ?></span>
        </nav>

        <header class="el-head">
            <div class="el-head-top">
                <?php if ($logo): ?>
                    <img src="<?php echo htmlspecialchars($logo); ?>" alt="<?php echo htmlspecialchars($examKey); ?> logo" width="58" height="58">
                <?php endif; ?>
                <h1><?php echo htmlspecialchars($h1); ?></h1>
            </div>
            <p><?php echo htmlspecialchars($intro); ?></p>

            <div class="el-cta">
                <?php if ($subject): ?>
                    <a class="el-btn primary" href="<?php echo SITE_URL; ?>/quiz.php?id=<?php echo (int)($papers[0]['id'] ?? 0); ?>">
                        <i class="fas fa-play"></i> Take the <?php echo htmlspecialchars($subject); ?> CBT Exams
                    </a>
                    <?php if ($studySlug): ?>
                        <a class="el-btn ghost" href="<?php echo SITE_URL; ?>/study.php?exam=<?php echo urlencode($examKey); ?>&amp;subject=<?php echo urlencode($studySlug); ?>">
                            <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($subject); ?> Past Questions and Answers
                        </a>
                    <?php endif; ?>
                    <a class="el-btn ghost" href="<?php echo SITE_URL; ?>/<?php echo $exam['file']; ?>">
                        <i class="fas fa-th-large"></i> All <?php echo htmlspecialchars($examKey); ?> Subjects
                    </a>
                <?php else: ?>
                    <a class="el-btn primary" href="<?php echo SITE_URL; ?>/quizzes.php?exam=<?php echo urlencode($examKey); ?>">
                        <i class="fas fa-play"></i> Take a free <?php echo htmlspecialchars($examKey); ?> CBT test
                    </a>
                    <a class="el-btn ghost" href="<?php echo SITE_URL; ?>/study.php?exam=<?php echo urlencode($examKey); ?>">
                        <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($examKey); ?> past questions by year
                    </a>
                    <a class="el-btn ghost" href="<?php echo SITE_URL; ?>/materials.php?level=Secondary&amp;secondary_board=<?php echo urlencode($examKey); ?>">
                        <i class="fas fa-book-open"></i> <?php echo htmlspecialchars($examKey); ?> study materials
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($subjects): ?>
        <section class="el-section">
            <h2><?php echo $subject ? 'Other ' . htmlspecialchars($examKey) . ' Subjects' : htmlspecialchars($exam['subjects_label']); ?></h2>
            <p>Every subject below has past questions with answers and a timed CBT test you can take right now.</p>
            <div class="el-grid">
                <?php foreach ($subjects as $s): ?>
                    <a class="el-card" href="<?php echo SITE_URL; ?>/exam-subject.php?exam=<?php echo urlencode($examKey); ?>&amp;subject=<?php echo urlencode($s['subject']); ?>">
                        <div class="el-card-title"><?php echo htmlspecialchars($s['subject']); ?></div>
                        <div class="el-card-go">Open subject <i class="fas fa-arrow-right"></i></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($papers): ?>
        <section class="el-section">
            <h2>
                <?php echo $subject
                    ? sprintf('%s %s past questions with answers', $examKey, $subject)
                    : sprintf('Latest %s past questions with answers', $examKey); ?>
            </h2>
            <div class="el-grid">
                <?php foreach ($papers as $paper): ?>
                    <a class="el-card paper" href="<?php echo SITE_URL; ?>/quiz.php?id=<?php echo (int)$paper['id']; ?>">
                        <div class="el-card-title"><?php echo htmlspecialchars($paper['title']); ?></div>
                        <div class="el-card-sub">
                            <?php echo htmlspecialchars($paper['subject'] ?: 'General'); ?>
                            &middot; <?php echo (int)($paper['duration_seconds'] ?? 0) > 0 ? (int)$paper['duration_seconds'] . 's' : 'Timed'; ?>
                            &middot; Free CBT practice
                        </div>
                        <div class="el-card-go">Start the test <i class="fas fa-arrow-right"></i></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="el-section">
            <h2>Frequently asked questions</h2>
            <div class="el-faq" style="margin-top:16px;">
                <?php foreach ($exam['faq'] as $item): ?>
                    <details>
                        <summary>
                            <?php echo htmlspecialchars($item['q']); ?>
                            <i class="fas fa-chevron-down" aria-hidden="true"></i>
                        </summary>
                        <p><?php echo htmlspecialchars($item['a']); ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="el-section">
            <h2>Other examinations</h2>
            <div class="el-exams" style="margin-top:16px;">
                <?php foreach ($others as $other): ?>
                    <?php $otherLogo = seo_exam_logo($other['key']); ?>
                    <a class="el-exam" href="<?php echo SITE_URL; ?>/<?php echo $other['file']; ?>">
                        <?php if ($otherLogo): ?>
                            <img src="<?php echo htmlspecialchars($otherLogo); ?>" alt="<?php echo htmlspecialchars($other['key']); ?> logo" width="46" height="46" loading="lazy">
                        <?php endif; ?>
                        <span>
                            <b><?php echo htmlspecialchars($other['tagline']); ?></b>
                            <span><?php echo htmlspecialchars($other['long']); ?> practice papers and answers.</span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
    <?php
}
