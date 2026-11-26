<?php
require_once __DIR__ . '/config/config.php';
$page_title = 'WAEC, NECO & JAMB Study Materials - Free Notes and Past Question Downloads';

// Load model
require_once __DIR__ . '/models/Material.php';
require_once __DIR__ . '/models/MaterialPayment.php';
require_once __DIR__ . '/includes/materials_paid.php';
$materialModel = new Material($pdo);
$materialPayments = new MaterialPayment($pdo);

// Build university id => acronym map for messages
$uniMap = [];
foreach ($materialModel->listUniversities() as $u) {
    $uniMap[(int)$u['id']] = $u['acronym'] ?: $u['name'];
}

// Params (GET)
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$level = isset($_GET['level']) ? (($_GET['level'] === 'University') ? 'University' : (($_GET['level'] === 'Secondary') ? 'Secondary' : null)) : null;
$secondaryBoard = isset($_GET['secondary_board']) ? (string)$_GET['secondary_board'] : '';
$isUniGen = isset($_GET['is_university_general']) ? (int)!!$_GET['is_university_general'] : null; // null = no filter
$universityId = isset($_GET['university_id']) ? (int)$_GET['university_id'] : 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;
$view = (($_GET['view'] ?? '') === 'purchased') ? 'purchased' : '';

$currentUserId = is_logged_in() ? (int)$_SESSION['user_id'] : 0;

if ($view === 'purchased' && $currentUserId > 0) {
    $ownedAll = $materialPayments->allOwnedIds($currentUserId);
    $materials = $ownedAll ? $materialModel->listByIds($ownedAll, $limit, $offset) : [];
    $totalCount = count($ownedAll);
} else {
    $view = '';
    // Fetch data using advanced search
    $materials = $materialModel->searchMaterials(
        $q !== '' ? $q : null,
        $level,
        $secondaryBoard ?: null,
        $isUniGen,
        $universityId ?: null,
        $limit,
        $offset,
        'approved'
    );

    // Get total count for pagination
    $totalCount = $materialModel->countSearchMaterials(
        $q !== '' ? $q : null,
        $level,
        $secondaryBoard ?: null,
        $isUniGen,
        $universityId ?: null,
        'approved'
    );
}
$totalPages = ceil($totalCount / $limit) ?: 1;

// Which of the materials about to be drawn is already paid for?
$viewerOwnsIds = $currentUserId > 0 ? $materialPayments->ownedIds($currentUserId, array_column($materials, 'id')) : [];
$viewerIsAdmin = $currentUserId > 0 && has_role('admin');

include __DIR__ . '/includes/header.php';
?>

