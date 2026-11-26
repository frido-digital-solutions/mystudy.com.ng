<?php
/**
 * The three examinations this site exists for.
 *
 * Almost every student who finds MyStudy Base is looking for one of these
 * three: WAEC, NECO or JAMB. So each one gets a page of its own, written for
 * the words a student actually types - "free waec past questions and
 * answers", "neco pass questions", "jamb cbt test" - and every one of those
 * pages leads straight to the papers, the materials and the answers.
 *
 * The three pages share one renderer, so they cannot drift apart, and each one
 * is a real page with real links on it - not a doorway.
 */

/**
 * The examinations, in the words students use.
 */
function seo_exam_pages(): array {
    return [
        'waec' => [
            'key'    => 'WAEC',
            'name'   => 'WAEC',
            'file'   => 'waec.php',
            'long'   => 'West African Examinations Council',
            'tagline'=> 'Free WAEC past questions and answers',
            'logo'   => 'waec.png',
            'title'  => 'Free WAEC Past Questions and Answers 2026 | WAEC CBT Practice Tests',
            'desc'   => 'Free WAEC past questions with answers for every subject, plus free WAEC CBT practice tests you can take timed on your phone. Built for Nigerian students.',
            'keywords' => 'waec past questions and answers, free waec past questions, waec cbt, waec exams, waec practice test, waec past question download, waec answer key, waec past questions 2026, waec questions and answers',
            'blurb'  => 'Practice the papers that came out before, with the answers beside them, and see your score the moment you finish.',
            'subjects_label' => 'WAEC subjects',
            'faq'    => [
                [
                    'q' => 'Are these WAEC past questions free?',
                    'a' => 'Yes. Every practice paper on this page is free to open and free to take. You only need an account to keep your scores and your progress.',
                ],
                [
                    'q' => 'Where can I get WAEC past questions and answers?',
                    'a' => 'On this page. Each subject lists the past questions with their answers, and you can take them as a timed CBT test straight away.',
                ],
                [
                    'q' => 'Can I take WAEC CBT practice on my phone?',
                    'a' => 'Yes. The same papers are in the MyStudy Base app, where you can also save a paper to your phone and take it without internet.',
                ],
                [
                    'q' => 'Does this cover the whole WAEC syllabus?',
                    'a' => 'The subjects below are the ones with papers ready now, and new ones are added as they are written. Every paper is marked with the real scheme.',
                ],
            ],
        ],
        'neco' => [
            'key'    => 'NECO',
            'name'   => 'NECO',
            'file'   => 'neco.php',
            'long'   => 'National Examinations Council',
            'tagline'=> 'Free NECO past questions and answers',
            'logo'   => 'neco.png',
            'title'  => 'Free NECO Past Questions and Answers 2026 | NECO CBT Practice Tests',
            'desc'   => 'Free NECO past questions with answers for every subject, plus free NECO CBT practice tests you can take timed on your phone. Built for Nigerian students.',
            'keywords' => 'neco past questions and answers, free neco past questions, neco cbt, neco exams, neco practice test, neco past question download, neco answer key, neco past questions 2026, neco questions and answers',
            'blurb'  => 'The papers, the answers and the marks you get straight after - so you know exactly where you stand before the real exam.',
            'subjects_label' => 'NECO subjects',
            'faq'    => [
                [
                    'q' => 'Are these NECO past questions free?',
                    'a' => 'Yes. Every practice paper here is free to open and free to take. An account only keeps your scores and your progress.',
                ],
                [
                    'q' => 'Where can I download NECO past questions with answers?',
                    'a' => 'From the subjects on this page. Each one lists the papers with their answers, and you can sit them as a timed CBT test.',
                ],
                [
                    'q' => 'Can I take NECO CBT on my phone?',
                    'a' => 'Yes. The papers are in the MyStudy Base app as well, and you can keep one on your phone to take it without internet.',
                ],
                [
                    'q' => 'Are NECO and WAEC the same?',
                    'a' => 'They are different councils, and the papers differ. Use the NECO page for NECO past questions and the WAEC page for WAEC past questions.',
                ],
            ],
        ],
        'jamb' => [
            'key'    => 'JAMB',
            'name'   => 'JAMB',
            'file'   => 'jamb.php',
            'long'   => 'Joint Admissions and Matriculation Board',
            'tagline'=> 'Free JAMB past questions and answers',
            'logo'   => 'jamb.png',
            'title'  => 'Free JAMB Past Questions and Answers 2026 | JAMB CBT Practice Tests',
            'desc'   => 'Free JAMB past questions with answers for every subject, plus free JAMB CBT practice tests you can take timed on your phone. Built for candidates in Nigeria.',
            'keywords' => 'jamb past questions and answers, free jamb past questions, jamb cbt test, jamb practice test, jamb past question download, jamb answer key, jamb past questions 2026, jamb questions and answers, jamb english language past questions',
            'blurb'  => 'Four hours of JAMB is four hundred questions. Practise them here, timed, and find the subjects that need another week.',
            'subjects_label' => 'JAMB subjects',
            'faq'    => [
                [
                    'q' => 'Are these JAMB past questions free?',
                    'a' => 'Yes. Every paper here is free to open and free to take, with the answers and the marking scheme.',
                ],
                [
                    'q' => 'Can I take a full JAMB CBT test?',
                    'a' => 'Yes. Each subject can be taken as a timed test in the app, and you can mix subjects for a full mock examination.',
                ],
                [
                    'q' => 'Where can I get JAMB English Language past questions?',
                    'a' => 'English Language is one of the subjects on this page, with its past questions and answers.',
                ],
                [
                    'q' => 'Does the app work without internet for JAMB practice?',
                    'a' => 'Yes. Save a paper to your phone and it opens and is marked there, even on a bus with no signal.',
                ],
            ],
        ],
    ];
}

