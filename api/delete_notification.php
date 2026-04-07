<?php
session_start();
require_once '../config/database.php';
require_once '../includes/db_helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/csrf.php';
enforceCsrf();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$notification_id = $input['notification_id'] ?? null;

if (!$notification_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
    exit;
}

try {
    $conn = getDB();

    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :user_id");
    $stmt->execute([
        ':id' => $notification_id,
        ':user_id' => $_SESSION['user_id']
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Notification not found or access denied']);
    }
} catch (Exception $e) {
    error_log("Delete Notification Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete notification']);
}
?>