<!-- Styles loaded from design-system.css -->
<style>
.mat-lock{color:#f59e0b;font-size:.82em;margin-right:.15em}
.mat-price{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;font-weight:700}
.mat-owned{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;font-weight:700}
.mat-tabs{display:flex;gap:.4rem;flex-wrap:wrap}
.mat-tabs a{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .95rem;border-radius:999px;font-size:.86rem;font-weight:600;text-decoration:none;background:#fff;border:1px solid var(--border,#e2e8f0);color:var(--text-muted,#64748b)}
.mat-tabs a.active{background:var(--primary,#10b981);border-color:var(--primary,#10b981);color:#fff}
</style>

<div class="page-header">
    <div class="container d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <h1 class="page-title">Study Materials</h1>
            <p class="text-muted mb-0">Access thousands of resources to boost your learning</p>
        </div>
        <?php if (is_logged_in()): ?>
            <a href="<?php echo SITE_URL; ?>/upload-material.php" class="btn btn-primary rounded-pill px-4 mt-3 mt-md-0">
                <i class="fas fa-upload me-2"></i> Upload Material
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="container pb-5">
    <?php if (is_logged_in()): ?>
        <div class="mat-tabs mb-3">
            <a href="<?php echo SITE_URL; ?>/materials.php" class="<?php echo $view === '' ? 'active' : ''; ?>">
                <i class="fas fa-layer-group"></i> All materials
            </a>
            <a href="<?php echo SITE_URL; ?>/materials.php?view=purchased" class="<?php echo $view === 'purchased' ? 'active' : ''; ?>">
                <i class="fas fa-unlock"></i> My purchases
            </a>
        </div>
    <?php endif; ?>
    <!-- Search & Filter -->
    <div class="search-card">
        <form method="get" action="">
            <?php if ($view !== ''): ?><input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>"><?php endif; ?>
            <div class="row g-3">
                <div class="col-lg-5">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" name="q" placeholder="Keywords, titles, description..." value="<?php echo htmlspecialchars($q); ?>">
                    </div>
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="form-label">Level</label>
                    <select class="form-select" id="levelFilter" name="level">
                        <option value="" <?php echo $level===null?'selected':''; ?>>All Levels</option>
                        <option value="Secondary" <?php echo $level==='Secondary'?'selected':''; ?>>Secondary</option>
                        <option value="University" <?php echo $level==='University'?'selected':''; ?>>University</option>
                    </select>
                </div>
                
                <!-- Secondary Board Filter -->
                <div class="col-md-6 col-lg-3" id="secBoardWrap" style="display: <?php echo ($level==='Secondary')?'block':'none'; ?>;">
                    <label class="form-label">Board/Exam</label>
                    <select class="form-select" name="secondary_board">
                        <option value="">All Boards</option>
                        <?php foreach (['WAEC','NECO','JAMB','GCE'] as $b): ?>
                            <option value="<?php echo $b; ?>" <?php echo ($secondaryBoard===$b)?'selected':''; ?>><?php echo $b; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- University Filter -->
                <div class="col-md-6 col-lg-3" id="uniSelectWrap" style="display: <?php echo ($level==='University' && !$isUniGen)?'block':'none'; ?>;">
                    <label class="form-label">University</label>
                    <select class="form-select" name="university_id">
                        <option value="">All Universities</option>
                        <?php foreach ($materialModel->listUniversities() as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>" <?php echo ($universityId===(int)$u['id'])?'selected':''; ?>><?php echo htmlspecialchars($u['acronym']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 col-lg-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 rounded-3">Apply Filters</button>
                </div>
            </div>
            
            <!-- Checkbox for University General -->
            <div class="mt-3" id="uniGenWrap" style="display: <?php echo ($level==='University')?'block':'none'; ?>;">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="isUniversityGeneral" name="is_university_general" value="1" <?php echo ($isUniGen===1)?'checked':''; ?>>
                    <label class="form-check-label text-muted small" for="isUniversityGeneral">Show only general university materials (not specific to one institution)</label>
                </div>
            </div>
        </form>
    </div>

    <!-- Results -->
    <?php if (empty($materials)): ?>
        <div class="empty-state">
            <i class="fas fa-folder-open empty-icon"></i>
            <h4>No materials found</h4>
            <div class="text-muted">Try adjusting your search filters or check back later.</div>
            <?php if (isset($q) && $q !== ''): ?>
                <div class="mt-2 text-muted small">Matches for: "<?php echo htmlspecialchars($q); ?>"</div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="result-bar">
            <div class="result-count">Found <strong><?php echo number_format($totalCount); ?></strong> matches</div>
        </div>
        <div class="row g-4">
            <?php foreach ($materials as $m): 
                $ext = strtolower(pathinfo($m['file_path'], PATHINFO_EXTENSION));
                $iconClass = 'fa-file';
                $colorClass = '';
                
                if (in_array($ext, ['pdf'])) { $iconClass = 'fa-file-pdf'; $colorClass = 'pdf'; }
                elseif (in_array($ext, ['doc','docx'])) { $iconClass = 'fa-file-word'; $colorClass = 'doc'; }
                elseif (in_array($ext, ['ppt','pptx'])) { $iconClass = 'fa-file-powerpoint'; $colorClass = 'ppt'; }
                elseif (in_array($ext, ['xls','xlsx'])) { $iconClass = 'fa-file-excel'; $colorClass = 'xls'; }

                $mPaid = material_is_paid($m);
                $mOwned = in_array((int)$m['id'], $viewerOwnsIds, true)
                    || (int)($m['uploader_id'] ?? 0) === $currentUserId
                    || $viewerIsAdmin;
                $mLocked = $mPaid && !$mOwned;
            ?>
            <div class="col-md-6 col-lg-3">
                <div class="material-card">
                    <div class="material-icon <?php echo $colorClass; ?>">
                        <i class="fas <?php echo $iconClass; ?>"></i>
                    </div>
                    <div class="material-body">
                        <h3 class="material-title">
                            <?php if ($mLocked): ?><i class="fas fa-lock mat-lock" title="Paid material"></i> <?php endif; ?>
                            <?php echo htmlspecialchars($m['title']); ?>
                        </h3>
                        <p class="material-desc"><?php echo htmlspecialchars($m['description'] ?: 'No description provided.'); ?></p>
                        
                        <div class="material-meta mb-3 d-flex flex-wrap gap-2">
                            <?php if ($m['level'] === 'Secondary'): ?>
                                <span class="badge bg-success text-white px-2 py-1 shadow-sm"><i class="fas fa-school me-1"></i> Secondary</span>
                                <?php if (!empty($m['secondary_board'])): ?>
                                    <span class="badge bg-success text-white px-2 py-1 shadow-sm"><?php echo htmlspecialchars($m['secondary_board']); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-success text-white px-2 py-1 shadow-sm"><i class="fas fa-university me-1"></i> University</span>
                                <?php if (!empty($m['university_id']) && isset($uniMap[$m['university_id']])): ?>
                                    <span class="badge bg-success text-white px-2 py-1 shadow-sm"><?php echo htmlspecialchars($uniMap[$m['university_id']]); ?></span>
                                <?php elseif ($m['is_university_general']): ?>
                                    <span class="badge bg-success text-white px-2 py-1 shadow-sm">General</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <span class="badge bg-light text-muted border px-2 py-1"><?php echo strtoupper($ext); ?></span>
                            <?php if ($mLocked): ?>
                                <span class="badge mat-price">&#8358;<?php echo number_format((float)$m['price'], 0); ?></span>
                            <?php elseif ($mPaid): ?>
                                <span class="badge mat-owned"><i class="fas fa-check me-1"></i>Unlocked</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="material-stats mt-3 mb-3 d-flex justify-content-between text-muted small">
                            <span><i class="far fa-calendar-alt me-1"></i> <?php echo date('M d, Y', strtotime($m['created_at'])); ?></span>
                            <?php if (!empty($m['file_size'])): ?>
                                <span><i class="fas fa-hdd me-1"></i> <?php echo round($m['file_size'] / 1024 / 1024, 2); ?> MB</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($mLocked): ?>
                            <a href="<?php echo SITE_URL; ?>/material.php?id=<?php echo (int)$m['id']; ?>"
                               class="btn btn-primary btn-download mt-auto rounded-pill" target="_self">
                                <i class="fas fa-lock-open me-2"></i> Pay &#8358;<?php echo number_format((float)$m['price'], 0); ?> &amp; download
                            </a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/material_download.php?id=<?php echo (int)$m['id']; ?>" class="btn btn-outline-primary btn-download mt-auto" target="_blank">
                                <i class="fas fa-download me-2"></i> Download
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): 
            $queryParams = $_GET;
            unset($queryParams['page']);
            $qs = http_build_query($queryParams);
            $baseUrl = '?' . ($qs ? $qs . '&' : '') . 'page=';
        ?>
            <!-- Pagination -->
            <nav aria-label="Page navigation" class="mt-5">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link shadow-sm" href="<?php echo $baseUrl . ($page - 1); ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link shadow-sm" href="<?php echo $baseUrl . $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link shadow-sm" href="<?php echo $baseUrl . ($page + 1); ?>">Next <i class="fas fa-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Filter logic handled by main.js -->

<?php include __DIR__ . '/includes/footer.php'; ?>
