<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/schema-ensure.php';

// The admin tables are created here rather than assumed: a database set up by
// hand on shared hosting does not have them, and a missing table is a page
// that throws instead of a page that works.
msb_ensure_admin_tables($pdo);

// Only super admins can access the activity log
require_super_admin();

$page_title = 'Activity Log';

// Check if clearing log
if (isset($_POST['action']) && $_POST['action'] === 'clear_log') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid form token. Please try again.');
        redirect(SITE_URL . '/admin/activity-log.php');
    }

    try {
        $pdo->exec("TRUNCATE TABLE admin_activity_log");
        set_flash_message('success', 'Activity log cleared successfully.');
    } catch (Exception $e) {
        set_flash_message('error', 'Error clearing log: ' . $e->getMessage());
    }
    redirect(SITE_URL . '/admin/activity-log.php');
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Specific filtering
$filter_admin = $_GET['admin_id'] ?? '';
$where_clause = "1=1";
$params = [];

if ($filter_admin !== '') {
    $where_clause .= " AND l.admin_user_id = ?";
    $params[] = $filter_admin;
}

// Get total for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_activity_log l WHERE $where_clause");
$count_stmt->execute($params);
$total_logs = $count_stmt->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

// Get activity logs
$query = "SELECT l.*, u.name, u.email 
          FROM admin_activity_log l 
          LEFT JOIN users u ON l.admin_user_id = u.id 
          WHERE $where_clause 
          ORDER BY l.created_at DESC 
          LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($query);
// Bind parameters carefully because of LIMIT/OFFSET
foreach ($params as $key => $val) {
    if (is_int($val)) {
        $stmt->bindValue($key + 1, $val, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key + 1, $val);
    }
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all admins for the filter dropdown
$admins = $pdo->query("SELECT id, name, email FROM users WHERE user_type = 'admin' OR is_super_admin = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      
      <!-- Page Header -->
      <div class="page-header d-flex justify-content-between align-items-center mb-4 pt-4 border-bottom pb-3">
        <div>
          <h1 class="h3"><i class="fas fa-history me-2 text-primary"></i> Admin Activity Log</h1>
          <p class="text-muted mb-0">Track all actions performed by administrators across the platform.</p>
        </div>
        <form method="post" onsubmit="return confirm('Are you sure you want to permanently clear the entire activity log? This cannot be undone.');">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="action" value="clear_log">
            <button class="btn btn-outline-danger" type="submit">
                <i class="fas fa-trash-alt me-1"></i> Clear Log
            </button>
        </form>
      </div>

      <?php echo display_flash_message(); ?>

      <!-- Filters -->
      <div class="card shadow-sm border-0 mb-4 rounded-4">
          <div class="card-body bg-light rounded-4">
              <form method="get" class="row gx-3 gy-2 align-items-center">
                  <div class="col-auto">
                      <label class="visually-hidden" for="adminFilter">Admin User</label>
                      <select class="form-select" id="adminFilter" name="admin_id">
                          <option value="">All Administrators</option>
                          <?php foreach ($admins as $admin): ?>
                              <option value="<?php echo $admin['id']; ?>" <?php echo $filter_admin == $admin['id'] ? 'selected' : ''; ?>>
                                  <?php echo htmlspecialchars($admin['name'] . ' (' . $admin['email'] . ')'); ?>
                              </option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="col-auto">
                      <button type="submit" class="btn btn-primary">Filter</button>
                      <?php if (!empty($filter_admin)): ?>
                          <a href="activity-log.php" class="btn btn-outline-secondary ms-2">Clear</a>
                      <?php endif; ?>
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
                  <th style="width: 25%;">Admin User</th>
                  <th style="width: 20%;">Action</th>
                  <th style="width: 35%;">Details</th>
                  <th style="width: 20%;">Date / IP</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            <i class="fas fa-clipboard-list mb-3" style="font-size: 2rem;"></i>
                            <p class="mb-0">No activity logs found.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                      <tr>
                        <td>
                          <div class="fw-bold"><?php echo htmlspecialchars($log['name'] ?? 'Unknown User'); ?></div>
                          <div class="text-muted small"><?php echo htmlspecialchars($log['email'] ?? 'Unknown Email'); ?></div>
                        </td>
                        <td>
                          <span class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($log['action']); ?></span>
                        </td>
                        <td>
                            <div class="small text-wrap" style="max-width: 400px;"><?php echo htmlspecialchars($log['details'] ?? ''); ?></div>
                        </td>
                        <td>
                            <div class="text-nowrap text-muted fw-bold" style="font-size: 0.9rem;">
                                <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                            </div>
                            <div class="small text-muted opacity-75 mt-1 font-monospace">
                                IP: <?php echo htmlspecialchars($log['ip_address'] ?? 'Unknown'); ?>
                            </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      
      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
      <nav aria-label="Page navigation" class="mb-5">
        <ul class="pagination justify-content-center">
            <?php
            // Preserve filter parameters in pagination links
            $query_params = $_GET;
            unset($query_params['page']);
            $base_url = '?' . http_build_query($query_params);
            $base_url .= empty($query_params) ? 'page=' : '&page=';
            ?>
            
          <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $base_url . ($page - 1); ?>">Previous</a>
          </li>
          
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
              <a class="page-link" href="<?php echo $base_url . $i; ?>"><?php echo $i; ?></a>
            </li>
          <?php endfor; ?>
          
          <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $base_url . ($page + 1); ?>">Next</a>
          </li>
        </ul>
      </nav>
      <?php endif; ?>
      
    </main>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
