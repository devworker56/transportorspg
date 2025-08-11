<?php
// Test if index.php loads without errors
ob_start();
include 'index.php';
$content = ob_get_clean();

if ($content === false) {
    die("❌ Error: index.php failed to load");
}

// Check for essential elements
$checks = [
    'DOCTYPE html' => strpos($content, '<!DOCTYPE html') !== false,
    'Title tag' => strpos($content, '<title>') !== false,
    'Header content' => strpos($content, 'Transporteur Provincial') !== false,
    'No PHP errors' => strpos($content, 'Warning:') === false && strpos($content, 'Error:') === false,
    'Session started' => isset($_SESSION) || strpos($content, 'session_start()') !== false
];

echo "<h1>Index.php Test Results</h1>";
echo "<table border='1'>";
foreach ($checks as $label => $result) {
    echo "<tr><td>$label</td><td>" . ($result ? "✅ PASS" : "❌ FAIL") . "</td></tr>";
}
echo "</table>";

// Output raw content for debugging (comment out in production)
echo "<h2>Page Content (first 500 chars)</h2>";
echo "<pre>" . htmlspecialchars(substr($content, 0, 500)) . "...</pre>";