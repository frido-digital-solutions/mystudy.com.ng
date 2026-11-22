<?php
/**
 * The signed-in student's own settings - the same door as profile.php.
 *
 * GET hands the app everything the website form shows: the account line, the
 * school fields (only editable for university students), the eight privacy
 * switches with their stored values, and the lists the pickers need.
 *
 * POST applies the website's contract unchanged: name is required, email must
 * be valid and nobody else's, the password block is only checked when the
 * person actually typed in it, the avatar is JPG/PNG/GIF under 2MB and the old
 * file is deleted, and the switches are stored exactly where the website
 * stores them (user_meta, except Show Level, which is a column on users).
 *
 * A switch that is not sent at all keeps its value rather than being read as
 * "off", so a partially-filled phone form cannot quietly hide someone's
 * profile.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/User.php';

$user = api_auth();
$userId = (int)$user['id'];
$model = new User($pdo);
$model->getById($userId);

$isUniversity = ($user['user_type'] ?? '') === 'university';

$levels = ['', '100 Level', '200 Level', '300 Level', '400 Level', '500 Level', '600 Level', 'Postgraduate'];
$universityNames = [];
try {
    $universityNames = $pdo->query('SELECT name FROM universities ORDER BY name ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    // Same as the website: the table is created by the admin seed, so a fresh
    // install simply shows the "Other" choice.
    error_log('api profile universities: ' . $e->getMessage());
}
$universityNames = array_values(array_map('strval', $universityNames));

$privacyMap = [
    'show_school' => 'privacy_show_school',
    'show_stats' => 'privacy_show_stats',
    'show_discussions' => 'privacy_show_discussions',
    'show_badges' => 'privacy_show_badges',
    'show_battles' => 'privacy_show_battles',
    'show_studies' => 'privacy_show_studies',
    'show_quizzes' => 'privacy_show_quizzes',
    'allow_messages' => 'privacy_allow_messages',
    'share_status' => 'privacy_share_status',
    'show_level' => 'show_level_on_users_page',
];

$privacy = [];
foreach ($privacyMap as $out => $prop) {
    $privacy[$out] = (int)$model->$prop === 1;
}

$payload = [
    'ok' => true,
    'account' => [
        'id' => $userId,
        'name' => (string)$model->name,
        'email' => (string)$model->email,
        'user_type' => (string)$model->user_type,
        'picture' => api_url($model->profile_picture),
        'points' => (int)$model->points,
        'email_verified' => (int)($model->email_verified ?? 0) === 1,
        'status' => (string)$model->status,
        'created_at' => $model->created_at,
        'is_super_admin' => (int)($user['is_super_admin'] ?? 0) === 1,
    ],
    'school' => [
        'editable' => $isUniversity,
        'university' => (string)($model->university ?? ''),
        'department' => (string)($model->department ?? ''),
        'level' => (string)($model->level ?? ''),
    ],
    'privacy' => $privacy,
    'universities' => array_values(array_unique(array_merge($universityNames, ['Other']))),
    'levels' => array_values(array_diff($levels, [''])),
    'avatar' => ['max_bytes' => 2 * 1024 * 1024, 'extensions' => ['jpg', 'jpeg', 'png', 'gif']],
    'support_email' => site_setting('support_email', ADMIN_EMAIL),
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    api_out($payload);
}

api_require_post();

$in = api_input();
$errors = [];

// A switch arrives as "1"/"0" from a phone form and as a real boolean from a
// JSON body; both mean the same thing here, and nothing else turns a profile
// section on.
$truthy = static function ($v): bool {
    if (is_bool($v)) return $v;
    if (is_int($v)) return $v === 1;
    return in_array(strtolower(trim((string)$v)), ['1', 'true', 'on', 'yes'], true);
};

$name = array_key_exists('name', $in) ? sanitize(api_field($in, 'name')) : (string)$model->name;
$email = array_key_exists('email', $in) ? sanitize(api_field($in, 'email')) : (string)$model->email;

if ($name === '') {
    $errors['name'] = 'Name is required.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
} elseif (strcasecmp($email, (string)$model->email) !== 0 && $model->emailExists($email)) {
    $errors['email'] = 'That email is already taken.';
}

// The password block is only a request when someone actually typed in it -
// exactly like the website, which skips it entirely for a picture-only save.
$current = api_field($in, 'current_password');
$new = array_key_exists('new_password', $in) ? (string)$in['new_password'] : '';
$confirm = array_key_exists('confirm_password', $in) ? (string)$in['confirm_password'] : '';
$wantsPassword = $current !== '' || $new !== '' || $confirm !== '';

if ($wantsPassword) {
    if ($current === '') {
        $errors['current_password'] = 'Please type your current password so we know it is really you.';
    } elseif (!password_verify($current, (string)$user['password_hash'])) {
        $errors['current_password'] = 'The current password you entered is incorrect. Please type it again.';
    }
    if ($new === '') {
        $errors['new_password'] = 'Please type the new password you want to use.';
    } elseif (strlen($new) < 8) {
        $errors['new_password'] = 'Your new password must be at least 8 characters long.';
    } elseif ($current !== '' && $new === $current) {
        $errors['new_password'] = 'You cannot keep using your current password as the new one. Choose a different password.';
    } elseif ($confirm === '') {
        $errors['confirm_password'] = 'Please type your new password once more in the Confirm box.';
    } elseif ($new !== $confirm) {
        $errors['confirm_password'] = 'The new password and the confirmation do not match. Please type the same password in both boxes.';
    }
}

$university = $model->university;
$department = $model->department;
$level = $model->level;
if ($isUniversity) {
    if (array_key_exists('university', $in)) {
        $wanted = sanitize(api_field($in, 'university'));
        if ($wanted !== '' && !in_array($wanted, $payload['universities'], true)) {
            $errors['university'] = 'That university is not on the list.';
        }
        $university = $wanted;
    }
    if (array_key_exists('department', $in)) {
        $department = mb_substr(sanitize(api_field($in, 'department')), 0, 100);
    }
    if (array_key_exists('level', $in)) {
        $wanted = api_field($in, 'level');
        if (!in_array($wanted, $levels, true)) {
            $errors['level'] = 'Please choose a level from the list.';
        }
        $level = $wanted;
    }
}

foreach ($privacyMap as $out => $prop) {
    if (array_key_exists($prop, $in)) {
        $model->$prop = $truthy($in[$prop]) ? 1 : 0;
    } elseif (array_key_exists($out, $in)) {
        $model->$prop = $truthy($in[$out]) ? 1 : 0;
    }
}

// The avatar arrives as a multipart file, the same 2MB image rule the website
// enforces before it writes anything.
$avatarField = isset($_FILES['picture']) ? 'picture' : (isset($_FILES['profile_picture']) ? 'profile_picture' : null);
$avatarExt = null;
if ($avatarField !== null) {
    $file = $_FILES[$avatarField];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        // A form that names the field but sends nothing is a no-op, not an error.
        $avatarField = null;
    } elseif (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors['picture'] = 'The photo did not reach the server. Please try again.';
    } elseif ((int)($file['size'] ?? 0) <= 0) {
        $errors['picture'] = 'The photo arrived empty. Please choose it once more.';
    } else {
        // What the bytes actually are decides everything, including the stored
        // name. A phone picker often sends a file with no usable name, and
        // trusting that name used to reject a perfectly good photo.
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $mime = (string)finfo_file($fi, (string)$file['tmp_name']);
                finfo_close($fi);
            }
        }
        if ($mime === '') {
            $mime = (string)(mime_content_type((string)$file['tmp_name']) ?: '');
        }
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
        if (!isset($allowedTypes[$mime])) {
            $errors['picture'] = $mime === ''
                ? 'The photo could not be read. Please choose a JPG, PNG or GIF photo and try again.'
                : 'That file is a ' . $mime . ' file. Please choose a JPG, PNG or GIF photo instead.';
        } elseif ((int)($file['size'] ?? 0) > $payload['avatar']['max_bytes']) {
            $errors['picture'] = 'The photo is ' . round(((int)$file['size'] / 1048576), 1) . 'MB. It must be less than 2MB.';
        } else {
            $avatarExt = $allowedTypes[$mime];
        }
    }
}

if ($errors) {
    // The phone shows this string on a banner, so it has to be the actual
    // reason, not a shrug. The per-field map still rides along for the inline
    // text under each box.
    $first = trim((string)reset($errors));
    api_err('validation_failed', $first !== '' ? $first : 'Some details need your attention.', 422, ['fields' => $errors]);
}

$picture = $model->profile_picture;
$storedAvatar = null;
if ($avatarField !== null) {
    $dir = ROOT_PATH . '/assets/uploads/profile_pictures/';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $ext = $avatarExt;
    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        // Same tidy-up the website does: the replaced photo goes, the placeholder stays.
        $old = (string)($model->profile_picture ?? '');
        if ($old !== '' && strpos($old, 'default-avatar') === false && is_file(ROOT_PATH . '/' . $old)) {
            @unlink(ROOT_PATH . '/' . $old);
        }
        $picture = 'assets/uploads/profile_pictures/' . $filename;
        $storedAvatar = $picture;
    } else {
        error_log('api profile avatar write failed for user ' . $userId . ' into ' . $dir);
        api_err('save_failed', 'The photo could not be stored on the server, so nothing was changed. Please try again.', 500);
    }
}

$model->name = $name;
$model->email = $email;
$model->profile_picture = $picture;
// update() re-writes the status column, so it has to carry what is already there.
$model->status = (string)$user['status'];

if (!$model->update()) {
    error_log('api profile update failed for user ' . $userId);
    // The new file would otherwise be orphaned by the failed row write.
    if ($storedAvatar !== null && is_file(ROOT_PATH . '/' . $storedAvatar)) {
        @unlink(ROOT_PATH . '/' . $storedAvatar);
    }
    api_err('save_failed', 'Your settings could not be saved. Please try again.', 500);
}

if ($isUniversity) {
    $model->saveSchoolDetails($university, $department, $level);
}

if ($wantsPassword && !$model->updatePassword($new)) {
    error_log('api profile password update failed for user ' . $userId);
    api_err('password_failed', 'Your other details were saved, but the password could not be changed. Please try again.', 500);
}

$_SESSION['user_name'] = $model->name;
$_SESSION['user_email'] = $model->email;
$_SESSION['user_picture'] = $model->profile_picture;

$model->getById($userId);
$payload['account']['name'] = (string)$model->name;
$payload['account']['email'] = (string)$model->email;
$payload['account']['picture'] = api_url($model->profile_picture);
$payload['school']['university'] = (string)($model->university ?? '');
$payload['school']['department'] = (string)($model->department ?? '');
$payload['school']['level'] = (string)($model->level ?? '');
foreach ($privacyMap as $out => $prop) {
    $privacy[$out] = (int)$model->$prop === 1;
}
$payload['privacy'] = $privacy;
$payload['saved'] = true;
$payload['message'] = 'Your profile has been updated.';

api_out($payload);
