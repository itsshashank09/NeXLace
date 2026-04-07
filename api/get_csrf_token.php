<?php
/**
 * API: Get CSRF Token
 * Returns a CSRF token for the current session.
 * Frontend pages should call this to get a token before making state-changing requests.
 */

session_start();
require_once '../config/csrf.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$token = generateCsrfToken();

echo json_encode([
    'success' => true,
    'csrf_token' => $token
]);
?>