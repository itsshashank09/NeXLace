<?php

/**
 * API: Send Job Invitation
 * Sends a job invitation from client to developer
 * 
 * CRITICAL RULE: There must be ONLY ONE conversation between any user pair.
 * Before creating any invitation, we check for existing conversations in BOTH directions.
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
$work_type = trim($input['work_type'] ?? '');
$work_email = trim($input['work_email'] ?? '');
$work_details = trim($input['work_details'] ?? '');

// Validation
if (!$receiver_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Developer ID is required']);
    exit;
}

if (empty($work_type)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Work type is required']);
    exit;
}

if (empty($work_details)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Work details are required']);
    exit;
}

// Cannot send invitation to yourself
if ($receiver_id == $_SESSION['user_id']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'You cannot send an invitation to yourself']);
    exit;
}

try {
    $conn = getDB();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $userId = $_SESSION['user_id'];

    // Check if receiver exists
    $checkStmt = $conn->prepare("SELECT Id, Name FROM register WHERE Id = ?");
    $checkStmt->execute([$receiver_id]);
    $receiver = $checkStmt->fetch();

    if (!$receiver) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Developer not found']);
        exit;
    }

    // STEP 1: Check for ANY accepted invitation between the two users (in EITHER direction)
    // If accepted, they already have a conversation - redirect to chat
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
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'You already have an active conversation with ' . $receiver['Name'] . '. Go to Messages to chat with them.',
            'has_conversation' => true
        ]);
        exit;
    }

    // STEP 2: Check for ANY pending invitation between the two users (in EITHER direction)
    // This prevents duplicate pending requests
    $pendingStmt = $conn->prepare("
        SELECT id, work_type, sender_id FROM invitations 
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        AND status = 'pending'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $pendingStmt->execute([$userId, $receiver_id, $receiver_id, $userId]);
    $pendingInvitation = $pendingStmt->fetch();

    if ($pendingInvitation) {
        if ($pendingInvitation['sender_id'] == $userId) {
            // User already sent a pending invitation
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'You already have a pending invitation to this developer for "' . $pendingInvitation['work_type'] . '". Please wait for their response.'
            ]);
        } else {
            // Developer sent a pending invitation to user - user should respond to that first
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'This developer has already sent you a pending request. Please check your Messages to respond.'
            ]);
        }
        exit;
    }

    // STEP 3: Check for existing rejected invitation with SAME work_type from this user
    // Allow re-sending by updating the existing rejected invitation
    $rejectedStmt = $conn->prepare("
        SELECT id FROM invitations 
        WHERE sender_id = ? AND receiver_id = ? AND work_type = ? AND status = 'rejected'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $rejectedStmt->execute([$userId, $receiver_id, $work_type]);
    $rejectedInvitation = $rejectedStmt->fetch();

    if ($rejectedInvitation) {
        // Update the existing rejected invitation to pending with new details
        $updateStmt = $conn->prepare("
            UPDATE invitations 
            SET status = 'pending', work_email = :work_email, work_details = :work_details, created_at = NOW(), responded_at = NULL
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':work_email' => $work_email,
            ':work_details' => $work_details,
            ':id' => $rejectedInvitation['id']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Invitation resent successfully to ' . $receiver['Name'],
            'invitation_id' => $rejectedInvitation['id'],
            'info' => 'Previous rejected invitation updated - awaiting response'
        ]);
        exit;
    }

    // STEP 4: Check if there's ANY rejected invitation between users (different work_type)
    // If so, update that one instead of creating a new entry
    $anyRejectedStmt = $conn->prepare("
        SELECT id FROM invitations 
        WHERE sender_id = ? AND receiver_id = ? AND status = 'rejected'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $anyRejectedStmt->execute([$userId, $receiver_id]);
    $anyRejectedInvitation = $anyRejectedStmt->fetch();

    if ($anyRejectedInvitation) {
        // Update the existing rejected invitation with new work_type and details
        $updateStmt = $conn->prepare("
            UPDATE invitations 
            SET status = 'pending', work_type = :work_type, work_email = :work_email, work_details = :work_details, created_at = NOW(), responded_at = NULL
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':work_type' => $work_type,
            ':work_email' => $work_email,
            ':work_details' => $work_details,
            ':id' => $anyRejectedInvitation['id']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Invitation sent successfully to ' . $receiver['Name'],
            'invitation_id' => $anyRejectedInvitation['id'],
            'info' => 'Previous invitation updated - awaiting response'
        ]);
        exit;
    }

    // STEP 5: No existing conversation - create new invitation
    $stmt = $conn->prepare("
        INSERT INTO invitations (sender_id, receiver_id, work_type, work_email, work_details) 
        VALUES (:sender, :receiver, :work_type, :work_email, :work_details)
    ");

    $stmt->execute([
        ':sender' => $userId,
        ':receiver' => $receiver_id,
        ':work_type' => $work_type,
        ':work_email' => $work_email,
        ':work_details' => $work_details
    ]);

    $invitationId = $conn->lastInsertId();

    // Get sender's name for the notification
    $senderStmt = $conn->prepare("SELECT Name FROM register WHERE id = ?");
    $senderStmt->execute([$userId]);
    $senderInfo = $senderStmt->fetch();
    $senderName = $senderInfo['Name'] ?? 'Someone';

    // Create notification for the receiver
    $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'invitation', ?, ?, 'messages.php', 0, NOW())");
    $notifTitle = "New Job Invitation from " . $senderName;
    $notifMessage = "You received an invitation for: " . $work_type;
    $notifStmt->execute([$receiver_id, $notifTitle, $notifMessage]);

    echo json_encode([
        'success' => true,
        'message' => 'Invitation sent successfully to ' . $receiver['Name'],
        'invitation_id' => $invitationId
    ]);
} catch (Exception $e) {
    error_log("Send Invitation Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to send invitation. Please try again.']);
}
