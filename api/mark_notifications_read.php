<?php
/**
 * API: Mark Notifications as Read
 * Marks all or specific notifications as read
 */

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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$notificationId = $input['notification_id'] ?? null; // Optional: mark single notification
$markAll = $input['mark_all'] ?? false; // Mark all as read

try {
    $conn = getDB();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $userId = $_SESSION['user_id'];

    if ($markAll || $notificationId === null) {
        // Mark all notifications as read for this user
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute([':user_id' => $userId]);
        $affected = $stmt->rowCount();

        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read',
            'count' => $affected
        ]);
    } else {
        // Mark single notification as read
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Notification not found or already read'
            ]);
        }
    }

} catch (Exception $e) {
    error_log("Mark Notifications Read Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to mark notifications as read']);
}
?>