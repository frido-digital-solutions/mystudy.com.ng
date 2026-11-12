<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Material.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$materialModel = new Material($pdo);
$material = $id ? $materialModel->getById($id) : null;

if (!$material || ($material['status'] ?? '') !== 'approved') {
    set_flash_message('error', 'Material not found.');
    header('Location: ' . SITE_URL . '/materials.php');
    exit;
}

$page_title = htmlspecialchars($material['title']);
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="mb-2"><?php echo htmlspecialchars($material['title']); ?></h3>
                    <div class="text-muted mb-3">
                        Category: <?php echo htmlspecialchars($material['category_name'] ?? 'Uncategorized'); ?>
                        · Uploaded: <?php echo htmlspecialchars(date('M j, Y', strtotime($material['created_at']))); ?>
                    </div>
                    <?php if (!empty($material['description'])): ?>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($material['description'])); ?></p>
                    <?php endif; ?>

                    <?php
                      $fileUrl = SITE_URL . '/' . ltrim(($material['file_path'] ?? ''), '/');
                      $mime = strtolower($material['mime_type'] ?? '');
                      $previewHtml = '';
                      if (strpos($mime, 'application/pdf') !== false) {
                          $previewHtml = '<embed src="'.htmlspecialchars($fileUrl).'" type="application/pdf" style="width:100%;height:70vh;" />';
                      } elseif (strpos($mime, 'image/') === 0) {
                          $previewHtml = '<img src="'.htmlspecialchars($fileUrl).'" class="img-fluid rounded" />';
                      } elseif (preg_match('/\.docx?$|\.pptx?$|\.xlsx?$/i', $fileUrl)) {
                          $ow = 'https://view.officeapps.live.com/op/embed.aspx?src=' . urlencode($fileUrl);
                          $previewHtml = '<iframe src="'.htmlspecialchars($ow).'" style="width:100%;height:70vh;" frameborder="0"></iframe>';
                      } else {
                          $previewHtml = '<div class="alert alert-info">No inline preview available. Use the Download button to view this file.</div>';
                      }
                      echo $previewHtml;
                    ?>

                    <div class="mt-3">
                        <?php 
                          $downloadUrl = SITE_URL . '/download.php?id=' . (int)$material['id'];
                          $downloadName = !empty($material['original_name']) ? $material['original_name'] : basename($material['file_path']);
                        ?>
                        <a class="btn btn-primary" href="<?php echo htmlspecialchars($downloadUrl); ?>" download="<?php echo htmlspecialchars($downloadName); ?>">
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <a class="btn btn-outline-secondary" href="<?php echo SITE_URL; ?>/materials.php">
                            <i class="fas fa-arrow-left me-1"></i> Back to list
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white"><strong>Details</strong></div>
                <div class="card-body">
                    <div class="mb-2"><strong>Type:</strong> <?php echo htmlspecialchars($material['mime_type'] ?? 'Unknown'); ?></div>
                    <div class="mb-2"><strong>Size:</strong> <?php echo isset($material['file_size']) ? number_format($material['file_size'] / 1024, 1) . ' KB' : 'Unknown'; ?></div>
                    <div class="mb-2"><strong>Visibility:</strong> <?php echo htmlspecialchars($material['visibility']); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
