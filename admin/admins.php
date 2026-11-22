<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/schema-ensure.php';

// The admin tables are created here rather than assumed: a database set up by
// hand on shared hosting does not have them, and a missing table is a page
// that throws instead of a page that works.
msb_ensure_admin_tables($pdo);

// Only super admins can access this page
require_super_admin();

$page_title = 'Manage Admins';

$available_pages = [
    'dashboard' => 'Dashboard',
    'posts' => 'Posts',
    'materials' => 'Study Materials',
    'tutorials' => 'Tutorials',
    'quizzes' => 'Quizzes',
    'discussions' => 'Forum Moderation',
    'contact_messages' => 'Contact Messages',
    'payments' => 'Payments',
    'rewards' => 'Rewards',
    'access_codes' => 'Access Codes',
    'users' => 'Users',
    'settings' => 'Settings',
    'maintenance' => 'Maintenance',
    'backup' => 'Backup'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid form token. Please try again.');
        redirect(SITE_URL . '/admin/admins.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_admin') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $permissions = $_POST['permissions'] ?? [];

        if (empty($name) || empty($email) || empty($password)) {
            set_flash_message('error', 'Please fill in all required fields.');
        } else {
            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT id, user_type FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Update existing user to admin
                    $adminId = $user['id'];
                    $pdo->prepare("UPDATE users SET user_type = 'admin', is_super_admin = 0 WHERE id = ?")->execute([$adminId]);
                    set_flash_message('success', 'Existing user updated to admin role.');
                } else {
                    // Create new user
                    $userModel = new User($pdo);
                    $userModel->name = $name;
                    $userModel->email = $email;
                    $userModel->password_hash = $password; // Hashed inside create()
                    $userModel->user_type = 'admin';
                    $userModel->create();
                    $adminId = $userModel->id;
                    $pdo->prepare("UPDATE users SET is_super_admin = 0 WHERE id = ?")->execute([$adminId]);
                    set_flash_message('success', 'New admin created successfully.');
                }

                // Replace any previously granted pages: admin_permissions has no
                // unique key, so re-inserting alone would leave old grants active.
                if ($adminId) {
                    $pdo->prepare("DELETE FROM admin_permissions WHERE admin_user_id = ?")->execute([$adminId]);
                }

                // Add permissions
                if ($adminId && !empty($permissions)) {
                    $insertStmt = $pdo->prepare("INSERT INTO admin_permissions (admin_user_id, page_key) VALUES (?, ?)");
                    foreach ($permissions as $page_key) {
                        if (array_key_exists($page_key, $available_pages)) {
                            $insertStmt->execute([$adminId, $page_key]);
                        }
                    }
                }
                
                log_admin_activity($pdo, 'Created Admin', "Created/updated admin: $email with " . count($permissions) . " permissions");

            } catch (Exception $e) {
                set_flash_message('error', 'Error creating admin: ' . $e->getMessage());
            }
        }
        redirect(SITE_URL . '/admin/admins.php');
    }

    if ($action === 'set_super_admin') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        // The password is never stored in plain text, echoed back or logged.
        if ($name === '' || mb_strlen($name) < 2) {
            set_flash_message('error', 'Enter the admin name (at least 2 characters).');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash_message('error', 'Enter a valid email address — it is the login username for this account.');
        } elseif (strlen($password) < 10) {
            set_flash_message('error', 'Super Admin password must be at least 10 characters.');
        } else {
            try {
                $safeName = htmlspecialchars(strip_tags($name), ENT_QUOTES, 'UTF-8');
                $hash = hash_password($password);

                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $found = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($found) {
                    $superId = (int)$found['id'];
                    $pdo->prepare(
                        "UPDATE users
                            SET name = ?, password_hash = ?, user_type = 'admin', is_super_admin = 1,
                                status = 'active', is_frozen = 0, email_verified = 1
                          WHERE id = ?"
                    )->execute([$safeName, $hash, $superId]);
                    $verb = 'updated';
                } else {
                    $pdo->prepare(
                        "INSERT INTO users (name, email, password_hash, user_type, is_super_admin, status, email_verified)
                         VALUES (?, ?, ?, 'admin', 1, 'active', 1)"
                    )->execute([$safeName, $email, $hash]);
                    $superId = (int)$pdo->lastInsertId();
                    $verb = 'created';
                }

                // Grant every page. Super admins bypass per-page checks, but leaving
                // empty grants would show "No permissions" and break any code that
                // consults admin_permissions directly.
                $pdo->prepare('DELETE FROM admin_permissions WHERE admin_user_id = ?')->execute([$superId]);
                $ins = $pdo->prepare('INSERT INTO admin_permissions (admin_user_id, page_key) VALUES (?, ?)');
                foreach (array_keys($available_pages) as $page_key) {
                    $ins->execute([$superId, $page_key]);
                }

                log_admin_activity($pdo, 'Set Super Admin', "Super Admin login {$verb}: {$email}");
                set_flash_message('success', "Super Admin {$verb}: {$email}. Use that email and your new password to sign in.");
            } catch (Exception $e) {
                log_error('Set super admin failed', ['err' => $e->getMessage()]);
                set_flash_message('error', 'Could not set the Super Admin. Please try again.');
            }
        }
        redirect(SITE_URL . '/admin/admins.php');
    }

    if ($action === 'update_permissions') {
        $adminId = (int)$_POST['admin_id'];
        $permissions = $_POST['permissions'] ?? [];

        try {
            $curStmt = $pdo->prepare("SELECT is_super_admin FROM users WHERE id = ?");
            $curStmt->execute([$adminId]);
            $isSuperAdmin = (int)$curStmt->fetchColumn() === 1;

            // Check if upgrading to super admin
            if (isset($_POST['make_super_admin'])) {
                $pdo->prepare("UPDATE users SET is_super_admin = 1 WHERE id = ?")->execute([$adminId]);
                log_admin_activity($pdo, 'Upgraded Admin', "Upgraded admin ID $adminId to Super Admin");
                set_flash_message('success', 'Admin upgraded to Super Admin.');
            } elseif ($isSuperAdmin) {
                // Box unchecked for an existing super admin: demote
                if ($adminId === (int)$_SESSION['user_id']) {
                    set_flash_message('error', 'You cannot remove your own Super Admin privileges.');
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE users SET is_super_admin = 0 WHERE id = ?")->execute([$adminId]);
                    // A super admin has no page grants of their own, so the ticked
                    // pages must be written here or the demotion locks them out of
                    // every admin page.
                    $pdo->prepare("DELETE FROM admin_permissions WHERE admin_user_id = ?")->execute([$adminId]);
                    if (!empty($permissions)) {
                        $insertStmt = $pdo->prepare("INSERT INTO admin_permissions (admin_user_id, page_key) VALUES (?, ?)");
                        foreach ($permissions as $page_key) {
                            if (array_key_exists($page_key, $available_pages)) {
                                $insertStmt->execute([$adminId, $page_key]);
                            }
                        }
                    }
                    $pdo->commit();
                    log_admin_activity($pdo, 'Demoted Admin', "Removed Super Admin privileges from admin ID $adminId");
                    set_flash_message('success', 'Super Admin privileges removed.');
                }
            } else {
                // Regular permission update
                $pdo->beginTransaction();
                
                // Remove old permissions
                $pdo->prepare("DELETE FROM admin_permissions WHERE admin_user_id = ?")->execute([$adminId]);
                
                // Add new permissions
                if (!empty($permissions)) {
                    $insertStmt = $pdo->prepare("INSERT INTO admin_permissions (admin_user_id, page_key) VALUES (?, ?)");
                    foreach ($permissions as $page_key) {
                        if (array_key_exists($page_key, $available_pages)) {
                            $insertStmt->execute([$adminId, $page_key]);
                        }
                    }
                }
                
                $pdo->commit();
                log_admin_activity($pdo, 'Updated Permissions', "Updated permissions for admin ID $adminId");
                set_flash_message('success', 'Permissions updated successfully.');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            set_flash_message('error', 'Error updating permissions: ' . $e->getMessage());
        }
        redirect(SITE_URL . '/admin/admins.php');
    }

    if ($action === 'revoke_admin') {
        $adminId = (int)$_POST['admin_id'];

        // Never let the last super admin be demoted: that locks everyone out of
        // every super-admin-only page, including this one.
        $superStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'admin' AND is_super_admin = 1 AND id <> ?");
        $superStmt->execute([$adminId]);
        if ((int)$superStmt->fetchColumn() === 0) {
            set_flash_message('error', 'This is the only Super Admin left. Promote another admin first, then revoke.');
            redirect(SITE_URL . '/admin/admins.php');
        }

        // Prevent self-revocation
        if ($adminId === (int)$_SESSION['user_id']) {
            set_flash_message('error', 'You cannot revoke your own admin access.');
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE users SET user_type = 'university', is_super_admin = 0 WHERE id = ?")->execute([$adminId]);
                $pdo->prepare("DELETE FROM admin_permissions WHERE admin_user_id = ?")->execute([$adminId]);
                $pdo->commit();
                
                log_admin_activity($pdo, 'Revoked Admin', "Revoked admin access for ID $adminId");
                set_flash_message('success', 'Admin access revoked.');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                set_flash_message('error', 'Error revoking access: ' . $e->getMessage());
            }
        }
        redirect(SITE_URL . '/admin/admins.php');
    }
}

