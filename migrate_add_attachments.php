<?php
/**
 * Database Migration: Add attachment columns to messages table
 * Run this file once to add the necessary columns for file attachments
 */

require_once 'config/database.php';
require_once 'config/security_headers.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    if (!$conn) {
        die("Database connection failed\n");
    }

    // Check if columns already exist
    $stmt = $conn->query("SHOW COLUMNS FROM messages LIKE 'attachment_path'");
    if ($stmt->rowCount() > 0) {
        echo "Migration already applied: attachment columns exist.\n";
        exit;
    }

    // Add attachment columns
    $sql = "ALTER TABLE messages 
            ADD COLUMN attachment_path VARCHAR(500) NULL DEFAULT NULL AFTER message,
            ADD COLUMN attachment_name VARCHAR(255) NULL DEFAULT NULL AFTER attachment_path";

    $conn->exec($sql);

    echo "Migration successful! Added attachment_path and attachment_name columns to messages table.\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>