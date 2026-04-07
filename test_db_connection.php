<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db) {
        echo "<h1>Database Connection Successful!</h1>";
        echo "<p>Connected to database 'nexlace' on port 3307.</p>";

        // Try a simple query
        $stmt = $db->query("SELECT VERSION()");
        $version = $stmt->fetchColumn();
        echo "<p>MySQL Version: " . htmlspecialchars($version) . "</p>";
    } else {
        echo "<h1>Connection Failed</h1>";
        echo "<p>Could not connect to the database. Check your settings in config/database.php</p>";
    }
} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
