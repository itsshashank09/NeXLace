<?php

/**
 * API endpoint to handle job applications
 * POST: Submit a job application
 */

session_start();
header('Content-Type: application/json');

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
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
    echo json_encode(['success' => false, 'message' => 'Please login to apply for jobs']);
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

// Handle POST request - Submit application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        $job_id = intval($data['job_id'] ?? 0);
        $cover_letter = trim($data['cover_letter'] ?? '');
        $proposed_rate = floatval($data['proposed_rate'] ?? 0);
        $user_id = $_SESSION['user_id'];

        if ($job_id <= 0) {
            throw new Exception('Invalid job ID');
        }

        // Check if job exists and get job details
        $jobStmt = $conn->prepare("SELECT id, job_title, user_id as client_id FROM post_jobs WHERE id = ?");
        $jobStmt->execute([$job_id]);
        $job = $jobStmt->fetch();

        if (!$job) {
            throw new Exception('Job not found');
        }

        // Check if user is the job poster (can't apply to own job)
        if ($job['client_id'] == $user_id) {
            throw new Exception('You cannot apply to your own job posting');
        }

        // Create job_applications table if it doesn't exist
        $conn->exec("CREATE TABLE IF NOT EXISTS job_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            developer_id INT NOT NULL,
            client_id INT NOT NULL,
            cover_letter TEXT,
            proposed_rate DECIMAL(10,2),
            status ENUM('pending', 'accepted', 'rejected', 'withdrawn') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_job (job_id),
            INDEX idx_developer (developer_id),
            INDEX idx_client (client_id)
        )");

        // Auto-fix columns if they are missing (schema evolution)
        $columnsToCheck = [
            'developer_id' => "INT NOT NULL",
            'client_id' => "INT NOT NULL DEFAULT 0",
            'proposed_rate' => "DECIMAL(10,2) DEFAULT 0.00"
        ];

        foreach ($columnsToCheck as $colName => $colDef) {
            try {
                $stmt = $conn->prepare("SHOW COLUMNS FROM job_applications LIKE ?");
                $stmt->execute([$colName]);
                if ($stmt->rowCount() == 0) {
                    // Start transaction for safety? No, DDL implicitly commits.
                    // We can't parameterize column names in ALTER TABLE, but $colName is from our hardcoded array keys (allow-list)
                    $conn->exec("ALTER TABLE job_applications ADD COLUMN $colName $colDef");
                }
            } catch (Exception $e) {
                // Ignore
            }
        }

        // Check if already applied
        $checkStmt = $conn->prepare("SELECT id, status FROM job_applications WHERE job_id = ? AND developer_id = ?");
        $checkStmt->execute([$job_id, $user_id]);
        $existingApplication = $checkStmt->fetch();

        if ($existingApplication) {
            // If previous application was rejected/declined, allow re-applying by updating the existing record
            if ($existingApplication['status'] === 'rejected') {
                // Update existing application to pending
                $updateAppStmt = $conn->prepare("
                    UPDATE job_applications 
                    SET status = 'pending', 
                        cover_letter = ?, 
                        proposed_rate = ?,
                        created_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateAppStmt->execute([$cover_letter, $proposed_rate, $existingApplication['id']]);
                $applicationId = $existingApplication['id'];

                // Continue to create/update invitation and notification below
            } elseif ($existingApplication['status'] === 'pending') {
                throw new Exception('You have already applied for this job. Please wait for a response.');
            } elseif ($existingApplication['status'] === 'accepted') {
                throw new Exception('Your application for this job has already been accepted.');
            } else {
                throw new Exception('You have already applied for this job');
            }
        } else {
            // No existing application - will insert new one below
            $applicationId = null;
        }

        // Get developer profile info
        $devStmt = $conn->prepare("SELECT id, title, rate FROM developers WHERE user_id = ?");
        $devStmt->execute([$user_id]);
        $developer = $devStmt->fetch();

        if (!$developer) {
            throw new Exception('Please create a developer profile first to apply for jobs');
        }

        // Use developer's rate if no proposed rate provided
        if ($proposed_rate <= 0) {
            $proposed_rate = $developer['rate'] ?? 0;
        }

        // Insert new application (only if not re-applying to a rejected application)
        if ($applicationId === null) {
            $insertStmt = $conn->prepare("INSERT INTO job_applications (job_id, developer_id, client_id, cover_letter, proposed_rate) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->execute([$job_id, $user_id, $job['client_id'], $cover_letter, $proposed_rate]);
            $applicationId = $conn->lastInsertId();
        }

        // Get developer name for notification
        $nameStmt = $conn->prepare("SELECT Name, Email FROM register WHERE id = ?");
        $nameStmt->execute([$user_id]);
        $userInfo = $nameStmt->fetch();
        $developerName = $userInfo['Name'] ?? 'A developer';
        $developerEmail = $userInfo['Email'] ?? '';

        // Check if an invitation already exists between developer and client
        $existingInvStmt = $conn->prepare("
            SELECT id, status FROM invitations 
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $existingInvStmt->execute([$user_id, $job['client_id'], $job['client_id'], $user_id]);
        $existingInv = $existingInvStmt->fetch();

        // Prepare the work details with job application info
        $workDetails = "Hi! I'm interested in your job posting: \"{$job['job_title']}\"\n\n";
        $workDetails .= "Proposed Rate: ₹" . number_format($proposed_rate) . "\n\n";
        if (!empty($cover_letter)) {
            $workDetails .= "Cover Letter:\n" . $cover_letter;
        }

        if ($existingInv) {
            // Update existing invitation if it's rejected, otherwise skip
            if ($existingInv['status'] === 'rejected') {
                $updateInvStmt = $conn->prepare("
                    UPDATE invitations 
                    SET status = 'pending', 
                        work_type = :work_type, 
                        work_email = :work_email, 
                        work_details = :work_details,
                        job_id = :job_id,
                        application_id = :application_id,
                        created_at = NOW(),
                        responded_at = NULL
                    WHERE id = :id
                ");
                $updateInvStmt->execute([
                    ':work_type' => 'Job Application',
                    ':work_email' => $developerEmail,
                    ':work_details' => $workDetails,
                    ':job_id' => $job_id,
                    ':application_id' => $applicationId,
                    ':id' => $existingInv['id']
                ]);
            }
            // If accepted or pending, they already have a conversation
        } else {
            // Ensure invitations table has job_id and application_id columns (Robust Fix)
            $invColumns = ['job_id' => 'INT DEFAULT NULL', 'application_id' => 'INT DEFAULT NULL'];
            foreach ($invColumns as $colName => $colDef) {
                try {
                    $stmt = $conn->prepare("SHOW COLUMNS FROM invitations LIKE ?");
                    $stmt->execute([$colName]);
                    if ($stmt->rowCount() == 0) {
                        $conn->exec("ALTER TABLE invitations ADD COLUMN $colName $colDef");
                    }
                } catch (Exception $e) {
                    // Ignore
                }
            }

            // Create a new invitation for the job application
            $invStmt = $conn->prepare("
                INSERT INTO invitations (sender_id, receiver_id, work_type, work_email, work_details, job_id, application_id, status) 
                VALUES (:sender, :receiver, :work_type, :work_email, :work_details, :job_id, :application_id, 'pending')
            ");
            $invStmt->execute([
                ':sender' => $user_id,
                ':receiver' => $job['client_id'],
                ':work_type' => 'Job Application',
                ':work_email' => $developerEmail,
                ':work_details' => $workDetails,
                ':job_id' => $job_id,
                ':application_id' => $applicationId
            ]);
        }

        // Create notification for the client
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'application', ?, ?, ?)");
        $notifTitle = "New Application Received";
        $notifMessage = "{$developerName} has applied for your job: {$job['job_title']}";
        $notifLink = "messages.php"; // Redirect to messages where they can see and respond
        $notifStmt->execute([$job['client_id'], $notifTitle, $notifMessage, $notifLink]);

        echo json_encode([
            'success' => true,
            'message' => 'Application submitted successfully! The job poster will receive your application in their messages.',
            'application_id' => $applicationId
        ]);
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred while processing your application.']);
    } catch (Exception $e) {
        error_log("Application Error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    exit();
}

// Handle GET request - Get developer profile for popup
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $user_id = $_SESSION['user_id'];

        // Get developer profile
        $stmt = $conn->prepare("
            SELECT d.*, r.Name, r.Email, r.image as profile_image 
            FROM developers d 
            JOIN register r ON d.user_id = r.id 
            WHERE d.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $developer = $stmt->fetch();

        if (!$developer) {
            // Return basic user info if no developer profile
            $userStmt = $conn->prepare("SELECT Name, Email, image FROM register WHERE id = ?");
            $userStmt->execute([$user_id]);
            $user = $userStmt->fetch();

            echo json_encode([
                'success' => true,
                'has_profile' => false,
                'developer' => [
                    'name' => $user['Name'] ?? '',
                    'email' => $user['Email'] ?? '',
                    'profile_image' => $user['image'] ?? ''
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'has_profile' => true,
                'developer' => [
                    'name' => $developer['Name'] ?? '',
                    'email' => $developer['Email'] ?? '',
                    'title' => $developer['title'] ?? '',
                    'rate' => $developer['rate'] ?? 0,
                    'skills' => $developer['skills'] ?? '',
                    'experience' => $developer['years_experience'] ?? '',
                    'bio' => $developer['bio'] ?? '',
                    'profile_image' => $developer['profile_image'] ?? $developer['image_path'] ?? '',
                    'portfolio_images' => $developer['portfolio_images'] ?? ''
                ]
            ]);
        }
    } catch (Exception $e) {
        error_log("Profile Fetch Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load profile data.']);
    }
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