// Fetch all admins
$stmt = $pdo->query("SELECT id, name, email, is_super_admin, created_at, last_login FROM users WHERE user_type = 'admin' ORDER BY is_super_admin DESC, name ASC");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch permissions for each admin
$admin_perms = [];
$permStmt = $pdo->query("SELECT admin_user_id, page_key FROM admin_permissions");
while ($row = $permStmt->fetch(PDO::FETCH_ASSOC)) {
    $admin_perms[$row['admin_user_id']][] = $row['page_key'];
}

include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      
      <!-- Page Header -->
      <div class="page-header d-flex justify-content-between align-items-center mb-4 pt-4 border-bottom pb-3">
        <div>
          <h1 class="h3"><i class="fas fa-user-shield me-2 text-primary"></i> Manage Admins</h1>
          <p class="text-muted mb-0">Create new administrators and configure their permissions</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAdminModal">
            <i class="fas fa-plus me-1"></i> New Admin
        </button>
      </div>

      <?php echo display_flash_message(); ?>

      <?php
      $superCount = 0;
      foreach ($admins as $a) { if (!empty($a['is_super_admin'])) { $superCount++; } }
      ?>
      <!-- Primary Super Admin: the only way to grant (or move) the top-level role.
           "New Admin" below always creates a Standard Admin, never a super one. -->
      <div class="card shadow-sm border-0 mb-4 rounded-4" style="border-left: 4px solid #dc3545 !important;">
        <div class="card-body">
          <h2 class="h5 mb-1"><i class="fas fa-crown me-2 text-danger"></i> Primary Super Admin</h2>
          <p class="text-muted small mb-3">
            Sets the main account for this site: its login email, its password, and the Super Admin role
            with every permission. Enter an email that already exists to promote that account; enter a new
            one to create it. There <?php echo $superCount === 1 ? 'is currently <strong>1</strong> Super Admin' : 'are currently <strong>' . $superCount . '</strong> Super Admins'; ?>.
          </p>
          <form method="post" class="row g-2 align-items-end">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="action" value="set_super_admin">
            <div class="col-md-3">
              <label class="form-label text-muted small fw-bold text-uppercase" for="sa_name">Name</label>
              <input type="text" id="sa_name" name="name" class="form-control" required minlength="2"
                     placeholder="<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Site Owner'); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-bold text-uppercase" for="sa_email">Login Email</label>
              <input type="email" id="sa_email" name="email" class="form-control" required
                     placeholder="admin@yourdomain.ng" autocomplete="off">
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small fw-bold text-uppercase" for="sa_password">New Password</label>
              <input type="password" id="sa_password" name="password" class="form-control" required minlength="10"
                     autocomplete="new-password">
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-danger w-100"
                      onclick="return confirm('This changes the Super Admin login and password. Make sure you can receive email at that address before continuing.');">
                <i class="fas fa-key me-1"></i> Set
              </button>
            </div>
            <div class="col-12">
              <div class="form-text">
                <i class="fas fa-triangle-exclamation me-1 text-warning"></i>
                That address must be a mailbox that actually receives mail — password reset links are sent to it.
              </div>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm border-0 mb-4 rounded-4">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Admin User</th>
                  <th>Role / Permissions</th>
                  <th>Last Login</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($admins as $admin): ?>
                  <tr>
                    <td>
                      <div class="fw-bold"><?php echo htmlspecialchars($admin['name']); ?></div>
                      <div class="text-muted small"><?php echo htmlspecialchars($admin['email']); ?></div>
                    </td>
                    <td>
                      <?php if ($admin['is_super_admin']): ?>
                        <span class="badge bg-danger rounded-pill"><i class="fas fa-star me-1"></i> Super Admin</span>
                        <div class="text-muted small mt-1">Full platform access</div>
                      <?php else: ?>
                        <span class="badge bg-primary rounded-pill mb-1">Standard Admin</span>
                        <div class="d-flex flex-wrap gap-1 mt-1" style="max-width: 300px;">
                            <?php 
                            $perms = $admin_perms[$admin['id']] ?? [];
                            if (empty($perms)): ?>
                                <span class="badge bg-light text-muted border">No permissions</span>
                            <?php else: 
                                foreach ($perms as $p): ?>
                                    <span class="badge bg-light text-dark border"><?php echo $available_pages[$p] ?? $p; ?></span>
                                <?php endforeach; 
                            endif; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                        <span class="text-muted small">
                            <?php echo $admin['last_login'] ? date('M j, Y g:i A', strtotime($admin['last_login'])) : 'Never logged in'; ?>
                        </span>
                    </td>
                    <td class="text-end">
                      <?php if ($admin['id'] !== $_SESSION['user_id']): ?>
                          <button class="btn btn-sm btn-outline-primary" onclick='editPermissions(<?php echo json_encode($admin); ?>, <?php echo json_encode($admin_perms[$admin['id']] ?? []); ?>)'>
                            <i class="fas fa-edit me-1"></i> Edit
                          </button>
                          
                          <form method="post" class="d-inline" onsubmit="return confirm('Revoke admin access for this user? They will return to being a normal user.');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <input type="hidden" name="action" value="revoke_admin">
                            <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                              <i class="fas fa-user-times"></i>
                            </button>
                          </form>
                      <?php else: ?>
                          <span class="badge bg-success bg-opacity-10 text-success border border-success">Current User</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Create Admin Modal -->
