<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if (!admin_can('dashboard')) {
    set_flash_message('error', 'You do not have permission to access this page.');
    // Not dashboard.php: that is the default bounce target and would loop.
    redirect(SITE_URL . '/');
}

$page_title = 'Dashboard';

// Fetch real stats with error handling
try {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {
    $totalUsers = 0;
}

try {
    $totalMaterials = $pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();
} catch (Exception $e) {
    $totalMaterials = 0;
}

try {
    $totalQuizzes = $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
} catch (Exception $e) {
    $totalQuizzes = 0;
}

try {
    $activeChallenges = $pdo->query("SELECT COUNT(*) FROM quizzes WHERE mode='challenge' AND event_end > NOW()")->fetchColumn();
} catch (Exception $e) {
    $activeChallenges = 0;
}

try {
    $unreadMessages = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
} catch (Exception $e) {
    $unreadMessages = 0;
}

// Get maintenance status
$maintenance_mode = 0;
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'maintenance_mode'");
    $stmt->execute();
    $maintenance_mode = $stmt->fetchColumn();
} catch (Exception $e) {
    $maintenance_mode = 0;
}

// Get monthly user growth for chart (last 7 months)
$monthlyUsers = [];
$monthLabels = [];
try {
    for ($i = 6; $i >= 0; $i--) {
        $monthStart = date('Y-m-01', strtotime("-$i months"));
        $monthEnd = date('Y-m-t', strtotime("-$i months"));
        $monthLabels[] = date('M', strtotime("-$i months"));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at <= ?");
        $stmt->execute([$monthStart, $monthEnd . ' 23:59:59']);
        $monthlyUsers[] = (int)$stmt->fetchColumn();
    }
} catch (Exception $e) {
    $monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul'];
    $monthlyUsers = [0,0,0,0,0,0,0];
}

