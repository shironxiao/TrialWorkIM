<?php
/**
 * TABEYA SYSTEM - PATH DIAGNOSTIC TOOL
 * Place this file in your root directory to diagnose path issues
 */

echo "<h1>🔍 Tabeya System - Path Diagnostic</h1>";
echo "<hr>";

// Get current directory
$current_dir = __DIR__;
echo "<h2>Current Directory:</h2>";
echo "<code>" . $current_dir . "</code>";
echo "<hr>";

// Check for key files
echo "<h2>File Existence Check:</h2>";
echo "<ul>";

$files_to_check = [
    'save_product_reservation.php',
    'fetch_products.php',
    'userInfo.html',
    'CaterReservation.html',
    'index.html',
    'CSS/userInfoDesign.css',
    'CSS/CaterDesign.css',
    'uploads/',
    'uploads/gcash_receipts/'
];

foreach ($files_to_check as $file) {
    $path = $current_dir . '/' . $file;
    $exists = file_exists($path) ? '✅ EXISTS' : '❌ MISSING';
    $type = is_dir($path) ? '[DIR]' : '[FILE]';
    echo "<li><code>$file</code> $type - $exists</li>";
}
echo "</ul>";
echo "<hr>";

// Check subdirectories
echo "<h2>Directory Structure:</h2>";
echo "<pre>";
$output = [];
exec("find " . escapeshellarg($current_dir) . " -maxdepth 2 -type f -name '*.php' -o -name '*.html'", $output);
foreach ($output as $file) {
    echo htmlspecialchars(str_replace($current_dir, '', $file)) . "\n";
}
echo "</pre>";
echo "<hr>";

// Server info
echo "<h2>Server Information:</h2>";
echo "<ul>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "<li>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</li>";
echo "<li>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "<li>Request URI: " . $_SERVER['REQUEST_URI'] . "</li>";
echo "<li>Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "</li>";
echo "</ul>";
echo "<hr>";

// Test file paths
echo "<h2>Path Resolution Test:</h2>";
echo "<code>";
echo "Relative Path Test: " . realpath('save_product_reservation.php') ?? 'Not found<br>';
echo "Absolute Path Test: " . realpath(__DIR__ . '/save_product_reservation.php') ?? 'Not found<br>';
echo "</code>";
echo "<hr>";

// Permissions check
echo "<h2>Directory Permissions:</h2>";
echo "<ul>";
$dirs_to_check = [
    '.',
    'uploads',
    'uploads/gcash_receipts'
];

foreach ($dirs_to_check as $dir) {
    $full_path = $current_dir . '/' . $dir;
    if (is_dir($full_path)) {
        $perms = substr(sprintf('%o', fileperms($full_path)), -4);
        $writable = is_writable($full_path) ? '✅ Writable' : '❌ Not Writable';
        echo "<li><code>$dir</code> - Permissions: $perms - $writable</li>";
    }
}
echo "</ul>";
echo "<hr>";

echo "<h2>✅ What to Do Next:</h2>";
echo "<ol>";
echo "<li>Check if <code>save_product_reservation.php</code> exists in the same directory as this diagnostic file</li>";
echo "<li>If missing, create the file from the provided PHP code</li>";
echo "<li>Verify the file path in your HTML matches the server path</li>";
echo "<li>Make sure directories have correct permissions (755 for folders, 644 for files)</li>";
echo "<li>Clear browser cache and try again</li>";
echo "</ol>";
echo "<hr>";

echo "<p style='color: green; font-weight: bold;'>✅ If all files show [EXISTS], the issue is likely a caching or path problem.</p>";
echo "<p style='color: red; font-weight: bold;'>❌ If files show [MISSING], you need to create them in the correct location.</p>";
?>