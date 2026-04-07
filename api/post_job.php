<?php

/**
 * API Endpoint: Post a New Job
 * Saves job posting to the post_jobs table in nexlace database
 */

session_start();

// CORS Headers
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
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once '../config/database.php';

// Get form data
$job_title = trim($_POST['job_title'] ?? '');
$job_details = trim($_POST['job_details'] ?? '');
$skills_required = trim($_POST['skills_required'] ?? '');
$estimated_budget = intval($_POST['estimated_budget'] ?? 0);
$project_timeline = trim($_POST['project_timeline'] ?? '');
$category = trim($_POST['category'] ?? 'Web Development');
$project_type = trim($_POST['project_type'] ?? 'Fixed Price');
$experience_level = trim($_POST['experience_level'] ?? 'Intermediate');

// Validate required fields
if (empty($job_title) || empty($job_details)) {
    echo json_encode(['success' => false, 'message' => 'Job title and details are required']);
    exit();
}

try {
    require_once __DIR__ . '/../includes/db_helper.php';
    $conn = getDB();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Ensure table exists
    $createTableSql = "CREATE TABLE IF NOT EXISTS post_jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_title VARCHAR(255) NOT NULL,
        job_details TEXT NOT NULL,
        skills_required TEXT,
        estimated_budget INT,
        project_timeline VARCHAR(100),
        category VARCHAR(100) DEFAULT 'Web Development',
        project_type VARCHAR(50) DEFAULT 'Fixed Price',
        experience_level VARCHAR(50) DEFAULT 'Intermediate',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->exec($createTableSql);

    // Ensure columns exist (Auto-fix)
    $columnsToCheck = [
        'user_id' => "INT NOT NULL DEFAULT 0",
        'job_title' => "VARCHAR(255) NOT NULL",
        'job_details' => "TEXT NOT NULL",
        'skills_required' => "TEXT",
        'estimated_budget' => "INT",
        'project_timeline' => "VARCHAR(100)",
        'category' => "VARCHAR(100) DEFAULT 'Web Development'",
        'project_type' => "VARCHAR(50) DEFAULT 'Fixed Price'",
        'experience_level' => "VARCHAR(50) DEFAULT 'Intermediate'",
        'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($columnsToCheck as $colName => $colDef) {
        try {
            $stmt = $conn->prepare("SHOW COLUMNS FROM post_jobs LIKE ?");
            $stmt->execute([$colName]);
            if ($stmt->rowCount() == 0) {
                $conn->exec("ALTER TABLE post_jobs ADD COLUMN $colName $colDef");
            }
        } catch (Exception $e) {
            // Ignore error or log it
        }
    }

    // Get user ID from session
    $user_id = $_SESSION['user_id'];

    // Insert with all columns
    $sql = "INSERT INTO post_jobs (user_id, job_title, job_details, skills_required, estimated_budget, project_timeline, category, project_type, experience_level) 
            VALUES (:user_id, :job_title, :job_details, :skills_required, :estimated_budget, :project_timeline, :category, :project_type, :experience_level)";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':job_title', $job_title);
    $stmt->bindParam(':job_details', $job_details);
    $stmt->bindParam(':skills_required', $skills_required);
    $stmt->bindParam(':estimated_budget', $estimated_budget, PDO::PARAM_INT);
    $stmt->bindParam(':project_timeline', $project_timeline);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':project_type', $project_type);
    $stmt->bindParam(':experience_level', $experience_level);

    if ($stmt->execute()) {
        // Return success JSON
        echo json_encode(['success' => true, 'message' => 'Job posted successfully']);
        exit();
    } else {
        throw new Exception('Failed to insert job');
    }
} catch (Exception $e) {
    error_log("Post Job Error: " . $e->getMessage());
    // Return error JSON
    echo json_encode(['success' => false, 'message' => 'Failed to post job. Please try again.']);
    exit();
}
