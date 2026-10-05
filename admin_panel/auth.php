<?php
/**
 * Admin Authentication Helper
 * Handles admin login, session management, and authorization
 */

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security_headers.php';

/**
 * Check if admin is logged in
 * @return bool
 */
function isAdminLoggedIn()
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Require admin authentication
 * Redirects to login if not authenticated
 */
function requireAdminAuth()
{
    if (!isAdminLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

/**
 * Authenticate admin credentials
 * @param string $username
 * @param string $password
 * @return array ['success' => bool, 'message' => string, 'data' => array|null]
 */
function authenticateAdmin($username, $password)
{
    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Username and password are required'];
    }

    require_once __DIR__ . '/../includes/db_helper.php';
    $db = getDB();

    if (!$db) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        // Get admin by username
        $stmt = $db->prepare("SELECT id, username, password, full_name FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        // Admin passwords are provisioned as password_hash values.
        if (!password_verify($password, $admin['password'])) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        // Update last login
        $updateStmt = $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$admin['id']]);

        session_regenerate_id(true);

        // Set session variables
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_name'] = $admin['full_name'] ?? $admin['username'];

        return [
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'id' => $admin['id'],
                'username' => $admin['username'],
                'name' => $admin['full_name'] ?? $admin['username']
            ]
        ];

    } catch (PDOException $e) {
        error_log("Admin Auth Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during authentication'];
    }
}

/**
 * Logout admin
 */
function logoutAdmin()
{
    session_unset();
    session_destroy();
}

/**
 * Get current admin info
 * @return array|null
 */
function getCurrentAdmin()
{
    if (!isAdminLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['admin_id'] ?? null,
        'username' => $_SESSION['admin_username'] ?? null,
        'name' => $_SESSION['admin_name'] ?? null
    ];
}
?>