<?php
/**
 * SSE (Server-Sent Events) stream for real-time notifications.
 *
 * Events emitted:
 *   - "init"         : Full notification list + unread count on first connect.
 *   - "notification"  : A single new notification arrives.
 *   - "count"         : Updated unread count (sent periodically).
 *
 * Query params:
 *   - last_id : (int) only send notifications newer than this ID.
 */

session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];

// Release session lock immediately so other requests aren't blocked
session_write_close();

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db_helper.php';

$lastNotificationId = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;
$isFirstRun = true;

// Keep connection alive
set_time_limit(0);
ignore_user_abort(false);

// ───────────────────── helpers ─────────────────────

function formatTimeAgo(string $createdAt): string
{
    $created = new DateTime($createdAt);
    $now = new DateTime();
    $diff = $now->diff($created);

    if ($diff->days == 0) {
        if ($diff->h == 0) {
            return $diff->i <= 1 ? 'Just now' : $diff->i . ' minutes ago';
        }
        return $diff->h == 1 ? '1 hour ago' : $diff->h . ' hours ago';
    }
    if ($diff->days == 1)
        return '1 day ago';
    return $diff->days . ' days ago';
}

function sendEvent(string $event, $data, ?int $id = null): void
{
    if ($id !== null) {
        echo "id: {$id}\n";
    }
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";

    if (ob_get_level())
        ob_flush();
    flush();
}

function getUnreadCount(PDO $conn, int $userId): int
{
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
}

// ───────────────────── main loop ─────────────────────

while (true) {
    if (connection_aborted())
        break;

    try {
        $conn = getDB();
        if (!$conn)
            break;

        // On first run send the full, current state so the client can
        // hydrate without a separate fetch.
        if ($isFirstRun) {
            $stmt = $conn->prepare("
                SELECT id, title, message, type, is_read, link, created_at
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$userId]);
            $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $formatted = array_map(function ($n) {
                $n['time_ago'] = formatTimeAgo($n['created_at']);
                $n['id'] = (int) $n['id'];
                $n['is_read'] = (bool) $n['is_read'];
                return $n;
            }, $all);

            $unread = getUnreadCount($conn, $userId);

            sendEvent('init', [
                'notifications' => $formatted,
                'unread_count' => $unread,
            ]);

            // Track the highest id we've sent
            if (!empty($all)) {
                $lastNotificationId = max(array_column($all, 'id'));
            }

            $isFirstRun = false;
            sleep(3);
            continue;
        }

        // Check for new notifications since last check
        $stmt = $conn->prepare("
            SELECT id, title, message, type, is_read, link, created_at
            FROM notifications
            WHERE user_id = ? AND id > ?
            ORDER BY id ASC
        ");
        $stmt->execute([$userId, $lastNotificationId]);
        $newNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($newNotifications)) {
            foreach ($newNotifications as $notif) {
                $notif['time_ago'] = formatTimeAgo($notif['created_at']);
                $notif['id'] = (int) $notif['id'];
                $notif['is_read'] = (bool) $notif['is_read'];

                sendEvent('notification', $notif, $notif['id']);
                $lastNotificationId = $notif['id'];
            }
        }

        // Always send the latest unread count so header badge stays in sync
        $unread = getUnreadCount($conn, $userId);
        sendEvent('count', ['unread_count' => $unread]);

        // Heartbeat to keep connection alive
        echo ": heartbeat\n\n";
        if (ob_get_level())
            ob_flush();
        flush();

    } catch (Exception $e) {
        error_log("SSE Error: " . $e->getMessage());
        // Send error event so client can decide to reconnect
        sendEvent('error', ['message' => 'Server error, reconnecting...']);
        break;
    }

    sleep(5);
}
?>