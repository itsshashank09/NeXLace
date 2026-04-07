<?php
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

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Handle both JSON and FormData inputs
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'application/json') !== false) {
    // JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $receiver_id = $input['receiver_id'] ?? null;
    $message = $input['message'] ?? '';
} else {
    // FormData input (for file attachments)
    $receiver_id = $_POST['receiver_id'] ?? null;
    $message = $_POST['message'] ?? '';
}

$receiver_id = intval($receiver_id);

// At least receiver_id is required. Message can be empty if there's a file.
if (!$receiver_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Receiver ID is required']);
    exit;
}

// Handle file attachment
$attachmentPath = null;
$attachmentName = null;

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['attachment'];

    // Check file size (max 2MB)
    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File size exceeds 2MB limit']);
        exit;
    }

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'text/plain'
    ];

    if (!in_array($mimeType, $allowedMimeTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed types: JPG, PNG, GIF, WEBP, PDF, DOC, DOCX, TXT']);
        exit;
    }

    // Create uploads directory if it doesn't exist
    $uploadDir = '../uploads/messages/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $uniqueName = uniqid() . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $uniqueName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $attachmentPath = 'uploads/messages/' . $uniqueName;
        $attachmentName = $file['name'];
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
        exit;
    }
}

// If no message and no file, return error
if (empty($message) && !$attachmentPath) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please provide a message or attach a file']);
    exit;
}

try {
    $conn = getDB();

    // Check if messages table has attachment columns
    $tableInfo = $conn->query("DESCRIBE messages")->fetchAll(PDO::FETCH_COLUMN);

    // Insert message
    if (in_array('attachment_path', $tableInfo) && in_array('attachment_name', $tableInfo)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, attachment_path, attachment_name, is_read) VALUES (:sender, :receiver, :message, :attachment_path, :attachment_name, 0)");
        $stmt->execute([
            ':sender' => $_SESSION['user_id'],
            ':receiver' => $receiver_id,
            ':message' => $message,
            ':attachment_path' => $attachmentPath,
            ':attachment_name' => $attachmentName
        ]);
    } else {
        // Fallback for tables without attachment columns
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_read) VALUES (:sender, :receiver, :message, 0)");
        $stmt->execute([
            ':sender' => $_SESSION['user_id'],
            ':receiver' => $receiver_id,
            ':message' => $message . ($attachmentPath ? ' [Attachment: ' . $attachmentName . ']' : '')
        ]);
    }

    $messageId = $conn->lastInsertId();

    // Mark sender's message notifications as read since they are interacting with the chat
    $updateNotifStmt = $conn->prepare("
        UPDATE notifications n
        JOIN register r ON r.id = ?
        SET n.is_read = 1 
        WHERE n.user_id = ? AND n.type = 'message' AND n.is_read = 0
          AND (n.title LIKE CONCAT('%', r.Name, '%') OR n.link = CONCAT('messages.php?user_id=', r.id))
    ");
    $updateNotifStmt->execute([$receiver_id, $_SESSION['user_id']]);

    // Get sender's name for the notification
    $senderStmt = $conn->prepare("SELECT Name FROM register WHERE id = ?");
    $senderStmt->execute([$_SESSION['user_id']]);
    $senderInfo = $senderStmt->fetch();
    $senderName = $senderInfo['Name'] ?? 'Someone';

    // Create notification for the receiver
    $notifLink = 'messages.php?user_id=' . $_SESSION['user_id'];
    $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'message', ?, ?, ?, 0, NOW())");
    $notifTitle = "New Message from " . $senderName;
    $notifMessage = !empty($message) ? (strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message) : 'Sent you an attachment';
    $notifStmt->execute([$receiver_id, $notifTitle, $notifMessage, $notifLink]);

    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'attachment' => $attachmentPath ? [
            'path' => $attachmentPath,
            'name' => $attachmentName
        ] : null
    ]);
} catch (Exception $e) {
    error_log("Send Message Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
}
?>