<div class="modal fade" id="createAdminModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title"><i class="fas fa-user-plus me-2 text-primary"></i> Create New Admin</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="action" value="create_admin">
          
          <div class="alert alert-info py-2" style="font-size: 0.9rem;">
            <i class="fas fa-info-circle me-1"></i> If the email already belongs to a registered user, their account will be upgraded instead of creating a new one.
          </div>
          
          <div class="row mb-4">
            <div class="col-md-6 mb-3">
              <label class="form-label text-muted small fw-bold text-uppercase">Name</label>
              <input type="text" name="name" class="form-control" required placeholder="Jane Doe">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label text-muted small fw-bold text-uppercase">Email</label>
              <input type="email" name="email" class="form-control" required placeholder="admin@yourdomain.ng">
            </div>
            <div class="col-md-12">
              <label class="form-label text-muted small fw-bold text-uppercase">Password</label>
              <input type="password" name="password" class="form-control" required placeholder="Set temporary password">
              <div class="form-text">The admin should log in with this password initially.</div>
            </div>
          </div>
          
          <h6 class="fw-bold mb-3 border-bottom pb-2">Assign Permissions</h6>
          <div class="row g-3">
            <?php foreach ($available_pages as $key => $label): ?>
            <div class="col-md-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="permissions[]" value="<?php echo $key; ?>" id="perm_<?php echo $key; ?>">
                  <label class="form-check-label" for="perm_<?php echo $key; ?>">
                    <?php echo $label; ?>
                  </label>
                </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Admin</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Permissions Modal -->
