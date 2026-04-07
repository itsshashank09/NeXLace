<?php

/**
 * API: Send Message Request
 * Sends a message request from user to developer
 * 
 * CRITICAL RULE: There must be ONLY ONE conversation between any user pair.
 * 
 * Behavior:
 * 1. If there's already an accepted invitation/request between users → send as regular message
 * 2. If there's a pending request in EITHER direction → inform user to wait/respond
 * 3. If there's a rejected request → update it to pending with new message
 * 4. If no existing conversation → create new message request
 */

session_start();
require_once '../config/database.php';
require_once '../includes/db_helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/csrf.php';
enforceCsrf();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Please log in']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

$receiver_id = intval($input['receiver_id'] ?? 0);
$subject = trim($input['subject'] ?? 'Message Request');
$message = trim($input['message'] ?? '');

// Validation
if (!$receiver_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Developer ID is required']);
    exit;
}

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

// Cannot send message request to yourself
if ($receiver_id == $_SESSION['user_id']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'You cannot send a message request to yourself']);
    exit;
}

try {
    $conn = getDB();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Check if receiver exists
    $checkStmt = $conn->prepare("SELECT Id, Name FROM register WHERE Id = ?");
    $checkStmt->execute([$receiver_id]);
    $receiver = $checkStmt->fetch();

    if (!$receiver) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Developer not found']);
        exit;
    }

    $userId = $_SESSION['user_id'];

    // STEP 1: Check for ANY accepted invitation/request between the two users (in EITHER direction)
    $acceptedStmt = $conn->prepare("
        SELECT id, work_type FROM invitations 
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        AND status = 'accepted'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $acceptedStmt->execute([$userId, $receiver_id, $receiver_id, $userId]);
    $acceptedInvitation = $acceptedStmt->fetch();

    if ($acceptedInvitation) {
        // There's already an accepted connection - send as a regular message
        $msgStmt = $conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, created_at) 
            VALUES (:sender, :receiver, :message, NOW())
        ");

        $msgStmt->execute([
            ':sender' => $userId,
            ':receiver' => $receiver_id,
            ':message' => $message
        ]);

        $messageId = $conn->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Message sent to ' . $receiver['Name'],
            'message_id' => $messageId,
            'type' => 'message',
            'info' => 'Sent as regular message to existing conversation'
        ]);
        exit;
    }

    // STEP 2: Check for existing messages between the users (conversation without invitation)
    $existingMsgStmt = $conn->prepare("
        SELECT id FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        LIMIT 1
    ");
    $existingMsgStmt->execute([$userId, $receiver_id, $receiver_id, $userId]);
    $existingMessage = $existingMsgStmt->fetch();

    if ($existingMessage) {
        // There's already a message thread - send as a regular message
        $msgStmt = $conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, created_at) 
            VALUES (:sender, :receiver, :message, NOW())
        ");

        $msgStmt->execute([
            ':sender' => $userId,
            ':receiver' => $receiver_id,
            ':message' => $message
        ]);

        $messageId = $conn->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Message sent to ' . $receiver['Name'],
            'message_id' => $messageId,
            'type' => 'message',
            'info' => 'Sent as regular message to existing conversation'
        ]);
        exit;
    }

    // STEP 3: Check for ANY pending invitation/request between users (in EITHER direction)
    $pendingStmt = $conn->prepare("
        SELECT id, status, work_type, sender_id FROM invitations 
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        AND status = 'pending'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $pendingStmt->execute([$userId, $receiver_id, $receiver_id, $userId]);
    $pendingRequest = $pendingStmt->fetch();

    if ($pendingRequest) {
        if ($pendingRequest['sender_id'] == $userId) {
            // User already has a pending request to this developer
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'You already have a pending request to this developer. Please wait for their response.'
            ]);
        } else {
            // Developer has a pending request to the user - user should respond first
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'This developer has sent you a pending request. Please check your Messages to respond first.'
            ]);
        }
        exit;
    }

    // STEP 4: Check for existing rejected invitation/request from this user to this developer
    // Update it instead of creating a duplicate
    $rejectedStmt = $conn->prepare("
        SELECT id FROM invitations 
        WHERE sender_id = ? AND receiver_id = ? AND status = 'rejected'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $rejectedStmt->execute([$userId, $receiver_id]);
    $rejectedRequest = $rejectedStmt->fetch();

    if ($rejectedRequest) {
        // Update the existing rejected request to pending with new message
        $updateStmt = $conn->prepare("
            UPDATE invitations 
            SET status = 'pending', work_type = 'Message Request', work_email = :work_email, work_details = :work_details, created_at = NOW(), responded_at = NULL
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':work_email' => $subject,
            ':work_details' => $message,
            ':id' => $rejectedRequest['id']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Message request resent successfully to ' . $receiver['Name'],
            'request_id' => $rejectedRequest['id'],
            'type' => 'request',
            'info' => 'Previous request updated - awaiting response'
        ]);
        exit;
    }

    // STEP 5: No existing conversation - create new message request
    $stmt = $conn->prepare("
        INSERT INTO invitations (sender_id, receiver_id, work_type, work_email, work_details) 
        VALUES (:sender, :receiver, :work_type, :work_email, :work_details)
    ");

    $stmt->execute([
        ':sender' => $userId,
        ':receiver' => $receiver_id,
        ':work_type' => 'Message Request',
        ':work_email' => $subject,
        ':work_details' => $message
    ]);

    $requestId = $conn->lastInsertId();

    // Get sender's name for the notification
    $senderStmt = $conn->prepare("SELECT Name FROM register WHERE id = ?");
    $senderStmt->execute([$userId]);
    $senderInfo = $senderStmt->fetch();
    $senderName = $senderInfo['Name'] ?? 'Someone';

    // Create notification for the receiver
    $notifLink = 'messages.php?user_id=' . $userId;
    $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'message', ?, ?, ?, 0, NOW())");
    $notifTitle = "New Message Request from " . $senderName;
    $notifMessage = strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message;
    $notifStmt->execute([$receiver_id, $notifTitle, $notifMessage, $notifLink]);

    echo json_encode([
        'success' => true,
        'message' => 'Message request sent successfully to ' . $receiver['Name'],
        'request_id' => $requestId,
        'type' => 'request',
        'info' => 'New message request created - awaiting response'
    ]);
} catch (Exception $e) {
    error_log("Send Message Request Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to send message request. Please try again.']);
}
