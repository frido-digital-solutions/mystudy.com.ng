<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Notification.php';
require_admin();

if (!admin_can('users')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    redirect(SITE_URL . '/admin/dashboard.php');
}
$page_title = 'All Users';
require_once __DIR__ . '/../models/User.php';

// Handle Actions (Frozen, Strikes, Deactivate, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid form token. Please try again.');
        redirect('users.php');
    }

    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($userId > 0) {
        if ($action === 'freeze') {
            $pdo->prepare("UPDATE users SET is_frozen = 1 WHERE id = ?")->execute([$userId]);
            set_flash_message('warning', 'User account frozen.');
        } elseif ($action === 'unfreeze') {
            $pdo->prepare("UPDATE users SET is_frozen = 0, report_strikes = 0 WHERE id = ?")->execute([$userId]);
            set_flash_message('success', 'User account unfrozen and strikes reset.');
        } elseif ($action === 'add_strike') {
            $pdo->prepare("UPDATE users SET report_strikes = report_strikes + 1 WHERE id = ?")->execute([$userId]);
            $stmt = $pdo->prepare("SELECT report_strikes FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $strikes = (int)$stmt->fetchColumn();
            if ($strikes >= 3) {
                $pdo->prepare("UPDATE users SET is_frozen = 1 WHERE id = ?")->execute([$userId]);
                set_flash_message('error', 'User reached 3 strikes and has been frozen automatically.');
            } else {
                set_flash_message('warning', '1 strike added to user.');
            }
        } elseif ($action === 'remove_strike') {
            $pdo->prepare("UPDATE users SET report_strikes = GREATEST(0, report_strikes - 1) WHERE id = ?")->execute([$userId]);
            set_flash_message('success', '1 strike removed from user.');
        } elseif ($action === 'delete_user') {
            // Hard delete user and cascade via foreign keys where defined
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
            set_flash_message('success', 'User deleted permanently.');
        }
    }
    redirect('users.php');
}

$userModel = new User($pdo);
// Fetch all with moderation columns included
$stmt = $pdo->query("SELECT id, name, email, user_type, created_at, report_strikes, is_frozen FROM users ORDER BY created_at DESC LIMIT 100");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h4 mb-0"><i class="fas fa-users me-2 text-primary"></i> User Management</h1>
      </div>
      
      <?php echo display_flash_message(); ?>

      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>User</th>
                  <th>Role</th>
                  <th>Joined</th>
                  <th>Moderation</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($users)): ?>
                  <tr><td colspan="5" class="text-center text-muted py-5">No users found.</td></tr>
                <?php else: foreach ($users as $u): 
                    $isFrozen = (int)$u['is_frozen'] === 1;
                    $strikes = (int)$u['report_strikes'];
                ?>
                  <tr class="<?= $isFrozen ? 'table-danger' : '' ?>">
                    <td>
                        <div class="fw-bold fs-6"><?php echo htmlspecialchars($u['name']); ?></div>
                        <div class="text-muted small"><?php echo htmlspecialchars($u['email']); ?></div>
                    </td>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($u['user_type']); ?></span></td>
                    <td><span class="text-muted small"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></span></td>
                    
                    <!-- Moderation Status Column -->
                    <td>
                        <div class="d-flex flex-column gap-1">
                            <?php if ($isFrozen): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger w-fit"><i class="fas fa-snowflake me-1"></i> Frozen</span>
                            <?php else: ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success w-fit"><i class="fas fa-check-circle me-1"></i> Active</span>
                            <?php endif; ?>
                            
                            <div class="small fw-bold <?= $strikes > 0 ? 'text-danger' : 'text-muted' ?>">
                                <i class="fas fa-flag me-1"></i> <?= $strikes ?> / 3 Strikes
                            </div>
                        </div>
                    </td>

                    <!-- Admin Actions -->
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <?php if ($strikes > 0): ?>
                                    <button type="submit" name="action" value="remove_strike" class="btn btn-sm btn-outline-secondary" title="Remove Strike"><i class="fas fa-minus-circle"></i></button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="add_strike" class="btn btn-sm btn-outline-warning" title="Add Strike"><i class="fas fa-plus-circle"></i></button>
                            </form>
                            
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <?php if ($isFrozen): ?>
                                    <button type="submit" name="action" value="unfreeze" class="btn btn-sm btn-success fw-bold" onclick="return confirm('Unfreeze this user and reset their strikes?');"><i class="fas fa-unlock me-1"></i> Unfreeze</button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="freeze" class="btn btn-sm btn-danger fw-bold" onclick="return confirm('Freeze this user? They will be unable to access the platform.');"><i class="fas fa-snowflake me-1"></i> Freeze</button>
                                <?php endif; ?>
                            </form>
                            
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" name="action" value="delete_user" class="btn btn-sm btn-outline-danger fw-bold" onclick="return confirm('This will permanently delete this user and their data where supported. Are you absolutely sure?');">
                                    <i class="fas fa-user-times me-1"></i> Delete
                                </button>
                            </form>
                        </div>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

