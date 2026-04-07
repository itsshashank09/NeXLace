<?php
/**
 * API: Get Messages
 * Fetches messages between current user and another user
 */

session_start();
require_once '../config/database.php';
require_once '../includes/db_helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$other_user_id = $_GET['user_id'] ?? null;

if (!$other_user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User ID is required']);
    exit;
}

try {
    $conn = getDB();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $userId = $_SESSION['user_id'];

    // Get other user info
    $userStmt = $conn->prepare("SELECT Id, Name, Email, image FROM register WHERE Id = ?");
    $userStmt->execute([$other_user_id]);
    $otherUser = $userStmt->fetch();

    if (!$otherUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    // Get messages between the two users
    $msgStmt = $conn->prepare("
        SELECT 
            m.id,
            m.sender_id,
            m.receiver_id,
            m.message,
            m.attachment_path,
            m.attachment_name,
            m.created_at,
            m.is_read,
            sender.Name as sender_name,
            sender.image as sender_image
        FROM messages m
        JOIN register sender ON m.sender_id = sender.Id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $msgStmt->execute([$userId, $other_user_id, $other_user_id, $userId]);
    $messages = $msgStmt->fetchAll();

    // Mark messages as read
    $updateStmt = $conn->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $updateStmt->execute([$other_user_id, $userId]);

    // Mark message notifications as read for this specific conversation
    $updateNotifStmt = $conn->prepare("
        UPDATE notifications n
        JOIN register r ON r.id = ?
        SET n.is_read = 1 
        WHERE n.user_id = ? AND n.type = 'message' AND n.is_read = 0
          AND (n.title LIKE CONCAT('%', r.Name, '%') OR n.link = CONCAT('messages.php?user_id=', r.id))
    ");
    $updateNotifStmt->execute([$other_user_id, $userId]);

    // Format messages
    $formattedMessages = array_map(function ($msg) use ($userId) {
        return [
            'id' => (int) $msg['id'],
            'sender_id' => (int) $msg['sender_id'],
            'receiver_id' => (int) $msg['receiver_id'],
            'message' => $msg['message'],
            'attachment_path' => $msg['attachment_path'] ?? null,
            'attachment_name' => $msg['attachment_name'] ?? null,
            'created_at' => $msg['created_at'],
            'is_read' => (bool) $msg['is_read'],
            'is_mine' => $msg['sender_id'] == $userId,
            'sender_name' => $msg['sender_name'],
            'sender_image' => $msg['sender_image']
        ];
    }, $messages);

    echo json_encode([
        'success' => true,
        'other_user' => [
            'id' => (int) $otherUser['Id'],
            'name' => $otherUser['Name'],
            'email' => $otherUser['Email'],
            'image' => $otherUser['image']
        ],
        'messages' => $formattedMessages
    ]);

} catch (Exception $e) {
    error_log("Get Messages Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch messages']);
}
?>