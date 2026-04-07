<?php
/**
 * API endpoint to handle liking/unliking jobs
 * POST: Toggle like status for a job
 * GET: Get liked jobs for current user
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/csrf.php';
enforceCsrf();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to like jobs']);
    exit();
}

require_once '../config/database.php';

require_once __DIR__ . '/../includes/db_helper.php';
$conn = getDB();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Create liked_jobs table if it doesn't exist
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS liked_jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        job_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (user_id, job_id),
        INDEX idx_user (user_id),
        INDEX idx_job (job_id)
    )");
} catch (Exception $e) {
    // Table might already exist, ignore
}

// Handle POST request - Toggle like
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $job_id = intval($data['job_id'] ?? 0);

        if ($job_id <= 0) {
            throw new Exception('Invalid job ID');
        }

        // Check if job exists
        $jobStmt = $conn->prepare("SELECT id FROM post_jobs WHERE id = ?");
        $jobStmt->execute([$job_id]);
        if (!$jobStmt->fetch()) {
            throw new Exception('Job not found');
        }

        // Check if already liked
        $checkStmt = $conn->prepare("SELECT id FROM liked_jobs WHERE user_id = ? AND job_id = ?");
        $checkStmt->execute([$user_id, $job_id]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            // Unlike - remove the like
            $deleteStmt = $conn->prepare("DELETE FROM liked_jobs WHERE user_id = ? AND job_id = ?");
            $deleteStmt->execute([$user_id, $job_id]);

            echo json_encode([
                'success' => true,
                'liked' => false,
                'message' => 'Job removed from liked jobs'
            ]);
        } else {
            // Like - add the like
            $insertStmt = $conn->prepare("INSERT INTO liked_jobs (user_id, job_id) VALUES (?, ?)");
            $insertStmt->execute([$user_id, $job_id]);

            echo json_encode([
                'success' => true,
                'liked' => true,
                'message' => 'Job added to liked jobs'
            ]);
        }
    } catch (PDOException $e) {
        error_log("Like Job Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred.']);
    } catch (Exception $e) {
        error_log("Like Job Operation Error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    exit();
}

// Handle GET request - Get liked jobs
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $conn->prepare("
            SELECT lj.job_id, lj.created_at as liked_at,
                   pj.job_title, pj.job_details, pj.skills_required, pj.estimated_budget,
                   pj.project_timeline, pj.category, pj.project_type, pj.experience_level
            FROM liked_jobs lj
            JOIN post_jobs pj ON lj.job_id = pj.id
            WHERE lj.user_id = ?
            ORDER BY lj.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $likedJobs = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'count' => count($likedJobs),
            'jobs' => $likedJobs
        ]);
    } catch (Exception $e) {
        error_log("Get Liked Jobs Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load liked jobs.']);
    }
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