// Get real content counts for doughnut chart
try {
    $totalPosts = (int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
} catch (Exception $e) {
    $totalPosts = 0;
}
try {
    $totalTutorials = (int)$pdo->query("SELECT COUNT(*) FROM tutorials")->fetchColumn();
} catch (Exception $e) {
    $totalTutorials = 0;
}

include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      
      <!-- Page Header -->
      <div class="page-header">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h1><i class="fas fa-th-large me-2" style="color: var(--admin-primary);"></i> Dashboard</h1>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">Welcome back! Here's an overview of your platform.</p>
          </div>
          <?php if ($maintenance_mode == '1'): ?>
            <div class="alert alert-warning alert-dismissible fade show mb-0 py-2 px-3" role="alert" style="font-size: 0.85rem;">
              <i class="fas fa-exclamation-triangle me-1"></i>
              <strong>Maintenance Mode ON</strong>
              <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Stat Cards -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card stat-card" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5);">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="stat-label">Total Users</div>
                <div class="stat-number"><?php echo number_format($totalUsers); ?></div>
              </div>
              <div class="stat-icon" style="background: rgba(16,185,129,0.15); color: #059669;">
                <i class="fas fa-users"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card stat-card" style="background: linear-gradient(135deg, #eff6ff, #dbeafe);">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="stat-label">Materials</div>
                <div class="stat-number"><?php echo number_format($totalMaterials); ?></div>
              </div>
              <div class="stat-icon" style="background: rgba(59,130,246,0.15); color: #2563eb;">
                <i class="fas fa-book-open"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card stat-card" style="background: linear-gradient(135deg, #fef3c7, #fde68a);">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="stat-label">Quizzes</div>
                <div class="stat-number"><?php echo number_format($totalQuizzes); ?></div>
              </div>
              <div class="stat-icon" style="background: rgba(245,158,11,0.15); color: #d97706;">
                <i class="fas fa-brain"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card stat-card" style="background: linear-gradient(135deg, #fce7f3, #fbcfe8);">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="stat-label">Active Challenges</div>
                <div class="stat-number"><?php echo number_format($activeChallenges); ?></div>
              </div>
              <div class="stat-icon" style="background: rgba(236,72,153,0.15); color: #db2777;">
                <i class="fas fa-trophy"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions & Messages -->
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="mb-0"><i class="fas fa-envelope me-2 text-primary"></i> Contact Messages</h5>
            </div>
            <div class="card-body">
              <?php if ($unreadMessages > 0): ?>
                <div class="alert alert-info mb-3">
                  <i class="fas fa-info-circle me-2"></i>
                  You have <strong><?php echo $unreadMessages; ?></strong> unread message<?php echo $unreadMessages > 1 ? 's' : ''; ?>
                </div>
              <?php endif; ?>
              <a href="<?php echo SITE_URL; ?>/admin/contact-messages.php" class="btn btn-primary w-100">
                <i class="fas fa-inbox me-2"></i> View All Messages
                <?php if ($unreadMessages > 0): ?>
                  <span class="badge bg-danger ms-2"><?php echo $unreadMessages; ?></span>
                <?php endif; ?>
              </a>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="mb-0"><i class="fas fa-cogs me-2 text-warning"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
              <div class="d-grid gap-2">
                <a href="<?php echo SITE_URL; ?>/admin/settings.php" class="btn btn-outline-primary">
                  <i class="fas fa-sliders-h me-2"></i> System Settings
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn btn-outline-secondary">
                  <i class="fas fa-users me-2"></i> Manage Users
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/backup.php" class="btn btn-outline-success">
                  <i class="fas fa-database me-2"></i> Backup Database
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pending Actions (Only shown if there are pending items) -->
      <?php if (($pendingCounts['rewards'] > 0) || ($pendingCounts['payments'] > 0) || ($pendingCounts['materials'] > 0)): ?>
      <div class="row g-3 mb-4">
        <?php if ($pendingCounts['rewards'] > 0): ?>
        <div class="col-md-4">
          <a href="<?php echo SITE_URL; ?>/admin/rewards.php?status=pending" class="text-decoration-none">
            <div class="p-3 shadow-sm border-0 mb-0" style="background: linear-gradient(135deg, #fef3c7, #fde68a); color: #b45309; border-radius: 12px; display: flex; align-items: center; gap: 1rem;">
              <div style="background: rgba(217,119,6,0.15); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fas fa-gift"></i>
              </div>
              <div class="flex-fill">
                <div class="fw-bold" style="font-size: 1.1rem;"><?php echo $pendingCounts['rewards']; ?> Pending</div>
                <div style="font-size: 0.85rem;">Reward Claims</div>
              </div>
              <i class="fas fa-chevron-right opacity-50"></i>
            </div>
          </a>
        </div>
        <?php endif; ?>

        <?php if ($pendingCounts['payments'] > 0): ?>
        <div class="col-md-4">
          <a href="<?php echo SITE_URL; ?>/admin/payments.php" class="text-decoration-none">
            <div class="p-3 shadow-sm border-0 mb-0" style="background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #1d4ed8; border-radius: 12px; display: flex; align-items: center; gap: 1rem;">
              <div style="background: rgba(37,99,235,0.15); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fas fa-credit-card"></i>
              </div>
              <div class="flex-fill">
                <div class="fw-bold" style="font-size: 1.1rem;"><?php echo $pendingCounts['payments']; ?> Pending</div>
                <div style="font-size: 0.85rem;">Payments to verify</div>
              </div>
              <i class="fas fa-chevron-right opacity-50"></i>
            </div>
          </a>
        </div>
        <?php endif; ?>

        <?php if ($pendingCounts['materials'] > 0): ?>
        <div class="col-md-4">
          <a href="<?php echo SITE_URL; ?>/admin/materials.php?status=pending" class="text-decoration-none">
            <div class="p-3 shadow-sm border-0 mb-0" style="background: linear-gradient(135deg, #fee2e2, #fecaca); color: #b91c1c; border-radius: 12px; display: flex; align-items: center; gap: 1rem;">
              <div style="background: rgba(220,38,38,0.15); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fas fa-book-open"></i>
              </div>
              <div class="flex-fill">
                <div class="fw-bold" style="font-size: 1.1rem;"><?php echo $pendingCounts['materials']; ?> Pending</div>
                <div style="font-size: 0.85rem;">Materials to review</div>
              </div>
              <i class="fas fa-chevron-right opacity-50"></i>
            </div>
          </a>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Quick Actions -->
      <div class="card mb-4">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="fas fa-bolt" style="color: var(--admin-primary);"></i>
          <strong>Quick Actions</strong>
        </div>
        <div class="card-body">
          <div class="row g-2">
            <div class="col-6 col-md-3">
              <a href="<?php echo SITE_URL; ?>/admin/quiz_edit.php" class="btn btn-outline-primary w-100 py-3 d-flex flex-column align-items-center gap-1">
                <i class="fas fa-plus-circle" style="font-size:1.2rem;"></i>
                <small class="fw-600">New Quiz</small>
              </a>
            </div>
            <div class="col-6 col-md-3">
              <a href="<?php echo SITE_URL; ?>/admin/post_edit.php" class="btn btn-outline-primary w-100 py-3 d-flex flex-column align-items-center gap-1">
                <i class="fas fa-pen-alt" style="font-size:1.2rem;"></i>
                <small class="fw-600">New Post</small>
              </a>
            </div>
            <div class="col-6 col-md-3">
              <a href="<?php echo SITE_URL; ?>/admin/payments.php" class="btn btn-outline-primary w-100 py-3 d-flex flex-column align-items-center gap-1">
                <i class="fas fa-credit-card" style="font-size:1.2rem;"></i>
                <small class="fw-600">Payments</small>
              </a>
            </div>
            <div class="col-6 col-md-3">
              <a href="<?php echo SITE_URL; ?>/admin/rewards.php" class="btn btn-outline-primary w-100 py-3 d-flex flex-column align-items-center gap-1">
                <i class="fas fa-gift" style="font-size:1.2rem;"></i>
                <small class="fw-600">Rewards</small>
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="row">
        <div class="col-md-7 mb-4">
          <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
              <i class="fas fa-chart-area" style="color: var(--admin-primary);"></i>
              <strong>User Growth</strong>
            </div>
            <div class="card-body">
              <canvas id="userChart" height="280"></canvas>
            </div>
          </div>
        </div>
        <div class="col-md-5 mb-4">
          <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
              <i class="fas fa-chart-pie" style="color: var(--admin-primary);"></i>
              <strong>Content Distribution</strong>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
              <canvas id="contentChart" height="280"></canvas>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const userCtx = document.getElementById('userChart').getContext('2d');
  new Chart(userCtx, {
    type: 'line',
    data: {
      labels: <?php echo json_encode($monthLabels); ?>,
      datasets: [{
        label: 'New Users',
        data: <?php echo json_encode($monthlyUsers); ?>,
        borderColor: '#10b981',
        backgroundColor: 'rgba(16,185,129,0.08)',
        tension: 0.4,
        fill: true,
        pointBackgroundColor: '#10b981',
        pointBorderWidth: 0,
        pointRadius: 4,
        pointHoverRadius: 6
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } },
        x: { grid: { display: false }, ticks: { font: { size: 11 } } }
      }
    }
  });

  const contentCtx = document.getElementById('contentChart').getContext('2d');
  new Chart(contentCtx, {
    type: 'doughnut',
    data: {
      labels: ['Quizzes','Materials','Posts','Tutorials'],
      datasets: [{
        data: [<?php echo $totalQuizzes; ?>, <?php echo $totalMaterials; ?>, <?php echo $totalPosts; ?>, <?php echo $totalTutorials; ?>],
        backgroundColor: ['#10b981','#3b82f6','#f59e0b','#ec4899'],
        borderWidth: 0
      }]
    },
    options: {
      plugins: { legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true, font: { size: 12 } } } },
      cutout: '65%'
    }
  });
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
