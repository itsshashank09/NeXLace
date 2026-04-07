<?php

/**
 * API: Respond to Invitation
 * Accept or reject job invitations
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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

$invitation_id = intval($input['invitation_id'] ?? 0);
$response = $input['response'] ?? null; // 'accept' or 'reject'

// Validation
if (!$invitation_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invitation ID is required']);
    exit;
}

if (!$response || !in_array($response, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid response. Must be "accept" or "reject"']);
    exit;
}

try {
    $conn = getDB();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get the invitation and verify the user is the receiver
    $stmt = $conn->prepare("
        SELECT i.*, 
               sender.Name as sender_name, 
               sender.Email as sender_email,
               sender.image as sender_image
        FROM invitations i
        JOIN register sender ON i.sender_id = sender.Id
        WHERE i.id = ? AND i.receiver_id = ?
    ");
    $stmt->execute([$invitation_id, $_SESSION['user_id']]);
    $invitation = $stmt->fetch();

    if (!$invitation) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Invitation not found or you are not authorized']);
        exit;
    }

    if ($invitation['status'] !== 'pending') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'This invitation has already been ' . $invitation['status']
        ]);
        exit;
    }

    // Update the invitation status
    $newStatus = $response === 'accept' ? 'accepted' : 'rejected';

    $updateStmt = $conn->prepare("
        UPDATE invitations 
        SET status = ?, responded_at = NOW() 
        WHERE id = ?
    ");
    $updateStmt->execute([$newStatus, $invitation_id]);

    // Check if this is a job application (has application_id)
    $isJobApplication = $invitation['work_type'] === 'Job Application';
    $applicationId = $invitation['application_id'] ?? null;

    // If this is a job application, also update the job_applications table
    if ($applicationId) {
        try {
            $appUpdateStmt = $conn->prepare("
                UPDATE job_applications 
                SET status = ? 
                WHERE id = ?
            ");
            $appUpdateStmt->execute([$newStatus, $applicationId]);
        } catch (Exception $e) {
            // Log but don't fail - the application table might not exist or column might not match
            error_log("Failed to update job_applications: " . $e->getMessage());
        }
    }

    // If accepted, create an initial message to start the conversation
    if ($response === 'accept') {
        if ($isJobApplication) {
            // For job applications - publisher (receiver) accepts developer's (sender's) application
            $initialMessage = "Hi! I've reviewed your job application and I'd like to move forward. Let's discuss the project details and next steps!";
        } else {
            // For regular invitations - developer (receiver) accepts client's (sender's) invitation
            $initialMessage = "Hi! I've accepted your job invitation for \"" .
                $invitation['work_type'] . "\". I'm excited to discuss the project details with you. Let's connect!";
        }

        $msgStmt = $conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, message) 
            VALUES (?, ?, ?)
        ");
        $msgStmt->execute([
            $_SESSION['user_id'],
            $invitation['sender_id'],
            $initialMessage
        ]);
    }

    // Generate appropriate response message
    if ($isJobApplication) {
        $responseMessage = $response === 'accept'
            ? 'Application accepted! You can now chat with ' . $invitation['sender_name'] . '.'
            : 'You have declined the application from ' . $invitation['sender_name'] . '.';
    } else {
        $responseMessage = $response === 'accept'
            ? 'Invitation accepted! You can now chat with ' . $invitation['sender_name'] . '.'
            : 'You have declined the invitation from ' . $invitation['sender_name'] . '.';
    }

    echo json_encode([
        'success' => true,
        'message' => $responseMessage,
        'status' => $newStatus,
        'invitation_id' => (int) $invitation_id,
        'sender_id' => (int) $invitation['sender_id'],
        'sender_name' => $invitation['sender_name'],
        'sender_email' => $invitation['sender_email'],
        'sender_image' => $invitation['sender_image'],
        'work_type' => $invitation['work_type'],
        'is_job_application' => $isJobApplication
    ]);
} catch (Exception $e) {
    error_log("Respond Invitation Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to respond to invitation']);
}
