<?php
/**
 * API: Get Invitations
 * Fetches invitations for the logged-in user (both sent and received)
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

$type = $_GET['type'] ?? 'received'; // 'received', 'sent', or 'all'
$status = $_GET['status'] ?? null; // 'pending', 'accepted', 'rejected', or null for all

try {
    $conn = getDB();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $userId = $_SESSION['user_id'];
    $params = [];

    // Base query with user info
    $baseSelect = "
        SELECT 
            i.id,
            i.sender_id,
            i.receiver_id,
            i.work_type,
            i.work_email,
            i.work_details,
            i.status,
            i.created_at,
            i.responded_at,
            sender.Name as sender_name,
            sender.Email as sender_email,
            sender.image as sender_image,
            receiver.Name as receiver_name,
            receiver.Email as receiver_email,
            receiver.image as receiver_image
        FROM invitations i
        JOIN register sender ON i.sender_id = sender.Id
        JOIN register receiver ON i.receiver_id = receiver.Id
    ";

    // Build WHERE clause based on type
    if ($type === 'received') {
        $whereClause = "WHERE i.receiver_id = ?";
        $params[] = $userId;
    } elseif ($type === 'sent') {
        $whereClause = "WHERE i.sender_id = ?";
        $params[] = $userId;
    } else {
        $whereClause = "WHERE (i.sender_id = ? OR i.receiver_id = ?)";
        $params[] = $userId;
        $params[] = $userId;
    }

    // Add status filter if specified
    if ($status && in_array($status, ['pending', 'accepted', 'rejected'])) {
        $whereClause .= " AND i.status = ?";
        $params[] = $status;
    }

    // Complete query
    $sql = $baseSelect . $whereClause . " ORDER BY i.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $invitations = $stmt->fetchAll();

    // Format the response
    $formattedInvitations = array_map(function ($inv) use ($userId) {
        $isReceived = $inv['receiver_id'] == $userId;
        return [
            'id' => (int) $inv['id'],
            'type' => $isReceived ? 'received' : 'sent',
            'work_type' => $inv['work_type'],
            'work_email' => $inv['work_email'],
            'work_details' => $inv['work_details'],
            'status' => $inv['status'],
            'created_at' => $inv['created_at'],
            'responded_at' => $inv['responded_at'],
            'sender' => [
                'id' => (int) $inv['sender_id'],
                'name' => $inv['sender_name'],
                'email' => $inv['sender_email'],
                'image' => $inv['sender_image']
            ],
            'receiver' => [
                'id' => (int) $inv['receiver_id'],
                'name' => $inv['receiver_name'],
                'email' => $inv['receiver_email'],
                'image' => $inv['receiver_image']
            ],
            'other_user' => $isReceived ? [
                'id' => (int) $inv['sender_id'],
                'name' => $inv['sender_name'],
                'email' => $inv['sender_email'],
                'image' => $inv['sender_image']
            ] : [
                'id' => (int) $inv['receiver_id'],
                'name' => $inv['receiver_name'],
                'email' => $inv['receiver_email'],
                'image' => $inv['receiver_image']
            ]
        ];
    }, $invitations);

    echo json_encode([
        'success' => true,
        'count' => count($formattedInvitations),
        'invitations' => $formattedInvitations
    ]);

} catch (Exception $e) {
    error_log("Get Invitations Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch invitations']);
}
?>