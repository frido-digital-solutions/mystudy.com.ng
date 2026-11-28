<?php
/**
 * Study - the past question library.
 *
 * One page, four steps, so a student can walk into the exact paper they
 * want:
 *
 *   study.php                                  the three examinations
 *   study.php?exam=WAEC                        the subjects of that exam
 *   study.php?exam=WAEC&subject=english-language
 *                                               the years that subject has
 *   study.php?exam=WAEC&subject=english-language&year=2024
 *                                               the paper itself
 *
 * The paper is a clean reading page: question 1 to the last, each one with
 * its options and its answer. No timer, no prize, no competition - this is
 * the part of the site where a student actually sits down and reads.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/study_schema.php';
require_once __DIR__ . '/models/Study.php';

$examKey   = strtoupper(trim((string)($_GET['exam'] ?? '')));
$subject   = trim((string)($_GET['subject'] ?? ''));
$yearParam = trim((string)($_GET['year'] ?? ''));
$year      = ctype_digit($yearParam) ? (int)$yearParam : 0;

$study = new Study($pdo);
try {
    msb_ensure_study_tables($pdo);
} catch (Throwable $e) {
    // A library that cannot be created is an empty library, not a dead page.
    $study = new Study($pdo);
}

$examMeta = [
    'JAMB' => [
        'name' => 'JAMB',
        'full' => 'Joint Admissions and Matriculation Board (UTME)',
        'logo' => '/assets/img/exams/jamb.png',
        'file' => 'jamb.php',
        'blurb' => 'Objective papers from the Unified Tertiary Matriculation Examination, by subject and year.',
    ],
    'WAEC' => [
        'name' => 'WAEC',
        'full' => 'West African Examinations Council (WASSCE)',
        'logo' => '/assets/img/exams/waec.png',
        'file' => 'waec.php',
        'blurb' => 'Objective papers from the West African Senior School Certificate Examination.',
    ],
    'NECO' => [
        'name' => 'NECO',
        'full' => 'National Examinations Council (SSCE and BECE)',
        'logo' => '/assets/img/exams/neco.png',
        'file' => 'neco.php',
        'blurb' => 'Objective papers from the Senior School Certificate and Basic Education Certificate Examinations.',
    ],
];

$exam     = $examMeta[$examKey] ?? null;
$summary  = $study->examSummary();
$totalQs  = array_sum(array_column($summary, 'questions'));

// ---------------------------------------------------------------- titles
if ($exam && $subject !== '' && $year > 0) {
    $subjectName = $study->subjectName($examKey, $subject) ?: ucfirst(str_replace('-', ' ', $subject));
    $page_title  = sprintf('%s %s Past Questions and Answers (%d) | MyStudy Base', $examKey, $subjectName, $year);
    $page_description = sprintf(
        'Read all %s %s %d past questions and answers in one clean page, with the options and the correct answer under every question.',
        $examKey,
        $subjectName,
        $year
    );
} elseif ($exam && $subject !== '') {
    $subjectName = $study->subjectName($examKey, $subject) ?: ucfirst(str_replace('-', ' ', $subject));
    $page_title  = sprintf('%s %s Past Questions by Year | MyStudy Base', $examKey, $subjectName);
    $page_description = sprintf(
        'Every year of %s %s past questions with answers, from real examination papers. Choose a year and read the paper.',
        $examKey,
        $subjectName
    );
} elseif ($exam) {
    $page_title  = sprintf('%s Past Questions and Answers by Subject and Year | MyStudy Base', $examKey);
    $page_description = sprintf(
        'Free %s past questions with answers for every subject and year. Read the real paper, with the options and the correct answer under each question.',
        $examKey
    );
} else {
    $page_title  = 'Study - Real Past Questions and Answers for JAMB, WAEC and NECO | MyStudy Base';
    $page_description = sprintf(
        'Study real past questions and answers for JAMB, WAEC and NECO. %s questions, every subject, every year, with the answer under each question.',
        number_format($totalQs)
    );
}

require_once __DIR__ . '/includes/header.php';

$crumbs = ['Home' => SITE_URL . '/'];
$crumbs['Study'] = SITE_URL . '/study.php';
if ($exam) {
    $crumbs[$examKey] = SITE_URL . '/study.php?exam=' . urlencode($examKey);
}
if ($exam && $subject !== '') {
    $crumbs[$subjectName] = SITE_URL . '/study.php?exam=' . urlencode($examKey) . '&subject=' . urlencode($subject);
}
if ($exam && $subject !== '' && $year > 0) {
    $crumbs[(string)$year] = SITE_URL . '/study.php?exam=' . urlencode($examKey) . '&subject=' . urlencode($subject) . '&year=' . $year;
}
seo_breadcrumb_jsonld($crumbs);
seo_jsonld([
    '@context' => 'https://schema.org',
    '@type'    => 'CollectionPage',
    'name'     => $page_title,
    'description' => $page_description,
    'url'      => SITE_URL . '/study.php' . ($exam ? '?exam=' . urlencode($examKey) : ''),
    'inLanguage' => 'en',
]);
?>

<!-- ===================================================================
     The look of the Study pages.
     Written here rather than in the site stylesheet, so that the Study
     pages carry their own design and cannot be broken by another page's
     change to the shared stylesheet.
     =================================================================== -->
<style>
.st-wrap{max-width:1080px;margin:0 auto;padding:28px 16px 56px}
.st-crumbs{font-size:.86rem;color:#64748b;margin-bottom:22px}
.st-crumbs a{color:#475569;text-decoration:none}
.st-crumbs a:hover{color:#059669}
.st-crumbs .sep{margin:0 7px;color:#cbd5e1}
.st-crumbs .here{color:#0f172a;font-weight:600}

.st-head{margin-bottom:26px}
.st-head h1{font-size:2rem;font-weight:800;color:#0f172a;margin:0 0 8px;letter-spacing:-.02em;line-height:1.15}
.st-head p{color:#475569;margin:0;max-width:70ch;line-height:1.65;font-size:1.02rem}
.st-eyebrow{display:inline-block;font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#059669;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:999px;padding:3px 10px;margin-bottom:12px}
.st-count{display:inline-block;font-size:.95rem;font-weight:700;color:#047857;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:999px;padding:5px 14px;margin-top:12px}

.st-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:18px}
.st-grid-2{grid-template-columns:repeat(auto-fill,minmax(230px,1fr))}

.st-card{display:block;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:22px;text-decoration:none;color:inherit;box-shadow:0 1px 2px rgba(15,23,42,.04);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
.st-card:hover{transform:translateY(-3px);border-color:#34d399;box-shadow:0 12px 28px rgba(16,185,129,.12)}
.st-card:focus-visible{outline:3px solid rgba(16,185,129,.45);outline-offset:2px}

.st-card-top{display:flex;align-items:center;gap:13px;margin-bottom:14px}
.st-card-top img{width:46px;height:46px;object-fit:contain;flex:0 0 auto}
.st-card-top h2{font-size:1.2rem;font-weight:800;color:#0f172a;margin:0;letter-spacing:-.01em}
.st-card-top p{margin:2px 0 0;font-size:.78rem;color:#64748b;line-height:1.4}
.st-card p.st-blurb{font-size:.9rem;color:#475569;margin:0 0 16px;line-height:1.6}

.st-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;border-top:1px solid #f1f5f9;padding-top:14px;text-align:center}
.st-stats div:last-child{text-align:right}
.st-stats div:first-child{text-align:left}
.st-stats dt{font-size:.7rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;font-weight:600}
.st-stats dd{margin:3px 0 0;font-size:1.12rem;font-weight:800;color:#0f172a}
.st-stats dd small{font-size:.74rem;font-weight:600;color:#64748b}

.st-row{display:flex;align-items:center;justify-content:space-between;gap:12px}
.st-row-name{font-weight:700;color:#0f172a;font-size:1.02rem;line-height:1.3}
.st-row-sub{font-size:.83rem;color:#64748b;margin-top:3px}
.st-row-go{flex:0 0 auto;width:32px;height:32px;border-radius:50%;background:#f1f5f9;color:#64748b;display:flex;align-items:center;justify-content:center;transition:background .18s ease,color .18s ease}
.st-card:hover .st-row-go{background:#10b981;color:#fff}
.st-year{font-size:1.3rem;font-weight:800;color:#0f172a;line-height:1.2}

.st-note{background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:14px;padding:16px 18px;font-size:.94rem;line-height:1.6}

.st-paper{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 1px 3px rgba(15,23,42,.05);overflow:hidden;margin-bottom:34px}
.st-paper-head{padding:20px 24px;border-bottom:1px solid #f1f5f9;background:linear-gradient(180deg,#f8fafc,#fff)}
.st-paper-head h2{margin:0;font-size:1.18rem;font-weight:800;color:#0f172a;letter-spacing:-.01em}
.st-paper-meta{margin:6px 0 0;font-size:.84rem;color:#64748b;display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.st-paper-meta a{color:#059669}

.st-qs{padding:8px 24px 24px}
.st-q{border-bottom:1px solid #f1f5f9;padding:20px 0}
.st-q:last-child{border-bottom:0;padding-bottom:4px}
.st-q-head{display:flex;gap:12px;align-items:flex-start}
.st-num{flex:0 0 auto;min-width:30px;height:30px;border-radius:9px;background:#ecfdf5;color:#047857;font-weight:800;font-size:.88rem;display:flex;align-items:center;justify-content:center;margin-top:1px}
.st-q-text{margin:0;font-size:1rem;line-height:1.7;color:#0f172a;font-weight:500;white-space:normal}
.st-q-text table{width:100%;border-collapse:collapse;margin:10px 0;font-size:.92rem}
.st-q-text td,.st-q-text th{border:1px solid #e2e8f0;padding:7px 10px;text-align:left}

.st-opts{list-style:none;margin:14px 0 0;padding:0;display:flex;flex-direction:column;gap:8px}
.st-opt{display:flex;gap:11px;align-items:flex-start;padding:10px 13px;border:1px solid #e2e8f0;border-radius:11px;background:#fff;font-size:.94rem;line-height:1.6;color:#334155}
.st-opt-label{flex:0 0 auto;min-width:24px;height:24px;border-radius:7px;background:#f1f5f9;color:#475569;font-weight:800;font-size:.78rem;display:flex;align-items:center;justify-content:center}
.st-opt-text{flex:1 1 auto;min-width:0}
.st-opt.is-answer{border-color:#6ee7b7;background:#ecfdf5;color:#065f46}
.st-opt.is-answer .st-opt-label{background:#10b981;color:#fff}
.st-answer-tag{margin-left:auto;flex:0 0 auto;font-size:.68rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#047857;background:#d1fae5;border-radius:999px;padding:3px 9px}

.st-exp{margin-top:12px;background:#f8fafc;border:1px solid #e2e8f0;border-left:3px solid #10b981;border-radius:0 12px 12px 0;padding:13px 16px;font-size:.9rem;line-height:1.65;color:#475569}
.st-exp strong{color:#0f172a}

.st-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:18px}
.st-toggle{border:1px solid #e2e8f0;background:#fff;color:#334155;font-size:.86rem;font-weight:600;padding:9px 16px;border-radius:999px;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:all .18s ease}
.st-toggle:hover{border-color:#10b981;color:#059669}
.st-hide-answers .st-answer-tag{display:none}
.st-hide-answers .st-opt.is-answer{background:#fff;color:#334155;border-color:#e2e8f0}
.st-hide-answers .st-opt.is-answer .st-opt-label{background:#f1f5f9;color:#475569}

.st-cta{margin-top:34px;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:24px;display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between}
.st-cta h2{margin:0 0 5px;font-size:1.08rem;font-weight:800;color:#0f172a}
.st-cta p{margin:0;font-size:.9rem;color:#64748b;line-height:1.6}
.st-btn{display:inline-block;background:#10b981;color:#fff;text-decoration:none;font-weight:700;font-size:.9rem;padding:11px 20px;border-radius:999px;transition:background .18s ease}
.st-btn:hover{background:#059669;color:#fff}

@media (max-width:640px){
  .st-wrap{padding:20px 14px 44px}
  .st-head h1{font-size:1.6rem}
  .st-qs{padding:6px 16px 20px}
  .st-paper-head{padding:16px}
  .st-cta{flex-direction:column;align-items:flex-start}
}
</style>

<div class="st-wrap">

  <nav class="st-crumbs" aria-label="Breadcrumb">
    <?php $last = end($crumbs); foreach ($crumbs as $label => $href): ?>
      <?php if ($href === $last): ?>
        <span class="here"><?php echo htmlspecialchars($label); ?></span>
      <?php else: ?>
        <a href="<?php echo htmlspecialchars($href); ?>"><?php echo htmlspecialchars($label); ?></a>
        <span class="sep">/</span>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <?php if (!$exam): ?>
    <!-- ============================ the three examinations ============ -->
    <header class="st-head">
      <h1>Study Real Past Questions and Answers.</h1>
      <span class="st-count"><?php echo number_format($totalQs); ?> total Questions Available</span>
    </header>

    <?php if ($totalQs === 0): ?>
      <div class="st-note" style="margin-bottom:22px">
        The library is being filled. Papers are being added now - check back shortly.
      </div>
    <?php endif; ?>

    <div class="st-grid">
      <?php foreach ($examMeta as $key => $meta):
        $s = $summary[$key] ?? ['questions' => 0, 'subjects' => 0, 'from_year' => 0, 'to_year' => 0];
        $years = $s['from_year'] && $s['to_year']
          ? ($s['from_year'] === $s['to_year'] ? (string)$s['from_year'] : $s['from_year'] . ' - ' . $s['to_year'])
          : 'no papers yet';
      ?>
        <a class="st-card" href="<?php echo SITE_URL; ?>/study.php?exam=<?php echo urlencode($key); ?>">
          <div class="st-card-top">
            <img src="<?php echo SITE_URL; ?><?php echo $meta['logo']; ?>" alt="<?php echo $key; ?>" width="46" height="46" loading="lazy">
            <div>
              <h2><?php echo $key; ?></h2>
              <p><?php echo htmlspecialchars($meta['full']); ?></p>
            </div>
          </div>
          <dl class="st-stats">
            <div>
              <dt>Questions</dt>
              <dd><?php echo number_format($s['questions']); ?></dd>
            </div>
            <div>
              <dt>Subjects</dt>
              <dd><?php echo number_format($s['subjects']); ?></dd>
            </div>
            <div>
              <dt>Years</dt>
              <dd><small><?php echo htmlspecialchars($years); ?></small></dd>
            </div>
          </dl>
        </a>
      <?php endforeach; ?>
    </div>

  <?php elseif ($subject === ''): ?>
    <!-- ============================ the subjects of one examination ==== -->
    <?php $subjects = $study->subjects($examKey); ?>
    <header class="st-head">
      <span class="st-eyebrow"><?php echo htmlspecialchars($examKey); ?> past questions</span>
      <h1><?php echo htmlspecialchars($examKey); ?> past questions</h1>
      <p><?php echo htmlspecialchars($exam['full']); ?>. Choose a subject to see the years it covers.</p>
    </header>

    <?php if (!$subjects): ?>
      <div class="st-note">No papers for this examination yet.</div>
    <?php else: ?>
      <div class="st-grid">
        <?php foreach ($subjects as $s):
          $sName = $s['subject_name'] ?: ucfirst(str_replace('-', ' ', $s['subject_slug']));
        ?>
          <a class="st-card" href="<?php echo SITE_URL; ?>/study.php?exam=<?php echo urlencode($examKey); ?>&amp;subject=<?php echo urlencode($s['subject_slug']); ?>">
            <div class="st-row">
              <div>
                <div class="st-row-name"><?php echo htmlspecialchars($sName); ?></div>
                <div class="st-row-sub">
                  <?php echo number_format($s['questions']); ?> questions
                  <?php if ($s['from_year'] && $s['to_year']): ?>
                    &middot; <?php echo (int)$s['from_year']; ?> - <?php echo (int)$s['to_year']; ?>
                  <?php endif; ?>
                </div>
              </div>
              <span class="st-row-go" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($year <= 0): ?>
    <!-- ============================ the years of one subject ============ -->
    <?php $years = $study->years($examKey, $subject); ?>
    <header class="st-head">
      <span class="st-eyebrow"><?php echo htmlspecialchars($examKey); ?> &middot; <?php echo htmlspecialchars($subjectName); ?></span>
      <h1><?php echo htmlspecialchars($examKey . ' ' . $subjectName); ?></h1>
      <p>Choose a year to read the paper. Every question comes with its options and the
         correct answer.</p>
    </header>

    <?php if (!$years): ?>
      <div class="st-note">No papers for this subject yet.</div>
    <?php else: ?>
      <div class="st-grid st-grid-2">
        <?php foreach ($years as $y): ?>
          <a class="st-card" href="<?php echo SITE_URL; ?>/study.php?exam=<?php echo urlencode($examKey); ?>&amp;subject=<?php echo urlencode($subject); ?>&amp;year=<?php echo (int)$y['year']; ?>">
            <div class="st-row">
              <div>
                <div class="st-year"><?php echo (int)$y['year']; ?></div>
                <div class="st-row-sub"><?php echo number_format($y['questions']); ?> questions</div>
              </div>
              <span class="st-row-go" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <!-- ============================ the paper itself ================== -->
    <?php $papers = $study->papers($examKey, $subject, $year); ?>
    <header class="st-head">
      <span class="st-eyebrow"><?php echo htmlspecialchars($examKey); ?> &middot; <?php echo htmlspecialchars($subjectName); ?> &middot; <?php echo (int)$year; ?></span>
      <h1><?php echo htmlspecialchars($examKey . ' ' . $subjectName . ' ' . $year); ?></h1>
      <p>Real <?php echo htmlspecialchars($examKey); ?> objective paper, <?php echo (int)$year; ?>. Read every question, then check the answer.</p>
    </header>

    <?php if (!$papers): ?>
      <div class="st-note">No paper found for that year.</div>
    <?php endif; ?>

    <?php $showAnswers = true; foreach ($papers as $paper):
      $questions = $study->questions((int)$paper['id']);
      if (!$questions) continue;
    ?>
      <article class="st-paper">
        <div class="st-paper-head">
          <h2><?php echo htmlspecialchars($paper['title']); ?></h2>
          <p class="st-paper-meta">
            <span><?php echo count($questions); ?> questions</span>
            <?php if (!empty($paper['source_url'])): ?>
              <span>&middot; <a href="<?php echo htmlspecialchars($paper['source_url']); ?>" rel="nofollow noopener" target="_blank">view the source</a></span>
            <?php endif; ?>
          </p>
        </div>

        <div class="st-qs">
          <?php foreach ($questions as $q):
            $options = json_decode((string)$q['options_json'], true);
            if (!is_array($options)) $options = [];
            $answer = strtoupper(trim((string)$q['correct_option']));
          ?>
            <div class="st-q">
              <div class="st-q-head">
                <span class="st-num"><?php echo (int)$q['display_number']; ?></span>
                <p class="st-q-text"><?php echo nl2br(htmlspecialchars($q['question'])); ?></p>
              </div>

              <?php if ($options): ?>
                <ul class="st-opts">
                  <?php foreach ($options as $opt):
                    $label = strtoupper(trim((string)($opt['label'] ?? '')));
                    $isAnswer = $answer !== '' && $label === $answer;
                  ?>
                    <li class="st-opt<?php echo $isAnswer ? ' is-answer' : ''; ?>">
                      <span class="st-opt-label"><?php echo htmlspecialchars($label); ?></span>
                      <span class="st-opt-text"><?php echo nl2br(htmlspecialchars((string)($opt['text'] ?? ''))); ?></span>
                      <?php if ($isAnswer): ?>
                        <span class="st-answer-tag">Answer</span>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>

              <?php if (!empty($q['explanation'])): ?>
                <div class="st-exp">
                  <strong>Why:</strong>
                  <?php echo nl2br(htmlspecialchars($q['explanation'])); ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="st-cta">
    <div>
      <h2>Want the papers timed and scored?</h2>
      <p>This library is for reading. If you want to sit a paper under exam conditions and get a
         score, the practice quizzes do that.</p>
    </div>
    <a class="st-btn" href="<?php echo SITE_URL; ?>/quizzes.php">Open the practice quizzes</a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
