<?php
/**
 * API: Get Notifications
 * Fetches notifications for the logged-in user
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

$filter = $_GET['filter'] ?? 'all'; // 'all' or 'unread'

try {
    $conn = getDB();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $userId = $_SESSION['user_id'];
    $params = [':user_id' => $userId];

    // Base query
    $sql = "SELECT id, title, message, type, is_read, link, created_at 
            FROM notifications 
            WHERE user_id = :user_id";

    // Add filter for unread only
    if ($filter === 'unread') {
        $sql .= " AND is_read = 0";
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();

    // Get unread count
    $countStmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = :user_id AND is_read = 0");
    $countStmt->execute([':user_id' => $userId]);
    $unreadCount = $countStmt->fetch()['unread_count'];

    // Format notifications with relative time
    $formattedNotifications = array_map(function ($notif) {
        $createdAt = new DateTime($notif['created_at']);
        $now = new DateTime();
        $diff = $now->diff($createdAt);

        if ($diff->days == 0) {
            if ($diff->h == 0) {
                $timeAgo = $diff->i <= 1 ? 'Just now' : $diff->i . ' minutes ago';
            } else {
                $timeAgo = $diff->h == 1 ? '1 hour ago' : $diff->h . ' hours ago';
            }
        } elseif ($diff->days == 1) {
            $timeAgo = '1 day ago';
        } else {
            $timeAgo = $diff->days . ' days ago';
        }

        return [
            'id' => (int) $notif['id'],
            'title' => $notif['title'],
            'message' => $notif['message'],
            'type' => $notif['type'],
            'is_read' => (bool) $notif['is_read'],
            'link' => $notif['link'],
            'created_at' => $notif['created_at'],
            'time_ago' => $timeAgo
        ];
    }, $notifications);

    echo json_encode([
        'success' => true,
        'count' => count($formattedNotifications),
        'unread_count' => (int) $unreadCount,
        'notifications' => $formattedNotifications
    ]);

} catch (Exception $e) {
    error_log("Get Notifications Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch notifications']);
}
?>