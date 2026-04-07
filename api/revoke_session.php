<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/csrf.php';
enforceCsrf();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

require_once __DIR__ . '/../includes/db_helper.php';

try {
    require_once __DIR__ . '/../includes/db_helper.php';
$conn = getDB();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sessionId = intval($_POST['device_id'] ?? 0);

        // Prevent deleting current session (or handle it as logout)
        // But for "Connected Devices" panel, usually we revoke *other* sessions.
        // If it's current, we should probably redirect to logout? 
        // For now, let's just allow deletion.

        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE id = :id AND user_id = :uid");
        $stmt->execute([
            ':id' => $sessionId,
            ':uid' => $_SESSION['user_id']
        ]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Device logged out successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Device not found or access denied']);
        }
    } else {
        throw new Exception('Invalid method');
    }

} catch (Exception $e) {
    error_log("Revoke Session Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to revoke session.']);
}
?>