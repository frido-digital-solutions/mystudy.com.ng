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

<div class="max-w-5xl mx-auto px-4 py-8 sm:px-6 lg:px-8">

  <nav class="text-sm text-slate-500 mb-6" aria-label="Breadcrumb">
    <?php $last = end($crumbs); foreach ($crumbs as $label => $href): ?>
      <?php if ($href === $last): ?>
        <span class="text-slate-900 font-medium"><?php echo htmlspecialchars($label); ?></span>
      <?php else: ?>
        <a href="<?php echo htmlspecialchars($href); ?>" class="hover:text-brand-600"><?php echo htmlspecialchars($label); ?></a>
        <span class="mx-1">/</span>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <?php if (!$exam): ?>
    <!-- ============================ the three examinations ============ -->
    <h1 class="text-3xl font-bold text-slate-900 mb-2">Study</h1>
    <p class="text-slate-600 mb-8 max-w-2xl">
      Real past questions and answers for the three examinations Nigerian students sit.
      Pick an examination, pick a subject, pick a year, and read the whole paper with the
      answer under every question.
    </p>

    <?php if ($totalQs === 0): ?>
      <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
        The library is being filled. Papers are being added now - check back shortly.
      </div>
    <?php endif; ?>

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($examMeta as $key => $meta):
        $s = $summary[$key];
        $years = $s['from_year'] && $s['to_year']
          ? ($s['from_year'] === $s['to_year'] ? (string)$s['from_year'] : $s['from_year'] . ' - ' . $s['to_year'])
          : 'no papers yet';
      ?>
        <a href="<?php echo SITE_URL; ?>/study.php?exam=<?php echo urlencode($key); ?>"
           class="group block rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-brand-400 hover:shadow-md">
          <div class="flex items-center gap-3 mb-3">
            <img src="<?php echo SITE_URL; ?><?php echo $meta['logo']; ?>" alt="<?php echo $key; ?>" width="48" height="48"
                 class="h-12 w-12 object-contain">
            <div>
              <h2 class="text-xl font-bold text-slate-900 group-hover:text-brand-700"><?php echo $key; ?></h2>
              <p class="text-xs text-slate-500"><?php echo htmlspecialchars($meta['full']); ?></p>
            </div>
          </div>
          <p class="text-sm text-slate-600 mb-4"><?php echo htmlspecialchars($meta['blurb']); ?></p>
          <dl class="grid grid-cols-3 gap-2 text-center border-t border-slate-100 pt-4">
            <div>
              <dt class="text-xs text-slate-500">Questions</dt>
              <dd class="text-lg font-bold text-slate-900"><?php echo number_format($s['questions']); ?></dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500">Subjects</dt>
              <dd class="text-lg font-bold text-slate-900"><?php echo number_format($s['subjects']); ?></dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500">Years</dt>
              <dd class="text-sm font-bold text-slate-900 pt-1"><?php echo htmlspecialchars($years); ?></dd>
            </div>
          </dl>
        </a>
      <?php endforeach; ?>
    </div>

  <?php elseif ($subject === ''): ?>
    <!-- ============================ subjects of one examination ======= -->
    <h1 class="text-3xl font-bold text-slate-900 mb-2"><?php echo $examKey; ?> past questions</h1>
    <p class="text-slate-600 mb-8">
      <?php echo htmlspecialchars($exam['full']); ?>. Choose a subject to see the years it covers.
    </p>

    <?php $subjects = $study->subjects($examKey); ?>
    <?php if (!$subjects): ?>
      <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
        No <?php echo $examKey; ?> papers yet.
      </div>
    <?php else: ?>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($subjects as $s):
          $sName = $examKey === 'JAMB' && $s['subject_slug'] === 'english-language'
            ? 'Use of English'
            : $s['subject_name'];
          $span = $s['from_year'] === $s['to_year']
            ? (string)$s['from_year']
            : $s['from_year'] . ' - ' . $s['to_year'];
        ?>
          <a href="<?php echo SITE_URL; ?>/study.php?exam=<?php echo urlencode($examKey); ?>&amp;subject=<?php echo urlencode($s['subject_slug']); ?>"
             class="group flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm transition hover:border-brand-400 hover:shadow-md">
            <span class="min-w-0">
              <span class="block font-semibold text-slate-900 group-hover:text-brand-700"><?php echo htmlspecialchars($sName); ?></span>
              <span class="block text-sm text-slate-500">
                <?php echo number_format((int)$s['questions']); ?> questions &middot; <?php echo htmlspecialchars($span); ?>
              </span>
            </span>
            <span class="shrink-0 text-slate-400 group-hover:text-brand-600" aria-hidden="true">&rarr;</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($year <= 0): ?>
    <!-- ============================ years of one subject ============== -->
    <?php $years = $study->years($examKey, $subject); ?>
    <h1 class="text-3xl font-bold text-slate-900 mb-2"><?php echo htmlspecialchars($examKey . ' ' . $subjectName); ?></h1>
    <p class="text-slate-600 mb-8">Choose a year to read the paper.</p>

    <?php if (!$years): ?>
      <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
        No papers for this subject yet.
      </div>
    <?php else: ?>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($years as $y): ?>
          <a href="<?php echo SITE_URL; ?>/study.php?exam=<?php echo urlencode($examKey); ?>&amp;subject=<?php echo urlencode($subject); ?>&amp;year=<?php echo (int)$y['year']; ?>"
             class="group flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm transition hover:border-brand-400 hover:shadow-md">
            <span>
              <span class="block text-lg font-bold text-slate-900 group-hover:text-brand-700"><?php echo (int)$y['year']; ?></span>
              <span class="block text-sm text-slate-500">
                <?php echo number_format((int)$y['questions']); ?> questions in <?php echo (int)$y['papers']; ?> paper<?php echo ((int)$y['papers'] === 1 ? '' : 's'); ?>
              </span>
            </span>
            <span class="shrink-0 text-slate-400 group-hover:text-brand-600" aria-hidden="true">&rarr;</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <!-- ============================ the paper itself ================== -->
    <?php $papers = $study->papers($examKey, $subject, $year); ?>
    <h1 class="text-3xl font-bold text-slate-900 mb-2">
      <?php echo htmlspecialchars($examKey . ' ' . $subjectName . ' ' . $year); ?>
    </h1>
    <p class="text-slate-600 mb-6">
      Real <?php echo $examKey; ?> objective paper, <?php echo (int)$year; ?>. Read every question, then check the answer.
    </p>

    <?php if (!$papers): ?>
      <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
        No paper found for that year.
      </div>
    <?php endif; ?>

    <?php foreach ($papers as $paper):
      $questions = $study->questions((int)$paper['id']);
      if (!$questions) continue;
    ?>
      <article class="mb-10">
        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b-2 border-slate-900 pb-2 mb-6">
          <h2 class="text-xl font-bold text-slate-900"><?php echo htmlspecialchars($paper['title']); ?></h2>
          <p class="text-sm text-slate-500">
            <?php echo count($questions); ?> questions
            <?php if (!empty($paper['source_url'])): ?>
              &middot; <a href="<?php echo htmlspecialchars($paper['source_url']); ?>" rel="nofollow noopener" target="_blank" class="underline">source</a>
            <?php endif; ?>
          </p>
        </div>

        <ol class="space-y-6">
          <?php foreach ($questions as $q):
            $options = json_decode((string)$q['options_json'], true);
            if (!is_array($options)) $options = [];
            $answer = strtoupper(trim((string)$q['correct_option']));
          ?>
            <li class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <p class="font-medium text-slate-900 mb-3">
                <span class="text-slate-400 mr-1"><?php echo (int)$q['display_number']; ?>.</span>
                <?php echo nl2br(htmlspecialchars($q['question'])); ?>
              </p>

              <?php if ($options): ?>
                <ul class="space-y-1.5">
                  <?php foreach ($options as $opt):
                    $label = strtoupper(trim((string)($opt['label'] ?? '')));
                    $isAnswer = $answer !== '' && $label === $answer;
                  ?>
                    <li class="flex gap-2 text-sm <?php echo $isAnswer ? 'text-emerald-800 font-medium' : 'text-slate-700'; ?>">
                      <span class="shrink-0 w-6 font-semibold <?php echo $isAnswer ? 'text-emerald-700' : 'text-slate-500'; ?>">
                        <?php echo htmlspecialchars($label); ?>.
                      </span>
                      <span><?php echo nl2br(htmlspecialchars((string)($opt['text'] ?? ''))); ?></span>
                      <?php if ($isAnswer): ?>
                        <span class="ml-1 shrink-0 rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-semibold text-emerald-800">answer</span>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>

              <?php if (!empty($q['explanation'])): ?>
                <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                  <span class="font-semibold text-slate-900">Explanation:</span>
                  <?php echo nl2br(htmlspecialchars($q['explanation'])); ?>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="mt-10 rounded-2xl border border-slate-200 bg-slate-50 p-6">
    <h2 class="text-lg font-bold text-slate-900 mb-2">Want the papers timed and scored?</h2>
    <p class="text-sm text-slate-600 mb-3">
      This library is for reading. If you want to sit a paper under exam conditions and get a
      score, the practice quizzes do that.
    </p>
    <a href="<?php echo SITE_URL; ?>/quizzes.php" class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
      Open the practice quizzes
    </a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
