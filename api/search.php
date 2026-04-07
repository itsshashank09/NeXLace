<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['name']) || empty($_SESSION['name'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(['results' => [], 'total' => 0]);
    exit();
}

require_once __DIR__ . '/../includes/db_helper.php';
$conn = getDB();

if (!$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$results = [
    'jobs' => [],
    'developers' => [],
    'users' => []
];

$searchParam = '%' . $query . '%';

try {
    // ===== SEARCH JOBS =====
    $jobStmt = $conn->prepare("
        SELECT id, job_title, job_details, skills_required, estimated_budget, 
               project_timeline, category, project_type, experience_level, created_at
        FROM post_jobs 
        WHERE job_title LIKE :q1 
           OR job_details LIKE :q2 
           OR skills_required LIKE :q3 
           OR category LIKE :q4
        ORDER BY 
            CASE 
                WHEN job_title LIKE :q5 THEN 1
                WHEN skills_required LIKE :q6 THEN 2
                WHEN category LIKE :q7 THEN 3
                ELSE 4
            END,
            created_at DESC
        LIMIT 8
    ");
    $jobStmt->execute([
        ':q1' => $searchParam,
        ':q2' => $searchParam,
        ':q3' => $searchParam,
        ':q4' => $searchParam,
        ':q5' => $searchParam,
        ':q6' => $searchParam,
        ':q7' => $searchParam
    ]);
    $jobResults = $jobStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($jobResults as $job) {
        $skills = array_filter(array_map('trim', explode(',', $job['skills_required'] ?? '')));
        $results['jobs'][] = [
            'id' => $job['id'],
            'title' => $job['job_title'],
            'description' => mb_substr($job['job_details'], 0, 120) . '...',
            'budget' => '₹' . number_format($job['estimated_budget']),
            'timeline' => $job['project_timeline'] ?? 'Flexible',
            'category' => $job['category'] ?? 'General',
            'project_type' => $job['project_type'] ?? 'Fixed Price',
            'skills' => array_slice($skills, 0, 4),
            'url' => 'findwork.php?q=' . urlencode($query)
        ];
    }

    // ===== SEARCH DEVELOPERS =====
    $devStmt = $conn->prepare("
        SELECT d.id, d.user_id, d.title, d.skills, d.rate, d.bio, 
               d.location, d.availability, d.image_path,
               r.Name, r.Email
        FROM developers d
        JOIN register r ON d.user_id = r.id
        WHERE r.Name LIKE :q1 
           OR d.title LIKE :q2 
           OR d.skills LIKE :q3 
           OR d.bio LIKE :q4
           OR d.location LIKE :q5
        ORDER BY 
            CASE 
                WHEN r.Name LIKE :q6 THEN 1 
                WHEN d.title LIKE :q7 THEN 2 
                WHEN d.skills LIKE :q8 THEN 3 
                ELSE 4 
            END,
            d.created_at DESC
        LIMIT 6
    ");
    $devStmt->execute([
        ':q1' => $searchParam,
        ':q2' => $searchParam,
        ':q3' => $searchParam,
        ':q4' => $searchParam,
        ':q5' => $searchParam,
        ':q6' => $searchParam,
        ':q7' => $searchParam,
        ':q8' => $searchParam
    ]);
    $devResults = $devStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($devResults as $dev) {
        $devName = $dev['Name'] ?? 'Developer';
        $nameParts = explode(' ', $devName);
        $displayName = $nameParts[0] . (isset($nameParts[1]) ? ' ' . strtoupper($nameParts[1][0]) . '.' : '');
        $skills = array_filter(array_map('trim', explode(',', $dev['skills'] ?? '')));

        $results['developers'][] = [
            'id' => $dev['id'],
            'user_id' => $dev['user_id'],
            'name' => $displayName,
            'full_name' => $devName,
            'title' => $dev['title'] ?? 'Developer',
            'rate' => '₹' . number_format($dev['rate'] ?? 0) . '/hr',
            'location' => $dev['location'] ?? 'Remote',
            'image' => $dev['image_path'] ?? '',
            'skills' => array_slice($skills, 0, 3),
            'url' => 'devprofiles.php?id=' . $dev['id']
        ];
    }

    // ===== SEARCH USERS (people) =====
    $userStmt = $conn->prepare("
        SELECT id, Name, Email, `Professional Headline`, image
        FROM register 
        WHERE Name LIKE :q1 
           OR Email LIKE :q2 
           OR `Professional Headline` LIKE :q3
        ORDER BY 
            CASE WHEN Name LIKE :q4 THEN 1 ELSE 2 END
        LIMIT 5
    ");
    $userStmt->execute([
        ':q1' => $searchParam,
        ':q2' => $searchParam,
        ':q3' => $searchParam,
        ':q4' => $searchParam
    ]);
    $userResults = $userStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($userResults as $user) {
        $results['users'][] = [
            'id' => $user['id'],
            'name' => $user['Name'],
            'headline' => $user['Professional Headline'] ?? '',
            'image' => $user['image'] ?? '',
            'url' => 'devprofiles.php?user_id=' . $user['id']
        ];
    }

} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
    echo json_encode(['error' => 'Search failed']);
    exit();
}

$total = count($results['jobs']) + count($results['developers']) + count($results['users']);

echo json_encode([
    'results' => $results,
    'total' => $total,
    'query' => $query
]);
