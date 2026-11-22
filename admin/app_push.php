<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('users')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}

require_once __DIR__ . '/../includes/Broadcast.php';

$page_title = 'App Push Broadcast';
$errors = [];
$success = null;
$result = null;

$deviceCount = 0;
$userCount = 0;
try {
    $row = $pdo->query('SELECT COUNT(*) AS devices, COUNT(DISTINCT user_id) AS users FROM device_push_tokens')->fetch(PDO::FETCH_ASSOC);
    $deviceCount = (int)($row['devices'] ?? 0);
    $userCount = (int)($row['users'] ?? 0);
} catch (Throwable $e) {
    log_error('Could not count push devices', ['error' => $e->getMessage()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        set_flash_message('error', 'Invalid form token.');
        redirect(SITE_URL . '/admin/app_push.php');
    }

    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $screen = trim($_POST['screen'] ?? 'Home');
    $alsoInApp = !empty($_POST['inapp']);

    if ($title === '') $errors[] = 'Title is required.';
    if ($message === '') $errors[] = 'Message is required.';
    if (mb_strlen($title) > 120) $errors[] = 'The title is longer than 120 characters.';
    if (mb_strlen($message) > 400) $errors[] = 'The message is longer than 400 characters.';

    // Only a screen the app actually knows how to open is accepted, because a
    // notice that opens nothing is worse than no notice.
    $allowed = ['Home', 'Quizzes', 'Chats', 'Community', 'Battles', 'Learn', 'Notifications'];
    if (!in_array($screen, $allowed, true)) $screen = 'Home';

    if (empty($errors)) {
        $result = msb_broadcast_all($pdo, $title, $message, [
            'screen' => $screen,
            'inapp' => $alsoInApp,
        ]);
        if (!empty($result['push_error'])) {
            $errors[] = $result['push_error'];
        } else {
            $success = 'Broadcast sent to ' . $result['devices'] . ' device(s) belonging to '
                . $result['users'] . ' account(s).'
                . ($result['inapp'] > 0 ? ' ' . $result['inapp'] . ' in-app notice(s) written.' : '')
                . ($result['failed'] > 0 ? ' ' . $result['failed'] . ' could not be delivered.' : '')
                . ($result['dead'] > 0 ? ' ' . $result['dead'] . ' stale device(s) removed.' : '');
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h4 mb-0">App Push Broadcast</h1>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars(implode(' ', $errors)); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <div class="card shadow-sm border-0 mb-4" style="border-radius: 0.75rem;">
        <div class="card-header bg-white">
          <strong>Send to every phone that has the app</strong>
        </div>
        <div class="card-body">
          <p class="text-muted small mb-3">
            <strong><?php echo (int)$deviceCount; ?></strong> registered device(s),
            belonging to <strong><?php echo (int)$userCount; ?></strong> account(s).
            Only people who installed the app on a phone can be reached this way.
          </p>

          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

            <div class="mb-3">
              <label class="form-label">Title</label>
              <input type="text" class="form-control" name="title" maxlength="120" required
                     placeholder="e.g. New quizzes are live">
              <small class="text-muted">Shown as the headline on the phone.</small>
            </div>

            <div class="mb-3">
              <label class="form-label">Message</label>
              <textarea class="form-control" name="message" rows="4" maxlength="400" required
                        placeholder="e.g. We just added 20 new WAEC and JAMB practice quizzes."></textarea>
              <small class="text-muted">Up to 400 characters. Longer text is trimmed on the phone.</small>
            </div>

            <div class="mb-3">
              <label class="form-label">Open this screen when tapped</label>
              <select class="form-select" name="screen">
                <option value="Home">Home</option>
                <option value="Quizzes">Quizzes</option>
                <option value="Chats">Chats</option>
                <option value="Community">Community</option>
                <option value="Battles">Battles</option>
                <option value="Learn">Learn</option>
                <option value="Notifications">Notifications</option>
              </select>
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="inapp" id="inappCheck" checked>
              <label class="form-check-label" for="inappCheck">
                Also show it in the app's Notifications list
              </label>
            </div>

            <div class="text-end">
              <button type="submit" class="btn btn-primary">Send Broadcast</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm border-0 mb-4" style="border-radius: 0.75rem;">
        <div class="card-header bg-white">
          <strong>The same thing from the terminal</strong>
        </div>
        <div class="card-body">
          <p class="text-muted small mb-2">If you would rather not use this page, the server can do it from a shell:</p>
          <pre class="bg-light p-3 rounded small mb-0"><code>php cli/broadcast_push.php --title="New quizzes are live" \
  --body="We just added 20 new WAEC and JAMB practice quizzes." \
  --screen=Quizzes</code></pre>
          <small class="text-muted d-block mt-2">Add <code>--dry-run</code> to see how many phones would be reached without sending anything.</small>
        </div>
      </div>
    </main>
  </div>
</div>
