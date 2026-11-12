<?php
// CSS Minifier and Compressor
ob_start("ob_gzhandler");
header("Content-Type: text/css");
header("Cache-Control: public, max-age=31536000"); // 1 year cache

// Read and minify CSS
$css_files = [
    'assets/css/design-system.css'
];

$minified_css = '';

foreach ($css_files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $css = file_get_contents(__DIR__ . '/' . $file);
        // Basic minification
        $css = preg_replace('/\/\*.*?\*\//s', '', $css); // Remove comments
        $css = preg_replace('/\s+/', ' ', $css); // Collapse whitespace
        $css = preg_replace('/;\s*}/', '}', $css); // Remove semicolons before }
        $css = str_replace([' {', '{ '], '{', $css); // Remove spaces around {
        $css = str_replace([' }', '} '], '}', $css); // Remove spaces around }
        $css = trim($css);
        $minified_css .= $css;
    }
}

echo $minified_css;
?>
