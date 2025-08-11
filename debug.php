<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>PHP is working</h1>";
echo "<p>Version: ".phpversion()."</p>";

// Test basic file inclusion
require __DIR__.'/includes/config.php';
echo "<p>Config loaded successfully</p>";

// Test session
session_start();
$_SESSION['test'] = 'OK';
echo "<p>Session test: ".$_SESSION['test']."</p>";