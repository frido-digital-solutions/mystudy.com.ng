<?php
/**
 * The subjects of the Study library, and the names each source calls them.
 *
 * The three sources do not agree on how a subject is spelled - Acadmate says
 * "use-of-english" and "christian-religious-knowledge", SmarTest says
 * "english-language" and "crs", StudentFirst says "english" and "crs". A
 * visitor must not have to know which site a question came from, so every
 * source slug is mapped here onto one canonical subject, and the library
 * stores the canonical slug.
 *
 * Return shape:
 *
 *   'english-language' => [
 *       'name'    => 'English Language',   // shown on the site
 *       'acadmate'    => ['use-of-english'],
 *       'smartest'    => ['english-language'],
 *       'studentfirst'=> ['english'],
 *   ]
 *
 * A source with no entry for a subject simply has no papers for it.
 */

return [
    'english-language' => [
        'name' => 'English Language',
        'acadmate' => ['use-of-english'],
        'studyproper' => ['English Language', 'english language'],
        'smartest' => ['english-language'],
        'studentfirst' => ['english'],
        'skolra' => ['english'],
    ],
    'mathematics' => [
        'name' => 'Mathematics',
        'acadmate' => ['mathematics'],
        'studyproper' => ['mathematics'],
        'skolra' => ['mathematics'],
        'smartest' => ['mathematics'],
        'studentfirst' => ['mathematics'],
    ],
    'physics' => [
        'name' => 'Physics',
        'acadmate' => ['physics'],
        'studyproper' => ['physics'],
        'skolra' => ['physics'],
        'smartest' => ['physics'],
        'studentfirst' => ['physics'],
    ],
    'chemistry' => [
        'name' => 'Chemistry',
        'acadmate' => ['chemistry'],
        'studyproper' => ['chemistry'],
        'skolra' => ['chemistry'],
        'smartest' => ['chemistry'],
        'studentfirst' => ['chemistry'],
    ],
    'biology' => [
        'name' => 'Biology',
        'acadmate' => ['biology'],
        'studyproper' => ['biology'],
        'skolra' => ['biology'],
        'smartest' => ['biology'],
        'studentfirst' => ['biology'],
    ],
    'government' => [
        'name' => 'Government',
        'acadmate' => ['government'],
        'studyproper' => ['government'],
        'skolra' => ['government'],
        'smartest' => ['government'],
        'studentfirst' => ['government'],
    ],
    'economics' => [
        'name' => 'Economics',
        'acadmate' => ['economics'],
        'studyproper' => ['economics'],
        'skolra' => ['economics'],
        'smartest' => ['economics'],
        'studentfirst' => ['economics'],
    ],
    'commerce' => [
        'name' => 'Commerce',
        'acadmate' => ['commerce'],
        'studyproper' => ['commerce'],
        'skolra' => ['commerce'],
        'smartest' => ['commerce'],
        'studentfirst' => ['commerce'],
    ],
    'geography' => [
        'name' => 'Geography',
        'acadmate' => ['geography'],
        'studyproper' => ['geography'],
        'skolra' => ['geography'],
        'smartest' => ['geography'],
        'studentfirst' => ['geography'],
    ],
    'literature' => [
        'name' => 'Literature in English',
        'acadmate' => ['literature-in-english'],
        'studyproper' => ['englishlit'],
        'skolra' => ['literature'],
        'smartest' => ['literature'],
        'studentfirst' => ['literature'],
    ],
    'crs' => [
        'name' => 'Christian Religious Studies',
        'acadmate' => ['christian-religious-knowledge'],
        'studyproper' => ['crk'],
        'skolra' => ['crk'],
        'smartest' => ['crs'],
        'studentfirst' => ['crs'],
    ],
    'irs' => [
        'name' => 'Islamic Studies',
        'smartest' => ['islamic-studies'],
        'studyproper' => ['irk'],
        'skolra' => ['irk'],
        'studentfirst' => ['irs'],
    ],
    'accounting' => [
        'name' => 'Financial Accounting',
        'acadmate' => ['accounts'],
        'studyproper' => ['accounting'],
        'skolra' => ['accounts'],
        'smartest' => ['accounting'],
        'studentfirst' => ['accounting'],
    ],
    'agricultural-science' => [
        'name' => 'Agricultural Science',
        'smartest' => ['agricultural-science'],
        'studyproper' => ['agriculture'],
        'skolra' => ['agricultural-science'],
        'studentfirst' => ['agriculture'],
    ],
    'computer-studies' => [
        'name' => 'Computer Studies',
        'smartest' => ['computer-studies'],
        'studyproper' => ['computerstudies'],
        'skolra' => ['computer-studies'],
        'studentfirst' => ['computer-studies'],
    ],
    'civic-education' => [
        'name' => 'Civic Education',
        'studentfirst' => ['civic-education'],
        'studyproper' => ['civiledu'],
        'skolra' => ['civic-education'],
    ],
    'further-mathematics' => [
        'name' => 'Further Mathematics',
        'studentfirst' => ['further-mathematics'],
        'skolra' => ['further-mathematics'],
    ],
    'data-processing' => [
        'name' => 'Data Processing',
        'studentfirst' => ['data-processing'],
        'skolra' => ['data-processing'],
    ],
    'home-economics' => [
        'name' => 'Home Economics',
        'smartest' => ['home-economics'],
        'studentfirst' => ['foods-and-nutrition'],
    ],
    'physical-education' => [
        'name' => 'Physical and Health Education',
        'smartest' => ['phe'],
        'skolra' => ['physical-education'],
        'studentfirst' => ['physical-education'],
    ],
    'technical-drawing' => [
        'name' => 'Technical Drawing',
        'studentfirst' => ['technical-drawing'],
    ],
    'business-studies' => [
        'name' => 'Business Studies',
        'studentfirst' => ['business-studies'],
    ],
    'animal-husbandry' => [
        'name' => 'Animal Husbandry',
        'studentfirst' => ['animal-husbandry'],
    ],
    'marketing' => [
        'name' => 'Marketing',
        'studentfirst' => ['marketing'],
        'skolra' => ['marketing'],
    ],
    'ict' => [
        'name' => 'Information and Communication Technology',
        'studentfirst' => ['ict'],
    ],
    'dyeing-and-bleaching' => [
        'name' => 'Dyeing and Bleaching',
        'studentfirst' => ['dyeing-and-bleaching'],
    ],
    'art' => [
        'name' => 'Fine Art',
        'smartest' => ['art'],
        'studyproper' => ['fineart'],
        'skolra' => ['fine-arts'],
    ],
    'history' => [
        'name' => 'History',
        'smartest' => ['history'],
        'studyproper' => ['history'],
        'skolra' => ['history'],
    ],
    'music' => [
        'name' => 'Music',
        'smartest' => ['music'],
        'skolra' => ['music'],
    ],
    'arabic' => [
        'name' => 'Arabic',
        'smartest' => ['arabic'],
        'studyproper' => ['arabicstudies'],
        'skolra' => ['arabic'],
    ],
    'french' => [
        'name' => 'French',
        'smartest' => ['french'],
        'skolra' => ['french'],
    ],
    'hausa' => [
        'name' => 'Hausa',
        'smartest' => ['hausa'],
        'studyproper' => ['hausa'],
        'skolra' => ['hausa'],
    ],
    'igbo' => [
        'name' => 'Igbo',
        'smartest' => ['igbo'],
        'studyproper' => ['igbo'],
        'skolra' => ['igbo'],
    ],
    'yoruba' => [
        'name' => 'Yoruba',
        'smartest' => ['yoruba'],
        'studyproper' => ['yoruba'],
        'skolra' => ['yoruba'],
    ],
    'catering-craft' => [
        'name' => 'Catering Craft Practice',
        'skolra' => ['catering-craft'],
    ],
    'insurance' => [
        'name' => 'Insurance',
        'skolra' => ['insurance'],
        'studyproper' => ['insurance'],
    ],
    'office-practice' => [
        'name' => 'Office Practice',
        'skolra' => ['office-practice'],
    ],
];
