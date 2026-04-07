<?php
session_start();

require_once __DIR__ . '/../includes/db_helper.php';

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

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

try {
    require_once __DIR__ . '/../includes/db_helper.php';
$conn = getDB();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $currentPassword = $_POST['currentPassword'] ?? '';
        $newPassword = $_POST['newPassword'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        $userId = $_SESSION['user_id'];

        // Basic validation
        if (empty($currentPassword) || empty($newPassword)) {
            throw new Exception('All fields are required');
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception('New passwords do not match');
        }

        if (strlen($newPassword) < 8) {
            throw new Exception('New password must be at least 8 characters long');
        }

        // Get user data to verify current password
        $stmt = $conn->prepare("SELECT Password FROM register WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['Password'])) {
            throw new Exception('Incorrect current password');
        }

        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE register SET Password = :password WHERE id = :id");
        $updateStmt->execute([
            ':password' => $hashedPassword,
            ':id' => $userId
        ]);

        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
    } else {
        throw new Exception('Invalid request method');
    }
} catch (PDOException $e) {
    error_log("Password Change DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating the password.']);
} catch (Exception $e) {
    error_log("Password Change Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>