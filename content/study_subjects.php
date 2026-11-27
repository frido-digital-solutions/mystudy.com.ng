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
        'schoolngr' => ['english-language'],
    ],
    'mathematics' => [
        'name' => 'Mathematics',
        'schoolngr' => ['mathematics'],
        'acadmate' => ['mathematics'],
        'studyproper' => ['mathematics'],
        'skolra' => ['mathematics'],
        'smartest' => ['mathematics'],
        'studentfirst' => ['mathematics'],
    ],
    'physics' => [
        'name' => 'Physics',
        'schoolngr' => ['physics'],
        'acadmate' => ['physics'],
        'studyproper' => ['physics'],
        'skolra' => ['physics'],
        'smartest' => ['physics'],
        'studentfirst' => ['physics'],
    ],
    'chemistry' => [
        'name' => 'Chemistry',
        'schoolngr' => ['chemistry'],
        'acadmate' => ['chemistry'],
        'studyproper' => ['chemistry'],
        'skolra' => ['chemistry'],
        'smartest' => ['chemistry'],
        'studentfirst' => ['chemistry'],
    ],
    'biology' => [
        'name' => 'Biology',
        'schoolngr' => ['biology'],
        'acadmate' => ['biology'],
        'studyproper' => ['biology'],
        'skolra' => ['biology'],
        'smartest' => ['biology'],
        'studentfirst' => ['biology'],
    ],
    'government' => [
        'name' => 'Government',
        'schoolngr' => ['government'],
        'acadmate' => ['government'],
        'studyproper' => ['government'],
        'skolra' => ['government'],
        'smartest' => ['government'],
        'studentfirst' => ['government'],
    ],
    'economics' => [
        'name' => 'Economics',
        'schoolngr' => ['economics'],
        'acadmate' => ['economics'],
        'studyproper' => ['economics'],
        'skolra' => ['economics'],
        'smartest' => ['economics'],
        'studentfirst' => ['economics'],
    ],
    'commerce' => [
        'name' => 'Commerce',
        'schoolngr' => ['commerce'],
        'acadmate' => ['commerce'],
        'studyproper' => ['commerce'],
        'skolra' => ['commerce'],
        'smartest' => ['commerce'],
        'studentfirst' => ['commerce'],
    ],
    'geography' => [
        'name' => 'Geography',
        'schoolngr' => ['geography'],
        'acadmate' => ['geography'],
        'studyproper' => ['geography'],
        'skolra' => ['geography'],
        'smartest' => ['geography'],
        'studentfirst' => ['geography'],
    ],
    'literature' => [
        'name' => 'Literature in English',
        'schoolngr' => ['literature-in-english'],
        'acadmate' => ['literature-in-english'],
        'studyproper' => ['englishlit'],
        'skolra' => ['literature'],
        'smartest' => ['literature'],
        'studentfirst' => ['literature'],
    ],
    'crs' => [
        'name' => 'Christian Religious Studies',
        'schoolngr' => ['christian-religious-studies', 'christian-religious-studies-crs'],
        'acadmate' => ['christian-religious-knowledge'],
        'studyproper' => ['crk'],
        'skolra' => ['crk'],
        'smartest' => ['crs'],
        'studentfirst' => ['crs'],
    ],
    'irs' => [
        'name' => 'Islamic Studies',
        'schoolngr' => ['islamic-religious-studies', 'islamic-religious-studies-irs'],
        'smartest' => ['islamic-studies'],
        'studyproper' => ['irk'],
        'skolra' => ['irk'],
        'studentfirst' => ['irs'],
    ],
    'accounting' => [
        'name' => 'Financial Accounting',
        'schoolngr' => ['accounts-principles-of-accounts', 'bookkeeping'],
        'acadmate' => ['accounts'],
        'studyproper' => ['accounting'],
        'skolra' => ['accounts'],
        'smartest' => ['accounting'],
        'studentfirst' => ['accounting'],
    ],
    'agricultural-science' => [
        'name' => 'Agricultural Science',
        'schoolngr' => ['agricultural-science'],
        'smartest' => ['agricultural-science'],
        'studyproper' => ['agriculture'],
        'skolra' => ['agricultural-science'],
        'studentfirst' => ['agriculture'],
    ],
    'computer-studies' => [
        'name' => 'Computer Studies',
        'schoolngr' => ['computer-studies'],
        'smartest' => ['computer-studies'],
        'studyproper' => ['computerstudies'],
        'skolra' => ['computer-studies'],
        'studentfirst' => ['computer-studies'],
    ],
    'civic-education' => [
        'name' => 'Civic Education',
        'schoolngr' => ['civic-education'],
        'studentfirst' => ['civic-education'],
        'studyproper' => ['civiledu'],
        'skolra' => ['civic-education'],
    ],
    'further-mathematics' => [
        'name' => 'Further Mathematics',
        'schoolngr' => ['further-mathematics'],
        'studentfirst' => ['further-mathematics'],
        'skolra' => ['further-mathematics'],
    ],
    'data-processing' => [
        'name' => 'Data Processing',
        'schoolngr' => ['data-processing'],
        'studentfirst' => ['data-processing'],
        'skolra' => ['data-processing'],
    ],
    'home-economics' => [
        'name' => 'Home Economics',
        'schoolngr' => ['food-and-nutrition', 'foods-and-nutrition', 'home-economics', 'home-management'],
        'smartest' => ['home-economics'],
        'studentfirst' => ['foods-and-nutrition'],
    ],
    'physical-education' => [
        'name' => 'Physical and Health Education',
        'schoolngr' => ['physical-education'],
        'smartest' => ['phe'],
        'skolra' => ['physical-education'],
        'studentfirst' => ['physical-education'],
    ],
    'technical-drawing' => [
        'name' => 'Technical Drawing',
        'schoolngr' => ['technical-drawing'],
        'studentfirst' => ['technical-drawing'],
    ],
    'business-studies' => [
        'name' => 'Business Studies',
        'studentfirst' => ['business-studies'],
    ],
    'animal-husbandry' => [
        'name' => 'Animal Husbandry',
        'schoolngr' => ['animal-husbandry'],
        'studentfirst' => ['animal-husbandry'],
    ],
    'marketing' => [
        'name' => 'Marketing',
        'schoolngr' => ['marketing'],
        'studentfirst' => ['marketing'],
        'skolra' => ['marketing'],
    ],
    'ict' => [
        'name' => 'Information and Communication Technology',
        'studentfirst' => ['ict'],
    ],
    'dyeing-and-bleaching' => [
        'name' => 'Dyeing and Bleaching',
        'schoolngr' => ['dyeing-and-bleaching'],
        'studentfirst' => ['dyeing-and-bleaching'],
    ],
    'art' => [
        'name' => 'Fine Art',
        'schoolngr' => ['visual-art', 'fine-arts', 'art'],
        'smartest' => ['art'],
        'studyproper' => ['fineart'],
        'skolra' => ['fine-arts'],
    ],
    'history' => [
        'name' => 'History',
        'schoolngr' => ['history'],
        'smartest' => ['history'],
        'studyproper' => ['history'],
        'skolra' => ['history'],
    ],
    'music' => [
        'name' => 'Music',
        'schoolngr' => ['music'],
        'smartest' => ['music'],
        'skolra' => ['music'],
    ],
    'arabic' => [
        'name' => 'Arabic',
        'schoolngr' => ['arabic'],
        'smartest' => ['arabic'],
        'studyproper' => ['arabicstudies'],
        'skolra' => ['arabic'],
    ],
    'french' => [
        'name' => 'French',
        'schoolngr' => ['french'],
        'smartest' => ['french'],
        'skolra' => ['french'],
    ],
    'hausa' => [
        'name' => 'Hausa',
        'schoolngr' => ['hausa'],
        'smartest' => ['hausa'],
        'studyproper' => ['hausa'],
        'skolra' => ['hausa'],
    ],
    'igbo' => [
        'name' => 'Igbo',
        'schoolngr' => ['igbo'],
        'smartest' => ['igbo'],
        'studyproper' => ['igbo'],
        'skolra' => ['igbo'],
    ],
    'yoruba' => [
        'name' => 'Yoruba',
        'schoolngr' => ['yoruba'],
        'smartest' => ['yoruba'],
        'studyproper' => ['yoruba'],
        'skolra' => ['yoruba'],
    ],
    'catering-craft' => [
        'name' => 'Catering Craft Practice',
        'schoolngr' => ['catering-craft-practice'],
        'skolra' => ['catering-craft'],
    ],
    'insurance' => [
        'name' => 'Insurance',
        'schoolngr' => ['insurance'],
        'skolra' => ['insurance'],
        'studyproper' => ['insurance'],
    ],
    'office-practice' => [
        'name' => 'Office Practice',
        'schoolngr' => ['office-practice'],
        'skolra' => ['office-practice'],
    ],
];
