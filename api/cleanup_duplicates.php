<?php

/**
 * API: Cleanup Duplicate Conversations
 * 
 * This script removes duplicate invitations/messages between the same user pairs,
 * keeping only the most recent one for each unique user-developer pair.
 * 
 * IMPORTANT: Run this once to clean up existing duplicate data.
 * After cleanup, the updated API files will prevent new duplicates from being created.
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow logged-in users to run this (or you can restrict to admin only)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $conn = getDB();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $cleanupResults = [
        'duplicates_found' => 0,
        'duplicates_removed' => 0,
        'user_pairs_affected' => 0
    ];

    // Find all unique user pairs with multiple invitations
    $findDuplicatesSql = "
        SELECT 
            LEAST(sender_id, receiver_id) as user1,
            GREATEST(sender_id, receiver_id) as user2,
            COUNT(*) as count
        FROM invitations
        GROUP BY LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id)
        HAVING COUNT(*) > 1
    ";

    $stmt = $conn->prepare($findDuplicatesSql);
    $stmt->execute();
    $duplicatePairs = $stmt->fetchAll();

    $cleanupResults['user_pairs_affected'] = count($duplicatePairs);

    foreach ($duplicatePairs as $pair) {
        $user1 = $pair['user1'];
        $user2 = $pair['user2'];
        $count = $pair['count'];

        $cleanupResults['duplicates_found'] += ($count - 1); // All but one are duplicates

        // Prioritize keeping: accepted > pending > rejected
        // Within same status, keep the most recent one
        $keepInvitationSql = "
            SELECT id FROM invitations 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
            ORDER BY 
                CASE status 
                    WHEN 'accepted' THEN 1 
                    WHEN 'pending' THEN 2 
                    WHEN 'rejected' THEN 3 
                END,
                created_at DESC
            LIMIT 1
        ";
        $keepStmt = $conn->prepare($keepInvitationSql);
        $keepStmt->execute([$user1, $user2, $user2, $user1]);
        $keepInvitation = $keepStmt->fetch();

        if ($keepInvitation) {
            // Delete all other invitations for this user pair
            $deleteSql = "
                DELETE FROM invitations 
                WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                AND id != ?
            ";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->execute([$user1, $user2, $user2, $user1, $keepInvitation['id']]);

            $cleanupResults['duplicates_removed'] += $deleteStmt->rowCount();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Duplicate cleanup completed',
        'results' => $cleanupResults
    ]);

} catch (Exception $e) {
    error_log("Cleanup Duplicates Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred during cleanup.']);
}
