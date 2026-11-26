<?php
/**
 * The 2027 exam season posts.
 *
 * Every fact in here comes from a named source that was checked when the post
 * was written, and every post carries those sources at the bottom so a reader
 * can go and see the announcement themselves. The wording is our own - these
 * are written for a student who wants to know what to do next, not a copy of
 * somebody else's article.
 *
 * The file is data only. cli/make_news_banners.php draws the pictures and
 * cli/publish_exam_posts.php puts the posts into the database.
 */

return [

    /* ------------------------------------------------------------------ */
    /* WAEC                                                              */
    /* ------------------------------------------------------------------ */

    [
        'slug'  => 'waec-2027-registration-open-hybrid-examinations',
        'date'  => '2026-11-17 09:00:00',
        'date_label' => '17 November 2026',
        'title' => 'WAEC 2027 Registration Is Open, and the Exam Will Be Hybrid: What That Means for You',
        'excerpt' => 'WAEC opened 2027 WASSCE registration for school candidates on 17 November 2026 and confirmed for the first time that the 2027 examination will run in hybrid mode. Here is what changed, what did not, and what your school has to do.',
        'level' => 'Secondary',
        'banner' => ['file' => 'waec-2027-hybrid.png', 'exam' => 'waec', 'kicker' => 'WAEC 2027'],
        'sources' => [
            ['WAEC Nigeria on X (17 Nov 2026)', 'https://x.com/waecnigeria/status/2122820045105500169'],
            ['Guardian Nigeria: WAEC opens 2027 WASSCE registration, introduces CBT', 'https://guardian.ng/breakingnews/waec-opens-2027-wassce-registration-introduces-cbt/'],
            ['Blueprint: WAEC opens registration for 2027 WASSCE', 'https://blueprint.ng/waec-opens-registration-for-2027-wassce/'],
            ['Daily Post: 2027 WASSCE - SS3 students to write objectives on computer', 'https://dailypost.ng/2026/11/18/2027-wassce-registration-opens-as-waec-unveils-hybrid-exam-format/'],
        ],
        'content' => <<<'HTML'
<p>On Tuesday, 17 November 2026, the West African Examinations Council announced on its official account that registration for the 2027 West African Senior School Certificate Examination (WASSCE) for school candidates had opened. The same statement carried a line that matters more than any date: <strong>the 2027 examination will be conducted in hybrid mode.</strong></p>

<h3>What "hybrid" actually means</h3>
<p>Hybrid is not the same as "everything on a computer". It is the format WAEC first introduced for private candidates in 2024: objective questions are answered on a computer, while essay-type and practical papers are still written on paper. WAEC has been moving towards full computer-based testing for years, and 2027 is the year that approach reaches the ordinary school candidate.</p>
<p>Practical meaning for a candidate: get used to typing quickly and reading on a screen for the objective section, and keep your handwriting practice for the essays. Students who have never sat a computer-based paper tend to lose time on the first ten questions, and that is where marks quietly go missing.</p>

<h3>What has not changed</h3>
<p>The subject rules stayed as they were for 2026. Candidates still register for a minimum of eight and a maximum of nine subjects, and English Language and General Mathematics remain the compulsory core. WAEC also said the 2026 subject registration guidelines - compulsory subjects, trade subjects and the minimum and maximum number of subjects - remain in force for 2027.</p>

<h3>Your school does the registration, not you</h3>
<p>This is the part students get wrong every year. There is no individual sign-up portal for the school WASSCE. Your principal and the school enter you, upload your Continuous Assessment Scores for SS1 and SS2, and certify your entries. WAEC told schools to complete CASS uploads promptly and to stick strictly to the registration timeline.</p>
<p>Two practical consequences. First, if your CASS is not uploaded, your registration stalls - and CASS uploads close earlier than the main registration window. Second, anyone offering to "register you" privately is not WAEC. WAEC has never sold school-candidate registration to individuals.</p>

<h3>What still has not been announced</h3>
<p>The 17 November announcement did not state a registration fee, a closing date or a timetable. A proposed examination window of 11 May to 18 June 2027 has been reported from the Federal Ministry of Education's harmonised calendar, but WAEC has not published a final subject timetable, and the fee question has not been publicly settled - the circular to principals quotes a normal entry fee of &#8358;30,000 per candidate, while a government proposal to raise fees further has not been resolved in public.</p>

<h3>What to do this week</h3>
<ul>
<li>Ask your school for confirmation that your CASS for SS1 and SS2 has been uploaded and accepted.</li>
<li>Check that your name, date of birth and passport photograph match your school records exactly. Corrections after the entry list is set are painful.</li>
<li>Make sure you have a valid NIN. WAEC requires it for every candidate in the 2027 exercise.</li>
<li>Confirm your subject list: eight or nine subjects, including English Language and General Mathematics.</li>
<li>Start timed practice on a computer, not only on paper. You can work through free WAEC past questions with answers right here on <a href="https://mystudy.com.ng/waec.php">the WAEC page</a>, and the same papers run inside the MyStudy Base app.</li>
</ul>

<p>Registration for the 2027 WASSCE is a school process, but the exam itself is a personal one. The schools will handle the paperwork; the preparation is yours.</p>
HTML,
    ],

    [
        'slug'  => 'waec-2027-registration-dates-cass-and-the-january-deadline',
        'date'  => '2026-11-18 09:00:00',
        'date_label' => '18 November 2026',
        'title' => 'WAEC 2027 Registration Dates: The Three Windows Every SS3 Candidate Should Know',
        'excerpt' => 'CASS uploads, offline entry and online registration run on three separate clocks, and the first one closes on 17 December 2026. Here are the dates, in order, and what each stage actually involves.',
        'level' => 'Secondary',
        'banner' => ['file' => 'waec-2027-dates.png', 'exam' => 'waec', 'kicker' => 'Registration timeline'],
        'sources' => [
            ['WAEC Nigeria on X (17 Nov 2026)', 'https://x.com/waecnigeria/status/2122820045105500169'],
            ['Schoolcrib: WAEC 2027 registration dates, fee and how to register', 'https://schoolcrib.com/waec-2027-registration/'],
            ['Megastar Magazine: WAEC begins registration for 2027 WASSCE', 'https://www.megastarmagazine.com/education/waec-begins-registration-for-2027-wassce/'],
        ],
        'content' => <<<'HTML'
<p>Registration for the 2027 WASSCE is not one date. It is three overlapping windows, and they do not all close at the same time. WAEC's circular to school principals, dated 16 October 2026, splits the exercise into three stages - and the first stage closes more than a month before the last one opens.</p>

<h3>The three stages</h3>
<ul>
<li><strong>Upload of CASS for SS1 and SS2</strong> - opened 20 October 2026, closes <strong>Thursday 17 December 2026</strong>.</li>
<li><strong>Offline registration</strong> (entry documents and certification at the school) - opened 2 November 2026, closes <strong>Tuesday 22 December 2026</strong>.</li>
<li><strong>Online registration</strong> (schools upload the certified entries) - opened 23 November 2026, closes <strong>Friday 22 January 2027</strong>.</li>
</ul>

<p>When the online window shuts on 22 January 2027, the entry list for the 2027 school WASSCE is fixed. Everything after that is a private arrangement between the candidate and WAEC, not something the school can push through.</p>

<h3>Why CASS is the deadline that bites</h3>
<p>Continuous Assessment Scores are the school-based component of the WASSCE, and they are already in your school records - SS1 and SS2 results that have been sitting in the register for two years. The trap is timing: CASS closes on 17 December, more than a month before online registration ends. A school that misses the CASS window cannot make it up by registering later. If your SS1 and SS2 scores have not been uploaded and accepted, that conversation with your principal is the one to have this week.</p>

<h3>What you need in place</h3>
<ul>
<li>A valid National Identification Number (NIN). WAEC requires one for every candidate in 2027.</li>
<li>Your Learner Identification Number (LIN).</li>
<li>Complete SS1 and SS2 CASS records.</li>
<li>A clear passport photograph.</li>
<li>Eight or nine confirmed subjects, including English Language and General Mathematics.</li>
</ul>

<h3>About the fee</h3>
<p>The circular to principals quotes a normal entry fee of &#8358;30,000 per candidate. Note the word "normal": WAEC has not restated the fee in the public announcement, and a government proposal to increase WAEC fees has not been publicly settled. Do not budget from a rumour - get the figure from your school, which works from the same circular, and keep your receipt.</p>

<h3>What about candidates who are not in school?</h3>
<p>You do not use any of these dates. If you left school, or you are not enrolled in a recognised secondary school, your route is the WASSCE for Private Candidates - popularly called WAEC GCE - which runs on its own schedule and its own registration process. WAEC runs three WASSCE diets a year: February for private candidates, May/June for school candidates, and November/December again for private candidates.</p>

<p>Timelines for a registration season are decided by somebody else's calendar, but the exam you sit is your own. Use the weeks while the portal is still open to build the paper practice you will need in May.</p>
HTML,
    ],

    [
        'slug'  => 'waec-gce-private-candidates-two-dietes-a-year',
        'date'  => '2026-11-19 09:00:00',
        'date_label' => '19 November 2026',
        'title' => 'WAEC GCE: What Private Candidates Should Know Now That Computer-Based Testing Is Here',
        'excerpt' => 'WAEC gives private candidates two chances a year, and the paper is no longer a paper-only affair. Here is how the GCE route works, what it costs in time, and why most candidates still need a proper study plan.',
        'level' => 'Secondary',
        'banner' => ['file' => 'waec-gce-private.png', 'exam' => 'waec', 'kicker' => 'Private candidates'],
        'sources' => [
            ['WAEC Nigeria: Frequently Asked Questions on registration and WASSCE entry rules', 'https://www.waecnigeria.org/index.php/node/9?page=0'],
            ['Schoolcrib: WAEC GCE registration 2026/2027', 'https://schoolcrib.com/waec-gce-registration/'],
            ['Guardian Nigeria: WAEC opens 2027 WASSCE registration, introduces CBT', 'https://guardian.ng/breakingnews/waec-opens-2027-wassce-registration-introduces-cbt/'],
        ],
        'content' => <<<'HTML'
<p>If you left school before the WASSCE, or you are resitting one or two subjects, the route open to you is the WASSCE for Private Candidates, which everybody calls WAEC GCE. It leads to the same certificate as the school examination. What is different is who enters you: you enter yourself.</p>

<h3>Two diets a year, not one</h3>
<p>WAEC conducts three WASSCE examinations each year. The first, in February, is for private candidates. The second, in May/June, is the school examination. The third, in November/December, is for private candidates again. So a private candidate gets two attempts a year - which is one of the strongest arguments for planning rather than panicking.</p>

<h3>The exam itself has already changed</h3>
<p>WAEC introduced computer-based testing for private candidates in 2024, and the hybrid format followed: objective papers on a computer, essays and practicals on paper. The 2026 Second Series registration portal listed approved CBT centres across the country, so a private candidate can now register for and sit a centre-based computer paper rather than a paper-only script. WAEC has said private candidates should follow the instructions for their specific series instead of assuming the school-candidate format applies to them.</p>

<h3>How the registration works</h3>
<ol>
<li>Buy a registration PIN from an accredited bank or agent. WAEC issues two PINs - one for registration, one for checking results.</li>
<li>Register online at registration.waecdirect.org, entering your bio-data exactly as it should appear on the certificate.</li>
<li>Complete biometric capture. Your fingerprints are taken at the centre, and the same biometric features are used to identify you on examination day.</li>
<li>Choose your examination town and your eight or nine subjects, including English Language and General Mathematics.</li>
<li>Read every field back before you submit, then print the admission notice or photo card.</li>
</ol>

<p>WAEC's instruction has been to conclude registration within the period given in the advert, which in recent diets has been around two weeks from first accessing the portal. That is short. A PIN bought in a hurry is an expensive hurry.</p>

<h3>The part nobody plans for</h3>
<p>Two things decide whether a private candidate passes. The first is whether the eight or nine registered subjects actually match what the university, polytechnic or college of education wants. The second is the ordinary school one - English Language and General Mathematics have to be among them, and WAEC's own entry rules make them compulsory core subjects.</p>
<p>Missing a subject on a registration form means re-registering for a whole diet. Get the list right before you pay for anything.</p>

<p>Two diets a year means you can afford to be deliberate: register early for the first series, use the month before it to practise timed papers, and keep the second series in reserve. Free WAEC past questions with answers are on <a href="https://mystudy.com.ng/waec.php">the WAEC page here</a>, and you can save a paper to your phone and take it offline in the app.</p>
HTML,
    ],

    [
        'slug'  => 'waec-2026-results-by-the-numbers',
        'date'  => '2026-08-06 09:00:00',
        'date_label' => '6 August 2026',
        'title' => 'WAEC 2026 Results by the Numbers: Nearly Two Million Sat, 61.54% Met the University Benchmark',
        'excerpt' => 'WAEC released the 2026 CB-WASSCE results in August with a slightly lower pass rate than 2025 and 167,486 results withheld. Here are the numbers that matter for a 2027 candidate, explained.',
        'level' => 'Secondary',
        'banner' => ['file' => 'waec-2026-results.png', 'exam' => 'waec', 'kicker' => '2026 results'],
        'sources' => [
            ['ThisDay: CB-WASSCE - WAEC records 61.54% pass rate, withholds 167,486 results', 'https://www.thisdaylive.com/2026/08/06/cb-wassce-waec-records-61-54-pass-rate-withholds-167486-results-over-malpractice/'],
            ['Pulse Nigeria: 7 important updates from WAEC 2026 WASSCE results release', 'https://www.pulse.ng/story/waec-2026-wassce-results-release-updates-2026080705291172584'],
            ['Edugist: 12 statistics that tell the real story of secondary education in Nigeria', 'https://edugist.org/2026-waec-results-by-the-numbers-12-statistics-that-tell-the-real-story-of-secondary-education-in-nigeria/'],
            ['Nairaland: WAEC releases 2026 results', 'https://www.nairaland.com/8722827/waec-releases-2026-results-how'],
        ],
        'content' => <<<'HTML'
<p>The 2026 WASSCE was the first full computer-based WASSCE for school candidates in Nigeria, and its results are the best dataset yet for understanding where students actually lose marks. WAEC released them in August 2026, and the headline numbers are worth reading carefully rather than skimming.</p>

<h3>The headline figures</h3>
<ul>
<li>1,959,668 candidates registered for the examination, from 24,207 schools across Nigeria and selected schools in Benin Republic, Cote d'Ivoire and Equatorial Guinea running the Nigerian curriculum.</li>
<li>1,950,726 candidates actually sat the paper, between 24 April and 19 June 2026.</li>
<li>1,200,514 candidates - <strong>61.54%</strong> - obtained credits and above in at least five subjects <em>including English Language and Mathematics</em>. This is the university admission benchmark.</li>
<li>1,687,378 candidates - <strong>86.50%</strong> - obtained credits and above in five subjects with or without English and Mathematics.</li>
<li>167,486 results were withheld over alleged examination malpractice.</li>
<li>Performance fell by 1.42 percentage points against 2025, on the same five-credit benchmark.</li>
</ul>

<h3>Why the gap between 61.54% and 86.50% is the story</h3>
<p>Almost 25 percentage points separate "passed five subjects" from "passed five subjects with English and Mathematics". Those candidates in between did real work and still cannot enter most universities, because the five-credit rule for direct entry includes both core subjects. This has been true for years, but the 2026 figures put a number on it: a quarter of all candidates who sat the exam are, in practice, one subject away from the thing they sat the exam for.</p>
<p>Read the WAEC Nigeria statement and the mathematics is stark. 953,459 of the candidates who sat were male and 997,267 were female - 48.88% against 51.12%.</p>

<h3>What a 2027 candidate should take from it</h3>
<p>If you are sitting in 2027, the numbers above are not statistics about other people. They are a map of where the marks are lost.</p>
<ul>
<li><strong>English Language and Mathematics are the exam.</strong> Not the eight other subjects. Every extra credit you collect in elective subjects does nothing for your admission if these two are missing.</li>
<li><strong>Both papers are now on a computer.</strong> English Language has moved to a computer-based format, which means reading speed and screen discipline count towards the mark now.</li>
<li><strong>Malpractice is being punished publicly and in bulk.</strong> 167,486 results withheld is not a rounding error. Candidates caught copying lose a whole diet, not a mark.</li>
</ul>

<p>If you want the practice that closes that gap, work the two core subjects first, timed, on a computer. Free WAEC past questions with answers for every subject are on <a href="https://mystudy.com.ng/waec.php">the WAEC page</a>, and each one runs as a timed CBT paper you can take offline in the MyStudy Base app.</p>
HTML,
    ],

    /* ------------------------------------------------------------------ */
    /* NECO                                                              */
    /* ------------------------------------------------------------------ */

    [
        'slug'  => 'neco-2027-ssce-registration-open-exam-moved-to-april',
        'date'  => '2026-11-06 09:00:00',
        'date_label' => '6 November 2026',
        'title' => 'NECO 2027 SSCE: Registration Is Open and the Whole Examination Has Moved Ten Weeks Earlier',
        'excerpt' => 'NECO opened 2027 SSCE Internal registration on 2 November 2026 and moved the examination to 1 April - 11 May 2027, weeks before WAEC writes. Here are the dates, the fee and what changed.',
        'level' => 'Secondary',
        'banner' => ['file' => 'neco-2027-ssce.png', 'exam' => 'neco', 'kicker' => 'NECO 2027'],
        'sources' => [
            ['MySchoolGist: NECO 2027 SSCE Internal registration - fees and dates', 'https://myschoolgist.com/news/neco-2027-ssce-registration-194575/'],
            ['Teen Trust News: NECO announces dates for the 2027 SSCE Internal', 'https://teentrust.ng/neco-announces-dates-for-2027-ssce-internal-examination/'],
            ['Naijasabi: NECO 2027 SSCE Internal registration guidelines and the FG harmonised timetable', 'https://naijasabi.com.ng/neco-2027-ssce-internal-registration-guidelines-fees-fg-harmonised-exam-timetable/'],
            ['Recruit Focus: When is the NECO 2027 examination starting?', 'https://recruitfocus.com.ng/when-is-neco-2027-examination-starting/'],
        ],
        'content' => <<<'HTML'
<p>NECO opened registration for the 2027 Senior School Certificate Examination (Internal) on Monday, 2 November 2026, and dropped a piece of news with it that changes how a student should plan the year: the examination itself has moved forward to <strong>Thursday 1 April - Tuesday 11 May 2027</strong>. That is roughly ten weeks earlier than NECO's traditional June/July slot, and it puts the NECO paper <em>before</em> the May/June WAEC WASSCE for the first time in this harmonised calendar.</p>

<h3>The dates that matter</h3>
<ul>
<li>Normal registration: 2 November 2026 to Friday 26 February 2027, closing at 11:59 p.m.</li>
<li>Late registration: Saturday 27 February to Friday 5 March 2027, with an extra &#8358;5,000 per candidate. NECO has said there will be no extension.</li>
<li>Examination: Thursday 1 April to Tuesday 11 May 2027.</li>
<li>Final validation list and payment receipt to the state office: 12 March 2027.</li>
<li>Results are expected around August 2027.</li>
</ul>

<p>A Federal Government harmonised timetable for 2027 has been circulating with 14 May as an end date for the NECO paper. NECO's own announcement says 11 May. Until NECO publishes the subject-by-subject timetable, treat the council's dates as the ones to plan around.</p>

<h3>The fee, and the part people misquote</h3>
<p>NECO's published registration guidelines put the examination fee at <strong>&#8358;30,000 per candidate</strong>. On top of that, the guidelines list a four-figure table and a certificate jacket, which take the statutory total to around &#8358;33,800 per candidate. Reports that a &#8358;50,000 figure has been approved for 2027 have circulated widely, but NECO's own published guidelines still carry the &#8358;30,000 fee. Check <a href="https://neco.gov.ng" rel="nofollow noopener" target="_blank">neco.gov.ng</a> before you transfer anything, because registration fees are not refundable.</p>

<h3>Three new requirements schools cannot skip</h3>
<ol>
<li><strong>Supervisor nomination is compulsory.</strong> A school must nominate its examination supervisors during registration, and the exercise cannot be completed without it.</li>
<li><strong>Identity is stricter.</strong> Every candidate needs a valid 11-digit NIN, and NECO will issue a National Learners' Identification Number (NLIN) for each candidate.</li>
<li><strong>Offline first, online second.</strong> Schools must complete offline registration and validation before uploading data online, and submit a signed Offline Validation List to the NECO state office. Corrections are made offline, not online.</li>
</ol>

<p>There is also a compliance expectation around teaching staff: at least 75% of a school's teachers in the examination system are to be TRCN-qualified.</p>

<h3>What students should actually be doing now</h3>
<p>NECO 2027 is strictly for school-based candidates in Nigeria and overseas. Private candidates are not eligible for the Internal paper; they sit the External. If your school has not started the registration, that is the conversation to have this week rather than in February.</p>
<p>And with the paper now landing in April, your revision calendar has to move with it. Free NECO past questions with answers are on <a href="https://mystudy.com.ng/neco.php">the NECO page</a> - start with the subjects you are weakest in, take them timed, and see the score the moment you finish.</p>
HTML,
    ],

    [
        'slug'  => 'neco-2027-bece-private-candidates-banned-and-the-nlin',
        'date'  => '2026-11-20 09:00:00',
        'date_label' => '20 November 2026',
        'title' => 'NECO 2027 BECE: Private Candidates Barred, Every Candidate Gets a Learners Number',
        'excerpt' => 'NECO released its 2027 Basic Education Certificate Examination guidelines on 20 November 2026, and they contain sharp new rules for JSS 3 students and the schools entering them.',
        'level' => 'Secondary',
        'banner' => ['file' => 'neco-2027-bece.png', 'exam' => 'neco', 'kicker' => 'NECO 2027 BECE'],
        'sources' => [
            ['News Central TV: NECO releases 2027 BECE registration guidelines', 'https://newscentraltv.com/neco-releases-2027-bece-registration-guidelines/'],
            ['The Eagle Online: 2027 BECE - NECO releases registration guidelines, bars private candidates', 'https://theeagleonline.com.ng/breaking-2027-bece-neco-releases-registration-guidelines-bars-private-candidates/'],
            ['Nigerian Eye: NECO declares University of America centres illegal (same week, NUC)', 'https://www.nigerianeye.com/2026/11/neco-declares-university-of-america.html'],
        ],
        'content' => <<<'HTML'
<p>While the big attention was on the senior school certificate, NECO quietly issued its 2027 Basic Education Certificate Examination guidelines on Friday, 20 November 2026, and the rules in them are stricter than anything JSS 3 students are used to.</p>

<h3>School-based candidates only</h3>
<p>The first thing the circular says is that the 2027 BECE is strictly for school-based candidates in Nigeria and overseas. <strong>Private candidates are not allowed.</strong> That is a hard line, and it continues into the registration process itself.</p>

<h3>A new number for every student</h3>
<p>Every candidate will be issued a unique National Learners' Identification Number (NLIN), and a valid National Identification Number (NIN) is compulsory. Candidate records are checked field by field: NIN, passport photograph, name, date of birth, gender, state of origin, local government area and subjects all have to be verified before anything is uploaded.</p>

<h3>Corrections now cost money</h3>
<p>Here is the change most likely to affect a family. NECO has announced a <strong>&#8358;10,000 non-validation penalty per candidate</strong> for requests to change details - passport photograph, name, date of birth, gender, subjects, state of origin or local government area - after the online submission. A change of photograph is only allowed before the registration period ends, and only one change is permitted, whether it is a name or a photograph. All corrections have to be made offline, and the signed Offline Validation List goes to the NECO state office.</p>

<h3>Schools have to activate before they can register</h3>
<p>State coordinators must activate schools after receiving their validation documents. Only then can a school pay into the NECO Treasury Single Account through Remita, and only then does it receive a registration quota. Schools paying in instalments are told to make sure the first payment covers at least twenty candidates. Every payment is non-refundable.</p>
<p>Special needs candidates have to be flagged during offline bio-data entry - low vision, complete blindness, albinism, hearing impairment and autism are named explicitly - and schools must formally notify NECO about candidates with autism.</p>

<h3>Why this matters even if you are not sitting BECE</h3>
<p>Two reasons. First, the pattern is instructive: Nigerian examination bodies are moving towards biometric identity, online-first records and penalties for correction errors. The SSCE Internal rules for 2027 follow the same logic. Second, the registration season is now the busiest part of the year, with WAEC, NECO and JAMB all running at once. Knowing the deadlines early is worth more than knowing them late.</p>

<p>For students already past JSS 3, the SSCE Internal rules are the ones that matter - those are covered on <a href="https://mystudy.com.ng/neco.php">the NECO page here</a>, with free past questions and answers for every subject.</p>
HTML,
    ],

    /* ------------------------------------------------------------------ */
    /* JAMB                                                              */
    /* ------------------------------------------------------------------ */

    [
        'slug'  => 'jamb-2027-utme-six-point-checklist-and-pre-registration-app',
        'date'  => '2026-11-18 09:00:00',
        'date_label' => '18 November 2026',
        'title' => 'JAMB Gives 2027 UTME Candidates a Six-Point Checklist and a Pre-Registration App',
        'excerpt' => 'JAMB has told candidates to fix their NIMC records, phone, email and subject combinations before registration - and the Registrar has announced a mobile app that lets you do most of it from home.',
        'level' => 'Secondary',
        'banner' => ['file' => 'jamb-2027-checklist.png', 'exam' => 'jamb', 'kicker' => 'JAMB 2027 UTME'],
        'sources' => [
            ['Premium Times: UTME 2027 - JAMB releases checklist for candidates ahead of registration', 'https://www.premiumtimesng.com/news/top-news/923972-utme-2027-jamb-releases-checklist-for-candidates-ahead-of-registration.html'],
            ['Guardian Nigeria: JAMB to introduce pre-registration app, tighten biometric checks for 2027 UTME', 'https://guardian.ng/news/jamb-to-introduce-pre-registration-app-tighten-biometric-checks-for-2027-utme/'],
            ['Gazette NGR: JAMB issues guidelines for prospective 2027 UTME candidates', 'https://gazettengr.com/jamb-issues-guidelines-for-prospective-2027-utme-candidates/'],
        ],
        'content' => <<<'HTML'
<p>JAMB has not announced a registration date for the 2027 UTME. What it has done is tell candidates exactly what to fix before the date arrives, and shown its hand on how registration itself is about to change.</p>

<h3>The six-point checklist</h3>
<p>Published on the board's official channels in November 2026, the advisory asks every candidate to do six things:</p>
<ol>
<li>Make sure your NIMC biodata - name, date of birth and everything else - is correct.</li>
<li>Have a registered phone number in your own name.</li>
<li>Maintain an active email address you can actually get into.</li>
<li>Decide on the course you intend to study.</li>
<li>Choose the institution, and check its admission requirements.</li>
<li>Confirm that your UTME subject combination matches what your intended programme requires.</li>
</ol>
<p>The board's own words carry a warning worth repeating: be sure about the programme you intend to study, and understand the requirements before choosing, rather than picking a course and checking later.</p>

<h3>A pre-registration app is coming</h3>
<p>JAMB's Registrar, Professor Segun Aina, announced in Abuja in November that the board will roll out a mobile application to let candidates begin registration from home. The idea is that you pull your details from NIMC, fill in your bio-data, choose your subjects, courses and institutions, and only then visit a centre.</p>
<p>The app is also meant to be a guide. It will show you the previous year's entry requirements and entry marks for the programmes you are considering, so the course you pick is one you actually have a realistic score for.</p>

<h3>Two changes that will catch people out</h3>
<p><strong>Your subjects will be checked automatically.</strong> The board says a new mechanism will flag incorrect subject combinations for the intended course and stop the application until the right subjects are chosen. In other words, a wrong subject combination will no longer be something you discover after registration - it will be blocked on the spot.</p>
<p><strong>Biometric checks at the centre will be stricter.</strong> In exchange for the convenience of doing the paperwork at home, the board is applying enhanced checks at the point of examination to protect the integrity of the system.</p>
<p>There is one more change: JAMB plans to let candidates report questions they believe are faulty during the computer-based test, so that disputed items can be investigated and candidates assessed fairly.</p>

<h3>What has not been said yet</h3>
<p>No registration opening date, no closing date and no 2027 fee has been announced. Different outlets have guessed at December 2026 and January 2027; the board has confirmed neither. The 2026 cycle ran from 26 January to 28 February, which is a reasonable expectation for 2027 - but it is an expectation, not a date.</p>
<p>Two things that do not change: registration is personal. You register, no group and no school, and no cybercafé can do it for you. And you can register while your O-Level result is still pending, provided you upload it afterwards.</p>

<h3>Do the checklist now, while it costs nothing</h3>
<p>Every item on that list can be done today, and each one that is wrong on registration day costs you time you will not have. Check your NIMC record, confirm your phone and email, then sit down with the course list for your intended programme and match your subjects to it line by line.</p>
<p>Then practise. JAMB past questions with answers for every subject are on <a href="https://mystudy.com.ng/jamb.php">the JAMB page</a>, and the app lets you save a paper to your phone and sit it timed, offline, the way the real thing works.</p>
HTML,
    ],

    [
        'slug'  => 'jamb-2027-registration-dates-fees-what-is-confirmed',
        'date'  => '2026-11-21 09:00:00',
        'date_label' => '21 November 2026',
        'title' => 'JAMB 2027 UTME: What Is Confirmed, What Is Only Rumour, and How to Prepare',
        'excerpt' => 'Registration dates, fees and even the exam date for the 2027 UTME are still unannounced. Here is a clear separation of what JAMB has actually said from what people online are guessing.',
        'level' => 'Secondary',
        'banner' => ['file' => 'jamb-2027-dates-fees.png', 'exam' => 'jamb', 'kicker' => 'JAMB 2027 UTME'],
        'sources' => [
            ['Premium Times: UTME 2027 checklist, JAMB has not disclosed when registration begins', 'https://www.premiumtimesng.com/news/top-news/923972-utme-2027-jamb-releases-checklist-for-candidates-ahead-of-registration.html'],
            ['Jump Africa: JAMB registration 2027 - profile code, e-PIN fees and what is known', 'https://www.jump.africa/news/jamb-registration'],
            ['Guardian Nigeria: JAMB pre-registration app and biometric checks for 2027 UTME', 'https://guardian.ng/news/jamb-to-introduce-pre-registration-app-tighten-biometric-checks-for-2027-utme/'],
        ],
        'content' => <<<'HTML'
<p>Search for "JAMB 2027 registration" and you will find pages quoting an opening date of 5 December, another quoting 12 December, a third quoting 19 January 2027, and several that say the date is not out. Only one of those is the truth right now, and it is the last one.</p>

<h3>What JAMB has actually confirmed</h3>
<ul>
<li>A six-point preparation checklist, published in November 2026.</li>
<li>A pre-registration mobile application that will let candidates pull their details from NIMC, choose subjects, courses and institutions, and see previous entry marks - before visiting a centre.</li>
<li>Automatic validation of subject combinations, so a wrong combination blocks the application instead of surfacing later.</li>
<li>Tighter biometric checks at the point of examination, and a channel for reporting faulty questions during the CBT.</li>
<li>Candidates must register personally. No group registration, no school registration, no agents doing it for you.</li>
<li>You may register with your O-Level result still pending, provided you upload it later.</li>
<li>The minimum age is 16 by 30 September of the examination year, with the board reserving the right to make exceptions for candidates who show outstanding ability.</li>
</ul>

<h3>What is still guesswork</h3>
<ul>
<li><strong>Registration opening date.</strong> Not announced. The December dates circulating online are projections, not board announcements.</li>
<li><strong>Registration fees.</strong> Not announced for 2027. For the 2026 cycle the e-PIN cost &#8358;7,200 for UTME, &#8358;8,700 for UTME with the optional mock exam, and &#8358;5,700 for Direct Entry. Expect those to be the reference point, not the confirmed figure.</li>
<li><strong>Examination dates.</strong> A window of 15-25 March 2027 has been reported from a federal proposal, and it is explicitly not final.</li>
</ul>

<h3>How registration actually works, step by step</h3>
<ol>
<li>Create a profile code by sending your NIN to 55019 (or 66019) by SMS.</li>
<li>Buy your e-PIN from an authorised JAMB agent or bank. Never from anyone advertising a discount.</li>
<li>Visit a JAMB-accredited CBT centre in person with your profile code and e-PIN. Cybercafes cannot register you.</li>
<li>Biometric capture: photograph and all ten fingerprints.</li>
<li>Enter your choices - institutions, courses, a working personal email, your O-Level details, your UTME subjects and your exam town. You may choose up to three universities, one polytechnic, and one college of education or technical institution, with only one university as first choice.</li>
<li>Confirm with your fingerprint, print the slip, and check every line of it before you leave the centre.</li>
</ol>

<h3>What to do with the uncertainty</h3>
<p>The uncertainty is real, and chasing it is a waste of revision time. What is not a waste: making sure your NIMC record is accurate, your phone number is registered to you, your email works, and your subject combination matches your intended course. Those four things are on JAMB's own checklist, and every one of them can be done today.</p>
<p>Use the waiting time for the thing that actually decides your score. Free JAMB past questions with answers are on <a href="https://mystudy.com.ng/jamb.php">the JAMB page</a> - take them timed, check your score the moment you finish, and use the app to save papers for offline practice on the days the network is bad.</p>
HTML,
    ],

    /* ------------------------------------------------------------------ */
    /* Universities                                                       */
    /* ------------------------------------------------------------------ */

    [
        'slug'  => 'jamb-701805-unfilled-university-admission-slots-2026',
        'date'  => '2026-11-17 09:00:00',
        'date_label' => '17 November 2026',
        'title' => '701,805 University Admission Spaces Sat Empty: What JAMB\'s Numbers Mean for You',
        'excerpt' => 'Nigerian universities filled only 48% of their approved 2026 admission quota. The number looks shocking until you break it down - and it is good news for candidates still waiting.',
        'level' => 'University',
        'university_general' => true,
        'banner' => ['file' => 'jamb-empty-slots.png', 'exam' => 'university', 'kicker' => 'University admissions'],
        'sources' => [
            ['Guardian Nigeria: 52% varsity admission slots wasted as 700,000 spaces lie empty', 'https://guardian.ng/education/52-varsity-admission-slots-wasted-as-700000-spaces-lie-empty/'],
            ['Edugist: Nigerian universities leave over 700,000 admission slots unfilled in 2026', 'https://edugist.org/jamb-nigerian-universities-leave-over-700000-admission-slots-unfilled-in-2026/'],
            ['iRead To Live: Why JAMB should publish admission figures against approved quotas', 'https://ireadtolive.org/jamb-publish-admission-figures-approved-quotas/'],
        ],
        'content' => <<<'HTML'
<p>Half of Nigeria's university admission capacity went unused in the 2026 exercise, and the figure has been widely reported as a scandal. Presented by JAMB's Registrar, Professor Segun Aina, at the fourth-quarter meeting of the Committee of Vice-Chancellors of Nigerian Universities, the numbers are these:</p>

<ul>
<li>Total approved admission quota for degree programmes: <strong>1,352,297</strong></li>
<li>Admissions recorded at the time of the meeting: <strong>650,492</strong> - 48 per cent</li>
<li>Spaces still unutilised: <strong>701,805</strong> - 52 per cent</li>
</ul>

<p>For context, the 2025 exercise left 706,418 of 1,716,154 quota spaces unfilled, about 41 per cent. The gap is large either way, and it is worth understanding what it actually means.</p>

<h3>Read the dates before you read the headline</h3>
<p>The 2026/2027 admission exercise closes in stages. Public universities were to conclude admissions on JAMB's Central Admissions Processing System (CAPS) by 31 October 2026, private universities follow on 30 November, and polytechnics, colleges of education and other institutions by 31 December. The figure was presented in mid-November - before two of those three windows had closed. A large share of those 701,805 spaces is simply seats that had not yet been filled at the moment of the announcement.</p>
<p>The board also disclosed that half the universities on its list had still not updated their IBASS requirements, despite an agreement made at the 2026 policy meeting that they would do so by 31 October 2026. Institutions that do not publish their requirements cannot admit students properly, and this is the part of the story that is genuinely actionable.</p>

<h3>What it means if you are still waiting for admission</h3>
<ul>
<li><strong>There is capacity.</strong> Half the quota is unused. That does not guarantee a place in the programme you want, but the arithmetic is no longer against you.</li>
<li><strong>Wait for the institutions that have not finished.</strong> Private universities and polytechnics were still inside their admission windows at the time of the announcement. If your status is pending, that pending is often just the queue.</li>
<li><strong>Keep checking your CAPS status and your institution's own list.</strong> Offers move in waves, and some institutions publish names after the bulk upload.</li>
<li><strong>Watch the cut-off for your programme.</strong> Where capacity is left over, it is rarely spread evenly. Programmes with unpopular locations or subjects fill first; competitive ones do not.</li>
</ul>

<h3>What it means for the 2027 cycle</h3>
<p>Two lessons carry forward. Candidates should not read a single mid-cycle statistic as a verdict on their own chances, and institutions that do not publish their requirements early cause their own problems. If you are preparing for 2027, the requirement you can control is your score and your subject combination - both of which are settled before JAMB opens any portal.</p>

<p>Free JAMB past questions with answers for every subject are on <a href="https://mystudy.com.ng/jamb.php">the JAMB page</a>, and the same papers run as timed CBT tests in the MyStudy Base app.</p>
HTML,
    ],

    [
        'slug'  => 'nuc-warns-students-against-unapproved-university-centres',
        'date'  => '2026-11-17 09:00:00',
        'date_label' => '17 November 2026',
        'title' => 'NUC Warns Students Against Unapproved University Centres: Why Your Degree Could Be Rejected',
        'excerpt' => 'The NUC has declared the University of America and the London College of Management and Leadership Education unauthorised in Nigeria. Here is what that means for NYSC, Law School and professional registration.',
        'level' => 'University',
        'university_general' => true,
        'banner' => ['file' => 'nuc-unapproved-centres.png', 'exam' => 'university', 'kicker' => 'NUC warning'],
        'sources' => [
            ['Vanguard: University of America challenges NUC over illegal operations', 'https://www.vanguardngr.com/2026/11/university-of-america-challenges-nuc-over-illegal-operations/'],
            ['Edugist: NUC warns Nigerians against enrolling at unapproved University of America centres', 'https://edugist.org/nuc-warns-nigerians-against-enrolling-at-unapproved-university-of-america-centres/'],
            ['BBC News Pidgin: NUC raises alarm over activities of two foreign universities', 'https://www.bbc.com/pidgin/articles/ck7v2dlm5dp2o.lite'],
        ],
        'content' => <<<'HTML'
<p>On Tuesday, 17 November 2026, the National Universities Commission issued a public notice naming two foreign institutions and the Nigerian centres they were using. It is worth reading the list carefully, because the consequences run well beyond the institutions named.</p>

<h3>What the NUC said</h3>
<p>The commission, through its Director of Public Affairs, Rita N. Okonjo, stated that the University of America, Curacao and the London College of Management and Leadership Education (LCMLE), United Kingdom, were operating academic programmes in Nigeria without authorisation. Neither has been approved to operate as a university or award recognised degrees in the country.</p>
<p>The centres named were Cosmopolitan International Business School in Garki, Abuja, its purported Federal Capital Territory Learning Centre, and the University of America College of Science and Technology (UAPOLY) in Ekpoma, Edo State. The commission said the institutions had been admitting students, running programmes and presenting themselves as degree-awarding centres without approval.</p>

<h3>What it costs you if you are already enrolled</h3>
<p>This is the part that should worry students most. The NUC warned that qualifications from unapproved institutions <strong>will not be recognised</strong> for:</p>
<ul>
<li>NYSC mobilisation,</li>
<li>Professional registration with the relevant regulatory bodies,</li>
<li>Admission into the Nigerian Law School.</li>
</ul>
<p>That is a three-year investment - or five, or seven - with no recognised end point. The commission advised students, parents and guardians against seeking admission, paying fees or taking part in any programme run by those institutions or their affiliates, and said those involved risked regulatory and legal action.</p>

<h3>Check before you pay, not after</h3>
<p>The commission's own advice is the practical part: verify an institution's accreditation status on the NUC's official list <em>before</em> you make an admission or financial commitment. A promotional brochure, a scholarship offer and a visit to a learning centre in a shopping mall are not accreditation. Only the NUC's list of approved institutions counts.</p>

<h3>Do not confuse it with AUN</h3>
<p>One important clarification, because the names are similar and the confusion is already circulating: this warning is about the University of America in Curacao and LCMLE. It is not about the American University of Nigeria in Yola, Adamawa State, which was founded by former Vice-President Atiku Abubakar and received NUC accreditation in 2008. Two different institutions, one legitimate, one not - and the wrong one costs you a degree.</p>

<h3>Where the university sector is heading</h3>
<p>The University of America has publicly disputed the NUC's position, which tells you the dispute is unresolved in the public record. But the commission's position is the regulatory one, and until it says otherwise, the safest reading is that these centres are not recognised. If you are mid-programme there, the cost of a switch now is far lower than the cost of finishing a degree the country will not accept.</p>
<p>There is legitimate study available in Nigeria - the NUC approved several new institutions for the 2026/2027 session, and the board ran a nationwide mock accreditation exercise on 28 November 2026. Checking the official list puts you in touch with the ones that are real.</p>
HTML,
    ],

    [
        'slug'  => 'nuc-proposes-election-break-for-universities-in-2027',
        'date'  => '2026-11-25 09:00:00',
        'date_label' => '25 November 2026',
        'title' => 'NUC Proposes a Three-to-Four Week Break for Universities Around the 2027 Elections',
        'excerpt' => 'Lecturers and university staff are expected to serve as returning officers, so the commission is proposing a short academic break. Here is what it means for your lecture calendar and your exams.',
        'level' => 'University',
        'university_general' => true,
        'banner' => ['file' => 'nuc-election-break.png', 'exam' => 'university', 'kicker' => '2027 elections'],
        'sources' => [
            ['Precision News: 2027 Elections - NUC proposes four-week break for Nigerian universities', 'https://theprecisionng.com/2026/11/2027-elections-nuc-proposes-four-week-break-for-nigerian-universities.html'],
            ['Edugist: November 2026 education news round-up', 'https://edugist.org/2026/11/'],
        ],
        'content' => <<<'HTML'
<p>Something unusual is being proposed for Nigerian universities: a short, deliberate break in the academic calendar to accommodate the 2027 general elections. The National Universities Commission raised it with the Independent National Electoral Commission on Monday, 23 November 2026.</p>

<h3>Why the break is on the table at all</h3>
<p>University staff are expected to play significant roles in the electoral process as collation and returning officers, and the NUC's Executive Secretary, Professor Abdullahi Yusufu Ribadu, told the meeting with INEC's Chairman, Professor Joash Amupitan, and other senior officials that the commission wants to make that participation possible without wrecking academic programmes. He said lecturers and university administrators are expected to be among those carrying out those duties.</p>

<h3>What was actually proposed</h3>
<ul>
<li>A break of <strong>three to four weeks</strong>, not exceeding four.</li>
<li>Adjustments made by each university, not one national shutdown. The NUC intends to engage vice-chancellors and adjust individual academic calendars.</li>
<li>A formal circular to universities, issued only after INEC makes an official request.</li>
<li>An assurance that the adjustment will not harm students' academic activities.</li>
</ul>

<p>So this is a proposal in consultation, not a decision in force. Until the circular lands, nothing changes on your timetable.</p>

<h3>What students should watch for</h3>
<p>If the break happens, the practical effects are the kind that reward planning rather than panic. Lectures would pause for a few weeks in the middle of a semester. Examinations would either shift or be compressed into a shorter window after the break. Continuous assessment deadlines set for the weeks in question would most likely move with them. Some courses with heavy practical or laboratory content - which cannot simply be delivered online - would feel the shift most.</p>
<p>The sensible preparation is to keep your own records of what has been taught, so a compressed revision window does not catch you with gaps. That is exactly the habit a good revision timetable builds anyway.</p>

<h3>The other thing the NUC announced</h3>
<p>Alongside the election discussion, Professor Ribadu said the commission would run a nationwide mock accreditation and result transmission exercise on Saturday, 28 November 2026, across the 36 states and the Federal Capital Territory. Accreditation is the process that decides which institutions and programmes are recognised, so a mock run of it is a health check on the system every institution depends on.</p>

<h3>Why this belongs on a student's radar</h3>
<p>University calendars are not only about lectures. They set when results are released, when NYSC mobilisation happens, when forms close for the next session, and when transfers and change of course are possible. A short break in one semester touches all of that. Knowing that it is being discussed - and that it will be communicated formally through your university - is enough preparation for now.</p>
HTML,
    ],

    [
        'slug'  => 'new-universities-approved-nuc-2026-2027-session',
        'date'  => '2026-11-21 09:00:00',
        'date_label' => '21 November 2026',
        'title' => 'New Universities Approved for the 2026/2027 Session: Medical, Engineering and General Options',
        'excerpt' => 'The NUC has licensed new institutions in Anambra, Ebonyi and Ibadan, with more programmes opening and admission under way. Here is what is new and how to check any institution yourself.',
        'level' => 'University',
        'university_general' => true,
        'banner' => ['file' => 'nuc-new-universities.png', 'exam' => 'university', 'kicker' => 'New institutions'],
        'sources' => [
            ['Vanguard: NUC approves medical university in Anambra, opens December 1', 'https://www.vanguardngr.com/2026/11/nuc-approves-medical-university-in-anambra-opens-december-1/'],
            ['The Sun: Ebonyi new varsity begins students\' admission', 'https://thesun.ng/ebonyi-new-varsity-begins-students-admission/'],
            ['Vanguard: NUC approves 7 new programmes for Precious Cornerstone varsity', 'https://www.vanguardngr.com/2026/11/nuc-approves-7-new-programmes-for-precious-cornerstone-varsity/'],
        ],
        'content' => <<<'HTML'
<p>Every November the National Universities Commission approves new institutions and new programmes, and this year's batch is already taking students. Here is what was approved, and what it means if you are shopping for where to study.</p>

<h3>Transatlantic University of Medicine and Health Sciences, Anambra</h3>
<p>The NUC approved the Transatlantic University of Medicine and Health Sciences, Umuchukwu, in Orumba South Local Government Area of Anambra State, with academic activities scheduled to commence in December 2026. Its Vice-Chancellor, Professor Charles Esimone, said admission had opened on 1 October and that lectures would begin in the first week of December.</p>
<p>The detail that matters for candidates is administrative rather than academic: once NUC approved the institution, it formally communicated the approval to JAMB, and JAMB issued the university an identification code that lets candidates apply for admission through the national system. Any institution you see on the JAMB list is one the board recognises.</p>

<h3>Euston University, Abakaliki, Ebonyi State</h3>
<p>The newly established Euston University has also begun admitting students into five programmes approved by the commission: Nursing Science, Public Health, Medical Laboratory Science, Cyber Security and Computer Science. The institution's Acting Vice-Chancellor, Professor Felicia Moh, asked suitably qualified candidates to collect and complete admission forms with their documents, noting that admission spaces for the 2026/2027 session are limited.</p>

<h3>Precious Cornerstone University, Ibadan</h3>
<p>Existing institutions are growing too. The NUC approved seven additional programmes for Precious Cornerstone University, Ibadan, beginning from the 2026/2027 session: Nursing Science, Public Health, Medical Laboratory Science, Community Health Science, Christian Religious Studies, Music and Performing Arts. The approval followed an assessment of the university's preparedness.</p>

<h3>How to check anything for yourself</h3>
<p>Three things make this checkable in minutes rather than months:</p>
<ul>
<li><strong>The NUC list of approved institutions and programmes.</strong> If a programme is not on it, it is not accredited, whatever the prospectus says.</li>
<li><strong>The JAMB platform itself.</strong> A JAMB identification code is the practical test. Institutions that appear in the national admission process have been through the system.</li>
<li><strong>The institution's own admissions page.</strong> Legitimate universities publish their programme list, entry requirements and the fees they will actually charge.</li>
</ul>

<h3>A small warning worth repeating</h3>
<p>The approval of new institutions in the same month that the NUC was shutting down unauthorised ones is not a coincidence - it is the commission doing both halves of its job. New institutions are good news for students, as competition for places grows and more programmes open in medicine, nursing and technology. But a fast-growing sector is also a target sector, and the warning issued on 17 November 2026 makes the standard plain: verify before you pay, not after you have finished.</p>

<p>If you are still choosing where to study, start from the NUC list, then check the programme's JAMB subject requirements, then look for the institution's own past questions and course materials. Free university-level materials are in the <a href="https://mystudy.com.ng/materials.php?level=University">materials library</a>.</p>
HTML,
    ],

    [
        'slug'  => 'neco-2027-ssce-registration-guidelines-in-plain-language',
        'title' => 'NECO 2027 SSCE Registration Guidelines: The Plain-Language Version for SS3 Students',
        'excerpt' => 'NECO&#8217;s registration circular is eight pages of official rules. Here is the same thing in ordinary English: who can register, what your school must do, every fee, and the four mistakes that cost students money.',
        'level' => 'Secondary',
        'date'  => '2026-11-02 09:00:00',
        'date_label' => '2 November 2026',
        'banner' => ['file' => 'neco-2027-guidelines.png', 'exam' => 'neco', 'kicker' => 'NECO 2027'],
        'sources' => [
            ['NECO: 2027 SSCE Internal', 'https://neco.gov.ng/exams/ssce-internal'],
            ['National Examinations Council, official website', 'https://neco.gov.ng'],
            ['NECO school registration portal', 'https://ssceinternal.neco.gov.ng'],
            ['NECO Continuous Assessment Scores portal', 'https://cas.neco.gov.ng'],
        ],
        'content' => <<<'HTML'
<p>NECO&#8217;s registration circular for the 2027 SSCE Internal is eight pages of official rules, written for principals and state ministries. If you are an SS3 student, this is the same document in ordinary language: who can register, what has to happen before you can sit the paper, what it costs, and the four mistakes that cost students money.</p>

<h3>First, can you even register?</h3>
<ul>
<li>Only students in their <strong>final year, SSS 3</strong>.</li>
<li>Only candidates who are <strong>in a school</strong> &#8212; in Nigeria or overseas.</li>
<li><strong>Private candidates cannot register</strong> for this paper. If you are not in a school, you need the NECO External instead.</li>
<li>Moving into SSS 3 from another school is <strong>not allowed</strong>. NECO has prohibited it, so your school cannot take you in at this stage.</li>
</ul>

<h3>The dates</h3>
<ul>
<li>Registration opens: <strong>Monday 2 November 2026</strong>.</li>
<li>Normal registration closes: <strong>11:59 pm, Friday 26 February 2027</strong>.</li>
<li>Late registration: <strong>Saturday 27 February to Friday 5 March 2027</strong>. The portal shuts at 11:59 pm on 5 March, and there is <strong>no extension</strong>.</li>
<li>The printed payment receipt and the signed validation list go to the NECO state office by <strong>12 March 2027</strong>. (The circular gives 12 March in one section and 5 March in another &#8212; confirm with your state office.)</li>
<li>The examination itself: <strong>Thursday 1 April to Tuesday 11 May 2027</strong>.</li>
</ul>

<h3>What you personally need</h3>
<ul>
<li>A valid <strong>NIN</strong>. It is now compulsory for every candidate.</li>
<li>A <strong>recent, sharp passport photograph</strong>, front facing, taken in your school uniform.</li>
<li>Your subjects, and your fingerprints captured at the school.</li>
</ul>

<h3>What your school has to do &#8212; and why you should ask about it now</h3>
<ol>
<li>Claim the school portal at <strong>ssceinternal.neco.gov.ng</strong> using the school&#8217;s official email and phone number.</li>
<li>Install the 2027 offline application and enter every candidate&#8217;s bio-data, including the NIN.</li>
<li>Capture fingerprints for all ten fingers with a FUTRONIC (FS80H) scanner.</li>
<li>Print each candidate&#8217;s photocard, check the details, and collect signatures on the validation list.</li>
<li>Nominate supervisors &#8212; this is now mandatory and registration cannot be completed without it.</li>
<li>Pay into the NECO Treasury Single Account through Revop/Remita.</li>
<li>Upload the entries online to get registration numbers.</li>
</ol>

<h3>The line that catches people: you are not registered until you have a registration number</h3>
<p>Entering your details offline is not registration. A candidate is only deemed registered when the offline entries are <strong>uploaded online and a registration number is issued</strong>, which can only happen after payment and after the supervisors have been nominated. Partial uploads are not accepted either, so your school has to confirm that every candidate was uploaded, not some of them.</p>

<h3>What it costs</h3>
<ul>
<li>Registration: <strong>&#8358;30,000</strong> per candidate</li>
<li>Four-figure mathematical table: <strong>&#8358;1,300</strong> per candidate</li>
<li>Waterproof certificate jacket or folder: <strong>&#8358;2,500</strong> per candidate</li>
<li>Syllabus: <strong>&#8358;2,000</strong> per copy &#8212; schools are advised to get at least four</li>
<li>E-photo album: <strong>&#8358;2,700</strong> per pair per school, and one album holds 171 candidates</li>
<li>Late registration: an extra <strong>&#8358;5,000</strong> per candidate</li>
<li>Unviable school fee: <strong>&#8358;70,000</strong> per school, if the school registers fewer than 20 candidates</li>
</ul>
<p>Stamp duty, service and Revop/Remita charges apply on top, and every payment is non-refundable. So the least a single candidate can cost is <strong>&#8358;33,800</strong> &#8212; the &#8358;30,000 registration fee, the &#8358;1,300 table and the &#8358;2,500 certificate jacket.</p>

<h3>The four mistakes that cost real money</h3>
<ol>
<li><strong>A blurred photograph.</strong> A school will not be activated for payment if any of its candidates has a blurred photo, and no certificate will be issued for a candidate with a poor quality photo. One soft picture can hold up everybody.</li>
<li><strong>Changing your details after the upload.</strong> That is called non-validation, and the penalty is <strong>&#8358;10,000 per candidate</strong>, whatever the change was. Only <strong>one</strong> change is allowed before registration closes &#8212; a photograph or a name, not both. Other corrections (gender, date of birth, subjects, state of origin, local government area, or a spelling in a name) can still be made before the examination ends.</li>
<li><strong>Somebody else capturing your fingerprints.</strong> If the fingerprints taken do not match the data uploaded, you will be treated as an impostor and you will not be allowed into the examination hall. Nobody can stand in for you.</li>
<li><strong>Registering twice.</strong> It will not be entertained. Your NIN and your fingerprints are unique, and double registration creates exactly the problem the biometric rule exists to catch.</li>
</ol>
<p>One more to remember: paying with a Revop/Remita e-invoice that was generated before registration closed will not be entertained, and it will not be refunded.</p>

<h3>The supervisor rule, in one line</h3>
<p>Public schools nominate two supervisors and two assistant supervisors; private schools nominate two supervisors. Every nominee needs a first degree in Education (or a first degree/HND with PGDE), a TRCN certificate, a NIN slip, and must not be over 60. Private school nominees must be at least 30, have three years of teaching experience, and hold at least the rank of Vice Principal.</p>

<h3>Continuous Assessment Scores are now uploaded every term</h3>
<p>For SSS 1 and SSS 2 only, Continuous Assessment Scores go up term by term &#8212; January, April and August &#8212; on <strong>cas.neco.gov.ng</strong>. Schools that miss those timelines get sanctioned, and a sanctioned school is a school whose candidates cannot be registered on time.</p>

<h3>How the paper will be written</h3>
<p>The 2027 examination runs in <strong>dual mode</strong>: hybrid computer-based testing and pen-and-paper, side by side. Your school must be told before the examination which format each set of candidates sits, and a school with computer facilities has to meet the standard for a CBE centre as listed on the NECO portal.</p>

<h3>What to do this week</h3>
<ul>
<li>Ask whether your school has claimed its portal and installed the 2027 offline app.</li>
<li>Have your NIN ready and get a sharp passport photograph taken in your school uniform.</li>
<li>Look at that photograph properly. A blurred one stops your school from being activated for payment.</li>
<li>Ask when fingerprint capture is happening, and make sure you are there for it.</li>
<li>If you are not in a school, this paper is not for you &#8212; look for the NECO External.</li>
</ul>

<p style="text-align:center; margin: 2rem 0;"><a class="btn btn-success btn-lg" style="border-radius: 50px; font-weight: 700; padding: 0.9rem 2rem;" href="https://mystudy.com.ng/materials.php?q=2027%20NECO%20%28SSCE%20INTERNAL%29%20REGISTRATION%20GUIDELINES"><i class="fas fa-file-pdf me-2"></i>Download the official PDF guide</a></p>

<p>The button takes you to our materials library and searches for <strong>2027 NECO (SSCE INTERNAL) REGISTRATION GUIDELINES</strong> &#8212; the full circular from NECO, so you can read the wording yourself rather than take somebody&#8217;s summary of it.</p>
HTML,
    ],

];
