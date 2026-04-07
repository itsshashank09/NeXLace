<?php
require_once __DIR__ . '/config/database.php';
$database = new Database();
$db = $database->getConnection();
try {
    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM register LIKE 'is_active'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE register ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        echo "Successfully added is_active column.\n";
    } else {
        echo "is_active column already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>