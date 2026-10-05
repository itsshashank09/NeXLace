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

    // Recheck persisted sessions so device revocation and account deactivation
    // take effect on the next authenticated database request.
    if (isset($_SESSION['user_id'])) {
        $check = $conn->prepare('SELECT 1 FROM user_sessions s JOIN register r ON r.Id = s.user_id WHERE s.user_id = ? AND s.session_token = ? AND r.is_active = 1 LIMIT 1');
        $check->execute([$_SESSION['user_id'], $_SESSION['session_token'] ?? '']);
        if (!$check->fetchColumn()) {
            unset($_SESSION['user_id'], $_SESSION['session_token'], $_SESSION['name'], $_SESSION['email'], $_SESSION['logged_in']);
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Your session has expired. Please sign in again.']);
            exit();
        }
    }
    return $conn;
}
?>
