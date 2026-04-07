<?php

/**
 * API: Get Conversations
 * Fetches list of conversations with last message and unread count
 * 
 * CRITICAL: This ensures only ONE conversation exists between any user-developer pair.
 * The unique key is MIN(user_id, other_user_id) + MAX(user_id, other_user_id)
 * This means if User A talks to User B, they share ONE conversation regardless of who initiated.
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

try {
    $conn = getDB();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $userId = $_SESSION['user_id'];

    // Track unique user pairs to prevent ANY duplicates
    // Key is the other_user_id - we only show ONE conversation per user pair
    $userConversations = [];

    // STEP 1: Get ALL invitations involving this user (both sent and received)
    // For each unique user pair, we only keep the LATEST invitation
    $invitationsSql = "
        SELECT 
            i.id as invitation_id,
            i.sender_id,
            i.receiver_id,
            CASE 
                WHEN i.sender_id = ? THEN i.receiver_id 
                ELSE i.sender_id 
            END as other_user_id,
            u.Name as user_name,
            u.Email as user_email,
            u.image as user_image,
            i.work_type,
            i.work_details,
            i.status as invitation_status,
            i.created_at as last_message_time,
            i.application_id,
            ja.proposed_rate,
            ja.cover_letter,
            CASE 
                WHEN i.sender_id = ? THEN 'sent' 
                ELSE 'received' 
            END as direction
        FROM invitations i
        JOIN register u ON (
            CASE 
                WHEN i.sender_id = ? THEN i.receiver_id 
                ELSE i.sender_id 
            END = u.Id
        )
        LEFT JOIN job_applications ja ON i.application_id = ja.id
        WHERE (i.sender_id = ? OR i.receiver_id = ?)
        ORDER BY i.created_at DESC
    ";

    $invStmt = $conn->prepare($invitationsSql);
    $invStmt->execute([$userId, $userId, $userId, $userId, $userId]);
    $allInvitations = $invStmt->fetchAll();

    // Process invitations - keep only the LATEST per user pair
    foreach ($allInvitations as $inv) {
        $otherUserId = (int) $inv['other_user_id'];

        // Skip if we already have a conversation with this user (duplicate prevention)
        if (isset($userConversations[$otherUserId])) {
            continue;
        }

        $isMessageRequest = $inv['work_type'] === 'Message Request';
        $isJobApplication = $inv['work_type'] === 'Job Application';
        $isSent = $inv['direction'] === 'sent';

        // Determine conversation type
        if ($isMessageRequest) {
            $type = $isSent ? 'message_request_sent' : 'message_request_received';
            $lastMessage = $isSent
                ? 'Message Request sent: ' . substr($inv['work_details'], 0, 50) . '...'
                : 'Message Request: ' . substr($inv['work_details'], 0, 50) . '...';
        } else if ($isJobApplication) {
            // Job Application from developer to client
            $type = $isSent ? 'invitation_sent' : 'invitation_received';
            $lastMessage = $isSent
                ? 'Application sent: ' . substr($inv['work_details'], 0, 50) . '...'
                : 'New Job Application: ' . substr($inv['work_details'], 0, 50) . '...';
        } else {
            $type = $isSent ? 'invitation_sent' : 'invitation_received';
            $lastMessage = $isSent
                ? 'Invitation sent: ' . $inv['work_type']
                : 'Job Invitation: ' . $inv['work_type'];
        }

        $userConversations[$otherUserId] = [
            'id' => ($isSent ? 'sent_inv_' : 'inv_') . $inv['invitation_id'],
            'invitation_id' => (int) $inv['invitation_id'],
            'user_id' => $otherUserId,
            'user_name' => $inv['user_name'],
            'user_email' => $inv['user_email'],
            'user_image' => $inv['user_image'],
            'last_message' => $lastMessage,
            'last_message_time' => $inv['last_message_time'],
            'unread_count' => (!$isSent && $inv['invitation_status'] === 'pending') ? 1 : 0,
            'type' => $type,
            'invitation_status' => $inv['invitation_status'],
            'work_type' => $inv['work_type'],
            'work_details' => $inv['work_details'],
            'is_message_request' => $isMessageRequest,
            'is_job_application' => $isJobApplication,
            'proposed_rate' => $inv['proposed_rate'] ?? null,
            'cover_letter' => $inv['cover_letter'] ?? null
        ];
    }

    // STEP 2: Get message conversations (for users not already in invitations)
    $messagesSql = "
        SELECT 
            CASE 
                WHEN m.sender_id = ? THEN m.receiver_id 
                ELSE m.sender_id 
            END as other_user_id,
            u.Name as user_name,
            u.Email as user_email,
            u.image as user_image,
            (SELECT message FROM messages m2 
             WHERE (m2.sender_id = ? AND m2.receiver_id = u.Id) 
                OR (m2.sender_id = u.Id AND m2.receiver_id = ?)
             ORDER BY m2.created_at DESC LIMIT 1) as last_message,
            MAX(m.created_at) as last_message_time,
            (SELECT COUNT(*) FROM messages m3
             WHERE m3.sender_id = u.Id AND m3.receiver_id = ? AND m3.is_read = 0) as unread_count
        FROM messages m
        JOIN register u ON (
            CASE 
                WHEN m.sender_id = ? THEN m.receiver_id 
                ELSE m.sender_id 
            END = u.Id
        )
        WHERE m.sender_id = ? OR m.receiver_id = ?
        GROUP BY other_user_id, u.Name, u.Email, u.image
        ORDER BY last_message_time DESC
    ";

    $msgStmt = $conn->prepare($messagesSql);
    $msgStmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId]);
    $messageConversations = $msgStmt->fetchAll();

    // Add message conversations (only if user pair not already added from invitations)
    foreach ($messageConversations as $conv) {
        $otherUserId = (int) $conv['other_user_id'];

        // Skip if we already have a conversation with this user
        if (isset($userConversations[$otherUserId])) {
            // Update the last message time if messages are more recent
            $existingTime = strtotime($userConversations[$otherUserId]['last_message_time']);
            $messageTime = strtotime($conv['last_message_time']);

            if ($messageTime > $existingTime) {
                // Update with more recent message info
                $userConversations[$otherUserId]['last_message'] = $conv['last_message'];
                $userConversations[$otherUserId]['last_message_time'] = $conv['last_message_time'];
                $userConversations[$otherUserId]['unread_count'] = (int) $conv['unread_count'];
            }
            continue;
        }

        $userConversations[$otherUserId] = [
            'id' => 'msg_' . $otherUserId,
            'user_id' => $otherUserId,
            'user_name' => $conv['user_name'],
            'user_email' => $conv['user_email'],
            'user_image' => $conv['user_image'],
            'last_message' => $conv['last_message'],
            'last_message_time' => $conv['last_message_time'],
            'unread_count' => (int) $conv['unread_count'],
            'type' => 'message'
        ];
    }

    // Convert to array and sort by last_message_time
    $allConversations = array_values($userConversations);
    usort($allConversations, function ($a, $b) {
        return strtotime($b['last_message_time']) - strtotime($a['last_message_time']);
    });

    echo json_encode([
        'success' => true,
        'conversations' => $allConversations
    ]);
} catch (Exception $e) {
    error_log("Get Conversations Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch conversations']);
}
