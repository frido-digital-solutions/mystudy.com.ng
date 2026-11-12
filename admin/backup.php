<?php
require_once __DIR__ . '/../config/config.php';
require_super_admin();

$page_title = 'Backup & Restore';

/**
 * Quote a single value for the dump. PDO::quote() uses the connection's real
 * escaping; the manual fallback only runs if the driver refuses the value.
 */
function backup_sql_value($pdo, $value) {
    if ($value === null) {
        return 'NULL';
    }
    $value = (string)$value;
    $quoted = $pdo->quote($value);
    if ($quoted === false) {
        $quoted = "'" . str_replace(
            ['\\', "'", "\0", "\n", "\r", "\x1a"],
            ['\\\\', "\\'", '\\0', '\\n', '\\r', '\\Z'],
            $value
        ) . "'";
    }
    return $quoted;
}

// Stream the dump straight to the browser. Nothing is written to disk.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid form token. Please try again.');
        redirect(SITE_URL . '/admin/backup.php');
    }

    log_admin_activity($pdo, 'Database Backup', 'Downloaded a full SQL dump of the database');

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    @set_time_limit(0);
    @ini_set('zlib.output_compression', '0');

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="mystudy-backup-' . date('Ymd-His') . '.sql"');
    header('X-Content-Type-Options: nosniff');
    header('Pragma: no-cache');
    header('Expires: 0');

    try {
        echo "-- MyStudy Base database backup\n";
        echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        echo "-- Database: " . DB_NAME . "\n\n";
        echo "SET NAMES utf8mb4;\n";
        echo "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        echo "SET time_zone = '+00:00';\n";
        echo "SET FOREIGN_KEY_CHECKS = 0;\n";

        // The app connection runs at +01:00. TIMESTAMP columns are rendered in the
        // session timezone, so the export session must match the one declared above
        // or every restored timestamp shifts by an hour.
        $pdo->exec("SET time_zone = '+00:00'");

        $tables = [];
        $tableStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        while ($tableRow = $tableStmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $tableRow[0];
        }
        $tableStmt->closeCursor();

        foreach ($tables as $table) {
            $safeTable = str_replace('`', '``', $table);

            $createStmt = $pdo->query("SHOW CREATE TABLE `" . $safeTable . "`");
            $createRow = $createStmt->fetch(PDO::FETCH_NUM);
            $createStmt->closeCursor();

            echo "\n-- --------------------------------------------------------\n";
            echo "-- Table structure for `" . $safeTable . "`\n\n";
            echo "DROP TABLE IF EXISTS `" . $safeTable . "`;\n";
            echo ($createRow[1] ?? '') . ";\n\n";
            echo "-- Dumping data for `" . $safeTable . "`\n\n";

            // Unbuffered so only one row is ever held in memory.
            $dataStmt = $pdo->prepare("SELECT * FROM `" . $safeTable . "`", [PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false]);
            $dataStmt->execute();

            $columns = null;
            $batch = [];
            $batchBytes = 0;

            while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                if ($columns === null) {
                    $names = [];
                    foreach (array_keys($row) as $col) {
                        $names[] = '`' . str_replace('`', '``', $col) . '`';
                    }
                    $columns = implode(', ', $names);
                }

                $values = [];
                foreach ($row as $value) {
                    $values[] = backup_sql_value($pdo, $value);
                }
                $tuple = '(' . implode(', ', $values) . ')';
                $batch[] = $tuple;
                $batchBytes += strlen($tuple) + 2;

                if (count($batch) >= 50 || $batchBytes >= 262144) {
                    echo "INSERT INTO `" . $safeTable . "` (" . $columns . ") VALUES\n" . implode(",\n", $batch) . ";\n";
                    $batch = [];
                    $batchBytes = 0;
                    flush();
                }
            }

            if (!empty($batch)) {
                echo "INSERT INTO `" . $safeTable . "` (" . $columns . ") VALUES\n" . implode(",\n", $batch) . ";\n";
                flush();
            }
            if ($columns === null) {
                echo "-- (no rows)\n";
            }

            $dataStmt->closeCursor();
        }

        echo "\nSET FOREIGN_KEY_CHECKS = 1;\n";
        echo "\n-- Backup complete: " . count($tables) . " table(s)\n";
    } catch (Throwable $e) {
        error_log('Database backup failed: ' . $e->getMessage());
        echo "\n-- BACKUP FAILED: " . str_replace(["\r", "\n"], ' ', $e->getMessage()) . "\n";
        echo "-- This file is INCOMPLETE and must not be used to restore.\n";
    }
    exit;
}

include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h4 mb-0">Backup & Restore</h1>
      </div>
      <?php echo display_flash_message(); ?>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header bg-white"><strong>Create Backup</strong></div>
            <div class="card-body">
              <p class="text-muted">Download a full SQL dump of every table in the database. Uploaded files are not included.</p>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="action" value="export">
                <button type="submit" class="btn btn-primary"><i class="fas fa-download me-1"></i> Download Backup</button>
              </form>
              <div class="form-text mt-2">Super admins only. The dump is streamed to your browser and is never saved on the server.</div>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header bg-white"><strong>Restore Backup</strong></div>
            <div class="card-body">
              <p class="text-muted">Restoring is not available in the admin panel. To restore, import the downloaded <code>.sql</code> file using phpMyAdmin (Import tab) in your hosting control panel.</p>
              <button class="btn btn-danger" disabled>Restore Unavailable</button>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>