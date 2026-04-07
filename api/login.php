<?php
/**
 * Login API Endpoint
 * Handles user authentication using Table_register
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
    $email = isset($input['email']) ? trim(strtolower($input['email'])) : '';
    $password = isset($input['password']) ? $input['password'] : '';
} else {
    $email = isset($_POST['email']) ? trim(strtolower($_POST['email'])) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
}

// Validation
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

// Database connection
$db = getDB();

if (!$db) {
    http_response_code(500);
    sendResponse(false, 'Database connection failed. Please try again later.');
}

try {
    // --- Brute Force Protection ---
    // Create login_attempts table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        email VARCHAR(255) NOT NULL,
        attempts INT DEFAULT 1,
        last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ip_email (ip_address, email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $maxAttempts = 5;
    $lockoutMinutes = 15;

    // Check recent failed attempts for this IP + email
    $attemptStmt = $db->prepare("
        SELECT attempts, last_attempt FROM login_attempts
        WHERE ip_address = :ip AND email = :email
        AND last_attempt > DATE_SUB(NOW(), INTERVAL :lockout MINUTE)
    ");
    $attemptStmt->execute([':ip' => $ipAddress, ':email' => $email, ':lockout' => $lockoutMinutes]);
    $attemptRow = $attemptStmt->fetch();

    if ($attemptRow && $attemptRow['attempts'] >= $maxAttempts) {
        http_response_code(429);
        sendResponse(false, 'Too many failed login attempts. Please try again in ' . $lockoutMinutes . ' minutes.');
    }

    // Find user by email
    $query = "SELECT Id, Name, Email, Password, is_active FROM register WHERE Email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        // Record failed attempt
        recordFailedAttempt($db, $ipAddress, $email);
        http_response_code(401);
        sendResponse(false, 'Invalid email or password.');
    }

    $user = $stmt->fetch();

    if (isset($user['is_active']) && $user['is_active'] == 0) {
        http_response_code(403);
        sendResponse(false, 'Your account has been deactivated. Please contact the admin to reactivate it.');
    }

    // Verify password
    if (!password_verify($password, $user['Password'])) {
        // Record failed attempt
        recordFailedAttempt($db, $ipAddress, $email);
        http_response_code(401);
        sendResponse(false, 'Invalid email or password.');
    }

    // Clear failed attempts on successful login
    $clearStmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip AND email = :email");
    $clearStmt->execute([':ip' => $ipAddress, ':email' => $email]);

    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    // Create session
    $_SESSION['user_id'] = $user['Id'];
    $_SESSION['name'] = $user['Name'];
    $_SESSION['email'] = $user['Email'];
    $_SESSION['logged_in'] = true;

    // Track User Session
    $sessionToken = bin2hex(random_bytes(32));
    $_SESSION['session_token'] = $sessionToken;

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    // Simple device detection
    $deviceType = 'Desktop'; // Default
    if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', $userAgent)) {
        $deviceType = 'Tablet';
    } elseif (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $userAgent)) {
        $deviceType = 'Mobile';
    }

    $insertSession = $db->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, device_type) VALUES (:uid, :token, :ip, :ua, :device)");
    $insertSession->execute([
        ':uid' => $user['Id'],
        ':token' => $sessionToken,
        ':ip' => $ipAddress,
        ':ua' => $userAgent,
        ':device' => $deviceType
    ]);

    // Success response
    http_response_code(200);
    sendResponse(true, 'Login successful! Redirecting...', [
        'userId' => $user['Id'],
        'name' => $user['Name'],
        'email' => $user['Email']
    ]);

} catch (PDOException $e) {
    error_log("Login Error: " . $e->getMessage());
    http_response_code(500);
    sendResponse(false, 'An error occurred during login. Please try again.');
}

/**
 * Record a failed login attempt in the database.
 */
function recordFailedAttempt($db, $ip, $email)
{
    try {
        // Try to update existing record
        $updateStmt = $db->prepare("
            UPDATE login_attempts
            SET attempts = attempts + 1, last_attempt = NOW()
            WHERE ip_address = :ip AND email = :email
            AND last_attempt > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $updateStmt->execute([':ip' => $ip, ':email' => $email]);

        // If no existing record was updated, insert a new one
        if ($updateStmt->rowCount() === 0) {
            $insertStmt = $db->prepare("
                INSERT INTO login_attempts (ip_address, email, attempts, last_attempt)
                VALUES (:ip, :email, 1, NOW())
            ");
            $insertStmt->execute([':ip' => $ip, ':email' => $email]);
        }
    } catch (PDOException $e) {
        error_log("Failed to record login attempt: " . $e->getMessage());
    }
}
?>