<div class="modal fade" id="editPermsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title"><i class="fas fa-user-shield me-2 text-primary"></i> Edit Admin Permissions</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="action" value="update_permissions">
          <input type="hidden" name="admin_id" id="edit_admin_id">
          
          <div class="mb-4 d-flex align-items-center gap-3">
              <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width:50px; height:50px; font-size:1.5rem;">
                  <i class="fas fa-user"></i>
              </div>
              <div>
                  <h6 class="mb-0 fw-bold" id="edit_admin_name">Admin Name</h6>
                  <div class="text-muted small" id="edit_admin_email">admin@email.com</div>
              </div>
          </div>
          
          <div id="superAdminWarning" class="alert alert-warning d-none">
              <i class="fas fa-star me-1"></i> This user is a Super Admin and already has full access to all pages.
              <div class="small mt-1">To remove those privileges: untick "Upgrade to Super Admin", tick the pages they should keep, then save. Saving with no pages ticked leaves them with no admin access.</div>
          </div>
          
          <div id="permissionsEditor">
              <h6 class="fw-bold mb-3 border-bottom pb-2">Granted Access</h6>
              
              <div class="form-check mb-4 bg-danger bg-opacity-10 p-3 rounded border border-danger border-opacity-25 text-danger">
                  <input class="form-check-input ms-1" type="checkbox" name="make_super_admin" id="makeSuperAdmin" value="1">
                  <label class="form-check-label ms-2 fw-bold" for="makeSuperAdmin">
                    Upgrade to Super Admin
                    <div class="small fw-normal mt-1 opacity-75">Warning: Grants the ability to create admins, change site settings, and access the activity log. This cannot be undone easily.</div>
                  </label>
              </div>

              <div class="row g-3" id="permsList">
                <?php foreach ($available_pages as $key => $label): ?>
                <div class="col-md-4 perm-checkbox-wrapper">
                    <div class="form-check">
                      <input class="form-check-input permission-checkbox" type="checkbox" name="permissions[]" value="<?php echo $key; ?>" id="edit_perm_<?php echo $key; ?>">
                      <label class="form-check-label" for="edit_perm_<?php echo $key; ?>">
                        <?php echo $label; ?>
                      </label>
                    </div>
                </div>
                <?php endforeach; ?>
              </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" id="savePermsBtn">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editPermissions(admin, perms) {
    document.getElementById('edit_admin_id').value = admin.id;
    document.getElementById('edit_admin_name').innerText = admin.name;
    document.getElementById('edit_admin_email').innerText = admin.email;
    
    const isSuper = admin.is_super_admin == 1;
    
    document.getElementById('superAdminWarning').classList.toggle('d-none', !isSuper);

    // Uncheck all first; the editor stays visible for super admins so the
    // "Upgrade to Super Admin" box can be unticked to demote them.
    document.querySelectorAll('.permission-checkbox').forEach(cb => { cb.checked = false; cb.disabled = isSuper; });
    document.getElementById('makeSuperAdmin').checked = isSuper;
    
    // Check granted permissions
    if (!isSuper && perms && perms.length) {
        perms.forEach(p => {
            const cb = document.getElementById('edit_perm_' + p);
            if (cb) cb.checked = true;
        });
    }
    
    new bootstrap.Modal(document.getElementById('editPermsModal')).show();
}

// Toggle disable on individual perms when Super Admin is checked
document.getElementById('makeSuperAdmin').addEventListener('change', function() {
    const disabled = this.checked;
    document.querySelectorAll('.permission-checkbox').forEach(cb => {
        cb.disabled = disabled;
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
