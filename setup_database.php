<?php
/**
 * Setup Database and Seed Data
 * This script runs the nexlace_schema.sql file and updates passwords with correct hashes.
 */

// Database configuration
$host = "localhost";
$port = "3307"; // Adjust if needed
$username = "root";
$password = "";
$dbname = "nexlace";

try {
    // 1. Connect to MySQL server (without database selected first)
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to MySQL server successfully.<br>";

    // 2. Read SQL file
    $sqlFile = __DIR__ . '/nexlace_schema.sql';
    if (!file_exists($sqlFile)) {
        die("Error: nexlace_schema.sql not found!");
    }
    
    $sql = file_get_contents($sqlFile);

    // 3. Execute SQL (Split by commands if necessary, but exec() can handle multi-query if configured)
    // PDO::exec() might fail on multiple queries depending on driver.
    // Better to split by semicolon, but simple approach first.
    
    // We'll replace the placeholder password with the real hash in the SQL string before executing
    $realHash = password_hash('password123', PASSWORD_DEFAULT);
    $sql = str_replace('$2y$10$PLACEHOLDER_FOR_PASSWORD123', $realHash, $sql);

    // Execute the SQL commands
    $pdo->exec($sql);
    
    echo "Database schema and seed data imported successfully.<br>";
    echo "Database 'nexlace' created/reset.<br>";
    
    echo "<h3>Test Accounts Created:</h3>";
    echo "<ul>";
    echo "<li><strong>Client:</strong> client@nexlace.com / password123</li>";
    echo "<li><strong>Developer:</strong> dev@nexlace.com / password123</li>";
    echo "<li><strong>Designer:</strong> designer@nexlace.com / password123</li>";
    echo "</ul>";
    echo "<p>You can now go to <a href='login.html'>Login Page</a>.</p>";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
