<?php
/**
 * Study tools for the app: the CGPA calculator and the timetable.
 *
 * Both write to the same tables the website pages write to (cgpa_entries and
 * timetable_entries), so a course added on the phone shows on the website and a
 * lecture saved in the browser shows on the phone. Nothing is duplicated and
 * nothing is converted.
 *
 * The grade scale is answered with every list so the app never carries its own
 * copy of it - if the school's scale ever changes, it changes in one place.
 */

require_once __DIR__ . '/_bootstrap.php';

$user = api_auth();
$userId = (int)$user['id'];
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$in = $isPost ? api_input() : [];
$action = $isPost ? api_field($in, 'action') : trim((string)($_GET['action'] ?? 'cgpa'));

/** The 5-point scale the website's own calculator uses. */
function cgpa_scale(): array
{
    return ['A' => 5.0, 'B' => 4.0, 'C' => 3.0, 'D' => 2.0, 'E' => 1.0, 'F' => 0.0];
}

function cgpa_tables(PDO $pdo): void
{
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS cgpa_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            course_code VARCHAR(32) NOT NULL,
            course_unit INT NOT NULL,
            grade_letter VARCHAR(2) NOT NULL,
            semester VARCHAR(32) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS timetable_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            day_of_week TINYINT NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            location VARCHAR(150) DEFAULT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY user_id (user_id),
            KEY day_of_week (day_of_week)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    } catch (Throwable $e) {
        error_log('api/v1 study tools tables: ' . $e->getMessage());
        api_err('storage_unavailable', 'Study tools are not ready on the server yet. Please retry shortly.', 503);
    }
}

$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

// ─────────────────────────────────────────────────────────── the GPA side ─────
if ($action === 'cgpa' || $action === 'cgpa_add' || $action === 'cgpa_delete') {
    cgpa_tables($pdo);
    $scale = cgpa_scale();

    if ($action === 'cgpa_add') {
        $code = strtoupper(trim(api_field($in, 'course_code')));
        $unit = (int)api_field($in, 'course_unit');
        $grade = strtoupper(trim(api_field($in, 'grade')));
        $semester = mb_substr(trim(api_field($in, 'semester')), 0, 32);

        if ($code === '' || $unit < 1 || $unit > 24 || !isset($scale[$grade])) {
            api_err('invalid_entry', 'Enter the course code, a real unit number and a grade from A to F.', 422);
        }
        $stmt = $pdo->prepare('INSERT INTO cgpa_entries (user_id, course_code, course_unit, grade_letter, semester)
                               VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, mb_substr($code, 0, 32), $unit, $grade, $semester !== '' ? $semester : null]);
        $action = 'cgpa';
    }

    if ($action === 'cgpa_delete') {
        $id = (int)api_field($in, 'id');
        $stmt = $pdo->prepare('DELETE FROM cgpa_entries WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        if ($stmt->rowCount() === 0) api_err('not_found', 'That course is not in your list any more.', 404);
        $action = 'cgpa';
    }

    $rows = $pdo->prepare('SELECT id, course_code, course_unit, grade_letter, semester, created_at
                             FROM cgpa_entries WHERE user_id = ? ORDER BY id DESC');
    $rows->execute([$userId]);
    $entries = [];
    $totalUnits = 0;
    $totalPoints = 0.0;
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $points = round((isset($scale[$r['grade_letter']]) ? $scale[$r['grade_letter']] : 0.0) * (int)$r['course_unit'], 2);
        $totalUnits += (int)$r['course_unit'];
        $totalPoints += $points;
        $entries[] = [
            'id' => (int)$r['id'],
            'course_code' => (string)$r['course_code'],
            'course_unit' => (int)$r['course_unit'],
            'grade_letter' => (string)$r['grade_letter'],
            'semester' => (string)($r['semester'] ?? ''),
            'points' => $points,
            'created_at' => (string)$r['created_at'],
        ];
    }

    api_out([
        'ok' => true,
        'entries' => $entries,
        'total_units' => $totalUnits,
        'total_points' => round($totalPoints, 2),
        'gpa' => $totalUnits > 0 ? round($totalPoints / $totalUnits, 2) : 0.0,
        'scale' => $scale,
    ]);
}

// ───────────────────────────────────────────────────────── the timetable side ─
if ($action === 'timetable' || $action === 'tt_add' || $action === 'tt_delete') {
    cgpa_tables($pdo);

    if ($action === 'tt_add') {
        $title = mb_substr(trim(api_field($in, 'title')), 0, 150);
        $day = (int)api_field($in, 'day');
        $start = trim(api_field($in, 'start_time'));
        $end = trim(api_field($in, 'end_time'));
        $location = mb_substr(trim(api_field($in, 'location')), 0, 150);
        $notes = mb_substr(trim(api_field($in, 'notes')), 0, 1000);

        if ($title === '' || !isset($days[$day]) || !preg_match('/^\d{1,2}:\d{2}$/', $start) || !preg_match('/^\d{1,2}:\d{2}$/', $end)) {
            api_err('invalid_entry', 'Add a title, the day and both times.', 422);
        }
        $stmt = $pdo->prepare('INSERT INTO timetable_entries (user_id, title, day_of_week, start_time, end_time, location, notes)
                               VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $day, $start . ':00', $end . ':00', $location !== '' ? $location : null, $notes !== '' ? $notes : null]);
        $action = 'timetable';
    }

    if ($action === 'tt_delete') {
        $id = (int)api_field($in, 'id');
        $stmt = $pdo->prepare('DELETE FROM timetable_entries WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        if ($stmt->rowCount() === 0) api_err('not_found', 'That class is not in your timetable any more.', 404);
        $action = 'timetable';
    }

    $rows = $pdo->prepare('SELECT id, title, day_of_week, start_time, end_time, location, notes
                             FROM timetable_entries WHERE user_id = ? ORDER BY day_of_week ASC, start_time ASC');
    $rows->execute([$userId]);
    $entries = [];
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $entries[] = [
            'id' => (int)$r['id'],
            'title' => (string)$r['title'],
            'day' => (int)$r['day_of_week'],
            'day_name' => $days[(int)$r['day_of_week']] ?? '',
            'start' => substr((string)$r['start_time'], 0, 5),
            'end' => substr((string)$r['end_time'], 0, 5),
            'location' => (string)($r['location'] ?? ''),
            'notes' => (string)($r['notes'] ?? ''),
        ];
    }

    api_out(['ok' => true, 'entries' => $entries, 'days' => $days]);
}

api_err('bad_request', 'Ask for your courses or your timetable.', 400);
