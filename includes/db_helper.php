<?php
require_once __DIR__ . '/../config/database.php';

function getDB()
{
    static $conn = null;

    if ($conn === null) {
        $database = new Database();
        $conn = $database->getConnection();

        if (!$conn) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit();
        }
    }

    return $conn;
}
?>