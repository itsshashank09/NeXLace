<?php
session_start();

// Include database connection
require_once __DIR__ . '/../includes/db_helper.php';

// Set response header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/csrf.php';
enforceCsrf();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

try {
    // Initialize database connection
    require_once __DIR__ . '/../includes/db_helper.php';
$conn = getDB();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Handle POST request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Get the posted data
        $headline = isset($_POST['headline']) ? trim($_POST['headline']) : '';
        $bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';
        $imageData = isset($_POST['image']) ? $_POST['image'] : null;
        $removeImage = isset($_POST['removeImage']) && $_POST['removeImage'] === 'true';

        // Validate lengths
        if (strlen($bio) > 500) {
            throw new Exception('Bio must be 500 characters or less');
        }

        if (strlen($headline) > 500) {
            throw new Exception('Headline must be 500 characters or less');
        }

        // Handle image
        $imagePath = null;

        if ($removeImage) {
            // Remove image - set to empty
            $imagePath = '';
        } elseif ($imageData && strpos($imageData, 'data:image') === 0) {
            // It's a base64 image, save it
            $uploadDir = __DIR__ . '/../uploads/profile_images/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Extract base64 data
            if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
                $imageData = substr($imageData, strpos($imageData, ',') + 1);
                $type = strtolower($type[1]);

                if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    throw new Exception('Invalid image type');
                }

                $imageData = base64_decode($imageData);
                if ($imageData === false) {
                    throw new Exception('Base64 decode failed');
                }

                // Generate unique filename
                $userId = $_SESSION['user_id'];
                $fileName = 'profile_' . $userId . '_' . time() . '.' . $type;
                $fullPath = $uploadDir . $fileName;
                $imagePath = 'uploads/profile_images/' . $fileName; // Web-accessible path for database

                // Get current image to delete old one
                $stmt = $conn->prepare("SELECT `image` FROM register WHERE id = :id");
                $stmt->execute([':id' => $_SESSION['user_id']]);
                $user = $stmt->fetch();

                // Delete old file if exists
                if ($user && !empty($user['image'])) {
                    $oldFilePath = __DIR__ . '/../' . $user['image'];
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                // Save new image
                if (!file_put_contents($fullPath, $imageData)) {
                    throw new Exception('Failed to save image');
                }
            }
        }

        // Build update query
        if ($imagePath !== null) {
            // Update with image
            $stmt = $conn->prepare("UPDATE register SET `Professional Headline` = :headline, `Bio` = :bio, `image` = :image WHERE id = :id");
            $stmt->execute([
                ':headline' => $headline,
                ':bio' => $bio,
                ':image' => $imagePath,
                ':id' => $_SESSION['user_id']
            ]);
        } else {
            // Update without image
            $stmt = $conn->prepare("UPDATE register SET `Professional Headline` = :headline, `Bio` = :bio WHERE id = :id");
            $stmt->execute([
                ':headline' => $headline,
                ':bio' => $bio,
                ':id' => $_SESSION['user_id']
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Profile saved successfully!'
        ]);
        exit();
    }

    throw new Exception('Invalid request method');

} catch (PDOException $e) {
    error_log("Save Profile DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while saving profile.']);
} catch (Exception $e) {
    error_log("Save Profile Operation Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>