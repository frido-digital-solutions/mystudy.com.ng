<?php
// Cache management script
echo "<h1>Cache Management</h1>";

$cache_dir = __DIR__ . '/cache';

// Clear cache if requested
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                echo "✅ Deleted: " . basename($file) . "<br>";
            }
        }
        echo "<br>✅ Cache cleared successfully!<br>";
    } else {
        echo "⚠️ No cache directory found.<br>";
    }
    echo '<br><a href="?">Back to Cache Status</a>';
    exit;
}

// Show cache status
echo "<h2>Cache Status:</h2>";

if (is_dir($cache_dir)) {
    $files = glob($cache_dir . '/*');
    $total_size = 0;
    
    echo "Cache directory: $cache_dir<br>";
    echo "Files in cache: " . count($files) . "<br><br>";
    
    if (count($files) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>File</th><th>Size</th><th>Age</th><th>Status</th></tr>";
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $size = filesize($file);
                $age = time() - filemtime($file);
                $total_size += $size;
                
                $status = $age < 300 ? '✅ Fresh' : '⚠️ Stale';
                
                echo "<tr>";
                echo "<td>" . basename($file) . "</td>";
                echo "<td>" . number_format($size / 1024, 2) . " KB</td>";
                echo "<td>" . number_format($age / 60, 1) . " min</td>";
                echo "<td>$status</td>";
                echo "</tr>";
            }
        }
        
        echo "</table>";
        echo "<br><strong>Total cache size: " . number_format($total_size / 1024, 2) . " KB</strong>";
    }
} else {
    echo "❌ Cache directory not found<br>";
    echo "Creating cache directory... ";
    if (mkdir($cache_dir, 0755, true)) {
        echo "✅ Created successfully<br>";
    } else {
        echo "❌ Failed to create<br>";
    }
}

echo "<br><br>";
echo "<a href='?action=clear'>🗑️ Clear Cache</a> | ";
echo "<a href='/'>🏠 Back to Site</a>";
?>
