<?php
/**
 * Registration API Endpoint
 * Handles user registration and stores data in Table_register
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
require_once '../config/csrf.php';
enforceCsrf();

require_once '../config/database.php';
require_once '../includes/db_helper.php';

// Response helper function
function sendResponse($success, $message, $data = null)
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendResponse(false, 'Method not allowed. Use POST.');
}

// Get JSON input or form data
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $name = isset($input['name']) ? trim($input['name']) : '';
    $email = isset($input['email']) ? trim(strtolower($input['email'])) : '';
    $password = isset($input['password']) ? $input['password'] : '';
} else {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim(strtolower($_POST['email'])) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
}

// Validation
if (empty($name)) {
    http_response_code(400);
    sendResponse(false, 'Name is required.');
}

if (empty($email)) {
    http_response_code(400);
    sendResponse(false, 'Email is required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    sendResponse(false, 'Invalid email format.');
}

if (empty($password)) {
    http_response_code(400);
    sendResponse(false, 'Password is required.');
}

if (strlen($password) < 8) {
    http_response_code(400);
    sendResponse(false, 'Password must be at least 8 characters long.');
}

// Database connection
$db = getDB();

if (!$db) {
    http_response_code(500);
    sendResponse(false, 'Database connection failed. Please try again later.');
}

try {
    // Check if email already exists
    $checkQuery = "SELECT Id FROM register WHERE Email = :email";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':email', $email);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        http_response_code(409);
        sendResponse(false, 'This email is already registered. Please use a different email or login.');
    }

    // Hash password for security
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $insertQuery = "INSERT INTO register (Name, Email, Password) VALUES (:name, :email, :password)";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':name', $name);
    $insertStmt->bindParam(':email', $email);
    $insertStmt->bindParam(':password', $hashedPassword);

    if ($insertStmt->execute()) {
        $userId = $db->lastInsertId();
        http_response_code(201);
        sendResponse(true, 'Registration successful! Redirecting...', [
            'userId' => $userId,
            'name' => $name,
            'email' => $email
        ]);
    } else {
        http_response_code(500);
        sendResponse(false, 'Failed to register. Please try again.');
    }

} catch (PDOException $e) {
    error_log("Registration Error: " . $e->getMessage());
    http_response_code(500);
    sendResponse(false, 'An error occurred during registration. Please try again.');
}
?>