/** The exam pages, for the footer, the home page and the sitemap. */
function seo_exam_list(): array {
    return seo_exam_pages();
}

/**
 * The official logo of an examination, for the cards and the page heads.
 *
 * @return string the image URL, or an empty string when the exam has no logo
 */
function seo_exam_logo(string $exam): string {
    foreach (seo_exam_pages() as $page) {
        if ($page['key'] === $exam && !empty($page['logo'])) {
            return (defined('SITE_URL') ? SITE_URL : '') . '/assets/img/exams/' . $page['logo'];
        }
    }
    return '';
}

/**
 * The subjects that have papers for one examination, with the number of papers
 * each one has.
 *
 * @return array<int, array{subject:string, count:int}>
 */
function seo_exam_subjects(PDO $pdo, string $exam): array {
    try {
        $stmt = $pdo->prepare("SELECT subject, COUNT(*) AS n
                               FROM quizzes
                              WHERE subject IS NOT NULL AND subject != ''
                                AND (mode IS NULL OR mode != 'challenge')
                                AND FIND_IN_SET(:et, REPLACE(COALESCE(exam_types, ''), ' ', '')) > 0
                              GROUP BY subject
                              ORDER BY subject ASC");
        $stmt->execute([':et' => $exam]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = ['subject' => (string)$row['subject'], 'count' => (int)$row['n']];
        }
        return $out;
    } catch (Throwable $e) {
        error_log('seo exam subjects: ' . $e->getMessage());
        return [];
    }
}

/**
 * The papers for one examination and one subject.
 */
function seo_exam_papers(PDO $pdo, string $exam, string $subject, int $limit = 12): array {
    try {
        $sql = "SELECT id, title, subject, level, exam_types, duration_seconds, total_phases, current_phase, is_paid, description
                   FROM quizzes
                  WHERE (mode IS NULL OR mode != 'challenge')
                    AND FIND_IN_SET(:et, REPLACE(COALESCE(exam_types, ''), ' ', '')) > 0";
        if ($subject !== '' && $subject !== null) {
            $sql .= ' AND subject = :sub';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :lim';
        $stmt = $pdo->prepare($sql);
        if ($subject !== '' && $subject !== null) {
            $stmt->bindValue(':sub', $subject);
        }
        $stmt->bindValue(':et', $exam);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('seo exam papers: ' . $e->getMessage());
        return [];
    }
}

/**
 * A page of structured data for the search engines.
 */
function seo_jsonld(array $data): void {
    echo '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}

/** The breadcrumb of a page, as the search engines like to see it. */
function seo_breadcrumb_jsonld(array $items): void {
    $list = [];
    $i = 1;
    foreach ($items as $name => $url) {
        $list[] = [
            '@type'    => 'ListItem',
            'position' => $i++,
            'name'     => $name,
            'item'     => $url,
        ];
    }
    seo_jsonld([
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $list,
    ]);
}

/** The questions and answers of a page, as a search engine wants them. */
function seo_faq_jsonld(string $exam, array $faq): void {
    $main = [];
    foreach ($faq as $item) {
        $main[] = [
            '@type'          => 'Question',
            'name'           => $item['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
        ];
    }
    seo_jsonld([
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $main,
    ]);
}
