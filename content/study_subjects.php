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
        'smartest' => ['english-language'],
        'studentfirst' => ['english'],
        'skolra' => ['english'],
    ],
    'mathematics' => [
        'name' => 'Mathematics',
        'acadmate' => ['mathematics'],
        'skolra' => ['mathematics'],
        'smartest' => ['mathematics'],
        'studentfirst' => ['mathematics'],
    ],
    'physics' => [
        'name' => 'Physics',
        'acadmate' => ['physics'],
        'skolra' => ['physics'],
        'smartest' => ['physics'],
        'studentfirst' => ['physics'],
    ],
    'chemistry' => [
        'name' => 'Chemistry',
        'acadmate' => ['chemistry'],
        'skolra' => ['chemistry'],
        'smartest' => ['chemistry'],
        'studentfirst' => ['chemistry'],
    ],
    'biology' => [
        'name' => 'Biology',
        'acadmate' => ['biology'],
        'skolra' => ['biology'],
        'smartest' => ['biology'],
        'studentfirst' => ['biology'],
    ],
    'government' => [
        'name' => 'Government',
        'acadmate' => ['government'],
        'skolra' => ['government'],
        'smartest' => ['government'],
        'studentfirst' => ['government'],
    ],
    'economics' => [
        'name' => 'Economics',
        'acadmate' => ['economics'],
        'skolra' => ['economics'],
        'smartest' => ['economics'],
        'studentfirst' => ['economics'],
    ],
    'commerce' => [
        'name' => 'Commerce',
        'acadmate' => ['commerce'],
        'skolra' => ['commerce'],
        'smartest' => ['commerce'],
        'studentfirst' => ['commerce'],
    ],
    'geography' => [
        'name' => 'Geography',
        'acadmate' => ['geography'],
        'skolra' => ['geography'],
        'smartest' => ['geography'],
        'studentfirst' => ['geography'],
    ],
    'literature' => [
        'name' => 'Literature in English',
        'acadmate' => ['literature-in-english'],
        'skolra' => ['literature'],
        'smartest' => ['literature'],
        'studentfirst' => ['literature'],
    ],
    'crs' => [
        'name' => 'Christian Religious Studies',
        'acadmate' => ['christian-religious-knowledge'],
        'skolra' => ['crk'],
        'smartest' => ['crs'],
        'studentfirst' => ['crs'],
    ],
    'irs' => [
        'name' => 'Islamic Studies',
        'smartest' => ['islamic-studies'],
        'skolra' => ['irk'],
        'studentfirst' => ['irs'],
    ],
    'accounting' => [
        'name' => 'Financial Accounting',
        'acadmate' => ['accounts'],
        'skolra' => ['accounts'],
        'smartest' => ['accounting'],
        'studentfirst' => ['accounting'],
    ],
    'agricultural-science' => [
        'name' => 'Agricultural Science',
        'smartest' => ['agricultural-science'],
        'skolra' => ['agricultural-science'],
        'studentfirst' => ['agriculture'],
    ],
    'computer-studies' => [
        'name' => 'Computer Studies',
        'smartest' => ['computer-studies'],
        'skolra' => ['computer-studies'],
        'studentfirst' => ['computer-studies'],
    ],
    'civic-education' => [
        'name' => 'Civic Education',
        'studentfirst' => ['civic-education'],
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
        'skolra' => ['fine-arts'],
    ],
    'history' => [
        'name' => 'History',
        'smartest' => ['history'],
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
        'skolra' => ['hausa'],
    ],
    'igbo' => [
        'name' => 'Igbo',
        'smartest' => ['igbo'],
        'skolra' => ['igbo'],
    ],
    'yoruba' => [
        'name' => 'Yoruba',
        'smartest' => ['yoruba'],
        'skolra' => ['yoruba'],
    ],
    'catering-craft' => [
        'name' => 'Catering Craft Practice',
        'skolra' => ['catering-craft'],
    ],
    'insurance' => [
        'name' => 'Insurance',
        'skolra' => ['insurance'],
    ],
    'office-practice' => [
        'name' => 'Office Practice',
        'skolra' => ['office-practice'],
    ],
];
