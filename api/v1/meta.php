<?php
/**
 * GET api/v1/meta.php — the pick lists the website renders from the database.
 *
 * The app must not hardcode universities: when the admin adds one on the
 * website it has to appear on the phone with no app release.
 */
require_once __DIR__ . '/_bootstrap.php';

$universities = [];
try {
    $universities = $pdo->query('SELECT name FROM universities ORDER BY name ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    // The table only exists once the seed has run; an empty list is a valid answer.
    error_log('api meta universities: ' . $e->getMessage());
}

api_out([
    'ok' => true,
    'universities' => array_values($universities),
    'university_levels' => ['100 Level', '200 Level', '300 Level', '400 Level', '500 Level', 'Postgraduate'],
    'user_types' => ['secondary', 'university'],
    'registrations_open' => site_setting('allow_registration', '1') === '1',
    'site_name' => SITE_NAME,
]);
