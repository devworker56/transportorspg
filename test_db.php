<?php
require_once 'includes/config.php';

try {
    // Create connection using PDO
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES  => false,
    ];
    
    $db = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Test query
    $stmt = $db->query("SELECT 1");
    $result = $stmt->fetch();
    
    echo "<h1>Database Connection Successful!</h1>";
    echo "<p>Connected to database: " . DB_NAME . "</p>";
    echo "<p>Host: " . DB_HOST . "</p>";
    echo "<p>Using PDO driver: " . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . "</p>";
    
    // Optional: Show server version
    echo "<p>MySQL Server Version: " . $db->getAttribute(PDO::ATTR_SERVER_VERSION) . "</p>";
    
} catch (PDOException $e) {
    echo "<h1>Database Connection Failed</h1>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Error Code:</strong> " . $e->getCode() . "</p>";
    
    // Debugging information (remove in production)
    echo "<h2>Debug Information</h2>";
    echo "<p><strong>Host:</strong> " . DB_HOST . "</p>";
    echo "<p><strong>Database:</strong> " . DB_NAME . "</p>";
    echo "<p><strong>Username:</strong> " . DB_USER . "</p>";
    echo "<p><strong>Password:</strong> " . (DB_PASS ? "(set)" : "not set") . "</p>";
}
?>