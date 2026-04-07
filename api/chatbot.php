<?php
/**
 * NeXLace Chatbot API Proxy
 * 
 * This endpoint receives user messages, forwards them to the Gemini API
 * with the NeXLace system prompt, and returns the AI response.
 * 
 * The API key is NEVER sent to the frontend.
 */

session_start();

// CORS Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Must be logged in
if (!isset($_SESSION['name']) || empty($_SESSION['name'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in to use the chatbot']);
    exit();
}

// Load config
require_once __DIR__ . '/../config/gemini_config.php';

// ========== RATE LIMITING ==========
$userId = $_SESSION['user_id'] ?? session_id();
$rateLimitFile = sys_get_temp_dir() . '/nexlace_chatbot_' . md5($userId) . '.json';

function checkRateLimit($file)
{
    $now = time();
    $data = ['requests' => [], 'daily_count' => 0, 'daily_reset' => $now];

    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: $data;
    }

    // Reset daily counter if new day
    if ($now - ($data['daily_reset'] ?? 0) > 86400) {
        $data['daily_count'] = 0;
        $data['daily_reset'] = $now;
    }

    // Remove requests older than 60 seconds
    $data['requests'] = array_filter($data['requests'] ?? [], function ($timestamp) use ($now) {
        return ($now - $timestamp) < 60;
    });

    // Check per-minute limit
    if (count($data['requests']) >= CHATBOT_MAX_REQUESTS_PER_MINUTE) {
        return ['allowed' => false, 'reason' => 'Too many requests. Please wait a moment before sending another message.'];
    }

    // Check daily limit
    if (($data['daily_count'] ?? 0) >= CHATBOT_MAX_REQUESTS_PER_DAY) {
        return ['allowed' => false, 'reason' => 'Daily message limit reached. Please try again tomorrow.'];
    }

    // Record this request
    $data['requests'][] = $now;
    $data['daily_count'] = ($data['daily_count'] ?? 0) + 1;
    file_put_contents($file, json_encode($data));

    return ['allowed' => true];
}

$rateCheck = checkRateLimit($rateLimitFile);
if (!$rateCheck['allowed']) {
    http_response_code(429);
    echo json_encode(['error' => $rateCheck['reason']]);
    exit();
}

// ========== PARSE INPUT ==========
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['message']) || empty(trim($input['message']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit();
}

$userMessage = trim($input['message']);
$conversationHistory = $input['history'] ?? [];

// Validate message length
if (strlen($userMessage) > CHATBOT_MAX_MESSAGE_LENGTH) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is too long. Please keep it under ' . CHATBOT_MAX_MESSAGE_LENGTH . ' characters.']);
    exit();
}

// Limit conversation history length
if (count($conversationHistory) > CHATBOT_MAX_HISTORY_LENGTH * 2) {
    $conversationHistory = array_slice($conversationHistory, -CHATBOT_MAX_HISTORY_LENGTH * 2);
}

// ========== BUILD GEMINI REQUEST ==========

// Build contents array with conversation history
$contents = [];

// Add conversation history
foreach ($conversationHistory as $msg) {
    $role = ($msg['role'] === 'user') ? 'user' : 'model';
    $contents[] = [
        'role' => $role,
        'parts' => [['text' => $msg['text']]]
    ];
}

// Add current user message
$contents[] = [
    'role' => 'user',
    'parts' => [['text' => $userMessage]]
];

// Add user context to the system prompt
$userName = $_SESSION['name'] ?? 'User';
$userEmail = $_SESSION['email'] ?? '';
$contextNote = "\n\n[CONTEXT: The current user's name is \"{$userName}\". Greet them by name when appropriate. Current page context may vary.]";

$requestBody = [
    'system_instruction' => [
        'parts' => [['text' => NEXLACE_SYSTEM_PROMPT . $contextNote]]
    ],
    'contents' => $contents,
    'generationConfig' => [
        'temperature' => 0.7,
        'topP' => 0.9,
        'topK' => 40,
        'maxOutputTokens' => 1024,
    ],
    'safetySettings' => [
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
    ]
];

// ========== CALL GEMINI API ==========
$ch = curl_init(GEMINI_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($requestBody),
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Handle cURL errors
if ($curlError) {
    error_log("Chatbot cURL error: " . $curlError);
    http_response_code(502);
    echo json_encode(['error' => 'Unable to connect to AI service. Please try again later.']);
    exit();
}

// Handle API errors
if ($httpCode !== 200) {
    error_log("Chatbot API error (HTTP {$httpCode}): " . $response);

    $errorData = json_decode($response, true);
    $errorMessage = 'AI service temporarily unavailable. Please try again later.';

    if ($httpCode === 429) {
        $errorMessage = 'AI service is busy. Please wait a few seconds and try again.';
    } elseif ($httpCode === 400) {
        $errorMessage = 'There was an issue processing your message. Please try rephrasing.';
    }

    http_response_code(502);
    echo json_encode(['error' => $errorMessage]);
    exit();
}

// ========== PARSE RESPONSE ==========
$responseData = json_decode($response, true);

if (!$responseData || !isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    // Check if blocked by safety
    if (
        isset($responseData['candidates'][0]['finishReason']) &&
        $responseData['candidates'][0]['finishReason'] === 'SAFETY'
    ) {
        echo json_encode([
            'reply' => "I can't respond to that type of message. Let me know if you have questions about NeXLace or coding — I'm happy to help! 😊"
        ]);
        exit();
    }

    error_log("Chatbot: Unexpected response structure: " . $response);
    http_response_code(502);
    echo json_encode(['error' => 'Received an unexpected response. Please try again.']);
    exit();
}

$botReply = $responseData['candidates'][0]['content']['parts'][0]['text'];

// Return success
echo json_encode([
    'reply' => $botReply,
    'timestamp' => time()
]);
