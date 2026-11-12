<?php
// Performance monitoring script
require_once __DIR__ . '/config/config.php';
$start_time = microtime(true);

echo "<h1>Performance Analysis</h1>";

// 1. Page Load Time
echo "<h2>Page Load Time:</h2>";
$load_time = microtime(true) - $start_time;
echo "Current load time: " . number_format($load_time, 4) . " seconds<br>";

// 2. Memory Usage
echo "<h2>Memory Usage:</h2>";
$memory_usage = memory_get_usage(true);
$memory_peak = memory_get_peak_usage(true);
echo "Current memory: " . number_format($memory_usage / 1024 / 1024, 2) . " MB<br>";
echo "Peak memory: " . number_format($memory_peak / 1024 / 1024, 2) . " MB<br>";

// 3. Database Connection Test
echo "<h2>Database Performance:</h2>";
$db_start = microtime(true);
try {
    require_once __DIR__ . '/config/database.php';
    $pdo = getDbConnection();
    $db_time = microtime(true) - $db_start;
    echo "Database connection time: " . number_format($db_time, 4) . " seconds<br>";
    
    // Test query performance
    $query_start = microtime(true);
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    $query_time = microtime(true) - $query_start;
    echo "Sample query time: " . number_format($query_time, 4) . " seconds<br>";
    echo "Total users: " . $result['count'] . "<br>";
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}

// 4. PHP Information
echo "<h2>PHP Configuration:</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . "s<br>";
echo "OPcache enabled: " . (extension_loaded('Zend OPcache') && ini_get('opcache.enable') ? 'Yes' : 'No') . "<br>";

// 5. Server Info
echo "<h2>Server Info:</h2>";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "<br>";

// 6. Check for common performance issues
echo "<h2>Performance Issues Check:</h2>";

// Check if debug.php exists (should be deleted)
if (file_exists(__DIR__ . '/debug.php')) {
    echo "⚠️ Delete debug.php - it's a security risk and slows down loading<br>";
}

// Check error reporting
if (ini_get('display_errors')) {
    echo "⚠️ Display errors is ON - turn off for production<br>";
}

// Check file sizes
$index_size = filesize(__DIR__ . '/index.php');
if ($index_size > 50000) { // 50KB
    echo "⚠️ index.php is large (" . number_format($index_size/1024, 2) . "KB) - consider optimizing<br>";
}

// Check autoload performance
$autoload_start = microtime(true);
spl_autoload_register(function ($class_name) {
    $paths = [
        __DIR__ . '/models/',
        __DIR__ . '/controllers/',
        __DIR__ . '/includes/'
    ];
    
    foreach ($paths as $path) {
        $file = $path . $class_name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
$autoload_time = microtime(true) - $autoload_start;
echo "Autoload setup time: " . number_format($autoload_time, 4) . " seconds<br>";

$total_time = microtime(true) - $start_time;
echo "<h2>Total Analysis Time:</h2>";
echo "Total time: " . number_format($total_time, 4) . " seconds<br>";

echo "<h2>Recommendations:</h2>";
echo "<ul>";
echo "<li>Enable OPcache in TrueHost cPanel (PHP Settings)</li>";
echo "<li>Delete unnecessary files (debug.php, setup_dirs.php)</li>";
echo "<li>Optimize database queries</li>";
echo "<li>Implement caching</li>";
echo "<li>Compress assets</li>";
echo "</ul>";
?>
