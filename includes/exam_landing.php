<?php
/**
 * The page one examination gets.
 *
 * Every page here answers the same four questions in the same order - what is
 * this, what is on it, how do I use it, and how do other students do it - and
 * every one of them ends with a way to start a paper, because a page that
 * cannot be acted on is a page a search engine sends people away from.
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

    $h1 = $subject
        ? sprintf('Free %s %s past questions and answers', $examKey, $subject)
        : sprintf('Free %s past questions and answers', $examKey);

    $intro = $subject
        ? sprintf(
            'Every free %s %s past question with its answers, solved the way the marking scheme does it. Take the paper as a timed CBT test, see your score the moment you finish, and keep the corrections for next time.',
            $examKey,
            $subject
        )
        : sprintf(
            'Every free %s past question, with the answers beside it and the marking scheme that goes with them. Take a paper as a timed CBT test, see your score the moment you finish, and find the subjects that still need another week. %s',
            $examKey,
            $exam['blurb']
        );
    ?>
    <main class="container py-4 py-lg-5">
        <nav aria-label="Breadcrumb" class="mb-3" style="font-size: 0.85rem;">
            <a href="<?php echo SITE_URL; ?>/" style="color: var(--bs-secondary-color); text-decoration: none;">Home</a>
            <span style="color: var(--bs-secondary-color);"> / </span>
            <a href="<?php echo SITE_URL; ?>/quizzes.php" style="color: var(--bs-secondary-color); text-decoration: none;">Quizzes</a>
            <span style="color: var(--bs-secondary-color);"> / </span>
            <span style="color: var(--bs-secondary-color);"><?php echo $subject ? htmlspecialchars($subject) : htmlspecialchars($examKey); ?></span>
        </nav>

        <header class="mb-4">
            <h1 style="font-size: clamp(1.7rem, 4vw, 2.6rem); font-weight: 800; line-height: 1.15;"><?php echo htmlspecialchars($h1); ?></h1>
            <p class="mt-3" style="max-width: 62ch; font-size: 1.02rem; line-height: 1.65; color: var(--bs-secondary-color);"><?php echo htmlspecialchars($intro); ?></p>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <?php if ($subject): ?>
                    <a class="btn btn-success" href="<?php echo SITE_URL; ?>/quiz.php?id=<?php echo (int)($papers[0]['id'] ?? 0); ?>">
                        <i class="fas fa-play"></i> Start the <?php echo htmlspecialchars($subject); ?> paper
                    </a>
                    <a class="btn btn-outline-success" href="<?php echo SITE_URL; ?>/<?php echo $exam['file']; ?>">
                        All <?php echo htmlspecialchars($examKey); ?> subjects
                    </a>
                <?php else: ?>
                    <a class="btn btn-success" href="<?php echo SITE_URL; ?>/quizzes.php?exam=<?php echo urlencode($examKey); ?>">
                        <i class="fas fa-play"></i> Take a free <?php echo htmlspecialchars($examKey); ?> CBT test
                    </a>
                    <a class="btn btn-outline-success" href="<?php echo SITE_URL; ?>/materials.php?board=<?php echo urlencode($examKey); ?>">
                        <i class="fas fa-book-open"></i> <?php echo htmlspecialchars($examKey); ?> study materials
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($subjects): ?>
        <section class="mb-5">
            <h2 style="font-size: 1.3rem; font-weight: 800;"><?php echo htmlspecialchars($exam['subjects_label']); ?></h2>
            <p class="text-muted" style="max-width: 70ch;">Every subject below has past questions with answers and a timed CBT test you can take right now.</p>
            <div class="row g-3 mt-1">
                <?php foreach ($subjects as $s): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a class="d-block h-100 text-decoration-none"
                           href="<?php echo SITE_URL; ?>/exam-subject.php?exam=<?php echo urlencode($examKey); ?>&amp;subject=<?php echo urlencode($s['subject']); ?>">
                            <div style="border: 1px solid rgba(16,185,129,0.25); border-radius: 14px; padding: 0.9rem 1rem; height: 100%; background: rgba(16,185,129,0.04);">
                                <div style="font-weight: 700; color: #047857; font-size: 0.95rem;"><?php echo htmlspecialchars($s['subject']); ?></div>
                                <div style="font-size: 0.78rem; color: var(--bs-secondary-color); margin-top: 4px;">
                                    <?php echo (int)$s['count']; ?> past question<?php echo (int)$s['count'] === 1 ? '' : 's'; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($papers): ?>
        <section class="mb-5">
            <h2 style="font-size: 1.3rem; font-weight: 800;">
                <?php echo $subject
                    ? sprintf('%s %s past questions with answers', $examKey, $subject)
                    : sprintf('Latest %s past questions with answers', $examKey); ?>
            </h2>
            <div class="row g-3 mt-1">
                <?php foreach ($papers as $paper): ?>
                    <div class="col-12 col-md-6">
                        <a class="d-block text-decoration-none" href="<?php echo SITE_URL; ?>/quiz.php?id=<?php echo (int)$paper['id']; ?>">
                            <div style="border: 1px solid var(--bs-border-color); border-radius: 14px; padding: 1rem 1.1rem; height: 100%;">
                                <div style="font-weight: 700; font-size: 0.98rem;"><?php echo htmlspecialchars($paper['title']); ?></div>
                                <div style="font-size: 0.8rem; color: var(--bs-secondary-color); margin-top: 6px;">
                                    <?php echo htmlspecialchars($paper['subject'] ?: 'General'); ?>
                                    &middot; <?php echo (int)($paper['duration_seconds'] ?? 0) > 0 ? (int)$paper['duration_seconds'] . 's' : 'Timed'; ?>
                                    &middot; Free CBT practice
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="mb-5">
            <h2 style="font-size: 1.3rem; font-weight: 800;">Frequently asked questions</h2>
            <div class="mt-3">
                <?php foreach ($exam['faq'] as $item): ?>
                    <div class="mb-3">
                        <h3 style="font-size: 1rem; font-weight: 700;"><?php echo htmlspecialchars($item['q']); ?></h3>
                        <p class="mb-0" style="color: var(--bs-secondary-color); line-height: 1.6;"><?php echo htmlspecialchars($item['a']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mb-5">
            <h2 style="font-size: 1.3rem; font-weight: 800;">Other examinations</h2>
            <div class="row g-3 mt-1">
                <?php foreach ($others as $other): ?>
                    <div class="col-12 col-md-6">
                        <a class="d-block text-decoration-none" href="<?php echo SITE_URL; ?>/<?php echo $other['file']; ?>">
                            <div style="border: 1px solid var(--bs-border-color); border-radius: 14px; padding: 1rem 1.1rem;">
                                <div style="font-weight: 700;"><?php echo htmlspecialchars($other['tagline']); ?></div>
                                <div style="font-size: 0.82rem; color: var(--bs-secondary-color); margin-top: 4px;">
                                    <?php echo htmlspecialchars($other['long']); ?> practice papers and answers.
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
    <?php
}
