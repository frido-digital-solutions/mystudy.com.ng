<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('users')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}

require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/User.php';

$page_title = 'Admin Broadcasts';
$errors = [];
$success = null;

$notifModel = new Notification($pdo);
$userModel = new User($pdo);

// Very simple target parsing: single user_id or "all"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        set_flash_message('error', 'Invalid form token.');
        redirect(SITE_URL . '/admin/admin_notifications.php');
    }

    $scope = $_POST['scope'] ?? 'broadcast';
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $singleUserId = (int)($_POST['user_id'] ?? 0);
    $roleFilter = trim($_POST['role_filter'] ?? '');
    $levelFilter = trim($_POST['level_filter'] ?? '');

    if ($title === '') { $errors[] = 'Title is required.'; }
    if ($message === '') { $errors[] = 'Message is required.'; }

    if ($scope === 'unicast' && $singleUserId <= 0) {
        $errors[] = 'Please select a valid user for Unicast.';
    }

    if (empty($errors)) {
        try {
            if ($scope === 'broadcast') {
                $stmt = $pdo->query("SELECT id FROM users WHERE status = 'active'");
                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($ids as $uid) {
                    $notifModel->create((int)$uid, 'admin_broadcast', $title, $message, null);
                }
            } elseif ($scope === 'multicast') {
                $where = ["status = 'active'"];
                $params = [];
                if ($roleFilter !== '') {
                    $where[] = 'user_type = :role';
                    $params[':role'] = $roleFilter;
                }
                if ($levelFilter !== '') {
                    $where[] = "id IN (SELECT user_id FROM user_meta WHERE meta_key = 'level' AND meta_value = :lvl)";
                    $params[':lvl'] = $levelFilter;
                }
                $sql = 'SELECT id FROM users';
                if (!empty($where)) {
                    $sql .= ' WHERE ' . implode(' AND ', $where);
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($ids as $uid) {
                    $notifModel->create((int)$uid, 'admin_multicast', $title, $message, null);
                }
            } elseif ($scope === 'unicast') {
                $notifModel->create($singleUserId, 'admin_unicast', $title, $message, null);
            }
            $success = 'Notification sent successfully.';
        } catch (Throwable $e) {
            log_error('Admin broadcast failed', ['error' => $e->getMessage()]);
            $errors[] = 'Could not send notification. Please try again.';
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
    <h1 class="h4 mb-0">Admin Broadcasts</h1>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars(implode(' ', $errors)); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <div class="card shadow-sm border-0 mb-4" style="border-radius: 0.75rem;">
    <div class="card-header bg-white">
      <strong>Send Notification</strong>
    </div>
    <div class="card-body">
      <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

        <div class="mb-3">
          <label class="form-label">Delivery Scope</label>
          <div class="d-flex flex-wrap gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="scope" id="scopeBroadcast" value="broadcast" checked>
              <label class="form-check-label" for="scopeBroadcast">
                Broadcast <span class="text-muted small">(All active users)</span>
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="scope" id="scopeMulticast" value="multicast">
              <label class="form-check-label" for="scopeMulticast">
                Multicast <span class="text-muted small">(Filter by role/level)</span>
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="scope" id="scopeUnicast" value="unicast">
              <label class="form-check-label" for="scopeUnicast">
                Unicast <span class="text-muted small">(Single user)</span>
              </label>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-6" id="multicastFiltersWrap" style="display:none;">
            <label class="form-label">Multicast Filters</label>
            <div class="mb-2">
              <label class="form-label small mb-1">User Role</label>
              <select class="form-select form-select-sm" name="role_filter">
                <option value="">Any</option>
                <option value="student">Students</option>
                <option value="university">University Students</option>
                <option value="admin">Admins</option>
              </select>
            </div>
            <div>
              <label class="form-label small mb-1">Level (for students)</label>
              <select class="form-select form-select-sm" name="level_filter">
                <option value="">Any Level</option>
                <option value="100">Level 100</option>
                <option value="200">Level 200</option>
                <option value="300">Level 300</option>
                <option value="400">Level 400</option>
              </select>
            </div>
            <small class="text-muted d-block mt-1">Multicast sends to users matching these filters only.</small>
          </div>

          <div class="col-md-6" id="unicastUserWrap" style="display:none;">
            <label class="form-label">Target User</label>
            <div class="input-group input-group-sm mb-1">
              <input type="text" class="form-control" id="userSearchInput" placeholder="Search by name or email">
              <button class="btn btn-outline-secondary" type="button" id="userSearchBtn">Search</button>
            </div>
            <select class="form-select form-select-sm" name="user_id" id="userSelect">
              <option value="">Select user from results</option>
            </select>
            <small class="text-muted d-block mt-1">Start typing and click search to find a specific user.</small>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Title</label>
          <input type="text" class="form-control" name="title" maxlength="255" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Message</label>
          <textarea class="form-control" name="message" rows="4" maxlength="2000" required></textarea>
          <small class="text-muted">Plain text only. This will appear inside the admin notice modal for users.</small>
        </div>

        <div class="text-end">
          <button type="submit" class="btn btn-primary">Send Notification</button>
        </div>
      </form>
    </div>
  </div>
    </main>
  </div>
</div>

<script>
(function(){
  var scopeBroadcast = document.getElementById('scopeBroadcast');
  var scopeMulticast = document.getElementById('scopeMulticast');
  var scopeUnicast = document.getElementById('scopeUnicast');
  var unicastWrap = document.getElementById('unicastUserWrap');
  var multicastWrap = document.getElementById('multicastFiltersWrap');
  var userSearchBtn = document.getElementById('userSearchBtn');
  var userSearchInput = document.getElementById('userSearchInput');
  var userSelect = document.getElementById('userSelect');

  function updateScope(){
    if (scopeMulticast && scopeMulticast.checked) {
      multicastWrap.style.display = '';
      unicastWrap.style.display = 'none';
    } else if (scopeUnicast && scopeUnicast.checked) {
      multicastWrap.style.display = 'none';
      unicastWrap.style.display = '';
    } else {
      multicastWrap.style.display = 'none';
      unicastWrap.style.display = 'none';
    }
  }

  scopeBroadcast && scopeBroadcast.addEventListener('change', updateScope);
  scopeMulticast && scopeMulticast.addEventListener('change', updateScope);
  scopeUnicast && scopeUnicast.addEventListener('change', updateScope);
  updateScope();

  if (userSearchBtn && userSearchInput && userSelect) {
    userSearchBtn.addEventListener('click', function(){
      var q = userSearchInput.value.trim();
      if (!q) {
        alert('Enter a name or email to search.');
        return;
      }
      fetch('<?php echo SITE_URL; ?>/ajax/admin_user_search.php?q=' + encodeURIComponent(q))
        .then(function(res){ return res.json(); })
        .then(function(json){
          while (userSelect.firstChild) userSelect.removeChild(userSelect.firstChild);
          var optDefault = document.createElement('option');
          optDefault.value = '';
          optDefault.textContent = 'Select user from results';
          userSelect.appendChild(optDefault);
          if (json && json.success && Array.isArray(json.results)) {
            json.results.forEach(function(u){
              var opt = document.createElement('option');
              opt.value = u.id;
              opt.textContent = u.name + ' (' + u.email + ')';
              userSelect.appendChild(opt);
            });
          } else {
            alert(json.error || 'No users found.');
          }
        })
        .catch(function(){
          alert('Search failed. Please try again.');
        });
    });
  }
})();
</script>

