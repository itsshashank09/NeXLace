<?php
require_once 'auth.php';
requireAdminAuth();
require_once __DIR__ . '/../includes/db_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? null;
$status = $input['status'] ?? null;

if (!$userId || !is_numeric($userId) || !in_array($status, [0, 1])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

$db = getDB();

if ($db) {
    try {
        $stmt = $db->prepare("UPDATE register SET is_active = ? WHERE Id = ?");
        $stmt->execute([$status, $userId]);

        $action = $status == 1 ? "Activated" : "Deactivated";
        echo json_encode(['success' => true, 'message' => "User account has been $action."]);
    } catch (PDOException $e) {
        error_log("Failed to toggle user status: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
}
?>