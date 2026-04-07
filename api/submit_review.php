<?php
require_once '../includes/auth_helper.php';
requireAuth();
require_once '../config/database.php';
require_once '../includes/db_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit();
}

$developer_id = $input['developer_id'] ?? null;
$rating = $input['rating'] ?? null;
$comment = $input['comment'] ?? '';

if (!$developer_id || !$rating) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$reviewer_id = $_SESSION['user_id'] ?? null;
if (!$reviewer_id) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$reviewer_name = $_SESSION['name'] ?? 'User';

try {
    $conn = getDB();

    // Insert review
    $stmt = $conn->prepare("INSERT INTO reviews (reviewer_id, reviewee_id, rating, review_text) VALUES (?, ?, ?, ?)");
    $stmt->execute([$reviewer_id, $developer_id, $rating, $comment]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error saving review: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
