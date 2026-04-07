<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

require_once __DIR__ . '/../includes/db_helper.php';

try {
    require_once __DIR__ . '/../includes/db_helper.php';
$conn = getDB();

    // Fetch all active sessions for this user
    $stmt = $conn->prepare("SELECT id, device_type, user_agent, ip_address, created_at, session_token FROM user_sessions WHERE user_id = :uid ORDER BY created_at DESC");
    $stmt->execute([':uid' => $_SESSION['user_id']]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process to mark current session
    $devices = [];
    $currentToken = $_SESSION['session_token'] ?? '';

    foreach ($sessions as $session) {
        // Parse User Agent for better display
        $browser = 'Unknown Browser';
        $os = 'Unknown OS';

        $ua = $session['user_agent'];

        // Simple UA parsing
        if (strpos($ua, 'Chrome') !== false)
            $browser = 'Chrome';
        elseif (strpos($ua, 'Firefox') !== false)
            $browser = 'Firefox';
        elseif (strpos($ua, 'Safari') !== false)
            $browser = 'Safari';
        elseif (strpos($ua, 'Edge') !== false)
            $browser = 'Edge';

        if (strpos($ua, 'Windows') !== false)
            $os = 'Windows';
        elseif (strpos($ua, 'Macintosh') !== false)
            $os = 'macOS';
        elseif (strpos($ua, 'Linux') !== false)
            $os = 'Linux';
        elseif (strpos($ua, 'Android') !== false)
            $os = 'Android';
        elseif (strpos($ua, 'iPhone') !== false)
            $os = 'iOS';

        $devices[] = [
            'id' => $session['id'],
            'name' => $os . ' Device', // Fallback name
            'description' => "$browser on $os • " . $session['ip_address'],
            'device_type' => $session['device_type'],
            'is_current' => ($session['session_token'] === $currentToken)
        ];
    }

    echo json_encode(['success' => true, 'devices' => $devices]);

} catch (Exception $e) {
    error_log("Get Devices Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to load devices.']);
}
?>