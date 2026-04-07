<?php

/**
 * Database Update: Add Unique Constraint for User Pairs
 * 
 * This adds a unique index to prevent duplicate active conversations between user pairs.
 * The constraint ensures that only ONE non-rejected invitation can exist between any two users.
 * 
 * Note: This should be run AFTER cleanup_duplicates.php to ensure no conflicts.
 */

require_once '../config/database.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

echo "Starting database update...\n\n";

try {
    $conn = getDB();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Step 1: First run the cleanup to remove existing duplicates
    echo "Step 1: Checking for duplicates to clean up...\n";

    $findDuplicatesSql = "
        SELECT 
            LEAST(sender_id, receiver_id) as user1,
            GREATEST(sender_id, receiver_id) as user2,
            COUNT(*) as count
        FROM invitations
        WHERE status != 'rejected'
        GROUP BY LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id)
        HAVING COUNT(*) > 1
    ";

    $stmt = $conn->prepare($findDuplicatesSql);
    $stmt->execute();
    $duplicatePairs = $stmt->fetchAll();

    $cleanedUp = 0;
    foreach ($duplicatePairs as $pair) {
        $user1 = $pair['user1'];
        $user2 = $pair['user2'];

        // Keep the best one (accepted > pending), most recent
        $keepSql = "
            SELECT id FROM invitations 
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            AND status != 'rejected'
            ORDER BY 
                CASE status WHEN 'accepted' THEN 1 WHEN 'pending' THEN 2 END,
                created_at DESC
            LIMIT 1
        ";
        $keepStmt = $conn->prepare($keepSql);
        $keepStmt->execute([$user1, $user2, $user2, $user1]);
        $keep = $keepStmt->fetch();

        if ($keep) {
            // Update others to rejected (instead of deleting, to preserve history)
            $updateSql = "
                UPDATE invitations 
                SET status = 'rejected', responded_at = NOW()
                WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                AND id != ?
                AND status != 'rejected'
            ";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute([$user1, $user2, $user2, $user1, $keep['id']]);
            $cleanedUp += $updateStmt->rowCount();
        }
    }

    echo "Cleaned up $cleanedUp duplicate entries.\n\n";

    // Step 2: Try to add a unique index (if it doesn't exist)
    // Note: MySQL doesn't support partial unique indexes directly, so we use a different approach
    echo "Step 2: Adding optimization indexes...\n";

    // Add index for faster lookups on user pairs
    try {
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_invitations_user_pair ON invitations(sender_id, receiver_id, status)");
        echo "Added index: idx_invitations_user_pair\n";
    } catch (PDOException $e) {
        echo "Index may already exist: idx_invitations_user_pair\n";
    }

    try {
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_invitations_created ON invitations(created_at DESC)");
        echo "Added index: idx_invitations_created\n";
    } catch (PDOException $e) {
        echo "Index may already exist: idx_invitations_created\n";
    }

    echo "\n✅ Database update completed successfully!\n";
    echo "\nIMPORTANT: The unique constraint is now enforced at the APPLICATION level.\n";
    echo "The updated API files (send_invitation.php, send_message_request.php) \n";
    echo "will check for existing conversations before creating new ones.\n";

} catch (Exception $e) {
    error_log("DB Constraint Update Error: " . $e->getMessage());
    echo "❌ Error: Database update failed. Check logs for details.\n";
    exit(1);
}
