<?php
/**
 * CSRF Token Helper
 * 
 * Provides functions to generate and validate CSRF tokens.
 * Tokens are stored in the user's session and must be included
 * in all state-changing requests (POST/PUT/DELETE) via the
 * X-CSRF-Token header or csrf_token body parameter.
 */

/**
 * Generate a CSRF token and store it in the session.
 * Returns the existing token if one is already set.
 */
function generateCsrfToken()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token from the request.
 * Checks both the X-CSRF-Token header and csrf_token in the request body.
 * 
 * @return bool True if the token is valid
 */
function validateCsrfToken()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Skip validation for OPTIONS (preflight) and GET requests
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'OPTIONS', 'HEAD'])) {
        return true;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken)) {
        return false;
    }

    // Check header first (preferred method for AJAX/fetch requests)
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($headerToken) && hash_equals($sessionToken, $headerToken)) {
        return true;
    }

    // Check request body (for form submissions)
    $bodyToken = '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $bodyToken = $input['csrf_token'] ?? '';
    } else {
        $bodyToken = $_POST['csrf_token'] ?? '';
    }

    if (!empty($bodyToken) && hash_equals($sessionToken, $bodyToken)) {
        return true;
    }

    return false;
}

/**
 * Enforce CSRF validation — sends 403 and exits if invalid.
 * Call this at the top of any state-changing API endpoint.
 */
function enforceCsrf()
{
    if (!validateCsrfToken()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or missing CSRF token. Please refresh the page and try again.'
        ]);
        exit();
    }
}
?>